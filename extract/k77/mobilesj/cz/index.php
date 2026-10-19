<?php
include("../../includes/common.php");

// 这里假设商家已登录，获取商家信息
session_start();
if (!isset($_SESSION['partner_id'])) {
    // 商家未登录，跳转到登录页面
    header("Location: login.php");
    exit();
}

// 获取商家信息
$partner_id = $_SESSION['partner_id'];

// 从数据库获取商家余额和保证金
try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $db->prepare("SELECT balance, deposit FROM partners WHERE id = ?");
    $stmt->execute([$partner_id]);
    $partner = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $partner_balance = $partner ? $partner['balance'] : 0.00;
    $partner_deposit = $partner ? $partner['deposit'] : 0.00;
} catch (PDOException $e) {
    // 数据库错误处理
    $partner_balance = 0.00;
    $partner_deposit = 0.00;
    error_log("数据库错误: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>账户余额充值</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* H5风格 - 商家充值 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #1d1d1f;
            min-height: 100vh;
            padding: 12px;
            -webkit-font-smoothing: antialiased;
        }

        .container {
            width: 100%;
            max-width: 600px;
            background: #ffffff;
            border: none;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            margin: 0 auto;
            animation: fadeInUp 0.6s ease-out;
            overflow: hidden;
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

        .header {
            border-bottom: 1px solid #f0f0f0;
            padding: 16px;
            position: relative;
            text-align: center;
        }

        .back-btn {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #666666;
            text-decoration: none;
            font-size: 20px;
            display: flex;
            align-items: center;
            transition: all 0.2s ease;
            width: 36px;
            height: 36px;
            justify-content: center;
            border-radius: 50%;
        }

        .back-btn:hover {
            color: #000000;
            background: #f5f5f5;
        }

        .header h1 {
            font-size: 17px;
            font-weight: 600;
            margin: 0;
            color: #000000;
        }

        .progress-steps {
            display: flex;
            justify-content: center;
            padding: 20px 0 16px;
            border-bottom: 1px solid #f0f0f0;
        }

        .step {
            position: relative;
            padding: 0 20px;
            text-align: center;
            color: #999999;
            font-weight: 500;
            font-size: 13px;
        }

        .step.active {
            color: #000000;
            font-weight: 600;
        }

        .step:not(:last-child):after {
            content: "";
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 12px;
            height: 2px;
            background: #e0e0e0;
        }

        .form-container {
            padding: 20px 16px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #000000;
            font-weight: 600;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: none;
            background: #f5f5f5;
            font-size: 15px;
            color: #1d1d1f;
            transition: all 0.2s ease;
            border-radius: 12px;
        }

        .form-control:focus {
            outline: none;
            background: #eeeeee;
            box-shadow: 0 0 0 3px rgba(245, 180, 0, 0.2);
        }

        .form-control[readonly] {
            background: #fafafa;
            color: #999999;
        }

        /* 金额选项 */
        .amount-options {
            display: flex;
            gap: 10px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        .amount-option {
            flex: 1 1 calc(25% - 8px);
            min-width: 70px;
        }

        .amount-option input {
            display: none;
        }

        .amount-option label {
            display: block;
            padding: 12px 8px;
            text-align: center;
            border: 2px solid #f0f0f0;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
            color: #666666;
            background: #ffffff;
            border-radius: 12px;
            font-weight: 500;
        }

        .amount-option input:checked + label {
            border-color: #f5b400;
            color: #000000;
            background: #fff7e6;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(245, 180, 0, 0.15);
        }

        .amount-option label:hover {
            border-color: #000000;
            transform: translateY(-2px);
        }

        /* 支付方式 */
        .payment-methods {
            display: flex;
            gap: 12px;
            margin-top: 8px;
        }

        .payment-method {
            flex: 1;
        }

        .payment-method input {
            display: none;
        }

        .payment-method label {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px 16px;
            border: 2px solid #f0f0f0;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #ffffff;
            border-radius: 12px;
        }

        .payment-method input:checked + label {
            border-color: #f5b400;
            background: #fff7e6;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(245, 180, 0, 0.15);
        }

        .payment-method label:hover {
            border-color: #000000;
            transform: translateY(-2px);
        }

        .payment-method img {
            width: 36px;
            height: 36px;
            margin-bottom: 10px;
            opacity: 1;
        }

        .payment-method span {
            font-size: 14px;
            color: #666666;
            font-weight: 500;
        }

        .payment-method input:checked + label span {
            color: #000000;
        }

        /* 提交按钮 */
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: #000000;
            border: none;
            color: #ffffff;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 16px;
            border-radius: 12px;
        }

        .btn-submit:hover {
            background: #333333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-submit:disabled {
            background: #999999;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* 用户信息卡片 */
        .user-info {
            background: #fafafa;
            border: none;
            padding: 20px;
            margin: 0 16px 16px;
            text-align: center;
            border-radius: 12px;
        }

        .user-info p {
            margin-bottom: 10px;
            color: #666666;
            font-size: 14px;
        }

        .user-info p:last-child {
            margin-bottom: 0;
        }

        .balance-amount {
            color: #000000;
            font-weight: 600;
            margin-left: 6px;
            font-size: 18px;
        }

        .deposit-amount {
            color: #666666;
            font-weight: 600;
            margin-left: 6px;
            font-size: 18px;
        }

        .footer {
            border-top: 1px solid #f0f0f0;
            padding: 16px;
            text-align: center;
            font-size: 12px;
            color: #999999;
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            body {
                padding: 12px;
            }

            .header {
                padding: 14px 16px;
            }

            .header h1 {
                font-size: 16px;
            }

            .back-btn {
                left: 12px;
                font-size: 18px;
            }

            .step {
                padding: 0 16px;
                font-size: 12px;
            }

            .form-container {
                padding: 16px;
            }

            .form-group {
                margin-bottom: 18px;
            }

            .form-control {
                padding: 11px 14px;
                font-size: 14px;
            }

            .amount-option label {
                padding: 10px 6px;
                font-size: 13px;
            }

            .payment-method label {
                padding: 16px 12px;
            }

            .payment-method img {
                width: 32px;
                height: 32px;
            }

            .btn-submit {
                padding: 13px;
                font-size: 15px;
            }

            .user-info {
                padding: 16px;
                margin: 0 16px 16px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }

            .amount-options {
                gap: 8px;
            }

            .amount-option {
                flex: 1 1 calc(50% - 4px);
            }

            .payment-methods {
                flex-direction: column;
                gap: 10px;
            }
        }

        /* 深色模式适配 */
        @media (prefers-color-scheme: dark) {
            body {
                background: #1a1a1a;
            }

            .container {
                background: #2a2a2a;
            }

            .header {
                border-bottom-color: #404040;
            }

            .header h1 {
                color: #ffffff;
            }

            .back-btn {
                color: #808080;
            }

            .back-btn:hover {
                color: #ffffff;
                background: #333333;
            }

            .progress-steps {
                border-bottom-color: #404040;
            }

            .step {
                color: #808080;
            }

            .step.active {
                color: #ffffff;
            }

            .step:not(:last-child):after {
                background: #404040;
            }

            .form-group label {
                color: #ffffff;
            }

            .form-control {
                background: #333333;
                color: #e0e0e0;
            }

            .form-control:focus {
                background: #404040;
            }

            .form-control[readonly] {
                background: #2a2a2a;
                color: #808080;
            }

            .amount-option label {
                background: #2a2a2a;
                border-color: #404040;
                color: #b0b0b0;
            }

            .amount-option input:checked + label {
                border-color: #f5b400;
                color: #ffffff;
                background: #3d3000;
            }

            .payment-method label {
                background: #2a2a2a;
                border-color: #404040;
            }

            .payment-method input:checked + label {
                border-color: #f5b400;
                background: #3d3000;
            }

            .payment-method span {
                color: #b0b0b0;
            }

            .payment-method input:checked + label span {
                color: #ffffff;
            }

            .payment-method img {
                filter: brightness(0.9);
            }

            .btn-submit {
                background: #ffffff;
                color: #000000;
            }

            .btn-submit:hover {
                background: #e0e0e0;
                color: #000000;
            }

            .btn-submit:disabled {
                background: #404040;
                color: #808080;
            }

            .user-info {
                background: #2a2a2a;
            }

            .user-info p {
                color: #b0b0b0;
            }

            .balance-amount {
                color: #ffffff;
            }

            .deposit-amount {
                color: #b0b0b0;
            }

            .footer {
                border-top-color: #404040;
                color: #808080;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="../../mobilesj/wallet.php" class="back-btn"><i class="fas fa-chevron-left"></i></a>
            <h1>账户余额充值</h1>
        </div>
        
        <div class="progress-steps">
            <div class="step active">1. 填写信息</div>
            <div class="step">2. 确认支付</div>
            <div class="step">3. 完成充值</div>
        </div>
        
        <div class="user-info">
            <p>当前账户余额: <span class="balance-amount">¥<?php echo number_format($partner_balance, 2); ?></span></p>
            <p>保证金余额: <span class="deposit-amount">¥<?php echo number_format($partner_deposit, 2); ?></span></p>
        </div>
        
        <form name="payment" action="epayapi.php" method="post" target="_blank" class="form-container">
            <input type="hidden" name="WIDout_trade_no" value="<?php echo date("YmdHis").mt_rand(1,999); ?>">
            <input type="hidden" name="WIDsubject" value="账户余额充值">
            <input type="hidden" name="WIDtotal_fee" id="realAmount" value="">
            <input type="hidden" name="partner_id" value="<?php echo $partner_id; ?>">
            
            <div class="form-group">
                <label for="orderNo">订单编号</label>
                <input type="text" id="orderNo" name="order_no" class="form-control" value="<?php echo $partner_id . '_' . date("YmdHis").mt_rand(1,999); ?>" readonly>
            </div>
            
            <div class="form-group">
                <label for="amount">充值金额 (元)</label>
                <input type="number" id="amount" name="amount" class="form-control" value="30" min="1" step="1">
                
                <div class="amount-options">
                    <div class="amount-option">
                        <input type="radio" id="amount10" name="amountOption" value="10">
                        <label for="amount10">10元</label>
                    </div>
                    <div class="amount-option">
                        <input type="radio" id="amount30" name="amountOption" value="30" checked>
                        <label for="amount30">30元</label>
                    </div>
                    <div class="amount-option">
                        <input type="radio" id="amount50" name="amountOption" value="50">
                        <label for="amount50">50元</label>
                    </div>
                    <div class="amount-option">
                        <input type="radio" id="amount100" name="amountOption" value="100">
                        <label for="amount100">100元</label>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>支付方式</label>
                <div class="payment-methods">
                    <div class="payment-method">
                        <input type="radio" id="alipay" name="type" value="alipay" checked>
                        <label for="alipay">
                            <i class="fab fa-alipay" style="font-size: 36px; color: #1677ff; margin-bottom: 10px;"></i>
                            <span>支付宝</span>
                        </label>
                    </div>
                    <div class="payment-method">
                        <input type="radio" id="wxpay" name="type" value="wxpay">
                        <label for="wxpay">
                            <i class="fab fa-weixin" style="font-size: 36px; color: #07c160; margin-bottom: 10px;"></i>
                            <span>微信支付</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn-submit">确认充值</button>
        </form>
        
        <div class="footer">
            <p>点击"确认充值"按钮即表示您同意本次交易操作</p>
        </div>
    </div>
    <script>
        // 金额选项点击事件
        document.querySelectorAll('.amount-option input').forEach(option => {
            option.addEventListener('change', function() {
                if(this.checked) {
                    document.getElementById('amount').value = this.value;
                }
            });
        });
        
        // 金额输入框变化时更新选项
        document.getElementById('amount').addEventListener('input', function() {
            const value = this.value;
            document.querySelectorAll('.amount-option input').forEach(option => {
                option.checked = (option.value === value);
            });
        });
        
        // 在提交表单前设置金额值
        document.querySelector('form').addEventListener('submit', function() {
            document.getElementById('realAmount').value = document.getElementById('amount').value;
            
            // 禁用提交按钮防止重复提交
            const submitBtn = this.querySelector('.btn-submit');
            submitBtn.disabled = true;
            submitBtn.textContent = '跳转支付中...';
        });
    </script>
</body>
</html>
