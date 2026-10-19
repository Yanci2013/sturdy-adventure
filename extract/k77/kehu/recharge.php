<?php
require '../includes/session_manager.php';
require '../config.php';

$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

$isLoggedIn = is_h5_logged_in();
$user_id = get_h5_user_id();

if (!$isLoggedIn || !$user_id) {
    header("Location: login.php?redirect=" . urlencode($currentUrl));
    exit();
}

try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

    $stmt = $db->prepare("SELECT * FROM h5user WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        clear_h5_session();
        die("<script>alert('登录信息已过期，请重新登录'); location.href='login.php?redirect=" . urlencode($currentUrl) . "';</script>");
    }
} catch (PDOException $e) {
    die("数据库错误: " . htmlspecialchars($e->getMessage()));
}

// 充值金额选项
$rechargeOptions = [10, 30, 50, 100, 200, 500];

// 获取支付配置
$payConfig = [];
try {
    $stmt = $db->query("SELECT payment_api_url, payment_app_id, payment_app_key, payment_notify_url FROM settings WHERE id = 1");
    $payConfig = $stmt->fetch();
} catch (Exception $e) {
    error_log('Error loading payment config: ' . $e->getMessage());
}

// 支付API地址
$payApiUrl = $payConfig['payment_api_url'] ?? '';
$payAppId = $payConfig['payment_app_id'] ?? '';
$payAppKey = $payConfig['payment_app_key'] ?? '';
$payNotifyUrl = $payConfig['payment_notify_url'] ?? '';

// 构建完整的回调地址
$notifyUrl = !empty($payNotifyUrl) ? $payNotifyUrl : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/h5/pay_callback.php');
$returnUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/h5/pay_return.php';

