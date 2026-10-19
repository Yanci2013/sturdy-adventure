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
    $sql = "SELECT o.* FROM orders o 
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

    // 1. 更新订单状态为待服务(2)，让打手可以重新提交
    $update_sql = "UPDATE orders SET 
                  order_status = 2
                  WHERE id = :order_id";
    
    $stmt = $pdo->prepare($update_sql);
    $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    $stmt->execute();

    // 提交事务
    $pdo->commit();
    
    $_SESSION['success_msg'] = "订单已拒绝，已退回给打手重新提交";
    header("Location: order_review.php");
    exit();
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    error_log("数据库错误: " . $e->getMessage());
    $_SESSION['error_msg'] = "系统错误，请稍后再试";
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
    $_SESSION['error_msg'] = "系统错误，请稍后再试";
    header("Location: order_review.php");
    exit();
}
