<?php
/**
 * 保证金充值系统 - 支付回调处理
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: text/plain');

require_once('config.php');

try {
    // 获取参数
    $out_trade_no = $_GET['out_trade_no'] ?? $_POST['out_trade_no'] ?? '';
    $trade_no = $_GET['trade_no'] ?? $_POST['trade_no'] ?? '';
    $money = floatval($_GET['money'] ?? $_POST['money'] ?? 0);
    
    // 检查订单号
    if (empty($out_trade_no)) {
        echo 'error: order number empty';
        exit;
    }
    
    if ($money <= 0) {
        echo 'error: invalid amount';
        exit;
    }
    
    // 查询临时订单
    $stmt = $pdo->prepare("SELECT * FROM order_temp WHERE order_no = ?");
    $stmt->execute([$out_trade_no]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo 'error: order not found';
        exit;
    }
    
    $userId = $order['user_id'];
    
    // 检查是否已处理
    $stmt = $pdo->prepare("SELECT id FROM fund_details WHERE order_no = ?");
    $stmt->execute([$out_trade_no]);
    if ($stmt->fetch()) {
        echo 'success';
        exit;
    }
    
    // 开始事务
    $pdo->beginTransaction();
    
    try {
        // 更新余额
        $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$money, $userId]);
        
        // 记录资金明细
        $stmt = $pdo->prepare("INSERT INTO fund_details (user_id, source, destination, amount, note, order_no, create_time) VALUES (?, 'payment', 'balance', ?, '账户充值', ?, NOW())");
        $stmt->execute([$userId, $money, $out_trade_no]);
        
        // 删除临时订单
        $stmt = $pdo->prepare("DELETE FROM order_temp WHERE order_no = ?");
        $stmt->execute([$out_trade_no]);
        
        $pdo->commit();
        echo 'success';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo 'error';
    }
    
} catch (Exception $e) {
    echo 'error';
}
?>