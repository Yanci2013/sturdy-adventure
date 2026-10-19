<?php
require_once 'header.php';

// 检查商家是否登录
if (empty($_SESSION['partner_info'])) {
    $_SESSION['error_msg'] = "请先登录商家账号";
    header("Location: login.php");
    exit;
}

// 验证订单ID
$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if (!$order_id) {
    $_SESSION['error_msg'] = "无效的订单ID";
    header("Location: order_review.php");
    exit;
}

try {
    // 获取订单信息 - 确保订单属于当前商家且状态为待审核(3)
    $sql = "SELECT o.*, 
            u.id as hunter_user_id,
            u.invitation_code,
            u.balance as hunter_balance
            FROM orders o 
            LEFT JOIN users u ON o.hunter_user_id = u.id 
            WHERE o.id = :order_id AND o.fadan_id = :partner_id AND o.order_status = 3";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    $stmt->bindParam(':partner_id', $_SESSION['partner_info']['id'], PDO::PARAM_STR);
    $stmt->execute();

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $_SESSION['error_msg'] = "订单不存在、无权操作或状态不正确";
        header("Location: order_review.php");
        exit;
    }

    // 开始事务处理
    $pdo->beginTransaction();

    // 1. 更新订单状态为已完成(4)
    $update_sql = "UPDATE orders SET 
                  order_status = 4, 
                  review_time = NOW(),
                  complete_time = NOW()
                  WHERE id = :order_id";
    
    $stmt = $pdo->prepare($update_sql);
    $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    $stmt->execute();

    // 2. 获取佣金比例设置
    $settings_stmt = $pdo->query("SELECT 
                                hunter_commission_rate, 
                                admin_commission_rate, 
                                manager_commission_rate
                                FROM settings LIMIT 1");
    $settings = $settings_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings) {
        throw new RuntimeException('佣金设置未找到');
    }
    
    $hunter_rate = $settings['hunter_commission_rate'] / 100;
    $admin_rate = $settings['admin_commission_rate'] / 100;
    $manager_rate = $settings['manager_commission_rate'] / 100;
    
    $order_amount = $order['price']; // 订单金额
    
    // 3. 计算各角色佣金
    $hunter_amount = round($order_amount * $hunter_rate, 2);
    $admin_amount = round($order_amount * $admin_rate, 2);
    $manager_amount = round($order_amount * $manager_rate, 2);
    
    // 4. 查找管事ID（通过partner_service_relations表查找邀请关系）
    $manager_id = null;
    if ($order['hunter_user_id']) {
        $stmt = $pdo->prepare("SELECT inviter_id 
                             FROM partner_service_relations 
                             WHERE invitee_id = :invitee_id 
                               AND invitee_type = 'fighter' 
                               AND inviter_type = 'eadmin'");
        $stmt->bindParam(':invitee_id', $order['hunter_user_id'], PDO::PARAM_INT);
        $stmt->execute();
        
        $relation = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($relation) {
            $manager_id = $relation['inviter_id'];
        }
    }
    
    // 5. 分配佣金到打手账户
    if ($order['hunter_user_id']) {
        // 更新打手余额
        $stmt = $pdo->prepare("UPDATE users SET balance = balance + :amount WHERE id = :user_id");
        $stmt->bindParam(':amount', $hunter_amount, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $order['hunter_user_id'], PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('打手佣金分配失败');
        }
        
        // 记录资金明细
        $stmt = $pdo->prepare("INSERT INTO fund_details 
                            (user_id, source, destination, amount, related_id, note, order_no) 
                            VALUES (:user_id, 'order_commission', 'user_balance', :amount, :order_id, '订单佣金', :order_no)");
        $stmt->bindParam(':user_id', $order['hunter_user_id'], PDO::PARAM_INT);
        $stmt->bindParam(':amount', $hunter_amount, PDO::PARAM_STR);
        $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
        $stmt->bindParam(':order_no', $order['order_no'], PDO::PARAM_STR);
        $stmt->execute();
    }
    
    // 6. 分配佣金到管事账户（如果有）
    if ($manager_id && $manager_amount > 0) {
        // 先检查管事是否存在
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE id = :user_id");
        $checkStmt->bindParam(':user_id', $manager_id, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if ($checkStmt->fetch()) {
            // 更新管事余额
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + :amount WHERE id = :user_id");
            $stmt->bindParam(':amount', $manager_amount, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $manager_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // 记录管事佣金
            $stmt = $pdo->prepare("INSERT INTO fund_details 
                                (user_id, source, destination, amount, related_id, note, order_no) 
                                VALUES (:user_id, 'order_commission', 'user_balance', :amount, :order_id, '管事订单佣金', :order_no)");
            $stmt->bindParam(':user_id', $manager_id, PDO::PARAM_INT);
            $stmt->bindParam(':amount', $manager_amount, PDO::PARAM_STR);
            $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
            $stmt->bindParam(':order_no', $order['order_no'], PDO::PARAM_STR);
            $stmt->execute();
        }
    }
    
    // 7. 记录平台佣金（不分配余额，只记录）
    $stmt = $pdo->prepare("INSERT INTO fund_details 
                        (source, destination, amount, related_id, note, order_no) 
                        VALUES ('order_commission', 'system_income', :amount, :order_id, '平台佣金', :order_no)");
    $stmt->bindParam(':amount', $admin_amount, PDO::PARAM_STR);
    $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    $stmt->bindParam(':order_no', $order['order_no'], PDO::PARAM_STR);
    $stmt->execute();

    // 提交事务
    $pdo->commit();
    
    $_SESSION['success_msg'] = "订单审核通过，佣金分配完成";
    header("Location: order_review.php");
    exit();
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    error_log("数据库错误: " . $e->getMessage());
    $_SESSION['error_msg'] = "系统错误，请稍后再试：" . $e->getMessage();
    header("Location: order_review.php");
    exit();
} catch (RuntimeException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    $_SESSION['error_msg'] = $e->getMessage();
    header("Location: order_review.php");
    exit();
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    error_log("系统错误: " . $e->getMessage());
    $_SESSION['error_msg'] = "系统错误，请稍后再试：" . $e->getMessage();
    header("Location: order_review.php");
    exit();
}
