<?php
ini_set('default_charset', 'utf-8');
ob_start();
header('Content-Type: application/json; charset=utf-8');

// 引入数据库连接文件
require_once __DIR__ . '/../../includes/session_manager.php';
require_once __DIR__ . '/../../includes/common.php';
require_once __DIR__ . '/../../includes/log_helper.php';

// 强制设置数据库字符集
$pdo->exec("SET NAMES utf8mb4");
$pdo->exec("SET CHARACTER SET utf8mb4");

// 确保PDO连接可用
if (!isset($pdo) || !$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['success' => false, 'msg' => '数据库连接失败'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 检查管理员登录
if (!is_admin_logged_in()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'msg' => '未登录或登录已过期'], JSON_UNESCAPED_UNICODE);
    exit;
}

$admin_id = $_SESSION['admin_id'] ?? null;
$admin_name = $_SESSION['admin_username'] ?? '未知管理员';

// 验证订单ID参数
if (!isset($_POST['order_id']) || !is_numeric($_POST['order_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'msg' => '无效的订单ID'], JSON_UNESCAPED_UNICODE);
    exit;
}
$order_id = intval($_POST['order_id']);

try {
    // 开始事务
    $pdo->beginTransaction();

    // 获取订单信息并锁定记录
    $stmt = $pdo->prepare("SELECT o.*, u.invitation_code 
                           FROM orders o 
                           LEFT JOIN users u ON o.hunter_user_id = u.id 
                           WHERE o.id = ? FOR UPDATE");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'msg' => '订单不存在'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 检查订单状态是否为待结算
    if ($order['order_status'] != 3) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'msg' => '订单状态不是待结算，无法结算'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 1. 获取佣金比例设置
    $stmt = $pdo->query("SELECT 
                        hunter_commission_rate, 
                        admin_commission_rate, 
                        manager_commission_rate 
                        FROM settings LIMIT 1");
    
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$settings) {
        throw new Exception('佣金设置未找到');
    }
    
    $hunter_rate = $settings['hunter_commission_rate'] / 100;
    $admin_rate = $settings['admin_commission_rate'] / 100;
    $manager_rate = $settings['manager_commission_rate'] / 100;

    // 验证佣金比例总和为100%（考虑浮点数精度）
    $total_rate = $hunter_rate + $admin_rate + $manager_rate;
    if (abs($total_rate - 1) > 0.0001) {
        throw new Exception('佣金比例设置错误，总和必须为100%');
    }
    
    $order_amount = $order['price']; // 订单金额
    
    // 2. 计算各角色佣金
    $hunter_amount = $order_amount * $hunter_rate;
    $admin_amount = $order_amount * $admin_rate;
    $manager_amount = $order_amount * $manager_rate;
    
    // 3. 查找管事ID（通过打手的邀请码）
    $manager_id = null;
    if (!empty($order['invitation_code'])) {
        $stmt = $pdo->prepare("SELECT generator_id 
                             FROM invitations 
                             WHERE code = ? AND generator_type = 'partner'");
        $stmt->execute([$order['invitation_code']]);
        
        if ($stmt->rowCount() > 0) {
            $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
            $manager_id = $invitation['generator_id'];
        }
    }
    
    // 4. 分配佣金到打手账户
    if ($order['hunter_user_id']) {
        // 检查是否有共享记录
        $shareStmt = $pdo->prepare("SELECT * FROM order_shares WHERE order_id = ?");
        $shareStmt->execute([$order_id]);
        $shareRecords = $shareStmt->fetchAll(PDO::FETCH_ASSOC);

        $shareCount = count($shareRecords) + 1; // +1 是订单所有者
        $perPersonAmount = $hunter_amount / $shareCount;

        if ($shareCount > 1) {
            // 记录分成信息到分成记录表
            $profitStmt = $pdo->prepare("INSERT INTO order_share_profits (order_id, user_id, profit_amount, share_count, per_person_amount) VALUES (?, ?, ?, ?, ?)");
            $profitStmt->execute([$order_id, $order['hunter_user_id'], $perPersonAmount, $shareCount, $perPersonAmount]);

            // 分配给订单所有者
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            if (!$stmt->execute([$perPersonAmount, $order['hunter_user_id']])) {
                throw new Exception('打手佣金分配失败');
            }

            // 记录资金明细
            $stmt = $pdo->prepare("INSERT INTO fund_details (user_id, source, destination, amount, related_id, note, order_no) VALUES (?, 'order_commission', 'user_balance', ?, ?, '订单佣金(共享分成)', ?)");
            if (!$stmt->execute([$order['hunter_user_id'], $perPersonAmount, $order_id, $order['order_no']])) {
                throw new Exception('打手佣金记录失败');
            }

            // 分配给每个被共享的用户
            foreach ($shareRecords as $share) {
                $profitStmt->execute([$order_id, $share['shared_user_id'], $perPersonAmount, $shareCount, $perPersonAmount]);

                $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                if (!$stmt->execute([$perPersonAmount, $share['shared_user_id']])) {
                    throw new Exception('共享用户佣金分配失败');
                }

                // 记录资金明细
                $stmt = $pdo->prepare("INSERT INTO fund_details (user_id, source, destination, amount, related_id, note, order_no) VALUES (?, 'order_commission', 'user_balance', ?, ?, '订单共享分成', ?)");
                if (!$stmt->execute([$share['shared_user_id'], $perPersonAmount, $order_id, $order['order_no']])) {
                    throw new Exception('共享用户佣金记录失败');
                }
            }
        } else {
            // 没有共享，正常分配全部佣金
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            if (!$stmt->execute([$hunter_amount, $order['hunter_user_id']])) {
                throw new Exception('打手佣金分配失败');
            }

            // 记录资金明细
            $stmt = $pdo->prepare("INSERT INTO fund_details (user_id, source, destination, amount, related_id, note, order_no) VALUES (?, 'order_commission', 'user_balance', ?, ?, '订单佣金', ?)");
            if (!$stmt->execute([$order['hunter_user_id'], $hunter_amount, $order_id, $order['order_no']])) {
                throw new Exception('打手佣金记录失败');
            }
        }
    }
    
    // 5. 分配佣金到管事账户（如果有）
    if ($manager_id) {
        // 更新管事余额
        $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        if (!$stmt->execute([$manager_amount, $manager_id])) {
            throw new Exception('管事佣金分配失败');
        }
        
        // 记录资金明细
        $stmt = $pdo->prepare("INSERT INTO fund_details 
                              (user_id, source, destination, amount, related_id, note, order_no) 
                              VALUES (?, 'order_commission', 'user_balance', ?, ?, '邀请打手订单佣金', ?)");
        if (!$stmt->execute([$manager_id, $manager_amount, $order_id, $order['order_no']])) {
            throw new Exception('管事佣金记录失败');
        }
    }
    
    // 6. 记录管理员佣金（不分配余额，只记录）
    $stmt = $pdo->prepare("INSERT INTO fund_details 
                          (source, destination, amount, related_id, note, order_no) 
                          VALUES ('order_commission', 'system_income', ?, ?, '平台佣金', ?)");
    if (!$stmt->execute([$admin_amount, $order_id, $order['order_no']])) {
        throw new Exception('平台佣金记录失败');
    }

    // 7. 更新订单状态为已完成
    $review_time = date('Y-m-d H:i:s');
    $updateStmt = $pdo->prepare("UPDATE orders SET order_status = 4, review_time = ?, update_time = NOW() WHERE id = ?");
    if (!$updateStmt->execute([$review_time, $order_id])) {
        throw new Exception('订单状态更新失败');
    }

    // 记录订单日志
    log_order_action(
        $pdo,
        $order_id,
        $order['order_no'],
        'settle',
        '订单已结算完成，佣金已发放',
        $admin_id,
        $admin_name,
        'admin',
        null,
        null,
        null
    );

    // 记录管理员操作日志
    log_admin_operation(
        $pdo,
        $admin_id,
        $admin_name,
        'settle_order',
        '管理员结算了订单：ID=' . $order_id . ', 订单号=' . $order['order_no'] . ', 金额=' . $order_amount
    );

    // 提交事务
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'msg' => '订单结算成功，佣金已发放',
        'commission' => [
            'hunter' => round($perPersonAmount, 2),
            'manager' => round($manager_amount, 2),
            'admin' => round($admin_amount, 2),
            'share_count' => $shareCount ?? 1,
            'share_info' => $shareCount > 1 ? '订单已共享给' . ($shareCount - 1) . '人，每人分得' . round($perPersonAmount, 2) . '元' : '无共享'
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'msg' => '数据库错误: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
