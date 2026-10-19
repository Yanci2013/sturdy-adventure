<?php
// 开启错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 开启输出缓冲
if (!ob_get_level()) {
    ob_start();
}

// 安全启动Session
session_start();
require '../config.php';
require '../includes/common.php';

// 初始化变量
$site_logo = "assets/images/logo.png";
$todaySalesRank = [];

try {
    // 数据库连接
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", 
                 DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // 获取网站Logo
    $stmt = $db->query("SELECT site_logo FROM settings WHERE id = 1");
    $logo_result = $stmt->fetch();
    if($logo_result && !empty($logo_result['site_logo'])) {
        $site_logo = $logo_result['site_logo'];
    }
    
    // 获取今日销量排行
    $today = date('Y-m-d');
    
    try {
        $sql = "SELECT 
                    p.id, 
                    p.name, 
                    COUNT(o.id) as today_sales,
                    p.images
                FROM 
                    products p
                LEFT JOIN 
                    orders o ON p.id = o.product_id
                WHERE 
                    DATE(o.create_time) = ?
                    AND o.order_status IN (1, 2, 3)
                GROUP BY 
                    p.id
                ORDER BY 
                    today_sales DESC
                LIMIT 20";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$today]);
        $todaySalesRank = $stmt->fetchAll();
    } catch (PDOException $e) {
        $todaySalesRank = [];
    }
    
    // 如果今日没有订单，获取所有商品
    if(empty($todaySalesRank)) {
        $sql = "SELECT 
                    id, 
                    name, 
                    0 as today_sales,
                    images
                FROM 
                    products 
                ORDER BY 
                    id DESC
                LIMIT 20";
        
        $stmt = $db->query($sql);
        $todaySalesRank = $stmt->fetchAll();
    }
    
} catch(PDOException $e) {
    error_log("数据库错误: ".$e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>排行榜</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-bg: #fef9e7;
            --card-bg: rgba(255,255,255,0.8);
            --text-primary: #333333;
            --text-secondary: #666666;
            --text-tertiary: #999999;
            --accent-gold: #d4a84b;
        }

        body {
            font-family: 'Noto Sans SC', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: url('../assets/1.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: var(--text-primary);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            padding-bottom: 80px;
        }

        /* 顶部导航 */
        .page-header {
            padding: 16px 20px;
            background: rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .back-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--card-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: var(--text-primary);
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .page-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            flex: 1;
            text-align: center;
            margin-right: 52px;
        }

        /* 主内容 */
        .main-content {
            padding: 0 16px;
        }

        /* 排行榜卡片 */
        .rank-card {
            background: rgba(255,255,255,0.8);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .rank-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f5f5f5;
        }

        .rank-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .rank-date {
            font-size: 13px;
            color: var(--text-tertiary);
        }

        /* 排行榜列表 */
        .rank-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .rank-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: #fafafa;
            border-radius: 12px;
            text-decoration: none;
            color: inherit;
            transition: background 0.2s ease;
        }

        .rank-item:active {
            background: #f0f0f0;
        }

        .rank-number {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--text-tertiary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .rank-number.top1 {
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
        }

        .rank-number.top2 {
            background: linear-gradient(135deg, #C0C0C0 0%, #A0A0A0 100%);
        }

        .rank-number.top3 {
            background: linear-gradient(135deg, #CD7F32 0%, #B87333 100%);
        }

        .product-image {
            width: 56px;
            height: 56px;
            border-radius: 8px;
            object-fit: cover;
            background: #f5f5f5;
            flex-shrink: 0;
        }

        .product-info {
            flex: 1;
            min-width: 0;
        }

        .product-name {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 4px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .product-sales {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .order-btn {
            padding: 8px 16px;
            background: linear-gradient(135deg, #d4a84b 0%, #f5c842 100%);
            color: #fff;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            flex-shrink: 0;
        }

        /* 空状态 */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: rgba(255,255,255,0.8);
            border-radius: 16px;
            margin: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .empty-icon {
            font-size: 48px;
            margin-bottom: 12px;
        }

        .empty-text {
            font-size: 15px;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }

        .empty-subtext {
            font-size: 13px;
            color: var(--text-tertiary);
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
        <a href="my.php" class="back-btn">
            <i class="fas fa-chevron-left"></i>
        </a>
        <h1 class="page-title">排行榜</h1>
    </div>

    <!-- 主内容 -->
    <main class="main-content">
        <?php if(!empty($todaySalesRank)): ?>
            <div class="rank-card">
                <div class="rank-header">
                    <div class="rank-title">今日热销榜</div>
                    <div class="rank-date"><?php echo date('Y年m月d日'); ?></div>
                </div>
                <div class="rank-list">
                    <?php foreach($todaySalesRank as $index => $product): ?>
                        <?php 
                            $rankClass = '';
                            if ($index == 0) $rankClass = 'top1';
                            elseif ($index == 1) $rankClass = 'top2';
                            elseif ($index == 2) $rankClass = 'top3';
                            
                            $productImage = 'assets/images/default-product.png';
                            if(!empty($product['images'])) {
                                $images = json_decode($product['images'], true);
                                if(!empty($images) && is_array($images)) {
                                    $productImage = $images[0];
                                }
                            }
                        ?>
                        <div class="rank-item">
                            <div class="rank-number <?php echo $rankClass; ?>">
                                <?php echo $index + 1; ?>
                            </div>
                            <img src="../<?php echo htmlspecialchars($productImage); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                            <div class="product-info">
                                <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div class="product-sales">销量: <?php echo $product['today_sales']; ?></div>
                            </div>
                            <a href="product_detail.php?id=<?php echo $product['id']; ?>" class="order-btn">立即点单</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">🏆</div>
                <div class="empty-text">暂无销量数据</div>
                <div class="empty-subtext">今日还没有订单，稍后再来看看吧</div>
            </div>
        <?php endif; ?>
    </main>

    <?php include 'footer.php'; ?>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
