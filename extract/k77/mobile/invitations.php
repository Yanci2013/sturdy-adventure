<?php
require_once 'header.php';

// 获取用户ID
$user_id = get_user_user_id() ?? 0;

// 检查用户权限 - 只有管事(user_role=2)才能生成邀请码
if ($_SESSION['user_user_role'] != 2) {
    $_SESSION['error'] = "您没有生成邀请码的权限";
    header("Location: index.php");
    exit();
}

// 生成CSRF令牌
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 处理删除邀请码请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_invite'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "CSRF令牌验证失败";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    $invite_id = intval($_POST['invite_id']);
    
    try {
        // 开始事务
        $pdo->beginTransaction();
        
        // 获取邀请码信息
        $stmt = $pdo->prepare("SELECT * FROM invitations WHERE id = ? AND generator_id = ?");
        $stmt->execute([$invite_id, $user_id]);
        $invite = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invite) {
            throw new Exception("邀请码不存在或无权操作");
        }
        
        // 如果邀请码有赠送保证金且未使用，返还保证金
        if ($invite['gift_deposit'] > 0 && $invite['status'] == 'active') {
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$invite['gift_deposit'], $user_id]);
            
            // 记录资金明细
            $stmt = $pdo->prepare("INSERT INTO fund_details 
                                 (user_id, source, destination, amount, note, create_time) 
                                 VALUES (?, 'invitation_refund', 'balance', ?, ?, NOW())");
            $stmt->execute([
                $user_id,
                $invite['gift_deposit'],
                "删除邀请码返还保证金: {$invite['code']}"
            ]);
        }
        
        // 删除邀请码
        $stmt = $pdo->prepare("DELETE FROM invitations WHERE id = ?");
        $stmt->execute([$invite_id]);
        
        $pdo->commit();
        
        $_SESSION['success'] = "邀请码已成功删除" . ($invite['gift_deposit'] > 0 && $invite['status'] == 'active' ? "，已返还 {$invite['gift_deposit']} 元保证金" : "");
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "删除邀请码失败: " . $e->getMessage();
        error_log("删除邀请码错误: " . $e->getMessage());
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// 获取当前管事用户生成的邀请码列表
$invites = [];
$query = "SELECT i.*, u.username as used_by 
          FROM invitations i 
          LEFT JOIN users u ON i.user_id = u.id 
          WHERE generator_type = 'partner' AND generator_id = ? 
          ORDER BY created_at DESC LIMIT 100";
$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$invites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 统计邀请码状态 - 初始化所有计数变量
$active_count = 0;
$used_count = 0;
$expired_count = 0;

foreach ($invites as $invite) {
    if ($invite['status'] == 'active') $active_count++;
    if ($invite['status'] == 'used') $used_count++;
    if ($invite['status'] == 'expired') $expired_count++;
}

