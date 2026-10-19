<?php
require_once '../includes/session_manager.php';
require_once '../config.php';

// 检查用户登录状态
if (!is_h5_logged_in()) {
    die("<script>alert('请先登录'); location.href='login.php';</script>");
}

// 获取当前登录用户信息（从h5user表）
try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", 
                 DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // 查询h5user表中的用户信息
    $stmt = $db->prepare("SELECT id, user_idu, nickname FROM h5user WHERE id = ? LIMIT 1");
    $stmt->execute([get_h5_user_id()]);
    $currentUser = $stmt->fetch();

    if (!$currentUser) {
        die("<script>alert('用户信息不存在'); location.href='login.php';</script>");
    }

    // 提取用户信息
    $user_id = $currentUser['id'];
    $user_idu = $currentUser['user_idu'];
    $user_nickname = $currentUser['nickname'];

} catch (PDOException $e) {
    die("数据库错误: ".$e->getMessage());
}

// 获取商品ID
$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 获取商品信息
$product = null;
$regionPrices = [];
$priceIncrease = null;
$finalPrice = 0;
if ($productId > 0) {
    try {
        $query = "SELECT *, 
                 (SELECT price_increase FROM products WHERE id = ?) AS price_increase 
                 FROM products WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$productId, $productId]);
        $product = $stmt->fetch();

        if (!$product) {
            die("商品不存在或已下架");
        }

        // 处理涨价信息
        $finalPrice = $product['price'];
        if (!empty($product['price_increase'])) {
            $priceIncrease = json_decode($product['price_increase'], true);
            if (json_last_error() === JSON_ERROR_NONE && isset($priceIncrease['price'])) {
                $finalPrice += $priceIncrease['price'];
            }
        }

        // 处理商品图片
        $images = json_decode($product['images'] ?? '[]', true);
        $firstImage = !empty($images) ? '../' . $images[0] : '../assets/images/placeholder.png';

        // 解析区域价格
        $regionPrices = json_decode($product['region_prices'] ?? '{}', true);

        // 获取抽奖商品的奖品
        $is_lottery = $product['is_lottery'] == 1;
        $lottery_prizes = [];
        if ($is_lottery) {
            try {
                $prize_stmt = $db->prepare("SELECT * FROM lottery_prizes WHERE product_id = ? AND status = 1 ORDER BY sort_order ASC, id ASC");
                $prize_stmt->execute([$productId]);
                $lottery_prizes = $prize_stmt->fetchAll();
                if (empty($lottery_prizes)) {
                    $prize_stmt = $db->query("SELECT * FROM lottery_prizes WHERE (product_id IS NULL OR product_id = 0) AND status = 1 ORDER BY sort_order ASC, id ASC LIMIT 8");
                    $lottery_prizes = $prize_stmt->fetchAll();
                }
            } catch (Exception $e) {
                $lottery_prizes = [];
            }
        }

    } catch (PDOException $e) {
        die("获取商品信息失败: ".$e->getMessage());
    }
}

// 获取打手列表（分页处理）
$huntersPerPage = 20;
$page = isset($_GET['hunter_page']) ? max(1, intval($_GET['hunter_page'])) : 1;
$offset = ($page - 1) * $huntersPerPage;

