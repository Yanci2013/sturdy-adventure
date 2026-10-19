<?php
header('Content-Type: application/json');
require_once __DIR__.'/../../includes/common.php';

// 状态映射
$statusMap = [
    0 => 'pending_payment',
    1 => 'pending_delivery',
    2 => 'pending_confirm',
    3 => 'completed',
    4 => 'cancelled',
    5 => 'refunded'
];

$statusText = [
    0 => '待支付',
    1 => '待发货',
    2 => '待确认',
    3 => '已完成',
    4 => '已取消',
    5 => '已退款'
];

$statusClass = [
    0 => 'layui-bg-orange',
    1 => 'layui-bg-blue',
    2 => 'layui-bg-blue',
    3 => 'layui-bg-green',
    4 => 'layui-bg-gray',
    5 => 'layui-bg-black'
];

// 获取请求参数
$action = $_GET['action'] ?? 'list';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$payment_method = $_GET['payment_method'] ?? '';
$publisher_id = $_GET['publisher_id'] ?? '';
$hunter_status = $_GET['hunter_status'] ?? '';

// 分页参数
$offset = ($page - 1) * $limit;

try {
    // 构建基础SQL查询
    $base_sql = "SELECT o.* FROM game_orders o WHERE 1=1";
    $count_sql = "SELECT COUNT(*) FROM game_orders o WHERE 1=1";
    
    $where_conditions = [];
    $params = [];
    
    // 状态筛选
    if ($status != 'all') {
        if ($status == 'pending_payment') {
            $where_conditions[] = "o.order_status = 0";
        } elseif ($status == 'pending_delivery') {
            $where_conditions[] = "o.order_status = 1";
        } elseif ($status == 'pending_confirm') {
            $where_conditions[] = "o.order_status = 2";
        } elseif ($status == 'completed') {
            $where_conditions[] = "o.order_status = 3";
        } elseif ($status == 'cancelled') {
            $where_conditions[] = "o.order_status = 4";
        } elseif ($status == 'refunded') {
            $where_conditions[] = "o.order_status = 5";
        }
    }

    // 搜索条件
    if (!empty($search)) {
        $where_conditions[] = "(o.order_id LIKE :search OR o.order_no LIKE :search OR o.item_name LIKE :search OR o.user_nickname LIKE :search)";
        $params[':search'] = "%$search%";
    }

    // 日期范围
    if (!empty($start_date) && !empty($end_date)) {
        $where_conditions[] = "o.created_at BETWEEN :start_date AND :end_date";
        $params[':start_date'] = $start_date . ' 00:00:00';
        $params[':end_date'] = $end_date . ' 23:59:59';
    } elseif (!empty($start_date)) {
        $where_conditions[] = "o.created_at >= :start_date";
        $params[':start_date'] = $start_date . ' 00:00:00';
    } elseif (!empty($end_date)) {
        $where_conditions[] = "o.created_at <= :end_date";
        $params[':end_date'] = $end_date . ' 23:59:59';
    }

    // 价格范围
    if (!empty($min_price) && !empty($max_price)) {
        $where_conditions[] = "o.final_price BETWEEN :min_price AND :max_price";
        $params[':min_price'] = $min_price;
        $params[':max_price'] = $max_price;
    } elseif (!empty($min_price)) {
        $where_conditions[] = "o.final_price >= :min_price";
        $params[':min_price'] = $min_price;
    } elseif (!empty($max_price)) {
        $where_conditions[] = "o.final_price <= :max_price";
        $params[':max_price'] = $max_price;
    }

    // 支付方式
    if (!empty($payment_method)) {
        $where_conditions[] = "o.payment_method = :payment_method";
        $params[':payment_method'] = $payment_method;
    }

    // 发布者
    if (!empty($publisher_id)) {
        $where_conditions[] = "o.publisher_id = :publisher_id";
        $params[':publisher_id'] = $publisher_id;
    }

    // 打手状态
    if (!empty($hunter_status)) {
        if ($hunter_status == 'assigned') {
            $where_conditions[] = "o.hunter_id IS NOT NULL";
        } elseif ($hunter_status == 'unassigned') {
            $where_conditions[] = "o.hunter_id IS NULL";
        }
    }

    // 构建完整SQL
    if (!empty($where_conditions)) {
        $where_sql = " AND " . implode(" AND ", $where_conditions);
        $base_sql .= $where_sql;
        $count_sql .= $where_sql;
    }

    $base_sql .= " ORDER BY o.created_at DESC LIMIT :limit OFFSET :offset";
    
    // 获取总订单数
    $count_stmt = $pdo->prepare($count_sql);
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    $count_stmt->execute();
    $total_orders = $count_stmt->fetchColumn();
    
    // 准备和执行查询
    $stmt = $pdo->prepare($base_sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 处理状态显示
    foreach ($orders as &$order) {
        $order['status_text'] = $statusText[$order['order_status']] ?? '';
        $order['status_class'] = $statusClass[$order['order_status']] ?? '';
    }
    
    // 返回结果
    echo json_encode([
        'code' => 0,
        'msg' => 'success',
        'count' => $total_orders,
        'data' => $orders,
        'status_text' => $statusText,
        'status_class' => $statusClass
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'code' => 500, 
        'msg' => '数据库错误: ' . $e->getMessage(),
        'data' => []
    ]);
}
?>