// 处理生成邀请码请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_invite'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "CSRF令牌验证失败";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    $quantity = intval($_POST['quantity']);
    $register_type = 1; // 固定为打手注册类型
    $gift_deposit = isset($_POST['gift_deposit']) ? floatval($_POST['gift_deposit']) : 0.00;
    
    // 验证数量
    if ($quantity < 1 || $quantity > 100) {
        $_SESSION['error'] = "生成数量必须在1-100之间";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    // 验证保证金金额
    if ($gift_deposit < 0 || $gift_deposit > 10000) {
        $_SESSION['error'] = "保证金金额必须在0-10000之间";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    // 检查管事余额是否足够
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_deposit = $gift_deposit * $quantity;
    if ($user['balance'] < $total_deposit) {
        $_SESSION['error'] = "您的余额不足，无法生成带有保证金的邀请码。需要扣除 {$total_deposit} 元，当前余额 {$user['balance']} 元";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    $success_count = 0;
    $generated_codes = [];
    $generator_type = 'partner'; // 生成者类型设为partner
    $generator_id = $user_id; // 当前管事用户ID
    
    // 开始事务
    $pdo->beginTransaction();
    
    try {
        // 扣除管事余额
        if ($total_deposit > 0) {
            $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
            $stmt->execute([$total_deposit, $generator_id]);
            
            // 记录资金明细
            $stmt = $pdo->prepare("INSERT INTO fund_details 
                                 (user_id, source, destination, amount, note, create_time) 
                                 VALUES (?, 'balance', 'invitation_deposit', ?, ?, NOW())");
            $stmt->execute([
                $generator_id, 
                -$total_deposit, 
                "生成 {$quantity} 个邀请码预存保证金 {$gift_deposit} 元/个"
            ]);
        }
        
        // 生成邀请码
        for ($i = 0; $i < $quantity; $i++) {
            $code = generateRandomString(16);
            
            $stmt = $pdo->prepare("INSERT INTO invitations 
                                  (code, generator_type, generator_id, register_type, status, 
                                   gift_deposit, deposit_deducted) 
                                  VALUES (?, ?, ?, ?, 'active', ?, ?)");
            
            if ($stmt->execute([$code, $generator_type, $generator_id, $register_type, $gift_deposit, ($gift_deposit > 0 ? 1 : 0)])) {
                $success_count++;
                $generated_codes[] = $code;
            } else {
                throw new Exception("执行失败: " . implode(" ", $stmt->errorInfo()));
            }
        }
        
        $pdo->commit();
        $_SESSION['success'] = "成功生成 {$success_count} 个打手注册邀请码" . ($gift_deposit > 0 ? "，每个将赠送 {$gift_deposit} 元保证金" : "");
        $_SESSION['generated_codes'] = $generated_codes;
        
        // 记录日志
        logAction($generator_id, "生成邀请码", "生成了 {$success_count} 个打手注册邀请码" . ($gift_deposit > 0 ? "，每个将赠送 {$gift_deposit} 元保证金" : ""));
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "生成邀请码时出错: " . $e->getMessage();
        error_log("生成邀请码错误: " . $e->getMessage());
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// 生成随机字符串函数
function generateRandomString($length = 16) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

// 记录日志函数
function logAction($user_id, $action, $details) {
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $pdo->prepare("INSERT INTO logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $details, $ip]);
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>邀请码管理</title>
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
            background: #f5f5f5;
            color: #1d1d1f;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .app-container {
            max-width: 100%;
            margin: 0 auto;
            padding: 12px;
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
            padding: 16px 12px;
            background: #ffffff;
            border-bottom: 1px solid #eaeaea;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-title {
            font-size: 18px;
            font-weight: 600;
            color: #1d1d1f;
        }

        .message {
            padding: 16px;
            border-radius: 16px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            background: #ffffff;
            border: 1px solid #eaeaea;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .message.error {
            border-left: 4px solid #ff4444;
        }

        .message.success {
            border-left: 4px solid #4CAF50;
        }

        .message i {
            font-size: 20px;
        }

        .message.error i {
            color: #ff4444;
        }

        .message.success i {
            color: #4CAF50;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 12px;
        }

        .stat-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px solid #eaeaea;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }

        .stat-icon {
            font-size: 24px;
            margin-bottom: 8px;
            color: #f5b400;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: #1d1d1f;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 13px;
            color: #999999;
        }

        .form-section {
            background: #ffffff;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 12px;
            border: 1px solid #eaeaea;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .form-section-title {
            font-size: 16px;
            font-weight: 600;
            color: #1d1d1f;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-section-title i {
            color: #f5b400;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
            color: #666666;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid #eaeaea;
            background: #f5f5f5;
            font-size: 14px;
            color: #1d1d1f;
            transition: all 0.2s ease;
        }

        .form-input:focus {
            outline: none;
            background: #ffffff;
            border-color: #f5b400;
            box-shadow: 0 0 0 3px rgba(245, 180, 0, 0.1);
        }

        .form-help {
            font-size: 12px;
            color: #999999;
            margin-top: 6px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 24px;
            border: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: #000000;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #333333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 13px;
            border-radius: 20px;
        }

        .btn-danger {
            background: #ff4444;
            color: #ffffff;
        }

        .btn-danger:hover {
            background: #cc0000;
        }

        .generated-codes {
            margin-top: 20px;
            padding: 20px;
            background: #fafafa;
            border-radius: 16px;
            border: 1px solid #eaeaea;
        }

        .generated-codes-title {
            font-size: 16px;
            font-weight: 600;
            color: #1d1d1f;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .generated-codes-title i {
            color: #f5b400;
        }

        .code-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            margin-bottom: 10px;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #eaeaea;
            font-family: 'Courier New', monospace;
            transition: all 0.2s ease;
        }

        .code-item:hover {
            border-color: #f5b400;
            box-shadow: 0 2px 8px rgba(245, 180, 0, 0.1);
        }

        .code-item span {
            color: #1d1d1f;
            font-weight: 500;
        }

        .table-section {
            background: #ffffff;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 12px;
            border: 1px solid #eaeaea;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .table-section-title {
            font-size: 16px;
            font-weight: 600;
            color: #1d1d1f;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-section-title i {
            color: #f5b400;
        }

        .table-section-title span {
            font-size: 13px;
            color: #999999;
            margin-left: 8px;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: #ffffff;
        }

        th {
            background: #fafafa;
            font-weight: 600;
            font-size: 13px;
            color: #666666;
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #eaeaea;
        }

        th:first-child {
            border-radius: 12px 0 0 0;
        }

        th:last-child {
            border-radius: 0 12px 0 0;
        }

        td {
            padding: 14px 12px;
            color: #1d1d1f;
            border-bottom: 1px solid #f0f0f0;
        }

        tr:hover td {
            background: #fafafa;
        }

        tr:last-child td:first-child {
            border-radius: 0 0 0 12px;
        }

        tr:last-child td:last-child {
            border-radius: 0 0 12px 0;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-active {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-used {
            background: #e3f2fd;
            color: #1565c0;
        }

        .status-expired {
            background: #fce4ec;
            color: #c62828;
        }

        .invite-code {
            cursor: pointer;
            color: #1d1d1f;
            font-family: 'Courier New', monospace;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .invite-code:hover {
            color: #f5b400;
            text-decoration: underline;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999999;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            color: #cccccc;
        }

        .empty-state div {
            margin-bottom: 8px;
            font-size: 15px;
        }

        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 24px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 1000;
            transform: translateX(120%);
            transition: transform 0.3s ease;
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast i {
            font-size: 20px;
        }

        .toast.success i {
            color: #4CAF50;
        }

        .toast.error i {
            color: #ff4444;
        }

        .toast span {
            font-size: 14px;
            color: #1d1d1f;
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: 16px;
            }

            .stat-value {
                font-size: 20px;
            }

            .form-section,
            .table-section {
                padding: 16px;
            }

            th, td {
                padding: 12px 8px;
                font-size: 12px;
            }

            .btn-sm {
                padding: 6px 12px;
                font-size: 12px;
            }
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
        <div class="header-title">
            <i class="fas fa-key" style="color: #f5b400; margin-right: 8px;"></i>
            邀请码管理
        </div>
    </header>

    <main class="app-container">
        <!-- 错误/成功消息 -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['error']); ?></span>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['success']); ?></span>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <!-- 状态统计卡片 -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-key"></i>
                </div>
                <div class="stat-value"><?= count($invites) ?></div>
                <div class="stat-label">总计生成</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?= $active_count ?></div>
                <div class="stat-label">可用邀请码</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user"></i>
                </div>
                <div class="stat-value"><?= $used_count ?></div>
                <div class="stat-label">已使用</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?= $expired_count ?></div>
                <div class="stat-label">已过期</div>
            </div>
        </div>

        <!-- 生成邀请码表单 -->
        <div class="form-section">
            <div class="form-section-title">
                <i class="fas fa-plus-circle"></i>
                生成打手注册邀请码
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="register_type" value="1">
                
                <div class="form-group">
                    <label class="form-label">生成数量</label>
                    <input type="number" name="quantity" class="form-input" placeholder="请输入生成数量" 
                           value="5" min="1" max="100" required>
                    <div class="form-help">一次最多可生成100个打手注册邀请码</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">赠送保证金</label>
                    <input type="number" name="gift_deposit" class="form-input" placeholder="请输入赠送保证金金额" 
                           value="0.00" min="0" max="10000" step="0.01">
                    <div class="form-help">将从您的余额中扣除相应金额</div>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="generate_invite" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-plus"></i> 生成打手邀请码
                    </button>
                </div>
            </form>
            
            <!-- 显示生成的邀请码 -->
            <?php if (isset($_SESSION['generated_codes'])): ?>
                <div class="generated-codes">
                    <div class="generated-codes-title">
                        <i class="fas fa-key"></i>
                        最新生成的打手邀请码
                    </div>
                    <?php foreach ($_SESSION['generated_codes'] as $code): ?>
                        <div class="code-item">
                            <span><?= htmlspecialchars($code) ?></span>
                            <button class="btn btn-sm btn-primary copy-btn" data-code="<?= htmlspecialchars($code) ?>">
                                <i class="fas fa-copy"></i> 复制
                            </button>
                        </div>
                    <?php endforeach; ?>
                    <button class="btn btn-sm" id="copyAllBtn" style="margin-top: 10px; width: 100%;">
                        <i class="fas fa-copy"></i> 复制全部
                    </button>
                </div>
                <?php unset($_SESSION['generated_codes']); ?>
            <?php endif; ?>
        </div>

        <!-- 邀请码列表 -->
        <div class="table-section">
            <div class="table-section-title">
                <i class="fas fa-list"></i>
                我生成的打手邀请码
                <span>共 <?= count($invites) ?> 个</span>
            </div>
            <?php if (count($invites) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>邀请码</th>
                                <th>状态</th>
                                <th>使用者</th>
                                <th>赠送保证金</th>
                                <th>生成时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invites as $invite): ?>
                                <tr>
                                    <td>
                                        <span class="invite-code" onclick="copyToClipboard('<?= htmlspecialchars($invite['code']) ?>')">
                                            <?= htmlspecialchars($invite['code']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($invite['status'] == 'active'): ?>
                                            <span class="status-badge status-active">可用</span>
                                        <?php elseif ($invite['status'] == 'used'): ?>
                                            <span class="status-badge status-used">已使用</span>
                                        <?php else: ?>
                                            <span class="status-badge status-expired">已过期</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $invite['used_by'] ? htmlspecialchars($invite['used_by']) : '-' ?></td>
                                    <td><?= $invite['gift_deposit'] > 0 ? $invite['gift_deposit'] . ' 元' : '无' ?></td>
                                    <td><?= date('Y-m-d H:i', strtotime($invite['created_at'])) ?></td>
                                    <td>
                                        <button class="btn btn-sm" onclick="copyToClipboard('<?= htmlspecialchars($invite['code']) ?>')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                        <?php if ($invite['status'] != 'used'): ?>
                                            <form method="POST" style="display: inline-block; margin-left: 4px;">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="invite_id" value="<?= $invite['id'] ?>">
                                                <button type="submit" name="delete_invite" class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('确定要删除此邀请码吗？<?= $invite['gift_deposit'] > 0 && $invite['status'] == 'active' ? "将返还 {$invite['gift_deposit']} 元到您的余额" : "" ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <div>暂无邀请码数据</div>
                    <div style="font-size: 13px; color: #999;">请点击上方按钮生成打手注册邀请码</div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <div id="toast" class="toast">
        <i class="fas fa-check-circle"></i>
        <span id="toastMessage"></span>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                showToast('邀请码已复制到剪贴板', 'success');
            }, function(err) {
                showToast('复制失败: ' + err, 'error');
            });
        }

        function showToast(message, type) {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');
            const icon = toast.querySelector('i');
            
            toastMessage.textContent = message;
            
            if (type === 'success') {
                icon.className = 'fas fa-check-circle';
                toast.className = 'toast success show';
            } else {
                icon.className = 'fas fa-exclamation-circle';
                toast.className = 'toast error show';
            }
            
            setTimeout(() => {
                toast.className = 'toast';
            }, 3000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const copyBtns = document.querySelectorAll('.copy-btn');
            copyBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const code = this.getAttribute('data-code');
                    copyToClipboard(code);
                });
            });
            
            const copyAllBtn = document.getElementById('copyAllBtn');
            if (copyAllBtn) {
                copyAllBtn.addEventListener('click', function() {
                    const codes = [];
                    const codeItems = document.querySelectorAll('.code-item span');
                    codeItems.forEach(el => {
                        codes.push(el.textContent);
                    });
                    
                    const text = codes.join('\n');
                    copyToClipboard(text);
                });
            }
            
            const cards = document.querySelectorAll('.stat-card, .form-section, .table-section');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 50 + index * 50);
            });
        });
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
<?php require_once 'footer.php'; ?>
