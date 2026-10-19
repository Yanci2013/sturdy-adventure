<?php
// withdrawal.php

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
$error = '';
$errors = [];

// 获取用户数据
$user = getUserById($pdo, $user_id);
if (!$user) {
    // 如果获取用户数据失败，可能是用户不存在或已被删除
    session_destroy(); // 清除会话
    header('Location: login.php');
    exit;
}
// 获取提现记录
$withdrawals = getUserWithdrawals($pdo, $user_id);

// 获取资金明细（保证金记录）
$deposits = getFundDetails($pdo, $user_id, 10, 'month');

// 获取系统设置
$settings = getSystemSettings($pdo);
$withdrawal_fee_rate = $settings['withdrawal_fee_rate'] ?? 5.00; // 默认5%手续费
$min_withdrawal = $settings['min_withdrawal_amount'] ?? 100.00; // 最低提现金额

// 预填充表单字段
$form_data = [
    'alipay_account' => $user['alipay_account'] ?? '',
    'real_name' => $user['real_name'] ?? ''
];

// 处理提现请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_withdrawal'])) {
    $amount = floatval($_POST['amount']);
    $alipay_account = trim($_POST['alipay_account']);
    $real_name = trim($_POST['real_name']);
    
    // 验证逻辑
    if (!preg_match('/^1[3-9]\d{9}$/', $alipay_account)) {
        $errors[] = "支付宝账号必须为有效的手机号";
    }

    if (!preg_match('/^[\x{4e00}-\x{9fa5}a-zA-Z\s]+$/u', $real_name)) {
        $errors[] = "真实姓名只能包含中文、英文或空格";
    }

    if ($amount <= 0) {
        $errors[] = "提现金额必须大于0";
    } elseif ($amount > $user['balance']) {
        $errors[] = "提现金额不能超过可用余额";
    }
    
    if ($amount < $min_withdrawal) {
        $errors[] = "提现金额不能少于¥" . number_format($min_withdrawal, 2);
    }
    
    if (empty($errors)) {
        // 执行提现操作
        $fee = $amount * $withdrawal_fee_rate / 100;
        $actual_amount = $amount - $fee;
        
        try {
            $pdo->beginTransaction();
            
            // 插入提现记录
            $stmt = $pdo->prepare("INSERT INTO withdrawals 
                (user_id, amount, fee, actual_amount, alipay_account, real_name, status, created_at) 
                VALUES (:user_id, :amount, :fee, :actual_amount, :alipay_account, :real_name, :status, NOW())");
            
            $status = 'pending';
            $stmt->execute([
                ':user_id' => $user_id,
                ':amount' => $amount,
                ':fee' => $fee,
                ':actual_amount' => $actual_amount,
                ':alipay_account' => $alipay_account,
                ':real_name' => $real_name,
                ':status' => $status
            ]);
            
            // 更新用户余额
            $new_balance = $user['balance'] - $amount;
            $stmt = $pdo->prepare("UPDATE users SET balance = :balance WHERE id = :user_id");
            $stmt->execute([
                ':balance' => $new_balance,
                ':user_id' => $user_id
            ]);
            
            // 记录系统日志
            addLog($pdo, $user_id, 'withdrawal_request', 
                "提现申请：金额¥" . number_format($amount, 2) . 
                "，手续费¥" . number_format($fee, 2) . 
                "，实际到账¥" . number_format($actual_amount, 2));
            
            $pdo->commit();
            
            // 重定向避免重复提交
            header('Location: withdrawal.php?withdrawal_success=1');
            exit();
            
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "提现申请失败：" . $e->getMessage();
            error_log("提现错误 - 用户ID:{$user_id} - " . $e->getMessage());
        }
    }
}

