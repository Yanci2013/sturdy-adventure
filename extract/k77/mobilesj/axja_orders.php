<?php
// 开启输出缓冲
ob_start();

// 包含必要文件
require_once 'header.php';

// 获取操作类型和订单ID
$action = isset($_GET['action']) ? $_GET['action'] : '';
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

// 验证订单ID
if ($order_id <= 0) {
    $_SESSION['error_msg'] = "无效的订单ID";
    header("Location: orders.php?status=$status");
    exit();
}

// 根据不同操作类型处理订单
switch ($action) {
    case 'cancel':
        cancelOrder($order_id, $status);
        break;
    case 'delete':
        deleteOrder($order_id);
        break;
    case 'approve':
        approveOrder($order_id);
        break;
    case 'reject':
        rejectOrder($order_id);
        break;
    default:
        $_SESSION['error_msg'] = "无效的操作类型";
        header("Location: orders.php?status=$status");
        exit();
}

// 结束输出缓冲并发送内容
ob_end_flush();

// 订单取消函数
function cancelOrder($order_id, $status) {
    global $pdo;
    
    try {
        // 更新订单状态为"已取消"
        $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$order_id]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['success_msg'] = "订单 #$order_id 已成功取消";
        } else {
            $_SESSION['error_msg'] = "订单不存在或状态未改变";
        }
        
        // 重定向回订单页面
        header("Location: orders.php?status=$status");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "取消订单时出错: " . $e->getMessage();
        header("Location: orders.php?status=$status");
        exit();
    }
}

// 订单删除函数
function deleteOrder($order_id) {
    global $pdo;
    
    try {
        // 获取订单信息
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            $_SESSION['error_msg'] = "订单不存在";
            header('Location: orders.php');
            exit();
        }
        
        // 检查状态
        if ($order['status'] == 'completed' || $order['status'] == 'rejected') {
            $_SESSION['error_msg'] = "已完成或已拒绝的订单不能删除";
            header('Location: orders.php');
            exit();
        }
        
        $pdo->beginTransaction();
        
        // 如果订单已被用户接单，退还保证金
        if (!empty($order['executor_id'])) {
            $settings = getSystemSettings();
            $deposit = $settings['deposit'];
            
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$deposit, $order['executor_id']]);
        }
        
        // 删除订单
        $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        
        $pdo->commit();
        
        $_SESSION['success_msg'] = "订单 #$order_id 已成功删除";
        header('Location: orders.php');
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "删除订单时出错: " . $e->getMessage();
        header('Location: orders.php');
        exit();
    }
}

// 订单审核通过函数
function approveOrder($order_id) {
    global $pdo;
    
    try {
        // 获取订单信息
        $stmt = $pdo->prepare("SELECT o.*, u.id AS user_id 
                            FROM orders o
                            LEFT JOIN users u ON o.executor_id = u.id
                            WHERE o.id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            $_SESSION['error_msg'] = "订单不存在";
            header('Location: order_review.php');
            exit();
        }
        
        // 检查状态
        if ($order['status'] != 'pending_review') {
            $_SESSION['error_msg'] = "订单状态不允许此操作";
            header('Location: order_review.php');
            exit();
        }
        
        $pdo->beginTransaction();
        
        // 1. 更新订单状态为 completed
        $stmt = $pdo->prepare("UPDATE orders 
                            SET status = 'completed', 
                                reviewed_at = NOW(),
                                completed_at = NOW()
                            WHERE id = ?");
        $stmt->execute([$order_id]);
        
        // 2. 计算用户收益并更新余额
        $user_id = $order['executor_id'];
        $profit = $order['price'] - $order['service_fee'];
        
        $stmt = $pdo->prepare("UPDATE users 
                             SET balance = balance + ? 
                             WHERE id = ?");
        $stmt->execute([$profit, $user_id]);
        
        // 3. 添加资金明细记录
        $stmt = $pdo->prepare("INSERT INTO fund_details 
                            (user_id, source, destination, amount, related_id, note, order_no)
                            VALUES 
                            (?, 'order_income', 'user_account', ?, ?, '订单完成，收入到账', ?)");
        $stmt->execute([
            $user_id, 
            $profit, 
            $order_id, 
            $order['order_no']
        ]);
        
        $pdo->commit();
        $_SESSION['success_msg'] = "订单 #$order_id 已审核通过";
        header('Location: order_review.php');
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "订单处理失败: " . $e->getMessage();
        header('Location: order_review.php');
        exit();
    }
}

// 订单拒绝函数
function rejectOrder($order_id) {
    global $pdo;
    
    try {
        // 获取订单信息
        $stmt = $pdo->prepare("SELECT o.*, u.id AS user_id 
                            FROM orders o
                            LEFT JOIN users u ON o.executor_id = u.id
                            WHERE o.id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            $_SESSION['error_msg'] = "订单不存在";
            header('Location: order_review.php');
            exit();
        }
        
        // 检查状态
        if ($order['status'] != 'pending_review') {
            $_SESSION['error_msg'] = "订单状态不允许此操作";
            header('Location: order_review.php');
            exit();
        }
        
        $pdo->beginTransaction();
        
        // 1. 更新订单状态为 rejected
        $stmt = $pdo->prepare("UPDATE orders 
                            SET status = 'rejected', 
                                reviewed_at = NOW() 
                            WHERE id = ?");
        $stmt->execute([$order_id]);
        
        // 2. 退还用户保证金
        $user_id = $order['executor_id'];
        if ($user_id) {
            $settings = getSystemSettings();
            $deposit = $settings['deposit'];
            
            $stmt = $pdo->prepare("UPDATE users 
                                 SET balance = balance + ? 
                                 WHERE id = ?");
            $stmt->execute([$deposit, $user_id]);
        }
        
        $pdo->commit();
        $_SESSION['success_msg'] = "订单 #$order_id 已拒绝";
        header('Location: order_review.php');
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "订单处理失败: " . $e->getMessage();
        header('Location: order_review.php');
        exit();
    }
}

// 假设的系统设置获取函数
function getSystemSettings() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}