<?php
ob_start();
require_once __DIR__.'/../../includes/session_manager.php';
require_once __DIR__.'/../../includes/common.php';

// 检查用户权限
if (!is_admin_logged_in()) {
    $_SESSION['error_msg'] = "请先登录管理员账号";
    header("Location: ../login.php");
    exit();
}

$statusText = [
    1 => '待接单',
    2 => '处理中',
    3 => '待结算',
    4 => '已完成',
    5 => '取消待审核中',
    6 => '客服介入',
    7 => '已撤销'
];

$statusClass = [
    1 => 'status-pending',
    2 => 'status-processing',
    3 => 'status-processing',
    4 => 'status-completed',
    5 => 'status-cancelled',
    6 => 'status-processing',
    7 => 'status-cancelled'
];

$search = $_GET['search'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$base_sql = "SELECT o.*, 
               u.username as issuer_username, u.phone as issuer_phone, u.avatar as issuer_avatar,
               hu.username as hunter_username, hu.phone as hunter_phone, hu.avatar as hunter_avatar,
               p.username as fadan_username
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        LEFT JOIN users hu ON o.hunter_user_id = hu.id
        LEFT JOIN partners p ON o.fadan_id = p.id
        WHERE o.order_status = 5 AND o.cancel_review_status = 'pending'";

$where_conditions = [];
$params = [];
$param_types = [];

if (!empty($search)) {
    $where_conditions[] = "(o.id LIKE :search OR o.order_no LIKE :search2 OR o.product_name LIKE :search3)";
    $params[':search'] = "%$search%";
    $params[':search2'] = "%$search%";
    $params[':search3'] = "%$search%";
    $param_types[':search'] = PDO::PARAM_STR;
    $param_types[':search2'] = PDO::PARAM_STR;
    $param_types[':search3'] = PDO::PARAM_STR;
}

if (!empty($start_date) && !empty($end_date)) {
    $where_conditions[] = "o.cancel_time BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $start_date . ' 00:00:00';
    $params[':end_date'] = $end_date . ' 23:59:59';
    $param_types[':start_date'] = PDO::PARAM_STR;
    $param_types[':end_date'] = PDO::PARAM_STR;
} elseif (!empty($start_date)) {
    $where_conditions[] = "o.cancel_time >= :start_date";
    $params[':start_date'] = $start_date . ' 00:00:00';
    $param_types[':start_date'] = PDO::PARAM_STR;
} elseif (!empty($end_date)) {
    $where_conditions[] = "o.cancel_time <= :end_date";
    $params[':end_date'] = $end_date . ' 23:59:59';
    $param_types[':end_date'] = PDO::PARAM_STR;
}

$where_sql = empty($where_conditions) ? '' : ' AND ' . implode(' AND ', $where_conditions);
$count_sql = "SELECT COUNT(*) as total FROM orders o WHERE o.order_status = 5 AND o.cancel_review_status = 'pending'" . $where_sql;
$sql = $base_sql . $where_sql . " ORDER BY o.cancel_time DESC, o.id DESC LIMIT :limit OFFSET :offset";

$params[':limit'] = $per_page;
$params[':offset'] = $offset;
$param_types[':limit'] = PDO::PARAM_INT;
$param_types[':offset'] = PDO::PARAM_INT;

try {
    $count_stmt = $pdo->prepare($count_sql);
    
    foreach ($params as $key => $value) {
        if ($key != ':limit' && $key != ':offset') {
            $count_stmt->bindValue($key, $value, $param_types[$key] ?? PDO::PARAM_STR);
        }
    }
    
    $count_stmt->execute();
    $total_orders = $count_stmt->fetchColumn();
    
    $total_pages = ceil($total_orders / $per_page);
    
    $stmt = $pdo->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, $param_types[$key] ?? PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("数据库查询失败: " . $e->getMessage());
}

$pageTitle = '取消订单管理';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Ma+Shan+Zheng&family=Noto+Serif+SC:wght@400;500;600;700&family=ZCOOL+XiaoWei&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #ffffff; color: #333; line-height: 1.5; padding: 20px; font-size: 13px; }
    .container { max-width: 1200px; margin: 0 auto; }
    h1 { font-size: 18px; font-weight: 600; margin-bottom: 16px; color: #000; }
    h2 { font-size: 14px; font-weight: 600; margin: 20px 0 12px 0; color: #333; }
    
    .card { background: #fafafa; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
    .card-header { font-size: 14px; font-weight: 600; color: #333; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #eee; }
    
    .filter-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 16px; }
    .form-group { margin-bottom: 0; }
    .form-label { display: block; font-size: 12px; color: #666; margin-bottom: 6px; }
    .form-input, .form-select { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; }
    .form-input:focus, .form-select:focus { outline: none; border-color: #1976d2; box-shadow: 0 0 0 2px rgba(25, 118, 210, 0.2); }
    
    .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; cursor: pointer; text-decoration: none; transition: all 0.2s; }
    .btn-primary { background: #1976d2; color: #fff; border-color: #1976d2; }
    .btn-primary:hover { background: #1565c0; }
    .btn-secondary { background: #f5f5f5; color: #333; }
    .btn-secondary:hover { background: #e0e0e0; }
    .btn-sm { padding: 6px 12px; font-size: 12px; }
    .btn-group { display: flex; gap: 8px; align-items: flex-end; flex-wrap: wrap; }
    
    .table-wrapper { overflow-x: auto; margin-top: 16px; }
    table { width: 100%; border-collapse: collapse; font-size: 12px; }
    th { background: #f8f8f8; color: #666; font-weight: 500; padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; }
    td { padding: 10px 12px; border-bottom: 1px solid #f5f5f5; color: #333; }
    tr:hover td { background: #fafafa; }
    
    .cancel-reason { max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    
    .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 500; }
    .badge-warning { background: #fff3e0; color: #f57c00; }
    
    .empty-state { text-align: center; padding: 40px 20px; }
    .empty-icon { font-size: 32px; color: #ccc; margin-bottom: 8px; }
    .empty-message { color: #999; font-size: 14px; }
    
    .pagination { display: flex; justify-content: center; gap: 4px; margin-top: 20px; flex-wrap: wrap; }
    .pagination a { display: inline-flex; align-items: center; justify-content: center; min-width: 32px; height: 32px; padding: 0 8px; border: 1px solid #ddd; background: #fff; color: #333; font-size: 12px; text-decoration: none; border-radius: 4px; transition: all 0.2s; }
    .pagination a:hover { background: #f5f5f5; }
    .pagination a.active { background: #1976d2; color: #fff; border-color: #1976d2; }
    
    @media (max-width: 768px) {
      .filter-form { grid-template-columns: 1fr; }
    }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚫 取消订单管理</h1>
        <div class="card">
            <div class="card-header">🔍 筛选条件</div>
            
            <form method="GET">
                <div class="filter-form">
                    <div class="form-group">
                        <label class="form-label">订单号</label>
                        <input type="text" name="search" placeholder="请输入订单号" class="form-input" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">开始日期</label>
                        <input type="date" name="start_date" class="form-input" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">结束日期</label>
                        <input type="date" name="end_date" class="form-input" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-search"></i> 搜索
                    </button>
                    <a href="orders-qxsh.php" class="btn btn-secondary">重置</a>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">📋 取消订单列表</div>
            
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>订单号</th>
                            <th>商品名称</th>
                            <th>取消原因</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <div class="empty-icon">📭</div>
                                        <div class="empty-message">没有找到取消订单记录</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($order['order_no']); ?></td>
                                <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                <td class="cancel-reason" title="<?php echo htmlspecialchars($order['cancel_reason']); ?>">
                                    <?php echo htmlspecialchars($order['cancel_reason']); ?>
                                </td>
                                <td>
                                    <span class="badge badge-warning">待审核</span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="admin_order_view.php?id=<?php echo $order['id']; ?>" class="btn btn-secondary btn-sm">
                                            <i class="fa fa-eye"></i> 查看
                                        </a>
                                        <a href="orders-approved.php?id=<?php echo $order['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fa fa-gavel"></i> 处理
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <a href="orders-qxsh.php?<?php 
                    echo http_build_query(array_merge(
                        ['page' => 1],
                        array_filter([
                            'search' => $search,
                            'start_date' => $start_date,
                            'end_date' => $end_date
                        ])
                    )); ?>" title="第一页">
                    <i class="fa fa-angle-double-left"></i>
                </a>
                
                <a href="orders-qxsh.php?<?php 
                    echo http_build_query(array_merge(
                        ['page' => max(1, $page - 1)],
                        array_filter([
                            'search' => $search,
                            'start_date' => $start_date,
                            'end_date' => $end_date
                        ])
                    )); ?>" title="上一页">
                    <i class="fa fa-angle-left"></i>
                </a>
                
                <?php 
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($end_page - $start_page < 4) {
                    if ($page < 3) {
                        $end_page = min($total_pages, $start_page + 4);
                    } else {
                        $start_page = max(1, $end_page - 4);
                    }
                }
                
                for ($i = $start_page; $i <= $end_page; $i++): 
                ?>
                    <a href="orders-qxsh.php?<?php 
                            echo http_build_query(array_merge(
                                ['page' => $i],
                                array_filter([
                                    'search' => $search,
                                    'start_date' => $start_date,
                                    'end_date' => $end_date
                                ])
                            )); ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <a href="orders-qxsh.php?<?php 
                    echo http_build_query(array_merge(
                        ['page' => min($total_pages, $page + 1)],
                        array_filter([
                            'search' => $search,
                            'start_date' => $start_date,
                            'end_date' => $end_date
                        ])
                    )); ?>" title="下一页">
                    <i class="fa fa-angle-right"></i>
                </a>
                
                <a href="orders-qxsh.php?<?php 
                    echo http_build_query(array_merge(
                        ['page' => $total_pages],
                        array_filter([
                            'search' => $search,
                            'start_date' => $start_date,
                            'end_date' => $end_date
                        ])
                    )); ?>" title="最后一页">
                    <i class="fa fa-angle-double-right"></i>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(10px)';
            
            setTimeout(() => {
                card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 50 + index * 30);
        });
    });
    </script>
</body>
</html>