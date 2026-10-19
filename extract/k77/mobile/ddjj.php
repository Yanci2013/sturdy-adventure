<?php
// 引入必要的文件
include 'header.php';

$user_id = get_user_user_id() ?? 0;
$order_id = (int)$_GET['id']; // 订单ID

// 开启事务
$pdo->beginTransaction();
try {
    // 更新订单状态为已完成
    $updateOrderStmt = $pdo->prepare("UPDATE orders SET status = 'completed' WHERE id = ? AND executor_id = ?");
    if (!$updateOrderStmt->execute([$order_id, $user_id])) {
        throw new Exception('更新订单状态失败');
    }

    // 解除保证金冻结
    $user = getUserById($user_id);
    $newDeposit = $user['deposit'] + 50;
    $updateDepositStmt = $pdo->prepare("UPDATE users SET deposit = ? WHERE id = ?");
    if (!$updateDepositStmt->execute([$newDeposit, $user_id])) {
        throw new Exception('解除保证金冻结失败');
    }

    // 添加系统日志（示例函数，需根据实际实现）
    addLog($user_id, '完成订单', "订单ID: {$order_id}");
    // 添加系统通知（示例函数，需根据实际实现）
    addNotification($user_id, '订单完成', "您已成功完成订单，订单ID: {$order_id}，保证金已解冻");

    $pdo->commit(); // 提交事务
    $success = '订单已成功完成，保证金已解冻';
    header('Location: my_orders.php?success=1');
    exit;
} catch (Exception $e) {
    $pdo->rollBack(); // 回滚事务
    $error = '完成订单失败，请重试。错误：' . $e->getMessage();
}
?>