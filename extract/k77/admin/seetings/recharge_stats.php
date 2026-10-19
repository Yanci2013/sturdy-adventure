<?php
// 开启会话
session_start();

// 开启输出缓冲
ob_start();

require_once __DIR__.'/../../includes/common.php';

// 检查是否有权限访问
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    // 重定向到登录页面而不是直接die
    header('Location: ../login.php');
    exit;
}

// 日期范围默认值
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// 确保日期格式正确
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    $start_date = date('Y-m-d', strtotime('-30 days'));
    $end_date = date('Y-m-d');
}

// 确保开始日期不晚于结束日期
if (strtotime($start_date) > strtotime($end_date)) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

// 初始化统计数据
$total_recharge_amount = 0;
$total_recharge_count = 0;
$total_recharge_users = 0;
$avg_recharge_amount = 0;
$recharge_daily_data = [];
$top_recharge_users = [];

// 确保recharge_logs表存在
$checkTable = $pdo->query("SHOW TABLES LIKE 'recharge_logs'");
if ($checkTable->rowCount() > 0) {
    try {
        // 1. 获取总充值金额和总充值次数
        $stmt = $pdo->prepare("        
            SELECT 
                SUM(amount) as total_amount,
                COUNT(*) as total_count,
                COUNT(DISTINCT user_id) as total_users
            FROM recharge_logs 
            WHERE create_time BETWEEN ? AND ?
            AND status = 'success'
        ");
        $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $total_recharge_amount = $result['total_amount'] ?? 0;
            $total_recharge_count = $result['total_count'] ?? 0;
            $total_recharge_users = $result['total_users'] ?? 0;
            $avg_recharge_amount = $total_recharge_count > 0 ? $total_recharge_amount / $total_recharge_count : 0;
        }
        
        // 2. 获取每日充值数据（用于图表展示）
        $stmt = $pdo->prepare("        
            SELECT 
                DATE(create_time) as date,
                SUM(amount) as daily_amount,
                COUNT(*) as daily_count,
                COUNT(DISTINCT user_id) as daily_users
            FROM recharge_logs 
            WHERE create_time BETWEEN ? AND ?
            AND status = 'success'
            GROUP BY DATE(create_time)
            ORDER BY date ASC
        ");
        $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $recharge_daily_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 3. 获取充值排行榜（TOP 10）
        $stmt = $pdo->prepare("        
            SELECT 
                r.user_id,
                COALESCE(h.username, '未知用户') as username,
                SUM(r.amount) as total_amount,
                COUNT(*) as recharge_count
            FROM recharge_logs r
            LEFT JOIN h5user h ON r.user_id = h.id
            WHERE r.create_time BETWEEN ? AND ?
            AND r.status = 'success'
            GROUP BY r.user_id
            ORDER BY total_amount DESC
            LIMIT 10
        ");
        $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $top_recharge_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        // 忽略查询错误
    }
}

// 准备图表数据
$chart_labels = [];
$chart_amounts = [];
$chart_counts = [];

foreach ($recharge_daily_data as $data) {
    $chart_labels[] = $data['date'];
    $chart_amounts[] = round($data['daily_amount'], 2);
    $chart_counts[] = $data['daily_count'];
}

// 格式化图表数据为JSON
$chart_labels_json = json_encode($chart_labels);
$chart_amounts_json = json_encode($chart_amounts);
$chart_counts_json = json_encode($chart_counts);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>充值统计 - 后台管理</title>
    <link rel="stylesheet" href="../res/layui/css/layui.css">
    <style>
        body {
            padding: 20px;
            background-color: #f5f5f5;
            font-family: 'Microsoft YaHei', Arial, sans-serif;
        }
        .header {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            margin: 0;
            color: #333;
            font-size: 24px;
        }
        .header .date-range {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .card {
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .card-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e6e6e6;
            font-size: 16px;
            font-weight: bold;
            color: #333;
            background-color: #f9f9f9;
        }
        .card-body {
            padding: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            text-align: center;
            border-left: 4px solid #1E9FFF;
        }
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #1E9FFF;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #606266;
            font-size: 14px;
        }
        .chart-container {
            height: 400px;
            margin-bottom: 30px;
        }
        .table-container {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e6e6e6;
        }
        th {
            background-color: #f5f7fa;
            font-weight: bold;
            color: #303133;
        }
        tr:hover {
            background-color: #fafafa;
        }
        .rank-number {
            width: 40px;
            text-align: center;
            font-weight: bold;
        }
        .rank-1 {
            color: #ff6b6b;
        }
        .rank-2 {
            color: #feca57;
        }
        .rank-3 {
            color: #48dbfb;
        }
        .recharge-amount {
            color: #ff6b6b;
            font-weight: bold;
        }
        .layui-btn {
            padding: 0 15px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>充值统计</h1>
        <div class="date-range">
            <span>日期范围：</span>
            <input type="date" id="start_date" value="<?php echo $start_date; ?>" style="padding: 5px;">
            <span>至</span>
            <input type="date" id="end_date" value="<?php echo $end_date; ?>" style="padding: 5px;">
            <button class="layui-btn layui-btn-normal" onclick="updateStats()">查询</button>
        </div>
    </div>
    
    <!-- 统计卡片 -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value">¥<?php echo number_format($total_recharge_amount, 2); ?></div>
            <div class="stat-label">总充值金额</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $total_recharge_count; ?></div>
            <div class="stat-label">总充值次数</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $total_recharge_users; ?></div>
            <div class="stat-label">充值用户数</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">¥<?php echo number_format($avg_recharge_amount, 2); ?></div>
            <div class="stat-label">平均充值金额</div>
        </div>
    </div>
    
    <!-- 充值趋势图表 -->
    <div class="card">
        <div class="card-header">充值趋势图表</div>
        <div class="card-body">
            <div class="chart-container" id="rechargeChart"></div>
        </div>
    </div>
    
    <!-- 充值排行榜 -->
    <div class="card">
        <div class="card-header">充值排行榜（TOP 10）</div>
        <div class="card-body">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th class="rank-number">排名</th>
                            <th>用户ID</th>
                            <th>用户名</th>
                            <th>充值总额</th>
                            <th>充值次数</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($top_recharge_users)): ?>
                            <?php foreach ($top_recharge_users as $key => $user): ?>
                                <tr>
                                    <td class="rank-number <?php echo $key < 3 ? 'rank-' . ($key + 1) : ''; ?>">
                                        <?php echo $key + 1; ?>
                                    </td>
                                    <td><?php echo $user['user_id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td class="recharge-amount">¥<?php echo number_format($user['total_amount'], 2); ?></td>
                                    <td><?php echo $user['recharge_count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #909399;">暂无充值数据</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="../res/layui/layui.js"></script>
    <script>
        // 更新统计数据
        function updateStats() {
            var start_date = document.getElementById('start_date').value;
            var end_date = document.getElementById('end_date').value;
            window.location.href = 'recharge_stats.php?start_date=' + start_date + '&end_date=' + end_date;
        }
        
        // 渲染图表
        layui.use(['echarts'], function() {
            var echarts = layui.echarts;
            
            // 初始化图表实例
            var chartDom = document.getElementById('rechargeChart');
            var myChart = echarts.init(chartDom);
            
            var option = {
                title: {
                    text: '充值趋势分析',
                    left: 'center'
                },
                tooltip: {
                    trigger: 'axis',
                    axisPointer: {
                        type: 'cross'
                    }
                },
                legend: {
                    data: ['充值金额', '充值次数'],
                    top: 30
                },
                grid: {
                    left: '3%',
                    right: '4%',
                    bottom: '3%',
                    containLabel: true
                },
                xAxis: {
                    type: 'category',
                    boundaryGap: false,
                    data: <?php echo $chart_labels_json; ?>
                },
                yAxis: [
                    {
                        type: 'value',
                        name: '充值金额(元)',
                        position: 'left',
                        axisLabel: {
                            formatter: '{value}'
                        }
                    },
                    {
                        type: 'value',
                        name: '充值次数',
                        position: 'right',
                        axisLabel: {
                            formatter: '{value}'
                        }
                    }
                ],
                series: [
                    {
                        name: '充值金额',
                        type: 'line',
                        smooth: true,
                        symbol: 'circle',
                        symbolSize: 6,
                        data: <?php echo $chart_amounts_json; ?>,
                        itemStyle: {
                            color: '#5470c6'
                        },
                        areaStyle: {
                            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                                {
                                    offset: 0,
                                    color: 'rgba(84, 112, 198, 0.3)'
                                },
                                {
                                    offset: 1,
                                    color: 'rgba(84, 112, 198, 0.1)'
                                }
                            ])
                        }
                    },
                    {
                        name: '充值次数',
                        type: 'line',
                        yAxisIndex: 1,
                        smooth: true,
                        symbol: 'circle',
                        symbolSize: 6,
                        data: <?php echo $chart_counts_json; ?>,
                        itemStyle: {
                            color: '#91cc75'
                        }
                    }
                ]
            };
            
            // 设置图表选项
            myChart.setOption(option);
            
            // 响应式处理
            window.addEventListener('resize', function() {
                myChart.resize();
            });
        });
    </script>
</body>
</html>