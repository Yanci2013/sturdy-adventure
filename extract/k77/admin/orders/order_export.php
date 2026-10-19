<?php
include_once __DIR__ . '/../../includes/common.php';

// 检查是否提交了导出请求
if (isset($_POST['export'])) {
    // 获取筛选参数
    $status = $_POST['status'] ?? 'all';
    $search = $_POST['search'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $min_price = $_POST['min_price'] ?? '';
    $max_price = $_POST['max_price'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';
    $publisher_id = $_POST['publisher_id'] ?? '';
    $hunter_status = $_POST['hunter_status'] ?? '';
    $format = $_POST['format'] ?? 'csv';

    // 构建SQL查询
    $base_sql = "SELECT * FROM game_orders WHERE 1=1";

    $where_conditions = [];
    $params = [];
    $param_types = [];

    // 状态筛选
    if ($status != 'all') {
        if ($status == 'pending_payment') {
            $where_conditions[] = "order_status = 0";
        } elseif ($status == 'pending_delivery') {
            $where_conditions[] = "order_status = 1";
        } elseif ($status == 'pending_confirm') {
            $where_conditions[] = "order_status = 2";
        } elseif ($status == 'completed') {
            $where_conditions[] = "order_status = 3";
        } elseif ($status == 'cancelled') {
            $where_conditions[] = "order_status = 4";
        } elseif ($status == 'refunded') {
            $where_conditions[] = "order_status = 5";
        }
    }

    // 搜索条件
    if (!empty($search)) {
        $where_conditions[] = "(order_id LIKE :search OR order_no LIKE :search2 OR item_name LIKE :search3 OR user_nickname LIKE :search4)";
        $params[':search'] = "%$search%";
        $params[':search2'] = "%$search%";
        $params[':search3'] = "%$search%";
        $params[':search4'] = "%$search%";
        $param_types[':search'] = PDO::PARAM_STR;
        $param_types[':search2'] = PDO::PARAM_STR;
        $param_types[':search3'] = PDO::PARAM_STR;
        $param_types[':search4'] = PDO::PARAM_STR;
    }

    // 日期范围
    if (!empty($start_date) && !empty($end_date)) {
        $where_conditions[] = "created_at BETWEEN :start_date AND :end_date";
        $params[':start_date'] = $start_date . ' 00:00:00';
        $params[':end_date'] = $end_date . ' 23:59:59';
        $param_types[':start_date'] = PDO::PARAM_STR;
        $param_types[':end_date'] = PDO::PARAM_STR;
    } elseif (!empty($start_date)) {
        $where_conditions[] = "created_at >= :start_date";
        $params[':start_date'] = $start_date . ' 00:00:00';
        $param_types[':start_date'] = PDO::PARAM_STR;
    } elseif (!empty($end_date)) {
        $where_conditions[] = "created_at <= :end_date";
        $params[':end_date'] = $end_date . ' 23:59:59';
        $param_types[':end_date'] = PDO::PARAM_STR;
    }

    // 价格范围
    if (!empty($min_price) && !empty($max_price)) {
        $where_conditions[] = "final_price BETWEEN :min_price AND :max_price";
        $params[':min_price'] = $min_price;
        $params[':max_price'] = $max_price;
        $param_types[':min_price'] = PDO::PARAM_STR;
        $param_types[':max_price'] = PDO::PARAM_STR;
    } elseif (!empty($min_price)) {
        $where_conditions[] = "final_price >= :min_price";
        $params[':min_price'] = $min_price;
        $param_types[':min_price'] = PDO::PARAM_STR;
    } elseif (!empty($max_price)) {
        $where_conditions[] = "final_price <= :max_price";
        $params[':max_price'] = $max_price;
        $param_types[':max_price'] = PDO::PARAM_STR;
    }

    // 支付方式
    if (!empty($payment_method)) {
        $where_conditions[] = "payment_method = :payment_method";
        $params[':payment_method'] = $payment_method;
        $param_types[':payment_method'] = PDO::PARAM_STR;
    }

    // 发布者
    if (!empty($publisher_id)) {
        $where_conditions[] = "publisher_id = :publisher_id";
        $params[':publisher_id'] = $publisher_id;
        $param_types[':publisher_id'] = PDO::PARAM_INT;
    }

    // 打手状态
    if (!empty($hunter_status)) {
        if ($hunter_status == 'assigned') {
            $where_conditions[] = "hunter_id IS NOT NULL";
        } elseif ($hunter_status == 'unassigned') {
            $where_conditions[] = "hunter_id IS NULL";
        }
    }

    // 构建完整SQL
    if (!empty($where_conditions)) {
        $where_sql = " AND " . implode(" AND ", $where_conditions);
        $base_sql .= $where_sql;
    }

    $base_sql .= " ORDER BY created_at DESC";

    try {
        // 执行查询
        $stmt = $pdo->prepare($base_sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, $param_types[$key] ?? PDO::PARAM_STR);
        }
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 准备CSV文件
        $filename = 'game_orders_export_' . date('YmdHis') . '.' . $format;
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        // 打开输出流
        $output = fopen('php://output', 'w');
        // 添加BOM头，解决中文乱码
        fwrite($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // 写入表头
        fputcsv($output, [
            '订单ID', '订单编号', '下单时间', '订单状态', '商品名称',
            '金额', '支付状态', '支付方式', '用户昵称', '发布者', '打手'
        ]);

        // 状态文本映射
        $statusText = [
            0 => '待支付', 1 => '待发货', 2 => '待确认',
            3 => '已完成', 4 => '已取消', 5 => '已退款'
        ];

        // 支付方式文本映射
        $paymentMethodText = [
            'wxpay' => '微信支付',
            'alipay' => '支付宝',
            'qqpay' => 'QQ支付',
            'bank' => '银行卡'
        ];

        // 写入数据
        foreach ($orders as $order) {
            fputcsv($output, [
                $order['order_id'],
                $order['order_no'],
                $order['created_at'],
                $statusText[$order['order_status']] ?? '未知',
                $order['item_name'],
                $order['final_price'],
                $order['payment_status'] == 'paid' ? '已支付' : '未支付',
                $paymentMethodText[$order['payment_method']] ?? $order['payment_method'] ?? '未支付',
                $order['user_nickname'],
                $order['publisher_id'] ?? '',
                $order['hunter_id'] ?? '未分配'
            ]);
        }
        fclose($output);
        exit;
    } catch (PDOException $e) {
        $error = "导出失败: " . $e->getMessage();
    }
}

$pageTitle = '导出订单数据';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <title><?php echo $pageTitle; ?> - 后台管理系统</title>
  <meta name="renderer" content="webkit">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <link href="../res/layui/css/layui.css" rel="stylesheet">
  <link href="../res/adminui/dist/css/admin.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #ffffff; color: #333; line-height: 1.5; padding: 20px; font-size: 13px; }
    .container { max-width: 1200px; margin: 0 auto; }
    h1 { font-size: 18px; font-weight: 600; margin-bottom: 16px; color: #000; }
    h2 { font-size: 14px; font-weight: 600; margin: 20px 0 12px 0; color: #333; }
    
    .card { background: #fafafa; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
    .card-header { font-size: 14px; font-weight: 600; color: #333; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    
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
    .btn-group { display: flex; gap: 8px; align-items: flex-end; }
    
    .export-options { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 16px; }
    
    .error-message { background: #ffebee; color: #c62828; padding: 12px; border-radius: 4px; margin-bottom: 16px; border-left: 4px solid #c62828; }
    
    @media (max-width: 768px) {
      .filter-form, .export-options { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>📤 导出订单数据</h1>

    <?php if (isset($error)): ?>
      <div class="error-message">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
      </div>
    <?php endif; ?>

    <form method="POST">
      <div class="card">
        <div class="card-header">
          <span>🔍 筛选条件</span>
          <a href="orders.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> 返回订单列表
          </a>
        </div>
        
        <div class="filter-form">
          <div class="form-group">
            <label class="form-label">订单状态</label>
            <select name="status" class="form-select">
              <option value="all">全部</option>
              <option value="pending_payment">待支付</option>
              <option value="pending_delivery">待发货</option>
              <option value="pending_confirm">待确认</option>
              <option value="completed">已完成</option>
              <option value="cancelled">已取消</option>
              <option value="refunded">已退款</option>
            </select>
          </div>
          
          <div class="form-group">
            <label class="form-label">搜索订单</label>
            <input type="text" name="search" placeholder="搜索订单ID、订单号、商品名称、用户昵称..." class="form-input">
          </div>
          
          <div class="form-group">
            <label class="form-label">开始日期</label>
            <input type="date" name="start_date" class="form-input">
          </div>
          
          <div class="form-group">
            <label class="form-label">结束日期</label>
            <input type="date" name="end_date" class="form-input">
          </div>
          
          <div class="form-group">
            <label class="form-label">最低价</label>
            <input type="number" name="min_price" placeholder="最低价" class="form-input">
          </div>
          
          <div class="form-group">
            <label class="form-label">最高价</label>
            <input type="number" name="max_price" placeholder="最高价" class="form-input">
          </div>
          
          <div class="form-group">
            <label class="form-label">支付方式</label>
            <select name="payment_method" class="form-select">
              <option value="">全部</option>
              <option value="wxpay">微信支付</option>
              <option value="alipay">支付宝</option>
              <option value="qqpay">QQ支付</option>
              <option value="bank">银行卡</option>
            </select>
          </div>
          
          <div class="form-group">
            <label class="form-label">打手状态</label>
            <select name="hunter_status" class="form-select">
              <option value="">全部</option>
              <option value="assigned">已分配</option>
              <option value="unassigned">未分配</option>
            </select>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">📤 导出选项</div>
        
        <div class="export-options">
          <div class="form-group">
            <label class="form-label">导出格式</label>
            <select name="format" class="form-select">
              <option value="csv">CSV</option>
              <option value="xls">Excel</option>
            </select>
          </div>
        </div>
        
        <div class="btn-group" style="justify-content: center; margin-top: 20px;">
          <button type="submit" name="export" class="btn btn-primary">
            <i class="fas fa-file-export"></i> 导出数据
          </button>
          <a href="orders.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> 取消
          </a>
        </div>
      </div>
    </form>
  </div>
</body>
</html>