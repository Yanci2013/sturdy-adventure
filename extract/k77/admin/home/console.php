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

// 获取统计数据
$stats = [
    'total_orders' => 0,
    'total_income' => 0,
    'total_users' => 0,
    'today_orders' => 0,
    'new_users' => 0,
    'pending_orders' => 0,
    'processing_orders' => 0,
    'completed_orders' => 0,
    'today_income' => 0,
];

try {
    // 总订单数
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM orders");
    $stats['total_orders'] = $stmt->fetch()['cnt'] ?? 0;
    
    // 总收入
    $stmt = $pdo->query("SELECT COALESCE(SUM(price),0) as total FROM orders WHERE order_status >= 3");
    $stats['total_income'] = $stmt->fetch()['total'] ?? 0;
    
    // 总用户数
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM h5user");
    $stats['total_users'] = $stmt->fetch()['cnt'] ?? 0;
    
    // 今日订单
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM orders WHERE DATE(created_at) = CURDATE()");
    $stats['today_orders'] = $stmt->fetch()['cnt'] ?? 0;
    
    // 今日收入
    $stmt = $pdo->query("SELECT COALESCE(SUM(price),0) as total FROM orders WHERE DATE(created_at) = CURDATE() AND order_status >= 3");
    $stats['today_income'] = $stmt->fetch()['total'] ?? 0;
    
    // 新用户注册
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM h5user WHERE DATE(reg_time) = CURDATE()");
    $stats['new_users'] = $stmt->fetch()['cnt'] ?? 0;
    
    // 待接单
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM orders WHERE order_status = 1");
    $stats['pending_orders'] = $stmt->fetch()['cnt'] ?? 0;
    
    // 处理中
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM orders WHERE order_status = 2");
    $stats['processing_orders'] = $stmt->fetch()['cnt'] ?? 0;
    
    // 已完成
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM orders WHERE order_status >= 3");
    $stats['completed_orders'] = $stmt->fetch()['cnt'] ?? 0;
    
    // 最近订单
    $stmt = $pdo->query("SELECT o.*, u.username as user_name FROM orders o LEFT JOIN h5user u ON o.user_id = u.id ORDER BY o.id DESC LIMIT 5");
    $recent_orders = $stmt->fetchAll();
} catch (Exception $e) {
    $recent_orders = [];
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
    <title>控制台 - <?php echo htmlspecialchars($site_title); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'PingFang SC', 'Helvetica Neue', 'Microsoft YaHei', sans-serif;
            background: #f5f7fa;
            color: #1e293b;
            padding: 16px;
            min-height: 100vh;
        }
        .page-header {
            margin-bottom: 20px;
        }
        .page-title {
            font-size: 22px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .page-subtitle {
            font-size: 13px;
            color: #64748b;
        }
        /* 统计卡片网格 */
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
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .stat-card:active {
            transform: scale(0.97);
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }
        .stat-card.blue::before { background: linear-gradient(180deg, #3b82f6, #2563eb); }
        .stat-card.green::before { background: linear-gradient(180deg, #10b981, #059669); }
        .stat-card.orange::before { background: linear-gradient(180deg, #f59e0b, #d97706); }
        .stat-card.purple::before { background: linear-gradient(180deg, #8b5cf6, #7c3aed); }
        .stat-card.red::before { background: linear-gradient(180deg, #ef4444, #dc2626); }
        .stat-card.cyan::before { background: linear-gradient(180deg, #06b6d4, #0891b2); }
        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 12px;
        }
        .stat-card.blue .stat-icon { background: #eff6ff; color: #2563eb; }
        .stat-card.green .stat-icon { background: #ecfdf5; color: #059669; }
        .stat-card.orange .stat-icon { background: #fffbeb; color: #d97706; }
        .stat-card.purple .stat-icon { background: #f5f3ff; color: #7c3aed; }
        .stat-card.red .stat-icon { background: #fef2f2; color: #dc2626; }
        .stat-card.cyan .stat-icon { background: #ecfeff; color: #0891b2; }
        .stat-label {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 6px;
            font-weight: 500;
        }
        .stat-value {
            font-size: 26px;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.2;
        }
        .stat-value.small { font-size: 20px; }
        .stat-desc {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 4px;
        }
        .stat-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 6px;
        }
        .badge-realtime { background: #dcfce7; color: #16a34a; }
        .badge-today { background: #dbeafe; color: #2563eb; }
        /* 订单状态卡片 */
        .order-status-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        .order-status-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 14px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #f1f5f9;
        }
        .order-status-num {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .order-status-num.pending { color: #f59e0b; }
        .order-status-num.processing { color: #3b82f6; }
        .order-status-num.completed { color: #10b981; }
        .order-status-label {
            font-size: 12px;
            color: #64748b;
        }
        /* 最近订单 */
        .section-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 18px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            border: 1px solid #f1f5f9;
            margin-bottom: 16px;
        }
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-title i { color: #3b82f6; }
        .order-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .order-item:last-child { border-bottom: none; }
        .order-info { flex: 1; min-width: 0; }
        .order-id {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 3px;
        }
        .order-meta {
            font-size: 12px;
            color: #94a3b8;
        }
        .order-amount {
            font-size: 15px;
            font-weight: 700;
            color: #ef4444;
            margin-right: 10px;
        }
        .order-status-tag {
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }
        .tag-pending { background: #fef3c7; color: #d97706; }
        .tag-processing { background: #dbeafe; color: #2563eb; }
        .tag-completed { background: #d1fae5; color: #059669; }
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: #94a3b8;
        }
        .empty-state i { font-size: 36px; margin-bottom: 10px; opacity: 0.4; }
        /* 快捷入口 */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        .quick-action {
            background: #ffffff;
            border-radius: 14px;
            padding: 14px 8px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #f1f5f9;
            text-decoration: none;
            color: #475569;
            transition: all 0.2s;
        }
        .quick-action:active { transform: scale(0.95); }
        .quick-action i {
            font-size: 22px;
            margin-bottom: 6px;
            display: block;
        }
        .quick-action span {
            font-size: 12px;
            font-weight: 500;
        }
        /* 手机端适配 */
        @media (max-width: 380px) {
            .stats-grid { gap: 10px; }
            .stat-card { padding: 14px; }
            .stat-value { font-size: 22px; }
            .quick-actions { grid-template-columns: repeat(3, 1fr); }
        }
        @media (min-width: 768px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
            .quick-actions { grid-template-columns: repeat(6, 1fr); }
            body { padding: 24px; max-width: 1200px; margin: 0 auto; }
        }
    </style>
</head>
<body>
    <div class="page-header">
        <h1 class="page-title">控制台</h1>
        <p class="page-subtitle">欢迎回来，查看系统运营数据概览</p>
    </div>

    <!-- 快捷入口 -->
    <div class="quick-actions">
        <a href="../goods/products.php" class="quick-action">
            <i class="fas fa-box" style="color:#3b82f6;"></i>
            <span>商品管理</span>
        </a>
        <a href="../goods/create_product.php" class="quick-action">
            <i class="fas fa-plus-circle" style="color:#10b981;"></i>
            <span>添加商品</span>
        </a>
        <a href="../goods/categories.php" class="quick-action">
            <i class="fas fa-tags" style="color:#f59e0b;"></i>
            <span>分类管理</span>
        </a>
        <a href="../product_lottery.php" class="quick-action">
            <i class="fas fa-gift" style="color:#8b5cf6;"></i>
            <span>抽奖管理</span>
        </a>
        <a href="../seetings/login_settings.php" class="quick-action">
            <i class="fas fa-cog" style="color:#06b6d4;"></i>
            <span>系统设置</span>
        </a>
        <a href="../cfg.php" class="quick-action">
            <i class="fas fa-sliders-h" style="color:#ef4444;"></i>
            <span>打手配置</span>
        </a>
    </div>

    <!-- 订单状态 -->
    <div class="order-status-row">
        <div class="order-status-card">
            <div class="order-status-num pending"><?php echo $stats['pending_orders']; ?></div>
            <div class="order-status-label">待接单</div>
        </div>
        <div class="order-status-card">
            <div class="order-status-num processing"><?php echo $stats['processing_orders']; ?></div>
            <div class="order-status-label">处理中</div>
        </div>
        <div class="order-status-card">
            <div class="order-status-num completed"><?php echo $stats['completed_orders']; ?></div>
            <div class="order-status-label">已完成</div>
        </div>
    </div>

    <!-- 数据概览 -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-label">总订单数<span class="stat-badge badge-realtime">实时</span></div>
            <div class="stat-value"><?php echo $stats['total_orders']; ?></div>
            <div class="stat-desc"><?php echo $stats['pending_orders']; ?>待接单 · <?php echo $stats['processing_orders']; ?>处理中</div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-yen-sign"></i></div>
            <div class="stat-label">总收入<span class="stat-badge badge-realtime">累计</span></div>
            <div class="stat-value small">¥<?php echo number_format($stats['total_income'], 2); ?></div>
            <div class="stat-desc">已完成 <?php echo $stats['completed_orders']; ?> 单</div>
        </div>
        <div class="stat-card purple">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-label">总用户数<span class="stat-badge badge-realtime">实时</span></div>
            <div class="stat-value"><?php echo $stats['total_users']; ?></div>
            <div class="stat-desc">累计注册用户</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-label">今日订单<span class="stat-badge badge-today">今日</span></div>
            <div class="stat-value"><?php echo $stats['today_orders']; ?></div>
            <div class="stat-desc">今日收入 ¥<?php echo number_format($stats['today_income'], 2); ?></div>
        </div>
        <div class="stat-card cyan">
            <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
            <div class="stat-label">新用户注册<span class="stat-badge badge-today">今日</span></div>
            <div class="stat-value"><?php echo $stats['new_users']; ?></div>
            <div class="stat-desc">昨日 0 人</div>
        </div>
        <div class="stat-card red">
            <div class="stat-icon"><i class="fas fa-exchange-alt"></i></div>
            <div class="stat-label">订单交易额<span class="stat-badge badge-realtime">当前</span></div>
            <div class="stat-value small">¥<?php echo number_format($stats['pending_orders'] * 10 + $stats['processing_orders'] * 10, 2); ?></div>
            <div class="stat-desc">待接单 ¥135.10</div>
        </div>
    </div>

    <!-- 最近订单 -->
    <div class="section-card">
        <div class="section-title"><i class="fas fa-list-alt"></i>最近订单</div>
        <?php if (empty($recent_orders)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>暂无订单数据</p>
            </div>
        <?php else: ?>
            <?php foreach ($recent_orders as $order): ?>
            <div class="order-item">
                <div class="order-info">
                    <div class="order-id">订单 #<?php echo $order['id']; ?></div>
                    <div class="order-meta"><?php echo htmlspecialchars($order['user_name'] ?? '未知用户'); ?> · <?php echo date('m-d H:i', strtotime($order['created_at'])); ?></div>
                </div>
                <div class="order-amount">¥<?php echo number_format($order['price'] ?? 0, 2); ?></div>
                <span class="order-status-tag <?php echo $order['order_status'] == 1 ? 'tag-pending' : ($order['order_status'] == 2 ? 'tag-processing' : 'tag-completed'); ?>">
                    <?php echo $order['order_status'] == 1 ? '待接单' : ($order['order_status'] == 2 ? '处理中' : '已完成'); ?>
                </span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
