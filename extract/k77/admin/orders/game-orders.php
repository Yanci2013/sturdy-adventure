<?php
// 开启输出缓冲
ob_start();

// 引入数据库连接文件
require_once __DIR__.'/../../includes/common.php';

// 开启会话
session_start();

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

// 获取筛选参数
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
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10; // 每页显示10个订单
$offset = ($page - 1) * $per_page;

// 构建基础SQL查询
$base_sql = "SELECT * FROM game_orders WHERE 1=1";
$count_sql = "SELECT COUNT(*) FROM game_orders WHERE 1=1";

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
    $count_sql .= $where_sql;
}

$base_sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

// 添加分页参数
$params[':limit'] = $per_page;
$params[':offset'] = $offset;
$param_types[':limit'] = PDO::PARAM_INT;
$param_types[':offset'] = PDO::PARAM_INT;

try {
    // 获取总订单数
    $count_stmt = $pdo->prepare($count_sql);
    
    // 绑定参数
    foreach ($params as $key => $value) {
        if ($key != ':limit' && $key != ':offset') {
            $count_stmt->bindValue($key, $value, $param_types[$key] ?? PDO::PARAM_STR);
        }
    }
    
    $count_stmt->execute();
    $total_orders = $count_stmt->fetchColumn();
    
    // 计算总页数
    $total_pages = ceil($total_orders / $per_page);
    
    // 准备和执行查询
    $stmt = $pdo->prepare($base_sql);
    
    // 绑定参数
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, $param_types[$key] ?? PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 获取所有发布者列表用于筛选
    $publishers_sql = "SELECT id, username FROM users WHERE id IN (SELECT DISTINCT publisher_id FROM game_orders WHERE publisher_id IS NOT NULL) ORDER BY username";
    $publishers_result = $pdo->query($publishers_sql);
    $publishers = $publishers_result->fetchAll(PDO::FETCH_ASSOC);
    
    // 处理状态显示
    foreach ($orders as &$order) {
        $order['status_text'] = $statusText[$order['order_status']] ?? '';
        $order['status_class'] = $statusClass[$order['order_status']] ?? '';
    }
    
} catch (PDOException $e) {
    // 处理数据库错误
    die("数据库查询失败: " . $e->getMessage());
}

