<?php
require_once './header.php';

/**
 * 获取管事招人排行榜数据
 * @param string $timeRange 时间范围: today, week, month, quarter, year, all
 * @return array 排行榜数据
 */
function getManagerRecruitmentLeaderboard($timeRange = 'month') {
    global $pdo;
    
    try {
        // 基础查询条件 - 使用partner_service_relations表
        $conditions = [
            'psr.inviter_type = \'eadmin\'',
            'psr.invitee_type = \'fighter\''
        ];
        
        $params = [];
        
        // 根据时间范围添加条件
        switch ($timeRange) {
            case 'today':
                $startDate = date('Y-m-d 00:00:00');
                $conditions[] = "psr.created_at >= :startDate";
                $params[':startDate'] = $startDate;
                break;
            case 'week':
                $startDate = date('Y-m-d 00:00:00', strtotime('last Monday'));
                $conditions[] = "psr.created_at >= :startDate";
                $params[':startDate'] = $startDate;
                break;
            case 'month':
                $startDate = date('Y-m-01 00:00:00');
                $conditions[] = "psr.created_at >= :startDate";
                $params[':startDate'] = $startDate;
                break;
            case 'quarter':
                $quarter = ceil(date('n') / 3);
                $startDate = date("Y-m-01 00:00:00", mktime(0, 0, 0, ($quarter - 1) * 3 + 1, 1, date('Y')));
                $conditions[] = "psr.created_at >= :startDate";
                $params[':startDate'] = $startDate;
                break;
            case 'year':
                $startDate = date('Y-01-01 00:00:00');
                $conditions[] = "psr.created_at >= :startDate";
                $params[':startDate'] = $startDate;
                break;
            case 'all':
            default:
                // 不需要额外条件
                break;
        }
        
        $whereClause = implode(' AND ', $conditions);
        
        $query = "SELECT 
                    u.id,
                    u.username,
                    u.avatar,
                    u.member_level,
                    COUNT(psr.id) AS recruit_count
                  FROM partner_service_relations psr
                  JOIN users u ON psr.inviter_id = u.id
                  WHERE {$whereClause}
                  GROUP BY u.id
                  ORDER BY recruit_count DESC
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

/**
 * 获取时间范围显示文本
 * @param string $range 时间范围
 * @return string 显示文本
 */
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

// 获取时间范围参数
$timeRange = $_GET['range'] ?? 'month';
$validRanges = ['today', 'week', 'month', 'quarter', 'year', 'all'];
if (!in_array($timeRange, $validRanges)) {
    $timeRange = 'month';
}

$leaderboard = getManagerRecruitmentLeaderboard($timeRange);
$siteTitle = '管事招人榜';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>管事招人榜</title>
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

        .content-section {
            margin: 12px 0;
        }

        /* 时间范围选择器 */
        .time-selector {
            background: #ffffff;
            border-radius: 16px;
            padding: 12px;
            margin-bottom: 12px;
            display: flex;
            overflow-x: auto;
            gap: 8px;
        }

        .time-btn {
            flex-shrink: 0;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            color: #666666;
            background: #f5f5f5;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .time-btn:hover {
            background: #e8e8e8;
        }

        .time-btn.active {
            background: #000000;
            color: #ffffff;
        }

        .manager-card {
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

        .manager-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }

        .manager-left {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .manager-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
        }

        .manager-rank {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            color: #ffffff;
            flex-shrink: 0;
        }

        .manager-rank.rank-1 {
            background: linear-gradient(135deg, #ffd700 0%, #f9d423 100%);
        }

        .manager-rank.rank-2 {
            background: linear-gradient(135deg, #c0c0c0 0%, #e0e0e0 100%);
        }

        .manager-rank.rank-3 {
            background: linear-gradient(135deg, #cd7f32 0%, #b87333 100%);
        }

        .manager-rank.rank-other {
            background: #999999;
        }

        .manager-info {
            flex: 1;
        }

        .manager-name {
            font-size: 15px;
            font-weight: 600;
            color: #000000;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .manager-level {
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 12px;
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
            font-weight: 500;
        }

        .manager-right {
            text-align: right;
        }

        .manager-count {
            font-size: 20px;
            font-weight: 700;
            color: #8b5cf6;
        }

        .manager-label {
            font-size: 12px;
            color: #999999;
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
            <h1 class="header-title">管事招人榜</h1>
            <div class="header-right"></div>
        </div>
    </header>

    <main class="app-container">
        <div class="content-section">
            <!-- 时间范围选择器 -->
            <div class="time-selector">
                <button class="time-btn <?php echo $timeRange === 'today' ? 'active' : '' ?>" onclick="changeTimeRange('today')">今日</button>
                <button class="time-btn <?php echo $timeRange === 'week' ? 'active' : '' ?>" onclick="changeTimeRange('week')">本周</button>
                <button class="time-btn <?php echo $timeRange === 'month' ? 'active' : '' ?>" onclick="changeTimeRange('month')">本月</button>
                <button class="time-btn <?php echo $timeRange === 'quarter' ? 'active' : '' ?>" onclick="changeTimeRange('quarter')">本季</button>
                <button class="time-btn <?php echo $timeRange === 'year' ? 'active' : '' ?>" onclick="changeTimeRange('year')">本年</button>
                <button class="time-btn <?php echo $timeRange === 'all' ? 'active' : '' ?>" onclick="changeTimeRange('all')">全部</button>
            </div>

            <?php if (!empty($leaderboard)): ?>
                <?php foreach ($leaderboard as $index => $manager): ?>
                    <div class="manager-card">
                        <div class="manager-left">
                            <div class="manager-rank rank-<?php echo $index + 1 <= 3 ? ($index + 1) : 'other'; ?>">
                                <?php echo $index + 1; ?>
                            </div>
                            <img src="../assets/images/userds/<?php echo htmlspecialchars($manager['avatar'] ?? 'default_avatar.png'); ?>" 
                                 onerror="this.src='../assets/images/user.png'" 
                                 class="manager-avatar" 
                                 alt="用户头像">
                            <div class="manager-info">
                                <div class="manager-name">
                                    <?php echo htmlspecialchars($manager['username']); ?>
                                    <!--<span class="manager-level"><?php echo htmlspecialchars($manager['member_level'] ?? '普通打手'); ?></span>-->
                                </div>
                            </div>
                        </div>
                        <div class="manager-right">
                            <div class="manager-count"><?php echo $manager['recruit_count']; ?></div>
                            <div class="manager-label">人</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">🏆</div>
                    <div class="empty-title">暂无招人数据</div>
                    <div class="empty-message">当前时间段暂无管事招人记录</div>
                </div>
            <?php endif; ?>
        </div>
    </main>

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
