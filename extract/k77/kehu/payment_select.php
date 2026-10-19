<?php
/**
 * 支付方式选择页面 - 完整功能版（已适配抽奖订单）
 * 包含所有订单信息的临时存储，支持普通订单和抽奖订单
 */

// 设置根路径
define('ROOT_PATH', dirname(__DIR__));

// 检查配置文件
if (!file_exists(ROOT_PATH.'/config.php')) {
    die("系统配置文件缺失，请联系管理员");
}

// 引入配置
require_once ROOT_PATH.'/config.php';

// 引入统一会话管理
require_once ROOT_PATH.'/includes/session_manager.php';

// 检查登录
if (!is_h5_logged_in()) {
    header("Location: login.php");
    exit;
}

// 获取客户端真实IP地址
function getClientIP() {
    $ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ipList[0]);
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    $ip = filter_var($ip, FILTER_VALIDATE_IP);
    return $ip ? $ip : '0.0.0.0';
}

$payment_ip = getClientIP();

// 数据库连接
try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", 
                 DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $stmt = $db->prepare("SELECT id, user_idu, nickname, balance FROM h5user WHERE id = ? LIMIT 1");
    $stmt->execute([get_h5_user_id()]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("用户信息获取失败");
    }

    $user_id = $user['id'];
    $user_idu = $user['user_idu'];
    $user_nickname = $user['nickname'];
    $user_balance = $user['balance'];

} catch (PDOException $e) {
    die("数据库错误: ".$e->getMessage());
} catch (Exception $e) {
    die($e->getMessage());
}

// ==================== 订单数据初始化 ====================
$transactionType = 'order';
$orderNo = '';
$productPrice = 0;
$productName = '';
$productId = 0;
$gameRegion = '';
$hunterOption = 'system';
$selectedHunterId = 0;
$categoryId = null;
$categoryName = null;
$subcategoryId = null;

// 抽奖订单专属字段
$game_area = '';
$game_nickname = '';
$game_id = '';
$isLottery = false;

// ==================== 接收交易数据 ====================

