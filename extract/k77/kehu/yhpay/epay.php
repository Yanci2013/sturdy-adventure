<?php
/**
 * 支付方式选择页面
 * 版本：2.2
 * 功能：安全处理订单信息，完整传递分类信息
 */

session_start();
require '../config.php';

// 检查用户是否登录
if(empty($_SESSION['user_id'])){
    header("Location: ../login.php");
    exit;
}

// 接收并过滤订单数据
$productName = isset($_POST['productName']) ? htmlspecialchars($_POST['productName']) : '';
$productPrice = isset($_POST['productPrice']) ? floatval($_POST['productPrice']) : 0;
$productId = isset($_POST['productId']) ? intval($_POST['productId']) : 0;
$categoryId = isset($_POST['categoryId']) ? intval($_POST['categoryId']) : 0;
$categoryName = isset($_POST['categoryName']) ? htmlspecialchars($_POST['categoryName']) : '';
$subcategoryId = isset($_POST['subcategoryId']) ? intval($_POST['subcategoryId']) : 0;
$subcategoryName = isset($_POST['subcategoryName']) ? htmlspecialchars($_POST['subcategoryName']) : '';
$gameRegion = isset($_POST['gameRegion']) ? htmlspecialchars($_POST['gameRegion']) : '';
$hunterOption = isset($_POST['hunterOption']) ? htmlspecialchars($_POST['hunterOption']) : 'system';
$hunterId = isset($_POST['hunterId']) ? intval($_POST['hunterId']) : 0;
$inputFields = isset($_POST['inputFields']) ? json_encode($_POST['inputFields']) : '[]';

// 生成订单号
$orderNo = date("YmdHis").mt_rand(100,999);

// 设置15分钟支付倒计时
$expireTime = time() + 900;

