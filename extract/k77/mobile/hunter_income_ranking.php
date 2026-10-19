<?php
require_once './header.php';

function getHunterIncomeLeaderboard($timeRange = 'month') {
    global $pdo;
    
    try {
        $conditions = [
            'o.order_status = 4',
            'o.hunter_user_id IS NOT NULL'
        ];
        
        $params = [];
        
        switch ($timeRange) {
            case 'today':
                $startDate = date('Y-m-d 00:00:00');
                $conditions[] = "o.complete_time >= :startDate";
                $params[':startDate'] = $startDate;
                break;
            case 'week':
                $startDate = date('Y-m-d 00:00:00', strtotime('last Monday'));
                $conditions[] = "o.complete_time >= :startDate";
                $params[':startDate'] = $startDate;
                break;
            case 'month':
                $startDate = date('Y-m-01 00:00:00');
                $conditions[] = "o.complete_time >= :startDate";
                $params[':startDate'] = $startDate;
                break;
            case 'quarter':
                $quarter = ceil(date('n') / 3);
                $startDate = date("Y-m-01 00:00:00", mktime(0, 0, 0, ($quarter - 1) * 3 + 1, 1, date('Y')));
                $conditions[] = "o.complete_time >= :startDate";
                $params[':startDate'] = $startDate;
                break;
            case 'year':
                $startDate = date('Y-01-01 00:00:00');
                $conditions[] = "o.complete_time >= :startDate";
                $params[':startDate'] = $startDate;
                break;
            case 'all':
            default:
                break;
        }
        
        $whereClause = implode(' AND ', $conditions);
        
        $query = "SELECT 
                    u.id, 
                    u.username, 
                    u.avatar, 
                    u.member_level,
                    COUNT(o.id) AS order_count,
                    SUM(o.price) AS total_income
                  FROM users u
                  JOIN orders o ON u.id = o.hunter_user_id
                  WHERE {$whereClause}
                  GROUP BY u.id
                  ORDER BY total_income DESC, order_count DESC
                  LIMIT 50";
        
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("数据库查询错误: " . $e->getMessage());
        return [];
    }
}

function getTimeRangeText($range) {
    switch ($range) {
        case 'today':
            return '今日 (' . date('Y-m-d') . ')';
        case 'week':
            $monday = date('Y-m-d', strtotime('last Monday'));
            $sunday = date('Y-m-d', strtotime('next Sunday'));
            return '本周 (' . $monday . ' 至 ' . $sunday . ')';
        case 'month':
            return '本月 (' . date('Y-m') . ')';
        case 'quarter':
            $quarter = ceil(date('n') / 3);
            return '本季度 (第' . $quarter . '季度)';
        case 'year':
            return '本年 (' . date('Y') . ')';
        case 'all':
            return '全部时间';
        default:
            return '本月 (' . date('Y-m') . ')';
    }
}

$timeRange = $_GET['range'] ?? 'month';
$validRanges = ['today', 'week', 'month', 'quarter', 'year', 'all'];
if (!in_array($timeRange, $validRanges)) {
    $timeRange = 'month';
}

