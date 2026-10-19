<?php
require_once './header.php';

$current_eadmin_id = get_user_user_id() ?? 0;

$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$search = trim($_GET['search'] ?? '');
$fighter_id = intval($_GET['fighter_id'] ?? 0);
$show_orders = isset($_GET['show_orders']);

$query_conditions = [
    "r.inviter_id = :eadmin_id",
    "r.inviter_type = 'eadmin'",
    "r.invitee_type = 'fighter'"
];
$params = [':eadmin_id' => $current_eadmin_id];

if (!empty($search)) {
    if (is_numeric($search)) {
        $query_conditions[] = "u.id = :search_id";
        $params[':search_id'] = $search;
    } else {
        $query_conditions[] = "u.username LIKE :search_name";
        $params[':search_name'] = "%$search%";
    }
}

$where_clause = implode(' AND ', $query_conditions);

$count_query = "SELECT COUNT(*) 
               FROM partner_service_relations r
               JOIN users u ON r.invitee_id = u.id
               WHERE $where_clause";
$count_stmt = $pdo->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_invited = $count_stmt->fetchColumn();

$fighters_query = "SELECT u.id, u.username, u.avatar, u.member_level, 
                  COUNT(o.id) AS order_count,
                  SUM(CASE WHEN o.order_status = 4 THEN o.price ELSE 0 END) AS total_income
                  FROM partner_service_relations r
                  JOIN users u ON r.invitee_id = u.id
                  LEFT JOIN orders o ON o.hunter_user_id = u.id
                  WHERE $where_clause
                  GROUP BY u.id
                  ORDER BY order_count DESC, total_income DESC
                  LIMIT :offset, :per_page";

$fighters_stmt = $pdo->prepare($fighters_query);
$offset = ($page - 1) * $per_page;
foreach ($params as $key => $value) {
    $fighters_stmt->bindValue($key, $value, PDO::PARAM_STR);
}
$fighters_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$fighters_stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$fighters_stmt->execute();
$invited_fighters = $fighters_stmt->fetchAll(PDO::FETCH_ASSOC);