// 场景1：来自 lottery.php 的 POST 提交（抽奖订单）
if (isset($_POST['order_id']) && isset($_POST['type']) && $_POST['type'] === 'lottery') {
    $isLottery = true;
    $transactionType = 'lottery';
    $orderNo = htmlspecialchars($_POST['order_id']);
    $productPrice = floatval($_POST['amount'] ?? 0);
    $productName = isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '幸运盲盒抽奖';

    // 如果前端未传金额或金额为0，从数据库读取最新设置
    if ($productPrice <= 0) {
        try {
            $lottery_stmt = $db->query("SELECT payment_amount FROM lottery_settings WHERE id = 1");
            $lottery_settings_pay = $lottery_stmt->fetch();
            if ($lottery_settings_pay && floatval($lottery_settings_pay['payment_amount']) > 0) {
                $productPrice = floatval($lottery_settings_pay['payment_amount']);
            }
        } catch (Exception $e) {
            error_log('Payment select lottery amount fallback error: ' . $e->getMessage());
        }
    }

    $game_area = isset($_POST['game_area']) ? htmlspecialchars($_POST['game_area']) : '';
    $game_nickname = isset($_POST['game_nickname']) ? htmlspecialchars($_POST['game_nickname']) : '';
    $game_id = isset($_POST['game_id']) ? htmlspecialchars($_POST['game_id']) : '';

    $gameRegion = $game_area;
    $hunterOption = 'system';
    $selectedHunterId = 0;
    $productId = 0;
    $categoryId = null;
    $subcategoryId = null;

    $inputFields = [
        '订单类型' => '幸运盲盒抽奖',
        '游戏大区' => $game_area,
        '游戏昵称' => $game_nickname,
        '游戏ID' => $game_id
    ];

// 场景2：来自 lottery.php 的 GET 提交（抽奖订单，备用）
} elseif (isset($_GET['order_id']) && isset($_GET['type']) && $_GET['type'] === 'lottery') {
    $isLottery = true;
    $transactionType = 'lottery';
    $orderNo = htmlspecialchars($_GET['order_id']);
    $productPrice = floatval($_GET['amount'] ?? 0);
    $productName = isset($_GET['description']) ? htmlspecialchars($_GET['description']) : '幸运盲盒抽奖';

    // 如果前端未传金额或金额为0，从数据库读取最新设置
    if ($productPrice <= 0) {
        try {
            $lottery_stmt = $db->query("SELECT payment_amount FROM lottery_settings WHERE id = 1");
            $lottery_settings_pay = $lottery_stmt->fetch();
            if ($lottery_settings_pay && floatval($lottery_settings_pay['payment_amount']) > 0) {
                $productPrice = floatval($lottery_settings_pay['payment_amount']);
            }
        } catch (Exception $e) {
            error_log('Payment select lottery amount fallback error: ' . $e->getMessage());
        }
    }

    $game_area = isset($_GET['game_area']) ? htmlspecialchars($_GET['game_area']) : '';
    $game_nickname = isset($_GET['game_nickname']) ? htmlspecialchars($_GET['game_nickname']) : '';
    $game_id = isset($_GET['game_id']) ? htmlspecialchars($_GET['game_id']) : '';

    $gameRegion = $game_area;
    $hunterOption = 'system';
    $selectedHunterId = 0;
    $productId = 0;
    $categoryId = null;
    $subcategoryId = null;

    $inputFields = [
        '订单类型' => '幸运盲盒抽奖',
        '游戏大区' => $game_area,
        '游戏昵称' => $game_nickname,
        '游戏ID' => $game_id
    ];

// 场景3：已有临时订单的 GET 请求（普通订单回调或重入）
} elseif (isset($_GET['order_no'])) {
    $orderNo = htmlspecialchars($_GET['order_no']);
    $productPrice = floatval($_GET['amount']);

    try {
        $stmt = $db->prepare("
            SELECT product_name, game_region, hunter_option, hunter_id, 
                   digital_id, chinese_id, notes, category_id, subcategory_id,
                   input_fields
            FROM order_temp 
            WHERE order_no = ? AND user_id = ?
        ");
        $stmt->execute([$orderNo, $user_id]);
        $tempOrder = $stmt->fetch();

        if ($tempOrder) {
            $productName = $tempOrder['product_name'];
            $gameRegion = $tempOrder['game_region'];
            $hunterOption = $tempOrder['hunter_option'];
            $selectedHunterId = $tempOrder['hunter_id'];
            $categoryId = $tempOrder['category_id'];
            $subcategoryId = $tempOrder['subcategory_id'];

            // 通过 notes 中的标记识别抽奖订单
            if (!empty($tempOrder['notes']) && strpos($tempOrder['notes'], '幸运盲盒抽奖') !== false) {
                $isLottery = true;
                $transactionType = 'lottery';
            }

            // 尝试从 input_fields 恢复游戏信息
            if ($isLottery && !empty($tempOrder['input_fields'])) {
                $savedFields = json_decode($tempOrder['input_fields'], true);
                if (is_array($savedFields)) {
                    $game_area = $savedFields['游戏大区'] ?? '';
                    $game_nickname = $savedFields['游戏昵称'] ?? '';
                    $game_id = $savedFields['游戏ID'] ?? '';
                }
            }

            $digital_id = $tempOrder['digital_id'];
            $chinese_id = $tempOrder['chinese_id'];
            $notes = $tempOrder['notes'];

            $inputFields = [
                '数字ID' => $digital_id,
                '中文ID' => $chinese_id,
                '备注' => $notes
            ];
        } else {
            throw new Exception("临时订单信息获取失败");
        }
    } catch (PDOException $e) {
        die("获取订单信息失败: " . $e->getMessage());
    }

    $productId = 0;

// 场景4：传统 POST 支付流程（普通商品订单 / 充值）
} else {
    $transactionType = isset($_POST['type']) ? htmlspecialchars($_POST['type']) : 'order';
    $productId = isset($_POST['productId']) ? intval($_POST['productId']) : 0;
    $productName = isset($_POST['productName']) ? htmlspecialchars($_POST['productName']) : ($transactionType == 'recharge' ? '账户余额充值' : '');
    $productPrice = isset($_POST['productPrice']) ? floatval($_POST['productPrice']) : 0;
    $orderNo = isset($_POST['orderNo']) ? htmlspecialchars($_POST['orderNo']) : date("YmdHis").mt_rand(100,999);
    $gameRegion = isset($_POST['gameRegion']) ? htmlspecialchars($_POST['gameRegion']) : '';
    $hunterOption = isset($_POST['hunterOption']) ? htmlspecialchars($_POST['hunterOption']) : 'system';
    $selectedHunterId = isset($_POST['selectedHunterId']) ? intval($_POST['selectedHunterId']) : 0;

    $inputFields = [
        'chinese_id' => isset($_POST['chinese_id']) ? htmlspecialchars($_POST['chinese_id']) : '',
        'numeric_id' => isset($_POST['numeric_id']) ? htmlspecialchars($_POST['numeric_id']) : '',
        'remark' => isset($_POST['remark']) ? htmlspecialchars($_POST['remark']) : '',
        '备注' => isset($_POST['remark']) ? htmlspecialchars($_POST['remark']) : ''
    ];
}

// ==================== 获取商品分类信息 ====================
if ($productId > 0 && !$isLottery) {
    try {
        $stmt = $db->prepare("
            SELECT 
                p.category_id,
                pc.name as category_name,
                pc_parent.id as parent_category_id,
                pc_parent.name as parent_category_name
            FROM products p
            LEFT JOIN product_categories pc ON p.category_id = pc.id
            LEFT JOIN product_categories pc_parent ON pc.parent_id = pc_parent.id
            WHERE p.id = ?
        ");
        $stmt->execute([$productId]);
        $productInfo = $stmt->fetch();

        if ($productInfo) {
            if ($productInfo['parent_category_id']) {
                $categoryId = $productInfo['parent_category_id'];
                $categoryName = $productInfo['parent_category_name'];
            } else {
                $categoryId = $productInfo['category_id'];
                $categoryName = $productInfo['category_name'];
            }
        }
    } catch (PDOException $e) {
        error_log("获取商品分类信息失败: ".$e->getMessage());
    }
}

// ==================== 创建/更新临时订单 ====================
if (!isset($_GET['order_no'])) {
    try {
        // 对于抽奖订单，将游戏信息映射到合适的字段
        if ($isLottery) {
            $digital_id = $game_id;           // 游戏ID存入数字ID字段
            $chinese_id = $game_nickname;    // 游戏昵称存入中文ID字段
            $notes = '【幸运盲盒抽奖】大区:' . $game_area . ' | ID:' . $game_id . ' | 昵称:' . $game_nickname;
        } else {
            $digital_id = isset($inputFields['numeric_id']) ? $inputFields['numeric_id'] : (isset($inputFields['digital_id']) ? $inputFields['digital_id'] : '');
            $chinese_id = isset($inputFields['chinese_id']) ? $inputFields['chinese_id'] : '';
            $notes = isset($inputFields['备注']) ? $inputFields['备注'] : (isset($inputFields['remark']) ? $inputFields['remark'] : '');
        }

        // 先检查是否已存在该订单号的临时记录（防止重复创建）
        $checkStmt = $db->prepare("SELECT id FROM order_temp WHERE order_no = ? AND user_id = ?");
        $checkStmt->execute([$orderNo, $user_id]);
        $exists = $checkStmt->fetch();

        if ($exists) {
            // 更新已有记录（只更新存在的字段，不碰 type）
            $sql = "UPDATE order_temp SET
                product_id = ?, product_name = ?, price = ?, payment_amount = ?,
                game_region = ?, hunter_option = ?, hunter_id = ?, input_fields = ?,
                category_id = ?, category_name = ?, user_idu = ?, user_nickname = ?,
                payment_ip = ?, digital_id = ?, chinese_id = ?, notes = ?, trade_no = ?
                WHERE order_no = ? AND user_id = ?";

            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                $productId, $productName, $productPrice, $productPrice,
                $gameRegion, $hunterOption, $selectedHunterId, json_encode($inputFields, JSON_UNESCAPED_UNICODE),
                $categoryId, $categoryName, $user_idu, $user_nickname,
                $payment_ip, $digital_id, $chinese_id, $notes, $orderNo,
                $orderNo, $user_id
            ]);
        } else {
            // 创建新记录（不插入 type 字段）
            $sql = "INSERT INTO order_temp (
                order_no, product_id, product_name, price, 
                payment_amount, game_region, hunter_option, hunter_id, input_fields,
                category_id, category_name,
                user_id, user_idu, user_nickname, payment_ip,
                digital_id, chinese_id, notes, trade_no,
                create_time, expire_time
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 15 MINUTE))";

            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                $orderNo,
                $productId,
                $productName,
                $productPrice,
                $productPrice,
                $gameRegion,
                $hunterOption,
                $selectedHunterId,
                json_encode($inputFields, JSON_UNESCAPED_UNICODE),
                $categoryId,
                $categoryName,
                $user_id,
                $user_idu,
                $user_nickname,
                $payment_ip,
                $digital_id,
                $chinese_id,
                $notes,
                $orderNo
            ]);
        }

        if (!$result) {
            throw new Exception("创建/更新临时订单失败");
        }

    } catch (PDOException $e) {
        die("创建订单时出错: " . $e->getMessage());
    } catch (Exception $e) {
        die($e->getMessage());
    }
}

