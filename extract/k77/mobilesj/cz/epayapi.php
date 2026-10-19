<?php
require_once("lib/epay.config.php");
require_once("lib/EpayCore.class.php");

// 检查数据库连接是否有效
try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception("数据库连接未初始化");
    }
    
    // 检查连接是否有效
    $pdo->query('SELECT 1');
} catch (Exception $e) {
    die('<div class="error-container" style="text-align:center;padding:20px;">
            <h3 style="color:#d32f2f;">数据库连接错误</h3>
            <p>无法连接到数据库，请稍后再试</p>
            <a href="javascript:history.back()" style="color:#00796b;">返回上一页</a>
         </div>');
}

// 从数据库获取支付回调基础地址
try {
    $sql = "SELECT payment_notify_url FROM settings LIMIT 1";
    $stmt = $pdo->query($sql);
    
    if ($stmt && $stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $base_url = rtrim($row['payment_notify_url'], '/');
        
        // 安全获取POST参数
        $out_trade_no = isset($_POST['WIDout_trade_no']) ? trim($_POST['WIDout_trade_no']) : '';
        $type = isset($_POST['type']) ? trim($_POST['type']) : 'alipay';
        $name = isset($_POST['WIDsubject']) ? trim($_POST['WIDsubject']) : '保证金充值';
        $money = isset($_POST['WIDtotal_fee']) ? floatval($_POST['WIDtotal_fee']) : 0;
        $partner_id = isset($_POST['partner_id']) ? trim($_POST['partner_id']) : '';

        // 验证订单号是否为空
        if(empty($out_trade_no)) {
            die('<div class="error-container" style="text-align:center;padding:20px;">
                    <h3 style="color:#d32f2f;">错误：订单号不能为空</h3>
                    <p>系统未能获取到有效的订单号，请返回重新提交</p>
                    <a href="javascript:history.back()" style="color:#00796b;">返回上一页</a>
                 </div>');
        }

        // 验证金额是否有效
        if($money <= 0) {
            die('<div class="error-container" style="text-align:center;padding:20px;">
                    <h3 style="color:#d32f2f;">错误：支付金额无效</h3>
                    <p>支付金额必须大于0，请返回重新提交</p>
                    <a href="javascript:history.back()" style="color:#00796b;">返回上一页</a>
                 </div>');
        }

        /**************************请求参数**************************/
        $notify_url = $base_url . "/mobilesj/cz/notify_url.php?partner_id=" . urlencode($partner_id);
        $return_url = $base_url . "/mobilesj/cz/return_url.php?partner_id=" . urlencode($partner_id);

        /************************************************************/

        // 构造要请求的参数数组
        $parameter = array(
            "pid" => $epay_config['pid'],
            "type" => $type,
            "notify_url" => $notify_url,
            "return_url" => $return_url,
            "out_trade_no" => $out_trade_no,
            "name" => $name,
            "money" => $money,
        );

        // 建立请求
        $epay = new EpayCore($epay_config);
        $html_text = $epay->pagePay($parameter);
    } else {
        die('<div class="error-container" style="text-align:center;padding:20px;">
                <h3 style="color:#d32f2f;">配置错误</h3>
                <p>无法获取支付回调地址配置，请联系管理员</p>
                <a href="javascript:history.back()" style="color:#00796b;">返回上一页</a>
             </div>');
    }
} catch (PDOException $e) {
    die('<div class="error-container" style="text-align:center;padding:20px;">
            <h3 style="color:#d32f2f;">数据库错误</h3>
            <p>处理支付请求时发生数据库错误</p>
            <a href="javascript:history.back()" style="color:#00796b;">返回上一页</a>
         </div>');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>正在为您跳转到支付页面，请稍候...</title>
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
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .loading-container {
            width: 90%;
            max-width: 500px;
            background: #ffffff;
            border-radius: 16px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
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
        
        .loading-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 24px;
            position: relative;
        }
        
        .loading-icon:before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 4px solid #f5f5f5;
            border-radius: 50%;
            border-top-color: #f5b400;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .loading-text {
            color: #000000;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        
        .loading-desc {
            color: #666666;
            font-size: 14px;
            margin-top: 8px;
        }
        
        .order-info {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 12px;
            margin-top: 24px;
            text-align: left;
        }
        
        .order-info p {
            margin: 10px 0;
            color: #000000;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .order-info strong {
            color: #000000;
            font-weight: 600;
        }
        
        @media (prefers-color-scheme: dark) {
            body {
                background-color: #1a1a1a;
            }
            
            .loading-container {
                background: #2d2d2d;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            }
            
            .loading-text {
                color: #ffffff;
            }
            
            .loading-desc {
                color: #999999;
            }
            
            .order-info {
                background: #3d3d3d;
            }
            
            .order-info p {
                color: #ffffff;
            }
            
            .order-info strong {
                color: #ffffff;
            }
        }
    </style>
</head>
<body>
    <?php echo $html_text; ?>
    
    <div class="loading-container">
        <div class="loading-icon"></div>
        <div class="loading-text">正在为您跳转到支付页面，请稍候...</div>
        <div class="loading-desc"><i class="fas fa-info-circle"></i> 请不要关闭或刷新此页面</div>
        
        <div class="order-info">
            <p><strong><i class="fas fa-hashtag"></i> 订单编号：</strong> <?php echo htmlspecialchars($out_trade_no); ?></p>
            <p><strong><i class="fas fa-shopping-cart"></i> 商品名称：</strong> <?php echo htmlspecialchars($name); ?></p>
            <p><strong><i class="fas fa-yen-sign"></i> 支付金额：</strong> <?php echo number_format($money, 2); ?>元</p>
            <p><strong><i class="fas fa-credit-card"></i> 支付方式：</strong> 
                <?php 
                switch($type) {
                    case 'alipay': echo '支付宝'; break;
                    case 'wxpay': echo '微信支付'; break;
                    case 'qqpay': echo 'QQ钱包'; break;
                    case 'bank': echo '云闪付'; break;
                    default: echo $type;
                }
                ?>
            </p>
        </div>
    </div>
</body>
</html>
