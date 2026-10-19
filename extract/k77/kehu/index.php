<?php
require_once '../includes/session_manager.php';

// 初始化变量
$categories = [];
$groupedProducts = [];
$carousel1 = [];
$carousel2 = [];
$announcement = "<h3>🎉 欢迎加入电竞</h3><p>感谢您成为我们平台的一员！这里汇集了顶尖游戏陪玩大神，随时为您提供专业的游戏体验。</p><ul><li>🎮 <strong>热门游戏全覆盖</strong>：香肠派对等</li><li>⭐ <strong>专业陪玩团队</strong>：千名经过认证的资深玩家</li><li>🎁 <strong>新人专享福利</strong>：首单立减20%优惠</li><li>🔒 <strong>安全交易保障</strong>：平台担保，放心无忧</li></ul><p>祝您游戏愉快，战绩节节攀升！</p>";
$announcement_title = "🎉 欢迎来到电竞";
$announcement_id = 0;
$show_announcement_modal = false;
$site_logo = "assets/images/logo.png";
$site_name = "小熠网络";
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$search_keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$has_db_connection = false;
$customer_service_link = "#";
$h5_announcement_text = "";
$customer_services = [];

// 加载配置
try {
    require '../config.php';
} catch (Exception $e) {
    error_log('Failed to load config: ' . $e->getMessage());
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'xiaoyudianjing');
}

function is_logged_in() {
    return is_h5_logged_in();
}

