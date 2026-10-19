<?php
// 引入头部文件
include 'header.php';

// 检查用户是否登录
$user_id = get_user_user_id() ?? 0;
if (!$user_id) {
    header('Location: login.php');
    exit;
}
$error = '';
$userData = [];
$stats = [
    'balance' => '0.00',
    'deposit' => '0.00',
    'order_count' => '0',
    'earnings' => '0.00',
    'today_orders' => '0',
    'today_earnings' => '0.00',
    'week_orders' => '0',
    'week_earnings' => '0.00',
    'month_orders' => '0',
    'month_earnings' => '0.00'
];

// 获取当前用户数据
try {
    // 使用PDO查询用户基本信息
    $stmt = $pdo->prepare("SELECT id, username, real_name, balance, deposit, user_role, avatar, member_level FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        throw new Exception("用户信息查询失败");
    }
    
    // 查询总接单数量
    $stmt = $pdo->prepare("SELECT COUNT(id) AS count FROM orders WHERE hunter_user_id = ? AND order_status IN (3,4)");
    $stmt->execute([$user_id]);
    $orderCount = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['order_count'] = $orderCount['count'] ?? '0';

    // 查询总收益金额
    $stmt = $pdo->prepare("SELECT SUM(amount) AS earnings FROM fund_details WHERE user_id = ? AND source = 'order_income' AND amount > 0");
    $stmt->execute([$user_id]);
    $earnings = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['earnings'] = number_format((float)($earnings['earnings'] ?? 0), 2, '.', '');

    // 格式化数据
    $stats['balance'] = number_format((float)($userData['balance'] ?? 0), 2, '.', '');
    $stats['deposit'] = number_format((float)($userData['deposit'] ?? 0), 2, '.', '');
    
    // 查询今日订单和收益
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(id) AS count FROM orders WHERE hunter_user_id = ? AND order_status IN (3,4) AND DATE(accept_time) = ?");
    $stmt->execute([$user_id, $today]);
    $todayOrders = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['today_orders'] = $todayOrders['count'] ?? '0';
    
    $stmt = $pdo->prepare("SELECT SUM(amount) AS earnings FROM fund_details WHERE user_id = ? AND source = 'order_income' AND amount > 0 AND DATE(create_time) = ?");
    $stmt->execute([$user_id, $today]);
    $todayEarnings = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['today_earnings'] = number_format((float)($todayEarnings['earnings'] ?? 0), 2, '.', '');
    
    // 查询本周订单和收益
    $weekStart = date('Y-m-d', strtotime('this week'));
    $weekEnd = date('Y-m-d', strtotime('this week +6 days'));
    $stmt = $pdo->prepare("SELECT COUNT(id) AS count FROM orders WHERE hunter_user_id = ? AND order_status IN (3,4) AND DATE(accept_time) BETWEEN ? AND ?");
    $stmt->execute([$user_id, $weekStart, $weekEnd]);
    $weekOrders = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['week_orders'] = $weekOrders['count'] ?? '0';
    
    $stmt = $pdo->prepare("SELECT SUM(amount) AS earnings FROM fund_details WHERE user_id = ? AND source = 'order_income' AND amount > 0 AND DATE(create_time) BETWEEN ? AND ?");
    $stmt->execute([$user_id, $weekStart, $weekEnd]);
    $weekEarnings = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['week_earnings'] = number_format((float)($weekEarnings['earnings'] ?? 0), 2, '.', '');
    
    // 查询本月订单和收益
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');
    $stmt = $pdo->prepare("SELECT COUNT(id) AS count FROM orders WHERE hunter_user_id = ? AND order_status IN (3,4) AND DATE(accept_time) BETWEEN ? AND ?");
    $stmt->execute([$user_id, $monthStart, $monthEnd]);
    $monthOrders = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['month_orders'] = $monthOrders['count'] ?? '0';
    
    $stmt = $pdo->prepare("SELECT SUM(amount) AS earnings FROM fund_details WHERE user_id = ? AND source = 'order_income' AND amount > 0 AND DATE(create_time) BETWEEN ? AND ?");
    $stmt->execute([$user_id, $monthStart, $monthEnd]);
    $monthEarnings = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['month_earnings'] = number_format((float)($monthEarnings['earnings'] ?? 0), 2, '.', '');

} catch (Exception $e) {
    $error = "数据加载失败: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>实时报表 - 俱乐部</title>
    <!-- 引入 Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- 引入 Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #ff6b00;
            --primary-dark: #e55a00;
            --accent: #ff8c00;
            --light: #ffffff;
            --dark: #000000;
            --gray: #999999;
            --light-gray: #f5f5f5;
            --dark-gray: #333333;
            --success: #4cd964;
            --warning: #ff9500;
            --danger: #ff3b30;
            --border-color: #e0e0e0;
            --shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            --radius: 8px;
            --transition: all 0.3s ease;
            font-size: 14px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-color: var(--light-gray);
            color: var(--dark-gray);
            font-family: 'PingFang SC', 'Microsoft YaHei', sans-serif;
            line-height: 1.4;
            min-height: 100vh;
            position: relative;
            padding-bottom: 80px;
        }
        
        /* 顶部导航栏 */
        .header {
            background: linear-gradient(135deg, #1a1a1a, #333333);
            color: var(--light);
            padding: 16px;
            border-radius: 0 0 20px 20px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: rgba(255, 215, 0, 0.1);
            border-radius: 50%;
            transform: translate(30%, -30%);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-title {
            font-size: 18px;
            font-weight: 600;
        }
        
        .header-actions {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .action-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }
        
        /* 统计卡片区 */
        .stats-section {
            padding: 0 16px;
            margin: -10px 0 16px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .stat-card {
            background: var(--light);
            padding: 16px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .stat-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: rgba(255, 107, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 14px;
        }
        
        .stat-title {
            font-size: 12px;
            color: var(--gray);
        }
        
        .stat-value {
            font-size: 20px;
            font-weight: 700;
            color: var(--dark-gray);
        }
        
        .stat-change {
            font-size: 11px;
            color: var(--success);
            margin-top: 4px;
        }
        
        /* 图表区 */
        .charts-section {
            padding: 0 16px;
            margin-bottom: 16px;
        }
        
        .chart-card {
            background: var(--light);
            border-radius: 12px;
            padding: 16px;
            box-shadow: var(--shadow);
            margin-bottom: 16px;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .chart-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--dark-gray);
        }
        
        .chart-period {
            display: flex;
            gap: 4px;
        }
        
        .period-btn {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            background: var(--light-gray);
            cursor: pointer;
            transition: var(--transition);
            border: none;
            color: var(--gray);
        }
        
        .period-btn.active {
            background: var(--primary);
            color: var(--light);
        }
        
        .chart-container {
            position: relative;
            height: 200px;
        }
        
        /* 订单列表区 */
        .orders-section {
            padding: 0 16px;
            margin-bottom: 80px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--dark-gray);
        }
        
        .order-item {
            background: var(--light);
            padding: 16px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            margin-bottom: 12px;
            transition: var(--transition);
        }
        
        .order-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .order-id {
            font-size: 12px;
            color: var(--gray);
        }
        
        .order-status {
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 10px;
            background: rgba(76, 217, 100, 0.1);
            color: var(--success);
            font-weight: 500;
        }
        
        .order-content {
            margin-bottom: 8px;
        }
        
        .order-product {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 4px;
            color: var(--dark-gray);
        }
        
        .order-meta {
            font-size: 12px;
            color: var(--gray);
        }
        
        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .order-price {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary);
        }
        
        .order-time {
            font-size: 11px;
            color: var(--gray);
        }
        
        /* 底部导航栏 */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--light);
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 12px 0;
            box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.1);
            z-index: 100;
        }
        
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            transition: var(--transition);
            padding: 4px 16px;
            border-radius: 8px;
            color: var(--gray);
        }
        
        .nav-item:hover {
            background: rgba(255, 107, 0, 0.1);
        }
        
        .nav-item.active {
            color: var(--primary);
        }
        
        .nav-icon {
            font-size: 20px;
        }
        
        .nav-text {
            font-size: 12px;
        }
        
        /* 响应式设计 */
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }
            
            .stat-card {
                padding: 12px;
            }
            
            .stat-value {
                font-size: 18px;
            }
            
            .chart-container {
                height: 180px;
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

    <!-- 顶部导航栏 -->
    <div class="header">
        <div class="header-content">
            <div class="header-title">实时报表</div>
            <div class="header-actions">
                <div class="action-btn">
                    <i class="fas fa-download"></i>
                </div>
                <div class="action-btn">
                    <i class="fas fa-cog"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 统计卡片区 -->
    <div class="stats-section">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">今日订单</span>
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $stats['today_orders']; ?></div>
                <div class="stat-change">+2.5% <i class="fas fa-arrow-up"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">今日收益</span>
                    <div class="stat-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                </div>
                <div class="stat-value">¥<?php echo $stats['today_earnings']; ?></div>
                <div class="stat-change">+12.8% <i class="fas fa-arrow-up"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">本周订单</span>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $stats['week_orders']; ?></div>
                <div class="stat-change">+8.3% <i class="fas fa-arrow-up"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">本周收益</span>
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
                <div class="stat-value">¥<?php echo $stats['week_earnings']; ?></div>
                <div class="stat-change">+15.2% <i class="fas fa-arrow-up"></i></div>
            </div>
        </div>
    </div>
    
    <!-- 图表区 -->
    <div class="charts-section">
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title">收益趋势</div>
                <div class="chart-period">
                    <button class="period-btn active">日</button>
                    <button class="period-btn">周</button>
                    <button class="period-btn">月</button>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="earningsChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title">订单分布</div>
                <div class="chart-period">
                    <button class="period-btn active">分类</button>
                    <button class="period-btn">状态</button>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="ordersChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- 订单列表区 -->
    <div class="orders-section">
        <h2 class="section-title">最近订单</h2>
        <div class="order-item">
            <div class="order-header">
                <span class="order-id">订单号: 20260205001</span>
                <span class="order-status">已完成</span>
            </div>
            <div class="order-content">
                <div class="order-product">物资配送服务</div>
                <div class="order-meta">区服: 华东1 | 数量: 1</div>
            </div>
            <div class="order-footer">
                <div class="order-price">¥50.00</div>
                <div class="order-time">2026-02-05 14:30</div>
            </div>
        </div>
        <div class="order-item">
            <div class="order-header">
                <span class="order-id">订单号: 20260205002</span>
                <span class="order-status">已完成</span>
            </div>
            <div class="order-content">
                <div class="order-product">地铁集结服务</div>
                <div class="order-meta">区服: 华北2 | 数量: 1</div>
            </div>
            <div class="order-footer">
                <div class="order-price">¥30.00</div>
                <div class="order-time">2026-02-05 12:15</div>
            </div>
        </div>
        <div class="order-item">
            <div class="order-header">
                <span class="order-id">订单号: 20260204001</span>
                <span class="order-status">已完成</span>
            </div>
            <div class="order-content">
                <div class="order-product">Delta服务</div>
                <div class="order-meta">区服: 华南1 | 数量: 1</div>
            </div>
            <div class="order-footer">
                <div class="order-price">¥80.00</div>
                <div class="order-time">2026-02-04 20:45</div>
            </div>
        </div>
    </div>
    
    <!-- 底部导航栏 -->
    <div class="bottom-nav">
        <a href="index.php" class="nav-item">
            <div class="nav-icon">
                <i class="fas fa-gamepad"></i>
            </div>
            <div class="nav-text">首页</div>
        </a>
        <a href="my_orders.php" class="nav-item">
            <div class="nav-icon">
                <i class="fas fa-list"></i>
            </div>
            <div class="nav-text">订单</div>
        </a>
        <a href="chat_list.php" class="nav-item">
            <div class="nav-icon">
                <i class="fas fa-comment"></i>
            </div>
            <div class="nav-text">消息</div>
        </a>
        <a href="profile.php" class="nav-item">
            <div class="nav-icon">
                <i class="fas fa-user"></i>
            </div>
            <div class="nav-text">我的</div>
        </a>
        <a href="report.php" class="nav-item active">
            <div class="nav-icon">
                <i class="fas fa-chart-pie"></i>
            </div>
            <div class="nav-text">报表</div>
        </a>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 收益趋势图表
            const earningsCtx = document.getElementById('earningsChart').getContext('2d');
            const earningsChart = new Chart(earningsCtx, {
                type: 'line',
                data: {
                    labels: ['1日', '2日', '3日', '4日', '5日', '6日', '7日'],
                    datasets: [{
                        label: '收益',
                        data: [120, 190, 150, 250, 220, 300, 280],
                        borderColor: '#ff6b00',
                        backgroundColor: 'rgba(255, 107, 0, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
            
            // 订单分布图表
            const ordersCtx = document.getElementById('ordersChart').getContext('2d');
            const ordersChart = new Chart(ordersCtx, {
                type: 'doughnut',
                data: {
                    labels: ['物资', '地铁集结', 'Delta', 'Pub', '其他'],
                    datasets: [{
                        data: [35, 25, 20, 15, 5],
                        backgroundColor: [
                            '#ff6b00',
                            '#4cd964',
                            '#ff9500',
                            '#34aadc',
                            '#9013fe'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 10,
                                font: {
                                    size: 11
                                }
                            }
                        }
                    },
                    cutout: '70%'
                }
            });
            
            // 时间段按钮点击事件
            const periodBtns = document.querySelectorAll('.period-btn');
            periodBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    // 移除同组其他按钮的active状态
                    const parent = this.parentElement;
                    const siblings = parent.querySelectorAll('.period-btn');
                    siblings.forEach(sibling => sibling.classList.remove('active'));
                    // 添加当前按钮的active状态
                    this.classList.add('active');
                });
            });
            
            // 导出按钮点击事件
            const exportBtn = document.querySelector('.action-btn:first-child');
            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    alert('报表导出功能即将上线，敬请期待！');
                });
            }
        });
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>