// 处理资金转移请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transfer_funds'])) {
    $transfer_type = $_POST['transfer_type']; // deposit_to_balance 或 balance_to_deposit
    $transfer_amount = floatval($_POST['transfer_amount']);
    
    try {
        // 验证转移金额
        if ($transfer_amount <= 0) {
            throw new Exception("转移金额必须大于0");
        }
        
        // 检查用户是否有足够的资金
        if ($transfer_type === 'deposit_to_balance') {
            if ($transfer_amount > $user['deposit']) {
                throw new Exception("转移金额不能超过当前保证金余额");
            }
            
            // 检查7天内是否有接单记录（仅对打手）
            if ($user['user_role'] == 1) { // 1-打手
                $seven_days_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
                $stmt = $pdo->prepare("SELECT COUNT(*) as order_count FROM orders 
                                      WHERE hunter_user_id = ? AND accept_time >= ?");
                $stmt->execute([$user_id, $seven_days_ago]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['order_count'] > 0) {
                    throw new Exception("您最近7天内有接单记录，无法将保证金转移到余额");
                }
            }
        } else {
            if ($transfer_amount > $user['balance']) {
                throw new Exception("转移金额不能超过当前可用余额");
            }
        }
        
        // 开始事务
        $pdo->beginTransaction();
        
        // 执行资金转移
        if ($transfer_type === 'deposit_to_balance') {
            // 保证金转余额
            $stmt = $pdo->prepare("UPDATE users SET deposit = deposit - ?, balance = balance + ? WHERE id = ?");
            $stmt->execute([$transfer_amount, $transfer_amount, $user_id]);
            
            // 记录资金流水
            $stmt = $pdo->prepare("INSERT INTO fund_details 
                                (user_id, source, destination, amount, note, create_time) 
                                VALUES (?, 'deposit', 'balance', ?, '保证金转移到余额', NOW())");
            $stmt->execute([$user_id, $transfer_amount]);
            
            $log_message = "保证金转移到余额：金额¥" . number_format($transfer_amount, 2);
        } else {
            // 余额转保证金
            $stmt = $pdo->prepare("UPDATE users SET balance = balance - ?, deposit = deposit + ? WHERE id = ?");
            $stmt->execute([$transfer_amount, $transfer_amount, $user_id]);
            
            // 记录资金流水
            $stmt = $pdo->prepare("INSERT INTO fund_details 
                                (user_id, source, destination, amount, note, create_time) 
                                VALUES (?, 'balance', 'deposit', ?, '余额转移到保证金', NOW())");
            $stmt->execute([$user_id, $transfer_amount]);
            
            $log_message = "余额转移到保证金：金额¥" . number_format($transfer_amount, 2);
        }
        
        // 记录系统日志
        addLog($pdo, $user_id, 'fund_transfer', $log_message);
        
        $pdo->commit();
        
        // 刷新用户数据
        $user = getUserById($pdo, $user_id);
        
        // 设置成功消息
        $_SESSION['transfer_success'] = "资金转移成功！";
        header('Location: withdrawal.php');
        exit();
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = "资金转移失败：" . $e->getMessage();
        error_log("资金转移错误 - 用户ID:{$user_id} - " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的钱包 - <?php echo getWebsiteTitle($pdo); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* 纯白色简洁风格 - 钱包页面 */
        :root {
            --pure-white: #ffffff;
            --off-white: #fafafa;
            --light-gray: #f5f5f5;
            --border-light: #eaeaea;
            --text-primary: #222222;
            --text-secondary: #666666;
            --text-tertiary: #999999;
            --success: #666666;
            --warning: #666666;
            --danger: #666666;
            --shadow-subtle: 0 1px 3px rgba(0, 0, 0, 0.02);
            --transition-fast: all 0.2s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: url('../assets/1.png') no-repeat center center fixed;
            background-size: cover;
            color: #1d1d1f;
            font-size: 14px;
            line-height: 1.5;
            min-height: 100vh;
            padding: 20px 16px 80px;
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

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border-radius: 16px;
            padding: 16px;
        }

        h2 {
            font-size: 18px;
            font-weight: 500;
            color: #000000;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        /* 钱包卡片 */
        .wallet-card {
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            background: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border-radius: 16px;
        }

        .wallet-card .card-body {
            padding: 20px;
        }

        .balance-card {
            border-left: 2px solid var(--text-tertiary);
        }

        .deposit-card {
            border-left: 2px solid var(--text-tertiary);
        }

        .fee-card {
            border-left: 2px solid var(--text-tertiary);
        }

        .transfer-card {
            border: 1px dashed var(--border-light);
            background: var(--off-white);
        }

        .balance-label {
            font-size: 12px;
            color: var(--text-tertiary);
            margin-bottom: 4px;
        }

        .balance-amount {
            font-size: 20px;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .text-muted {
            font-size: 11px;
            color: var(--text-tertiary);
        }

        /* 操作按钮 */
        .wallet-card .card-title {
            font-size: 15px;
            font-weight: 500;
            margin-bottom: 16px;
            color: var(--text-primary);
        }

        .d-flex {
            display: flex;
        }

        .justify-content-between {
            justify-content: space-between;
        }

        .align-items-center {
            align-items: center;
        }

        .btn {
            display: inline-block;
            padding: 8px 16px;
            border: 1px solid var(--border-light);
            background: var(--pure-white);
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 400;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition-fast);
            margin-right: 8px;
        }

        .btn:last-child {
            margin-right: 0;
        }

        .btn:hover {
            background: var(--off-white);
            border-color: var(--text-tertiary);
            color: var(--text-primary);
        }

        .btn-primary {
            background: var(--pure-white);
        }

        .btn-primary:hover {
            background: var(--off-white);
        }

        .btn-outline-primary {
            background: var(--pure-white);
        }

        /* 警告框 */
        .alert {
            padding: 12px 16px;
            margin-bottom: 20px;
            border: 1px solid var(--border-light);
            font-size: 13px;
            color: var(--text-secondary);
            position: relative;
        }

        .alert-success {
            border-color: var(--border-light);
            background: var(--off-white);
        }

        .alert-danger {
            border-color: var(--border-light);
            background: var(--off-white);
        }

        .alert ul {
            margin: 8px 0 0 20px;
            color: var(--text-tertiary);
        }

        .btn-close {
            position: absolute;
            top: 12px;
            right: 12px;
            background: none;
            border: none;
            font-size: 16px;
            cursor: pointer;
            color: var(--text-tertiary);
        }

        /* 资金转移 */
        .transfer-option {
            cursor: pointer;
            margin-bottom: 12px;
        }

        .transfer-option input:checked + label {
            background: var(--off-white);
            border-color: var(--text-tertiary);
            color: var(--text-primary);
        }

        .transfer-option label {
            display: block;
            padding: 8px 12px;
            text-align: center;
            border: 1px solid var(--border-light);
            background: var(--pure-white);
            font-size: 13px;
            transition: var(--transition-fast);
        }

        .transfer-option label:hover {
            border-color: var(--text-tertiary);
        }

        .input-group {
            display: flex;
            align-items: center;
            border: 1px solid var(--border-light);
            margin-bottom: 10px;
        }

        .input-group-text {
            padding: 0 12px;
            background: var(--off-white);
            color: var(--text-secondary);
            border-right: 1px solid var(--border-light);
            height: 38px;
            line-height: 38px;
        }

        .form-control {
            flex: 1;
            height: 38px;
            border: none;
            padding: 0 12px;
            font-size: 13px;
            color: var(--text-primary);
        }

        .form-control:focus {
            outline: none;
        }

        .form-control::placeholder {
            color: var(--text-tertiary);
        }

        .w-100 {
            width: 100%;
        }

        /* 标签页 */
        .nav-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-light);
            margin-bottom: 16px;
        }

        .nav-item {
            margin-right: 4px;
        }

        .nav-link {
            padding: 8px 16px;
            border: none;
            border-bottom: 2px solid transparent;
            background: none;
            color: var(--text-tertiary);
            font-size: 13px;
            cursor: pointer;
            transition: var(--transition-fast);
        }

        .nav-link:hover {
            color: var(--text-primary);
        }

        .nav-link.active {
            color: var(--text-primary);
            border-bottom-color: var(--text-primary);
            font-weight: 500;
        }

        /* 表格 */
        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .table th {
            background: var(--off-white);
            color: var(--text-secondary);
            font-weight: 500;
            padding: 12px 10px;
            border-bottom: 1px solid var(--border-light);
            text-align: left;
        }

        .table td {
            padding: 10px;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-secondary);
        }

        .text-success {
            color: var(--text-primary);
        }

        .text-danger {
            color: var(--text-secondary);
        }

        .text-center {
            text-align: center;
        }

        .py-3 {
            padding-top: 16px;
            padding-bottom: 16px;
        }

        /* 状态徽章 */
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border: 1px solid var(--border-light);
            font-size: 11px;
            color: var(--text-secondary);
        }

        .status-pending {
            border-color: var(--border-light);
        }

        .status-completed {
            border-color: var(--border-light);
        }

        .status-rejected {
            border-color: var(--border-light);
        }

        /* 拒绝原因按钮 */
        .reject-reason-btn {
            border: 1px solid var(--border-light);
            background: var(--pure-white);
            padding: 2px 6px;
            font-size: 11px;
            cursor: pointer;
            color: var(--text-tertiary);
        }

        .reject-reason-btn:hover {
            background: var(--off-white);
        }

        /* 响应式 */
        @media (max-width: 768px) {
            body {
                padding: 16px 12px 70px;
            }

            h2 {
                font-size: 16px;
                margin-bottom: 16px;
            }

            .wallet-card .card-body {
                padding: 16px;
            }

            .balance-amount {
                font-size: 18px;
            }

            .btn {
                padding: 6px 12px;
                font-size: 12px;
            }

            .row {
                display: flex;
                flex-direction: column;
            }

            .col-md-4 {
                margin-bottom: 12px;
            }

            .col-md-6,
            .col-md-8,
            .col-md-4 {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .table {
                font-size: 12px;
            }

            .table th,
            .table td {
                padding: 8px 6px;
            }

            .nav-link {
                padding: 6px 12px;
                font-size: 12px;
            }

            .transfer-option label {
                padding: 6px 10px;
                font-size: 12px;
            }

            .input-group-text,
            .form-control {
                height: 36px;
            }
        }

        /* 移除所有圆角和渐变 */
        .container,
        .wallet-card,
        .btn,
        .alert,
        .transfer-option label,
        .input-group,
        .nav-link,
        .status-badge,
        .reject-reason-btn {
            border-radius: 0;
            box-shadow: none;
        }

        /* 深色模式适配 */
        @media (prefers-color-scheme: dark) {
            :root {
                --pure-white: #1a1a1a;
                --off-white: #2a2a2a;
                --light-gray: #333333;
                --border-light: #404040;
                --text-primary: #e0e0e0;
                --text-secondary: #b0b0b0;
                --text-tertiary: #808080;
            }
        }
    </style>
    <!-- 引入 Font Awesome 图标库 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

    <div class="container">
        <h2><i class="fas fa-wallet" style="color: var(--text-tertiary); margin-right: 8px;"></i>我的钱包</h2>
        
        <!-- 账户概览 -->
        <div class="row" style="display: flex; gap: 20px; margin-bottom: 20px;">
            <div style="flex: 1;">
                <div class="wallet-card balance-card">
                    <div class="card-body">
                        <div class="balance-label">可用余额</div>
                        <div class="balance-amount">¥<?php echo number_format($user['balance'], 2); ?></div>
                        <div class="text-muted">可随时提现</div>
                    </div>
                </div>
            </div>
            <div style="flex: 1;">
                <div class="wallet-card deposit-card">
                    <div class="card-body">
                        <div class="balance-label">保证金</div>
                        <div class="balance-amount">¥<?php echo number_format($user['deposit'], 2); ?></div>
                        <div class="text-muted">用于保障交易安全</div>
                    </div>
                </div>
            </div>
            <div style="flex: 1;">
                <div class="wallet-card fee-card">
                    <div class="card-body">
                        <div class="balance-label">提现手续费率</div>
                        <div class="balance-amount"><?php echo $withdrawal_fee_rate; ?>%</div>
                        <div class="text-muted">实际到账<?php echo 100 - $withdrawal_fee_rate; ?>%</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 操作按钮 -->
        <div class="wallet-card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title"><i class="fas fa-cog" style="color: var(--text-tertiary); margin-right: 8px;"></i>资金操作</h5>
                    <div>
                        <a href="withdrawal_apply.php" class="btn">
                            <i class="fas fa-credit-card" style="margin-right: 4px;"></i> 提现
                        </a>
                        <a href="./bzj/index.php" class="btn">
                            <i class="fas fa-coins" style="margin-right: 4px;"></i> 充值保证金
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 提现成功提示 -->
        <?php if (isset($_GET['withdrawal_success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle" style="margin-right: 8px;"></i> 提现申请已提交，请等待审核处理！
            </div>
        <?php endif; ?>
        
        <!-- 错误提示 -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i> 操作时遇到问题：
                <ul style="margin-top: 8px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <!-- 资金转移 -->
        <div class="wallet-card transfer-card mb-3">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-exchange-alt" style="color: var(--text-tertiary); margin-right: 8px;"></i>资金转移</h5>
                
                <?php if (isset($_SESSION['transfer_success'])): ?>
                    <div class="alert alert-success" style="margin-bottom: 16px;">
                        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['transfer_success']; ?>
                    </div>
                    <?php unset($_SESSION['transfer_success']); ?>
                <?php endif; ?>
                
                <form method="post">
                    <div style="display: flex; gap: 10px; margin-bottom: 16px;">
                        <div style="flex: 1;">
                            <div class="transfer-option">
                                <input type="radio" id="deposit_to_balance" name="transfer_type" value="deposit_to_balance" checked style="display: none;">
                                <label for="deposit_to_balance">
                                    <i class="fas fa-arrow-right" style="margin-right: 4px;"></i> 保证金转余额
                                </label>
                            </div>
                        </div>
                        <div style="flex: 1;">
                            <div class="transfer-option">
                                <input type="radio" id="balance_to_deposit" name="transfer_type" value="balance_to_deposit" style="display: none;">
                                <label for="balance_to_deposit">
                                    <i class="fas fa-arrow-left" style="margin-right: 4px;"></i> 余额转保证金
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <div style="flex: 1;">
                            <div class="input-group">
                                <span class="input-group-text">¥</span>
                                <input type="number" name="transfer_amount" class="form-control" placeholder="输入转移金额" min="0.01" step="0.01" required>
                            </div>
                        </div>
                        <div style="width: 120px;">
                            <button type="submit" name="transfer_funds" class="btn w-100">
                                <i class="fas fa-check"></i> 确认转移
                            </button>
                        </div>
                    </div>
                    
                    <?php if ($user['user_role'] == 1): ?>
                        <div class="text-muted" style="margin-top: 8px;">
                            <i class="fas fa-info-circle"></i> 打手将保证金转移到余额需要7天内无接单记录
                        </div>
                    <?php else: ?>
                        <div class="text-muted" style="margin-top: 8px;">
                            <i class="fas fa-info-circle"></i> 资金转移即时到账，无需手续费
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <script>
        // 资金转移选项切换
        document.querySelectorAll('.transfer-option input').forEach(radio => {
            radio.addEventListener('change', function() {
                // 更新最大金额
                const amountInput = document.querySelector('input[name="transfer_amount"]');
                if (this.value === 'deposit_to_balance') {
                    amountInput.max = <?php echo $user['deposit']; ?>;
                } else {
                    amountInput.max = <?php echo $user['balance']; ?>;
                }
                
                // 更新选项样式
                document.querySelectorAll('.transfer-option label').forEach(label => {
                    label.classList.remove('bg-primary', 'text-white');
                });
                this.nextElementSibling.style.background = 'var(--off-white)';
                this.nextElementSibling.style.borderColor = 'var(--text-tertiary)';
                this.nextElementSibling.style.color = 'var(--text-primary)';
            });
        });
        
        // 显示拒绝原因
        function showRejectReason(reason) {
            alert('拒绝原因：' + reason);
        }
        
        // 标签页切换
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function() {
                // 移除所有active类
                document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(p => p.style.display = 'none');
                
                // 添加active类到当前标签
                this.classList.add('active');
                
                // 显示对应的tab内容
                const tabId = this.getAttribute('data-tab');
                document.querySelector(`.tab-pane[data-tab="${tabId}"]`).style.display = 'block';
            });
        });
    </script>
    
    <?php include 'footer.php'; ?>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>