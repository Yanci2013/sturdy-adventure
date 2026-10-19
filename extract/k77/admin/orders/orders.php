<?php
ob_start();
require_once __DIR__.'/../../includes/common.php';
require_once __DIR__.'/../../includes/log_helper.php';
session_start();

$statusMap = [
    1 => 'pending',
    2 => 'processing',
    3 => 'processing',
    4 => 'completed',
    5 => 'cancelled',
    6 => 'processing',
    7 => 'cancelled'
];

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
    3 => 'badge-secondary',
    4 => 'badge-success',
    5 => 'badge-secondary',
    6 => 'badge-secondary',
    7 => 'badge-secondary'
];

$status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$payment_type = $_GET['payment_type'] ?? '';
$fadan_id = $_GET['fadan_id'] ?? '';
$hunter_status = $_GET['hunter_status'] ?? '';

$statuses = ['all', 'pending', 'processing', 'completed', 'cancelled'];
if (!in_array($status, $statuses)) {
    $status = 'all';
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// 检查orders表是否包含接单时间和结单时间字段
try {
    $check_stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'accept_time'");
    if ($check_stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN accept_time DATETIME DEFAULT NULL");
    }
    
    $check_stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'complete_time'");
    if ($check_stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN complete_time DATETIME DEFAULT NULL");
    }
} catch (PDOException $e) {
    error_log("修改表结构失败: " . $e->getMessage());
}

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

if ($status != 'all') {
    if ($status == 'pending') {
        $where_conditions[] = "o.order_status = 1";
    } elseif ($status == 'processing') {
        $where_conditions[] = "o.order_status IN (2,3,6)";
    } elseif ($status == 'completed') {
        $where_conditions[] = "o.order_status = 4";
    } elseif ($status == 'cancelled') {
        $where_conditions[] = "o.order_status IN (5,7)";
    }
}

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

if (!empty($payment_type)) {
    $where_conditions[] = "o.payment_type = :payment_type";
    $params[':payment_type'] = $payment_type;
    $param_types[':payment_type'] = PDO::PARAM_STR;
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

if (!empty($hunter_status)) {
    if ($hunter_status == 'assigned') {
        $where_conditions[] = "o.hunter_user_id IS NOT NULL";
    } elseif ($hunter_status == 'unassigned') {
        $where_conditions[] = "o.hunter_user_id IS NULL";
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
    
    // 获取打手列表用于转单
    $hunters_sql = "SELECT id, username FROM users WHERE user_role = 1 ORDER BY username";
    $hunters_result = $pdo->query($hunters_sql);
    $hunters = $hunters_result->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("数据库查询失败: " . $e->getMessage());
}

// 处理删除订单的请求
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['order_id'])) {
    $order_id = intval($_GET['order_id']);
    
    try {
        // 开始事务
        $pdo->beginTransaction();
        
        // 删除订单
        $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        
        // 提交事务
        $pdo->commit();
        
        // 重定向回订单列表
        header('Location: orders.php?status=' . urlencode($status));
        exit;
    } catch (PDOException $e) {
        // 回滚事务
        $pdo->rollBack();
        die("删除订单失败: " . $e->getMessage());
    }
}

// 处理退回大厅请求 - POST 处理
$success_message = $error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'back_to_hall' && isset($_POST['order_id'])) {
        $back_order_id = intval($_POST['order_id']);
        $back_notes = $_POST['notes'] ?? '';
        
        try {
            $stmt = $pdo->prepare("UPDATE orders SET 
                                  order_status = 1, 
                                  hunter_user_id = NULL, 
                                  accept_time = NULL
                                  WHERE id = ? AND order_status IN (2, 3, 6)");
            
            $stmt->execute([$back_order_id]);
            
            if ($stmt->rowCount() > 0) {
                $order_stmt = $pdo->prepare("SELECT order_no FROM orders WHERE id = ?");
                $order_stmt->execute([$back_order_id]);
                $order_info = $order_stmt->fetch();
                
                log_order_action(
                    $pdo,
                    $back_order_id,
                    $order_info['order_no'] ?? '',
                    'back_to_hall',
                    '管理员将订单退回抢单大厅',
                    null,
                    '系统管理员',
                    'admin',
                    null,
                    null,
                    $back_notes
                );
                
                $success_message = '订单已成功退回抢单大厅';
            } else {
                $error_message = '订单退回失败，请重试';
            }
        } catch (PDOException $e) {
            $error_message = '订单退回失败: ' . $e->getMessage();
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'transfer' && isset($_POST['order_id']) && isset($_POST['target_hunter_id'])) {
        $transfer_order_id = intval($_POST['order_id']);
        $target_hunter_id = intval($_POST['target_hunter_id']);
        $transfer_notes = $_POST['notes'] ?? '';
        
        try {
            $check_stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ? AND user_role = 1");
            $check_stmt->execute([$target_hunter_id]);
            $target_hunter = $check_stmt->fetch();
            
            if (!$target_hunter) {
                $error_message = '目标打手不存在';
            } else {
                $update_stmt = $pdo->prepare("UPDATE orders SET 
                                              hunter_user_id = ?, 
                                              accept_time = NOW()
                                              WHERE id = ? AND order_status IN (2, 3, 6)");
                
                $update_stmt->execute([$target_hunter_id, $transfer_order_id]);
                
                if ($update_stmt->rowCount() > 0) {
                    $order_stmt = $pdo->prepare("SELECT order_no FROM orders WHERE id = ?");
                    $order_stmt->execute([$transfer_order_id]);
                    $order_info = $order_stmt->fetch();
                    
                    log_order_action(
                        $pdo,
                        $transfer_order_id,
                        $order_info['order_no'] ?? '',
                        'transfer',
                        '管理员转单给其他打手',
                        null,
                        '系统管理员',
                        'admin',
                        $target_hunter_id,
                        $target_hunter['username'],
                        $transfer_notes
                    );
                    
                    $success_message = '订单已成功转递给 ' . htmlspecialchars($target_hunter['username']);
                } else {
                    $error_message = '订单转单失败，请重试';
                }
            }
        } catch (PDOException $e) {
            $error_message = '订单转单失败: ' . $e->getMessage();
        }
    }
    
    if (!empty($success_message) || !empty($error_message)) {
        header('Location: orders.php?status=' . urlencode($status));
        exit;
    }
}

$pageTitle = '订单管理';
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
    
    .quick-filters { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
    .quick-filter-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: #f5f5f5; color: #333; text-decoration: none; border-radius: 8px; font-size: 13px; transition: all 0.2s; border: 1px solid #eee; }
    .quick-filter-btn:hover { background: #e0e0e0; }
    .quick-filter-btn.active { background: #e3f2fd; color: #1976d2; border-color: #bbdefb; }
    
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
    
    .empty-state { text-align: center; padding: 40px 20px; }
    .empty-icon { font-size: 32px; color: #ccc; margin-bottom: 8px; }
    .empty-message { color: #999; font-size: 14px; }
    
    /* 订单操作下拉菜单样式 */
    .order-actions {
      position: relative;
      display: inline-block;
    }
    
    .action-dropdown {
      position: absolute;
      top: 0;
      right: 100%;
      min-width: 180px;
      background: #ffffff;
      border: 1px solid #ddd;
      border-radius: 4px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      z-index: 1000;
      margin-right: 4px;
      font-size: 11px;
      overflow: hidden;
    }
    
    .action-item {
      padding: 6px 10px;
      cursor: pointer;
      border-bottom: 1px solid #f5f5f5;
      transition: background-color 0.2s;
    }
    
    .action-item:hover {
      background-color: #f5f5f5;
    }
    
    .action-item:last-child {
      border-bottom: none;
    }
    
    .pagination { display: flex; justify-content: center; gap: 4px; margin-top: 20px; flex-wrap: wrap; }
    .pagination a { display: inline-flex; align-items: center; justify-content: center; min-width: 32px; height: 32px; padding: 0 8px; border: 1px solid #ddd; background: #fff; color: #333; font-size: 12px; text-decoration: none; border-radius: 4px; transition: all 0.2s; }
    .pagination a:hover { background: #f5f5f5; }
    .pagination a.active { background: #1976d2; color: #fff; border-color: #1976d2; }
    
    .export-section { margin-top: 16px; padding: 16px; background: #fafafa; border-radius: 8px; }
    .export-section h3 { font-size: 14px; font-weight: 600; margin-bottom: 12px; color: #333; }
    .export-options { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 16px; }
    
    @media (max-width: 768px) {
      .filter-form, .export-options { grid-template-columns: 1fr; }
      .quick-filters { justify-content: center; }
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>📋 订单管理</h1>

    <div class="card">
      <div class="card-header">
        <span>🔍 筛选条件</span>
        <div>
          <button class="btn btn-primary" id="exportBtn">
            <i class="fas fa-file-export"></i> 导出数据
          </button>
          <button class="btn btn-secondary" id="importBtn" style="margin-left: 8px;">
            <i class="fas fa-file-import"></i> 导入数据
          </button>
        </div>
      </div>
      
      <div class="quick-filters">
        <a href="orders.php?status=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
           class="quick-filter-btn <?php echo $status == 'all' ? 'active' : ''; ?>">
          <i class="fas fa-list"></i> 全部
        </a>
        <a href="orders.php?status=pending<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
           class="quick-filter-btn <?php echo $status == 'pending' ? 'active' : ''; ?>">
          <i class="fas fa-clock"></i> 待接单
        </a>
        <a href="orders.php?status=processing<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
           class="quick-filter-btn <?php echo $status == 'processing' ? 'active' : ''; ?>">
          <i class="fas fa-spinner"></i> 处理中
        </a>
        <a href="orders.php?status=completed<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
           class="quick-filter-btn <?php echo $status == 'completed' ? 'active' : ''; ?>">
          <i class="fas fa-check-circle"></i> 已完成
        </a>
        <a href="orders.php?status=cancelled<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
           class="quick-filter-btn <?php echo $status == 'cancelled' ? 'active' : ''; ?>">
          <i class="fas fa-times-circle"></i> 已取消
        </a>
      </div>
      
      <form method="GET" class="filter-form">
        <input type="hidden" name="status" value="<?php echo $status; ?>">
        
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
          <label class="form-label">支付方式</label>
          <select name="payment_type" class="form-select">
            <option value="">全部</option>
            <option value="alipay" <?php echo $payment_type == 'alipay' ? 'selected' : ''; ?>>支付宝</option>
            <option value="wxpay" <?php echo $payment_type == 'wxpay' ? 'selected' : ''; ?>>微信支付</option>
            <option value="qqpay" <?php echo $payment_type == 'qqpay' ? 'selected' : ''; ?>>QQ支付</option>
            <option value="bank" <?php echo $payment_type == 'bank' ? 'selected' : ''; ?>>银行卡</option>
          </select>
        </div>
        
        <div class="form-group">
          <label class="form-label">发单人</label>
          <select name="fadan_id" class="form-select">
            <option value="">全部</option>
            <option value="system" <?php echo $fadan_id == 'system' ? 'selected' : ''; ?>>系统发单</option>
            <?php foreach ($partners as $partner): ?>
              <option value="<?php echo $partner['id']; ?>" <?php echo $fadan_id == $partner['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($partner['username']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="form-group">
          <label class="form-label">打手状态</label>
          <select name="hunter_status" class="form-select">
            <option value="">全部</option>
            <option value="assigned" <?php echo $hunter_status == 'assigned' ? 'selected' : ''; ?>>已分配</option>
            <option value="unassigned" <?php echo $hunter_status == 'unassigned' ? 'selected' : ''; ?>>未分配</option>
          </select>
        </div>
        
        <div class="form-group" style="align-self: flex-end;">
          <div class="btn-group">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-search"></i> 搜索
            </button>
            <a href="orders.php?status=<?php echo $status; ?>" class="btn btn-secondary">重置</a>
          </div>
        </div>
      </form>
    </div>

    <div class="card">
      <div class="card-header">📋 订单列表</div>
      
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>订单号</th>
              <th>商品信息</th>
              <th>订单状态</th>
              <th>下单用户信息</th>
              <th>下单表单信息</th>
              <th>接单用户信息</th>
              <th>订单操作</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($orders)): ?>
              <tr>
                <td colspan="8">
                  <div class="empty-state">
                    <div class="empty-icon">📦</div>
                    <div class="empty-message">没有找到订单记录</div>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($orders as $order):
                $issuer_avatar = !empty($order['issuer_avatar']) ? '../../assets/images/avatar/' . $order['issuer_avatar'] : 'https://sydj.bcorn.cn/assets/images/user.png';
                $hunter_avatar = !empty($order['hunter_avatar']) ? '../../assets/images/avatar/' . $order['hunter_avatar'] : 'https://sydj.bcorn.cn/assets/images/user.png';

                // 查询订单共享信息
                $orderShareInfo = null;
                try {
                    $shareStmt = $pdo->prepare("
                        SELECT os.*, u.username as shared_username
                        FROM order_shares os
                        LEFT JOIN users u ON os.shared_user_id = u.id
                        WHERE os.order_id = ?
                    ");
                    $shareStmt->execute([$order['id']]);
                    $shareRecords = $shareStmt->fetchAll(PDO::FETCH_ASSOC);
                    if (!empty($shareRecords)) {
                        $sharedUsernames = array_column($shareRecords, 'shared_username');
                        $orderShareInfo = [
                            'count' => count($shareRecords),
                            'usernames' => implode(', ', $sharedUsernames)
                        ];
                    }
                } catch (PDOException $e) {
                    // 忽略错误
                }

                // 解析输入数据（兼容input_data和input_fields）
                $input_data = [];
                if (!empty($order['input_fields'])) {
                    $input_data = json_decode($order['input_fields'], true);
                } elseif (!empty($order['input_data'])) {
                    $input_data = json_decode($order['input_data'], true);
                }
                
                // 获取游戏区服
                $game_region = !empty($order['game_region']) ? $order['game_region'] : (!empty($input_data['game_region']) ? $input_data['game_region'] : '无');
                
                // 获取下单数量
                $order_quantity = !empty($input_data['quantity']) ? $input_data['quantity'] : 1;
                
                // 获取备注（与抢单大厅逻辑一致）
                $order_note = '';
                if (isset($input_data['备注']) && !empty($input_data['备注'])) {
                    $order_note = $input_data['备注'];
                } elseif (isset($input_data['note']) && !empty($input_data['note'])) {
                    $order_note = $input_data['note'];
                } elseif (isset($input_data['remarks']) && !empty($input_data['remarks'])) {
                    $order_note = $input_data['remarks'];
                }
              ?>
              <tr>
                <td><?php echo $order['id']; ?></td>
                <td>
                  <div>
                    <div><?php echo $order['order_no']; ?></div>
                    <div style="font-size: 11px; color: #666;">实付金额: ¥<?php echo number_format($order['price'], 2); ?></div>
                    <div style="font-size: 11px; color: #666;">发布方: <?php echo $order['fadan_id'] == 1 ? '系统发单' : (htmlspecialchars($order['fadan_username'] ?? '未知商家')); ?></div>
                  </div>
                </td>
                <td>
                  <div>
                    <div><?php echo htmlspecialchars($order['product_name']); ?></div>
                  </div>
                </td>
                <td>
                  <div>
                    <div style="margin-bottom: 4px;">创建时间: <?php echo date('Y-m-d H:i:s', strtotime($order['create_time'])); ?></div>
                    <div style="margin-bottom: 4px;">接单时间: <?php echo !empty($order['accept_time']) ? date('Y-m-d H:i:s', strtotime($order['accept_time'])) : '未接单'; ?></div>
                    <div style="margin-bottom: 4px;">结单时间: <?php echo !empty($order['complete_time']) ? date('Y-m-d H:i:s', strtotime($order['complete_time'])) : '未完成'; ?></div>
                    <div style="margin-bottom: 4px;">付款状态: <?php echo $order['payment_status'] == 'paid' ? '<span style="color: #388e3c; font-weight: 500;">已支付</span>' : ($order['payment_status'] == 'pending' ? '<span style="color: #f57c00;">待支付</span>' : '<span style="color: #999;">' . $order['payment_status'] . '</span>'); ?></div>
                    <div style="margin-bottom: 4px;">进度状态: <span class="badge <?php echo $statusClass[$order['order_status']] ?? 'badge-secondary'; ?>"><?php echo $statusText[$order['order_status']]; ?></span></div>
                  </div>
                </td>
                <td>
                  <div>
                    <div>用户ID: <?php echo $order['user_id'] ?? '无'; ?></div>
                  </div>
                </td>
                <td>
                  <div>
                    <div>中文ID: <?php echo htmlspecialchars($order['issuer_username'] ?? ''); ?></div>
                    <div>数字ID: <?php echo $order['user_id'] ?? ''; ?></div>
                    <div>游戏区服: <?php echo $game_region; ?></div>
                    <div>下单备注: <?php echo $order_note ? $order_note : '无'; ?></div>
                    <div>下单数量: <?php echo $order_quantity; ?></div>
                  </div>
                </td>
                <td>
                  <div>
                    <div>名称: <?php echo $order['hunter_user_id'] ? htmlspecialchars($order['hunter_username']) : '未接单'; ?></div>
                    <div>实际到手金额: ¥<?php echo $order['hunter_user_id'] ? number_format($order['price'] * 0.8, 2) : '0.00'; ?></div>
                    <?php if ($orderShareInfo): ?>
                    <div style="margin-top:5px;padding:5px 8px;background:#e8f5e9;border-radius:4px;font-size:11px;">
                        <div style="color:#2e7d32;font-weight:600;">已共享 (<?php echo $orderShareInfo['count']; ?>人)</div>
                        <div style="color:#666;margin-top:2px;"><?php echo htmlspecialchars($orderShareInfo['usernames']); ?></div>
                    </div>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="order-actions">
                    <button class="btn btn-secondary order-action-btn" data-order-id="<?php echo $order['id']; ?>">
                      订单操作
                    </button>
                    <div class="action-dropdown" id="actionDropdown<?php echo $order['id']; ?>" style="display: none;">
                      <?php if ($order['order_status'] == 2 || $order['order_status'] == 3 || $order['order_status'] == 6): ?>
                        <div class="action-item" onclick="backToHall(<?php echo $order['id']; ?>)">
                          退回大厅
                        </div>
                        <div class="action-item" onclick="transferOrder(<?php echo $order['id']; ?>)">
                          转单
                        </div>
                      <?php endif; ?>
                      <div class="action-item" onclick="issueTicket(<?php echo $order['id']; ?>)">
                        开罚单
                      </div>
                      <div class="action-item" onclick="viewHunterProof(<?php echo $order['id']; ?>)">
                        查看打手结单证明材料
                      </div>
                      <div class="action-item" onclick="editOrderNote(<?php echo $order['id']; ?>)">
                        修改订单备注
                      </div>
                      <div class="action-item" onclick="confirmDelete(<?php echo $order['id']; ?>)">
                        删除订单
                      </div>
                    </div>
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
        <a href="orders.php?<?php 
            echo http_build_query(array_merge(
                ['status' => $status, 'page' => 1],
                array_filter([
                    'search' => $search,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'min_price' => $min_price,
                    'max_price' => $max_price,
                    'payment_type' => $payment_type,
                    'fadan_id' => $fadan_id,
                    'hunter_status' => $hunter_status
                ])
            )); ?>", title="第一页">
          <i class="fas fa-angle-double-left"></i>
        </a>
        
        <a href="orders.php?<?php 
            echo http_build_query(array_merge(
                ['status' => $status, 'page' => max(1, $page - 1)],
                array_filter([
                    'search' => $search,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'min_price' => $min_price,
                    'max_price' => $max_price,
                    'payment_type' => $payment_type,
                    'fadan_id' => $fadan_id,
                    'hunter_status' => $hunter_status
                ])
            )); ?>", title="上一页">
          <i class="fas fa-angle-left"></i>
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
          <a href="orders.php?<?php 
                  echo http_build_query(array_merge(
                      ['status' => $status, 'page' => $i],
                      array_filter([
                          'search' => $search,
                          'start_date' => $start_date,
                          'end_date' => $end_date,
                          'min_price' => $min_price,
                          'max_price' => $max_price,
                          'payment_type' => $payment_type,
                          'fadan_id' => $fadan_id,
                          'hunter_status' => $hunter_status
                      ])
                  )); ?>", class="<?php echo $i == $page ? 'active' : ''; ?>">
            <?php echo $i; ?>
          </a>
        <?php endfor; ?>
        
        <a href="orders.php?<?php 
            echo http_build_query(array_merge(
                ['status' => $status, 'page' => min($total_pages, $page + 1)],
                array_filter([
                    'search' => $search,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'min_price' => $min_price,
                    'max_price' => $max_price,
                    'payment_type' => $payment_type,
                    'fadan_id' => $fadan_id,
                    'hunter_status' => $hunter_status
                ])
            )); ?>", title="下一页">
          <i class="fas fa-angle-right"></i>
        </a>
        
        <a href="orders.php?<?php 
            echo http_build_query(array_merge(
                ['status' => $status, 'page' => $total_pages],
                array_filter([
                    'search' => $search,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'min_price' => $min_price,
                    'max_price' => $max_price,
                    'payment_type' => $payment_type,
                    'fadan_id' => $fadan_id,
                    'hunter_status' => $hunter_status
                ])
            )); ?>", title="最后一页">
          <i class="fas fa-angle-double-right"></i>
        </a>
      </div>
      <?php endif; ?>
    </div>

    <!-- 导出选项弹窗 -->
    <div id="exportModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
      <div style="background: #fff; border-radius: 8px; padding: 20px; width: 90%; max-width: 500px;">
        <h3 style="margin-bottom: 16px; font-size: 16px; font-weight: 600;">导出订单数据</h3>
        <form id="exportForm" method="GET" action="export_orders.php">
          <input type="hidden" name="status" value="<?php echo $status; ?>">
          <input type="hidden" name="search" value="<?php echo $search; ?>">
          <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
          <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
          <input type="hidden" name="min_price" value="<?php echo $min_price; ?>">
          <input type="hidden" name="max_price" value="<?php echo $max_price; ?>">
          <input type="hidden" name="payment_type" value="<?php echo $payment_type; ?>">
          <input type="hidden" name="fadan_id" value="<?php echo $fadan_id; ?>">
          <input type="hidden" name="hunter_status" value="<?php echo $hunter_status; ?>">
          
          <div class="export-options">
            <div class="form-group">
              <label class="form-label">导出格式</label>
              <select name="format" class="form-select">
                <option value="csv">CSV</option>
                <option value="excel">Excel</option>
              </select>
            </div>
            
            <div class="form-group">
              <label class="form-label">导出字段</label>
              <select name="fields" class="form-select" multiple style="height: 100px;">
                <option value="id" selected>订单ID</option>
                <option value="order_no" selected>订单号</option>
                <option value="product_name" selected>商品名称</option>
                <option value="price" selected>价格</option>
                <option value="issuer_username" selected>客户</option>
                <option value="hunter_username" selected>打手</option>
                <option value="order_status" selected>订单状态</option>
                <option value="payment_status" selected>支付状态</option>
                <option value="create_time" selected>创建时间</option>
              </select>
            </div>
          </div>
          
          <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px;">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('exportModal').style.display = 'none'">取消</button>
            <button type="submit" class="btn btn-primary">导出</button>
          </div>
        </form>
      </div>
    </div>

    <!-- 导入选项弹窗 -->
    <div id="importModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
      <div style="background: #fff; border-radius: 8px; padding: 20px; width: 90%; max-width: 500px;">
        <h3 style="margin-bottom: 16px; font-size: 16px; font-weight: 600;">导入订单数据</h3>
        <form id="importForm" method="POST" action="import_orders.php" enctype="multipart/form-data">
          <div class="form-group">
            <label class="form-label">选择文件</label>
            <input type="file" name="file" class="form-input" accept=".csv,.xlsx,.xls" required>
          </div>
          
          <div class="form-group">
            <label class="form-label">导入模式</label>
            <select name="mode" class="form-select">
              <option value="insert">新增</option>
              <option value="update">更新</option>
              <option value="replace">替换</option>
            </select>
          </div>
          
          <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px;">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('importModal').style.display = 'none'">取消</button>
            <button type="submit" class="btn btn-primary">导入</button>
          </div>
        </form>
      </div>
    </div>
    
    <!-- 删除确认弹窗 -->
    <div id="deleteModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
      <div style="background: #fff; border-radius: 8px; padding: 20px; width: 90%; max-width: 400px;">
        <h3 style="margin-bottom: 16px; font-size: 16px; font-weight: 600; color: #d32f2f;">确认删除</h3>
        <p style="margin-bottom: 20px; color: #666;">确定要删除这个订单吗？此操作不可恢复。</p>
        <input type="hidden" id="orderId" value="">
        <div style="display: flex; justify-content: flex-end; gap: 8px;">
          <button type="button" class="btn btn-secondary" onclick="cancelDelete()">取消</button>
          <button type="button" class="btn btn-primary" style="background: #d32f2f; border-color: #d32f2f;" onclick="deleteOrder()">删除</button>
        </div>
      </div>
    </div>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    // 导出按钮点击事件
    document.getElementById('exportBtn').addEventListener('click', function() {
      document.getElementById('exportModal').style.display = 'flex';
    });
    
    // 导入按钮点击事件
    document.getElementById('importBtn').addEventListener('click', function() {
      document.getElementById('importModal').style.display = 'flex';
    });
    
    // 点击模态框外部关闭
    window.addEventListener('click', function(event) {
      const exportModal = document.getElementById('exportModal');
      const importModal = document.getElementById('importModal');
      const deleteModal = document.getElementById('deleteModal');
      
      if (event.target === exportModal) {
        exportModal.style.display = 'none';
      }
      
      if (event.target === importModal) {
        importModal.style.display = 'none';
      }
      
      if (event.target === deleteModal) {
        deleteModal.style.display = 'none';
      }
    });
  });
  
  // 确认删除函数
  function confirmDelete(orderId) {
    const deleteModal = document.getElementById('deleteModal');
    const orderIdInput = document.getElementById('orderId');
    orderIdInput.value = orderId;
    deleteModal.style.display = 'flex';
  }
  
  // 取消删除
  function cancelDelete() {
    document.getElementById('deleteModal').style.display = 'none';
  }
  
  // 执行删除
  function deleteOrder() {
    const orderId = document.getElementById('orderId').value;
    if (orderId) {
      window.location.href = 'orders.php?action=delete&order_id=' + orderId;
    }
  }
  
  // 订单操作下拉菜单
  document.addEventListener('DOMContentLoaded', function() {
    const actionBtns = document.querySelectorAll('.order-action-btn');
    
    actionBtns.forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const orderId = this.getAttribute('data-order-id');
        const dropdown = document.getElementById('actionDropdown' + orderId);
        
        // 关闭其他所有下拉菜单
        document.querySelectorAll('.action-dropdown').forEach(drop => {
          if (drop.id !== 'actionDropdown' + orderId) {
            drop.style.display = 'none';
          }
        });
        
        // 切换当前下拉菜单
        dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
      });
    });
    
    // 点击其他地方关闭下拉菜单
    document.addEventListener('click', function(e) {
      if (!e.target.closest('.order-actions')) {
        document.querySelectorAll('.action-dropdown').forEach(drop => {
          drop.style.display = 'none';
        });
      }
    });
  });
  
  // 订单操作函数

  
  function rejectHunterComplete(orderId) {
    if (confirm('确定要驳回打手结单吗？')) {
      // 这里添加驳回打手结单逻辑
      alert('驳回打手结单功能开发中');
    }
  }
  
  function issueTicket(orderId) {
    const amount = prompt('请输入罚单金额：');
    if (amount !== null && !isNaN(amount) && parseFloat(amount) > 0) {
      var xhr = new XMLHttpRequest();
      xhr.open('POST', 'issue_ticket.php', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
          var response = JSON.parse(xhr.responseText);
          if (response.success) {
            alert(response.message);
            location.reload();
          } else {
            alert('操作失败：' + response.message);
          }
        }
      };
      xhr.send('order_id=' + orderId + '&amount=' + encodeURIComponent(amount));
    } else if (amount !== null) {
      alert('请输入有效的金额');
    }
  }
  

  
  function viewHunterProof(orderId) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'get_order_proof.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
      if (xhr.readyState === 4 && xhr.status === 200) {
        try {
          var response = JSON.parse(xhr.responseText);
          if (response.success) {
            var data = response.data;
            var images = data.images || [];
            var note = data.note || '';
            
            // 构建弹窗内容
            var modalContent = '<div style="max-width: 800px; max-height: 90vh; overflow-y: auto; padding: 20px; background: #ffffff; border-radius: 8px;">';
            modalContent += '<h3 style="margin-bottom: 20px; font-size: 16px; font-weight: 600;">打手结单证明材料</h3>';
            
            // 显示结单备注
            if (note) {
              modalContent += '<div style="margin-bottom: 20px;">';
              modalContent += '<h4 style="margin-bottom: 8px; font-size: 14px; font-weight: 600;">结单备注</h4>';
              modalContent += '<div style="padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 13px;">' + note + '</div>';
              modalContent += '</div>';
            }
            
            // 显示结单图片
            if (images.length > 0) {
              modalContent += '<div style="margin-bottom: 20px;">';
              modalContent += '<h4 style="margin-bottom: 8px; font-size: 14px; font-weight: 600;">结单图片</h4>';
              modalContent += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">';
              images.forEach(function(image) {
                modalContent += '<div style="border: 1px solid #dee2e6; border-radius: 4px; overflow: hidden;">';
                modalContent += '<img src="' + image + '" style="width: 100%; height: 150px; object-fit: cover; cursor: pointer;" onclick="window.open(this.src, \'_blank\')">';
                modalContent += '</div>';
              });
              modalContent += '</div>';
              modalContent += '</div>';
            } else {
              modalContent += '<div style="margin-bottom: 20px; padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 13px; text-align: center;">';
              modalContent += '暂无结单图片';
              modalContent += '</div>';
            }
            
            modalContent += '<div style="text-align: right; margin-top: 20px;">';
            modalContent += '<button class="btn btn-primary" onclick="document.getElementById(\'proofModal\').style.display = \'none\'">关闭</button>';
            modalContent += '</div>';
            modalContent += '</div>';
            
            // 创建弹窗
            var modal = document.createElement('div');
            modal.id = 'proofModal';
            modal.style.cssText = 'display: flex; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;';
            modal.innerHTML = modalContent;
            document.body.appendChild(modal);
            
            // 点击模态框外部关闭
            modal.onclick = function(event) {
              if (event.target === modal) {
                modal.style.display = 'none';
                setTimeout(function() {
                  modal.remove();
                }, 300);
              }
            };
          } else {
            alert('操作失败：' + response.message);
          }
        } catch (e) {
          alert('解析响应失败：' + e.message);
        }
      } else if (xhr.readyState === 4) {
        alert('请求失败，状态码：' + xhr.status);
      }
    };
    xhr.onerror = function() {
      alert('网络错误，请检查网络连接');
    };
    xhr.send('order_id=' + orderId);
  }
  
  function editOrderNote(orderId) {
    const newNote = prompt('请输入新的订单备注：');
    if (newNote !== null) {
      var xhr = new XMLHttpRequest();
      xhr.open('POST', 'edit_order_note.php', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
          var response = JSON.parse(xhr.responseText);
          if (response.success) {
            alert(response.message);
            location.reload();
          } else {
            alert('操作失败：' + response.message);
          }
        }
      };
      xhr.send('order_id=' + orderId + '&note=' + encodeURIComponent(newNote));
    }
  }
  
  // 打手列表数据
  var hunters = <?php echo json_encode($hunters); ?>;
  
  function backToHall(orderId) {
    if (confirm('确定要将此订单退回抢单大厅吗？')) {
      var notes = prompt('请输入退回原因（可选）：');
      if (notes !== null) {
        // 创建表单
        var form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        var actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'back_to_hall';
        form.appendChild(actionInput);
        
        var orderIdInput = document.createElement('input');
        orderIdInput.type = 'hidden';
        orderIdInput.name = 'order_id';
        orderIdInput.value = orderId;
        form.appendChild(orderIdInput);
        
        var notesInput = document.createElement('input');
        notesInput.type = 'hidden';
        notesInput.name = 'notes';
        notesInput.value = notes || '';
        form.appendChild(notesInput);
        
        document.body.appendChild(form);
        form.submit();
      }
    }
  }
  
  function transferOrder(orderId) {
    // 构建转单弹窗内容
    var modalContent = '<div style="width: 400px; padding: 20px; background: #ffffff; border-radius: 8px;">';
    modalContent += '<h3 style="margin-bottom: 20px; font-size: 16px; font-weight: 600;">订单转单</h3>';
    modalContent += '<div style="margin-bottom: 15px;">';
    modalContent += '<label style="display: block; margin-bottom: 8px; font-size: 13px;">选择打手：</label>';
    modalContent += '<select id="targetHunterSelect" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">';
    modalContent += '<option value="">请选择打手</option>';
    hunters.forEach(function(hunter) {
      modalContent += '<option value="' + hunter.id + '">' + hunter.username + '</option>';
    });
    modalContent += '</select>';
    modalContent += '</div>';
    modalContent += '<div style="margin-bottom: 20px;">';
    modalContent += '<label style="display: block; margin-bottom: 8px; font-size: 13px;">转单备注（可选）：</label>';
    modalContent += '<textarea id="transferNotes" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; min-height: 80px;" placeholder="请输入转单备注..."></textarea>';
    modalContent += '</div>';
    modalContent += '<div style="display: flex; gap: 10px; justify-content: flex-end;">';
    modalContent += '<button class="btn btn-secondary" id="cancelTransfer">取消</button>';
    modalContent += '<button class="btn btn-primary" id="confirmTransfer">确认转单</button>';
    modalContent += '</div>';
    modalContent += '</div>';
    
    // 创建弹窗
    var modal = document.createElement('div');
    modal.id = 'transferModal';
    modal.style.cssText = 'display: flex; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;';
    modal.innerHTML = modalContent;
    document.body.appendChild(modal);
    
    // 绑定事件
    document.getElementById('cancelTransfer').onclick = function() {
      modal.style.display = 'none';
      setTimeout(function() { modal.remove(); }, 300);
    };
    
    document.getElementById('confirmTransfer').onclick = function() {
      var targetHunterId = document.getElementById('targetHunterSelect').value;
      var transferNotes = document.getElementById('transferNotes').value;
      
      if (!targetHunterId) {
        alert('请选择打手');
        return;
      }
      
      // 创建表单提交
      var form = document.createElement('form');
      form.method = 'POST';
      form.style.display = 'none';
      
      var actionInput = document.createElement('input');
      actionInput.type = 'hidden';
      actionInput.name = 'action';
      actionInput.value = 'transfer';
      form.appendChild(actionInput);
      
      var orderIdInput = document.createElement('input');
      orderIdInput.type = 'hidden';
      orderIdInput.name = 'order_id';
      orderIdInput.value = orderId;
      form.appendChild(orderIdInput);
      
      var targetHunterIdInput = document.createElement('input');
      targetHunterIdInput.type = 'hidden';
      targetHunterIdInput.name = 'target_hunter_id';
      targetHunterIdInput.value = targetHunterId;
      form.appendChild(targetHunterIdInput);
      
      var notesInput = document.createElement('input');
      notesInput.type = 'hidden';
      notesInput.name = 'notes';
      notesInput.value = transferNotes || '';
      form.appendChild(notesInput);
      
      document.body.appendChild(form);
      form.submit();
    };
    
    // 点击模态框外部关闭
    modal.onclick = function(event) {
      if (event.target === modal) {
        modal.style.display = 'none';
        setTimeout(function() { modal.remove(); }, 300);
      }
    };
  }
</script>
</body>
</html>