// 如果没有配置支付API，显示错误
$payEnabled = !empty($payApiUrl) && !empty($payAppId);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>余额充值</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-bg: #fef9e7;
            --card-bg: #ffffff;
            --text-primary: #333333;
            --text-secondary: #666666;
            --text-tertiary: #999999;
            --accent-gold: #d4a84b;
            --accent-dark: #1a1a1a;
        }

        body {
            font-family: 'Noto Sans SC', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--primary-bg);
            color: var(--text-primary);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            padding-bottom: 100px;
        }

        /* 顶部导航 */
        .page-header {
            padding: 16px 20px;
            background: var(--primary-bg);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .back-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--card-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: var(--text-primary);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .page-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            flex: 1;
            text-align: center;
            margin-right: 52px;
        }

        /* 主内容 */
        .main-content {
            padding: 0 16px;
        }

        /* 余额卡片 */
        .balance-card {
            background: linear-gradient(135deg, #2d2d2d 0%, #1a1a1a 100%);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 16px;
            color: #fff;
        }

        .balance-label {
            font-size: 14px;
            opacity: 0.8;
            margin-bottom: 8px;
        }

        .balance-value {
            font-size: 36px;
            font-weight: 700;
        }

        /* 充值选项 */
        .recharge-section {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 16px;
        }

        .recharge-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 16px;
        }

        .recharge-option {
            padding: 16px 8px;
            border: 2px solid #e8e8e8;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #fff;
        }

        .recharge-option:hover {
            border-color: var(--accent-gold);
        }

        .recharge-option.active {
            border-color: var(--accent-gold);
            background: #fff9e6;
        }

        .recharge-amount {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .recharge-bonus {
            font-size: 11px;
            color: #e74c3c;
        }

        /* 自定义金额 */
        .custom-amount {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border: 2px solid #e8e8e8;
            border-radius: 12px;
            margin-bottom: 16px;
        }

        .custom-amount span {
            font-size: 18px;
            color: var(--text-primary);
            font-weight: 600;
        }

        .custom-amount input {
            flex: 1;
            border: none;
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            outline: none;
        }

        .custom-amount input::placeholder {
            color: var(--text-tertiary);
            font-weight: 400;
        }

        /* 支付方式 */
        .payment-section {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .payment-item {
            display: flex;
            align-items: center;
            padding: 16px;
            border: 2px solid #e8e8e8;
            border-radius: 12px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .payment-item:last-child {
            margin-bottom: 0;
        }

        .payment-item.active {
            border-color: var(--accent-gold);
            background: #fff9e6;
        }

        .payment-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 20px;
        }

        .payment-icon.alipay {
            background: #1677ff;
            color: #fff;
        }

        .payment-icon.wechat {
            background: #07c160;
            color: #fff;
        }

        .payment-info {
            flex: 1;
        }

        .payment-name {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .payment-desc {
            font-size: 12px;
            color: var(--text-tertiary);
            margin-top: 2px;
        }

        .payment-check {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 2px solid #e8e8e8;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 12px;
        }

        .payment-item.active .payment-check {
            background: var(--accent-gold);
            border-color: var(--accent-gold);
        }

        /* 底部按钮 */
        .bottom-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--card-bg);
            padding: 12px 16px 24px;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .total-amount {
            flex: 1;
        }

        .total-label {
            font-size: 12px;
            color: var(--text-tertiary);
        }

        .total-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .recharge-btn {
            padding: 14px 32px;
            background: linear-gradient(135deg, #d4a84b 0%, #f5c842 100%);
            color: #fff;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }

        .recharge-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
    <link rel="stylesheet" href="../assets/css/theme-v2.css">
</head>
<body>
<!-- V2 加载动画 -->
<div class="v2-loader-overlay" id="v2Loader">
    <div class="v2-loader-logo-wrap">
        <div class="v2-loader-ring"></div>
        <div class="v2-loader-ring"></div>
        <div class="v2-loader-logo">小</div>
    </div>
    <div class="v2-loader-text">
        <span>加</span><span>载</span><span>中</span><span>...</span>
    </div>
    <div class="v2-loader-progress">
        <div class="v2-loader-progress-bar"></div>
    </div>
</div>

    <!-- 顶部导航 -->
    <div class="page-header">
        <a href="my.php" class="back-btn">
            <i class="fas fa-chevron-left"></i>
        </a>
        <h1 class="page-title">余额充值</h1>
    </div>

    <!-- 主内容 -->
    <main class="main-content">
        <!-- 余额卡片 -->
        <div class="balance-card">
            <div class="balance-label">当前余额</div>
            <div class="balance-value">¥<?php echo number_format($user['balance'] ?? 0, 2); ?></div>
        </div>

        <!-- 充值金额 -->
        <div class="recharge-section">
            <div class="section-title">选择充值金额</div>
            <div class="recharge-grid">
                <?php foreach ($rechargeOptions as $amount): ?>
                    <div class="recharge-option" data-amount="<?php echo $amount; ?>" onclick="selectAmount(this)">
                        <div class="recharge-amount">¥<?php echo $amount; ?></div>
                        <?php if ($amount >= 100): ?>
                            <div class="recharge-bonus">送<?php echo floor($amount * 0.05); ?>元</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="custom-amount">
                <span>¥</span>
                <input type="number" id="customAmount" placeholder="自定义金额" oninput="customAmountInput(this)">
            </div>
        </div>

        <!-- 支付方式 -->
        <div class="payment-section">
            <div class="section-title">支付方式</div>
            <div class="payment-item active" data-payment="alipay" onclick="selectPayment(this)">
                <div class="payment-icon alipay">
                    <i class="fab fa-alipay"></i>
                </div>
                <div class="payment-info">
                    <div class="payment-name">支付宝</div>
                    <div class="payment-desc">推荐使用</div>
                </div>
                <div class="payment-check"><i class="fas fa-check"></i></div>
            </div>
            <div class="payment-item" data-payment="wechat" onclick="selectPayment(this)">
                <div class="payment-icon wechat">
                    <i class="fab fa-weixin"></i>
                </div>
                <div class="payment-info">
                    <div class="payment-name">微信支付</div>
                    <div class="payment-desc">亿万用户的选择</div>
                </div>
                <div class="payment-check"><i class="fas fa-check"></i></div>
            </div>
        </div>
    </main>

    <!-- 底部按钮 -->
    <div class="bottom-bar">
        <div class="total-amount">
            <div class="total-label">实付金额</div>
            <div class="total-value" id="totalAmount">¥0.00</div>
        </div>
        <button class="recharge-btn" id="rechargeBtn" onclick="submitRecharge()" disabled>立即充值</button>
    </div>

    <script>
        let selectedAmount = 0;
        let selectedPayment = 'alipay';

        function selectAmount(element) {
            // 移除其他选中状态
            document.querySelectorAll('.recharge-option').forEach(el => {
                el.classList.remove('active');
            });
            // 添加选中状态
            element.classList.add('active');
            // 获取金额
            selectedAmount = parseInt(element.dataset.amount);
            // 清空自定义金额
            document.getElementById('customAmount').value = '';
            // 更新总金额
            updateTotal();
        }

        function customAmountInput(input) {
            // 移除固定金额的选中状态
            document.querySelectorAll('.recharge-option').forEach(el => {
                el.classList.remove('active');
            });
            // 获取自定义金额
            selectedAmount = parseFloat(input.value) || 0;
            // 更新总金额
            updateTotal();
        }

        function selectPayment(element) {
            // 移除其他选中状态
            document.querySelectorAll('.payment-item').forEach(el => {
                el.classList.remove('active');
            });
            // 添加选中状态
            element.classList.add('active');
            // 获取支付方式
            selectedPayment = element.dataset.payment;
        }

        function updateTotal() {
            const totalElement = document.getElementById('totalAmount');
            const rechargeBtn = document.getElementById('rechargeBtn');

            totalElement.textContent = '¥' + selectedAmount.toFixed(2);

            if (selectedAmount > 0) {
                rechargeBtn.disabled = false;
            } else {
                rechargeBtn.disabled = true;
            }
        }

        function submitRecharge() {
            if (selectedAmount <= 0) {
                alert('请选择或输入充值金额');
                return;
            }

            // 禁用按钮防止重复提交
            const btn = document.getElementById('rechargeBtn');
            btn.disabled = true;
            btn.textContent = '正在发起支付...';

            // 发送AJAX请求创建充值订单
            fetch('submit_recharge.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    amount: selectedAmount,
                    paymentType: selectedPayment
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 跳转到支付页面
                    window.location.href = data.paymentUrl;
                } else {
                    alert(data.message || '发起支付失败');
                    btn.disabled = false;
                    btn.textContent = '立即充值';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('网络错误，请重试');
                btn.disabled = false;
                btn.textContent = '立即充值';
            });
        }
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
