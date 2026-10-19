<?php
/**
 * 微信官方支付页面
 */
session_start();

require_once 'includes/common.php';
require_once 'includes/WechatPay.php';
require_once 'includes/session_manager.php';

// 检查登录
if (!is_h5_logged_in()) {
    header('Location: kehu/login.php');
    exit;
}

// 获取系统设置
$settingsStmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
$settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);

// 检查微信支付是否启用
if (!$settings['wechat_pay_enabled'] || empty($settings['wechat_mch_id']) || empty($settings['wechat_appid'])) {
    header('Location: kehu/payment_select.php?error=wechat_disabled');
    exit;
}

// 获取订单信息
$orderNo = isset($_POST['WIDout_trade_no']) ? $_POST['WIDout_trade_no'] : (isset($_GET['order_no']) ? $_GET['order_no'] : '');
$productName = isset($_POST['WIDsubject']) ? $_POST['WIDsubject'] : '订单支付';
$productPrice = isset($_POST['WIDtotal_fee']) ? floatval($_POST['WIDtotal_fee']) : 0;

if (empty($orderNo) || empty($productName) || $productPrice <= 0) {
    header('Location: kehu/payment_select.php?error=invalid_order');
    exit;
}

try {
    // 初始化微信支付
    $wechatPay = new WechatPay([
        'appid' => $settings['wechat_appid'],
        'mchid' => $settings['wechat_mch_id'],
        'mch_key' => $settings['wechat_mch_key'],
        'notify_url' => $settings['site_domain'] . '/wechat_notify.php',
        'cert_path' => $settings['wechat_mch_cert_path']
    ]);
    
    // 判断用户是否在微信中打开
    $isWechat = isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false;
    
    if ($isWechat) {
        // TODO: 在微信中，需要获取用户的openid，然后调用JSAPI支付
        // 这里我们暂时使用Native支付的简化版本
        $paymentInfo = ['success' => false, 'message' => '微信内支付需要额外配置openid'];
    } else {
        // 外部浏览器，使用Native支付（扫码支付）
        $paymentInfo = $wechatPay->createNativeOrder($orderNo, $productPrice, $productName);
    }
    
} catch (Exception $e) {
    $paymentInfo = [
        'success' => false,
        'message' => '微信支付初始化失败: ' . $e->getMessage()
    ];
}

