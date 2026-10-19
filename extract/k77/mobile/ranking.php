<?php
require_once './header.php';

/**
 * 获取打手接单排行榜数据
 * @param string $timeRange 时间范围: today, week, month, quarter, year, all
 * @return array 排行榜数据
 */
function getExecutorLeaderboard($timeRange = 'month') {
    global $pdo;
    
    try {
        // 基础查询条件
        $conditions = [
            'o.order_status = 4',         // 已完成订单
            'o.hunter_user_id IS NOT NULL',
            'o.review_time IS NOT NULL' // 确保有完成时间
        ];
        
        $params = [];
        
        // 根据时间范围添加条件
        switch ($timeRange) {
            case 'today':
                $startDate = date('Y-m-d 00:00:00');
                $conditions[] = "o.review_time >= :startDate";
                $params[':startDate'] = $startDate;
                break;
            case 'week':
                $startDate = date('Y-m-d 00:00:00', strtotime('last Monday'));
                $conditions[] = "o.review_time >= :startDate";
                $params[':startDate'] = $startDate;
                break;
            case 'month':
                $startDate = date('Y-m-01 00:00:00');
                $conditions[] = "o.review_time >= :startDate";
                $params[':startDate'] = $startDate;
                break;
            case 'quarter':
                $quarter = ceil(date('n') / 3);
                $startDate = date("Y-m-01 00:00:00", mktime(0, 0, 0, ($quarter - 1) * 3 + 1, 1, date('Y')));
                $conditions[] = "o.review_time >= :startDate";
                $params[':startDate'] = $startDate;
                break;
            case 'year':
                $startDate = date('Y-01-01 00:00:00');
                $conditions[] = "o.review_time >= :startDate";
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
                    COUNT(o.id) AS order_count,
                    SUM(o.price) AS total_income
                  FROM users u
                  JOIN orders o ON u.id = o.hunter_user_id
                  WHERE {$whereClause}
                  GROUP BY u.id
                  ORDER BY order_count DESC, total_income DESC
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

$leaderboard = getExecutorLeaderboard($timeRange);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>打手接单排行榜</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #6366f1;
            --secondary-color: #e0e7ff;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --gold-color: #ffd700;
            --silver-color: #c0c0c0;
            --bronze-color: #cd7f32;
            --glass-bg: rgba(255, 255, 255, 0.2);
            --glass-border: rgba(255, 255, 255, 0.3);
            --glass-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
            --gold-gradient: linear-gradient(135deg, #ffd700 0%, #f9d423 100%);
            --silver-gradient: linear-gradient(135deg, #c0c0c0 0%, #e0e0e0 100%);
            --bronze-gradient: linear-gradient(135deg, #cd7f32 0%, #b87333 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'PingFang SC', 'Microsoft YaHei', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e0e7ff 100%);
            color: var(--text-dark);
            min-height: 100vh;
            padding-bottom: 60px;
        }

        .container {
            max-width: 100%;
            margin: 0 auto;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #4f46e5 100%);
            color: white;
            padding: 25px 15px;
            text-align: center;
            position: relative;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            transform: rotate(30deg);
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 1;
        }

        .page-title i {
            margin-right: 12px;
            font-size: 1.8rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .stats-period {
            font-size: 0.95rem;
            margin-top: 8px;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .time-range-selector {
            display: flex;
            justify-content: center;
            padding: 15px;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            margin: 20px;
            border-radius: 16px;
            box-shadow: var(--glass-shadow);
            overflow-x: auto;
        }

        .time-range-btn {
            padding: 10px 18px;
            margin: 0 8px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-dark);
            background: rgba(255, 255, 255, 0.7);
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .time-range-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .time-range-btn.active {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .leaderboard-container {
            padding: 0 15px;
        }

        /* 前三名特殊样式 - 统一为水平排列 */
        .top3-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 30px;
            gap: 20px;
        }

        .top3-row {
            display: flex;
            justify-content: center;
            gap: 30px;
            width: 100%;
            flex-wrap: wrap;
        }

        .top3-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 1;
            transition: all 0.3s ease;
            margin: 10px;
        }

        .top3-avatar-container {
            position: relative;
            margin-bottom: 15px;
        }

        .top3-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            position: relative;
        }

        /* 金属描边效果 - 使用渐变边框 */
        .rank1 .top3-avatar {
            width: 140px;
            height: 140px;
            border: 6px solid transparent;
            background: 
                linear-gradient(white, white) padding-box,
                var(--gold-gradient) border-box;
            box-shadow: 0 10px 30px rgba(255, 215, 0, 0.3);
        }

        .rank2 .top3-avatar {
            border: 5px solid transparent;
            background: 
                linear-gradient(white, white) padding-box,
                var(--silver-gradient) border-box;
            box-shadow: 0 8px 25px rgba(192, 192, 192, 0.3);
        }

        .rank3 .top3-avatar {
            border: 4px solid transparent;
            background: 
                linear-gradient(white, white) padding-box,
                var(--bronze-gradient) border-box;
            box-shadow: 0 8px 25px rgba(205, 127, 50, 0.3);
        }

        /* 排名徽章 */
        .top3-rank {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            position: absolute;
            top: -15px;
            right: -15px;
            font-size: 1.2rem;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
            z-index: 2;
        }

        .rank1 .top3-rank { 
            background: var(--gold-gradient);
            width: 50px;
            height: 50px;
            font-size: 1.4rem;
            top: -20px;
            right: -20px;
        }
        .rank2 .top3-rank { 
            background: var(--silver-gradient);
        }
        .rank3 .top3-rank { 
            background: var(--bronze-gradient);
        }

        /* 前三名卡片 */
        .top3-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 20px 25px;
            width: 100%;
            text-align: center;
            box-shadow: var(--glass-shadow);
            transition: all 0.3s ease;
            min-width: 200px;
        }

        /* 卡片边框颜色 */
        .rank1 .top3-card {
            padding: 25px 30px;
            background: rgba(255, 255, 255, 0.3);
            border: 2px solid rgba(255, 215, 0, 0.5);
        }

        .rank2 .top3-card {
            border: 2px solid rgba(192, 192, 192, 0.5);
        }

        .rank3 .top3-card {
            border: 2px solid rgba(205, 127, 50, 0.5);
        }

        /* 第一名皇冠图标 */
        .crown-icon {
            position: absolute;
            top: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 2.5rem;
            color: var(--gold-color);
            text-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            z-index: 3;
        }

        /* 奖牌动画效果 */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .rank1 .top3-avatar-container {
            animation: pulse 2s infinite;
        }

        /* 排行榜列表样式 */
        .leaderboard-list {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            box-shadow: var(--glass-shadow);
            overflow: hidden;
            margin: 0 15px;
        }

        .leaderboard-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.2s ease;
            background: rgba(255, 255, 255, 0.1);
        }

        .leaderboard-item:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }

        .leaderboard-item:last-child {
            border-bottom: none;
        }

        .item-rank {
            font-weight: 700;
            color: var(--text-dark);
            width: 36px;
            text-align: center;
            font-size: 1.1rem;
            opacity: 0.8;
        }

        .item-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 15px;
            border: 2px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
        }

        .item-info {
            flex: 1;
            min-width: 0;
        }

        .item-name {
            font-weight: 500;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 1rem;
        }

        .item-level {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
        }

        .level-normal { 
            background-color: rgba(99, 102, 241, 0.15); 
            color: var(--primary-color); 
            border: 1px solid rgba(99, 102, 241, 0.3);
        }
        .level-pro { 
            background-color: rgba(245, 158, 11, 0.15); 
            color: var(--warning-color); 
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .level-vip { 
            background-color: rgba(16, 185, 129, 0.15); 
            color: var(--success-color); 
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .item-stats {
            text-align: right;
            margin-left: 10px;
        }

        .item-count {
            font-weight: 600;
            font-size: 1rem;
        }

        .item-income {
            font-size: 0.9rem;
            color: var(--success-color);
            font-weight: 600;
            margin-top: 3px;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--text-light);
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 16px;
            margin: 20px;
            border: 1px solid var(--glass-border);
        }

        .no-data i {
            font-size: 2.5rem;
            opacity: 0.5;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        /* 响应式设计 - 统一前三名排列方式 */
        @media (max-width: 768px) {
            .top3-row {
                gap: 20px;
            }
            
            .top3-avatar {
                width: 90px;
                height: 90px;
            }
            
            .rank1 .top3-avatar {
                width: 120px;
                height: 120px;
            }
            
            .top3-card {
                min-width: 180px;
                padding: 15px 20px;
            }
            
            .rank1 .top3-card {
                padding: 20px 25px;
            }
        }

        @media (max-width: 576px) {
            .time-range-selector {
                flex-wrap: wrap;
                justify-content: center;
                padding: 10px;
            }
            
            .time-range-btn {
                padding: 8px 15px;
                font-size: 0.85rem;
                margin: 5px;
            }
            
            .leaderboard-item {
                padding: 12px 15px;
            }
            
            .item-avatar {
                width: 45px;
                height: 45px;
                margin: 0 10px;
            }
            
            /* 小屏幕上缩小前三名间距 */
            .top3-row {
                gap: 15px;
            }
            
            .top3-avatar {
                width: 80px;
                height: 80px;
            }
            
            .rank1 .top3-avatar {
                width: 110px;
                height: 110px;
            }
            
            .top3-card {
                min-width: 160px;
                padding: 12px 15px;
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

    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <i class="fas fa-crown"></i>打手接单排行榜
            </div>
            <div class="stats-period">
                <?php echo getTimeRangeText($timeRange); ?>
            </div>
        </div>
        
        <!-- 时间范围选择器 -->
        <div class="time-range-selector">
            <button class="time-range-btn <?= $timeRange === 'today' ? 'active' : '' ?>" onclick="changeTimeRange('today')">今日</button>
            <button class="time-range-btn <?= $timeRange === 'week' ? 'active' : '' ?>" onclick="changeTimeRange('week')">本周</button>
            <button class="time-range-btn <?= $timeRange === 'month' ? 'active' : '' ?>" onclick="changeTimeRange('month')">本月</button>
            <button class="time-range-btn <?= $timeRange === 'quarter' ? 'active' : '' ?>" onclick="changeTimeRange('quarter')">本季</button>
            <button class="time-range-btn <?= $timeRange === 'year' ? 'active' : '' ?>" onclick="changeTimeRange('year')">本年</button>
            <button class="time-range-btn <?= $timeRange === 'all' ? 'active' : '' ?>" onclick="changeTimeRange('all')">全部</button>
        </div>
        
        <div class="leaderboard-container">
            <?php if (!empty($leaderboard)): ?>
                <!-- 前三名特殊展示 - 统一水平排列 -->
                <div class="top3-container">
                    <!-- 第一名单独一行 -->
                    <?php if (isset($leaderboard[0])): ?>
                        <div class="top3-item rank1">
                            <i class="fas fa-crown crown-icon"></i>
                            <div class="top3-avatar-container">
                                <img src="../assets/images/userds/<?php echo htmlspecialchars($leaderboard[0]['avatar'] ?? 'default_avatar.png'); ?>" 
                                     onerror="this.src='../assets/images/user.png'" 
                                     class="top3-avatar" 
                                     alt="第一名">
                                <div class="top3-rank">1</div>
                            </div>
                            <div class="top3-card">
                                <div class="top3-name"><?php echo htmlspecialchars($leaderboard[0]['username']); ?></div>
                                <div class="top3-stats">
                                    <div><i class="fas fa-clipboard-list"></i> 接单: <?php echo $leaderboard[0]['order_count']; ?></div>
                                    <div><i class="fas fa-coins"></i> 收入: ¥<?php echo number_format($leaderboard[0]['total_income'], 2); ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- 第二、三名在同一行 -->
                    <div class="top3-row">
                        <?php if (isset($leaderboard[1])): ?>
                            <div class="top3-item rank2">
                                <div class="top3-avatar-container">
                                    <img src="../assets/images/userds/<?php echo htmlspecialchars($leaderboard[1]['avatar'] ?? 'default_avatar.png'); ?>" 
                                         onerror="this.src='../assets/images/user.png'" 
                                         class="top3-avatar" 
                                         alt="第二名">
                                    <div class="top3-rank">2</div>
                                </div>
                                <div class="top3-card">
                                    <div class="top3-name"><?php echo htmlspecialchars($leaderboard[1]['username']); ?></div>
                                    <div class="top3-stats">
                                        <div><i class="fas fa-clipboard-list"></i> 接单: <?php echo $leaderboard[1]['order_count']; ?></div>
                                        <div><i class="fas fa-coins"></i> 收入: ¥<?php echo number_format($leaderboard[1]['total_income'], 2); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($leaderboard[2])): ?>
                            <div class="top3-item rank3">
                                <div class="top3-avatar-container">
                                    <img src="../assets/images/userds/<?php echo htmlspecialchars($leaderboard[2]['avatar'] ?? 'default_avatar.png'); ?>" 
                                         onerror="this.src='../assets/images/user.png'" 
                                         class="top3-avatar" 
                                         alt="第三名">
                                    <div class="top3-rank">3</div>
                                </div>
                                <div class="top3-card">
                                    <div class="top3-name"><?php echo htmlspecialchars($leaderboard[2]['username']); ?></div>
                                    <div class="top3-stats">
                                        <div><i class="fas fa-clipboard-list"></i> 接单: <?php echo $leaderboard[2]['order_count']; ?></div>
                                        <div><i class="fas fa-coins"></i> 收入: ¥<?php echo number_format($leaderboard[2]['total_income'], 2); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- 排行榜列表 -->
                <div class="leaderboard-list">
                    <?php foreach ($leaderboard as $index => $executor): ?>
                        <?php if ($index >= 3): ?>
                            <div class="leaderboard-item">
                                <div class="item-rank"><?php echo $index + 1; ?></div>
                                <img src="../assets/images/userds/<?php echo htmlspecialchars($executor['avatar'] ?? 'default_avatar.png'); ?>" 
                                     onerror="this.src='../assets/images/user.png'" 
                                     class="item-avatar" 
                                     alt="用户头像">
                                <div class="item-info">
                                    <div class="item-name"><?php echo htmlspecialchars($executor['username']); ?></div>
                                    <span class="item-level 
                                        <?php 
                                        switch ($executor['member_level'] ?? '普通打手') {
                                            case '普通打手': echo 'level-normal'; break;
                                            case '大神打手': echo 'level-pro'; break;
                                            case '至尊打手': echo 'level-vip'; break;
                                            default: echo 'level-normal';
                                        }
                                        ?>">
                                        <?php echo htmlspecialchars($executor['member_level'] ?? '普通打手'); ?>
                                    </span>
                                </div>
                                <div class="item-stats">
                                    <div class="item-count"><?php echo $executor['order_count']; ?>单</div>
                                    <div class="item-income">¥<?php echo number_format($executor['total_income'], 2); ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-trophy"></i>
                    <p>暂无打手数据</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>

    <script>
        // 切换时间范围
        function changeTimeRange(range) {
            const url = new URL(window.location.href);
            url.searchParams.set('range', range);
            window.location.href = url.toString();
        }
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>