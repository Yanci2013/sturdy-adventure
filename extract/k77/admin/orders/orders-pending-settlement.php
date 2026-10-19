<?php
ob_start();
require_once __DIR__.'/../../includes/common.php';
session_start();

// 订单状态映射
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
    1 => 'badge-warning',
    2 => 'badge-secondary',
    3 => 'badge-info',
    4 => 'badge-success',
    5 => 'badge-secondary',
    6 => 'badge-secondary',
    7 => 'badge-secondary'
];

$search = $_GET['search'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$fadan_id = $_GET['fadan_id'] ?? '';

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
        LEFT JOIN partners p ON o.fadan_id = p.id";

$where_conditions = [];
$params = [];
$param_types = [];

// 只查询待结算状态的订单 (status = 3)
$where_conditions[] = "o.order_status = 3";

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
    $where_conditions[] = "o.create_time BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $start_date . ' 00:00:00';
    $params[':end_date'] = $end_date . ' 23:59:59';
    $param_types[':start_date'] = PDO::PARAM_STR;
    $param_types[':end_date'] = PDO::PARAM_STR;
} elseif (!empty($start_date)) {
    $where_conditions[] = "o.create_time >= :start_date";
    $params[':start_date'] = $start_date . ' 00:00:00';
    $param_types[':start_date'] = PDO::PARAM_STR;
} elseif (!empty($end_date)) {
    $where_conditions[] = "o.create_time <= :end_date";
    $params[':end_date'] = $end_date . ' 23:59:59';
    $param_types[':end_date'] = PDO::PARAM_STR;
}

if (!empty($min_price) && !empty($max_price)) {
    $where_conditions[] = "o.price BETWEEN :min_price AND :max_price";
    $params[':min_price'] = $min_price;
    $params[':max_price'] = $max_price;
    $param_types[':min_price'] = PDO::PARAM_STR;
    $param_types[':max_price'] = PDO::PARAM_STR;
} elseif (!empty($min_price)) {
    $where_conditions[] = "o.price >= :min_price";
    $params[':min_price'] = $min_price;
    $param_types[':min_price'] = PDO::PARAM_STR;
} elseif (!empty($max_price)) {
    $where_conditions[] = "o.price <= :max_price";
    $params[':max_price'] = $max_price;
    $param_types[':max_price'] = PDO::PARAM_STR;
}

if (!empty($fadan_id)) {
    if ($fadan_id == 'system') {
        $where_conditions[] = "o.fadan_id = 1";
    } else {
        $where_conditions[] = "o.fadan_id = :fadan_id";
        $params[':fadan_id'] = $fadan_id;
        $param_types[':fadan_id'] = PDO::PARAM_INT;
    }
}

$where_sql = empty($where_conditions) ? '' : ' WHERE ' . implode(' AND ', $where_conditions);
$count_sql = "SELECT COUNT(*) as total FROM orders o" . $where_sql;
$sql = $base_sql . $where_sql . " ORDER BY o.create_time DESC LIMIT :limit OFFSET :offset";

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
    
    $partners_sql = "SELECT id, username FROM partners ORDER BY username";
    $partners_result = $pdo->query($partners_sql);
    $partners = $partners_result->fetchAll(PDO::FETCH_ASSOC);
    
    // 计算待结算订单总金额
    $total_amount_sql = "SELECT SUM(price) as total_amount FROM orders o" . $where_sql;
    $total_amount_stmt = $pdo->prepare($total_amount_sql);
    foreach ($params as $key => $value) {
        if ($key != ':limit' && $key != ':offset') {
            $total_amount_stmt->bindValue($key, $value, $param_types[$key] ?? PDO::PARAM_STR);
        }
    }
    $total_amount_stmt->execute();
    $total_amount = $total_amount_stmt->fetchColumn() ?? 0;
    
} catch (PDOException $e) {
    die("数据库查询失败: " . $e->getMessage());
}