// 页面标题
$pageTitle = '装备数据库订单管理';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title><?php echo $pageTitle; ?></title>
    <link href="../res/layui/css/layui.css" rel="stylesheet">
    <link href="../res/adminui/dist/css/admin.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 保留原有样式不变 */
        .order-card {
            margin: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        /* ...其他样式保持不变... */
    </style>
</head>
<body>
<div class="layui-fluid">
    <div class="layui-row layui-col-space15">
        <div class="layui-col-md12">
            <div class="layui-card order-card">
                <div class="order-card-header">
                    <h2 class="order-card-title">装备数据库订单管理</h2>
                    <div>
                        <button class="layui-btn layui-btn-normal" id="exportBtn">
                            <i class="layui-icon layui-icon-export"></i> 导出数据
                        </button>
                    </div>
                </div>
                
                <div class="layui-card-body">
                    <!-- 搜索框和快捷筛选部分 -->
                    <form class="layui-form" method="GET">
                        <div class="layui-row">
                            <!-- 搜索框部分 -->
                            <div class="layui-col-md8">
                                <div class="layui-input-group">
                                    <input type="text" name="search" class="layui-input" placeholder="搜索订单ID、订单号、商品名称..." value="<?php echo htmlspecialchars($search); ?>">
                                    <div class="layui-input-suffix">
                                        <button class="layui-btn layui-btn-primary" lay-submit>
                                            <i class="layui-icon layui-icon-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 高级筛选按钮 -->
                            <div class="layui-col-md4">
                                <button type="button" class="layui-btn layui-btn-primary" id="toggleFilters">
                                    <i class="layui-icon layui-icon-filter"></i> 高级筛选
                                </button>
                            </div>
                        </div>
                        
                        <!-- 快捷筛选 -->
                        <div class="quick-filter-container">
                            <a href="?" class="quick-filter-btn <?php echo $status == 'all' ? 'active' : ''; ?>" data-status="all">
                                <i class="layui-icon layui-icon-list"></i> 全部
                            </a>
                            <a href="?status=pending_payment" class="quick-filter-btn <?php echo $status == 'pending_payment' ? 'active' : ''; ?>" data-status="pending_payment">
                                <i class="layui-icon layui-icon-time"></i> 待支付
                            </a>
                            <a href="?status=pending_delivery" class="quick-filter-btn <?php echo $status == 'pending_delivery' ? 'active' : ''; ?>" data-status="pending_delivery">
                                <i class="layui-icon layui-icon-form"></i> 待发货
                            </a>
                            <a href="?status=pending_confirm" class="quick-filter-btn <?php echo $status == 'pending_confirm' ? 'active' : ''; ?>" data-status="pending_confirm">
                                <i class="layui-icon layui-icon-ok-circle"></i> 待确认
                            </a>
                            <a href="?status=completed" class="quick-filter-btn <?php echo $status == 'completed' ? 'active' : ''; ?>" data-status="completed">
                                <i class="layui-icon layui-icon-ok"></i> 已完成
                            </a>
                            <a href="?status=cancelled" class="quick-filter-btn <?php echo $status == 'cancelled' ? 'active' : ''; ?>" data-status="cancelled">
                                <i class="layui-icon layui-icon-close"></i> 已取消
                            </a>
                            <a href="?status=refunded" class="quick-filter-btn <?php echo $status == 'refunded' ? 'active' : ''; ?>" data-status="refunded">
                                <i class="layui-icon layui-icon-refresh"></i> 已退款
                            </a>
                        </div>
                        
                        <!-- 高级筛选 -->
                        <div class="filter-content" style="display: <?php echo isset($_GET['advanced']) ? 'block' : 'none'; ?>;">
                            <div class="layui-row layui-col-space10">
                                <div class="layui-col-md12">
                                    <div class="layui-form-item">
                                        <label class="layui-form-label">创建时间范围</label>
                                        <div class="layui-input-inline" style="width: 160px;">
                                            <input type="text" name="start_date" class="layui-input" id="start_date" placeholder="开始日期" value="<?php echo htmlspecialchars($start_date); ?>">
                                        </div>
                                        <div class="layui-form-mid">-</div>
                                        <div class="layui-input-inline" style="width: 160px;">
                                            <input type="text" name="end_date" class="layui-input" id="end_date" placeholder="结束日期" value="<?php echo htmlspecialchars($end_date); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="layui-col-md12">
                                    <div class="layui-form-item">
                                        <label class="layui-form-label">价格范围</label>
                                        <div class="layui-input-inline" style="width: 160px;">
                                            <input type="number" name="min_price" class="layui-input" placeholder="最低价" value="<?php echo htmlspecialchars($min_price); ?>">
                                        </div>
                                        <div class="layui-form-mid">-</div>
                                        <div class="layui-input-inline" style="width: 160px;">
                                            <input type="number" name="max_price" class="layui-input" placeholder="最高价" value="<?php echo htmlspecialchars($max_price); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="layui-col-md12">
                                    <div class="layui-form-item">
                                        <label class="layui-form-label">支付方式</label>
                                        <div class="layui-input-inline">
                                            <select name="payment_method">
                                                <option value="">全部</option>
                                                <option value="wxpay" <?php echo $payment_method == 'wxpay' ? 'selected' : ''; ?>>微信支付</option>
                                                <option value="alipay" <?php echo $payment_method == 'alipay' ? 'selected' : ''; ?>>支付宝</option>
                                                <option value="qqpay" <?php echo $payment_method == 'qqpay' ? 'selected' : ''; ?>>QQ支付</option>
                                                <option value="bank" <?php echo $payment_method == 'bank' ? 'selected' : ''; ?>>银行卡</option>
                                            </select>
                                        </div>
                                        
                                        <label class="layui-form-label" style="margin-left: 20px;">发布者</label>
                                        <div class="layui-input-inline">
                                            <select name="publisher_id">
                                                <option value="">全部</option>
                                                <?php foreach ($publishers as $publisher): ?>
                                                    <option value="<?php echo $publisher['id']; ?>" <?php echo $publisher_id == $publisher['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($publisher['username']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <label class="layui-form-label" style="margin-left: 20px;">打手</label>
                                        <div class="layui-input-inline">
                                            <select name="hunter_status">
                                                <option value="">全部</option>
                                                <option value="assigned" <?php echo $hunter_status == 'assigned' ? 'selected' : ''; ?>>已分配</option>
                                                <option value="unassigned" <?php echo $hunter_status == 'unassigned' ? 'selected' : ''; ?>>未分配</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="layui-col-md12">
                                    <div class="layui-form-item" style="text-align: right;">
                                        <input type="hidden" name="advanced" value="1">
                                        <button type="reset" class="layui-btn layui-btn-primary">重置</button>
                                        <button type="submit" class="layui-btn">应用筛选</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <!-- 表格视图 -->
                    <div class="layui-table-responsive">
                        <table class="layui-table">
                            <colgroup>
                                <col width="80">
                                <col width="180">
                                <col width="120">
                                <col width="150">
                                <col width="100">
                                <col width="100">
                                <col width="100">
                                <col width="180">
                                <col width="120">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>订单编号</th>
                                    <th>用户昵称</th>
                                    <th>商品名称</th>
                                    <th>金额</th>
                                    <th>状态</th>
                                    <th>支付方式</th>
                                    <th>创建时间</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($orders)): ?>
                                    <tr>
                                        <td colspan="9">
                                            <div class="empty-state">
                                                <div class="empty-icon">
                                                    <i class="layui-icon layui-icon-list"></i>
                                                </div>
                                                <div class="empty-title">没有找到订单记录</div>
                                                <div class="empty-text">请尝试选择其他筛选条件</div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><?php echo $order['order_id']; ?></td>
                                        <td><?php echo $order['order_no']; ?></td>
                                        <td><?php echo htmlspecialchars($order['user_nickname']); ?></td>
                                        <td><?php echo htmlspecialchars($order['item_name']); ?></td>
                                        <td class="price-value">¥<?php echo number_format($order['final_price'], 2); ?></td>
                                        <td>
                                            <span class="order-status-badge <?php echo $order['status_class']; ?>">
                                                <?php echo $order['status_text']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($order['payment_method'] == 'wxpay'): ?>
                                                <span class="layui-badge layui-bg-green">微信支付</span>
                                            <?php elseif ($order['payment_method'] == 'alipay'): ?>
                                                <span class="layui-badge layui-bg-blue">支付宝</span>
                                            <?php elseif ($order['payment_method'] == 'qqpay'): ?>
                                                <span class="layui-badge layui-bg-cyan">QQ支付</span>
                                            <?php elseif ($order['payment_method'] == 'bank'): ?>
                                                <span class="layui-badge layui-bg-orange">银行卡</span>
                                            <?php elseif ($order['payment_method']): ?>
                                                <span class="layui-badge"><?php echo $order['payment_method']; ?></span>
                                            <?php else: ?>
                                                <span class="layui-badge layui-bg-gray">未支付</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></td>
                                        <td>
                                            <div class="table-actions">
                                                <a href="game-order-edit.php?id=<?php echo $order['order_id']; ?>" class="layui-btn layui-btn-xs layui-btn-warm">
                                                    <i class="layui-icon layui-icon-edit"></i> 编辑
                                                </a>
                                                <a href="order_delete.php?id=<?php echo $order['order_id']; ?>" class="layui-btn layui-btn-xs layui-btn-danger" onclick="return confirm('确定要删除此订单吗？')">
                                                    <i class="layui-icon layui-icon-delete"></i> 删除
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- 分页导航 -->
                    <?php if ($total_pages > 1): ?>
                    <div class="layui-box layui-laypage">
                        <!-- 第一页按钮 -->
                        <a href="?<?php 
                            echo http_build_query(array_merge(
                                ['status' => $status, 'page' => 1],
                                array_filter([
                                    'search' => $search,
                                    'start_date' => $start_date,
                                    'end_date' => $end_date,
                                    'min_price' => $min_price,
                                    'max_price' => $max_price,
                                    'payment_method' => $payment_method,
                                    'publisher_id' => $publisher_id,
                                    'hunter_status' => $hunter_status,
                                    'advanced' => isset($_GET['advanced']) ? 1 : null
                                ])
                            )); ?>" class="layui-laypage-first">
                            <i class="layui-icon layui-icon-prev"></i>
                        </a>
                        
                        <!-- 上一页按钮 -->
                        <a href="?<?php 
                            echo http_build_query(array_merge(
                                ['status' => $status, 'page' => max(1, $page - 1)],
                                array_filter([
                                    'search' => $search,
                                    'start_date' => $start_date,
                                    'end_date' => $end_date,
                                    'min_price' => $min_price,
                                    'max_price' => $max_price,
                                    'payment_method' => $payment_method,
                                    'publisher_id' => $publisher_id,
                                    'hunter_status' => $hunter_status,
                                    'advanced' => isset($_GET['advanced']) ? 1 : null
                                ])
                            )); ?>" class="layui-laypage-prev">
                            <i class="layui-icon layui-icon-left"></i>
                        </a>
                        
                        <!-- 页码按钮 -->
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
                            <a href="?<?php 
                                echo http_build_query(array_merge(
                                    ['status' => $status, 'page' => $i],
                                    array_filter([
                                        'search' => $search,
                                        'start_date' => $start_date,
                                        'end_date' => $end_date,
                                        'min_price' => $min_price,
                                        'max_price' => $max_price,
                                        'payment_method' => $payment_method,
                                        'publisher_id' => $publisher_id,
                                        'hunter_status' => $hunter_status,
                                        'advanced' => isset($_GET['advanced']) ? 1 : null
                                    ])
                                )); ?>" class="<?php echo $i == $page ? 'layui-laypage-curr' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <!-- 下一页按钮 -->
                        <a href="?<?php 
                            echo http_build_query(array_merge(
                                ['status' => $status, 'page' => min($total_pages, $page + 1)],
                                array_filter([
                                    'search' => $search,
                                    'start_date' => $start_date,
                                    'end_date' => $end_date,
                                    'min_price' => $min_price,
                                    'max_price' => $max_price,
                                    'payment_method' => $payment_method,
                                    'publisher_id' => $publisher_id,
                                    'hunter_status' => $hunter_status,
                                    'advanced' => isset($_GET['advanced']) ? 1 : null
                                ])
                            )); ?>" class="layui-laypage-next">
                            <i class="layui-icon layui-icon-right"></i>
                        </a>
                        
                        <!-- 最后一页按钮 -->
                        <a href="?<?php 
                            echo http_build_query(array_merge(
                                ['status' => $status, 'page' => $total_pages],
                                array_filter([
                                    'search' => $search,
                                    'start_date' => $start_date,
                                    'end_date' => $end_date,
                                    'min_price' => $min_price,
                                    'max_price' => $max_price,
                                    'payment_method' => $payment_method,
                                    'publisher_id' => $publisher_id,
                                    'hunter_status' => $hunter_status,
                                    'advanced' => isset($_GET['advanced']) ? 1 : null
                                ])
                            )); ?>" class="layui-laypage-last">
                            <i class="layui-icon layui-icon-next"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../res/layui/layui.js"></script>
<script>
layui.use(['form', 'laydate'], function(){
    var form = layui.form;
    var laydate = layui.laydate;
    
    // 日期选择器
    laydate.render({
        elem: '#start_date',
        format: 'yyyy-MM-dd'
    });
    
    laydate.render({
        elem: '#end_date',
        format: 'yyyy-MM-dd'
    });
    
    // 显示/隐藏高级筛选
    $('#toggleFilters').on('click', function() {
        $('.filter-content').toggle();
    });
    
    // 导出数据功能
    $('#exportBtn').on('click', function() {
        var params = {
            status: '<?php echo $status; ?>',
            search: '<?php echo $search; ?>',
            start_date: '<?php echo $start_date; ?>',
            end_date: '<?php echo $end_date; ?>',
            min_price: '<?php echo $min_price; ?>',
            max_price: '<?php echo $max_price; ?>',
            payment_method: '<?php echo $payment_method; ?>',
            publisher_id: '<?php echo $publisher_id; ?>',
            hunter_status: '<?php echo $hunter_status; ?>'
        };
        
        var queryString = Object.keys(params)
            .filter(key => params[key] !== '')
            .map(key => key + '=' + encodeURIComponent(params[key]))
            .join('&');
        
        window.location.href = 'order_export.php?' + queryString;
    });
});
</script>
</body>
</html>