$fighter_orders = [];
$selected_fighter = null;
if ($show_orders && $fighter_id > 0) {
    $verify_query = "SELECT u.id, u.username, u.avatar 
                    FROM partner_service_relations r
                    JOIN users u ON r.invitee_id = u.id
                    WHERE r.inviter_id = :eadmin_id
                    AND r.inviter_type = 'eadmin'
                    AND r.invitee_type = 'fighter'
                    AND u.id = :fighter_id";
    
    $verify_stmt = $pdo->prepare($verify_query);
    $verify_stmt->bindParam(':eadmin_id', $current_eadmin_id, PDO::PARAM_INT);
    $verify_stmt->bindParam(':fighter_id', $fighter_id, PDO::PARAM_INT);
    $verify_stmt->execute();
    
    if ($verify_stmt->rowCount() > 0) {
        $selected_fighter = $verify_stmt->fetch(PDO::FETCH_ASSOC);
        
        $orders_query = "SELECT 
                        o.id, o.order_no, o.product_name, o.price, 
                        o.create_time, o.complete_time, o.order_status,
                        p.username AS partner_name
                        FROM orders o
                        LEFT JOIN users p ON o.user_id = p.id
                        WHERE o.hunter_user_id = :fighter_id
                        ORDER BY o.create_time DESC
                        LIMIT 50";
        
        $orders_stmt = $pdo->prepare($orders_query);
        $orders_stmt->bindParam(':fighter_id', $fighter_id, PDO::PARAM_INT);
        $orders_stmt->execute();
        $fighter_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>打手管理</title>
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
        .container {
            padding: 16px;
        }
        .search-box {
            background: #ffffff;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 16px;
            display: flex;
            gap: 12px;
            animation: fadeInUp 0.5s ease;
        }
        .search-input {
            flex: 1;
            padding: 14px 16px;
            border: 2px solid #eee;
            border-radius: 12px;
            font-size: 15px;
            background: #f8f8f8;
            color: #1a1a1a;
            transition: all 0.3s ease;
        }
        .search-input:focus {
            outline: none;
            border-color: #f5b400;
            background: #ffffff;
        }
        .search-btn {
            padding: 14px 24px;
            background: #1a1a1a;
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .search-btn:hover {
            background: #333333;
            transform: translateY(-2px);
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding: 0 4px;
        }
        .section-title {
            font-size: 16px;
            font-weight: 600;
        }
        .section-count {
            font-size: 14px;
            color: #999;
        }
        .fighter-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            animation: fadeInUp 0.5s ease;
        }
        .fighter-info {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }
        .fighter-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 12px;
            border: 3px solid #f5f5f5;
        }
        .fighter-details {
            flex: 1;
        }
        .fighter-name {
            font-size: 17px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 4px;
        }
        .fighter-meta {
            font-size: 13px;
            color: #999;
            margin-bottom: 4px;
        }
        .fighter-level {
            display: inline-block;
            background: #f5b400;
            color: #1a1a1a;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .fighter-stats {
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
        .stat-value {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 4px;
        }
        .stat-label {
            font-size: 12px;
            color: #999;
        }
        .action-btn {
            width: 100%;
            padding: 12px;
            background: #f5f5f5;
            color: #1a1a1a;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .action-btn:hover {
            background: #e8e8e8;
            transform: translateY(-2px);
        }
        .order-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            animation: fadeInUp 0.5s ease;
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .order-no {
            font-size: 13px;
            color: #999;
        }
        .order-price {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a1a;
        }
        .order-product {
            font-size: 15px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 12px;
        }
        .order-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 8px;
        }
        .meta-item {
            font-size: 13px;
            color: #666;
        }
        .meta-item strong {
            color: #1a1a1a;
            font-weight: 500;
        }
        .order-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-completed {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .status-cancelled {
            background: #ffebee;
            color: #c62828;
        }
        .status-pending {
            background: #fff3e0;
            color: #ef6c00;
        }
        .order-time {
            font-size: 12px;
            color: #999;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
        }
        .page-link {
            padding: 10px 16px;
            background: #ffffff;
            border: 2px solid #eee;
            border-radius: 10px;
            color: #1a1a1a;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .page-link:hover {
            border-color: #f5b400;
            background: #fff9e6;
        }
        .page-link.active {
            background: #1a1a1a;
            border-color: #1a1a1a;
            color: #ffffff;
        }
        .page-link.disabled {
            opacity: 0.5;
            pointer-events: none;
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
        .fighter-card:nth-child(2) { animation-delay: 0.1s; }
        .fighter-card:nth-child(3) { animation-delay: 0.2s; }
        .fighter-card:nth-child(4) { animation-delay: 0.3s; }
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
        <div class="page-title">打手管理</div>
        <div style="width: 36px;"></div>
    </div>
    <div class="container">
        <?php if ($show_orders && $selected_fighter): ?>
            <div class="section-header">
                <div class="section-title"><?= htmlspecialchars($selected_fighter['username']) ?> 的订单</div>
                <div class="section-count"><?= count($fighter_orders) ?> 单</div>
            </div>
            
            <?php if (!empty($fighter_orders)): ?>
                <?php foreach ($fighter_orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-no">订单号: <?= $order['order_no'] ?></div>
                            <div class="order-price">¥<?= number_format($order['price'], 2) ?></div>
                        </div>
                        <div class="order-product"><?= htmlspecialchars($order['product_name']) ?></div>
                        <div class="order-meta">
                            <div class="meta-item">
                                商家: <strong><?= htmlspecialchars($order['partner_name'] ?? '未知') ?></strong>
                            </div>
                            <span class="order-status <?php 
                                switch($order['order_status']) {
                                    case 4: echo 'status-completed'; break;
                                    case 5: echo 'status-cancelled'; break;
                                    default: echo 'status-pending';
                                }
                            ?>">
                                <?php 
                                switch($order['order_status']) {
                                    case 4: echo '已完成'; break;
                                    case 5: echo '已取消'; break;
                                    default: echo '进行中';
                                }
                                ?>
                            </span>
                        </div>
                        <div class="order-time">
                            接单: <?= date('m-d H:i', strtotime($order['create_time'])) ?>
                            <?php if ($order['complete_time']): ?>
                                &nbsp;&nbsp;完成: <?= date('m-d H:i', strtotime($order['complete_time'])) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>暂无订单记录</p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="search-box">
                <form class="search-form" method="get" id="searchForm" style="display: flex; gap: 12px; width: 100%;">
                    <input type="hidden" name="page" value="1">
                    <input type="text" name="search" class="search-input" 
                           placeholder="输入打手ID或名称搜索" 
                           value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
            
            <div class="section-header">
                <div class="section-title">打手列表</div>
                <div class="section-count">共 <?= $total_invited ?> 人</div>
            </div>
            
            <?php if (!empty($invited_fighters)): ?>
                <?php foreach ($invited_fighters as $fighter): ?>
                    <div class="fighter-card">
                        <div class="fighter-info">
                            <img src="../assets/images/userds/<?= htmlspecialchars($fighter['avatar'] ?? 'default_avatar.png') ?>" 
                                 onerror="this.src='../assets/images/user.png'" 
                                 class="fighter-avatar" 
                                 alt="<?= htmlspecialchars($fighter['username']) ?>">
                            <div class="fighter-details">
                                <div class="fighter-name"><?= htmlspecialchars($fighter['username']) ?></div>
                                <div class="fighter-meta">ID: <?= $fighter['id'] ?></div>
                                <span class="fighter-level"><?= htmlspecialchars($fighter['member_level'] ?? '普通打手') ?></span>
                            </div>
                        </div>
                        <div class="fighter-stats">
                            <div class="stat-box">
                                <div class="stat-value"><?= $fighter['order_count'] ?></div>
                                <div class="stat-label">接单数量</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-value">¥<?= number_format($fighter['total_income'], 2) ?></div>
                                <div class="stat-label">总收入</div>
                            </div>
                        </div>
                        <a href="?show_orders=1&fighter_id=<?= $fighter['id'] ?>&search=<?= urlencode($search) ?>" 
                           class="action-btn">
                            <i class="fas fa-list"></i> 查看订单
                        </a>
                    </div>
                <?php endforeach; ?>
                
                <?php if ($total_invited > $per_page): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" class="page-link">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-link disabled">
                                <i class="fas fa-chevron-left"></i>
                            </span>
                        <?php endif; ?>
                        
                        <?php 
                        $total_pages = ceil($total_invited / $per_page);
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1) {
                            echo '<a href="?page=1&search='.urlencode($search).'" class="page-link">1</a>';
                            if ($start_page > 2) {
                                echo '<span class="page-link disabled">...</span>';
                            }
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            $active = $i == $page ? 'active' : '';
                            echo '<a href="?page='.$i.'&search='.urlencode($search).'" class="page-link '.$active.'">'.$i.'</a>';
                        }
                        
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<span class="page-link disabled">...</span>';
                            }
                            echo '<a href="?page='.$total_pages.'&search='.urlencode($search).'" class="page-link">'.$total_pages.'</a>';
                        }
                        ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" class="page-link">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-link disabled">
                                <i class="fas fa-chevron-right"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <p>暂无邀请的打手</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var items = document.querySelectorAll('.fighter-card, .order-card');
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