// 保存订单到临时表
try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $db->prepare("
        INSERT INTO order_temp (
            order_no, product_id, category_id, category_name,
            subcategory_id, subcategory_name, product_name, price, 
            game_region, hunter_option, hunter_id,
            user_id, input_fields, create_time, expire_time
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 15 MINUTE)
        )
    ");
    
    $stmt->execute([
        $orderNo, $productId, $categoryId, $categoryName,
        $subcategoryId, $subcategoryName, $productName, $productPrice,
        $gameRegion, $hunterOption, $hunterId,
        $_SESSION['user_id'], $inputFields
    ]);
    
} catch(PDOException $e) {
    die("订单保存失败: ".htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>支付方式选择 - 拼购商城</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        body {
            background-color: #f5f5f5;
            color: #333;
            font-size: 14px;
            line-height: 1.5;
        }
        #main {
            max-width: 750px;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }
        .payment-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .step {
            text-align: center;
            flex: 1;
            position: relative;
        }
        .step-number {
            width: 30px;
            height: 30px;
            background: #ddd;
            color: #fff;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 5px;
        }
        .step.active .step-number {
            background: #ff4e22;
        }
        .step-text {
            font-size: 14px;
            color: #999;
        }
        .step.active .step-text {
            color: #ff4e22;
            font-weight: bold;
        }
        .payment-info {
            text-align: center;
            margin: 30px 0;
        }
        .product-name {
            font-size: 16px;
            margin-bottom: 10px;
        }
        .payment-amount {
            font-size: 28px;
            color: #ff4e22;
            font-weight: bold;
            margin: 15px 0;
        }
        .countdown {
            font-size: 16px;
            color: #f44336;
            margin: 15px 0;
        }
        .payment-methods {
            margin: 30px auto;
            max-width: 500px;
        }
        .payment-method {
            display: flex;
            align-items: center;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #eee;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .payment-method:hover {
            border-color: #ff4e22;
            box-shadow: 0 2px 10px rgba(255,78,34,0.1);
        }
        .payment-method input[type="radio"] {
            margin-right: 15px;
            width: 20px;
            height: 20px;
        }
        .payment-icon {
            width: 40px;
            height: 40px;
            margin-right: 15px;
        }
        .payment-name {
            font-size: 16px;
            font-weight: bold;
        }
        .payment-desc {
            font-size: 12px;
            color: #999;
            margin-top: 3px;
        }
        .submit-btn {
            display: block;
            width: 200px;
            height: 50px;
            margin: 30px auto;
            background: #ff4e22;
            color: #fff;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        .submit-btn:hover {
            background: #ff6e4a;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(255,78,34,0.3);
        }
        .note-help {
            text-align: center;
            color: #999;
            font-size: 12px;
            margin-top: 20px;
        }
    </style>
    <script>
    // 支付倒计时
    function startCountdown() {
        var endTime = <?php echo $expireTime; ?> * 1000;
        var timer = setInterval(function() {
            var now = new Date().getTime();
            var distance = endTime - now;
            
            var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            document.getElementById("countdown").innerHTML = "支付剩余时间: " + minutes + "分" + seconds + "秒";
            
            if (distance < 0) {
                clearInterval(timer);
                document.getElementById("countdown").innerHTML = "支付已超时";
                document.getElementById("submitBtn").disabled = true;
                document.getElementById("submitBtn").style.opacity = "0.5";
            }
        }, 1000);
    }
    
    window.onload = function() {
        startCountdown();
    };
    </script>
</head>
<body>
    <div id="main">
        <div class="title">请选择支付方式</div>
        
        <div class="payment-steps">
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-text">确认订单</div>
            </div>
            <div class="step active">
                <div class="step-number">2</div>
                <div class="step-text">选择支付方式</div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-text">完成支付</div>
            </div>
        </div>
        
        <div class="payment-info">
            <div class="product-name"><?php echo htmlspecialchars($productName); ?></div>
            <div class="payment-amount">¥<?php echo number_format($productPrice, 2); ?></div>
            <div class="countdown" id="countdown">支付剩余时间: 15分00秒</div>
        </div>
        
        <form name="payment" action="epayapi.php" method="post" target="_blank">
            <!-- 隐藏字段，传递订单信息 -->
            <input type="hidden" name="WIDout_trade_no" value="<?php echo $orderNo; ?>">
            <input type="hidden" name="WIDsubject" value="<?php echo htmlspecialchars($productName); ?>">
            <input type="hidden" name="WIDtotal_fee" value="<?php echo $productPrice; ?>">
            <input type="hidden" name="WIDuser_id" value="<?php echo $_SESSION['user_id']; ?>">
            <input type="hidden" name="category_id" value="<?php echo $categoryId; ?>">
            <input type="hidden" name="category_name" value="<?php echo htmlspecialchars($categoryName); ?>">
            <input type="hidden" name="subcategory_id" value="<?php echo $subcategoryId; ?>">
            <input type="hidden" name="subcategory_name" value="<?php echo htmlspecialchars($subcategoryName); ?>">
            
            <div class="payment-methods">
                <label class="payment-method">
                    <input type="radio" name="type" value="alipay" checked>
                    <img src="../assets/images/payment/alipay.png" class="payment-icon" alt="支付宝">
                    <div>
                        <div class="payment-name">支付宝支付</div>
                        <div class="payment-desc">推荐使用支付宝APP扫码支付</div>
                    </div>
                </label>
                
                <label class="payment-method">
                    <input type="radio" name="type" value="wxpay">
                    <img src="../assets/images/payment/wechat.png" class="payment-icon" alt="微信">
                    <div>
                        <div class="payment-name">微信支付</div>
                        <div class="payment-desc">推荐使用微信扫码支付</div>
                    </div>
                </label>
            </div>
            
            <button type="submit" class="submit-btn" id="submitBtn">立即支付</button>
        </form>
        
        <div class="note-help">请在15分钟内完成支付，超时订单将自动取消</div>
    </div>
</body>
</html>