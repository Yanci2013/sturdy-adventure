<?php
ob_start();
require_once __DIR__.'/../../includes/common.php';
session_start();

$search = $_GET['search'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$type = $_GET['type'] ?? '';

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

try {
    $where_conditions = [];
    $params = [];
    
    // 查询与余额相关的流水
    // source = 'balance' 或 destination = 'balance' 或 source/destination 包含 balance
    $where_conditions[] = "(fd.source = 'balance' OR fd.destination = 'balance' OR fd.note LIKE '%余额%')";
    
    if (!empty($search)) {
        $where_conditions[] = "(u.username LIKE :search OR u.phone LIKE :search2 OR fd.order_no LIKE :search3 OR fd.note LIKE :search4)";
        $params[':search'] = "%$search%";
        $params[':search2'] = "%$search%";
        $params[':search3'] = "%$search%";
        $params[':search4'] = "%$search%";
    }
    
    if (!empty($start_date) && !empty($end_date)) {
        $where_conditions[] = "fd.create_time BETWEEN :start_date AND :end_date";
        $params[':start_date'] = $start_date . ' 00:00:00';
        $params[':end_date'] = $end_date . ' 23:59:59';
    } elseif (!empty($start_date)) {
        $where_conditions[] = "fd.create_time >= :start_date";
        $params[':start_date'] = $start_date . ' 00:00:00';
    } elseif (!empty($end_date)) {
        $where_conditions[] = "fd.create_time <= :end_date";
        $params[':end_date'] = $end_date . ' 23:59:59';
    }
    
    if (!empty($type)) {
        if ($type == 'recharge') {
            $where_conditions[] = "fd.amount > 0 AND (fd.note LIKE '%充值%' OR fd.note LIKE '%转入%' OR fd.note LIKE '%佣金%')";
        } elseif ($type == 'transfer_in') {
            $where_conditions[] = "fd.amount > 0 AND (fd.note LIKE '%转入%' OR fd.note LIKE '%佣金%')";
        } elseif ($type == 'transfer_out') {
            $where_conditions[] = "fd.amount < 0 AND fd.note LIKE '%转出%'";
        } elseif ($type == 'expense') {
            $where_conditions[] = "fd.amount < 0";
        }
    }
    
    $where_sql = ' WHERE ' . implode(' AND ', $where_conditions);
    
    // 获取总数
    $count_sql = "SELECT COUNT(*) FROM fund_details fd LEFT JOIN users u ON fd.user_id = u.id" . $where_sql;
    $count_stmt = $pdo->prepare($count_sql);
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    $count_stmt->execute();
    $total_orders = $count_stmt->fetchColumn();
    $total_pages = ceil($total_orders / $per_page);
    
    // 获取列表
    $sql = "SELECT fd.*, u.username, u.phone, u.avatar 
            FROM fund_details fd 
            LEFT JOIN users u ON fd.user_id = u.id 
            $where_sql 
            ORDER BY fd.create_time DESC 
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 计算总变动金额
    $total_in = 0;
    $total_out = 0;
    $total_sql = "SELECT 
        SUM(CASE WHEN fd.amount > 0 THEN fd.amount ELSE 0 END) as total_in,
        SUM(CASE WHEN fd.amount < 0 THEN ABS(fd.amount) ELSE 0 END) as total_out
        FROM fund_details fd 
        LEFT JOIN users u ON fd.user_id = u.id 
        $where_sql";
    $total_stmt = $pdo->prepare($total_sql);
    foreach ($params as $key => $value) {
        $total_stmt->bindValue($key, $value);
    }
    $total_stmt->execute();
    $totals = $total_stmt->fetch(PDO::FETCH_ASSOC);
    $total_in = $totals['total_in'] ?? 0;
    $total_out = $totals['total_out'] ?? 0;
    
} catch (PDOException $e) {
    die("数据库查询失败: " . $e->getMessage());
}

// 交易类型标签
function getTypeLabel($row) {
    $note = $row['note'] ?? '';
    $amount = $row['amount'] ?? 0;
    $source = $row['source'] ?? '';
    $destination = $row['destination'] ?? '';
    
    if (strpos($note, '充值') !== false) {
        return ['label' => '余额充值', 'class' => 'badge-recharge'];
    } elseif (strpos($note, '订单佣金') !== false) {
        return ['label' => '订单佣金', 'class' => 'badge-commission'];
    } elseif (strpos($note, '保证金转出到余额') !== false || strpos($note, '保证金转移到余额') !== false) {
        return ['label' => '保证金转余额', 'class' => 'badge-transfer-in'];
    } elseif (strpos($note, '余额转入保证金') !== false) {
        return ['label' => '余额转保证金', 'class' => 'badge-transfer-out'];
    } elseif (strpos($note, '开会员') !== false || strpos($note, '会员') !== false) {
        return ['label' => '会员消费', 'class' => 'badge-expense'];
    } elseif (strpos($note, '提现') !== false) {
        return ['label' => '提现', 'class' => 'badge-withdraw'];
    } elseif (strpos($note, '打赏') !== false) {
        return ['label' => '打赏', 'class' => 'badge-tip'];
    } elseif ($amount > 0) {
        return ['label' => '收入', 'class' => 'badge-income'];
    } else {
        return ['label' => '支出', 'class' => 'badge-expense'];
    }
}

$pageTitle = '余额资金流水';
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
    
    .stats-container { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 16px; }
    .stats-card { background: #f5f5f5; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.2s; }
    .stats-card.income { border-left: 4px solid #4caf50; }
    .stats-card.outcome { border-left: 4px solid #f44336; }
    .stats-card.balance { border-left: 4px solid #2196f3; }
    .stats-card h3 { font-size: 12px; font-weight: 500; color: #666; margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }
    .stats-card .amount { font-size: 20px; font-weight: 700; color: #333; }
    .stats-card.income .amount { color: #4caf50; }
    .stats-card.outcome .amount { color: #f44336; }
    .stats-card.balance .amount { color: #2196f3; }
    .stats-card .count { font-size: 11px; color: #999; margin-top: 4px; }
    
    .card { background: #fafafa; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
    .card-header { font-size: 14px; font-weight: 600; color: #333; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #eee; }
    
    .filter-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end; margin-bottom: 16px; }
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
    
    .user-info { display: flex; align-items: center; gap: 8px; }
    .user-avatar { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; background: #f5f5f5; }
    .price-value { font-weight: 600; }
    .price-value.income { color: #4caf50; }
    .price-value.outcome { color: #f44336; }
    
    .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 500; }
    .badge-recharge { background: #e8f5e9; color: #388e3c; }
    .badge-commission { background: #e3f2fd; color: #1976d2; }
    .badge-transfer-in { background: #e8f5e9; color: #388e3c; }
    .badge-transfer-out { background: #fff3e0; color: #f57c00; }
    .badge-income { background: #e8f5e9; color: #388e3c; }
    .badge-expense { background: #ffebee; color: #d32f2f; }
    .badge-withdraw { background: #f3e5f5; color: #7b1fa2; }
    .badge-tip { background: #fce4ec; color: #c2185b; }
    
    .empty-state { text-align: center; padding: 40px 20px; }
    .empty-icon { font-size: 32px; color: #ccc; margin-bottom: 8px; }
    .empty-message { color: #999; font-size: 14px; }
    
    .pagination { display: flex; justify-content: center; gap: 4px; margin-top: 20px; flex-wrap: wrap; }
    .pagination a { display: inline-flex; align-items: center; justify-content: center; min-width: 32px; height: 32px; padding: 0 8px; border: 1px solid #ddd; background: #fff; color: #333; font-size: 12px; text-decoration: none; border-radius: 4px; transition: all 0.2s; }
    .pagination a:hover { background: #f5f5f5; }
    .pagination a.active { background: #1976d2; color: #fff; border-color: #1976d2; }
    
    @media (max-width: 768px) {
      .stats-container { grid-template-columns: 1fr; }
      .filter-form { grid-template-columns: 1fr; }
    }
    </style>
</head>
<body>
    <div class="container">
        <h1>💳 余额资金流水</h1>
        <a href="orders.php" class="btn btn-secondary btn-sm" style="margin-bottom: 16px; display: inline-block;">
            <i class="fa fa-arrow-left"></i> 返回订单列表
        </a>
        <!-- 统计卡片 -->
        <div class="stats-container">
            <div class="stats-card income">
                <h3><i class="fa fa-arrow-down"></i> 总流入</h3>
                <div class="amount">¥<?php echo number_format($total_in, 2); ?></div>
                <div class="count">充值 + 佣金 + 保证金转入</div>
            </div>
            <div class="stats-card outcome">
                <h3><i class="fa fa-arrow-up"></i> 总流出</h3>
                <div class="amount">¥<?php echo number_format($total_out, 2); ?></div>
                <div class="count">提现 + 转保证金 + 消费</div>
            </div>
            <div class="stats-card balance">
                <h3><i class="fa fa-wallet"></i> 净变动</h3>
                <div class="amount">¥<?php echo number_format($total_in - $total_out, 2); ?></div>
                <div class="count">共 <?php echo $total_orders; ?> 笔流水</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">🔍 筛选条件</div>
            <form method="GET">
                <div class="filter-form">
                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label">搜索用户/订单</label>
                        <input type="text" name="search" placeholder="搜索用户名、手机号、订单号..." 
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
                        <label class="form-label">类型</label>
                        <select name="type" class="form-select">
                            <option value="">全部</option>
                            <option value="recharge" <?php echo $type == 'recharge' ? 'selected' : ''; ?>>充值/佣金</option>
                            <option value="transfer_in" <?php echo $type == 'transfer_in' ? 'selected' : ''; ?>>保证金转入</option>
                            <option value="transfer_out" <?php echo $type == 'transfer_out' ? 'selected' : ''; ?>>转出到保证金</option>
                            <option value="expense" <?php echo $type == 'expense' ? 'selected' : ''; ?>>支出</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> 搜索</button>
                            <a href="orders-balance-recharge.php" class="btn btn-secondary">重置</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">📋 余额资金流水</div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户</th>
                            <th>类型</th>
                            <th>变动金额</th>
                            <th>来源</th>
                            <th>去向</th>
                            <th>备注</th>
                            <th>订单号</th>
                            <th>时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <div class="empty-icon">💳</div>
                                        <div class="empty-message">暂无余额流水记录</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): 
                                $avatar = !empty($order['avatar']) ? '../../assets/images/avatar/' . $order['avatar'] : 'https://sydj.bcorn.cn/assets/images/user.png';
                                $type_info = getTypeLabel($order);
                                $is_income = $order['amount'] > 0;
                            ?>
                            <tr>
                                <td><?php echo $order['id']; ?></td>
                                <td>
                                    <div class="user-info">
                                        <img src="<?php echo $avatar; ?>" class="user-avatar">
                                        <?php echo htmlspecialchars($order['username'] ?? '未知用户'); ?>
                                    </div>
                                </td>
                                <td><span class="badge <?php echo $type_info['class']; ?>"><?php echo $type_info['label']; ?></span></td>
                                <td class="price-value <?php echo $is_income ? 'income' : 'outcome'; ?>">
                                    <?php echo $is_income ? '+' : ''; ?>¥<?php echo number_format(abs($order['amount']), 2); ?>
                                </td>
                                <td><?php echo htmlspecialchars($order['source'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($order['destination'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($order['note'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($order['order_no'] ?? '-'); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($order['create_time'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <a href="orders-balance-recharge.php?<?php echo http_build_query(array_merge(['page' => 1], array_filter(['search' => $search, 'start_date' => $start_date, 'end_date' => $end_date, 'type' => $type]))); ?>" title="第一页"><i class="fa fa-angle-double-left"></i></a>
                <a href="orders-balance-recharge.php?<?php echo http_build_query(array_merge(['page' => max(1, $page - 1)], array_filter(['search' => $search, 'start_date' => $start_date, 'end_date' => $end_date, 'type' => $type]))); ?>" title="上一页"><i class="fa fa-angle-left"></i></a>
                <?php 
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($end_page - $start_page < 4) {
                    if ($page < 3) { $end_page = min($total_pages, $start_page + 4); }
                    else { $start_page = max(1, $end_page - 4); }
                }
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="orders-balance-recharge.php?<?php echo http_build_query(array_merge(['page' => $i], array_filter(['search' => $search, 'start_date' => $start_date, 'end_date' => $end_date, 'type' => $type]))); ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <a href="orders-balance-recharge.php?<?php echo http_build_query(array_merge(['page' => min($total_pages, $page + 1)], array_filter(['search' => $search, 'start_date' => $start_date, 'end_date' => $end_date, 'type' => $type]))); ?>" title="下一页"><i class="fa fa-angle-right"></i></a>
                <a href="orders-balance-recharge.php?<?php echo http_build_query(array_merge(['page' => $total_pages], array_filter(['search' => $search, 'start_date' => $start_date, 'end_date' => $end_date, 'type' => $type]))); ?>" title="最后一页"><i class="fa fa-angle-double-right"></i></a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
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