$leaderboard = getHunterIncomeLeaderboard($timeRange);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>打手流水榜</title>
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
        }

        .header-title {
            font-size: 18px;
            font-weight: 600;
            color: #000000;
        }

        .stats-period {
            padding: 16px 12px;
            font-size: 14px;
            color: #666666;
        }

        .time-range-selector {
            display: flex;
            gap: 8px;
            padding: 12px;
            background: #ffffff;
            border-radius: 16px;
            margin-bottom: 12px;
            overflow-x: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .time-range-btn {
            padding: 10px 16px;
            border: none;
            background: #f5f5f5;
            color: #666666;
            font-size: 14px;
            font-weight: 500;
            border-radius: 12px;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s ease;
        }

        .time-range-btn:hover {
            background: #e8e8e8;
        }

        .time-range-btn.active {
            background: #000000;
            color: #ffffff;
        }

        .ranking-card {
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

        .ranking-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }

        .ranking-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .ranking-number {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
        }

        .ranking-number.rank-1 {
            background: linear-gradient(135deg, #ffd700 0%, #f9d423 100%);
            color: #1d1d1f;
        }

        .ranking-number.rank-2 {
            background: linear-gradient(135deg, #c0c0c0 0%, #e0e0e0 100%);
            color: #1d1d1f;
        }

        .ranking-number.rank-3 {
            background: linear-gradient(135deg, #cd7f32 0%, #b87333 100%);
            color: #ffffff;
        }

        .ranking-number.rank-other {
            background: #f5f5f5;
            color: #666666;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }

        .user-info {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-size: 15px;
            font-weight: 600;
            color: #000000;
            margin-bottom: 4px;
        }

        .user-level {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .level-normal { 
            background-color: rgba(16, 185, 129, 0.15); 
            color: #10b981; 
        }

        .level-pro { 
            background-color: rgba(245, 158, 11, 0.15); 
            color: #f59e0b; 
        }

        .level-vip { 
            background-color: rgba(139, 92, 246, 0.15); 
            color: #8b5cf6; 
        }

        .ranking-right {
            text-align: right;
        }

        .income-amount {
            font-size: 18px;
            font-weight: 700;
            color: #10b981;
            margin-bottom: 4px;
        }

        .order-count {
            font-size: 13px;
            color: #666666;
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
            font-size: 14px;
            color: #666666;
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

    <div class="app-container">
        <div class="header">
            <div class="header-content">
                <a href="profile2.php" class="back-btn">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <div class="header-title">打手流水榜</div>
                <div style="width: 40px;"></div>
            </div>
        </div>

        <div class="stats-period">
            <?php echo getTimeRangeText($timeRange); ?>
        </div>

        <div class="time-range-selector">
            <button class="time-range-btn <?= $timeRange === 'today' ? 'active' : '' ?>" onclick="changeTimeRange('today')">今日</button>
            <button class="time-range-btn <?= $timeRange === 'week' ? 'active' : '' ?>" onclick="changeTimeRange('week')">本周</button>
            <button class="time-range-btn <?= $timeRange === 'month' ? 'active' : '' ?>" onclick="changeTimeRange('month')">本月</button>
            <button class="time-range-btn <?= $timeRange === 'quarter' ? 'active' : '' ?>" onclick="changeTimeRange('quarter')">本季</button>
            <button class="time-range-btn <?= $timeRange === 'year' ? 'active' : '' ?>" onclick="changeTimeRange('year')">本年</button>
            <button class="time-range-btn <?= $timeRange === 'all' ? 'active' : '' ?>" onclick="changeTimeRange('all')">全部</button>
        </div>

        <?php if (!empty($leaderboard)): ?>
            <?php foreach ($leaderboard as $index => $hunter): ?>
                <div class="ranking-card">
                    <div class="ranking-left">
                        <div class="ranking-number <?php echo $index === 0 ? 'rank-1' : ($index === 1 ? 'rank-2' : ($index === 2 ? 'rank-3' : 'rank-other')); ?>">
                            <?php echo $index + 1; ?>
                        </div>
                        <img src="../assets/images/userds/<?php echo htmlspecialchars($hunter['avatar'] ?? 'default_avatar.png'); ?>" 
                             onerror="this.src='../assets/images/user.png'" 
                             class="user-avatar" 
                             alt="用户头像">
                        <div class="user-info">
                            <div class="user-name"><?php echo htmlspecialchars($hunter['username']); ?></div>
                            <!--<span class="user-level -->
                                <?php 
                                switch ($hunter['member_level'] ?? '普通打手') {
                                    case '普通打手': echo 'level-normal'; break;
                                    case '大神打手': echo 'level-pro'; break;
                                    case '至尊打手': echo 'level-vip'; break;
                                    // default: echo 'level-normal';
                                }
                                ?><!--">-->
                                <!--<?php echo htmlspecialchars($hunter['member_level'] ?? '普通打手'); ?>-->
                            <!--</span>-->
                        </div>
                    </div>
                    <div class="ranking-right">
                        <div class="income-amount">¥<?php echo number_format($hunter['total_income'], 2); ?></div>
                        <div class="order-count"><?php echo $hunter['order_count']; ?>单</div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="empty-title">暂无数据</div>
                <div class="empty-message">当前时间范围内暂无打手流水数据</div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        function changeTimeRange(range) {
            const url = new URL(window.location.href);
            url.searchParams.set('range', range);
            window.location.href = url.toString();
        }
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
