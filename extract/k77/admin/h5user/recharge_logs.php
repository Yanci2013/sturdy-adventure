<?php
// 开启输出缓冲
ob_start();

require_once __DIR__.'/../../includes/common.php';

// 获取搜索参数
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_field = isset($_GET['search_field']) ? $_GET['search_field'] : 'user_id';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// 分页参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10; // 每页显示10条记录
$offset = ($page - 1) * $per_page;

// 构建基础SQL查询
$base_sql = "SELECT r.id, r.user_id, h.username, h.phone, r.amount, r.payment_type, r.trade_no, r.order_status, r.create_time 
             FROM recharge_orders r 
             LEFT JOIN h5user h ON r.user_id = h.id";
$count_sql = "SELECT COUNT(*) as total 
              FROM recharge_orders r";
$search_conditions = "";
$search_params = [];

// 如果有搜索查询，构建搜索条件
if (!empty($search_query)) {
    $search_conditions .= " WHERE ";
    switch ($search_field) {
        case 'user_id':
            $search_conditions .= "r.user_id = :search_id";
            $search_params[':search_id'] = $search_query;
            break;
        case 'username':
            $search_conditions .= "h.username LIKE :search_term";
            $search_params[':search_term'] = "%$search_query%";
            break;
        case 'phone':
            $search_conditions .= "h.phone LIKE :search_term";
            $search_params[':search_term'] = "%$search_query%";
            break;
        case 'transaction_id':
            $search_conditions .= "r.trade_no LIKE :search_term";
            $search_params[':search_term'] = "%$search_query%";
            break;
    }
} else {
    $search_conditions = " WHERE 1=1";
}

// 日期筛选
if (!empty($start_date)) {
    $search_conditions .= " AND r.create_time >= :start_date";
    $search_params[':start_date'] = $start_date . ' 00:00:00';
}

if (!empty($end_date)) {
    $search_conditions .= " AND r.create_time <= :end_date";
    $search_params[':end_date'] = $end_date . ' 23:59:59';
}

try {
    // 获取总记录数
    $stmt = $pdo->prepare($count_sql . $search_conditions);
    
    // 绑定搜索参数
    foreach ($search_params as $key => $value) {
        if (strpos($count_sql, $key) !== false || strpos($search_conditions, $key) !== false) {
            $param_type = ($key == ':search_id') ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $param_type);
        }
    }
    
    $stmt->execute();
    $total_logs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 计算总页数
    $total_pages = ceil($total_logs / $per_page);
    
    // 构建数据查询SQL
    $sql = $base_sql . $search_conditions . " ORDER BY r.create_time DESC LIMIT :per_page OFFSET :offset";
    
    // 准备并执行查询
    $stmt = $pdo->prepare($sql);
    
    // 绑定搜索参数
    foreach ($search_params as $key => $value) {
        if (strpos($sql, $key) !== false) {
            $param_type = ($key == ':search_id') ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $param_type);
        }
    }
    
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("查询充值记录失败: " . $e->getMessage());
}

