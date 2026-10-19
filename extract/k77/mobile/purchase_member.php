<?php
require_once 'header.php';

$user_id = get_user_user_id() ?? 0;
if (!$user_id) {
    header('Location: login.php');
    exit;
}
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("用户不存在");
    }
} catch (Exception $e) {
    die("获取用户信息失败: " . $e->getMessage());
}

try {
    $stmt = $pdo->query("SELECT * FROM member_levels WHERE status = 1 ORDER BY id ASC");
    $member_levels = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("获取会员等级失败: " . $e->getMessage());
}

// 获取系统设置
try {
    $stmt = $pdo->query("SELECT manager_invite_hunter_commission_rate FROM settings WHERE id = 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $settings = ['manager_invite_hunter_commission_rate' => 0];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase'])) {
    $member_level_id = intval($_POST['member_level_id']);
    $period = $_POST['period'];
    $payment_method = $_POST['payment_method'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM member_levels WHERE id = ? AND status = 1");
        $stmt->execute([$member_level_id]);
        $member_level = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$member_level) {
            throw new Exception("无效的会员等级");
        }
        
        $price_column = $period . '_price';
        $price = $member_level[$price_column];
        
        if ($price <= 0) {
            throw new Exception("该会员等级的价格设置不正确");
        }
        
        $balance_column = ($payment_method === 'deposit') ? 'deposit' : 'balance';
        if ($user[$balance_column] < $price) {
            throw new Exception("您的" . ($payment_method === 'deposit' ? "保证金" : "账户余额") . "不足");
        }
        
        $start_time = date('Y-m-d H:i:s');
        $end_time = '';
        
        switch ($period) {
            case 'daily':
                $end_time = date('Y-m-d H:i:s', strtotime('+1 day'));
                break;
            case 'weekly':
                $end_time = date('Y-m-d H:i:s', strtotime('+1 week'));
                break;
            case 'monthly':
                $end_time = date('Y-m-d H:i:s', strtotime('+1 month'));
                break;
            case 'quarterly':
                $end_time = date('Y-m-d H:i:s', strtotime('+3 months'));
                break;
            case 'yearly':
                $end_time = date('Y-m-d H:i:s', strtotime('+1 year'));
                break;
            default:
                throw new Exception("无效的会员期限");
        }
        
        $order_no = date('YmdHis') . mt_rand(10000, 99999);
        
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE users SET $balance_column = $balance_column - ? WHERE id = ?");
        $stmt->execute([$price, $user_id]);
        
        $source = ($payment_method === 'deposit') ? 'deposit' : 'balance';
        $destination = 'system_income';
        
        $stmt = $pdo->prepare("INSERT INTO fund_details 
            (user_id, source, destination, amount, related_id, note, order_no, create_time) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $user_id,
            $source,
            $destination,
            -$price,
            $member_level_id,
            "购买会员: {$member_level['name']} ({$period})",
            $order_no
        ]);
        
        $stmt = $pdo->prepare("UPDATE users SET 
            member_level_id = ?,
            member_start_time = ?,
            member_end_time = ?,
            is_trial_member = 0
            WHERE id = ?");
        $stmt->execute([$member_level_id, $start_time, $end_time, $user_id]);
        
        if ($member_level['points_given'] > 0) {
            $stmt = $pdo->prepare("UPDATE users SET member_points = member_points + ? WHERE id = ?");
            $stmt->execute([$member_level['points_given'], $user_id]);
        }
        
        $manager_commission_rate = $settings['manager_invite_hunter_commission_rate'] ?? 0;
        if ($manager_commission_rate > 0) {
            $inviter_id = null;
            if (!empty($user['invitation_code'])) {
                $stmt = $pdo->prepare("SELECT inviter_id FROM partner_service_relations WHERE invitee_id = ? AND invitee_type = 'fighter' AND inviter_type = 'eadmin'");
                $stmt->execute([$user_id]);
                $relation = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($relation) {
                    $inviter_id = $relation['inviter_id'];
                } else {
                    $stmt = $pdo->prepare("SELECT generator_id FROM invitations WHERE code = ? AND register_type = 1 AND generator_type = 'eadmin'");
                    $stmt->execute([$user['invitation_code']]);
                    $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($invitation) {
                        $inviter_id = $invitation['generator_id'];
                    }
                }
            }
            
            if ($inviter_id) {
                $commission_amount = $price * ($manager_commission_rate / 100);
                
                if ($commission_amount > 0) {
                    $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                    $stmt->execute([$commission_amount, $inviter_id]);
                    
                    $stmt = $pdo->prepare("INSERT INTO fund_details 
                        (user_id, source, destination, amount, related_id, note, order_no, create_time) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([
                        $inviter_id,
                        'system_income',
                        'balance',
                        $commission_amount,
                        $member_level_id,
                        "会员购买返佣: {$user['username']} 购买 {$member_level['name']}",
                        $order_no
                    ]);
                }
            }
        }
        
        $pdo->commit();
        
        $_SESSION['success_message'] = "会员开通成功！有效期至 " . $end_time;
        header("Location: member_center.php");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>开通会员</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: url('../assets/1.png') no-repeat center center fixed;
            background-size: cover;
            color: #1a1a1a;
            font-size: 14px;
            line-height: 1.6;
            min-height: 100vh;
            padding-bottom: 80px;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.2);
            z-index: -1;
        }
        .page-header {
            background: #ffffff;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .back-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #f5f5f5;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #1a1a1a;
            font-size: 16px;
        }
        .page-title {
            font-size: 18px;
            font-weight: 600;
            flex: 1;
        }
        .container {
            padding: 16px;
        }
        .balance-card {
            background: linear-gradient(135deg, #1a1a1a 0%, #333333 100%);
            border-radius: 20px;
            padding: 24px;
            color: #ffffff;
            margin-bottom: 20px;
            animation: fadeInUp 0.5s ease;
        }
        .balance-card h2 {
            font-size: 14px;
            font-weight: 500;
            opacity: 0.8;
            margin-bottom: 8px;
        }
        .balance-amount {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .balance-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .balance-item {
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 12px;
        }
        .balance-item-label {
            font-size: 12px;
            opacity: 0.7;
            margin-bottom: 4px;
        }
        .balance-item-value {
            font-size: 18px;
            font-weight: 600;
        }
        .section-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            padding-left: 4px;
        }
        .member-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            animation: fadeInUp 0.5s ease;
        }
        .member-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        .member-name {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a1a;
        }
        .member-desc {
            font-size: 13px;
            color: #999;
            margin-top: 4px;
        }
        .member-tag {
            background: #f5b400;
            color: #1a1a1a;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .member-features {
            background: #f8f8f8;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .feature-item {
            display: flex;
            align-items: center;
            padding: 8px 0;
            font-size: 14px;
            color: #333;
        }
        .feature-item i {
            color: #f5b400;
            margin-right: 10px;
            font-size: 16px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
            color: #1a1a1a;
        }
        .form-select {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #eee;
            border-radius: 12px;
            font-size: 15px;
            background: #fff;
            color: #1a1a1a;
            transition: all 0.3s ease;
        }
        .form-select:focus {
            outline: none;
            border-color: #f5b400;
        }
        .payment-options {
            display: flex;
            gap: 12px;
        }
        .payment-option {
            flex: 1;
            border: 2px solid #eee;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #fff;
        }
        .payment-option.active {
            border-color: #f5b400;
            background: #fff9e6;
        }
        .payment-option i {
            font-size: 24px;
            color: #f5b400;
            margin-bottom: 8px;
        }
        .payment-option-text {
            font-size: 14px;
            font-weight: 500;
            color: #1a1a1a;
        }
        .btn-primary {
            width: 100%;
            padding: 16px;
            background: #1a1a1a;
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: #333333;
            transform: translateY(-2px);
        }
        .btn-primary:active {
            transform: translateY(0);
        }
        .warning-box {
            background: #fff9e6;
            border: 1px solid #f5b400;
            border-radius: 12px;
            padding: 16px;
            margin-top: 16px;
            text-align: center;
        }
        .warning-box p {
            font-size: 13px;
            color: #8a6d00;
            line-height: 1.6;
        }
        .warning-box a {
            color: #1a1a1a;
            font-weight: 600;
            text-decoration: underline;
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
        .member-card:nth-child(2) { animation-delay: 0.1s; }
        .member-card:nth-child(3) { animation-delay: 0.2s; }
        .member-card:nth-child(4) { animation-delay: 0.3s; }
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

    <div class="page-header">
        <button class="back-btn" onclick="history.back()">
            <i class="fas fa-chevron-left"></i>
        </button>
        <div class="page-title">开通会员</div>
        <div style="width: 36px;"></div>
    </div>
    <div class="container">
        <?php if (isset($error_message)): ?>
            <div class="warning-box">
                <p><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?></p>
            </div>
        <?php endif; ?>
        
        <div class="balance-card">
            <h2>我的余额</h2>
            <div class="balance-amount">¥<?= number_format($user['balance'] + $user['deposit'], 2) ?></div>
            <div class="balance-grid">
                <div class="balance-item">
                    <div class="balance-item-label">账户余额</div>
                    <div class="balance-item-value">¥<?= number_format($user['balance'], 2) ?></div>
                </div>
                <div class="balance-item">
                    <div class="balance-item-label">保证金</div>
                    <div class="balance-item-value">¥<?= number_format($user['deposit'], 2) ?></div>
                </div>
            </div>
        </div>
        
        <div class="section-title">选择会员等级</div>
        
        <?php foreach ($member_levels as $level): ?>
            <div class="member-card">
                <div class="member-header">
                    <div>
                    <div class="member-name"><?= htmlspecialchars($level['name']) ?></div>
                    <div class="member-desc"><?= htmlspecialchars($level['description']) ?></div>
                </div>
                <div class="member-tag">推荐</div>
            </div>
            
            <div class="member-features">
                <div class="feature-item">
                    <i class="fas fa-gift"></i>
                    <span>开通赠送积分: <?= $level['points_given'] ?> 分</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-check-circle"></i>
                    <span>最低接单积分: <?= $level['min_points_for_order'] ?> 分</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-wallet"></i>
                    <span>最低提现积分: <?= $level['min_points_for_withdraw'] ?> 分</span>
                </div>
            </div>
            
            <form method="post" onsubmit="return validateForm(this)">
                <input type="hidden" name="member_level_id" value="<?= $level['id'] ?>">
                
                <div class="form-group">
                    <label class="form-label">选择期限</label>
                    <select name="period" class="form-select" required>
                        <?php if ($level['daily_price'] > 0): ?>
                            <option value="daily" data-price="<?= $level['daily_price'] ?>">日付 ¥<?= number_format($level['daily_price'], 2) ?></option>
                        <?php endif; ?>
                        <?php if ($level['weekly_price'] > 0): ?>
                            <option value="weekly" data-price="<?= $level['weekly_price'] ?>">周付 ¥<?= number_format($level['weekly_price'], 2) ?></option>
                        <?php endif; ?>
                        <?php if ($level['monthly_price'] > 0): ?>
                            <option value="monthly" data-price="<?= $level['monthly_price'] ?>" selected>月付 ¥<?= number_format($level['monthly_price'], 2) ?></option>
                        <?php endif; ?>
                        <?php if ($level['quarterly_price'] > 0): ?>
                            <option value="quarterly" data-price="<?= $level['quarterly_price'] ?>">季度 ¥<?= number_format($level['quarterly_price'], 2) ?></option>
                        <?php endif; ?>
                        <?php if ($level['yearly_price'] > 0): ?>
                            <option value="yearly" data-price="<?= $level['yearly_price'] ?>">年度 ¥<?= number_format($level['yearly_price'], 2) ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">支付方式</label>
                    <div class="payment-options">
                        <label class="payment-option active" onclick="selectPayment(this, 'balance')">
                            <input type="radio" name="payment_method" value="balance" checked style="display:none;">
                            <i class="fas fa-wallet"></i>
                            <div class="payment-option-text">账户余额</div>
                        </label>
                        <label class="payment-option" onclick="selectPayment(this, 'deposit')">
                            <input type="radio" name="payment_method" value="deposit" style="display:none;">
                            <i class="fas fa-shield-alt"></i>
                            <div class="payment-option-text">保证金</div>
                        </label>
                    </div>
                </div>
                
                <button class="btn-primary" name="purchase" type="submit">
                    <i class="fas fa-crown"></i> 立即开通
                </button>
                
                <div class="warning-box">
                    <p>请注意！没有余额或保证金点开通会报错！<br>
                    若您余额不足，请<a href="bzj/index.php">点我充值余额</a></p>
                </div>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    
    <script>
        function selectPayment(element, method) {
            document.querySelectorAll('.payment-option').forEach(opt => opt.classList.remove('active'));
            element.classList.add('active');
            element.querySelector('input[type="radio"]').checked = true;
        }
        
        function validateForm(form) {
            const paymentMethod = form.querySelector('input[name="payment_method"]:checked');
            const selectElement = form.querySelector('select[name="period"]');
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const price = parseFloat(selectedOption.getAttribute('data-price') || 0);
            
            const balance = <?= $user['balance'] ?>;
            const deposit = <?= $user['deposit'] ?>;
            
            if (paymentMethod.value === 'balance') {
                if (balance < price) {
                    alert('账户余额不足，请充值后再试！');
                    return false;
                }
            } else {
                if (deposit < price) {
                    alert('保证金余额不足，请充值后再试！');
                    return false;
                }
            }
            
            return true;
        }
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
<?php include 'footer.php'; ?>
