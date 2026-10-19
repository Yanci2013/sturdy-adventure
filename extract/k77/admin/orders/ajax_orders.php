<?php
// 必须是文件的绝对第一行，前面不能有任何空格/换行
ob_start();  // 开启输出缓冲
header('Content-Type: application/json');

// 强制清除所有可能的前置输出
ob_clean();

// 引入数据库连接文件
require_once __DIR__.'/../../includes/common.php';

// 获取订单ID和操作
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if (!$order_id || !in_array($action, ['approve', 'reject'])) {
    die(json_encode(['success' => false, 'message' => '参数错误']));
}

try {
    // 开始事务处理
    $pdo->beginTransaction();

    // 获取订单当前状态和金额
    $stmt = $pdo->prepare("SELECT o.*, u.invitation_code 
                           FROM orders o 
                           LEFT JOIN users u ON o.hunter_user_id = u.id 
                           WHERE o.id = ?");
    $stmt->execute([$order_id]);
    
    if ($stmt->rowCount() == 0) {
        throw new Exception('订单不存在');
    }

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    // 检查订单状态是否为待审核(3)
    if ($order['order_status'] != 3) {
        throw new Exception('订单状态不正确');
    }

    // 根据操作更新订单状态
    $new_status = $action === 'approve' ? 4 : 5; // 4=已完成, 5=关闭中
    $review_time = date('Y-m-d H:i:s');

    // 更新订单状态
    $stmt = $pdo->prepare("UPDATE orders SET order_status = ?, review_time = ? WHERE id = ?");
    
    if (!$stmt->execute([$new_status, $review_time, $order_id])) {
        throw new Exception('订单状态更新失败');
    }

    // 如果是审核通过，处理佣金分配
    if ($action === 'approve') {
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
        if (abs($total_rate - 1) > 0.0001) {  // 允许微小的误差
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
            // 更新打手余额
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            if (!$stmt->execute([$hunter_amount, $order['hunter_user_id']])) {
                throw new Exception('打手佣金分配失败');
            }
            
            // 记录资金明细
            $stmt = $pdo->prepare("INSERT INTO fund_details 
                                  (user_id, source, destination, amount, related_id, note, order_no) 
                                  VALUES (?, 'order_commission', 'user_balance', ?, ?, '订单佣金', ?)");
            if (!$stmt->execute([$order['hunter_user_id'], $hunter_amount, $order_id, $order['order_no']])) {
                throw new Exception('打手佣金记录失败');
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
    }
    
    // 提交事务
    $pdo->commit();
    
    // 返回成功响应
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => '操作成功',
        'commission_distributed' => $action === 'approve'
    ]);
    
} catch (PDOException $e) {
    // 回滚事务
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // 返回错误响应
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '数据库错误: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    // 回滚事务
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // 返回错误响应
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>