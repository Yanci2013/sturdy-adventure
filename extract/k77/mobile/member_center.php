<?php
require_once 'header.php';

$user_id = get_user_user_id() ?? 0;
if (!$user_id) {
    header('Location: login.php');
    exit;
}
try {
    $stmt = $pdo->prepare("SELECT u.*, ml.name as member_level_name 
                          FROM users u 
                          LEFT JOIN member_levels ml ON u.member_level_id = ml.id 
                          WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("用户不存在");
    }
    
    $stmt = $pdo->prepare("SELECT * FROM fund_details 
                          WHERE user_id = ? AND (source = 'balance' OR source = 'deposit') AND destination = 'system_income'
                          ORDER BY create_time DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("获取用户信息失败: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>会员中心</title>
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

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }

        .info-item {
            padding: 12px 0;
            border-bottom: 1px solid #f5f5f5;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #999999;
            font-size: 14px;
        }

        .info-value {
            color: #000000;
            font-size: 14px;
            font-weight: 500;
        }

        .info-value.highlight {
            color: #f5b400;
            font-size: 18px;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }

        .empty-icon {
            font-size: 48px;
            color: #cccccc;
            margin-bottom: 12px;
        }

        .empty-text {
            color: #999999;
            font-size: 14px;
            margin-bottom: 16px;
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

        .btn-block {
            width: 100%;
        }

        .alert-success {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            background: #f0fdf4;
            border: 1px solid #86efac;
            color: #16a34a;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success i {
            font-size: 18px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .table th {
            background: #fafafa;
            color: #666666;
            font-weight: 500;
            padding: 12px 10px;
            border-bottom: 1px solid #eaeaea;
            text-align: left;
        }

        .table td {
            padding: 10px;
            border-bottom: 1px solid #eaeaea;
            color: #666666;
        }

        .table tbody tr:hover {
            background: #fafafa;
        }

        .text-center {
            text-align: center;
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
            <a href="profile2.php" class="back-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <h1 class="header-title">会员中心</h1>
            <div class="header-right"></div>
        </div>
    </header>

    <main class="app-container">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($_SESSION['success_message']); ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <div class="info-grid">
            <div class="card">
                <div class="card-title">
                    <i class="fas fa-crown"></i>
                    会员信息
                </div>
                <?php if ($user['member_level_id']): ?>
                    <div class="info-item">
                        <span class="info-label">会员等级</span>
                        <span class="info-value highlight"><?php echo htmlspecialchars($user['member_level_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">会员状态</span>
                        <span class="info-value"><?php echo $user['is_trial_member'] ? '体验会员' : '正式会员'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">开通时间</span>
                        <span class="info-value"><?php echo date('Y-m-d H:i', strtotime($user['member_start_time'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">到期时间</span>
                        <span class="info-value"><?php echo date('Y-m-d H:i', strtotime($user['member_end_time'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">会员积分</span>
                        <span class="info-value highlight"><?php echo $user['member_points']; ?></span>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-crown"></i>
                        </div>
                        <div class="empty-text">您当前还不是会员</div>
                        <a href="purchase_member.php" class="btn btn-primary btn-block">
                            <i class="fas fa-credit-card"></i>
                            立即开通会员
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-title">
                    <i class="fas fa-wallet"></i>
                    账户信息
                </div>
                <div class="info-item">
                    <span class="info-label">账户余额</span>
                    <span class="info-value highlight">¥<?php echo number_format($user['balance'], 2); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">保证金余额</span>
                    <span class="info-value highlight">¥<?php echo number_format($user['deposit'], 2); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">账户状态</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['status']); ?></span>
                </div>
            </div>
        </div>

        <?php if ($user['member_level_id']): ?>
            <div class="card">
                <div class="card-title">
                    <i class="fas fa-history"></i>
                    最近交易记录
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>交易时间</th>
                                <th>金额</th>
                                <th>支付方式</th>
                                <th>备注</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $tx): ?>
                                <tr>
                                    <td><?php echo date('m-d H:i', strtotime($tx['create_time'])); ?></td>
                                    <td>
                                        <?php echo $tx['amount'] > 0 ? '+' : '' ?>¥<?php echo number_format(abs($tx['amount']), 2); ?>
                                    </td>
                                    <td><?php echo $tx['source'] === 'balance' ? '余额' : ($tx['source'] === 'deposit' ? '保证金' : $tx['source']); ?></td>
                                    <td><?php echo htmlspecialchars($tx['note']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">暂无交易记录</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <?php include 'footer.php'; ?>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