$db = null;
try {
    if (in_array('mysql', PDO::getAvailableDrivers())) {
        $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", 
                     DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $has_db_connection = true;
    } else {
        error_log('MySQL PDO driver not available');
        $has_db_connection = false;
    }

    if ($has_db_connection) {
        // 查询设置（site_title, site_logo, h5_announcement 等）
        try {
            $stmt = $db->query("SELECT site_title, site_logo, enterprise_customer_link, h5_announcement, announcement FROM settings WHERE id = 1");
            $settings_result = $stmt->fetch();
            if($settings_result) {
                if(!empty($settings_result['site_title'])) {
                    $site_name = $settings_result['site_title'];
                }
                if(!empty($settings_result['site_logo'])) {
                    $site_logo = $settings_result['site_logo'];
                }
                $customer_service_link = $settings_result['enterprise_customer_link'] ?: '#';
                $h5_announcement_text = $settings_result['h5_announcement'] ?: '';
                if(!empty($settings_result['announcement'])) {
                    $announcement = $settings_result['announcement'];
                }
            }
        } catch (Exception $e) {
            error_log('Error loading settings: ' . $e->getMessage());
        }

        // 获取客服列表
        try {
            $customer_stmt = $db->query("SELECT * FROM customer_services WHERE status = 1 ORDER BY id DESC");
            $customer_services = $customer_stmt->fetchAll();
        } catch (Exception $e) {
            error_log('Error loading customer services: ' . $e->getMessage());
        }

        // 获取抽奖设置
        $lottery_enabled = false;
        try {
            $lottery_stmt = $db->query("SELECT enabled FROM lottery_settings WHERE id = 1");
            $lottery_result = $lottery_stmt->fetch();
            if ($lottery_result) {
                $lottery_enabled = $lottery_result['enabled'] == 1;
            }
        } catch (Exception $e) {
            error_log('Error loading lottery settings: ' . $e->getMessage());
            $lottery_enabled = false;
        }

        // 获取快捷入口
        $quick_entries = [];
        try {
            $quick_stmt = $db->query("SELECT * FROM quick_entries WHERE status = 1 ORDER BY sort_order ASC, id DESC");
            $quick_entries = $quick_stmt->fetchAll();
        } catch (Exception $e) {
            error_log('Error loading quick entries: ' . $e->getMessage());
            $quick_entries = [];
        }

        // 获取公告
        try {
            $stmt = $db->query("SELECT id, title, content FROM announcements WHERE status = 1 ORDER BY created_at DESC LIMIT 1");
            $announcement_data = $stmt->fetch();
            if($announcement_data) {
                $announcement_id = $announcement_data['id'];
                $announcement_title = $announcement_data['title'];
                $announcement = $announcement_data['content'];
            }
        } catch (Exception $e) {
            error_log('Error querying announcements: ' . $e->getMessage());
        }

        // 处理公告查看记录
        if (is_logged_in() && $announcement_id > 0 && get_h5_user_id()) {
            try {
                $stmt = $db->prepare("INSERT IGNORE INTO user_announcements (user_id, announcement_id, viewed_at) VALUES (?, ?, NOW())");
                $stmt->execute([get_h5_user_id(), $announcement_id]);
            } catch (Exception $e) {
                error_log('Error updating user announcement: ' . $e->getMessage());
            }
            $show_announcement_modal = true;
        }

        // 获取双轮播图（按 position 分组）
        try {
            $check = $db->query("SHOW COLUMNS FROM carousel LIKE 'position'");
            $has_position = $check->rowCount() > 0;
        } catch (Exception $e) {
            $has_position = false;
        }

        try {
            if ($has_position) {
                $carousel1 = $db->query("SELECT image_path, link_url FROM carousel WHERE position = 'carousel_1' ORDER BY sort_order DESC, id DESC LIMIT 5")->fetchAll();
                $carousel2 = $db->query("SELECT image_path, link_url FROM carousel WHERE position = 'carousel_2' ORDER BY sort_order DESC, id DESC LIMIT 5")->fetchAll();
            } else {
                $all = $db->query("SELECT image_path, link_url FROM carousel ORDER BY sort_order DESC, id DESC LIMIT 5")->fetchAll();
                $carousel2 = $all;
            }
        } catch (Exception $e) {
            error_log('Error loading carousel: ' . $e->getMessage());
        }

        // 获取分类
        try {
            $categories = $db->query("SELECT * FROM product_categories WHERE level = 1 AND status = 1 ORDER BY sort_order DESC, id DESC")->fetchAll();
        } catch (Exception $e) {
            error_log('Error loading categories: ' . $e->getMessage());
            $categories = [];
        }

        // 获取商品
        try {
            if (!empty($search_keyword)) {
                $sql = "SELECT p.*, pc.name AS category_name, pc.id AS category_id 
                        FROM products p
                        LEFT JOIN product_categories pc ON p.category_id = pc.id
                        WHERE p.status = 1 AND (p.name LIKE ? OR p.chinese_name LIKE ?)
                        ORDER BY p.sort_order DESC, p.created_at DESC LIMIT 40";
                $stmt = $db->prepare($sql);
                $search_param = "%{$search_keyword}%";
                $stmt->execute([$search_param, $search_param]);
                $products = $stmt->fetchAll();

                if (!empty($products)) {
                    $groupedProducts['search'] = [
                        'category_name' => '搜索结果',
                        'sub_categories' => [
                            [
                                'sub_category_name' => '包含 "' . $search_keyword . '" 的商品',
                                'products' => $products
                            ]
                        ]
                    ];
                }
            } elseif ($category_id > 0) {
                $stmt = $db->prepare("SELECT level, parent_id, name FROM product_categories WHERE id = ?");
                $stmt->execute([$category_id]);
                $categoryInfo = $stmt->fetch();

                if ($categoryInfo) {
                    if ($categoryInfo['level'] == 1) {
                        $subCategories = $db->prepare("SELECT id, name FROM product_categories WHERE parent_id = ? AND status = 1 ORDER BY sort_order DESC, id DESC");
                        $subCategories->execute([$category_id]);
                        $subCategories = $subCategories->fetchAll();

                        foreach ($subCategories as $subCategory) {
                            $sql = "SELECT p.*, pc.name AS category_name, pc.id AS category_id 
                                    FROM products p
                                    LEFT JOIN product_categories pc ON p.category_id = pc.id
                                    WHERE p.status = 1 AND p.category_id = ?
                                    ORDER BY p.sort_order DESC, p.created_at DESC LIMIT 20";

                            $stmt = $db->prepare($sql);
                            $stmt->execute([$subCategory['id']]);
                            $products = $stmt->fetchAll();

                            if (!empty($products)) {
                                if (!isset($groupedProducts[$category_id])) {
                                    $groupedProducts[$category_id] = [
                                        'category_name' => $categoryInfo['name'],
                                        'sub_categories' => []
                                    ];
                                }

                                $groupedProducts[$category_id]['sub_categories'][] = [
                                    'sub_category_name' => $subCategory['name'],
                                    'products' => $products
                                ];
                            }
                        }
                    } else {
                        $sql = "SELECT p.*, pc.name AS category_name, pc.id AS category_id 
                                FROM products p
                                LEFT JOIN product_categories pc ON p.category_id = pc.id
                                WHERE p.status = 1 AND p.category_id = ?
                                ORDER BY p.sort_order DESC, p.created_at DESC LIMIT 20";

                        $stmt = $db->prepare($sql);
                        $stmt->execute([$category_id]);
                        $products = $stmt->fetchAll();

                        if (!empty($products)) {
                            $parentCategory = $db->prepare("SELECT name FROM product_categories WHERE id = ?");
                            $parentCategory->execute([$categoryInfo['parent_id']]);
                            $parentCategory = $parentCategory->fetch();

                            $groupedProducts[$categoryInfo['parent_id']] = [
                                'category_name' => $parentCategory['name'],
                                'sub_categories' => [
                                    [
                                        'sub_category_name' => $categoryInfo['name'],
                                        'products' => $products
                                    ]
                                ]
                            ];
                        }
                    }
                }
            } else {
                foreach ($categories as $category) {
                    $subCategories = $db->prepare("SELECT id, name FROM product_categories WHERE parent_id = ? AND status = 1 ORDER BY sort_order DESC, id DESC");
                    $subCategories->execute([$category['id']]);
                    $subCategories = $subCategories->fetchAll();

                    foreach ($subCategories as $subCategory) {
                        $sql = "SELECT p.*, pc.name AS category_name, pc.id AS category_id 
                                FROM products p
                                LEFT JOIN product_categories pc ON p.category_id = pc.id
                                WHERE p.status = 1 AND p.category_id = ?
                                ORDER BY p.sort_order DESC, p.created_at DESC LIMIT 20";

                        $stmt = $db->prepare($sql);
                        $stmt->execute([$subCategory['id']]);
                        $products = $stmt->fetchAll();

                        if (!empty($products)) {
                            if (!isset($groupedProducts[$category['id']])) {
                                $groupedProducts[$category['id']] = [
                                    'category_name' => $category['name'],
                                    'sub_categories' => []
                                ];
                            }

                            $groupedProducts[$category['id']]['sub_categories'][] = [
                                'sub_category_name' => $subCategory['name'],
                                'products' => $products
                            ];
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Error loading products: ' . $e->getMessage());
            $groupedProducts = [];
        }
    }

} catch(PDOException $e) {
    error_log("数据库错误: ".$e->getMessage());

    if (empty($categories)) {
        $categories = [
            ['id' => 1, 'name' => '和平地铁', 'logo' => '', 'sort_order' => 10],
            ['id' => 2, 'name' => '三倍专区', 'logo' => '', 'sort_order' => 9],
            ['id' => 3, 'name' => '考核装备', 'logo' => '', 'sort_order' => 8],
            ['id' => 4, 'name' => '三角洲', 'logo' => '', 'sort_order' => 7]
        ];
    }

    if (empty($groupedProducts)) {
        $groupedProducts = [
            1 => [
                'category_name' => '热门游戏',
                'sub_categories' => [
                    [
                        'sub_category_name' => '英雄联盟',
                        'products' => [
                            [
                                'id' => 1,
                                'name' => '老板专属定制单',
                                'price' => 5.90,
                                'original_price' => 0,
                                'images' => json_encode(['assets/images/categories/category_1750707900.jpg']),
                                'chinese_name' => '老板想玩什么就玩什么',
                                'virtual_sales' => 999,
                                'sold_quantity' => 0
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="小熠网络 - 专业游戏陪玩服务平台">
    <meta name="keywords" content="游戏陪玩,电竞,陪玩平台,游戏代练">
    <title>小熠网络-<?php echo htmlspecialchars($site_name); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background: #f5f5f5; color: #333; line-height: 1.5; padding-bottom: 80px; }

        /* ===== 顶部搜索栏 ===== */
        .top-search-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            background: #fff;
            position: sticky;
            top: 0;
            z-index: 50;
            border-bottom: 1px solid #f0f0f0;
        }
        .top-logo {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            overflow: hidden;
            flex-shrink: 0;
            background: #fff8dc;
            border: 1px solid #ffe4b5;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .top-logo img { width: 100%; height: 100%; object-fit: cover; }
        .top-logo-default {
            width: 100%; height: 100%;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #fff8dc, #ffe4b5);
            color: #d4a017;
            font-size: 10px;
            font-weight: bold;
        }
        .top-site-name {
            font-size: 16px;
            font-weight: 700;
            color: #222;
            flex-shrink: 0;
            letter-spacing: 1px;
        }
        .top-search-form {
            flex: 1;
            position: relative;
        }
        .top-search-input {
            width: 100%;
            height: 36px;
            border: none;
            border-radius: 18px;
            background: #f5f5f5;
            padding: 0 36px 0 14px;
            font-size: 13px;
            color: #333;
            outline: none;
        }
        .top-search-input::placeholder { color: #bbb; }
        .top-search-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #999;
            font-size: 16px;
            cursor: pointer;
            padding: 4px;
        }

        .app-container { max-width: 100%; margin: 0 auto; padding: 0 10px; }

        /* ===== 轮播图通用 ===== */
        .swiper { border-radius: 12px; overflow: hidden; }
        .swiper-slide { position: relative; overflow: hidden; }
        .swiper-slide img { width: 100%; height: 100%; object-fit: cover; display: block; }

        /* ===== 轮播图1 ===== */
        .hero-section-1 { margin: 10px 0; }
        .swiper-1 { height: 180px; }
        .swiper-1 .swiper-slide { height: 180px; cursor: pointer; }
        .swiper-1 .swiper-pagination-bullet { background: rgba(255,255,255,0.5); opacity: 1; }
        .swiper-1 .swiper-pagination-bullet-active { background: #ffcc00; width: 16px; border-radius: 8px; }

        /* ===== 公告栏 ===== */
        .announcement-bar {
            background: linear-gradient(90deg, #ffcc66, #ffaa33);
            border-radius: 8px;
            padding: 8px 12px;
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 8px;
            overflow: hidden;
        }
        .announcement-bar i { color: #fff; font-size: 14px; flex-shrink: 0; }
        .announcement-scroll { flex: 1; overflow: hidden; white-space: nowrap; }
        .announcement-scroll span { 
            display: inline-block; 
            font-size: 12px; 
            color: #fff; 
            animation: scroll-left 12s linear infinite;
            padding-left: 100%;
        }
        @keyframes scroll-left { 0% { transform: translateX(0); } 100% { transform: translateX(-100%); } }

        /* ===== 轮播图2 ===== */
        .hero-section-2 { margin: 10px 0; }
        .swiper-2 { height: 110px; }
        .swiper-2 .swiper-slide { height: 110px; cursor: pointer; }
        .swiper-2 .swiper-pagination-bullet { background: rgba(255,255,255,0.5); opacity: 1; }
        .swiper-2 .swiper-pagination-bullet-active { background: #fff; width: 16px; border-radius: 8px; }

        /* ===== 一级分类（4列图标） ===== */
        .primary-categories {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin: 14px 0;
        }
        .cat-item {
            text-decoration: none;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }
        .cat-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            overflow: hidden;
            background: #1a1a1a;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .cat-icon img { width: 100%; height: 100%; object-fit: cover; }
        .cat-icon-default {
            width: 100%; height: 100%;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #1a1a1a, #333);
            color: #ffcc00;
            font-size: 12px;
            font-weight: bold;
        }
        .cat-name { font-size: 12px; color: #333; font-weight: 500; }

        /* ===== 快捷入口 ===== */
        .quick-entries {
            display: flex;
            gap: 12px;
            margin: 14px 0;
            overflow-x: auto;
            padding-bottom: 4px;
        }
        .quick-entries::-webkit-scrollbar { display: none; }
        .quick-item {
            flex-shrink: 0;
            text-align: center;
            cursor: pointer;
            width: 72px;
        }
        .quick-item img {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            object-fit: cover;
            display: block;
            margin: 0 auto 6px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .quick-item span { font-size: 12px; color: #333; }

        /* ===== 分类标题 ===== */
        .section-title {
            font-size: 18px;
            font-weight: 800;
            color: #222;
            margin: 20px 0 10px;
            display: inline-block;
            position: relative;
        }
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, #ffcc00, #ff9900);
            border-radius: 3px;
            opacity: 0.5;
            z-index: -1;
        }

        /* ===== 商品3列网格 ===== */
        .products-grid-three {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 16px;
        }
        .product-card-three {
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            text-decoration: none;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            transition: transform 0.15s;
        }
        .product-card-three:active { transform: scale(0.97); }
        .product-img-three {
            width: 100%;
            aspect-ratio: 1 / 1;
            position: relative;
            overflow: hidden;
            background: #ffe6f0;
        }
        .product-img-three img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .lottery-badge {
            position: absolute;
            top: 4px;
            left: 4px;
            background: linear-gradient(135deg, #ff4757, #ff6b81);
            color: #fff;
            font-size: 9px;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
        }
        .product-title-three {
            font-size: 12px;
            color: #222;
            font-weight: 600;
            padding: 6px 6px 2px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.3;
            min-height: 34px;
        }
        .product-price-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 2px 6px 8px;
        }
        .price-three {
            font-size: 15px;
            font-weight: 700;
            color: #ff3333;
        }
        .cart-btn-three {
            width: 24px;
            height: 24px;
            background: linear-gradient(135deg, #ffcc00, #ff9900);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 10px;
        }

        /* ===== 空状态 ===== */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
            font-size: 14px;
        }

        /* ===== 图片放大弹窗 ===== */
        .lightbox {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.92);
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        .lightbox.active { display: flex; }
        .lightbox img { max-width: 92%; max-height: 80%; border-radius: 8px; object-fit: contain; }
        .lightbox-close {
            position: absolute;
            top: 20px; right: 20px;
            color: #fff;
            font-size: 28px;
            cursor: pointer;
            width: 40px; height: 40px;
            display: flex; align-items: center; justify-content: center;
        }

        /* ===== 抽奖提示弹窗 ===== */
        .lottery-tip-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
            justify-content: center;
            align-items: center;
        }
        .lottery-tip-modal.active { display: flex; }
        .lottery-tip-content {
            background: #fff;
            border-radius: 16px;
            width: 80%;
            max-width: 320px;
            padding: 24px;
            text-align: center;
            animation: popIn 0.2s ease;
        }
        @keyframes popIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .lottery-tip-content .icon { font-size: 48px; margin-bottom: 10px; }
        .lottery-tip-content h3 { font-size: 16px; margin-bottom: 8px; }
        .lottery-tip-content p { font-size: 13px; color: #666; margin-bottom: 18px; }
        .lottery-tip-btns { display: flex; gap: 10px; }
        .lottery-tip-btns a, .lottery-tip-btns button {
            flex: 1;
            padding: 10px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-cancel { background: #f0f0f0; color: #666; }
        .btn-go { background: linear-gradient(135deg, #ffcc00, #ff9900); color: #fff; font-weight: 600; }

        /* ===== 快捷入口弹窗 ===== */
        .quick-entry-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.55);
            justify-content: center;
            align-items: center;
        }
        .quick-entry-modal.active { display: flex; }
        .quick-entry-modal-content {
            background: #fff;
            border-radius: 16px;
            width: 90%;
            max-width: 400px;
            max-height: 70vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            animation: popIn 0.2s ease;
        }
        .quick-entry-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 16px;
            border-bottom: 1px solid #eee;
            font-weight: 600;
            font-size: 15px;
        }
        .quick-entry-modal-close { font-size: 24px; color: #999; cursor: pointer; }
        .quick-entry-modal-body { padding: 16px; overflow-y: auto; flex: 1; font-size: 14px; line-height: 1.6; }

    </style>
    <link rel="stylesheet" href="../assets/css/theme-v2.css">
</head>
<body>
<!-- V2 加载动画 -->
<div class="v2-loader-overlay" id="v2Loader">
    <div class="v2-loader-logo-wrap">
        <div class="v2-loader-ring"></div>
        <div class="v2-loader-ring"></div>
        <div class="v2-loader-logo">澜</div>
    </div>
    <div class="v2-loader-text">
        <span>加</span><span>载</span><span>中</span><span>...</span>
    </div>
    <div class="v2-loader-progress">
        <div class="v2-loader-progress-bar"></div>
    </div>
</div>

    <!-- ===== 顶部搜索栏 ===== -->
    <div class="top-search-bar">
        <div class="top-logo">
            <?php if(!empty($site_logo)): ?>
                <img src="../<?php echo htmlspecialchars($site_logo); ?>" alt="logo">
            <?php else: ?>
                <div class="top-logo-default">LOGO</div>
            <?php endif; ?>
        </div>
        <div class="top-site-name"><?php echo htmlspecialchars($site_name); ?></div>
        <form class="top-search-form" action="index.php" method="GET">
            <input type="text" name="keyword" class="top-search-input" placeholder="搜索你喜欢的单子" value="<?php echo htmlspecialchars($search_keyword); ?>">
            <button type="submit" class="top-search-btn"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <main class="app-container">
        <!-- ===== 轮播图1（有数据才显示） ===== -->
        <?php if(!empty($carousel1)): ?>
        <section class="hero-section-1">
            <div class="swiper swiper-1">
                <div class="swiper-wrapper">
                    <?php foreach($carousel1 as $c1): ?>
                    <div class="swiper-slide" onclick="openLightbox('../<?php echo htmlspecialchars($c1['image_path']); ?>')">
                        <img src="../<?php echo htmlspecialchars($c1['image_path']); ?>" alt="轮播图">
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </section>
        <?php endif; ?>

        <!-- ===== 公告栏 ===== -->
        <?php if(!empty($h5_announcement_text)): ?>
        <div class="announcement-bar">
            <i class="fas fa-volume-up"></i>
            <div class="announcement-scroll">
                <span><?php echo htmlspecialchars($h5_announcement_text); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== 轮播图2（有数据才显示） ===== -->
        <?php if(!empty($carousel2)): ?>
        <section class="hero-section-2">
            <div class="swiper swiper-2">
                <div class="swiper-wrapper">
                    <?php foreach($carousel2 as $c2): ?>
                    <div class="swiper-slide" onclick="openLightbox('../<?php echo htmlspecialchars($c2['image_path']); ?>')">
                        <img src="../<?php echo htmlspecialchars($c2['image_path']); ?>" alt="轮播图">
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </section>
        <?php endif; ?>

        <!-- ===== 一级分类（4列图标+文字） ===== -->
        <div class="primary-categories">
            <?php foreach($categories as $category): ?>
            <a href="index.php?category_id=<?php echo $category['id']; ?>" class="cat-item">
                <div class="cat-icon">
                    <?php if(!empty($category['logo'])): ?>
                        <img src="../<?php echo htmlspecialchars($category['logo']); ?>" alt="<?php echo htmlspecialchars($category['name']); ?>">
                    <?php else: ?>
                        <div class="cat-icon-default"><?php echo mb_substr($category['name'], 0, 2); ?></div>
                    <?php endif; ?>
                </div>
                <div class="cat-name"><?php echo htmlspecialchars($category['name']); ?></div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- ===== 快捷入口 ===== -->
        <?php if (!empty($quick_entries)): ?>
        <div class="quick-entries">
            <?php foreach ($quick_entries as $entry): ?>
            <div class="quick-item" onclick="showQuickEntryModal(this)" 
                 data-title="<?php echo htmlspecialchars($entry['title']); ?>" 
                 data-content="<?php echo htmlspecialchars($entry['content'] ?? ''); ?>">
                <img src="../<?php echo htmlspecialchars($entry['icon_image']); ?>" alt="<?php echo htmlspecialchars($entry['title']); ?>">
                <span><?php echo htmlspecialchars($entry['title']); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ===== 商品列表 ===== -->
        <section class="products-section">
            <?php if(empty($groupedProducts)): ?>
                <div class="empty-state">
                    <div style="font-size:42px;margin-bottom:10px;">😂</div>
                    <div>暂无商品，敬请期待</div>
                </div>
            <?php else: ?>
                <?php foreach($groupedProducts as $categoryId => $categoryData): ?>
                    <?php foreach($categoryData['sub_categories'] as $subCategoryData): ?>
                        <div class="section-title"><?php echo htmlspecialchars($categoryData['category_name'] . ' · ' . $subCategoryData['sub_category_name']); ?></div>

                        <div class="products-grid-three">
                            <?php foreach($subCategoryData['products'] as $product): 
                                $images = json_decode($product['images'] ?? '[]', true);
                                if(empty($images)) { $images = ['assets/images/default-product.png']; }

                                // 判断是否为抽奖商品
                                $product_name_lower = strtolower($product['name']);
                                $is_lottery = false;
                                $lottery_keywords = array('抽奖', '盲盒', '转盘', '幸运', '福利');
                                foreach($lottery_keywords as $kw) {
                                    if(strpos($product_name_lower, $kw) !== false) { $is_lottery = true; break; }
                                }
                                if(isset($product['is_lottery']) && $product['is_lottery'] == 1) {
                                    $is_lottery = true;
                                }
                            ?>
                            <a href="product_detail.php?id=<?php echo $product['id']; ?>" class="product-card-three">
                                <div class="product-img-three">
                                    <img src="../<?php echo htmlspecialchars($images[0]); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    <?php if($is_lottery): ?>
                                        <div class="lottery-badge">🎁 抽奖</div>
                                    <?php endif; ?>
                                </div>
                                <div class="product-title-three"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div class="product-price-row">
                                    <span class="price-three">¥<?php echo number_format($product['price'], 1); ?></span>
                                    <span class="cart-btn-three"><i class="fas fa-shopping-cart"></i></span>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>

    <!-- ===== 图片放大弹窗 ===== -->
    <div id="lightbox" class="lightbox" onclick="closeLightbox()">
        <div class="lightbox-close">&times;</div>
        <img id="lightbox-img" src="" alt="">
    </div>

    <!-- ===== 抽奖提示弹窗 ===== -->
    <div id="lotteryTipModal" class="lottery-tip-modal" onclick="if(event.target===this)closeLotteryTip()">
        <div class="lottery-tip-content">
            <div class="icon">🎁</div>
            <h3>抽奖商品</h3>
            <p>该商品为抽奖类型，点击确定进入抽奖页面</p>
            <div class="lottery-tip-btns">
                <button class="btn-cancel" onclick="closeLotteryTip()">取消</button>
                <a id="lotteryGoLink" href="" class="btn-go">去抽奖</a>
            </div>
        </div>
    </div>

    <!-- ===== 快捷入口弹窗 ===== -->
    <div id="quickEntryModal" class="quick-entry-modal" onclick="if(event.target===this)closeQuickEntryModal()">
        <div class="quick-entry-modal-content">
            <div class="quick-entry-modal-header">
                <span id="quickEntryModalTitle"></span>
                <span class="quick-entry-modal-close" onclick="closeQuickEntryModal()">&times;</span>
            </div>
            <div class="quick-entry-modal-body" id="quickEntryModalBody"></div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        // 轮播图初始化
        document.addEventListener('DOMContentLoaded', function() {
            if (document.querySelector('.swiper-1 .swiper-slide')) {
                new Swiper('.swiper-1', {
                    loop: true,
                    autoplay: { delay: 4000, disableOnInteraction: false },
                    pagination: { el: '.swiper-1 .swiper-pagination', clickable: true }
                });
            }

            if (document.querySelector('.swiper-2 .swiper-slide')) {
                new Swiper('.swiper-2', {
                    loop: true,
                    autoplay: { delay: 3000, disableOnInteraction: false },
                    pagination: { el: '.swiper-2 .swiper-pagination', clickable: true }
                });
            }
        });

        // ===== 图片放大 =====
        function openLightbox(src) {
            document.getElementById('lightbox-img').src = src;
            document.getElementById('lightbox').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            document.getElementById('lightbox').classList.remove('active');
            document.body.style.overflow = '';
        }

        // ===== 抽奖提示 =====
        function showLotteryTip(productId) {
            document.getElementById('lotteryGoLink').href = 'lottery.php?product_id=' + productId;
            document.getElementById('lotteryTipModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeLotteryTip() {
            document.getElementById('lotteryTipModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // ===== 快捷入口弹窗 =====
        function showQuickEntryModal(el) {
            document.getElementById('quickEntryModalTitle').innerText = el.getAttribute('data-title');
            document.getElementById('quickEntryModalBody').innerHTML = el.getAttribute('data-content');
            document.getElementById('quickEntryModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeQuickEntryModal() {
            document.getElementById('quickEntryModal').classList.remove('active');
            document.body.style.overflow = '';
        }
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
