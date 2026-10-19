<?php
// 开启输出缓冲
ob_start();

require_once("../../includes/session_manager.php");
include("../../includes/common.php");

// 检查管理员登录
if (!is_admin_logged_in()) {
    ob_end_clean();
    header("Location: ../login.php");
    exit();
}

// 获取筛选参数
$page = max(1, isset($_GET['page']) ? intval($_GET['page']) : 1);
$limit = 20;
$offset = ($page - 1) * $limit;

$search_user = isset($_GET['search_user']) ? trim($_GET['search_user']) : '';
$search_type = isset($_GET['search_type']) ? trim($_GET['search_type']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// 交易类型映射
$typeMap = [
    'fund_order_income' => ['label' => '订单收入', 'class' => 'order_income'],
    'fund_withdrawal' => ['label' => '提现支出', 'class' => 'withdrawal'],
    'fund_recharge' => ['label' => '充值收入', 'class' => 'recharge'],
    'fund_refund' => ['label' => '退款', 'class' => 'refund'],
    'fund_penalty' => ['label' => '处罚扣款', 'class' => 'penalty'],
    'fund_bonus' => ['label' => '奖励', 'class' => 'bonus'],
    'fund_adjustment' => ['label' => '手动调整', 'class' => 'adjustment'],
    'fund_order_payment' => ['label' => '订单支付', 'class' => 'order_payment'],
    'withdrawal_pending' => ['label' => '提现申请', 'class' => 'withdrawal'],
    'withdrawal_completed' => ['label' => '提现完成', 'class' => 'withdrawal'],
    'withdrawal_rejected' => ['label' => '提现拒绝', 'class' => 'penalty'],
    'recharge_online' => ['label' => '在线充值', 'class' => 'recharge'],
    'recharge_manual' => ['label' => '手动充值', 'class' => 'recharge'],
];

$all_transactions = [];
$fund_error = null;

try {
    // 构建基础查询条件
    $where_conditions = [];
    $params = [];
    
    if (!empty($search_user)) {
        $where_conditions[] = "(u.username LIKE ? OR u.id = ?)";
        $params[] = "%{$search_user}%";
        $params[] = is_numeric($search_user) ? intval($search_user) : 0;
    }
    
    if (!empty($date_from)) {
        $where_conditions[] = "create_time >= ?";
        $params[] = $date_from . ' 00:00:00';
    }
    
    if (!empty($date_to)) {
        $where_conditions[] = "create_time <= ?";
        $params[] = $date_to . ' 23:59:59';
    }
    
    $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // 查询1: fund_details 资金明细表
    $fund_conditions = $where_conditions;
    $fund_params = $params;
    
    if (!empty($search_type) && strpos($search_type, 'fund_') === 0) {
        $source = str_replace('fund_', '', $search_type);
        $fund_conditions[] = "source = ?";
        $fund_params[] = $source;
    }
    
    $fund_where = !empty($fund_conditions) ? 'WHERE ' . implode(' AND ', $fund_conditions) : '';
    
    $fundSql = "SELECT 
        fd.id, 
        fd.user_id,
        u.username,
        fd.create_time, 
        CONCAT('fund_', fd.source) as type,
        fd.source as source_type,
        fd.destination,
        fd.amount, 
        fd.related_id,
        fd.note,
        fd.order_no,
        'fund_details' as table_source
        FROM fund_details fd
        LEFT JOIN users u ON fd.user_id = u.id
        {$fund_where}";
    
    // 查询2: withdrawals 提现表
    $withdrawal_conditions = $where_conditions;
    $withdrawal_params = $params;
    
    if (!empty($search_type) && strpos($search_type, 'withdrawal_') === 0) {
        $status = str_replace('withdrawal_', '', $search_type);
        $withdrawal_conditions[] = "w.status = ?";
        $withdrawal_params[] = $status;
    }
    
    $withdrawal_where = !empty($withdrawal_conditions) ? 'WHERE ' . implode(' AND ', $withdrawal_conditions) : '';
    
    $withdrawalSql = "SELECT 
        w.id,
        w.user_id,
        u.username,
        w.created_at as create_time,
        CONCAT('withdrawal_', w.status) as type,
        'withdrawal' as source_type,
        w.payment_method as destination,
        -w.amount as amount,
        w.id as related_id,
        CONCAT('提现', CASE w.status WHEN 'pending' THEN '申请' WHEN 'completed' THEN '完成' WHEN 'rejected' THEN '拒绝' ELSE w.status END, IF(w.remark != '', CONCAT(' - ', w.remark), '')) as note,
        w.withdrawal_no as order_no,
        'withdrawals' as table_source
        FROM withdrawals w
        LEFT JOIN users u ON w.user_id = u.id
        {$withdrawal_where}";
    
    // 合并查询
    $union_params = array_merge($fund_params, $withdrawal_params);
    $unionSql = "({$fundSql}) UNION ALL ({$withdrawalSql}) ORDER BY create_time DESC LIMIT ?, ?";
    
    $stmt = $pdo->prepare($unionSql);
    
    // 绑定参数
    $paramIndex = 1;
    foreach ($union_params as $param) {
        $stmt->bindValue($paramIndex++, $param);
    }
    $stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
    $stmt->bindValue($paramIndex, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $all_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    // 查询总记录数
    $countSql = "SELECT COUNT(*) FROM (({$fundSql}) UNION ALL ({$withdrawalSql})) as total";
    $countStmt = $pdo->prepare($countSql);
    $paramIndex = 1;
    foreach ($union_params as $param) {
        $countStmt->bindValue($paramIndex++, $param);
    }
    $countStmt->execute();
    $total_rows = $countStmt->fetchColumn();
    
} catch (PDOException $e) {
    $fund_error = "查询资金明细失败: " . $e->getMessage();
    error_log($fund_error);
    $total_rows = 0;
}

$total_pages = ceil($total_rows / $limit);

// 统计今日收支
$today_income = 0;
$today_expense = 0;
try {
    $today = date('Y-m-d');
    
    // 今日收入（fund_details中amount>0的）
    $incomeSql = "SELECT SUM(amount) FROM fund_details WHERE DATE(create_time) = ? AND amount > 0";
    $incomeStmt = $pdo->prepare($incomeSql);
    $incomeStmt->execute([$today]);
    $today_income = $incomeStmt->fetchColumn() ?? 0;
    
    // 今日支出（fund_details中amount<0的 + withdrawals中completed的）
    $expenseSql = "SELECT SUM(ABS(amount)) FROM fund_details WHERE DATE(create_time) = ? AND amount < 0";
    $expenseStmt = $pdo->prepare($expenseSql);
    $expenseStmt->execute([$today]);
    $fund_expense = $expenseStmt->fetchColumn() ?? 0;
    
    $withdrawalExpenseSql = "SELECT SUM(amount) FROM withdrawals WHERE DATE(created_at) = ? AND status IN ('completed', 'pending')";
    $withdrawalStmt = $pdo->prepare($withdrawalExpenseSql);
    $withdrawalStmt->execute([$today]);
    $withdrawal_expense = $withdrawalStmt->fetchColumn() ?? 0;
    
    $today_expense = $fund_expense + $withdrawal_expense;
    
} catch (PDOException $e) {
    error_log("统计今日收支失败: " . $e->getMessage());
}

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>资金流水 - 后台管理</title>
    <link href="../res/layui/css/layui.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #ffffff; color: #333; line-height: 1.5; padding: 20px; font-size: 13px; }
    .container { max-width: 1200px; margin: 0 auto; }
    h1 { font-size: 18px; font-weight: 600; margin-bottom: 16px; color: #000; }
    h2 { font-size: 14px; font-weight: 600; margin: 20px 0 12px 0; color: #333; }
    
    .page-header { padding: 16px; background: #f8f9fa; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e9ecef; }
    .page-title { font-size: 18px; font-weight: 600; color: #333; }
    .page-subtitle { font-size: 14px; color: #6c757d; margin-top: 4px; }
    
    .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }
    .stat-card { background: #f8f9fa; border-radius: 8px; padding: 16px; border: 1px solid #e9ecef; display: flex; align-items: center; gap: 12px; }
    .stat-icon { width: 48px; height: 48px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
    .stat-icon.income { background: #e8f5e9; color: #2e7d32; }
    .stat-icon.expense { background: #ffebee; color: #c62828; }
    .stat-icon.total { background: #e3f2fd; color: #1565c0; }
    .stat-info h3 { font-size: 12px; font-weight: 500; color: #6c757d; margin-bottom: 4px; }
    .stat-info .amount { font-size: 20px; font-weight: 700; color: #333; }
    .stat-info .amount.income { color: #2e7d32; }
    .stat-info .amount.expense { color: #c62828; }
    
    .filter-bar { background: #f8f9fa; border-radius: 8px; padding: 16px; margin-bottom: 20px; border: 1px solid #e9ecef; }
    .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end; }
    .filter-item { display: flex; flex-direction: column; }
    .filter-item label { display: block; font-size: 12px; font-weight: 500; color: #6c757d; margin-bottom: 6px; }
    .filter-item input, .filter-item select { width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 13px; }
    .filter-item input:focus, .filter-item select:focus { outline: none; border-color: #007bff; box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25); }
    
    .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border: 1px solid #ced4da; border-radius: 4px; font-size: 13px; cursor: pointer; text-decoration: none; transition: all 0.2s; }
    .btn-primary { background: #007bff; color: #fff; border-color: #007bff; }
    .btn-primary:hover { background: #0069d9; }
    .btn-secondary { background: #6c757d; color: #fff; border-color: #6c757d; }
    .btn-secondary:hover { background: #5a6268; }
    .btn-group { display: flex; gap: 8px; align-items: flex-end; flex-wrap: wrap; }
    
    .data-card { background: #f8f9fa; border-radius: 8px; padding: 16px; margin-bottom: 20px; border: 1px solid #e9ecef; }
    .data-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .data-table th { background: #e9ecef; color: #495057; font-weight: 500; padding: 10px 12px; text-align: left; border-bottom: 2px solid #dee2e6; }
    .data-table td { padding: 10px 12px; border-bottom: 1px solid #dee2e6; color: #333; }
    .data-table tbody tr:hover { background: #f8f9fa; }
    
    .user-info { display: flex; align-items: center; gap: 8px; }
    .user-avatar { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; background: #e9ecef; }
    .user-name { font-weight: 500; color: #333; }
    .user-id { font-size: 11px; color: #6c757d; }
    
    .amount { font-weight: 600; font-size: 14px; }
    .amount.income { color: #2e7d32; }
    .amount.expense { color: #c62828; }
    
    .type-tag { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 500; }
    .type-tag.order_income { background: #e8f5e9; color: #2e7d32; }
    .type-tag.withdrawal { background: #ffebee; color: #c62828; }
    .type-tag.recharge { background: #e3f2fd; color: #1565c0; }
    .type-tag.refund { background: #fff3e0; color: #ef6c00; }
    .type-tag.penalty { background: #ffebee; color: #c62828; }
    .type-tag.bonus { background: #e8f5e9; color: #2e7d32; }
    .type-tag.adjustment { background: #fff3e0; color: #ef6c00; }
    .type-tag.order_payment { background: #e3f2fd; color: #1565c0; }
    .type-tag.default { background: #f8f9fa; color: #6c757d; }
    
    .time { font-size: 12px; color: #6c757d; }
    .note { font-size: 11px; color: #6c757d; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .table-source { font-size: 11px; color: #6c757d; background: #e9ecef; padding: 2px 8px; border-radius: 10px; }
    
    .pagination { display: flex; justify-content: center; gap: 4px; margin-top: 20px; flex-wrap: wrap; }
    .page-btn { display: inline-flex; align-items: center; justify-content: center; min-width: 32px; height: 32px; padding: 0 8px; border: 1px solid #dee2e6; background: #fff; color: #495057; font-size: 12px; text-decoration: none; border-radius: 4px; transition: all 0.2s; }
    .page-btn:hover { background: #e9ecef; }
    .page-btn.active { background: #007bff; color: #fff; border-color: #007bff; }
    .page-btn.disabled { opacity: 0.5; cursor: not-allowed; }
    
    .empty-state { text-align: center; padding: 40px 20px; }
    .empty-icon { font-size: 32px; color: #ced4da; margin-bottom: 12px; }
    .empty-title { font-size: 16px; font-weight: 600; color: #333; margin-bottom: 8px; }
    .empty-message { font-size: 14px; color: #6c757d; }
    
    @media (max-width: 768px) {
      .filter-grid { grid-template-columns: 1fr; }
    }
    </style>
</head>
<body>
    <div class="container">
        <!-- 页面标题 -->
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-chart-line"></i> 资金流水</h1>
            <p class="page-subtitle">查看全系统所有资金变动记录（包含资金明细、提现记录等）</p>
        </div>

        <!-- 统计卡片 -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon income">
                    <i class="fas fa-arrow-down"></i>
                </div>
                <div class="stat-info">
                    <h3>今日收入</h3>
                    <div class="amount income">¥<?php echo number_format($today_income, 2); ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon expense">
                    <i class="fas fa-arrow-up"></i>
                </div>
                <div class="stat-info">
                    <h3>今日支出</h3>
                    <div class="amount expense">¥<?php echo number_format($today_expense, 2); ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="stat-info">
                    <h3>今日净额</h3>
                    <div class="amount">¥<?php echo number_format($today_income - $today_expense, 2); ?></div>
                </div>
            </div>
        </div>

        <!-- 筛选栏 -->
        <div class="filter-bar">
            <form class="filter-grid" method="get" action="">
                <div class="filter-item">
                    <label>用户搜索</label>
                    <input type="text" name="search_user" placeholder="用户名或ID" value="<?php echo htmlspecialchars($search_user); ?>">
                </div>
                <div class="filter-item">
                    <label>类型筛选</label>
                    <select name="search_type">
                        <option value="">全部类型</option>
                        <optgroup label="资金明细">
                            <option value="fund_order_income" <?php echo $search_type == 'fund_order_income' ? 'selected' : ''; ?>>订单收入</option>
                            <option value="fund_withdrawal" <?php echo $search_type == 'fund_withdrawal' ? 'selected' : ''; ?>>提现支出</option>
                            <option value="fund_recharge" <?php echo $search_type == 'fund_recharge' ? 'selected' : ''; ?>>充值收入</option>
                            <option value="fund_refund" <?php echo $search_type == 'fund_refund' ? 'selected' : ''; ?>>退款</option>
                            <option value="fund_penalty" <?php echo $search_type == 'fund_penalty' ? 'selected' : ''; ?>>处罚扣款</option>
                            <option value="fund_bonus" <?php echo $search_type == 'fund_bonus' ? 'selected' : ''; ?>>奖励</option>
                            <option value="fund_adjustment" <?php echo $search_type == 'fund_adjustment' ? 'selected' : ''; ?>>手动调整</option>
                            <option value="fund_order_payment" <?php echo $search_type == 'fund_order_payment' ? 'selected' : ''; ?>>订单支付</option>
                        </optgroup>
                        <optgroup label="提现记录">
                            <option value="withdrawal_pending" <?php echo $search_type == 'withdrawal_pending' ? 'selected' : ''; ?>>提现申请</option>
                            <option value="withdrawal_completed" <?php echo $search_type == 'withdrawal_completed' ? 'selected' : ''; ?>>提现完成</option>
                            <option value="withdrawal_rejected" <?php echo $search_type == 'withdrawal_rejected' ? 'selected' : ''; ?>>提现拒绝</option>
                        </optgroup>
                    </select>
                </div>
                <div class="filter-item">
                    <label>开始日期</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="filter-item">
                    <label>结束日期</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="filter-item">
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> 搜索
                        </button>
                        <a href="transaction_logs.php" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> 重置
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- 数据表格 -->
        <div class="data-card">
            <?php if ($fund_error): ?>
                <div class="empty-state">
                    <div class="empty-icon">⚠️</div>
                    <div class="empty-title">查询失败</div>
                    <div class="empty-message"><?php echo htmlspecialchars($fund_error); ?></div>
                </div>
            <?php elseif (empty($all_transactions)): ?>
                <div class="empty-state">
                    <div class="empty-icon">💰</div>
                    <div class="empty-title">暂无资金记录</div>
                    <div class="empty-message">没有找到符合条件的资金流水记录</div>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户</th>
                            <th>类型</th>
                            <th>金额</th>
                            <th>关联订单</th>
                            <th>单号</th>
                            <th>备注</th>
                            <th>数据来源</th>
                            <th>时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_transactions as $transaction): 
                            $typeInfo = $typeMap[$transaction['type']] ?? ['label' => $transaction['type'], 'class' => 'default'];
                        ?>
                            <tr>
                                <td><?php echo $transaction['id']; ?></td>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php echo mb_substr($transaction['username'] ?? 'U', 0, 1, 'UTF-8'); ?>
                                        </div>
                                        <div>
                                            <div class="user-name"><?php echo htmlspecialchars($transaction['username'] ?? '未知用户'); ?></div>
                                            <div class="user-id">ID: <?php echo $transaction['user_id']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="type-tag <?php echo $typeInfo['class']; ?>">
                                        <?php echo $typeInfo['label']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="amount <?php echo $transaction['amount'] > 0 ? 'income' : 'expense'; ?>">
                                        <?php echo $transaction['amount'] > 0 ? '+' : ''; ?>¥<?php echo number_format($transaction['amount'], 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($transaction['related_id'])): ?>
                                        <a href="../orders/order_view.php?id=<?php echo $transaction['related_id']; ?>" target="_blank">
                                            #<?php echo $transaction['related_id']; ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($transaction['order_no'] ?? '-'); ?></td>
                                <td>
                                    <div class="note" title="<?php echo htmlspecialchars($transaction['note'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($transaction['note'] ?? '-'); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="table-source">
                                        <?php echo $transaction['table_source'] == 'fund_details' ? '资金明细' : '提现记录'; ?>
                                    </span>
                                </td>
                                <td class="time"><?php echo date('Y-m-d H:i:s', strtotime($transaction['create_time'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- 分页 -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" class="page-btn">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-btn disabled"><i class="fas fa-chevron-left"></i></span>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        if ($start > 1) {
                            echo '<a href="?page=1&' . http_build_query(array_diff_key($_GET, ['page' => ''])) . '" class="page-btn">1</a>';
                            if ($start > 2) {
                                echo '<span class="page-btn disabled">...</span>';
                            }
                        }
                        
                        for ($i = $start; $i <= $end; $i++): 
                            $query_params = array_diff_key($_GET, ['page' => '']);
                            $query_params['page'] = $i;
                        ?>
                            <?php if ($i == $page): ?>
                                <span class="page-btn active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?<?php echo http_build_query($query_params); ?>" class="page-btn"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor;
                        
                        if ($end < $total_pages) {
                            if ($end < $total_pages - 1) {
                                echo '<span class="page-btn disabled">...</span>';
                            }
                            echo '<a href="?page=' . $total_pages . '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) . '" class="page-btn">' . $total_pages . '</a>';
                        }
                        ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" class="page-btn">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-btn disabled"><i class="fas fa-chevron-right"></i></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