// 如果支付创建失败，显示简化的支付页面
$showSimplified = !$paymentInfo['success'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>微信支付</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #07c160 0%, #06ad56 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .pay-container {
            background: white;
            border-radius: 16px;
            padding: 30px;
            width: 100%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        
        .pay-icon {
            font-size: 48px;
            color: #07c160;
            margin-bottom: 20px;
        }
        
        .pay-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .pay-amount {
            font-size: 36px;
            font-weight: 700;
            color: #ff4444;
            margin-bottom: 30px;
        }
        
        .pay-amount span {
            font-size: 20px;
        }
        
        .qr-container {
            background: #f5f5f5;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .qr-title {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }
        
        .qr-code {
            width: 200px;
            height: 200px;
            background: white;
            margin: 0 auto;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .qr-code img {
            max-width: 100%;
            max-height: 100%;
        }
        
        .qr-tip {
            font-size: 13px;
            color: #999;
            margin-top: 15px;
        }
        
        .order-info {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: left;
        }
        
        .order-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 13px;
        }
        
        .order-row:last-child {
            margin-bottom: 0;
        }
        
        .order-label {
            color: #666;
        }
        
        .order-value {
            color: #333;
            font-weight: 500;
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            margin-bottom: 10px;
        }
        
        .btn-primary {
            background: #07c160;
            color: white;
        }
        
        .btn-primary:hover {
            background: #06ad56;
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: #666;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        
        .alert-info {
            background: #e3f2fd;
            color: #1565c0;
            border: 1px solid #bbdefb;
        }
        
        .simplified-content {
            text-align: left;
        }
        
        .simplified-content h3 {
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .simplified-content p {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        
        .check-payment {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .check-status {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        
        @media screen and (max-width: 480px) {
            .pay-container {
                padding: 20px;
            }
            
            .pay-title {
                font-size: 18px;
            }
            
            .pay-amount {
                font-size: 32px;
            }
            
            .qr-code {
                width: 180px;
                height: 180px;
            }
        }
    </style>
</head>
<body>
    <div class="pay-container">
        <div class="pay-icon">
            <i class="fab fa-weixin"></i>
        </div>
        
        <div class="pay-title">微信支付</div>
        <div class="pay-amount"><span>¥</span><?php echo number_format($productPrice, 2); ?></div>
        
        <div class="order-info">
            <div class="order-row">
                <span class="order-label">商品名称</span>
                <span class="order-value"><?php echo htmlspecialchars($productName); ?></span>
            </div>
            <div class="order-row">
                <span class="order-label">订单编号</span>
                <span class="order-value"><?php echo htmlspecialchars($orderNo); ?></span>
            </div>
        </div>
        
        <?php if ($showSimplified): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            正在使用简化支付流程，支付成功后请点击下方按钮刷新订单状态。
        </div>
        
        <div class="simplified-content">
            <h3>支付说明</h3>
            <p>1. 请使用微信扫描下方二维码进行支付</p>
            <p>2. 支付完成后，请点击「查询订单状态」按钮</p>
            <p>3. 如遇问题，请联系客服处理</p>
        </div>
        
        <!-- 简化的支付说明 -->
        <div class="qr-container">
            <div class="qr-title">微信扫码支付</div>
            <div class="qr-code">
                <div style="color: #999; font-size: 14px; text-align: center;">
                    <i class="fas fa-qrcode" style="font-size: 60px; margin-bottom: 10px; display: block;"></i>
                    请在后台配置<br>商户证书和API密钥
                </div>
            </div>
            <div class="qr-tip">请使用微信扫描二维码支付</div>
        </div>
        
        <div class="check-payment">
            <div class="check-status">支付完成后，请点击下方按钮</div>
            <a href="kehu/check_order.php?order_no=<?php echo urlencode($orderNo); ?>" class="btn btn-primary">
                <i class="fas fa-sync-alt"></i> 查询订单状态
            </a>
        </div>
        
        <?php elseif (isset($paymentInfo['code_url'])): ?>
        
        <!-- 扫码支付 -->
        <div class="qr-container">
            <div class="qr-title">请使用微信扫描二维码支付</div>
            <div class="qr-code">
                <!-- 这里我们使用第三方库生成二维码，或者让用户复制链接 -->
                <div style="text-align: center;">
                    <p style="color: #666; margin-bottom: 10px;">二维码链接:</p>
                    <p style="font-size: 12px; word-break: break-all; color: #07c160; background: #f0f9f0; padding: 10px; border-radius: 4px;">
                        <?php echo htmlspecialchars($paymentInfo['code_url']); ?>
                    </p>
                </div>
            </div>
            <div class="qr-tip">使用微信扫描上方二维码支付</div>
        </div>
        
        <a href="kehu/check_order.php?order_no=<?php echo urlencode($orderNo); ?>" class="btn btn-primary">
            <i class="fas fa-sync-alt"></i> 我已支付完成
        </a>
        
        <?php else: ?>
        
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($paymentInfo['message']); ?>
        </div>
        
        <div class="simplified-content">
            <h3>请检查后台配置</h3>
            <p>1. 确认商户号(mch_id)和商户密钥(mch_key)是否正确</p>
            <p>2. 确认商户证书路径是否正确</p>
            <p>3. 确认微信支付产品已开通Native支付</p>
        </div>
        
        <?php endif; ?>
        
        <a href="kehu/payment_select.php?order_no=<?php echo urlencode($orderNo); ?>" class="btn btn-secondary">
            <i class="fas fa-chevron-left"></i> 返回选择支付方式
        </a>
    </div>
    
    <script>
        // 自动检查订单状态
        <?php if (!$showSimplified): ?>
        // 每5秒检查一次订单状态
        let checkCount = 0;
        const maxCheckCount = 60; // 最多检查5分钟
        
        function autoCheckOrder() {
            if (checkCount >= maxCheckCount) {
                return;
            }
            
            setTimeout(function() {
                fetch('kehu/check_order_ajax.php?order_no=<?php echo urlencode($orderNo); ?>')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.paid) {
                            window.location.href = 'kehu/index.php';
                        } else {
                            checkCount++;
                            autoCheckOrder();
                        }
                    })
                    .catch(error => {
                        checkCount++;
                        autoCheckOrder();
                    });
            }, 5000);
        }
        
        // 30秒后开始自动检查
        setTimeout(autoCheckOrder, 30000);
        <?php endif; ?>
    </script>
</body>
</html>
