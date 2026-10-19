<?php
ob_start();
$siteTitle = '我的订单';
require_once 'header.php';

if (function_exists('cleanupExpiredLocks')) {
    cleanupExpiredLocks($pdo);
}

$partner_id = $_SESSION['partner_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_order'])) {
    $order_id = intval($_POST['order_id']);
    $status = $_GET['status'] ?? 'all';

    try {
        $check_sql = "SELECT id, hunter_user_id FROM orders WHERE id = ? AND fadan_id = ? AND order_status = 1";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$order_id, $partner_id]);
        $order_info = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order_info) {
            $_SESSION['error_msg'] = "订单不存在或无权操作";
            header("Location: orders.php?status=$status");
            exit();
        }

        if ($order_info['hunter_user_id']) {
            $_SESSION['error_msg'] = "订单已被接单，无法取消";
            header("Location: orders.php?status=$status");
            exit();
        }

        $update_sql = "UPDATE orders SET order_status = 5, cancel_time = NOW() WHERE id = ?";
        $update_stmt = $pdo->prepare($update_sql);

        if ($update_stmt->execute([$order_id])) {
            $_SESSION['success_msg'] = "订单 #$order_id 取消待审核中";
        } else {
            $_SESSION['error_msg'] = "取消订单时出错";
        }

        header("Location: orders.php?status=$status");
        exit();

    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "数据库错误: " . $e->getMessage();
        header("Location: orders.php?status=$status");
        exit();
    }
}

$statusText = [
    1 => '待接单', 2 => '处理中', 3 => '待结算', 4 => '已完成',
    5 => '取消待审核中', 6 => '客服介入', 7 => '已撤销', 8 => '已删除', 9 => '已关闭'
];

$statusClass = [
    1 => 'status-pending', 2 => 'status-processing', 3 => 'status-processing',
    4 => 'status-completed', 5 => 'status-cancelled', 6 => 'status-processing',
    7 => 'status-cancelled', 9 => 'status-cancelled'
];

$lockStatusText = [0 => '未锁定', 1 => '锁定中'];
$lockStatusClass = [0 => 'lock-status-unlocked', 1 => 'lock-status-locked'];

$status = $_GET['status'] ?? 'all';
$statuses = ['all', 'pending', 'processing', 'completed', 'cancelled'];
$status_map = ['1' => 'pending', '2' => 'processing', '3' => 'processing', '4' => 'completed', '5' => 'cancelled', '6' => 'processing'];

if (isset($status_map[$status])) {
    $status = $status_map[$status];
}

