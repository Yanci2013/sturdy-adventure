<?php
/**
 * 保证金充值系统 - 支付返回页面
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once('config.php');

// 记录日志
$logFile = LOG_DIR . 'bzj_return_' . date('Ymd') . '.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " | GET: " . json_encode($_GET) . "\n", FILE_APPEND);

$order_no = $_GET['out_trade_no'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>充值返回</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
        .container { max-width: 500px; margin: 0 auto; padding: 20px; }
        .card { background: #fff; border-radius: 12px; padding: 30px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; }
        .icon { font-size: 60px; margin-bottom: 20px; }
        .icon.success { color: #52c41a; }
        .icon.error { color: #f5222d; }
        .title { font-size: 20px; color: #333; margin-bottom: 10px; font-weight: 600; }
        .order-no { font-size: 14px; color: #999; margin-top: 10px; }
        .btn { display: inline-block; padding: 12px 30px; background: #ff6b00; color: #fff; border-radius: 8px; text-decoration: none; margin-top: 20px; }
        .btn:hover { background: #e65c00; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="icon success">✓</div>
            <div class="title">支付成功</div>
            <div class="order-no">订单号: <?php echo htmlspecialchars($order_no); ?></div>
            <p style="color:#666;margin-top:15px;">如有疑问请联系客服</p>
            <a href="index.php" class="btn">返回充值页面</a>
        </div>
    </div>
</body>
</html>