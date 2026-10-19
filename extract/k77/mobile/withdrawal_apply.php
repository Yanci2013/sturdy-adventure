<?php
// withdrawal_apply.php

// 开启会话
session_start();

// 引入头部文件
include 'header.php';

// 检查用户是否登录
$user_id = get_user_user_id() ?? 0;
if (!$user_id) {
    header('Location: login.php');
    exit;
}
$errors = [];

// 获取用户数据
$user = getUserById($pdo, $user_id);
if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// 获取系统设置
$settings = getSystemSettings($pdo);
$withdrawal_fee_rate = $settings['withdrawal_fee_rate'] ?? 5.00;
$min_withdrawal = 1.00;

// 定义上传目录
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/assets/images/user/';
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        die("无法创建上传目录，请检查权限");
    }
}

// 处理提现请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_payment_method'])) {
        $payment_method = $_POST['payment_method'] ?? 'wechat';
        $alipay_account = trim($_POST['alipay_account'] ?? '');
        $real_name = trim($_POST['real_name'] ?? '');
        
        if (empty($real_name)) {
            $errors[] = "真实姓名不能为空";
        }
        
        if ($payment_method === 'alipay' && empty($alipay_account)) {
            $errors[] = "支付宝账号不能为空";
        }
        
        $alipay_qrcode = $user['alipay_qrcode'] ?? null;
        $wechat_qrcode = $user['wechat_qrcode'] ?? null;
        
        if (!empty($_FILES['alipay_qrcode']['name'])) {
            $upload = uploadImage($_FILES['alipay_qrcode'], $upload_dir, 1024 * 1024);
            if ($upload['success']) {
                $alipay_qrcode = $upload['file_name'];
                if (!empty($user['alipay_qrcode']) && file_exists($upload_dir . $user['alipay_qrcode'])) {
                    unlink($upload_dir . $user['alipay_qrcode']);
                }
            } else {
                $errors[] = "支付宝收款码上传失败: " . $upload['error'];
            }
        }
        
        if (!empty($_FILES['wechat_qrcode']['name'])) {
            $upload = uploadImage($_FILES['wechat_qrcode'], $upload_dir, 1024 * 1024);
            if ($upload['success']) {
                $wechat_qrcode = $upload['file_name'];
                if (!empty($user['wechat_qrcode']) && file_exists($upload_dir . $user['wechat_qrcode'])) {
                    unlink($upload_dir . $user['wechat_qrcode']);
                }
            } else {
                $errors[] = "微信收款码上传失败: " . $upload['error'];
            }
        }
        
        if (empty($errors)) {
            try {
                $stmt_check = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'payment_method'");
                $stmt_check->execute();
                $column_exists = ($stmt_check->rowCount() > 0);
                
                if ($column_exists) {
                    $stmt = $pdo->prepare("UPDATE users SET 
                        payment_method = ?, 
                        alipay_account = ?, 
                        real_name = ?,
                        alipay_qrcode = ?,
                        wechat_qrcode = ?
                        WHERE id = ?");
                    
                    $stmt->execute([
                        $payment_method,
                        $alipay_account,
                        $real_name,
                        $alipay_qrcode,
                        $wechat_qrcode,
                        $user_id
                    ]);
                } else {
                    $pdo->exec("ALTER TABLE users ADD COLUMN payment_method ENUM('wechat','alipay') DEFAULT 'wechat'");
                    
                    $stmt = $pdo->prepare("UPDATE users SET 
                        alipay_account = ?, 
                        real_name = ?,
                        alipay_qrcode = ?,
                        wechat_qrcode = ?,
                        payment_method = ?
                        WHERE id = ?");
                    
                    $stmt->execute([
                        $alipay_account,
                        $real_name,
                        $alipay_qrcode,
                        $wechat_qrcode,
                        $payment_method,
                        $user_id
                    ]);
                }
                
                $_SESSION['success'] = "收款信息已保存";
                header('Location: withdrawal_apply.php');
                exit;
                
            } catch (PDOException $e) {
                $errors[] = "保存失败: " . $e->getMessage();
                error_log("保存收款信息错误: " . $e->getMessage());
            }
        }
    }
    
    if (isset($_POST['submit_withdrawal'])) {
        $amount = floatval($_POST['amount']);
        $payment_method = $_POST['payment_method'] ?? ($user['payment_method'] ?? 'wechat');
        $real_name = trim($_POST['real_name'] ?? '');
        
        if ($amount <= 0) {
            $errors[] = "提现金额必须大于0";
        } elseif ($amount > $user['balance']) {
            $errors[] = "提现金额不能超过可用余额";
        }
        
        if ($amount < $min_withdrawal) {
            $errors[] = "提现金额不能少于¥" . number_format($min_withdrawal, 2);
        }
        
        if ($payment_method === 'wechat') {
            if (empty($user['wechat_qrcode'])) {
                $errors[] = "请上传微信收款码";
            }
        } else {
            if (empty($user['alipay_account'])) {
                $errors[] = "请设置支付宝账号";
            }
            if (empty($user['alipay_qrcode'])) {
                $errors[] = "请上传支付宝收款码";
            }
        }
        
        if (empty($real_name)) {
            $errors[] = "请填写真实姓名";
        }
        
        if (empty($errors)) {
            $fee = $amount * $withdrawal_fee_rate / 100;
            $actual_amount = $amount - $fee;
            
            try {
                $pdo->beginTransaction();
                
                $withdrawal_no = strtoupper($payment_method) . date('YmdHis') . str_pad($user_id, 6, '0', STR_PAD_LEFT);
                
                $withdrawal_data = [
                    ':user_id' => $user_id,
                    ':amount' => $amount,
                    ':fee' => $fee,
                    ':actual_amount' => $actual_amount,
                    ':real_name' => $real_name,
                    ':withdrawal_no' => $withdrawal_no,
                    ':status' => 'pending',
                    ':payment_method' => $payment_method,
                    ':alipay_qrcode' => $payment_method === 'alipay' ? $user['alipay_qrcode'] : null,
                    ':wechat_qrcode' => $payment_method === 'wechat' ? $user['wechat_qrcode'] : null
                ];
                
                if ($payment_method === 'wechat') {
                    $withdrawal_data[':wechat_openid'] = $user['wechat_openid'] ?? null;
                    $withdrawal_data[':alipay_account'] = null;
                } else {
                    $withdrawal_data[':wechat_openid'] = null;
                    $withdrawal_data[':alipay_account'] = $user['alipay_account'];
                }
                
                $stmt = $pdo->prepare("INSERT INTO withdrawals 
                    (user_id, amount, fee, actual_amount, wechat_openid, alipay_account, real_name, 
                    withdrawal_no, status, payment_method, alipay_qrcode, wechat_qrcode, created_at) 
                    VALUES (:user_id, :amount, :fee, :actual_amount, :wechat_openid, :alipay_account, :real_name, 
                    :withdrawal_no, :status, :payment_method, :alipay_qrcode, :wechat_qrcode, NOW())");
                
                $stmt->execute($withdrawal_data);
                
                $new_balance = $user['balance'] - $amount;
                $stmt = $pdo->prepare("UPDATE users SET balance = :balance WHERE id = :user_id");
                $stmt->execute([
                    ':balance' => $new_balance,
                    ':user_id' => $user_id
                ]);
                
                if (empty($user['real_name'])) {
                    $stmt = $pdo->prepare("UPDATE users SET real_name = ? WHERE id = ?");
                    $stmt->execute([$real_name, $user_id]);
                }
                
                $method_name = $payment_method === 'alipay' ? '支付宝' : '微信';
                addLog($pdo, $user_id, 'withdrawal_request', 
                    "{$method_name}提现申请：单号{$withdrawal_no}，金额¥" . number_format($amount, 2) . 
                    "，手续费¥" . number_format($fee, 2) . 
                    "，实际到账¥" . number_format($actual_amount, 2));
                
                $pdo->commit();
                
                header('Location: withdrawal.php?withdrawal_success=1');
                exit();
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errors[] = "提现申请失败：" . $e->getMessage();
                error_log("提现错误 - 用户ID:{$user_id} - " . $e->getMessage());
            }
        }
    }
}

function uploadImage($file, $target_dir, $max_size) {
    $result = ['success' => false, 'error' => ''];
    
    if (!file_exists($target_dir)) {
        if (!mkdir($target_dir, 0755, true)) {
            $result['error'] = '无法创建上传目录: ' . $target_dir;
            return $result;
        }
    }
    
    if (!is_writable($target_dir)) {
        $result['error'] = '上传目录不可写: ' . $target_dir;
        return $result;
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = '上传错误: ' . $file['error'];
        return $result;
    }
    
    if ($file['size'] > $max_size) {
        $result['error'] = '文件大小不能超过 ' . ($max_size / 1024 / 1024) . 'MB';
        return $result;
    }
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        $result['error'] = '只允许上传JPEG, PNG或GIF图片';
        return $result;
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = uniqid('qr_') . '.' . $ext;
    $target_path = rtrim($target_dir, '/') . '/' . $file_name;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        $result['success'] = true;
        $result['file_name'] = $file_name;
        $result['file_path'] = $target_path;
    } else {
        $error = error_get_last();
        $result['error'] = '文件移动失败: ' . ($error['message'] ?? '未知错误');
    }
    
    return $result;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>提现申请</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: url('../assets/1.png') no-repeat center center fixed;
            background-size: cover;
            color: #1d1d1f;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
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

        .app-container {
            max-width: 100%;
            margin: 0 auto;
            padding: 0 12px;
            padding-bottom: 80px;
            animation: fadeInUp 0.6s ease-out;
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
            padding: 12px 12px;
            background: #ffffff;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .back-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f5f5f5;
            border: none;
            color: #1d1d1f;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .back-btn:hover {
            background: #e8e8e8;
            transform: scale(1.1);
        }

        .header-title {
            flex: 1;
            text-align: center;
            font-size: 18px;
            font-weight: 600;
            color: #000000;
        }

        .header-right {
            width: 40px;
        }

        .card {
            background: #ffffff;
            border-radius: 16px;
            padding: 16px;
            margin: 12px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: #000000;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title i {
            color: #f5b400;
        }

        .balance-card {
            background: linear-gradient(135deg, #f5b400 0%, #e6a800 100%);
            color: #ffffff;
        }

        .balance-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 8px;
        }

        .balance-amount {
            font-size: 32px;
            font-weight: 700;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #000000;
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #eaeaea;
            border-radius: 12px;
            font-size: 14px;
            color: #000000;
            background: #fafafa;
            transition: all 0.2s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #f5b400;
            box-shadow: 0 0 0 3px rgba(245, 180, 0, 0.1);
            background: #ffffff;
        }

        .form-input::placeholder {
            color: #cccccc;
        }

        .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #eaeaea;
            border-radius: 12px;
            font-size: 14px;
            color: #000000;
            background: #fafafa;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .form-select:focus {
            outline: none;
            border-color: #f5b400;
            box-shadow: 0 0 0 3px rgba(245, 180, 0, 0.1);
            background: #ffffff;
        }

        .input-group {
            display: flex;
            align-items: center;
            border: 1px solid #eaeaea;
            border-radius: 12px;
            overflow: hidden;
        }

        .input-group-prefix {
            padding: 12px 16px;
            background: #f5f5f5;
            color: #666666;
            font-weight: 600;
            border-right: 1px solid #eaeaea;
        }

        .input-group .form-input {
            border: none;
            border-radius: 0;
            flex: 1;
        }

        .form-text {
            font-size: 12px;
            color: #999999;
            margin-top: 6px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: #000000;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #333333;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #666666;
        }

        .btn-secondary:hover {
            background: #e8e8e8;
            transform: translateY(-2px);
        }

        .btn-block {
            width: 100%;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 16px;
        }

        .btn-group .btn {
            flex: 1;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 14px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        .alert-info {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1d4ed8;
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #86efac;
            color: #16a34a;
        }

        .alert i {
            font-size: 18px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .alert ul {
            margin: 6px 0 0 20px;
        }

        .alert li {
            margin-bottom: 4px;
        }

        .qrcode-preview {
            max-width: 120px;
            max-height: 120px;
            border-radius: 8px;
            border: 1px solid #eaeaea;
            margin-top: 8px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f5f5f5;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #666666;
            font-size: 14px;
        }

        .info-value {
            color: #000000;
            font-size: 14px;
            font-weight: 500;
        }

        .info-value.highlight {
            color: #f5b400;
            font-size: 16px;
            font-weight: 600;
        }

        .divider {
            height: 1px;
            background: #f5f5f5;
            margin: 16px 0;
        }

        .section-title {
            font-size: 15px;
            font-weight: 600;
            color: #000000;
            margin: 20px 0 12px 0;
            padding-left: 0;
        }

        ::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: #dddddd;
            border-radius: 2px;
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

    <header class="header">
        <div class="header-content">
            <h1 class="header-title">提现申请</h1>
            <div class="header-right"></div>
        </div>
    </header>

    <main class="app-container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($_SESSION['success']); ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>操作失败：</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <div class="card balance-card">
            <div class="balance-label">可用余额</div>
            <div class="balance-amount">¥<?php echo number_format($user['balance'], 2); ?></div>
        </div>

        <div class="section-title">收款信息设置</div>
        <div class="card">
            <div class="card-title">
                <i class="fas fa-cog"></i>
                设置收款方式
            </div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="save_payment_method" value="1">
                
                <div class="form-group">
                    <label class="form-label">真实姓名</label>
                    <input type="text" class="form-input" name="real_name" 
                           value="<?php echo htmlspecialchars($user['real_name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">默认提现方式</label>
                    <select class="form-select" name="payment_method">
                        <option value="wechat" <?php echo ($user['payment_method'] ?? 'wechat') === 'wechat' ? 'selected' : ''; ?>>微信钱包</option>
                        <option value="alipay" <?php echo ($user['payment_method'] ?? '') === 'alipay' ? 'selected' : ''; ?>>支付宝</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">支付宝账号</label>
                    <input type="text" class="form-input" name="alipay_account" 
                           value="<?php echo htmlspecialchars($user['alipay_account'] ?? ''); ?>" placeholder="用于支付宝提现">
                </div>
                
                <div class="form-group">
                    <label class="form-label">支付宝收款码</label>
                    <input type="file" class="form-input" name="alipay_qrcode" accept="image/*">
                    <?php if (!empty($user['alipay_qrcode'])): ?>
                        <img src="/assets/images/user/<?php echo htmlspecialchars($user['alipay_qrcode']); ?>" class="qrcode-preview">
                        <div class="form-text">当前支付宝收款码</div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label class="form-label">微信收款码</label>
                    <input type="file" class="form-input" name="wechat_qrcode" accept="image/*">
                    <?php if (!empty($user['wechat_qrcode'])): ?>
                        <img src="/assets/images/user/<?php echo htmlspecialchars($user['wechat_qrcode']); ?>" class="qrcode-preview">
                        <div class="form-text">当前微信收款码</div>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-check"></i>
                    保存收款信息
                </button>
            </form>
        </div>

        <div class="section-title">费用说明</div>
        <div class="card">
            <div class="info-row">
                <span class="info-label">提现手续费率</span>
                <span class="info-value"><?php echo $withdrawal_fee_rate; ?>%</span>
            </div>
            <div class="info-row">
                <span class="info-label">最低提现金额</span>
                <span class="info-value">¥<?php echo number_format($min_withdrawal, 2); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">实际到账比例</span>
                <span class="info-value"><?php echo 100 - $withdrawal_fee_rate; ?>%</span>
            </div>
            <div class="info-row">
                <span class="info-label">预计到账时间</span>
                <span class="info-value">1-3个工作日</span>
            </div>
        </div>

        <div class="section-title">提现申请</div>
        <div class="card">
            <div class="card-title">
                <i class="fas fa-credit-card"></i>
                申请提现
            </div>
            
            <div class="info-row">
                <span class="info-label">当前提现方式</span>
                <span class="info-value"><?php echo ($user['payment_method'] ?? 'wechat') === 'wechat' ? '微信钱包' : '支付宝'; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">收款人</span>
                <span class="info-value"><?php echo htmlspecialchars($user['real_name'] ?? '未设置'); ?></span>
            </div>
            
            <div class="divider"></div>
            
            <form method="post">
                <input type="hidden" name="submit_withdrawal" value="1">
                
                <div class="form-group">
                    <label class="form-label">真实姓名</label>
                    <input type="text" class="form-input" name="real_name" 
                           value="<?php echo htmlspecialchars($user['real_name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">提现方式</label>
                    <select class="form-select" name="payment_method" required>
                        <option value="wechat" <?php echo ($user['payment_method'] ?? 'wechat') === 'wechat' ? 'selected' : ''; ?>>微信钱包</option>
                        <option value="alipay" <?php echo ($user['payment_method'] ?? '') === 'alipay' ? 'selected' : ''; ?>>支付宝</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">提现金额</label>
                    <div class="input-group">
                        <span class="input-group-prefix">¥</span>
                        <input type="number" class="form-input" id="amount" name="amount" 
                               min="<?php echo $min_withdrawal; ?>" 
                               step="0.01" 
                               max="<?php echo $user['balance']; ?>" 
                               placeholder="请输入提现金额"
                               required>
                    </div>
                    <div class="form-text">
                        可提现范围：¥<?php echo number_format($min_withdrawal, 2); ?> - ¥<?php echo number_format($user['balance'], 2); ?>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    提现金额将扣除<?php echo $withdrawal_fee_rate; ?>%手续费后转入您选择的账户
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i>
                        提交申请
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        document.getElementById('amount').addEventListener('input', function() {
            const amount = parseFloat(this.value);
            const min = <?php echo $min_withdrawal; ?>;
            const max = <?php echo $user['balance']; ?>;
            
            if (isNaN(amount) || amount < min) {
                this.setCustomValidity(`提现金额不能少于¥${min.toFixed(2)}`);
            } else if (amount > max) {
                this.setCustomValidity(`提现金额不能超过¥${max.toFixed(2)}`);
            } else {
                this.setCustomValidity('');
            }
        });
        
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.createElement('img');
                    preview.src = e.target.result;
                    preview.className = 'qrcode-preview';
                    
                    const container = this.parentElement;
                    const oldPreview = container.querySelector('.qrcode-preview');
                    if (oldPreview) {
                        oldPreview.replaceWith(preview);
                    } else {
                        container.appendChild(preview);
                    }
                }.bind(this);
                reader.readAsDataURL(file);
            });
        });
    </script>

    <?php include 'footer.php'; ?>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
