<?php
require '../includes/session_manager.php';
require '../config.php';

if (!is_h5_logged_in()) {
    header("Location: login.php");
    exit;
}
$user_id = get_h5_user_id();

try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $userStmt = $db->prepare("SELECT nickname FROM h5user WHERE id = ? LIMIT 1");
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch();

    $orderStmt = $db->prepare("SELECT COUNT(*) as total_orders, IFNULL(SUM(price),0) as total_spend FROM orders WHERE user_id = ?");
    $orderStmt->execute([$user_id]);
    $orderData = $orderStmt->fetch();
    $totalOrders = intval($orderData['total_orders']);
    $totalSpend = floatval($orderData['total_spend']);

} catch (PDOException $e) {
    die("数据加载失败：" . htmlspecialchars($e->getMessage()));
}

// 等级配置 仅保留名称+升级条件
$levelList = [
    ['level' => 1, 'name' => '青铜老板', 'order' => 0, 'spend' => 0],
    ['level' => 2, 'name' => '黄金老板', 'order' => 10, 'spend' => 100],
    ['level' => 3, 'name' => '星耀老板', 'order' => 50, 'spend' => 500],
    ['level' => 4, 'name' => '钻石老板', 'order' => 100, 'spend' => 800],
    ['level' => 5, 'name' => '至尊老板', 'order' => 100, 'spend' => 1000]
];

// 精准等级判定 消费优先
if ($totalSpend >= 1000) {
    $currLevelIndex = 4;
} elseif ($totalSpend >= 800) {
    $currLevelIndex = 3;
} elseif ($totalSpend >= 500) {
    $currLevelIndex = 2;
} elseif ($totalSpend >= 100) {
    $currLevelIndex = 1;
} elseif ($totalOrders >= 100) {
    $currLevelIndex = 3;
} elseif ($totalOrders >= 50) {
    $currLevelIndex = 2;
} elseif ($totalOrders >= 10) {
    $currLevelIndex = 1;
} else {
    $currLevelIndex = 0;
}
$currLevelInfo = $levelList[$currLevelIndex];
$nextLevelInfo = $currLevelIndex < 4 ? $levelList[$currLevelIndex + 1] : null;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>会员等级</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: #f5f7fa;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            padding: 15px;
            padding-bottom: 75px;
        }
        .page-title {
            font-size: 20px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 20px;
            color: #222;
        }
        .curr-level-card {
            background: linear-gradient(135deg, #d4a84b, #b88f35);
            border-radius: 16px;
            padding: 20px;
            color: #fff;
            text-align: center;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(212, 168, 75, 0.25);
        }
        .curr-level-name {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .curr-data {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        .next-tip {
            font-size: 13px;
            background: rgba(255,255,255,0.2);
            padding: 6px 12px;
            border-radius: 12px;
            display: inline-block;
        }
        .level-item {
            background: #fff;
            border-radius: 12px;
            padding: 18px 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .level-name {
            font-size: 18px;
            font-weight: 600;
            color: #222;
        }
        .level-condition {
            font-size: 13px;
            color: #999;
        }
        .current-tag {
            background: #d4a84b;
            color: #fff;
            font-size: 11px;
            padding: 3px 10px;
            border-radius: 12px;
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

    <div class="curr-level-card">
        <div class="curr-level-name"><?php echo $currLevelInfo['name']; ?></div>
        <div class="curr-data">
            累计订单：<?php echo $totalOrders; ?> 单 &nbsp;&nbsp; 累计消费：¥<?php echo number_format($totalSpend, 2); ?>
        </div>
        <?php if ($nextLevelInfo): ?>
        <div class="next-tip">
            升级下一等级：<?php echo $nextLevelInfo['name']; ?>
            <br>
            还需订单≥<?php echo $nextLevelInfo['order']; ?> 或 消费≥¥<?php echo $nextLevelInfo['spend']; ?>
        </div>
        <?php else: ?>
        <div class="next-tip">已达最高等级，尊享全部特权</div>
        <?php endif; ?>
    </div>

    <?php foreach ($levelList as $item): ?>
    <div class="level-item">
        <div class="level-name"><?php echo $item['name']; ?></div>
        <div>
            <?php if ($item['level'] == $currLevelInfo['level']): ?>
                <span class="current-tag">当前等级</span>
            <?php else: ?>
                <span class="level-condition">订单≥<?php echo $item['order']; ?> / 消费≥¥<?php echo $item['spend']; ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

<?php include 'footer.php'; ?>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>