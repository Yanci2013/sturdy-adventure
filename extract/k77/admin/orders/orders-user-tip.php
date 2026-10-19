<?php
ob_start();
require_once __DIR__.'/../../includes/common.php';
session_start();

$search = $_GET['search'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

try {
    // 检查表是否存在
    $table_exists = false;
    $stmt = $pdo->query("SHOW TABLES LIKE 'tip_orders'");
    $table_exists = $stmt->rowCount() > 0;
    
    $orders = [];
    $total_orders = 0;
    $total_amount = 0;
    $total_pages = 0;
    
    if ($table_exists) {
        $where_conditions = [];
        $params = [];
        
        if (!empty($search)) {
            $where_conditions[] = "(t.order_no LIKE :search OR from_u.username LIKE :search2 OR to_u.username LIKE :search3)";
            $params[':search'] = "%$search%";
            $params[':search2'] = "%$search%";
            $params[':search3'] = "%$search%";
        }
        
        if (!empty($start_date) && !empty($end_date)) {
            $where_conditions[] = "t.create_time BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
            $params[':end_date'] = $end_date . ' 23:59:59';
        } elseif (!empty($start_date)) {
            $where_conditions[] = "t.create_time >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        } elseif (!empty($end_date)) {
            $where_conditions[] = "t.create_time <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
        
        $where_sql = empty($where_conditions) ? '' : ' WHERE ' . implode(' AND ', $where_conditions);
        
        // 获取总数
        $count_sql = "SELECT COUNT(*) FROM tip_orders t" . $where_sql;
        $count_stmt = $pdo->prepare($count_sql);
        foreach ($params as $key => $value) {
            $count_stmt->bindValue($key, $value);
        }
        $count_stmt->execute();
        $total_orders = $count_stmt->fetchColumn();
        $total_pages = ceil($total_orders / $per_page);
        
        // 获取列表
        $sql = "SELECT t.*, 
                       from_u.username as from_username, from_u.avatar as from_avatar,
                       to_u.username as to_username, to_u.avatar as to_avatar
                FROM tip_orders t 
                LEFT JOIN users from_u ON t.from_user_id = from_u.id 
                LEFT JOIN users to_u ON t.to_user_id = to_u.id 
                $where_sql 
                ORDER BY t.create_time DESC 
                LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 计算总金额
        $total_sql = "SELECT SUM(amount) FROM tip_orders t $where_sql";
        $total_stmt = $pdo->prepare($total_sql);
        foreach ($params as $key => $value) {
            $total_stmt->bindValue($key, $value);
        }
        $total_stmt->execute();
        $total_amount = $total_stmt->fetchColumn() ?? 0;
    }
    
} catch (PDOException $e) {
    die("数据库查询失败: " . $e->getMessage());
}

$pageTitle = '打赏用户订单';
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
    :root {
      --primary-teal: #3baaa3;
      --primary-teal-dark: #2a8a84;
      --primary-teal-light: #5bc4bd;
      --scroll-paper: #fdf8e8;
      --scroll-paper-dark: #f5ecd8;
      --gold: #c9a45c;
      --gold-dark: #a8843d;
      --gold-light: #e0c078;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: 'Noto Serif SC', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(135deg, #f5f5f5 0%, var(--scroll-paper) 100%);
        color: #333; line-height: 1.5; -webkit-font-smoothing: antialiased;
        padding: 12px; padding-bottom: 80px;
    }
    .app-container { max-width: 100%; margin: 0 auto; animation: fadeInUp 0.6s ease-out; }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .header {
        padding: 16px 12px; background: #ffffff; margin: -12px -12px 12px -12px;
        border-radius: 0 0 16px 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        display: flex; justify-content: space-between; align-items: center;
    }
    .header-title { font-size: 20px; font-weight: 600; color: var(--gold-dark); }
    .stats-card {
        background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
        color: #fff; border-radius: 16px; padding: 20px; margin-bottom: 16px;
        box-shadow: 0 4px 15px rgba(236, 72, 153, 0.3);
    }
    .stats-card h3 { font-size: 14px; font-weight: 500; opacity: 0.9; margin-bottom: 8px; }
    .stats-card .amount { font-size: 28px; font-weight: 700; }
    .stats-card .count { font-size: 14px; opacity: 0.8; margin-top: 4px; }
    .card {
        background: #ffffff; border-radius: 16px; padding: 16px; margin-bottom: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08); transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.1); }
    .card-header {
        font-size: 16px; font-weight: 600; color: var(--gold-dark); margin-bottom: 16px;
        padding-bottom: 12px; border-bottom: 1px solid var(--scroll-paper-dark);
    }
    .form-group { margin-bottom: 16px; }
    .form-label { display: block; font-size: 14px; font-weight: 500; color: #666; margin-bottom: 8px; }
    .form-input {
        width: 100%; padding: 12px 16px; border: 1px solid var(--scroll-paper-dark);
        border-radius: 12px; font-size: 14px; color: #333; background: #ffffff; transition: all 0.3s ease;
    }
    .form-input:focus { outline: none; border-color: var(--gold-dark); box-shadow: 0 0 0 3px rgba(0,0,0,0.1); }
    .form-input::placeholder { color: #cccccc; }
    .btn {
        display: inline-block; padding: 12px 24px; border: none; border-radius: 12px;
        font-size: 14px; font-weight: 500; cursor: pointer; text-decoration: none; transition: all 0.3s ease;
    }
    .btn-primary { background: linear-gradient(135deg, var(--primary-teal) 0%, var(--primary-teal-dark) 100%); color: #ffffff; }
    .btn-primary:hover { background: linear-gradient(135deg, var(--primary-teal-light) 0%, var(--primary-teal) 100%); transform: translateY(-2px); }
    .btn-secondary { background: linear-gradient(135deg, #f5f5f5 0%, var(--scroll-paper) 100%); color: #666; }
    .btn-secondary:hover { background: #e8e8e8; transform: translateY(-2px); }
    .btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 8px; }
    .btn-group { display: flex; gap: 10px; flex-wrap: wrap; }
    .filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
    .filter-item { flex: 1; min-width: 200px; }
    .table-wrapper { overflow-x: auto; border-radius: 12px; }
    .table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 1000px; }
    .table th { background: var(--scroll-paper); color: #666; font-weight: 600; padding: 12px 10px; text-align: left; border-bottom: 1px solid #eaeaea; }
    .table td { padding: 12px 10px; border-bottom: 1px solid var(--scroll-paper-dark); color: #666; }
    .table tbody tr:hover { background: var(--scroll-paper); }
    .user-info { display: flex; align-items: center; gap: 8px; }
    .user-avatar { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; background: linear-gradient(135deg, #f5f5f5 0%, var(--scroll-paper) 100%); }
    .price-value { color: #ec4899; font-weight: 600; }
    .tip-arrow { color: #ec4899; font-size: 16px; margin: 0 8px; }
    .empty-state { text-align: center; padding: 60px 20px; }
    .empty-icon { font-size: 48px; color: #cccccc; margin-bottom: 12px; }
    .empty-message { color: #999; font-size: 15px; }
    .pagination { margin-top: 24px; display: flex; justify-content: center; gap: 4px; flex-wrap: wrap; }
    .pagination a {
        display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px;
        padding: 0 8px; border: 1px solid var(--scroll-paper-dark); background: #ffffff; color: #666;
        font-size: 13px; text-decoration: none; transition: all 0.3s ease; border-radius: 8px;
    }
    .pagination a:hover { background: var(--scroll-paper); border-color: #999; color: #222222; }
    .pagination a.active { background: linear-gradient(135deg, var(--primary-teal) 0%, var(--primary-teal-dark) 100%); color: #ffffff; border-color: var(--gold-dark); font-weight: 500; }
    .pagination a i { color: #999; }
    .pagination a:hover i { color: #666; }
    ::-webkit-scrollbar { width: 4px; height: 4px; }
    ::-webkit-scrollbar-thumb { background: #dddddd; border-radius: 2px; }
    @media (max-width: 480px) {
        body { padding: 10px; }
        .card { padding: 14px; }
        .header { flex-direction: column; gap: 12px; align-items: flex-start; }
        .form-input { padding: 10px 14px; font-size: 13px; }
        .filter-item { min-width: 100%; }
        .table { font-size: 12px; }
        .table th, .table td { padding: 10px 8px; }
        .btn { padding: 10px 20px; font-size: 13px; }
        .pagination a { min-width: 32px; height: 32px; font-size: 12px; }
    }
    </style>
</head>
<body>
    <header class="header">
        <h1 class="header-title">🎁 打赏用户订单</h1>
        <a href="orders.php" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left"></i> 返回订单列表
        </a>
    </header>

    <main class="app-container">
        <!-- 统计卡片 -->
        <div class="stats-card">
            <h3><i class="fa fa-gift"></i> 打赏总金额</h3>
            <div class="amount">¥<?php echo number_format($total_amount, 2); ?></div>
            <div class="count">共 <?php echo $total_orders; ?> 笔打赏</div>
        </div>

        <div class="card">
            <div class="card-header">🔍 筛选条件</div>
            <form method="GET">
                <div class="filter-row">
                    <div class="filter-item" style="flex: 2;">
                        <div class="form-group">
                            <label class="form-label">搜索</label>
                            <input type="text" name="search" placeholder="搜索订单号、用户昵称..." 
                                   value="<?php echo htmlspecialchars($search); ?>" class="form-input">
                        </div>
                    </div>
                    <div class="filter-item">
                        <div class="form-group">
                            <label class="form-label">开始日期</label>
                            <input type="date" name="start_date" class="form-input" 
                                   value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>
                    </div>
                    <div class="filter-item">
                        <div class="form-group">
                            <label class="form-label">结束日期</label>
                            <input type="date" name="end_date" class="form-input" 
                                   value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>
                    </div>
                    <div class="filter-item">
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> 搜索</button>
                            <a href="orders-user-tip.php" class="btn btn-secondary">重置</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">📋 打赏订单列表</div>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>订单号</th>
                            <th>打赏人</th>
                            <th></th>
                            <th>被打赏人</th>
                            <th>打赏金额</th>
                            <th>备注</th>
                            <th>创建时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <div class="empty-icon">🎁</div>
                                        <div class="empty-message">暂无打赏订单</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): 
                                $from_avatar = !empty($order['from_avatar']) ? '../../assets/images/avatar/' . $order['from_avatar'] : 'https://sydj.bcorn.cn/assets/images/user.png';
                                $to_avatar = !empty($order['to_avatar']) ? '../../assets/images/avatar/' . $order['to_avatar'] : 'https://sydj.bcorn.cn/assets/images/user.png';
                            ?>
                            <tr>
                                <td><?php echo $order['id']; ?></td>
                                <td><?php echo $order['order_no']; ?></td>
                                <td>
                                    <div class="user-info">
                                        <img src="<?php echo $from_avatar; ?>" class="user-avatar">
                                        <?php echo htmlspecialchars($order['from_username'] ?? '未知用户'); ?>
                                    </div>
                                </td>
                                <td class="tip-arrow"><i class="fa fa-arrow-right"></i></td>
                                <td>
                                    <div class="user-info">
                                        <img src="<?php echo $to_avatar; ?>" class="user-avatar">
                                        <?php echo htmlspecialchars($order['to_username'] ?? '未知用户'); ?>
                                    </div>
                                </td>
                                <td class="price-value">¥<?php echo number_format($order['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($order['remark'] ?? '-'); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($order['create_time'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <a href="orders-user-tip.php?<?php echo http_build_query(array_merge(['page' => 1], array_filter(['search' => $search, 'start_date' => $start_date, 'end_date' => $end_date]))); ?>" title="第一页"><i class="fa fa-angle-double-left"></i></a>
                <a href="orders-user-tip.php?<?php echo http_build_query(array_merge(['page' => max(1, $page - 1)], array_filter(['search' => $search, 'start_date' => $start_date, 'end_date' => $end_date]))); ?>" title="上一页"><i class="fa fa-angle-left"></i></a>
                <?php 
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($end_page - $start_page < 4) {
                    if ($page < 3) { $end_page = min($total_pages, $start_page + 4); }
                    else { $start_page = max(1, $end_page - 4); }
                }
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="orders-user-tip.php?<?php echo http_build_query(array_merge(['page' => $i], array_filter(['search' => $search, 'start_date' => $start_date, 'end_date' => $end_date]))); ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <a href="orders-user-tip.php?<?php echo http_build_query(array_merge(['page' => min($total_pages, $page + 1)], array_filter(['search' => $search, 'start_date' => $start_date, 'end_date' => $end_date]))); ?>" title="下一页"><i class="fa fa-angle-right"></i></a>
                <a href="orders-user-tip.php?<?php echo http_build_query(array_merge(['page' => $total_pages], array_filter(['search' => $search, 'start_date' => $start_date, 'end_date' => $end_date]))); ?>" title="最后一页"><i class="fa fa-angle-double-right"></i></a>
            </div>
            <?php endif; ?>
        </div>
    </main>

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
