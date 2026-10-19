<?php
require_once './header.php';

// 获取当前用户ID
$current_user_id = get_user_user_id();

// 获取时间筛选参数
$date_filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$start_date = null;

switch ($date_filter) {
    case 'today':
        $start_date = date('Y-m-d 00:00:00');
        $date_condition = "AND u.created_at >= :start_date";
        break;
    case 'week':
        $start_date = date('Y-m-d 00:00:00', strtotime('-1 week'));
        $date_condition = "AND u.created_at >= :start_date";
        break;
    case 'month':
        $start_date = date('Y-m-d 00:00:00', strtotime('-1 month'));
        $date_condition = "AND u.created_at >= :start_date";
        break;
    default:
        $date_condition = "";
        break;
}

// 获取通过当前用户邀请码注册的所有下级用户
function getInvitedUsers($eadmin_id, $date_condition, $start_date = null) {
    global $pdo;
    
    $query = "SELECT 
                u.id AS user_id,
                u.username,
                u.phone,
                u.avatar,
                u.balance,
                u.created_at,
                u.status,
                r.invitation_code,
                (SELECT COUNT(*) FROM orders WHERE hunter_user_id = u.id) AS total_orders,
                (SELECT COUNT(*) FROM orders WHERE hunter_user_id = u.id AND order_status = 4) AS completed_orders,
                (SELECT COALESCE(SUM(amount), 0) FROM fund_details WHERE user_id = u.id AND amount > 0) AS total_income,
                (SELECT COALESCE(SUM(amount), 0) FROM fund_details WHERE user_id = u.id AND amount < 0) AS total_expense
              FROM partner_service_relations r
              JOIN users u ON r.invitee_id = u.id
              WHERE r.inviter_type = 'eadmin' 
                AND r.inviter_id = :eadmin_id
                AND r.invitee_type = 'fighter'
              GROUP BY u.id
              ORDER BY u.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':eadmin_id', $eadmin_id, PDO::PARAM_INT);
    
    if ($date_condition !== '' && $start_date !== null) {
        $stmt->bindParam(':start_date', $start_date);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$invited_users = getInvitedUsers($current_user_id, $date_condition, $start_date);

// 统计信息
$total_count = count($invited_users);
$total_balance = 0;
$total_income = 0;
$total_orders = 0;

foreach ($invited_users as $user) {
    $total_balance += $user['balance'];
    $total_income += $user['total_income'];
    $total_orders += $user['total_orders'];
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>我的下级 - 发单抢单系统</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #ff6b00;
            --primary-dark: #e55a00;
            --accent: #ff8c00;
            --light: #000000;
            --light-gray: #F5F5F5;
            --gray: #888888;
            --dark-text: #333333;
            --shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            --radius: 8px;
            --transition: all 0.3s ease;
            --bg-color: #ffffff;
            --card-bg: transparent;
            --text-light: #666666;
            --border-color: rgba(255, 107, 0, 0.2);
            --warning-color: #ff9500;
            --success-color: #4cd964;
            --danger-color: #ff3b30;
            --wechat-color: #07c160;
            --qq-color: #12b7f5;
            font-size: 14px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: url('../assets/1.png') no-repeat center center fixed;
            background-size: cover;
            color: var(--light);
            font-family: 'PingFang SC', 'Microsoft YaHei', sans-serif;
            line-height: 1.4;
            min-height: 100vh;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: -1;
        }

        .container {
            max-width: 100%;
            margin: 0 auto;
            padding: 0;
        }

        /* 头部统计卡片 */
        .stats-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 20px 16px;
            border-radius: 0 0 24px 24px;
            margin-bottom: 16px;
        }

        .stats-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 12px;
            text-align: center;
        }

        .stat-value {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 12px;
            opacity: 0.9;
        }

        /* 筛选标签 */
        .filter-tabs {
            display: flex;
            gap: 8px;
            padding: 0 16px;
            margin-bottom: 16px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .filter-tabs::-webkit-scrollbar {
            display: none;
        }

        .filter-tab {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            white-space: nowrap;
            background: rgba(255, 255, 255, 0.9);
            color: var(--dark-text);
            border: 1px solid var(--border-color);
            text-decoration: none;
            transition: all 0.2s;
        }

        .filter-tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* 用户列表 */
        .user-list {
            padding: 0 16px;
        }

        .user-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(10px);
        }

        .user-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--gray);
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-size: 15px;
            font-weight: 600;
            color: var(--dark-text);
            margin-bottom: 4px;
        }

        .user-phone {
            font-size: 13px;
            color: var(--text-light);
        }

        .user-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }

        .user-status.active {
            background: rgba(76, 217, 100, 0.1);
            color: var(--success-color);
        }

        .user-status.disabled {
            background: rgba(255, 59, 48, 0.1);
            color: var(--danger-color);
        }

        .user-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            padding-top: 12px;
            border-top: 1px solid var(--border-color);
        }

        .user-stat {
            text-align: center;
        }

        .user-stat-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark-text);
            margin-bottom: 2px;
        }

        .user-stat-value.income {
            color: var(--success-color);
        }

        .user-stat-value.expense {
            color: var(--danger-color);
        }

        .user-stat-label {
            font-size: 11px;
            color: var(--text-light);
        }

        .user-meta {
            display: flex;
            justify-content: space-between;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--border-color);
            font-size: 12px;
            color: var(--text-light);
        }

        .user-meta-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* 空状态 */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
        }

        .empty-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-text {
            font-size: 14px;
            margin-bottom: 8px;
        }

        .empty-hint {
            font-size: 12px;
            opacity: 0.7;
        }

        /* 返回按钮 */
        .back-btn {
            position: fixed;
            top: 20px;
            left: 16px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark-text);
            text-decoration: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            z-index: 100;
        }

        /* 响应式 */
        @media (max-width: 375px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }

            .stat-value {
                font-size: 18px;
            }

            .user-stats {
                grid-template-columns: repeat(3, 1fr);
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

    <a href="javascript:history.back()" class="back-btn">
        <i class="fas fa-chevron-left"></i>
    </a>

    <div class="container">
        <!-- 头部统计 -->
        <div class="stats-header">
            <div class="stats-title">
                <i class="fas fa-users"></i>
                我的下级
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_count; ?></div>
                    <div class="stat-label">下级人数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($total_balance, 2); ?></div>
                    <div class="stat-label">总余额(元)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($total_income, 2); ?></div>
                    <div class="stat-label">总流水(元)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_orders; ?></div>
                    <div class="stat-label">总订单数</div>
                </div>
            </div>
        </div>

        <!-- 筛选标签 -->
        <div class="filter-tabs">
            <a href="?filter=all" class="filter-tab <?php echo $date_filter === 'all' ? 'active' : ''; ?>">全部</a>
            <a href="?filter=today" class="filter-tab <?php echo $date_filter === 'today' ? 'active' : ''; ?>">今日</a>
            <a href="?filter=week" class="filter-tab <?php echo $date_filter === 'week' ? 'active' : ''; ?>">本周</a>
            <a href="?filter=month" class="filter-tab <?php echo $date_filter === 'month' ? 'active' : ''; ?>">本月</a>
        </div>

        <!-- 用户列表 -->
        <div class="user-list">
            <?php if (empty($invited_users)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div class="empty-text">暂无下级用户</div>
                    <div class="empty-hint">分享您的邀请码，让更多人加入</div>
                </div>
            <?php else: ?>
                <?php foreach ($invited_users as $user): ?>
                    <div class="user-card">
                        <div class="user-header">
                            <div class="user-avatar">
                                <?php if (!empty($user['avatar']) && file_exists('../assets/images/userds/' . $user['avatar'])): ?>
                                    <img src="../assets/images/userds/<?php echo htmlspecialchars($user['avatar']); ?>" alt="">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <div class="user-info">
                                <div class="user-name"><?php echo htmlspecialchars($user['username']); ?></div>
                                <div class="user-phone"><?php echo htmlspecialchars($user['phone'] ?? '未绑定手机'); ?></div>
                            </div>
                            <div class="user-status <?php echo $user['status'] === '正常' ? 'active' : 'disabled'; ?>">
                                <?php echo htmlspecialchars($user['status'] ?? '正常'); ?>
                            </div>
                        </div>
                        <div class="user-stats">
                            <div class="user-stat">
                                <div class="user-stat-value income"><?php echo number_format($user['total_income'], 2); ?></div>
                                <div class="user-stat-label">总收入(元)</div>
                            </div>
                            <div class="user-stat">
                                <div class="user-stat-value"><?php echo number_format($user['balance'], 2); ?></div>
                                <div class="user-stat-label">余额(元)</div>
                            </div>
                            <div class="user-stat">
                                <div class="user-stat-value"><?php echo $user['completed_orders']; ?>/<?php echo $user['total_orders']; ?></div>
                                <div class="user-stat-label">完成/总订单</div>
                            </div>
                        </div>
                        <div class="user-meta">
                            <div class="user-meta-item">
                                <i class="fas fa-clock"></i>
                                注册时间: <?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?>
                            </div>
                            <div class="user-meta-item">
                                <i class="fas fa-key"></i>
                                <?php echo htmlspecialchars(substr($user['invitation_code'], 0, 8)); ?>...
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 触摸优化
            const cards = document.querySelectorAll('.user-card');
            cards.forEach(card => {
                card.addEventListener('touchstart', function() {
                    this.style.transform = 'scale(0.98)';
                });
                card.addEventListener('touchend', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        });
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
