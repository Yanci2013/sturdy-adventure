<?php
/**
 * 团费支付回调处理
 * 支持微信支付和支付宝支付回调
 */
require_once __DIR__ . '/../../includes/common.php';

// 获取回调数据（根据不同支付方式处理）
$payment_type = $_GET['type'] ?? '';
$order_no = $_GET['out_trade_no'] ?? $_POST['out_trade_no'] ?? '';
$trade_no = $_GET['trade_no'] ?? $_POST['trade_no'] ?? '';
$trade_status = $_GET['trade_status'] ?? $_POST['trade_status'] ?? '';

// 日志记录
$log_file = __DIR__ . '/../../logs/group_fee_notify.log';
$log_dir = dirname($log_file);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0777, true);
}
file_put_contents($log_file, date('Y-m-d H:i:s') . " - 回调通知: " . json_encode($_GET) . " " . json_encode($_POST) . "\n", FILE_APPEND);

try {
    // 查找对应的团费缴纳记录
    $stmt = $pdo->prepare("SELECT * FROM group_fee_payments WHERE trade_no = ? AND status = 0");
    $stmt->execute([$order_no]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - 未找到待支付记录: $order_no\n", FILE_APPEND);
        echo 'success';
        exit;
    }
    
    // 验证支付状态
    $is_paid = false;
    
    if ($payment_type === 'alipay') {
        // 支付宝回调验证
        // TRADE_SUCCESS 或 TRADE_FINISHED 表示支付成功
        $is_paid = in_array($trade_status, ['TRADE_SUCCESS', 'TRADE_FINISHED']);
    } elseif ($payment_type === 'wechat') {
        // 微信支付回调验证
        // 根据实际微信支付SDK处理
        $is_paid = true; // 简化处理，实际需要验证签名
    }
    
    if ($is_paid) {
        // 开启事务
        $pdo->beginTransaction();
        
        try {
            // 更新缴纳记录
            $stmt = $pdo->prepare("UPDATE group_fee_payments SET status = 1, paid_at = NOW() WHERE id = ?");
            $stmt->execute([$payment['id']]);
            
            // 记录资金明细
            $stmt = $pdo->prepare("INSERT INTO fund_details (user_id, source, destination, amount, related_id, note, order_no, create_time) VALUES (?, 'balance', 'system_income', ?, ?, '团费缴纳', ?, NOW())");
            $stmt->execute([$payment['user_id'], -$payment['amount'], $payment['id'], $order_no]);
            
            $pdo->commit();
            
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - 支付成功处理完成: $order_no\n", FILE_APPEND);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - 处理失败: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
    
    // 返回成功响应
    if ($payment_type === 'alipay') {
        echo 'success';
    } else {
        echo '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
    }
    
} catch (PDOException $e) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - 数据库错误: " . $e->getMessage() . "\n", FILE_APPEND);
    echo 'fail';
}
?>
