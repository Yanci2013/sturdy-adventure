<?php
include_once __DIR__ . '/../../includes/common.php';

// 获取筛选参数
$status = $_GET['status'] ?? 'all';
$search_type = $_GET['search_type'] ?? 'order_no';
$search = $_GET['search'] ?? '';
$hunter_search = $_GET['hunter_search'] ?? '';
$merchant_search = $_GET['merchant_search'] ?? '';
$product_name = $_GET['product_name'] ?? '';
$completion_status = $_GET['completion_status'] ?? '';
$platform = $_GET['platform'] ?? 'all';
$payment_status = $_GET['payment_status'] ?? 'all';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// 构建SQL查询
$base_sql = "SELECT o.*, 
               u.username as issuer_username, u.phone as issuer_phone,
               h.username as h5_user_name, h.phone as h5_user_phone,
               hu.username as hunter_username, hu.phone as hunter_phone,
               p.username as fadan_username
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        LEFT JOIN h5user h ON o.user_id = h.id
        LEFT JOIN users hu ON o.hunter_user_id = hu.id 
        LEFT JOIN partners p ON o.fadan_id = p.id";

// 构建WHERE条件
$where_conditions = [];
$params = [];
$param_types = [];

// 状态筛选
$statusMap = array(
      1 => 'pending',         // 待接单
      2 => 'to_submit',       // 待提交
      3 => 'to_settle',       // 待结算
      4 => 'completed',       // 已完成
      5 => 'cancelling',      // 撤销中
      6 => 'platform_intervention',     // 平台介入
);

if ($status != 'all') {
    $statusMapReverse = array_flip($statusMap);
    if (isset($statusMapReverse[$status])) {
        $where_conditions[] = "o.order_status = :order_status";
        $params[':order_status'] = $statusMapReverse[$status];
        $param_types[':order_status'] = PDO::PARAM_INT;
    } elseif ($status == 'platform') {
        $where_conditions[] = "o.order_status = 6";
    }
}

// 搜索条件
if (!empty($search)) {
    if ($search_type == 'order_no') {
        $where_conditions[] = "o.order_no LIKE :search";
    } elseif ($search_type == 'hunter_id') {
        $where_conditions[] = "hu.id LIKE :search OR hu.username LIKE :search";
    } elseif ($search_type == 'merchant_id') {
        $where_conditions[] = "p.id LIKE :search OR p.username LIKE :search";
    }
    $params[':search'] = "%$search%";
    $param_types[':search'] = PDO::PARAM_STR;
}

// 打手搜索条件
if (!empty($hunter_search)) {
    $where_conditions[] = "hu.username LIKE :hunter_search OR hu.phone LIKE :hunter_search OR hu.id LIKE :hunter_search";
    $params[':hunter_search'] = "%$hunter_search%";
    $param_types[':hunter_search'] = PDO::PARAM_STR;
}

// 商家搜索条件
if (!empty($merchant_search)) {
    $where_conditions[] = "p.username LIKE :merchant_search OR p.id LIKE :merchant_search";
    $params[':merchant_search'] = "%$merchant_search%";
    $param_types[':merchant_search'] = PDO::PARAM_STR;
}

// 商品名称搜索
if (!empty($product_name)) {
    $where_conditions[] = "o.product_name LIKE :product_name";
    $params[':product_name'] = "%$product_name%";
    $param_types[':product_name'] = PDO::PARAM_STR;
}

// 完成情况筛选
if (!empty($completion_status)) {
    $where_conditions[] = "o.completion_status = :completion_status";
    $params[':completion_status'] = $completion_status;
    $param_types[':completion_status'] = PDO::PARAM_STR;
}

// 平台筛选
if ($platform != 'all') {
    $where_conditions[] = "o.platform = :platform";
    $params[':platform'] = $platform;
    $param_types[':platform'] = PDO::PARAM_STR;
}

// 支付状态筛选
if ($payment_status != 'all') {
    $where_conditions[] = "o.payment_status = :payment_status";
    $params[':payment_status'] = $payment_status;
    $param_types[':payment_status'] = PDO::PARAM_STR;
}

// 日期范围
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

// 构建完整SQL
$where_sql = empty($where_conditions) ? '' : ' WHERE ' . implode(' AND ', $where_conditions);
$sql = $base_sql . $where_sql . " ORDER BY o.create_time DESC";

try {
    // 执行查询
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, $param_types[$key] ?? PDO::PARAM_STR);
    }
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 准备CSV文件
    $filename = 'orders_export_' . date('YmdHis') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    // 打开输出流
    $output = fopen('php://output', 'w');
    // 添加BOM头，解决中文乱码
    fwrite($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // 写入表头
    fputcsv($output, [
        '订单ID', '订单编号', '下单时间', '订单状态', '商品名称',
        '金额', '支付状态', '支付金额', '打手', '商家', '创建人'
    ]);

    // 状态文本映射
    $statusText = array(
        1 => '待接单', 2 => '待提交', 3 => '待结算',
        4 => '已完成', 5 => '撤销中', 6 => '平台介入'
    );

    // 写入数据
    foreach ($orders as $order) {
        fputcsv($output, [
            $order['id'],
            $order['order_no'],
            $order['create_time'],
            $statusText[$order['order_status']] ?? '未知',
            $order['product_name'],
            $order['price'],
            $order['payment_status'] == 'paid' ? '已支付' : '未支付',
            $order['payment_amount'],
            $order['hunter_username'] ?? '',
            $order['fadan_username'] ?? '',
            $order['issuer_username'] ?? $order['h5_user_name'] ?? ''
        ]);
    }
    fclose($output);
    exit;
} catch (PDOException $e) {
    die("导出失败: " . $e->getMessage());
}
?>