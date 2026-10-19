<?php
require_once '../includes/common.php';
// 检查PDO连接状态
if (!isset($pdo) || !$pdo instanceof PDO) {
    die('数据库连接失败: PDO对象未初始化');
}
require_once 'header.php';
// 再次检查PDO连接状态
if (!isset($pdo) || !$pdo instanceof PDO) {
    die('数据库连接失败: header.php后PDO对象丢失');
}

// 检查用户登录状态
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 获取筛选参数
$status = isset($_GET['status']) ? $_GET['status'] : 'online';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'online';
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

// 构建查询条件
$conditions = [];
$params = [];

if ($status === 'online') {
    $conditions[] = "h.online_status = 1 AND h.order_status = 0";
} elseif ($status === 'busy') {
    $conditions[] = "h.order_status = 1";
}

if ($keyword) {
    $conditions[] = "(h.username LIKE ? OR h.tags LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}

$where_clause = $conditions ? "WHERE " . implode(' AND ', $conditions) : '';

// 排序条件
$order_by = "h.online_status DESC, h.created_at DESC";
if ($sort === 'orders') {
    $order_by = "h.order_count DESC";
} elseif ($sort === 'rating') {
    $order_by = "h.rating DESC";
}

// 获取打手列表
$hunters = [];
try {
    $stmt = $pdo->prepare("SELECT h.*, 
       COUNT(DISTINCT d.id) as dynamic_count,
       MAX(d.created_at) as latest_dynamic_time,
       (SELECT content FROM hunter_dynamics WHERE hunter_id = h.id AND status = 1 ORDER BY created_at DESC LIMIT 1) as latest_dynamic,
       (SELECT GROUP_CONCAT(content SEPARATOR '||') FROM hunter_evaluations WHERE hunter_id = h.id ORDER BY created_at DESC LIMIT 2) as recent_evaluations
                       FROM hunters h
                       LEFT JOIN hunter_dynamics d ON h.id = d.hunter_id AND d.status = 1
                       $where_clause
                       GROUP BY h.id
                       ORDER BY $order_by
                       LIMIT 20");
    $stmt->execute($params);
    $hunters = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('获取打手列表失败: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>打手列表</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { -webkit-tap-highlight-color: transparent; }
        .page-header {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            padding: 20px;
            border-radius: 0 0 24px 24px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(59,130,246,0.3);
        }
        .page-title {
            color: #fff;
            font-size: 22px;
            font-weight: 700;
            margin: 0;
        }
        .page-subtitle {
            color: rgba(255,255,255,0.8);
            font-size: 13px;
            margin-top: 4px;
        }
        .filter-bar {
            background: #fff;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: 0 2px 12px rgba(59,130,246,0.08);
        }
        .filter-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        .filter-row:last-child { margin-bottom: 0; }
        .filter-select {
            flex: 1;
            padding: 10px 14px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            color: #334155;
            background: #f8fafc;
            outline: none;
            transition: all 0.2s;
        }
        .filter-select:focus {
            border-color: #3b82f6;
            background: #fff;
        }
        .search-input {
            flex: 2;
            padding: 10px 14px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            color: #334155;
            background: #f8fafc;
            outline: none;
            transition: all 0.2s;
        }
        .search-input:focus {
            border-color: #3b82f6;
            background: #fff;
        }
        .search-btn {
            padding: 10px 20px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .search-btn:active { transform: scale(0.96); }
        .hunter-card {
            background: #fff;
            border-radius: 18px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 2px 12px rgba(59,130,246,0.08);
            border: 1px solid rgba(59,130,246,0.06);
            margin-bottom: 14px;
        }
        .hunter-card:active {
            transform: scale(0.98);
            box-shadow: 0 4px 20px rgba(59,130,246,0.15);
        }
        .hunter-card-body {
            padding: 16px;
        }
        .hunter-card-top {
            display: flex;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .hunter-avatar-wrap {
            position: relative;
            margin-right: 14px;
            flex-shrink: 0;
        }
        .hunter-avatar {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            object-fit: cover;
            border: 2px solid #e0ecff;
        }
        .hunter-status-dot {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 2px solid #fff;
        }
        .status-online { background: #10b981; }
        .status-busy { background: #f59e0b; }
        .status-offline { background: #94a3b8; }
        .hunter-info {
            flex: 1;
            min-width: 0;
        }
        .hunter-name-row {
            display: flex;
            align-items: center;
            margin-bottom: 6px;
        }
        .hunter-name {
            font-size: 17px;
            font-weight: 700;
            color: #1e293b;
            margin-right: 8px;
        }
        .hunter-status-badge {
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-online { background: #d1fae5; color: #059669; }
        .badge-busy { background: #fef3c7; color: #d97706; }
        .badge-offline { background: #f1f5f9; color: #64748b; }
        .hunter-stats {
            display: flex;
            gap: 14px;
            font-size: 13px;
            color: #64748b;
        }
        .hunter-stats span {
            display: flex;
            align-items: center;
            gap: 3px;
        }
        .stat-rating { color: #f59e0b; font-weight: 600; }
        .stat-orders { color: #3b82f6; font-weight: 600; }
        .hunter-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 10px;
        }
        .hunter-tag {
            padding: 3px 10px;
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            color: #2563eb;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
        }
        .hunter-desc {
            font-size: 13px;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .hunter-dynamic {
            background: linear-gradient(135deg, #f0f9ff, #eff6ff);
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 12px;
            border-left: 3px solid #3b82f6;
        }
        .hunter-dynamic p {
            font-size: 13px;
            color: #334155;
            margin: 0 0 4px 0;
            line-height: 1.4;
        }
        .hunter-dynamic small {
            font-size: 11px;
            color: #94a3b8;
        }
        .hunter-action-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .hunter-action-btn:active {
            transform: scale(0.97);
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 56px;
            margin-bottom: 16px;
            opacity: 0.4;
        }
        .empty-state p {
            font-size: 15px;
            margin: 0;
        }
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 12px;
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

    <!-- 页面头部 -->
    <div class="page-header">
        <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i>返回首页</a>
        <h1 class="page-title"><i class="fas fa-users me-2"></i>打手列表</h1>
        <p class="page-subtitle">选择心仪的打手，开启你的游戏之旅</p>
    </div>

    <div style="padding: 0 16px 100px 16px; max-width: 600px; margin: 0 auto;">
        <!-- 筛选栏 -->
        <div class="filter-bar">
            <form method="get">
                <div class="filter-row">
                    <select class="filter-select" name="status" onchange="this.form.submit()">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>全部状态</option>
                        <option value="online" <?php echo $status === 'online' ? 'selected' : ''; ?>>在线打手</option>
                        <option value="busy" <?php echo $status === 'busy' ? 'selected' : ''; ?>>接单中</option>
                    </select>
                    <select class="filter-select" name="sort" onchange="this.form.submit()">
                        <option value="online" <?php echo $sort === 'online' ? 'selected' : ''; ?>>在线优先</option>
                        <option value="orders" <?php echo $sort === 'orders' ? 'selected' : ''; ?>>接单量</option>
                        <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>好评率</option>
                    </select>
                </div>
                <div class="filter-row">
                    <input type="text" class="search-input" name="keyword" placeholder="搜索打手名称或标签" value="<?php echo htmlspecialchars($keyword); ?>">
                    <button class="search-btn" type="submit"><i class="fas fa-search"></i></button>
                </div>
            </form>
        </div>

        <!-- 打手列表 -->
        <?php if (empty($hunters)): ?>
            <div class="empty-state">
                <i class="fas fa-user-slash"></i>
                <p>未找到符合条件的打手</p>
            </div>
        <?php else: ?>
            <?php foreach ($hunters as $hunter): ?>
                <div class="hunter-card">
                    <div class="hunter-card-body">
                        <div class="hunter-card-top">
                            <div class="hunter-avatar-wrap">
                                <img src="<?php echo $hunter['avatar'] ? $hunter['avatar'] : 'https://img.icons8.com/ios-filled/100/3b82f6/user.png'; ?>" alt="<?php echo htmlspecialchars($hunter['username']); ?>" class="hunter-avatar">
                                <span class="hunter-status-dot <?php echo $hunter['online_status'] ? 'status-online' : ($hunter['order_status'] ? 'status-busy' : 'status-offline'); ?>"></span>
                            </div>
                            <div class="hunter-info">
                                <div class="hunter-name-row">
                                    <span class="hunter-name"><?php echo htmlspecialchars($hunter['username']); ?></span>
                                    <span class="hunter-status-badge <?php echo $hunter['online_status'] ? 'badge-online' : ($hunter['order_status'] ? 'badge-busy' : 'badge-offline'); ?>">
                                        <?php echo $hunter['online_status'] ? '在线' : ($hunter['order_status'] ? '接单中' : '离线'); ?>
                                    </span>
                                </div>
                                <div class="hunter-stats">
                                    <span><i class="fas fa-star stat-rating"></i><?php echo number_format($hunter['rating'], 1); ?></span>
                                    <span><i class="fas fa-check-circle stat-orders"></i><?php echo $hunter['order_count']; ?>单</span>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($hunter['tags'])): ?>
                            <div class="hunter-tags">
                                <?php $tags = explode(',', $hunter['tags']); ?>
                                <?php foreach ($tags as $tag): ?>
                                    <?php if (trim($tag)): ?>
                                        <span class="hunter-tag"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($hunter['introduction'])): ?>
                            <p class="hunter-desc"><?php echo htmlspecialchars($hunter['introduction']); ?></p>
                        <?php endif; ?>

                        <?php if ($hunter['dynamic_count'] > 0 && !empty($hunter['latest_dynamic'])): ?>
                            <div class="hunter-dynamic">
                                <p><?php echo htmlspecialchars($hunter['latest_dynamic']); ?></p>
                                <small><?php echo date('m-d H:i', strtotime($hunter['latest_dynamic_time'])); ?></small>
                            </div>
                        <?php endif; ?>

                        <a href="hunter_detail.php?id=<?php echo $hunter['id']; ?>" class="hunter-action-btn">
                            <i class="fas fa-user-plus"></i>查看详情并下单
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>