<?php
require_once '../includes/session_manager.php';
require_once '../config.php';

// 检查用户是否登录
if (!is_h5_logged_in()) {
    header("Location: login.php");
    exit;
}

// 获取当前用户信息
$current_user_id = get_h5_user_id();

// 获取筛选状态
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';

try {
    // 数据库连接
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", 
                 DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // 构建查询条件
    $where = "WHERE o.user_id = ?";
    $params = [$current_user_id];
    
    if ($filter_status !== 'all') {
        $status_map = [
            'pending' => [1],      // 待接单
            'processing' => [2,3], // 服务中
            'completed' => [4],    // 已完成
        ];
        
        if (isset($status_map[$filter_status])) {
            $placeholders = implode(',', array_fill(0, count($status_map[$filter_status]), '?'));
            $where .= " AND o.order_status IN ($placeholders)";
            $params = array_merge($params, $status_map[$filter_status]);
        }
    }
    
    // 获取订单总数
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM orders o $where");
    $stmt->execute($params);
    $totalOrders = $stmt->fetch()['total'];
    
    // 分页处理
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $perPage = 10;
    $offset = ($page - 1) * $perPage;
    $totalPages = ceil($totalOrders / $perPage);
    
    // 修复点：直接拼接LIMIT和OFFSET值，而不是使用参数绑定
    $limitClause = "LIMIT $perPage OFFSET $offset";
    
    // 查询订单列表
    $stmt = $db->prepare("
        SELECT o.*, 
               u.nickname as hunter_username, 
               u.avatar as hunter_avatar,
               p.images as product_images,
               p.name as product_name
        FROM orders o
        LEFT JOIN h5user u ON o.hunter_user_id = u.id
        LEFT JOIN products p ON o.product_id = p.id
        $where
        ORDER BY o.create_time DESC
        $limitClause
    ");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("数据库错误: ".$e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>订单列表</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=PingFang+SC:wght@300;400;500;700&display=swap" rel="stylesheet">
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: url('../assets/1.png');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        background-attachment: fixed;
        color: #1d1d1f;
        line-height: 1.5;
        -webkit-font-smoothing: antialiased;
        padding-top: 56px;
        padding-bottom: 80px;
        animation: fadeInUp 0.6s ease-out;
        min-height: 100vh;
        position: relative;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .app-container {
        max-width: 100%;
        margin: 0 auto;
        padding: 0 12px;
        position: relative;
        z-index: 1;
    }

    /* 顶部导航 */
    .page-header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 56px;
        background: rgba(255, 255, 255, 0.5);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .back-btn {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.5);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: #1d1d1f;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .back-btn:hover {
        background: rgba(255, 255, 255, 0.7);
        transform: translateY(-50%) scale(1.1);
    }

    .back-btn:active {
        transform: translateY(-50%) scale(0.95);
    }

    .page-header div:last-child {
        font-size: 16px;
        font-weight: 600;
        color: #000000;
    }

    /* 状态筛选 */
    .status-tabs {
        display: flex;
        overflow-x: auto;
        padding: 12px 0;
        background: transparent;
        scrollbar-width: none;
        gap: 8px;
        margin-bottom: 12px;
    }

    .status-tabs::-webkit-scrollbar {
        display: none;
    }

    .status-tab {
        flex: 0 0 auto;
        padding: 8px 20px;
        font-size: 14px;
        font-weight: 500;
        color: #666666;
        white-space: nowrap;
        transition: all 0.2s ease;
        text-decoration: none;
        border-radius: 24px;
        background: rgba(255, 255, 255, 0.5);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .status-tab:hover {
        border-color: rgba(0, 0, 0, 0.2);
        background: rgba(255, 255, 255, 0.7);
        transform: translateY(-2px);
    }

    .status-tab.active {
        background: #000000;
        border-color: #000000;
        color: #ffffff;
        transform: translateY(-2px);
    }

    /* 订单容器 */
    .order-container {
        min-height: calc(100vh - 200px);
    }

    /* 空状态 */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: rgba(255, 255, 255, 0.5);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        border-radius: 16px;
        margin: 20px 0;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .empty-icon {
        font-size: 48px;
        color: #cccccc;
        margin-bottom: 12px;
    }

    .empty-text {
        color: #999999;
        font-size: 15px;
    }

    /* 订单卡片 */
    .order-card {
        background: rgba(255, 255, 255, 0.5);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        border-radius: 16px;
        margin-bottom: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        border: 1px solid rgba(255, 255, 255, 0.3);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .order-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        background: rgba(255, 255, 255, 0.7);
    }

    .order-header {
        padding: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .order-no {
        font-size: 12px;
        color: #999999;
    }

    .order-status {
        font-size: 12px;
        font-weight: 600;
        color: #000000;
    }

    .order-body {
        padding: 12px;
    }

    .product-item {
        display: flex;
        margin-bottom: 12px;
    }

    .product-image {
        width: 80px;
        height: 80px;
        border-radius: 12px;
        object-fit: cover;
        margin-right: 12px;
        flex-shrink: 0;
        background: rgba(0, 0, 0, 0.05);
    }

    .product-info {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 80px;
    }

    .product-name {
        font-size: 15px;
        font-weight: 500;
        color: #000000;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        margin-bottom: 8px;
        line-height: 1.4;
    }

    .product-price {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: auto;
    }

    .price {
        font-size: 18px;
        font-weight: 600;
        color: #ff4444;
    }

    .price span {
        font-size: 11px;
        font-weight: 400;
        margin-right: 2px;
    }

    .quantity {
        font-size: 13px;
        color: #999999;
    }

    .order-footer {
        padding: 12px;
        border-top: 1px solid rgba(0, 0, 0, 0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
    }

    .total-amount {
        font-size: 14px;
        color: #666666;
    }

    .total-price {
        font-size: 18px;
        font-weight: 600;
        color: #ff4444;
        margin-left: 8px;
    }

    .tip-amount {
        font-size: 12px;
        color: #ff9900;
        margin-left: 4px;
    }

    .action-btn {
        padding: 8px 18px;
        font-size: 13px;
        font-weight: 500;
        border-radius: 20px;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        background: rgba(255, 255, 255, 0.5);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: #1d1d1f;
    }

    .action-btn:hover {
        background: rgba(255, 255, 255, 0.7);
        border-color: rgba(0, 0, 0, 0.2);
        transform: translateY(-2px);
    }

    /* 响应式调整 */
    @media (max-width: 480px) {
        body {
            padding-top: 52px;
            padding-bottom: 75px;
        }

        .page-header {
            height: 52px;
        }

        .back-btn {
            left: 12px;
            width: 36px;
            height: 36px;
        }

        .page-header div:last-child {
            font-size: 15px;
        }

        .product-image {
            width: 70px;
            height: 70px;
            margin-right: 12px;
        }

        .product-name {
            font-size: 14px;
        }

        .price {
            font-size: 16px;
        }
    }
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

    <!-- 顶部导航 -->
    <div class="page-header">
        <a href="javascript:history.back()" class="back-btn">
            <i class="fas fa-chevron-left"></i>
        </a>
        <div>订单列表</div>
        <div style="width: 40px;"></div> <!-- 占位保持对称 -->
    </div>

    <main class="app-container">
        <!-- 状态筛选 -->
        <div class="status-tabs">
            <a href="?status=all" class="status-tab <?= $filter_status === 'all' ? 'active' : '' ?>">
                全部
            </a>
            <a href="?status=pending" class="status-tab <?= $filter_status === 'pending' ? 'active' : '' ?>">
                待接单
            </a>
            <a href="?status=processing" class="status-tab <?= $filter_status === 'processing' ? 'active' : '' ?>">
                服务中
            </a>
            <a href="?status=completed" class="status-tab <?= $filter_status === 'completed' ? 'active' : '' ?>">
                已完成
            </a>
        </div>

        <div class="order-container">
            <?php if (empty($orders)): ?>
                <!-- 空状态 -->
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <div class="empty-text">暂无订单</div>
                </div>
            <?php else: ?>
                <!-- 订单列表 -->
                <?php foreach ($orders as $order): 
                    // 获取商品图片
                    $productImages = json_decode($order['product_images'] ?? '[]', true);
                    $orderImage = !empty($productImages) ? '../' . $productImages[0] : '../assets/images/products/default-product.png';
                        
                    // 订单状态
                    $statusText = '';
                    if ($order['order_status'] == 1) {
                        $statusText = '待接单';
                    } elseif ($order['order_status'] == 2) {
                        $statusText = '服务中';
                    } elseif ($order['order_status'] == 3) {
                        $statusText = '服务中';
                    } elseif ($order['order_status'] == 4) {
                        $statusText = '已完成';
                    } elseif ($order['order_status'] == 5) {
                        $statusText = '售后中';
                    } elseif ($order['order_status'] == 7) {
                        $statusText = '已退款';
                    }
                ?>
                <a href="order_detail.php?id=<?= $order['id'] ?>" class="order-card" style="text-decoration: none;">
                    <!-- 订单头部 -->
                    <div class="order-header">
                        <span class="order-no">订单号: <?= htmlspecialchars($order['order_no']) ?></span>
                        <span class="order-status"><?= $statusText ?></span>
                    </div>
                    
                    <!-- 订单内容 -->
                    <div class="order-body">
                        <div class="product-item">
                            <img src="<?= htmlspecialchars($orderImage) ?>" 
                                 alt="<?= htmlspecialchars($order['product_name']) ?>" 
                                 class="product-image">
                            <div class="product-info">
                                <h3 class="product-name"><?= htmlspecialchars($order['product_name']) ?></h3>
                                <div class="product-price">
                                    <span class="price"><span>¥</span><?= number_format($order['price'], 2) ?></span>
                                    <span class="quantity">x1</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 订单底部 -->
                    <div class="order-footer">
                        <div>
                            <span class="total-amount">共1件商品 合计:</span>
                            <span class="total-price">¥<?= number_format($order['price'], 2) ?></span>
                            <?php 
                            $tipAmount = isset($order['tip_amount']) ? floatval($order['tip_amount']) : 0;
                            if ($tipAmount > 0): 
                            ?>
                            <span class="tip-amount">（含打赏¥<?= number_format($tipAmount, 2) ?>）</span>
                            <?php endif; ?>
                        </div>
                        <span class="action-btn">
                            查看详情
                        </span>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 图片加载失败处理
        document.querySelectorAll('img').forEach(img => {
            img.onerror = function() {
                if (this.classList.contains('product-image')) {
                    this.src = '../assets/images/products/default-product.png';
                }
            };
        });
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
<?php include 'footer.php'; ?>