if (!in_array($status, $statuses)) {
    $status = 'all';
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$base_sql = "SELECT o.*,
               u.username as issuer_username, u.phone as issuer_phone, u.avatar as issuer_avatar,
               hu.username as hunter_username, hu.phone as hunter_phone, hu.avatar as hunter_avatar,
               lu.username as lock_username
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN users hu ON o.hunter_user_id = hu.id
        LEFT JOIN users lu ON o.lock_user_id = lu.id
        WHERE o.fadan_id = ?";

$count_sql = "SELECT COUNT(*) as total FROM orders o WHERE o.fadan_id = ?";

if ($status != 'all') {
    if ($status == 'pending') {
        $count_sql .= " AND o.order_status = ?";
    } elseif ($status == 'processing') {
        $count_sql .= " AND o.order_status IN (2,3,6)";
    } elseif ($status == 'completed') {
        $count_sql .= " AND o.order_status = ?";
    } else {
        $count_sql .= " AND o.order_status = ?";
    }
}

try {
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->bindValue(1, $partner_id, PDO::PARAM_STR);
    if ($status != 'all') {
        if ($status == 'pending') {
            $count_stmt->bindValue(2, 1, PDO::PARAM_INT);
        } elseif ($status == 'completed') {
            $count_stmt->bindValue(2, 4, PDO::PARAM_INT);
        } elseif ($status == 'cancelled') {
            $count_stmt->bindValue(2, 5, PDO::PARAM_INT);
        }
    }
    $count_stmt->execute();
    $total_orders = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_orders / $per_page);

    $sql = $base_sql;
    if ($status != 'all') {
        if ($status == 'pending') {
            $sql .= " AND o.order_status = ?";
        } elseif ($status == 'processing') {
            $sql .= " AND o.order_status IN (2,3,6)";
        } elseif ($status == 'completed') {
            $sql .= " AND o.order_status = ?";
        } else {
            $sql .= " AND o.order_status = ?";
        }
    }
    $sql .= " ORDER BY o.create_time DESC LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $partner_id, PDO::PARAM_STR);
    $param_index = 2;
    if ($status != 'all') {
        if ($status == 'pending') {
            $stmt->bindValue($param_index++, 1, PDO::PARAM_INT);
        } elseif ($status == 'completed') {
            $stmt->bindValue($param_index++, 4, PDO::PARAM_INT);
        } elseif ($status == 'cancelled') {
            $stmt->bindValue($param_index++, 5, PDO::PARAM_INT);
        }
    }
    $stmt->bindValue($param_index++, $per_page, PDO::PARAM_INT);
    $stmt->bindValue($param_index, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['error_msg'] = "数据库查询错误: " . $e->getMessage();
    $orders = [];
    $total_orders = 0;
    $total_pages = 1;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $siteTitle; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--primary-teal:#3baaa3;--primary-teal-dark:#2a8a84;--gold:#c9a45c;--gold-dark:#a8843d}
        body{font-family:'Noto Sans SC',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:url('../assets/1.png');background-size:cover;background-position:center;background-repeat:no-repeat;background-attachment:fixed;color:#333;line-height:1.5;-webkit-font-smoothing:antialiased;min-height:100vh;position:relative}
        .app-container{max-width:100%;margin:0 auto;padding:0 12px 80px;position:relative;z-index:1}
        .header{padding:12px;background:rgba(255,255,255,0.8);position:sticky;top:0;z-index:100;box-shadow:0 2px 10px rgba(0,0,0,.08)}
        .header-content{display:flex;align-items:center;justify-content:space-between;gap:12px}
        .header-title{font-size:18px;font-weight:700;color:#333;flex-shrink:0}
        .header-btn{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#ffcc00 0%,#ff9900 100%);border:none;color:#000;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px;text-decoration:none;transition:all .2s ease;box-shadow:0 3px 10px rgba(255,204,0,.4)}
        .header-btn:hover{transform:scale(1.1)}
        .page-title{margin:20px 0 16px;font-size:18px;font-weight:700;color:#333;display:flex;align-items:center;gap:8px}
        .page-title i{color:#ffcc00}
        .alert{padding:14px 16px;border-radius:12px;margin-bottom:16px;font-size:14px;display:flex;align-items:center;gap:8px;background:rgba(255,255,255,0.9);border:1px solid #e0e0e0}
        .alert-success{background:rgba(232,245,233,0.9)!important;border-color:#c8e6c9!important;color:#2e7d32}
        .alert-error{background:rgba(255,235,238,0.9)!important;border-color:#ffcdd2!important;color:#c62828}
        .status-tabs{display:flex;gap:8px;margin-bottom:16px;overflow-x:auto;padding-bottom:4px}
        .status-tab{padding:10px 18px;background:rgba(255,255,255,0.8);border:1px solid #e0e0e0;border-radius:25px;font-size:13px;font-weight:600;color:#666;text-decoration:none;white-space:nowrap;transition:all .3s ease;box-shadow:0 2px 8px rgba(0,0,0,.05)}
        .status-tab:hover{border-color:#ffcc00;transform:translateY(-2px);box-shadow:0 4px 12px rgba(255,204,0,.2)}
        .status-tab.active{background:linear-gradient(135deg,#ffcc00 0%,#ff9900 100%);color:#000;border-color:#ffcc00;box-shadow:0 4px 15px rgba(255,204,0,.3)}
        .orders-list{background:rgba(255,255,255,0.8);border-radius:16px;padding:16px;margin-bottom:16px;box-shadow:0 4px 15px rgba(0,0,0,.08)}
        .order-card{background:rgba(255,255,255,0.95);border:1px solid #e0e0e0;border-radius:12px;padding:16px;margin-bottom:12px;transition:all .3s ease}
        .order-card:last-child{margin-bottom:0}
        .order-card:hover{border-color:#ffcc00;transform:translateY(-2px);box-shadow:0 4px 15px rgba(255,204,0,.2)}
        .order-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #f0f0f0}
        .order-id{font-size:15px;font-weight:700;color:#333}
        .status-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600}
        .status-pending{background:#fff3e0;color:#f57c00}
        .status-processing{background:#e3f2fd;color:#1976d2}
        .status-completed{background:#e8f5e9;color:#388e3c}
        .status-cancelled{background:#ffebee;color:#d32f2f}
        .lock-status-unlocked{background:#e8f5e9;color:#388e3c}
        .lock-status-locked{background:#fff3e0;color:#f57c00}
        .info-row{display:flex;margin-bottom:8px;font-size:13px}
        .info-label{width:70px;color:#999;flex-shrink:0}
        .info-value{flex:1;color:#333}
        .price-value{color:#ff4444;font-weight:700}
        .order-actions{display:flex;gap:8px;margin-top:12px;padding-top:12px;border-top:1px solid #f0f0f0}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 16px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;transition:all .2s ease}
        .btn-primary{background:linear-gradient(135deg,#ffcc00 0%,#ff9900 100%);color:#000;box-shadow:0 2px 8px rgba(255,204,0,.3)}
        .btn-primary:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(255,204,0,.4)}
        .btn-secondary{background:#f5f5f5;color:#333}
        .btn-secondary:hover{background:#e0e0e0}
        .btn-danger{background:#ffebee;color:#d32f2f}
        .btn-danger:hover{background:#ffcdd2}
        .empty-state{text-align:center;padding:60px 20px;background:rgba(255,255,255,0.8);border-radius:16px;border:1px solid #e0e0e0}
        .empty-icon{font-size:48px;color:#ccc;margin-bottom:16px}
        .empty-message{font-size:16px;font-weight:600;color:#666}
        .pagination{display:flex;justify-content:center;gap:8px;margin-top:20px}
        .page-btn{display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,0.8);border:1px solid #e0e0e0;color:#333;font-size:14px;text-decoration:none;transition:all .2s ease}
        .page-btn:hover{background:#ffcc00;color:#000;border-color:#ffcc00}
        .page-btn.active{background:linear-gradient(135deg,#ffcc00 0%,#ff9900 100%);color:#000;border-color:#ffcc00}
        footer p{font-size:22px !important;font-weight:600;background:linear-gradient(135deg,var(--gold) 0%,var(--gold-dark) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;text-align:center;margin:30px 0 20px;letter-spacing:6px}
        @media (max-width:480px){.status-tabs{gap:6px}.status-tab{padding:8px 14px;font-size:12px}.info-label{width:60px;font-size:12px}}
    </style>
    <link rel="stylesheet" href="../assets/css/theme-v2.css">
</head>
<body>
<!-- V2 加载动画 -->
<div class="v2-loader-overlay" id="v2Loader">
    <div class="v2-loader-logo-wrap">
        <div class="v2-loader-ring"></div>
        <div class="v2-loader-ring"></div>
        <div class="v2-loader-logo">小</div>
    </div>
    <div class="v2-loader-text">
        <span>加</span><span>载</span><span>中</span><span>...</span>
    </div>
    <div class="v2-loader-progress">
        <div class="v2-loader-progress-bar"></div>
    </div>
</div>

    <header class="header">
        <div class="header-content">
            <div class="header-title">我的订单</div>
            <a href="order_create.php" class="header-btn">
                <i class="fas fa-plus"></i>
            </a>
        </div>
    </header>

    <main class="app-container">
        <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
        </div>
        <?php endif; ?>

        <div class="status-tabs">
            <a href="orders.php?status=all" class="status-tab <?php echo $status == 'all' ? 'active' : ''; ?>">全部</a>
            <a href="orders.php?status=pending" class="status-tab <?php echo $status == 'pending' ? 'active' : ''; ?>">待接单</a>
            <a href="orders.php?status=processing" class="status-tab <?php echo $status == 'processing' ? 'active' : ''; ?>">处理中</a>
            <a href="orders.php?status=completed" class="status-tab <?php echo $status == 'completed' ? 'active' : ''; ?>">已完成</a>
            <a href="orders.php?status=cancelled" class="status-tab <?php echo $status == 'cancelled' ? 'active' : ''; ?>">已取消</a>
        </div>

        <?php if (empty($orders)): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-inbox"></i></div>
            <div class="empty-message">暂无订单记录</div>
        </div>
        <?php else: ?>
        <div class="orders-list">
            <?php foreach ($orders as $order): ?>
            <div class="order-card">
                <div class="order-header">
                    <div class="order-id">#<?php echo $order['id']; ?></div>
                    <div class="status-badge <?php echo $statusClass[$order['order_status']]; ?>">
                        <?php echo $statusText[$order['order_status']]; ?>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-label">订单名称</div>
                    <div class="info-value"><?php echo htmlspecialchars($order['product_name']); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">分类</div>
                    <div class="info-value"><?php echo htmlspecialchars($order['category_name'] ?? '无'); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">价格</div>
                    <div class="info-value price-value">¥<?php echo number_format($order['price'], 2); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">打手</div>
                    <div class="info-value"><?php echo $order['hunter_username'] ? htmlspecialchars($order['hunter_username']) : '未接单'; ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">创建时间</div>
                    <div class="info-value"><?php echo date('Y-m-d H:i', strtotime($order['create_time'])); ?></div>
                </div>

                <?php if ($order['order_status'] == 1): ?>
                <div class="info-row">
                    <div class="info-label">锁单状态</div>
                    <div class="info-value">
                        <span class="status-badge <?php echo $lockStatusClass[$order['lock_status']]; ?>">
                            <?php echo $lockStatusText[$order['lock_status']]; ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>

                <div class="order-actions">
                    <a href="order_view.php?id=<?php echo $order['id']; ?>" class="btn btn-secondary">
                        <i class="fas fa-eye"></i> 查看
                    </a>

                    <?php if ($order['order_status'] == 1): ?>

                    <button type="button" class="btn btn-danger" onclick="handleCancel(<?php echo $order['id']; ?>, <?php echo $order['hunter_user_id'] ? 'true' : 'false'; ?>)">
                        <i class="fas fa-times"></i> 取消
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="orders.php?status=<?php echo $status; ?>&page=<?php echo $page - 1; ?>" class="page-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="orders.php?status=<?php echo $status; ?>&page=<?php echo $i; ?>" class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
            <a href="orders.php?status=<?php echo $status; ?>&page=<?php echo $page + 1; ?>" class="page-btn">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </main>

    <footer>
        <p>到底啦！</p>
    </footer>

    <?php require 'footer.php'; ?>

    <script>
    function handleCancel(orderId, isAssigned) {
        if (isAssigned) {
            alert("订单已被接单，无法取消");
            return;
        }

        if (confirm('确定要取消订单 #' + orderId + ' 吗？')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'cancel_order';
            input.value = '1';

            const orderIdInput = document.createElement('input');
            orderIdInput.type = 'hidden';
            orderIdInput.name = 'order_id';
            orderIdInput.value = orderId;

            form.appendChild(input);
            form.appendChild(orderIdInput);
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>