// 检查余额是否足够支付
$balance_enough = ($user_balance >= $productPrice);

// 获取系统设置
$settingsStmt = $db->query("SELECT * FROM settings WHERE id = 1");
$settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);

// 设置支付倒计时
$expireTime = time() + 900;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>收银台</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;600;700&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --primary-bg: url('../assets/1.png');
            --card-bg: rgba(255, 255, 255, 0.5);
            --text-primary: #333333;
            --text-secondary: #666666;
            --text-tertiary: #999999;
            --accent-gold: #ffcc00;
            --accent-dark: #1a1a1a;
            --border-color: rgba(255, 255, 255, 0.3);
        }

        body {
            font-family: 'Noto Sans SC', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--primary-bg);
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: var(--text-primary);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            padding-bottom: 100px;
            position: relative;
        }

        .page-header {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 56px;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .nav-left {
            position: absolute;
            left: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-btn {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 16px;
        }

        .page-title {
            font-size: 17px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .main-content {
            padding: 72px 12px 20px;
            position: relative;
            z-index: 1;
        }

        .card {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid var(--border-color);
        }

        .payment-amount {
            text-align: center;
            padding: 24px;
            background: rgba(255, 249, 230, 0.9);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-radius: 16px;
            margin-bottom: 12px;
            border: 1px solid rgba(255, 204, 0, 0.3);
        }

        .amount-label { font-size: 14px; color: var(--text-secondary); margin-bottom: 8px; }
        .amount-value { font-size: 36px; font-weight: 700; color: #ff4444; }
        .amount-value span { font-size: 16px; }

        .countdown {
            text-align: center;
            font-size: 13px;
            color: #ff9800;
            margin-bottom: 12px;
            padding: 12px;
            background: rgba(255, 243, 224, 0.9);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-radius: 12px;
            border: 1px solid rgba(255, 152, 0, 0.3);
        }

        .card-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title i { color: var(--text-secondary); font-size: 16px; }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .info-row:last-child { border-bottom: none; }
        .info-label { font-size: 14px; color: var(--text-secondary); }
        .info-value {
            font-size: 14px;
            color: var(--text-primary);
            font-weight: 500;
            text-align: right;
            max-width: 60%;
            word-break: break-all;
        }

        .lottery-badge {
            display: inline-block;
            background: linear-gradient(135deg, #ffcc00, #ff9900);
            color: #000;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 6px;
        }

        .option-tabs { display: flex; flex-direction: column; gap: 12px; }

        .option-tab {
            display: flex;
            align-items: center;
            padding: 16px;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 2px solid transparent;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .option-tab:hover { background: rgba(255, 255, 255, 0.7); }
        .option-tab.active { background: rgba(255, 255, 255, 0.9); border-color: var(--accent-dark); }

        .option-tab input[type="radio"] {
            appearance: none;
            width: 20px; height: 20px;
            border: 2px solid #e0e0e0;
            border-radius: 50%;
            margin-right: 12px;
            position: relative;
            cursor: pointer;
            flex-shrink: 0;
        }

        .option-tab input[type="radio"]:checked { border-color: var(--accent-dark); }
        .option-tab input[type="radio"]:checked::after {
            content: '';
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 10px; height: 10px;
            background: var(--accent-dark);
            border-radius: 50%;
        }
        .option-tab input[type="radio"]:disabled { opacity: 0.4; cursor: not-allowed; }

        .option-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-right: 12px;
            flex-shrink: 0;
        }

        .option-icon.wechat { background: #e8f5e9; color: #07c160; }
        .option-icon.alipay { background: #e3f2fd; color: #1677ff; }
        .option-icon.balance { background: #f5f5f5; color: var(--text-primary); }

        .option-info { flex: 1; }
        .option-name { font-size: 15px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px; }
        .option-desc { font-size: 12px; color: var(--text-tertiary); }
        .balance-status { font-size: 12px; margin-top: 4px; }
        .balance-enough { color: #4caf50; }
        .balance-not-enough { color: #ff4444; }

        .agreement {
            display: flex;
            align-items: center;
            margin: 20px 0;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .agreement input[type="checkbox"] {
            appearance: none;
            width: 20px; height: 20px;
            border: 2px solid rgba(0, 0, 0, 0.2);
            border-radius: 6px;
            margin-right: 10px;
            position: relative;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.5);
            flex-shrink: 0;
        }

        .agreement input[type="checkbox"]:checked {
            border-color: var(--accent-dark);
            background: var(--accent-dark);
        }
        .agreement input[type="checkbox"]:checked::after {
            content: '✓';
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            color: #ffffff;
            font-size: 12px;
            font-weight: 700;
        }

        .pay-button {
            display: block;
            width: 100%;
            padding: 16px;
            background: var(--accent-gold);
            color: var(--text-primary);
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .pay-button:hover:not(:disabled) { opacity: 0.9; }
        .pay-button:disabled { opacity: 0.5; cursor: not-allowed; background: #d9d9d9; }
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

    <div class="page-header">
        <div class="nav-left">
            <a href="javascript:history.back()" class="nav-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <a href="index.php" class="nav-btn">
                <i class="fas fa-home"></i>
            </a>
        </div>
        <div class="page-title">收银台</div>
    </div>

    <main class="main-content">
        <div class="payment-amount">
            <div class="amount-label">支付金额</div>
            <div class="amount-value"><span>¥</span><?php echo number_format($productPrice, 2); ?></div>
        </div>

        <div class="countdown" id="countdown">
            <i class="fas fa-clock"></i> 支付剩余时间 15:00
        </div>

        <div class="card">
            <div class="card-title">
                <i class="fas fa-receipt"></i> 
                订单信息
                <?php if ($isLottery): ?>
                    <span class="lottery-badge">盲盒抽奖</span>
                <?php endif; ?>
            </div>
            <div class="info-row">
                <span class="info-label">商品名称</span>
                <span class="info-value"><?php echo htmlspecialchars($productName); ?></span>
            </div>
            <?php if ($categoryName && !$isLottery): ?>
            <div class="info-row">
                <span class="info-label">商品分类</span>
                <span class="info-value"><?php echo htmlspecialchars($categoryName); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($gameRegion): ?>
            <div class="info-row">
                <span class="info-label">游戏大区</span>
                <span class="info-value"><?php echo htmlspecialchars($gameRegion); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($isLottery && $game_nickname): ?>
            <div class="info-row">
                <span class="info-label">游戏昵称</span>
                <span class="info-value"><?php echo htmlspecialchars($game_nickname); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($isLottery && $game_id): ?>
            <div class="info-row">
                <span class="info-label">游戏ID</span>
                <span class="info-value"><?php echo htmlspecialchars($game_id); ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span class="info-label">订单编号</span>
                <span class="info-value"><?php echo htmlspecialchars($orderNo); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">账户余额</span>
                <span class="info-value">¥<?php echo number_format($user_balance, 2); ?></span>
            </div>
        </div>

        <div class="card">
            <div class="card-title">
                <i class="fas fa-credit-card"></i> 选择支付方式
            </div>

            <form id="paymentForm" method="post">
                <!-- 核心交易信息 -->
                <input type="hidden" name="WIDout_trade_no" value="<?php echo $orderNo; ?>">
                <input type="hidden" name="WIDsubject" value="<?php echo $productName; ?>">
                <input type="hidden" name="WIDtotal_fee" value="<?php echo $productPrice; ?>">
                <input type="hidden" name="productId" value="<?php echo $productId; ?>">
                <input type="hidden" name="gameRegion" value="<?php echo $gameRegion; ?>">
                <input type="hidden" name="hunterOption" value="<?php echo $hunterOption; ?>">
                <input type="hidden" name="selectedHunterId" value="<?php echo $selectedHunterId; ?>">
                <input type="hidden" name="categoryId" value="<?php echo $categoryId; ?>">
                <input type="hidden" name="categoryName" value="<?php echo htmlspecialchars($categoryName); ?>">
                <input type="hidden" name="inputFields" value="<?php echo htmlspecialchars(json_encode($inputFields)); ?>">
                <input type="hidden" name="payment_ip" value="<?php echo htmlspecialchars($payment_ip); ?>">
                <input type="hidden" name="transactionType" value="<?php echo $transactionType; ?>">

                <?php if ($isLottery): ?>
                <!-- 抽奖订单专属隐藏字段：支付成功后需回传给抽奖接口 -->
                <input type="hidden" name="game_area" value="<?php echo htmlspecialchars($game_area); ?>">
                <input type="hidden" name="game_nickname" value="<?php echo htmlspecialchars($game_nickname); ?>">
                <input type="hidden" name="game_id" value="<?php echo htmlspecialchars($game_id); ?>">
                <input type="hidden" name="lottery_order" value="1">
                <?php endif; ?>

                <div class="option-tabs">
                    <?php if ($settings['payment_method'] === 'wechat' && $settings['wechat_pay_enabled']): ?>
                    <label class="option-tab active">
                        <input type="radio" name="type" value="wechat_official" checked>
                        <div class="option-icon wechat">
                            <i class="fab fa-weixin"></i>
                        </div>
                        <div class="option-info">
                            <div class="option-name">微信支付（官方）</div>
                            <div class="option-desc">直接使用微信官方支付</div>
                        </div>
                    </label>
                    <?php else: ?>
                    <label class="option-tab active">
                        <input type="radio" name="type" value="wxpay" checked>
                        <div class="option-icon wechat">
                            <i class="fab fa-weixin"></i>
                        </div>
                        <div class="option-info">
                            <div class="option-name">微信支付</div>
                            <div class="option-desc">推荐微信用户使用</div>
                        </div>
                    </label>

                    <label class="option-tab">
                        <input type="radio" name="type" value="alipay">
                        <div class="option-icon alipay">
                            <i class="fab fa-alipay"></i>
                        </div>
                        <div class="option-info">
                            <div class="option-name">支付宝支付</div>
                            <div class="option-desc">推荐支付宝用户使用</div>
                        </div>
                    </label>
                    <?php endif; ?>

                    <?php if ($transactionType !== 'recharge'): ?>
                    <label class="option-tab">
                        <input type="radio" name="type" value="balance" <?php echo !$balance_enough ? 'disabled' : ''; ?>>
                        <div class="option-icon balance">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="option-info">
                            <div class="option-name">余额支付</div>
                            <div class="option-desc">使用账户余额支付</div>
                            <div class="balance-status <?php echo $balance_enough ? 'balance-enough' : 'balance-not-enough'; ?>">
                                当前余额: ¥<?php echo number_format($user_balance, 2); ?>
                                <?php if(!$balance_enough): ?>(余额不足)<?php endif; ?>
                            </div>
                        </div>
                    </label>
                    <?php endif; ?>
                </div>

                <div class="agreement">
                    <input type="checkbox" id="agreementCheck" required>
                    <label for="agreementCheck">本平台服务仅限成年人，本人确认已满18岁</label>
                </div>

                <button type="submit" class="pay-button" id="submitBtn">
                    立即支付 ¥<?php echo number_format($productPrice, 2); ?>
                </button>
            </form>
        </div>
    </main>

    <?php include 'footer.php'; ?>

    <script>
        // 支付倒计时
        function startCountdown() {
            var endTime = <?php echo $expireTime; ?> * 1000;
            var timer = setInterval(function() {
                var now = new Date().getTime();
                var distance = endTime - now;

                var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                var seconds = Math.floor((distance % (1000 * 60)) / 1000);

                document.getElementById("countdown").innerHTML = 
                    '<i class="fas fa-clock"></i> 支付剩余时间 ' + 
                    (minutes < 10 ? "0" + minutes : minutes) + ":" + 
                    (seconds < 10 ? "0" + seconds : seconds);

                if (distance < 0) {
                    clearInterval(timer);
                    document.getElementById("countdown").innerHTML = '<i class="fas fa-exclamation-circle"></i> 支付已超时';
                    document.getElementById("submitBtn").disabled = true;
                }
            }, 1000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            startCountdown();

            document.querySelectorAll('input[name="type"]').forEach(function(radio) {
                radio.addEventListener('change', function() {
                    document.querySelectorAll('.option-tab').forEach(function(label) {
                        label.classList.remove('active');
                    });
                    this.closest('.option-tab').classList.add('active');

                    if (this.value === 'balance' && this.disabled) {
                        alert('余额不足，请选择其他支付方式');
                        document.querySelector('input[type="radio"][value="wxpay"]').checked = true;
                        document.querySelector('input[type="radio"][value="wxpay"]').closest('.option-tab').classList.add('active');
                    }
                });
            });

            document.getElementById('paymentForm').addEventListener('submit', function(e) {
                e.preventDefault();

                const paymentType = document.querySelector('input[name="type"]:checked').value;
                const transactionType = document.querySelector('input[name="transactionType"]').value;

                if (transactionType === 'recharge' && paymentType === 'balance') {
                    alert('账户充值不能使用余额支付！请选择其他支付方式。');
                    return;
                }

                const submitBtn = document.getElementById('submitBtn');
                submitBtn.innerHTML = '处理中...';
                submitBtn.disabled = true;

                if (paymentType === 'balance') {
                    this.action = 'yezf.php';
                    this.submit();
                } else if (paymentType === 'wechat_official') {
                    this.action = '../wechat_pay.php';
                    this.method = 'post';
                    this.submit();
                } else {
                    this.action = 'yhpay/epayapi.php';
                    this.method = 'post';
                    this.submit();
                }
            });
        });
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
