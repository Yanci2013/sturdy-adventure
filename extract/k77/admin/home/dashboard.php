<?php
ob_start();
// 确保session在iframe中也能正常传递
ini_set('session.cookie_samesite', 'Lax');
require_once("../../includes/session_manager.php");

if (!is_admin_logged_in()) {
    header("Location: ../login.php");
    exit();
}

// 自己创建数据库连接
require_once("../../config.php");
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}

$site_title = "小熠网络";
try {
    $stmt = $pdo->query("SELECT site_title FROM settings WHERE id = 1");
    $row = $stmt->fetch();
    if ($row) $site_title = $row['site_title'];
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>数据看板 - <?php echo htmlspecialchars($site_title); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'PingFang SC', 'Helvetica Neue', 'Microsoft YaHei', sans-serif;
            background: #f5f7fa;
            color: #1e293b;
            padding: 16px;
            min-height: 100vh;
        }
        .page-header { margin-bottom: 20px; }
        .page-title { font-size: 22px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
        .page-subtitle { font-size: 13px; color: #64748b; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 18px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            border: 1px solid #f1f5f9;
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 4px; height: 100%;
        }
        .stat-card.blue::before { background: linear-gradient(180deg, #3b82f6, #2563eb); }
        .stat-card.green::before { background: linear-gradient(180deg, #10b981, #059669); }
        .stat-card.orange::before { background: linear-gradient(180deg, #f59e0b, #d97706); }
        .stat-card.purple::before { background: linear-gradient(180deg, #8b5cf6, #7c3aed); }
        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            margin-bottom: 12px;
        }
        .stat-card.blue .stat-icon { background: #eff6ff; color: #2563eb; }
        .stat-card.green .stat-icon { background: #ecfdf5; color: #059669; }
        .stat-card.orange .stat-icon { background: #fffbeb; color: #d97706; }
        .stat-card.purple .stat-icon { background: #f5f3ff; color: #7c3aed; }
        .stat-label { font-size: 13px; color: #64748b; margin-bottom: 6px; font-weight: 500; }
        .stat-value { font-size: 26px; font-weight: 700; color: #1e293b; }
        .section-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 18px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            border: 1px solid #f1f5f9;
            margin-bottom: 16px;
        }
        .section-title {
            font-size: 16px; font-weight: 700; color: #1e293b;
            margin-bottom: 14px;
            display: flex; align-items: center; gap: 8px;
        }
        .section-title i { color: #3b82f6; }
        .chart-placeholder {
            height: 200px;
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            color: #94a3b8;
            font-size: 14px;
        }
        .chart-placeholder i { font-size: 36px; margin-bottom: 8px; opacity: 0.4; }
        @media (min-width: 768px) {
            .stats-grid { grid-template-columns: repeat(4, 1fr); }
            body { padding: 24px; max-width: 1200px; margin: 0 auto; }
        }
    </style>
</head>
<body>
    <div class="page-header">
        <h1 class="page-title">数据看板</h1>
        <p class="page-subtitle">实时监控平台运营数据</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-label">今日订单</div>
            <div class="stat-value">0</div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-yen-sign"></i></div>
            <div class="stat-label">今日收入</div>
            <div class="stat-value">¥0.00</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
            <div class="stat-label">新增用户</div>
            <div class="stat-value">0</div>
        </div>
        <div class="stat-card purple">
            <div class="stat-icon"><i class="fas fa-gift"></i></div>
            <div class="stat-label">抽奖次数</div>
            <div class="stat-value">0</div>
        </div>
    </div>

    <div class="section-card">
        <div class="section-title"><i class="fas fa-chart-line"></i>收入趋势</div>
        <div class="chart-placeholder">
            <div style="text-align:center;">
                <i class="fas fa-chart-area"></i>
                <div>图表数据加载中...</div>
            </div>
        </div>
    </div>

    <div class="section-card">
        <div class="section-title"><i class="fas fa-chart-bar"></i>订单统计</div>
        <div class="chart-placeholder">
            <div style="text-align:center;">
                <i class="fas fa-chart-bar"></i>
                <div>图表数据加载中...</div>
            </div>
        </div>
    </div>
</body>
</html>
