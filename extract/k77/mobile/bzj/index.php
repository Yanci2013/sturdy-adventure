<?php
/**
 * 保证金充值系统 - 充值主页
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 引入配置和session管理
require_once('config.php');
require_once(APP_ROOT . '/../includes/session_manager.php');

// 检查用户是否登录
$user_id = get_user_user_id();
if (!$user_id) {
    header('Location: ../login.php');
    exit;
}

// 获取用户信息
$stmt = $pdo->prepare("SELECT balance, deposit FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$user_balance = $user ? floatval($user['balance']) : 0;
$user_deposit = $user ? floatval($user['deposit']) : 0;

// 获取支付配置
$stmt = $pdo->prepare("SELECT payment_api_url, payment_app_id, payment_app_key FROM settings WHERE id = 1");
$stmt->execute();
$payConfig = $stmt->fetch();
$epay_config = [
    'apiurl' => $payConfig['payment_api_url'] ?? 'http://pay.www.com/',
    'pid' => $payConfig['payment_app_id'] ?? '1000',
    'key' => $payConfig['payment_app_key'] ?? ''
];

// 处理充值请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recharge_action'])) {
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_type = $_POST['payment_type'] ?? 'alipay';
    
    if ($amount <= 0) {
        $error_msg = "充值金额必须大于0";
    } else {
        // 生成订单号（缩短格式以适应数据库字段长度）
        $order_no = 'BZ' . $user_id . date('His');
        
        // 插入临时订单
        $expire_time = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        $stmt = $pdo->prepare("INSERT INTO order_temp (order_no, user_id, product_id, product_name, price, payment_amount, digital_id, chinese_id, trade_no, expire_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$order_no, $user_id, 0, '保证金充值', $amount, $amount, '', '', '', $expire_time]);
        
        // 构建支付参数
        $notify_url = 'http://' . $_SERVER['HTTP_HOST'] . '/mobile/bzj/notify_url.php';
        $return_url = 'http://' . $_SERVER['HTTP_HOST'] . '/mobile/bzj/index.php?payment_result=success&order_no=' . urlencode($order_no);
        
        $params = [
            'pid' => $epay_config['pid'],
            'type' => $payment_type,
            'notify_url' => $notify_url,
            'return_url' => $return_url,
            'out_trade_no' => $order_no,
            'name' => '保证金充值',
            'money' => $amount
        ];
        
        // 使用EpayCore生成支付表单
        require_once('lib/EpayCore.class.php');
        $epay = new EpayCore($epay_config);
        echo $epay->pagePay($params);
        exit;
    }
}

// 处理支付返回结果
$success_msg = '';
$error_msg = '';
if (isset($_GET['payment_result'])) {
    if ($_GET['payment_result'] === 'success') {
        $success_msg = "充值成功！订单号: " . htmlspecialchars($_GET['order_no'] ?? '');
    } elseif ($_GET['payment_result'] === 'failed') {
        $error_msg = "充值失败，请重试";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>保证金充值</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
        .container { max-width: 500px; margin: 0 auto; padding: 20px; }
        .card { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .card-title { font-size: 16px; color: #333; margin-bottom: 15px; font-weight: 600; }
        .balance-row { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .balance-label { color: #666; font-size: 14px; }
        .balance-value { color: #333; font-size: 14px; font-weight: 500; }
        .balance-main { font-size: 28px; color: #ff6b00; font-weight: bold; }
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; color: #333; font-size: 14px; margin-bottom: 8px; }
        .input-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; }
        .input-group input:focus { outline: none; border-color: #ff6b00; }
        .quick-amounts { display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; }
        .quick-btn { flex: 1; min-width: 60px; padding: 10px; border: 1px solid #ddd; border-radius: 8px; background: #fff; color: #333; font-size: 14px; cursor: pointer; }
        .quick-btn.active { background: #fff3e0; border-color: #ff6b00; color: #ff6b00; }
        .pay-types { display: flex; gap: 10px; margin-bottom: 20px; }
        .pay-type { flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 8px; background: #fff; text-align: center; cursor: pointer; }
        .pay-type.active { border-color: #ff6b00; background: #fff3e0; }
        .pay-type img { width: 24px; height: 24px; margin-bottom: 5px; }
        .submit-btn { width: 100%; padding: 14px; background: #ff6b00; color: #fff; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; }
        .submit-btn:hover { background: #e65c00; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-title">账户信息</div>
            <div class="balance-row">
                <span class="balance-label">可用余额</span>
                <span class="balance-value balance-main">¥<?php echo number_format($user_balance, 2); ?></span>
            </div>
            <div class="balance-row">
                <span class="balance-label">保证金</span>
                <span class="balance-value">¥<?php echo number_format($user_deposit, 2); ?></span>
            </div>
        </div>

        <div class="card">
            <div class="card-title">充值金额</div>
            
            <?php if ($success_msg): ?>
            <div class="alert alert-success"><?php echo $success_msg; ?></div>
            <?php endif; ?>
            
            <?php if ($error_msg): ?>
            <div class="alert alert-error"><?php echo $error_msg; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="input-group">
                    <label>输入充值金额</label>
                    <input type="number" name="amount" id="amount" step="0.01" min="1" placeholder="请输入充值金额" required>
                </div>
                
                <div class="quick-amounts">
                    <button type="button" class="quick-btn" onclick="setAmount(10)">10元</button>
                    <button type="button" class="quick-btn" onclick="setAmount(30)">30元</button>
                    <button type="button" class="quick-btn" onclick="setAmount(50)">50元</button>
                    <button type="button" class="quick-btn" onclick="setAmount(100)">100元</button>
                    <button type="button" class="quick-btn" onclick="setAmount(500)">500元</button>
                </div>
                
                <div class="pay-types">
                    <div class="pay-type active" onclick="selectPayType('alipay', this)">
                        <div style="font-size:20px;">支</div>
                        <div style="font-size:12px;">支付宝</div>
                    </div>
                    <div class="pay-type" onclick="selectPayType('wxpay', this)">
                        <div style="font-size:20px;">微</div>
                        <div style="font-size:12px;">微信支付</div>
                    </div>
                </div>
                
                <input type="hidden" name="payment_type" id="payment_type" value="alipay">
                <input type="hidden" name="recharge_action" value="1">
                
                <button type="submit" class="submit-btn">立即充值</button>
            </form>
        </div>
    </div>

    <script>
        function setAmount(amount) {
            document.getElementById('amount').value = amount;
            document.querySelectorAll('.quick-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
        }
        
        function selectPayType(type, el) {
            document.getElementById('payment_type').value = type;
            document.querySelectorAll('.pay-type').forEach(el => el.classList.remove('active'));
            el.classList.add('active');
        }
    </script>
</body>
</html>