// 页面标题
$pageTitle = 'H5用户充值记录';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Ma+Shan+Zheng&family=Noto+Serif+SC:wght@400;500;600;700&family=ZCOOL+XiaoWei&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 白色主题 - H5用户充值记录 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', sans-serif;
            background: #ffffff;
            color: #333;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            padding: 24px;
        }

        .container-fluid {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0;
        }

        /* 卡片样式 */
        .card {
            background: #ffffff;
            border: 1px solid #eaeaea;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .card-header {
            border-bottom: 1px solid #eaeaea;
            padding: 16px 20px;
            background: #ffffff;
        }

        .card-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin: 0 0 4px 0;
        }

        .card-header small {
            font-size: 12px;
            color: #999;
        }

        .card-body {
            padding: 20px;
        }

        /* 表单样式 */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: -10px;
        }

        .col-12 {
            width: 100%;
            padding: 10px;
        }

        .col-md-3 {
            width: 25%;
            padding: 10px;
        }

        @media screen and (max-width: 768px) {
            .col-md-3 {
                width: 100%;
            }
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #eaeaea;
            font-size: 14px;
            color: #333;
            transition: all 0.3s ease;
            border-radius: 12px;
            background: #ffffff;
        }

        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.2);
        }

        .form-control::placeholder {
            color: #cccccc;
        }

        .input-group {
            display: flex;
            align-items: center;
            border: 1px solid #eaeaea;
            border-radius: 12px;
            overflow: hidden;
        }

        .input-group .form-control {
            border: none;
            flex: 1;
            border-radius: 0;
        }

        .input-group-text {
            padding: 10px 12px;
            background: #f8f9fa;
            color: #666;
            border-left: 1px solid #eaeaea;
            font-size: 14px;
        }

        .btn {
            padding: 10px 16px;
            border: 1px solid #eaeaea;
            background: #ffffff;
            color: #666;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            border-radius: 12px;
        }

        .btn:hover {
            background: #f8f9fa;
            border-color: #999;
            color: #333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .btn-primary {
            background: #007bff;
            border-color: #007bff;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #0069d9;
            border-color: #0069d9;
            color: #ffffff;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 8px;
        }

        .btn-group {
            display: flex;
            gap: 4px;
        }

        /* 徽章样式 */
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border: 1px solid #eaeaea;
            font-size: 11px;
            color: #666;
            border-radius: 8px;
        }

        .status-success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
            border-color: #ffeaa7;
        }

        .status-failed {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        /* 表格样式 */
        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .table th {
            background: #f8f9fa;
            color: #666;
            font-weight: 500;
            padding: 14px 12px;
            border-bottom: 1px solid #eaeaea;
            text-align: left;
        }

        .table td {
            padding: 14px 12px;
            border-bottom: 1px solid #eaeaea;
            color: #333;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        /* 金额样式 */
        .amount {
            font-weight: 600;
            color: #28a745;
        }

        /* 空状态 */
        .no-results {
            text-align: center;
            padding: 60px 20px;
            background: #ffffff;
            border-radius: 12px;
            margin: 20px 0;
        }

        .no-results i {
            font-size: 48px;
            color: #cccccc;
            margin-bottom: 16px;
        }

        .no-results h4 {
            font-size: 16px;
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
        }

        .no-results p {
            font-size: 13px;
            color: #999;
        }

        /* 分页样式 */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 4px;
            margin-top: 24px;
            list-style: none;
        }

        .page-item {
            list-style: none;
        }

        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 8px;
            border: 1px solid #eaeaea;
            background: #ffffff;
            color: #666;
            font-size: 13px;
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 8px;
        }

        .page-link:hover {
            background: #f8f9fa;
            border-color: #999;
            color: #333;
            transform: translateY(-2px);
        }

        .page-item.active .page-link {
            background: #007bff;
            color: #ffffff;
            border-color: #007bff;
            font-weight: 500;
        }

        .page-item.disabled .page-link {
            opacity: 0.5;
            pointer-events: none;
        }

        /* 响应式设计 */
        @media screen and (max-width: 768px) {
            body {
                padding: 20px;
            }

            .card-header h3 {
                font-size: 16px;
            }

            .table {
                font-size: 12px;
            }

            .table th,
            .table td {
                padding: 10px 8px;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn-sm {
                padding: 4px 8px;
                font-size: 11px;
            }

            .page-link {
                min-width: 32px;
                height: 32px;
                font-size: 12px;
            }
        }

        @media screen and (max-width: 480px) {
            body {
                padding: 16px;
            }

            .card-header {
                padding: 14px 16px;
            }

            .card-body {
                padding: 16px;
            }

            .table {
                display: block;
                overflow-x: auto;
            }

            .pagination {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3><?php echo $pageTitle; ?></h3>
                    <small>查看H5用户的充值历史记录</small>
                </div>
                
                <!-- 搜索框 -->
                <div class="card-body">
                    <form class="row" action="recharge_logs.php" method="get">
                        <div class="col-md-3">
                            <input type="text" name="search" class="form-control" 
                                   placeholder="输入用户ID、用户名、手机号或交易ID..." 
                                   value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="search_field" class="form-control">
                                <option value="user_id" <?php echo $search_field == 'user_id' ? 'selected' : ''; ?>>用户ID</option>
                                <option value="username" <?php echo $search_field == 'username' ? 'selected' : ''; ?>>用户名</option>
                                <option value="phone" <?php echo $search_field == 'phone' ? 'selected' : ''; ?>>手机号</option>
                                <option value="transaction_id" <?php echo $search_field == 'transaction_id' ? 'selected' : ''; ?>>交易ID</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="date" name="start_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>
                        <div class="col-md-3">
                            <div class="input-group">
                                <input type="date" name="end_date" class="form-control" 
                                       value="<?php echo htmlspecialchars($end_date); ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> 搜索
                                </button>
                                <?php if (!empty($search_query) || !empty($start_date) || !empty($end_date)): ?>
                                <a href="recharge_logs.php" class="btn">
                                    <i class="fas fa-times"></i> 重置
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- 显示搜索结果信息 -->
                <?php if (!empty($search_query) || !empty($start_date) || !empty($end_date)): ?>
                <div class="card-body">
                    <div style="padding: 12px 16px; border: 1px solid #eaeaea; background: #f8f9fa;">
                        <i class="fas fa-search"></i>
                        共找到 <?php echo $total_logs; ?> 条充值记录
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="card-body">
                    <?php if (empty($logs) && (!empty($search_query) || !empty($start_date) || !empty($end_date))): ?>
                        <div class="no-results">
                            <i class="fas fa-history"></i>
                            <h4>没有找到匹配的充值记录</h4>
                            <p>请尝试修改搜索条件</p>
                        </div>
                    <?php elseif (empty($logs)): ?>
                        <div class="no-results">
                            <i class="fas fa-history"></i>
                            <h4>暂无充值记录</h4>
                            <p>暂无H5用户充值数据</p>
                        </div>
                    <?php else: ?>
                        <!-- 充值记录表格 -->
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>用户ID</th>
                                        <th>用户名</th>
                                        <th>手机号</th>
                                        <th>充值金额</th>
                                        <th>支付方式</th>
                                        <th>交易ID</th>
                                        <th>状态</th>
                                        <th>充值时间</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo $log['id']; ?></td>
                                        <td><?php echo $log['user_id']; ?></td>
                                        <td><?php echo htmlspecialchars($log['username'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($log['phone'] ?? '-'); ?></td>
                                        <td class="amount">¥<?php echo number_format($log['amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($log['payment_type'] ?? '未知'); ?></td>
                                        <td><?php echo htmlspecialchars($log['trade_no'] ?? '-'); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $log['order_status']; ?>">
                                                <?php 
                                                switch ($log['order_status']) {
                                                    case 0: echo '待支付'; break;
                                                    case 1: echo '已支付'; break;
                                                    default: echo '未知'; break;
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($log['create_time'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- 分页 -->
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="recharge_logs.php?page=<?php echo $page - 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) . '&search_field=' . urlencode($search_field) : ''; ?><?php echo !empty($start_date) ? '&start_date=' . urlencode($start_date) : ''; ?><?php echo !empty($end_date) ? '&end_date=' . urlencode($end_date) : ''; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link">
                                    <i class="fas fa-chevron-left"></i>
                                </span>
                            </li>
                            <?php endif; ?>
                            
                            <?php 
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $start_page + 4);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="recharge_logs.php?page=<?php echo $i; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) . '&search_field=' . urlencode($search_field) : ''; ?><?php echo !empty($start_date) ? '&start_date=' . urlencode($start_date) : ''; ?><?php echo !empty($end_date) ? '&end_date=' . urlencode($end_date) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="recharge_logs.php?page=<?php echo $page + 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) . '&search_field=' . urlencode($search_field) : ''; ?><?php echo !empty($start_date) ? '&start_date=' . urlencode($start_date) : ''; ?><?php echo !empty($end_date) ? '&end_date=' . urlencode($end_date) : ''; ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link">
                                    <i class="fas fa-chevron-right"></i>
                                </span>
                            </li>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php
// 关闭输出缓冲
ob_end_flush();
?>