try {
    // 获取打手总数
    $countQuery = "SELECT COUNT(*) as total FROM users 
                  WHERE member_level IN ('普通会员', '大神打手', '至尊打手') 
                  AND status = '正常'";
    $countResult = $db->query($countQuery);
    $totalHunters = $countResult->fetch()['total'];
    $totalPages = ceil($totalHunters / $huntersPerPage);

    // 获取当前页的打手
    $query = "SELECT id, username, avatar, member_level FROM users 
              WHERE member_level IN ('普通会员', '大神打手', '至尊打手') 
              AND status = '正常'
              ORDER BY FIELD(member_level, '至尊打手', '大神打手', '普通会员'), id DESC
              LIMIT ? OFFSET ?";
    $stmt = $db->prepare($query);
    $stmt->bindValue(1, $huntersPerPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $hunters = $stmt->fetchAll();

} catch (PDOException $e) {
    die("获取打手列表失败: ".$e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>提交订单</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.4;
            -webkit-font-smoothing: antialiased;
            padding-bottom: 70px;
        }

        /* ========== 顶部导航 ========== */
        .header {
            background: #fff;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            border-bottom: 1px solid #f0f0f0;
        }

        .header-back {
            position: absolute;
            left: 8px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #333;
        }

        .header-back svg {
            width: 20px;
            height: 20px;
        }

        .header-title {
            font-size: 17px;
            font-weight: 500;
            color: #333;
        }

        .header-more {
            position: absolute;
            right: 12px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }

        .header-more svg {
            width: 20px;
            height: 20px;
        }

        /* ========== 主内容 ========== */
        .container {
            padding: 10px 12px;
        }

        /* ========== 卡片 ========== */
        .card {
            background: #fff;
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 10px;
        }

        /* ========== 商品信息 ========== */
        .product-box {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .product-img {
            width: 70px;
            height: 70px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
            background: #f0f0f0;
        }

        .product-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 70px;
        }

        .product-name {
            font-size: 15px;
            font-weight: 500;
            color: #333;
            line-height: 1.4;
        }

        .product-bottom {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            margin-top: auto;
        }

        .product-price {
            font-size: 20px;
            font-weight: 700;
            color: #e4393c;
        }

        .product-price .yen {
            font-size: 14px;
            margin-right: 1px;
        }

        .product-qty {
            font-size: 14px;
            color: #999;
        }

        /* ========== 表单行 ========== */
        .form-row {
            display: flex;
            align-items: flex-start;
            padding: 14px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .form-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .form-row:first-child {
            padding-top: 0;
        }

        .form-label {
            width: 82px;
            font-size: 15px;
            font-weight: 700;
            color: #333;
            flex-shrink: 0;
            padding-top: 10px;
            line-height: 1.4;
        }

        /* 黄色马克笔高亮效果 */
        .form-label .highlight {
            background: linear-gradient(to bottom, transparent 50%, #ffeb3b 50%);
            padding: 0 2px;
        }

        .form-right {
            flex: 1;
            min-width: 0;
        }

        /* ========== 查看游戏编号链接 ========== */
        .view-id-link {
            display: block;
            text-align: right;
            font-size: 13px;
            color: #1989fa;
            margin-bottom: 8px;
            text-decoration: none;
        }

        /* ========== 输入框 ========== */
        .input-wrap {
            position: relative;
        }

        .form-input {
            width: 100%;
            height: 44px;
            padding: 0 50px 0 12px;
            border: 1px solid #e8e8e8;
            border-radius: 8px;
            font-size: 14px;
            color: #333;
            outline: none;
            background: #fff;
            -webkit-appearance: none;
        }

        .form-input::placeholder {
            color: #ccc;
        }

        .char-count {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 13px;
            color: #ccc;
            pointer-events: none;
        }

        /* ========== 文本域 ========== */
        .textarea-wrap {
            position: relative;
        }

        .form-textarea {
            width: 100%;
            min-height: 80px;
            padding: 10px 12px 24px;
            border: 1px solid #e8e8e8;
            border-radius: 8px;
            font-size: 14px;
            color: #333;
            outline: none;
            resize: none;
            background: #fff;
            font-family: inherit;
            -webkit-appearance: none;
        }

        .form-textarea::placeholder {
            color: #ccc;
        }

        .textarea-count {
            text-align: right;
            font-size: 12px;
            color: #ccc;
            margin-top: 4px;
        }

        /* ========== 单选组 ========== */
        .radio-group {
            display: flex;
            align-items: center;
            gap: 24px;
            padding-top: 9px;
        }

        .radio-item {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            font-size: 14px;
            color: #333;
            -webkit-tap-highlight-color: transparent;
        }

        .radio-circle {
            width: 18px;
            height: 18px;
            border: 1px solid #ccc;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.15s;
            background: #fff;
        }

        .radio-item.active .radio-circle {
            border-color: #1989fa;
            background: #1989fa;
        }

        .radio-item.active .radio-circle::after {
            content: '';
            width: 7px;
            height: 4px;
            border-left: 1.5px solid #fff;
            border-bottom: 1.5px solid #fff;
            transform: rotate(-45deg) translate(0.5px, -1px);
        }

        /* ========== 数量选择器 ========== */
        .qty-box {
            display: flex;
            align-items: center;
            padding-top: 5px;
        }

        .qty-btn {
            width: 36px;
            height: 36px;
            border: 1px solid #e0e0e0;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #666;
            cursor: pointer;
            outline: none;
            -webkit-appearance: none;
        }

        .qty-btn:first-child {
            border-radius: 4px 0 0 4px;
            font-size: 18px;
            padding-bottom: 2px;
        }

        .qty-btn:last-child {
            border-radius: 0 4px 4px 0;
        }

        .qty-btn:disabled {
            color: #ccc;
            cursor: not-allowed;
        }

        .qty-num {
            width: 50px;
            height: 36px;
            border-top: 1px solid #e0e0e0;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 500;
            color: #333;
            background: #fff;
        }

        /* ========== 已选打手展示 ========== */
        .selected-hunter-row {
            display: none;
            align-items: center;
            gap: 10px;
            margin-top: 12px;
        }

        .selected-hunter-row.show {
            display: flex;
        }

        .selected-hunter-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }

        .selected-hunter-name {
            font-size: 14px;
            color: #333;
            flex: 1;
        }

        .hunter-tip {
            font-size: 13px;
            color: #e4393c;
            margin-top: 8px;
            line-height: 1.5;
        }

        /* ========== 下单说明 ========== */
        .notice-section {
            background: #fff;
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 10px;
        }

        .notice-title {
            font-size: 15px;
            font-weight: 700;
            color: #333;
            margin-bottom: 12px;
        }

        .notice-alert {
            background: #fff0f0;
            border-radius: 6px;
            padding: 10px;
            text-align: center;
            margin-bottom: 10px;
        }

        .notice-alert-text {
            font-size: 14px;
            font-weight: 600;
            color: #e4393c;
        }

        .notice-body {
            font-size: 13px;
            color: #333;
            line-height: 1.8;
        }

        .notice-body p {
            margin-bottom: 4px;
        }

        .notice-body .red {
            color: #e4393c;
        }

        /* ========== 底部栏 ========== */
        .bottom-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 56px;
            background: #fff;
            border-top: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 12px;
            z-index: 100;
        }

        .total-left {
            display: flex;
            align-items: baseline;
            gap: 2px;
        }

        .total-label {
            font-size: 14px;
            color: #666;
        }

        .total-price {
            font-size: 20px;
            font-weight: 700;
            color: #e4393c;
        }

        .total-price .yen {
            font-size: 13px;
        }

        .pay-btn {
            padding: 0 32px;
            height: 40px;
            background: #ffcc00;
            border: none;
            border-radius: 20px;
            font-size: 15px;
            font-weight: 600;
            color: #333;
            cursor: pointer;
            outline: none;
            -webkit-appearance: none;
        }

        .pay-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* ========== 打手选择弹窗 ========== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: flex-end;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            width: 100%;
            max-height: 75vh;
            background: #fff;
            border-radius: 16px 16px 0 0;
            padding: 16px 0;
            overflow-y: auto;
            animation: slideUp 0.25s ease;
            display: flex;
            flex-direction: column;
        }

        @keyframes slideUp {
            from { transform: translateY(100%); }
            to { transform: translateY(0); }
        }

        .modal-header {
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            margin-bottom: 16px;
            padding: 0 16px;
        }

        .modal-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }

        .modal-close {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 28px;
            height: 28px;
            border: 1px solid #e0e0e0;
            background: #fff;
            border-radius: 50%;
            font-size: 14px;
            color: #333;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            outline: none;
        }

        /* 弹窗搜索框 */
        .modal-search {
            padding: 0 16px;
            margin-bottom: 12px;
        }

        .modal-search-box {
            display: flex;
            align-items: center;
            border: 1px solid #e0e0e0;
            border-radius: 22px;
            padding: 2px;
            background: #fff;
        }

        .modal-search-input {
            flex: 1;
            height: 40px;
            border: none;
            outline: none;
            padding: 0 14px;
            font-size: 14px;
            background: transparent;
            -webkit-appearance: none;
        }

        .modal-search-input::placeholder {
            color: #ccc;
        }

        .modal-search-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #000;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
        }

        .modal-search-btn svg {
            width: 18px;
            height: 18px;
            fill: #fff;
        }

        /* 弹窗列表 */
        .hunter-list {
            padding: 0 16px;
        }

        .hunter-list-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f5f5f5;
            cursor: pointer;
        }

        .hunter-list-item:last-child {
            border-bottom: none;
        }

        .hunter-list-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
            margin-right: 12px;
        }

        .hunter-list-name {
            flex: 1;
            font-size: 15px;
            color: #333;
        }

        .hunter-list-radio {
            width: 20px;
            height: 20px;
            border: 1px solid #ccc;
            border-radius: 50%;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s;
        }

        .hunter-list-item.selected .hunter-list-radio {
            border-color: #1989fa;
            background: #1989fa;
        }

        .hunter-list-item.selected .hunter-list-radio::after {
            content: '';
            width: 8px;
            height: 5px;
            border-left: 1.5px solid #fff;
            border-bottom: 1.5px solid #fff;
            transform: rotate(-45deg) translate(0px, -1px);
        }

        /* 弹窗底部确认按钮 */
        .modal-footer {
            padding: 12px 16px 0;
        }

        .modal-confirm {
            width: 100%;
            height: 44px;
            background: #333;
            color: #fff;
            border: none;
            border-radius: 22px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            outline: none;
            -webkit-appearance: none;
        }

        .modal-confirm:disabled {
            opacity: 0.5;
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
    <div class="header">
        <a href="javascript:history.back()" class="header-back">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </a>
        <div class="header-title">提交订单</div>
        <div class="header-more">
            <svg viewBox="0 0 24 24" fill="currentColor">
                <circle cx="6" cy="12" r="2"/>
                <circle cx="12" cy="12" r="2"/>
                <circle cx="18" cy="12" r="2"/>
            </svg>
        </div>
    </div>

    <div class="container">
        <!-- 商品信息 -->
        <div class="card">
            <div class="product-box">
                <img src="<?= htmlspecialchars($firstImage) ?>" alt="" class="product-img" onerror="this.style.display='none'">
                <div class="product-info">
                    <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                    <div class="product-bottom">
                        <span class="product-price"><span class="yen">¥</span><?= number_format($finalPrice, 1) == intval($finalPrice) ? number_format($finalPrice, 1) : $finalPrice ?></span>
                        <span class="product-qty">x<span id="productQtyDisplay">1</span></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 订单表单 -->
        <form id="orderForm" action="payment_select.php" method="post">
            <input type="hidden" name="user_id" value="<?= $user_id ?>">
            <input type="hidden" name="user_idu" value="<?= $user_idu ?>">
            <input type="hidden" name="user_nickname" value="<?= htmlspecialchars($user_nickname) ?>">
            <input type="hidden" name="productId" value="<?= $product['id'] ?>">
            <input type="hidden" name="productName" value="<?= htmlspecialchars($product['name']) ?>">
            <input type="hidden" id="productPrice" name="productPrice" value="<?= $finalPrice ?>">
            <input type="hidden" name="orderNo" value="<?= date("YmdHis").mt_rand(100,999) ?>">
            <input type="hidden" id="quantity" name="quantity" value="1">
            <input type="hidden" id="hunterId" name="selectedHunterId" value="">
            <input type="hidden" id="hunterOption" name="hunterOption" value="system">
            <input type="hidden" id="gameRegion" name="gameRegion" value="<?= !empty($regions) ? htmlspecialchars($regions[0]) : '' ?>">
            <input type="hidden" id="tipAmount" name="tipAmount" value="0">

            <div class="card">
                <!-- 数字编号 -->
                <div class="form-row">
                    <div class="form-label"><span class="highlight">数字编号</span></div>
                    <div class="form-right">
                        <a href="#" class="view-id-link">查看游戏编号</a>
                        <div class="input-wrap">
                            <input type="text" name="numeric_id" class="form-input" placeholder="请输入游戏数字编号" maxlength="30">
                            <span class="char-count">0/30</span>
                        </div>
                    </div>
                </div>

                <!-- 文字ID -->
                <div class="form-row">
                    <div class="form-label"><span class="highlight">文字ID</span></div>
                    <div class="form-right">
                        <div class="input-wrap">
                            <input type="text" name="chinese_id" class="form-input" placeholder="请输入文字ID" maxlength="30" required>
                            <span class="char-count">0/30</span>
                        </div>
                    </div>
                </div>

                <!-- 游戏区服 -->
                <div class="form-row">
                    <div class="form-label"><span class="highlight">游戏区服</span></div>
                    <div class="form-right">
                        <div class="radio-group">
                            <?php 
                            try {
                                $regions = json_decode($product['game_region_options'] ?? '[]', true);
                                if (json_last_error() === JSON_ERROR_NONE && !empty($regions)) {
                                    $first = true;
                                    foreach ($regions as $index => $region): 
                            ?>
                            <label class="radio-item <?= $first ? 'active' : '' ?>" onclick="selectRegion(this, '<?= htmlspecialchars($region) ?>')">
                                <div class="radio-circle"></div>
                                <span><?= htmlspecialchars($region) ?></span>
                            </label>
                            <?php 
                                        $first = false;
                                    endforeach;
                                } else {
                            ?>
                            <label class="radio-item active" onclick="selectRegion(this, '')">
                                <div class="radio-circle"></div>
                                <span>暂无可用区服</span>
                            </label>
                            <?php
                                }
                            } catch (Exception $e) {
                            ?>
                            <label class="radio-item active" onclick="selectRegion(this, '')">
                                <div class="radio-circle"></div>
                                <span>区服加载失败</span>
                            </label>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <!-- 接单打手 -->
                <div class="form-row">
                    <div class="form-label"><span class="highlight">接单打手</span></div>
                    <div class="form-right">
                        <div class="radio-group">
                            <label class="radio-item active" onclick="selectHunterOption(this, 'auto')">
                                <div class="radio-circle"></div>
                                <span>随机打手</span>
                            </label>
                            <label class="radio-item" onclick="selectHunterOption(this, 'assign')">
                                <div class="radio-circle"></div>
                                <span>指定打手</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- 指定打手展示 -->
                <div class="form-row" id="selectedHunterRow" style="display:none;">
                    <div class="form-label"><span class="highlight">指定打手</span></div>
                    <div class="form-right">
                        <div class="selected-hunter-row" id="selectedHunterBox">
                            <img src="" alt="" class="selected-hunter-avatar" id="selectedHunterAvatar">
                            <div class="selected-hunter-name" id="selectedHunterName"></div>
                        </div>
                        <div class="hunter-tip">(如5分钟该打手未接单,则自动匹配随机打手,为您服务!)</div>
                    </div>
                </div>

                <!-- 下单备注 -->
                <div class="form-row">
                    <div class="form-label"><span class="highlight">下单备注</span></div>
                    <div class="form-right">
                        <div class="textarea-wrap">
                            <textarea name="remark" class="form-textarea" placeholder="请填写下单备注(选填)" maxlength="140"></textarea>
                            <div class="textarea-count">0/140</div>
                        </div>
                    </div>
                </div>

                <!-- 商品数量 -->
                <div class="form-row">
                    <div class="form-label"><span class="highlight">商品数量</span></div>
                    <div class="form-right">
                        <div class="qty-box">
                            <button type="button" class="qty-btn" id="minusBtn" disabled onclick="changeQty(-1)">−</button>
                            <div class="qty-num" id="qtyNum">1</div>
                            <button type="button" class="qty-btn" id="plusBtn" onclick="changeQty(1)">+</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- 下单说明 -->
        <div class="notice-section">
            <div class="notice-title">下单说明</div>
            <div class="notice-alert">
                <div class="notice-alert-text">⚠私下交易无保障！全程平台操作才安全⚠</div>
            </div>
            <div class="notice-body">
                <p>禁止在未打单时间私下联系等(举报有奖)</p>
                <p>1.装备收益:一件七级抵扣40W，卓越枪抵扣50W,金色抵扣60W其他都等同于变卖物，一周以后所有装备按游戏内价值计算，例如:(7级甲22万)最终收益按撤离结算页面，打手带出给老板的装备价值计算</p>
                <p>2.下单后导师预计1-10分钟之内加您好友，请您上号等待即使通过好友!</p>
                <p>3.请听导师指挥，不听导师指挥乱跑被淘汰不补不退!</p>
                <p>(如长时间未接单可在订单详细取消订单指定请联系客服)</p>
                <p>打完后确定没问题返回本平台点击"确认结单"</p>
                <p class="red">温馨提示:如果导师没打完，导师诱骗您点击"确定结单"，请勿相信!如果私下交易被骗，本店概不负责!!!消费前用户需要保证自身为成年人，未成年人请勿购买!如有需要请在监护人陪同下进行如有隐瞒年龄，执意购买者自行承担后果与本平台无关!</p>
                <p class="red">(监护人不履行监护职责或侵害被监护人合法权益的应当承担责任)</p>
            </div>
        </div>
    </div>

    <!-- 打手选择弹窗 -->
    <div class="modal-overlay" id="hunterModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">请选择指定打手</div>
                <button class="modal-close" onclick="closeModal()">✕</button>
            </div>
            <div class="modal-search">
                <div class="modal-search-box">
                    <input type="text" class="modal-search-input" placeholder="请输入打手昵称" id="hunterSearchInput">
                    <button class="modal-search-btn" onclick="searchHunter()">
                        <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    </button>
                </div>
            </div>
            <div class="hunter-list" id="hunterList">
                <?php if (!empty($hunters)): ?>
                    <?php foreach ($hunters as $hunter):
                        $avatarPath = '../assets/images/userds/' . $hunter['avatar'];
                    ?>
                    <div class="hunter-list-item" data-id="<?= $hunter['id'] ?>" data-name="<?= htmlspecialchars($hunter['username']) ?>" data-level="<?= htmlspecialchars($hunter['member_level']) ?>" data-avatar="<?= $avatarPath ?>" onclick="selectHunter(this)">
                        <img src="<?= $avatarPath ?>" alt="" class="hunter-list-avatar" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2244%22 height=%2244%22><circle cx=%2222%22 cy=%2222%22 r=%2222%22 fill=%22%23e0e0e0%22/></svg>'">
                        <div class="hunter-list-name"><?= htmlspecialchars($hunter['username']) ?></div>
                        <div class="hunter-list-radio"></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button class="modal-confirm" id="confirmHunterBtn" disabled onclick="confirmHunter()">确定选择</button>
            </div>
        </div>
    </div>

    <!-- 底部操作栏 -->
    <div class="bottom-bar">
        <div class="total-left">
            <span class="total-label">合计:</span>
            <span class="total-price"><span class="yen">¥</span><span id="totalPrice"><?= number_format($finalPrice, 2) ?></span></span>
        </div>
        <button type="button" class="pay-btn" id="submitBtn" onclick="submitOrder()">立即支付</button>
    </div>

    <script>
        let quantity = 1;
        let tipAmount = 0;
        let selectedHunter = null;
        const basePrice = <?= $finalPrice ?>;

        // 数量变化
        function changeQty(delta) {
            quantity += delta;
            if (quantity < 1) quantity = 1;
            document.getElementById('qtyNum').textContent = quantity;
            document.getElementById('productQtyDisplay').textContent = quantity;
            document.getElementById('quantity').value = quantity;
            document.getElementById('minusBtn').disabled = quantity <= 1;
            updateTotal();
        }

        function updateTotal() {
            const total = (basePrice * quantity + tipAmount).toFixed(2);
            document.getElementById('totalPrice').textContent = total;
            document.getElementById('productPrice').value = total;
        }

        // 区服选择
        function selectRegion(el, val) {
            const group = el.parentElement;
            group.querySelectorAll('.radio-item').forEach(item => item.classList.remove('active'));
            el.classList.add('active');
            document.getElementById('gameRegion').value = val;
        }

        // 打手选项
        function selectHunterOption(el, type) {
            const group = el.parentElement;
            group.querySelectorAll('.radio-item').forEach(item => item.classList.remove('active'));
            el.classList.add('active');

            if (type === 'assign') {
                document.getElementById('hunterModal').classList.add('active');
                document.body.style.overflow = 'hidden';
            } else {
                document.getElementById('hunterOption').value = 'system';
                document.getElementById('hunterId').value = '';
                document.getElementById('selectedHunterRow').style.display = 'none';
                document.getElementById('selectedHunterBox').classList.remove('show');
            }
        }

        // 弹窗内选择打手
        function selectHunter(el) {
            document.querySelectorAll('.hunter-list-item').forEach(item => item.classList.remove('selected'));
            el.classList.add('selected');
            selectedHunter = el;
            document.getElementById('confirmHunterBtn').disabled = false;
        }

        function confirmHunter() {
            if (!selectedHunter) return;
            const id = selectedHunter.dataset.id;
            const name = selectedHunter.dataset.name;
            const avatar = selectedHunter.dataset.avatar;

            document.getElementById('hunterId').value = id;
            document.getElementById('hunterOption').value = 'assign';
            document.getElementById('selectedHunterAvatar').src = avatar;
            document.getElementById('selectedHunterName').textContent = name;
            document.getElementById('selectedHunterRow').style.display = 'flex';
            document.getElementById('selectedHunterBox').classList.add('show');
            closeModal();
        }

        function closeModal() {
            document.getElementById('hunterModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        document.getElementById('hunterModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        // 搜索打手
        function searchHunter() {
            const keyword = document.getElementById('hunterSearchInput').value.toLowerCase();
            document.querySelectorAll('.hunter-list-item').forEach(item => {
                const name = item.dataset.name.toLowerCase();
                item.style.display = name.includes(keyword) ? 'flex' : 'none';
            });
        }

        document.getElementById('hunterSearchInput').addEventListener('input', searchHunter);

        // 字符计数
        document.querySelectorAll('.form-input').forEach(input => {
            const max = input.getAttribute('maxlength') || 30;
            const countEl = input.parentElement.querySelector('.char-count');
            if (countEl) {
                input.addEventListener('input', function() {
                    countEl.textContent = this.value.length + '/' + max;
                });
            }
        });

        document.querySelector('.form-textarea').addEventListener('input', function() {
            const max = this.getAttribute('maxlength') || 140;
            this.parentElement.querySelector('.textarea-count').textContent = this.value.length + '/' + max;
        });

        // 提交订单
        function submitOrder() {
            const form = document.getElementById('orderForm');
            const chineseId = form.querySelector('[name="chinese_id"]').value;
            const gameRegion = document.getElementById('gameRegion').value;

            if (!chineseId) {
                alert('请输入文字ID');
                return;
            }

            if (!gameRegion) {
                alert('请选择游戏区服');
                return;
            }

            const assignActive = document.querySelector('.radio-item.active').textContent.includes('指定打手');
            if (assignActive && !document.getElementById('hunterId').value) {
                alert('请选择打手');
                return;
            }

            // 如果是抽奖商品，先弹出大转盘抽奖
            if (isLotteryProduct && lotteryPrizes.length > 0) {
                showLotteryWheel();
                return;
            }

            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').textContent = '提交中...';
            form.submit();
        }

        // === 大转盘抽奖 ===
        var isLotteryProduct = <?php echo !empty($is_lottery) ? 'true' : 'false'; ?>;
        var lotteryPrizes = <?php echo json_encode($lottery_prizes, JSON_UNESCAPED_UNICODE); ?>;
        var wheelDrawn = false;

        function showLotteryWheel() {
            if (wheelDrawn) {
                document.getElementById('orderForm').submit();
                return;
            }
            var modal = document.getElementById('lotteryWheelModal');
            modal.classList.add('active');
            drawLotteryWheel();
        }

        function drawLotteryWheel() {
            var canvas = document.getElementById('lotteryCanvas');
            var ctx = canvas.getContext('2d');
            var centerX = 280, centerY = 280, radius = 270;
            var n = lotteryPrizes.length;
            var arc = (2 * Math.PI) / n;
            var colors = ['#3b82f6', '#06b6d4', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#ef4444', '#6366f1'];

            ctx.clearRect(0, 0, 560, 560);
            for (var i = 0; i < n; i++) {
                var angle = i * arc - Math.PI / 2;
                ctx.beginPath();
                ctx.fillStyle = colors[i % colors.length];
                ctx.moveTo(centerX, centerY);
                ctx.arc(centerX, centerY, radius, angle, angle + arc);
                ctx.closePath();
                ctx.fill();
                ctx.save();
                ctx.translate(centerX, centerY);
                ctx.rotate(angle + arc / 2);
                ctx.textAlign = 'right';
                ctx.fillStyle = '#fff';
                ctx.font = 'bold 18px sans-serif';
                var name = lotteryPrizes[i].name.length > 6 ? lotteryPrizes[i].name.substring(0, 6) : lotteryPrizes[i].name;
                ctx.fillText(name, radius - 20, 6);
                ctx.restore();
            }
            ctx.beginPath();
            ctx.arc(centerX, centerY, 50, 0, 2 * Math.PI);
            ctx.fillStyle = '#fff';
            ctx.fill();
        }

        function spinLotteryWheel() {
            if (wheelDrawn) return;
            wheelDrawn = true;
            var canvas = document.getElementById('lotteryCanvas');
            var n = lotteryPrizes.length;
            var arc = 360 / n;
            var targetIndex = Math.floor(Math.random() * n);
            var targetAngle = 360 * 5 + (360 - (targetIndex * arc + arc / 2));
            canvas.style.transform = 'rotate(' + targetAngle + 'deg)';
            document.getElementById('lotteryWheelTip').textContent = '抽奖中...';

            setTimeout(function() {
                document.getElementById('lotteryWheelContainer').style.display = 'none';
                document.getElementById('lotteryResult').style.display = 'block';
                document.getElementById('lotteryResultName').textContent = lotteryPrizes[targetIndex].name;
                document.getElementById('lotteryWheelTip').style.display = 'none';
            }, 4200);
        }

        function closeLotteryWheel() {
            document.getElementById('lotteryWheelModal').classList.remove('active');
            // 抽奖完成后提交订单
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').textContent = '提交中...';
            document.getElementById('orderForm').submit();
        }

        // 页面加载时检查URL参数，自动指定打手
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const hunterId = urlParams.get('hunter_id');
            if (hunterId) {
                setTimeout(function() {
                    const hunterItem = document.querySelector('.hunter-list-item[data-id="' + hunterId + '"]');
                    if (hunterItem) {
                        selectHunter(hunterItem);
                        confirmHunter();
                        // 切换到指定打手选项
                        document.querySelectorAll('.radio-item').forEach(function(el) {
                            el.classList.remove('active');
                        });
                        const assignLabel = document.querySelector('.radio-item[onclick*="assign"]');
                        if (assignLabel) assignLabel.classList.add('active');
                    }
                }, 300);
            }
        });
    </script>
    
<!-- 大转盘抽奖弹窗 -->
<div class="lottery-wheel-modal" id="lotteryWheelModal">
    <div class="lottery-wheel-container" id="lotteryWheelContainer">
        <div class="lottery-wheel-pointer"></div>
        <canvas class="lottery-wheel-canvas" id="lotteryCanvas" width="560" height="560"></canvas>
        <div class="lottery-wheel-center" onclick="spinLotteryWheel()">抽奖</div>
    </div>
    <div class="lottery-result" id="lotteryResult" style="display:none;">
        <div class="lottery-result-icon">🎉</div>
        <h3>恭喜获得</h3>
        <div class="lottery-result-name" id="lotteryResultName">-</div>
        <p>奖品将随订单一起发放</p>
        <button class="lottery-result-btn" onclick="closeLotteryWheel()">继续支付</button>
    </div>
    <div class="lottery-wheel-tip" id="lotteryWheelTip">点击中心按钮开始抽奖</div>
</div>
<style>
.lottery-wheel-modal {
    display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.75); z-index: 9999;
    justify-content: center; align-items: center; flex-direction: column;
}
.lottery-wheel-modal.active { display: flex; animation: fadeIn 0.3s ease; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.lottery-wheel-container {
    position: relative; width: 280px; height: 280px; margin-bottom: 20px;
}
.lottery-wheel-pointer {
    position: absolute; top: -10px; left: 50%; transform: translateX(-50%);
    width: 0; height: 0; z-index: 10;
    border-left: 14px solid transparent; border-right: 14px solid transparent;
    border-top: 24px solid #ef4444; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
}
.lottery-wheel-canvas {
    width: 100%; height: 100%; border-radius: 50%;
    box-shadow: 0 0 0 8px #fff, 0 0 0 12px #3b82f6, 0 8px 30px rgba(0,0,0,0.3);
    transition: transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99);
}
.lottery-wheel-center {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
    width: 60px; height: 60px; border-radius: 50%;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 800; font-size: 14px;
    box-shadow: 0 4px 12px rgba(245,158,11,0.4); z-index: 5; cursor: pointer;
}
.lottery-result {
    background: #fff; border-radius: 16px; padding: 24px; text-align: center;
    max-width: 300px; width: 80%; animation: scaleIn 0.4s cubic-bezier(0.34,1.56,0.64,1);
}
@keyframes scaleIn { from { opacity:0; transform:scale(0.8); } to { opacity:1; transform:scale(1); } }
.lottery-result-icon { font-size: 48px; margin-bottom: 10px; }
.lottery-result h3 { font-size: 18px; color: #1e293b; margin-bottom: 6px; }
.lottery-result p { font-size: 14px; color: #64748b; margin-bottom: 16px; }
.lottery-result-name {
    font-size: 20px; font-weight: 800;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text; margin-bottom: 16px;
}
.lottery-result-btn {
    padding: 10px 30px; border: none; border-radius: 20px;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: #fff; font-size: 14px; font-weight: 600; cursor: pointer;
}
.lottery-wheel-tip { color: rgba(255,255,255,0.8); font-size: 13px; margin-top: 10px; }
</style>

<script src="../assets/js/loader-v2.js"></script>
</body>
</html>