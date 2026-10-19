<?php
session_start();
require_once './header.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    die("数据库连接失败");
}

$user_id = get_user_user_id() ?? 0;
if (!$user_id) {
    header('Location: ./login.php');
    exit();
}

try {
    $userStmt = $pdo->prepare("SELECT id, username, balance FROM users WHERE id = ?");
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    if (empty($user)) {
        header('Location: ./error.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("获取用户信息失败: " . $e->getMessage());
    die("获取用户信息失败");
}

$balance = $user['balance'];
$sourceMap = [
    'order_income' => '订单收入',
    'withdrawal' => '提现支出'
];

$page = max(1, isset($_GET['page']) ? intval($_GET['page']) : 1);
$limit = 20;
$offset = ($page - 1) * $limit;

$fund_details = [];
$fund_error = null;

$fundSql = "SELECT 
    id, 
    create_time, 
    source, 
    amount, 
    related_id AS order_id,
    note
    FROM fund_details 
    WHERE user_id = ? 
    ORDER BY create_time DESC 
    LIMIT ?, ?";

try {
    $fundStmt = $pdo->prepare($fundSql);
    $fundStmt->bindValue(1, $user_id, PDO::PARAM_INT);
    $fundStmt->bindValue(2, $offset, PDO::PARAM_INT);
    $fundStmt->bindValue(3, $limit, PDO::PARAM_INT);
    $fundStmt->execute();
    $fund_details = $fundStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $fund_error = "查询资金明细失败: " . $e->getMessage();
    error_log($fund_error);
}

$total_rows = 0;
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) AS count FROM fund_details WHERE user_id = ?");
    $countStmt->execute([$user_id]);
    $total_rows = $countStmt->fetchColumn();
} catch (PDOException $e) {
    error_log("查询总记录数失败: " . $e->getMessage());
}

$total_pages = ceil($total_rows / $limit);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>资金明细</title>
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

        .balance-card {
            background: linear-gradient(135deg, #000000 0%, #333333 100%);
            border-radius: 20px;
            padding: 24px 20px;
            margin: 12px 0;
            color: #ffffff;
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        }

        .balance-label {
            font-size: 13px;
            color: #999999;
            margin-bottom: 8px;
        }

        .balance-amount {
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .balance-amount span {
            font-size: 20px;
            font-weight: 400;
        }

        .content-section {
            margin: 12px 0;
        }

        .fund-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .fund-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }

        .fund-left {
            flex: 1;
        }

        .fund-type {
            font-size: 15px;
            font-weight: 600;
            color: #000000;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .fund-type-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .fund-type-icon.income {
            background: rgba(82, 196, 26, 0.1);
            color: #52c41a;
        }

        .fund-type-icon.expense {
            background: rgba(255, 68, 68, 0.1);
            color: #ff4444;
        }

        .fund-order {
            font-size: 13px;
            color: #666666;
            margin-bottom: 4px;
        }

        .fund-time {
            font-size: 12px;
            color: #999999;
        }

        .fund-right {
            text-align: right;
        }

        .fund-amount {
            font-size: 18px;
            font-weight: 700;
        }

        .fund-amount.income {
            color: #52c41a;
        }

        .fund-amount.expense {
            color: #ff4444;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #ffffff;
            border-radius: 16px;
            margin: 20px 0;
            animation: fadeInUp 0.6s ease-out;
        }

        .empty-icon {
            font-size: 64px;
            color: #cccccc;
            margin-bottom: 16px;
        }

        .empty-title {
            font-size: 18px;
            font-weight: 600;
            color: #000000;
            margin-bottom: 8px;
        }

        .empty-message {
            color: #999999;
            font-size: 14px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
        }

        .page-btn {
            width: 40px;
            height: 40px;
            border-radius: 20px;
            background: #ffffff;
            border: 1px solid #eaeaea;
            color: #666666;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .page-btn:hover {
            background: #f5f5f5;
            border-color: #cccccc;
        }

        .page-btn.active {
            background: #000000;
            color: #ffffff;
            border-color: #000000;
        }

        .page-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .alert {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 12px;
            color: #856404;
            font-size: 14px;
        }

        .alert-danger {
            background: #f8d7da;
            border-color: #ff4444;
            color: #721c24;
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
            <a href="javascript:history.back()" class="back-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <h1 class="header-title">资金明细</h1>
            <div class="header-right"></div>
        </div>
    </header>

    <main class="app-container">
        <div class="balance-card">
            <div class="balance-label">当前余额</div>
            <div class="balance-amount"><span>¥</span><?php echo number_format($balance, 2); ?></div>
        </div>

        <div class="content-section">
            <?php if ($fund_error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($fund_error); ?></div>
            <?php elseif (empty($fund_details)): ?>
                <div class="empty-state">
                    <div class="empty-icon">💰</div>
                    <div class="empty-title">暂无资金记录</div>
                    <div class="empty-message">您还没有任何资金记录</div>
                </div>
            <?php else: ?>
                <?php foreach ($fund_details as $detail): ?>
                    <div class="fund-card">
                        <div class="fund-left">
                            <div class="fund-type">
                                <div class="fund-type-icon <?php echo $detail['amount'] > 0 ? 'income' : 'expense'; ?>">
                                    <i class="fas fa-<?php echo $detail['amount'] > 0 ? 'arrow-down' : 'arrow-up'; ?>"></i>
                                </div>
                                <?php echo $sourceMap[$detail['source']] ?? '资金变动'; ?>
                            </div>
                            <?php if (!empty($detail['note'])): ?>
                                <div class="fund-order" style="color: #000000; margin-top: 4px; font-size: 12px; line-height: 1.4;">
                                    <?php echo htmlspecialchars($detail['note']); ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($detail['order_id'])): ?>
                                <div class="fund-order" style="margin-top: 4px;">订单号: <?php echo $detail['order_id']; ?></div>
                            <?php endif; ?>
                            <div class="fund-time">
                                <?php echo date('Y-m-d H:i', strtotime($detail['create_time'])); ?>
                            </div>
                        </div>
                        <div class="fund-right">
                            <div class="fund-amount <?php echo $detail['amount'] > 0 ? 'income' : 'expense'; ?>">
                                <?php echo $detail['amount'] > 0 ? '+' : '-'; ?>
                                ¥<?php echo number_format(abs($detail['amount']), 2); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>" class="page-btn">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-btn disabled">
                                <i class="fas fa-chevron-left"></i>
                            </span>
                        <?php endif; ?>

                        <?php 
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        if ($start > 1) {
                            echo '<a href="?page=1" class="page-btn">1</a>';
                            if ($start > 2) {
                                echo '<span class="page-btn disabled">...</span>';
                            }
                        }
                        
                        for ($i = $start; $i <= $end; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="page-btn active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>" class="page-btn"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor;
                        
                        if ($end < $total_pages) {
                            if ($end < $total_pages - 1) {
                                echo '<span class="page-btn disabled">...</span>';
                            }
                            echo '<a href="?page=' . $total_pages . '" class="page-btn">' . $total_pages . '</a>';
                        }
                        ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>" class="page-btn">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-btn disabled">
                                <i class="fas fa-chevron-right"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'footer.php'; ?>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
