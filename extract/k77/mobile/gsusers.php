<?php
require_once './header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_user_available') {
    try {
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $newStatus = isset($_POST['available']) ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE users SET available_for_orders = ? WHERE id = ?");
        $stmt->execute([$newStatus, $user_id]);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?range=' . ($_GET['range'] ?? 'week'));
        exit;
    } catch (PDOException $e) {
        error_log('切换可接单状态失败: ' . $e->getMessage());
    }
}

$current_user_id = get_user_user_id() ?? 0;

$time_range = $_GET['range'] ?? 'week';
$range_label = '本周';

switch ($time_range) {
    case 'all':
        $date_condition = '';
        $range_label = '全部';
        break;
    case 'year':
        $start_date = date('Y-01-01 00:00:00');
        $date_condition = "AND o.create_time >= :start_date";
        $range_label = '本年';
        break;
    case 'quarter':
        $quarter = ceil(date('n') / 3);
        $start_month = ($quarter - 1) * 3 + 1;
        $start_date = date("Y-$start_month-01 00:00:00");
        $date_condition = "AND o.create_time >= :start_date";
        $range_label = '本季度';
        break;
    case 'month':
        $start_date = date('Y-m-01 00:00:00');
        $date_condition = "AND o.create_time >= :start_date";
        $range_label = '本月';
        break;
    case 'day':
        $start_date = date('Y-m-d 00:00:00');
        $date_condition = "AND o.create_time >= :start_date";
        $range_label = '本日';
        break;
    case 'week':
    default:
        $start_date = date('Y-m-d 00:00:00', strtotime('-1 week'));
        $date_condition = "AND o.create_time >= :start_date";
        $range_label = '本周';
        break;
}