$pageTitle = '待结算订单';
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
    .card-header { font-size: 14px; font-weight: 600; color: #333; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    
    .stats-card { background: #f5f5f5; border-radius: 8px; padding: 16px; margin-bottom: 16px; border-left: 4px solid #1976d2; }
    .stats-card h3 { font-size: 14px; font-weight: 500; color: #666; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
    .stats-card .amount { font-size: 24px; font-weight: 700; color: #1976d2; }
    .stats-card .count { font-size: 12px; color: #999; margin-top: 4px; }
    
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
    .btn-success { background: #4caf50; color: #fff; border-color: #4caf50; }
    .btn-success:hover { background: #45a049; }
    .btn-sm { padding: 6px 12px; font-size: 12px; }
    .btn-group { display: flex; gap: 8px; align-items: flex-end; flex-wrap: wrap; }
    
    .table-wrapper { overflow-x: auto; margin-top: 16px; }
    table { width: 100%; border-collapse: collapse; font-size: 12px; }
    th { background: #f8f8f8; color: #666; font-weight: 500; padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; }
    td { padding: 10px 12px; border-bottom: 1px solid #f5f5f5; color: #333; }
    tr:hover td { background: #fafafa; }
    
    .user-info { display: flex; align-items: center; gap: 8px; }
    .user-avatar { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; background: #f5f5f5; }
    .price-value { color: #d32f2f; font-weight: 600; }
    
    .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 500; }
    .badge-success { background: #e8f5e9; color: #388e3c; }
    .badge-warning { background: #fff3e0; color: #f57c00; }
    .badge-secondary { background: #f5f5f5; color: #666; }
    .badge-info { background: #e3f2fd; color: #1976d2; }
    
    .empty-state { text-align: center; padding: 40px 20px; }
    .empty-icon { font-size: 32px; color: #ccc; margin-bottom: 8px; }
    .empty-message { color: #999; font-size: 14px; }
    
    .pagination { display: flex; justify-content: center; gap: 4px; margin-top: 20px; flex-wrap: wrap; }
    .pagination a { display: inline-flex; align-items: center; justify-content: center; min-width: 32px; height: 32px; padding: 0 8px; border: 1px solid #ddd; background: #fff; color: #333; font-size: 12px; text-decoration: none; border-radius: 4px; transition: all 0.2s; }
    .pagination a:hover { background: #f5f5f5; }
    .pagination a.active { background: #1976d2; color: #fff; border-color: #1976d2; }
    
    .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; }
    .modal-content { background: #fff; border-radius: 8px; padding: 20px; max-width: 400px; width: 90%; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    .modal-title { font-size: 16px; font-weight: 600; margin-bottom: 12px; color: #333; display: flex; align-items: center; justify-content: center; gap: 8px; }
    .modal-message { color: #666; margin-bottom: 16px; }
    .modal-buttons { display: flex; gap: 8px; justify-content: center; }
    
    @media (max-width: 768px) {
      .filter-form { grid-template-columns: 1fr; }
    }
    </style>
</head>
<body>
    <div class="container">
        <h1>💰 待结算订单</h1>
        <a href="orders.php" class="btn btn-secondary btn-sm" style="margin-bottom: 16px;">
            <i class="fa fa-arrow-left"></i> 返回订单列表
        </a>
        <!-- 统计卡片 -->
        <div class="stats-card">
            <h3><i class="fa fa-wallet"></i> 待结算总金额</h3>
            <div class="amount">¥<?php echo number_format($total_amount, 2); ?></div>
            <div class="count">共 <?php echo $total_orders; ?> 个待结算订单</div>
        </div>

        <div class="card">
            <div class="card-header">
                <span>🔍 筛选条件</span>
            </div>
            
            <form method="GET">
                <div class="filter-form">
                    <div class="form-group">
                        <label class="form-label">搜索订单</label>
                        <input type="text" name="search" placeholder="搜索订单ID、订单号、商品名称..." 
                               value="<?php echo htmlspecialchars($search); ?>" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">开始日期</label>
                        <input type="date" name="start_date" class="form-input" 
                               value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">结束日期</label>
                        <input type="date" name="end_date" class="form-input" 
                               value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">最低价</label>
                        <input type="number" name="min_price" placeholder="最低价" class="form-input" 
                               value="<?php echo htmlspecialchars($min_price); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">最高价</label>
                        <input type="number" name="max_price" placeholder="最高价" class="form-input" 
                               value="<?php echo htmlspecialchars($max_price); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">发单人</label>
                        <select name="fadan_id" class="form-select">
                            <option value="">全部（系统+商家）</option>
                            <option value="system" <?php echo $fadan_id == 'system' ? 'selected' : ''; ?>>系统发单</option>
                            <?php foreach ($partners as $partner): ?>
                                <option value="<?php echo $partner['id']; ?>" <?php echo $fadan_id == $partner['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($partner['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-search"></i> 搜索
                    </button>
                    <a href="orders-pending-settlement.php" class="btn btn-secondary">重置</a>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <span>📋 待结算订单列表</span>
            </div>
            
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>订单号</th>
                            <th>订单名称</th>
                            <th>价格</th>
                            <th>发单人</th>
                            <th>客户</th>
                            <th>打手</th>
                            <th>状态</th>
                            <th>支付状态</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="11">
                                    <div class="empty-state">
                                        <div class="empty-icon">📦</div>
                                        <div class="empty-message">暂无待结算订单</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): 
                                $issuer_avatar = !empty($order['issuer_avatar']) ? '../../assets/images/avatar/' . $order['issuer_avatar'] : 'https://sydj.bcorn.cn/assets/images/user.png';
                                $hunter_avatar = !empty($order['hunter_avatar']) ? '../../assets/images/avatar/' . $order['hunter_avatar'] : 'https://sydj.bcorn.cn/assets/images/user.png';
                            ?>
                            <tr>
                                <td><?php echo $order['id']; ?></td>
                                <td><?php echo $order['order_no']; ?></td>
                                <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                <td class="price-value">¥<?php echo number_format($order['price'], 2); ?></td>
                                <td>
                                    <?php if ($order['fadan_id'] == 1): ?>
                                        <span class="badge badge-secondary">系统发单</span>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($order['fadan_username'] ?? '未知商家'); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($order['user_id']): ?>
                                        <div class="user-info">
                                            <img src="<?php echo $issuer_avatar; ?>" class="user-avatar">
                                            <?php echo htmlspecialchars($order['issuer_username'] ?? '客户'); ?>
                                        </div>
                                    <?php else: ?>
                                        <span>系统订单</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($order['hunter_user_id']): ?>
                                        <div class="user-info">
                                            <img src="<?php echo $hunter_avatar; ?>" class="user-avatar">
                                            <?php echo htmlspecialchars($order['hunter_username']); ?>
                                        </div>
                                    <?php else: ?>
                                        <span>未接单</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $statusClass[$order['order_status']] ?? 'badge-secondary'; ?>">
                                        <?php echo $statusText[$order['order_status']]; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($order['payment_status'] == 'paid'): ?>
                                        <span style="color: #388e3c; font-weight: 500;">已支付</span>
                                    <?php elseif ($order['payment_status'] == 'pending'): ?>
                                        <span style="color: #666;">待支付</span>
                                    <?php else: ?>
                                        <span style="color: #666;"><?php echo $order['payment_status']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('Y-m-d H:i', strtotime($order['create_time'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="admin_order_view.php?id=<?php echo $order['id']; ?>" class="btn btn-secondary btn-sm" title="查看">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-success btn-sm" onclick="confirmSettle(<?php echo $order['id']; ?>)" title="结算">
                                            <i class="fa fa-check"></i> 结算
                                        </button>
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
                <a href="orders-pending-settlement.php?<?php 
                    echo http_build_query(array_merge(
                        ['page' => 1],
                        array_filter([
                            'search' => $search,
                            'start_date' => $start_date,
                            'end_date' => $end_date,
                            'min_price' => $min_price,
                            'max_price' => $max_price,
                            'fadan_id' => $fadan_id
                        ])
                    )); ?>" title="第一页">
                    <i class="fa fa-angle-double-left"></i>
                </a>
                
                <a href="orders-pending-settlement.php?<?php 
                    echo http_build_query(array_merge(
                        ['page' => max(1, $page - 1)],
                        array_filter([
                            'search' => $search,
                            'start_date' => $start_date,
                            'end_date' => $end_date,
                            'min_price' => $min_price,
                            'max_price' => $max_price,
                            'fadan_id' => $fadan_id
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
                    <a href="orders-pending-settlement.php?<?php 
                            echo http_build_query(array_merge(
                                ['page' => $i],
                                array_filter([
                                    'search' => $search,
                                    'start_date' => $start_date,
                                    'end_date' => $end_date,
                                    'min_price' => $min_price,
                                    'max_price' => $max_price,
                                    'fadan_id' => $fadan_id
                                ])
                            )); ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <a href="orders-pending-settlement.php?<?php 
                    echo http_build_query(array_merge(
                        ['page' => min($total_pages, $page + 1)],
                        array_filter([
                            'search' => $search,
                            'start_date' => $start_date,
                            'end_date' => $end_date,
                            'min_price' => $min_price,
                            'max_price' => $max_price,
                            'fadan_id' => $fadan_id
                        ])
                    )); ?>" title="下一页">
                    <i class="fa fa-angle-right"></i>
                </a>
                
                <a href="orders-pending-settlement.php?<?php 
                    echo http_build_query(array_merge(
                        ['page' => $total_pages],
                        array_filter([
                            'search' => $search,
                            'start_date' => $start_date,
                            'end_date' => $end_date,
                            'min_price' => $min_price,
                            'max_price' => $max_price,
                            'fadan_id' => $fadan_id
                        ])
                    )); ?>" title="最后一页">
                    <i class="fa fa-angle-double-right"></i>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 结算确认弹窗 -->
    <div class="modal" id="settleModal">
        <div class="modal-content">
            <div class="modal-title"><i class="fa fa-question-circle"></i> 确认结算</div>
            <div class="modal-message">确定要结算该订单吗？结算后订单状态将变为已完成。</div>
            <div class="modal-buttons">
                <button class="btn btn-secondary" onclick="closeModal()">取消</button>
                <button class="btn btn-success" id="confirmSettleBtn">确认结算</button>
            </div>
        </div>
    </div>

    <script>
    let currentOrderId = null;

    function confirmSettle(orderId) {
        currentOrderId = orderId;
        document.getElementById('settleModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('settleModal').style.display = 'none';
        currentOrderId = null;
    }

    document.getElementById('confirmSettleBtn').addEventListener('click', function() {
        if (!currentOrderId) return;

        const btn = this;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> 处理中...';
        btn.disabled = true;

        fetch('settle_order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'order_id=' + currentOrderId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('结算成功！');
                window.location.reload();
            } else {
                alert('结算失败：' + data.msg);
                btn.innerHTML = '确认结算';
                btn.disabled = false;
            }
        })
        .catch(error => {
            alert('网络错误，请稍后重试');
            btn.innerHTML = '确认结算';
            btn.disabled = false;
        })
        .finally(() => {
            closeModal();
        });
    });

    // 点击弹窗外部关闭
    document.getElementById('settleModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.card, .stats-card');
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