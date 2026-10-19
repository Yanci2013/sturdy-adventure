<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();
header('Content-Type: text/html; charset=utf-8');

require_once("lib/epay.config.php");
require_once("lib/EpayCore.class.php");
require_once '../../includes/common.php';

$partner_id_from_url = $_GET['partner_id'] ?? null;
unset($_GET['partner_id']);

$epay = new EpayCore($epay_config);
$verify_result = $epay->verifyReturn();

if ($partner_id_from_url) {
    $_GET['partner_id'] = $partner_id_from_url;
}

if($verify_result) {
    $out_trade_no = $_GET['out_trade_no'] ?? '';
    $trade_no = $_GET['trade_no'] ?? '';
    $trade_status = $_GET['trade_status'] ?? '';
    $type = $_GET['type'] ?? '';
    $money = floatval($_GET['money'] ?? 0);
    
    try {
        global $pdo;
        
        if (!($pdo instanceof PDO)) {
            throw new Exception("数据库连接未初始化");
        }
        
        $pdo->beginTransaction();
        
        $partner_id = null;
        if (strpos($out_trade_no, '_') !== false) {
            $parts = explode('_', $out_trade_no);
            $partner_id = $parts[0];
        }
        
        if (empty($partner_id)) {
            $partner_id = $_POST['partner_id'] ?? $_GET['partner_id'] ?? null;
        }
        
        if (empty($partner_id)) {
            throw new Exception("无法确定商家ID，订单号: {$out_trade_no}");
        }
        
        $stmt = $pdo->prepare("SELECT id, name, balance FROM partners WHERE id = ?");
        $stmt->execute([$partner_id]);
        $partner = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$partner) {
            throw new Exception("商家ID {$partner_id} 不存在");
        }
        
        $stmt = $pdo->prepare("SELECT id FROM fund_details WHERE order_no = ?");
        $stmt->execute([$out_trade_no]);
        $existingRecord = $stmt->fetch();
        
        if (!$existingRecord) {
            $stmt = $pdo->prepare("UPDATE partners SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$money, $partner_id]);
            
            if ($stmt->rowCount() == 0) {
                throw new Exception("更新商家账户余额失败");
            }
            
            $stmt = $pdo->prepare("INSERT INTO fund_details 
                                (partner_id, source, destination, amount, order_no, note, create_time)
                                VALUES (?, 'recharge', 'partner_balance', ?, ?, '商家账户余额充值', NOW())");
            $stmt->execute([$partner_id, $money, $out_trade_no]);
            
            if ($stmt->rowCount() == 0) {
                throw new Exception("记录资金流水失败");
            }
        }
        
        $pdo->commit();
        
        $stmt = $pdo->prepare("SELECT balance FROM partners WHERE id = ?");
        $stmt->execute([$partner_id]);
        $updated_balance = $stmt->fetchColumn();
        
        $payment_type = '';
        switch($type) {
            case 'alipay': $payment_type = '支付宝'; break;
            case 'wxpay': $payment_type = '微信支付'; break;
            case 'qqpay': $payment_type = 'QQ钱包'; break;
            case 'bank': $payment_type = '云闪付'; break;
            default: $payment_type = $type;
        }
        
        ob_end_clean();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>充值成功</title>
    <meta http-equiv="refresh" content="2;url=../../mobilesj/wallet.php">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        
        .success-container {
            background: #ffffff;
            padding: 40px 30px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            text-align: center;
            max-width: 500px;
            width: 100%;
            animation: fadeInUp 0.5s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            color: white;
            font-size: 40px;
        }
        
        h1 {
            color: #000000;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 24px;
        }
        
        .order-info {
            text-align: left;
            margin: 24px 0;
            padding: 20px;
            background: #f5f5f5;
            border-radius: 12px;
        }
        
        .order-info p {
            margin: 12px 0;
            color: #000000;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .order-info strong {
            color: #000000;
            font-weight: 600;
        }
        
        .redirect-message {
            color: #666666;
            margin-top: 24px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon"><i class="fas fa-check"></i></div>
        <h1>账户余额充值成功</h1>
        
        <div class="order-info">
            <p><strong>商家名称：</strong> <?php echo htmlspecialchars($partner['name']); ?></p>
            <p><strong>商家ID：</strong> <?php echo htmlspecialchars($partner_id); ?></p>
            <p><strong>订单编号：</strong> <?php echo htmlspecialchars($out_trade_no); ?></p>
            <p><strong>充值金额：</strong> <?php echo number_format($money, 2); ?>元</p>
            <p><strong>支付方式：</strong> <?php echo $payment_type; ?></p>
            <p><strong>交易时间：</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
            <p><strong>当前账户余额：</strong> <?php echo number_format($updated_balance, 2); ?>元</p>
        </div>
        
        <div class="redirect-message"><i class="fas fa-spinner fa-spin"></i> 2秒后自动返回商家中心...</div>
    </div>
</body>
</html>
<?php
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("处理商家充值返回时出错: " . $e->getMessage());
        error_log("订单详情 - 订单号: {$out_trade_no}, 金额: {$money}, 类型: {$type}");
        
        ob_end_clean();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>处理错误</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        
        .error-container {
            background: #ffffff;
            padding: 40px 30px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            text-align: center;
            max-width: 500px;
            width: 100%;
            animation: fadeInUp 0.5s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .error-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #F44336 0%, #d32f2f 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            color: white;
            font-size: 40px;
        }
        
        h1 {
            color: #000000;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 24px;
        }
        
        .info {
            text-align: left;
            margin: 24px 0;
            padding: 20px;
            background: #f5f5f5;
            border-radius: 12px;
        }
        
        .info p {
            margin: 12px 0;
            color: #000000;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .action {
            margin-top: 24px;
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 12px 24px;
            background: #f5b400;
            color: #000000;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn:hover {
            background: #e0a300;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 180, 0, 0.3);
        }
        
        .btn-secondary {
            background: #000000;
            color: #ffffff;
        }
        
        .btn-secondary:hover {
            background: #333333;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon"><i class="fas fa-times"></i></div>
        <h1>处理错误</h1>
        
        <div class="info">
            <p>处理您的账户余额充值请求时发生错误</p>
            <p>错误信息: <?php echo htmlspecialchars($e->getMessage()); ?></p>
            <p>订单号: <?php echo htmlspecialchars($out_trade_no); ?></p>
            <p>支付金额: <?php echo number_format($money, 2); ?>元</p>
            <p>如果已扣款但未到账，请联系客服并提供订单号</p>
        </div>
        
        <div class="action">
            <a href="../../mobilesj/wallet.php" class="btn"><i class="fas fa-arrow-left"></i> 返回商家中心</a>
            <a href="mailto:support@example.com" class="btn btn-secondary"><i class="fas fa-envelope"></i> 联系客服</a>
        </div>
    </div>
</body>
</html>
<?php
    }
} else {
    ob_end_clean();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>验证失败</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        
        .error-container {
            background: #ffffff;
            padding: 40px 30px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            text-align: center;
            max-width: 500px;
            width: 100%;
            animation: fadeInUp 0.5s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .error-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #F44336 0%, #d32f2f 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            color: white;
            font-size: 40px;
        }
        
        h1 {
            color: #000000;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 24px;
        }
        
        .info {
            text-align: left;
            margin: 24px 0;
            padding: 20px;
            background: #f5f5f5;
            border-radius: 12px;
        }
        
        .info p {
            margin: 12px 0;
            color: #000000;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .action {
            margin-top: 24px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 12px 24px;
            background: #f5b400;
            color: #000000;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn:hover {
            background: #e0a300;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 180, 0, 0.3);
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <h1>验证失败</h1>
        
        <div class="info">
            <p>未能验证您的支付信息，请检查是否已完成支付</p>
            <p>如果已扣款但未到账，请联系客服处理</p>
        </div>
        
        <div class="action">
            <a href="../../mobilesj/wallet.php" class="btn"><i class="fas fa-arrow-left"></i> 返回商家中心</a>
        </div>
    </div>
</body>
</html>
<?php
}