function getInvitedUsers($eadmin_id, $date_condition, $start_date = null) {
    global $pdo;
    
    $query = "SELECT 
                u.id AS user_id,
                u.username,
                u.avatar,
                u.available_for_orders,
                r.invitation_code,
                COUNT(o.id) AS order_count,
                IFNULL(SUM(CASE WHEN o.order_status = 4 THEN o.price ELSE 0 END), 0) AS total_earnings
              FROM partner_service_relations r
              JOIN users u ON r.invitee_id = u.id
              LEFT JOIN orders o ON 
                o.hunter_user_id = u.id
                $date_condition
              WHERE 
                r.inviter_type = 'eadmin' AND 
                r.inviter_id = :eadmin_id AND
                r.invitee_type = 'fighter'
              GROUP BY u.id
              ORDER BY u.id DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':eadmin_id', $eadmin_id, PDO::PARAM_INT);
    
    if ($date_condition !== '' && $start_date !== null) {
        $stmt->bindParam(':start_date', $start_date);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$invited_users = getInvitedUsers($current_user_id, $date_condition, $start_date ?? null);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>我的邀请打手</title>
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
            background: #f5f5f5;
            color: #1a1a1a;
            font-size: 14px;
            line-height: 1.6;
            min-height: 100vh;
            padding-bottom: 80px;
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
        .page-subtitle {
            font-size: 13px;
            color: #999;
            margin-top: 2px;
        }
        .container {
            padding: 16px;
        }
        .time-selector {
            display: flex;
            overflow-x: auto;
            gap: 8px;
            margin-bottom: 16px;
            padding: 4px 0;
        }
        .time-selector::-webkit-scrollbar {
            display: none;
        }
        .time-btn {
            flex-shrink: 0;
            padding: 10px 16px;
            background: #ffffff;
            border: 2px solid #eee;
            border-radius: 20px;
            color: #666;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .time-btn:hover {
            border-color: #f5b400;
            background: #fff9e6;
        }
        .time-btn.active {
            background: #1a1a1a;
            border-color: #1a1a1a;
            color: #ffffff;
        }
        .stats-card {
            background: linear-gradient(135deg, #1a1a1a 0%, #333333 100%);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 20px;
            color: #ffffff;
            animation: fadeInUp 0.5s ease;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .stat-label {
            font-size: 12px;
            opacity: 0.8;
        }
        .user-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            animation: fadeInUp 0.5s ease;
        }
        .user-info {
            display: flex;
            align-items: center;
            margin-bottom: 16px;
        }
        .user-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 12px;
            border: 3px solid #f5f5f5;
        }
        .user-details {
            flex: 1;
        }
        .user-name {
            font-size: 17px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 4px;
        }
        .user-meta {
            font-size: 13px;
            color: #999;
            margin-bottom: 4px;
        }
        .user-code {
            font-size: 12px;
            color: #666;
            background: #f5f5f5;
            padding: 4px 8px;
            border-radius: 8px;
            display: inline-block;
        }
        .user-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }
        .stat-box {
            background: #f8f8f8;
            border-radius: 12px;
            padding: 12px;
            text-align: center;
        }
        .stat-number {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 2px;
        }
        .stat-text {
            font-size: 12px;
            color: #999;
        }
        .toggle-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid #f0f0f0;
        }
        .toggle-label {
            font-size: 14px;
            font-weight: 500;
            color: #1a1a1a;
        }
        .toggle-switch {
            position: relative;
            width: 52px;
            height: 28px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ddd;
            transition: 0.3s;
            border-radius: 28px;
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .toggle-switch input:checked + .toggle-slider {
            background-color: #4cd964;
        }
        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #ffffff;
            border-radius: 16px;
            animation: fadeInUp 0.5s ease;
        }
        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 16px;
        }
        .empty-state p {
            font-size: 15px;
            color: #999;
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
        .user-card:nth-child(2) { animation-delay: 0.1s; }
        .user-card:nth-child(3) { animation-delay: 0.2s; }
        .user-card:nth-child(4) { animation-delay: 0.3s; }
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
        <div>
            <div class="page-title">我的邀请打手</div>
            <div class="page-subtitle">统计<?= $range_label ?>数据</div>
        </div>
        <div style="width: 36px;"></div>
    </div>
    <div class="container">
        <div class="time-selector">
            <a href="?range=all" class="time-btn <?= $time_range === 'all' ? 'active' : '' ?>">全部</a>
            <a href="?range=year" class="time-btn <?= $time_range === 'year' ? 'active' : '' ?>">本年</a>
            <a href="?range=quarter" class="time-btn <?= $time_range === 'quarter' ? 'active' : '' ?>">本季度</a>
            <a href="?range=month" class="time-btn <?= $time_range === 'month' ? 'active' : '' ?>">本月</a>
            <a href="?range=week" class="time-btn <?= $time_range === 'week' ? 'active' : '' ?>">本周</a>
            <a href="?range=day" class="time-btn <?= $time_range === 'day' ? 'active' : '' ?>">本日</a>
        </div>
        
        <?php if (!empty($invited_users)): ?>
            <div class="stats-card">
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value"><?= count($invited_users) ?></div>
                        <div class="stat-label">邀请人数</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= array_sum(array_column($invited_users, 'order_count')) ?></div>
                        <div class="stat-label"><?= $range_label ?>接单</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">¥<?= number_format(array_sum(array_column($invited_users, 'total_earnings')), 2) ?></div>
                        <div class="stat-label"><?= $range_label ?>收益</div>
                    </div>
                </div>
            </div>
            
            <?php foreach ($invited_users as $user): ?>
                <div class="user-card">
                    <div class="user-info">
                        <img src="../assets/images/userds/<?= htmlspecialchars($user['avatar'] ?? 'default_avatar.png') ?>" 
                             onerror="this.src='../assets/images/user.png'" 
                             class="user-avatar" 
                             alt="用户头像">
                        <div class="user-details">
                            <div class="user-name"><?= htmlspecialchars($user['username']) ?></div>
                            <div class="user-meta">ID: <?= $user['user_id'] ?></div>
                            <div class="user-code">邀请码: <?= htmlspecialchars($user['invitation_code']) ?></div>
                        </div>
                    </div>
                    <div class="user-stats">
                        <div class="stat-box">
                            <div class="stat-number"><?= $user['order_count'] ?>单</div>
                            <div class="stat-text"><?= $range_label ?>接单</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-number">¥<?= number_format($user['total_earnings'], 2) ?></div>
                            <div class="stat-text"><?= $range_label ?>收益</div>
                        </div>
                    </div>
                    <div class="toggle-container">
                        <div class="toggle-label">
                            <span style="color: <?= (isset($user['available_for_orders']) && $user['available_for_orders'] == 1) ? '#4cd964' : '#ff3b30' ?>; font-weight: 600;">
                                <i class="fas fa-circle" style="font-size: 10px; margin-right: 4px;"></i>
                                <?= (isset($user['available_for_orders']) && $user['available_for_orders'] == 1) ? '可接单' : '不可接单' ?>
                            </span>
                        </div>
                        <form method="POST" action="" style="margin: 0;">
                            <input type="hidden" name="action" value="toggle_user_available">
                            <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                            <label class="toggle-switch">
                                <input type="checkbox" name="available" onchange="this.form.submit()" <?= (isset($user['available_for_orders']) && $user['available_for_orders'] == 1) ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <p>暂无邀请打手数据</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var items = document.querySelectorAll('.user-card, .stats-card');
            items.forEach(function(item, index) {
                item.style.opacity = '0';
                item.style.transform = 'translateY(10px)';
                setTimeout(function() {
                    item.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, 50 + index * 50);
            });
        });
    </script>
    <?php include 'footer.php'; ?>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
