<?php
require '../includes/session_manager.php';
require '../config.php';

$user_id = get_h5_user_id() ? get_h5_user_id() : 0;
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($product_id < 1){
    die("<script>alert('商品不存在'); history.back();</script>");
}

try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
                 DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    if($user_id > 0) {
        $stmt = $db->prepare("SELECT id FROM h5user WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if(!$user) { clear_h5_session(); $user_id = 0; }
    }

    if($user_id < 1) {
        $_SESSION['return_url'] = $_SERVER['REQUEST_URI'];
        header("Location: login.php");
        exit;
    }

    $stmt = $db->prepare("SELECT p.*, pc.name AS category_name FROM products p
                          LEFT JOIN product_categories pc ON p.category_id = pc.id
                          WHERE p.id = ? AND p.status = 1");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if(empty($product)){
        die("<script>alert('商品不存在或已下架'); history.back();</script>");
    }

    $images = json_decode($product['images'] ?? '[]', true);
    if(empty($images)) { $images = ['assets/images/default-product.png']; }

    $original_price = $product['original_price'] > $product['price'] ? $product['original_price'] : 0;
    $sold_count = $product['virtual_sales'] > 0 ? $product['virtual_sales'] : $product['sold_quantity'];
    $tags = json_decode($product['tags'] ?? '[]', true);
    if(empty($tags)) { $tags = ['火爆推荐']; }

    $is_lottery = $product['is_lottery'] == 1;

    $lottery_prizes = [];
    if ($is_lottery) {
        $stmt = $db->prepare("SELECT * FROM lottery_prizes WHERE product_id = ? AND status = 1 ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$product_id]);
        $lottery_prizes = $stmt->fetchAll();
        if (empty($lottery_prizes)) {
            $stmt = $db->query("SELECT * FROM lottery_prizes WHERE (product_id IS NULL OR product_id = 0) AND status = 1 ORDER BY sort_order ASC, id ASC LIMIT 8");
            $lottery_prizes = $stmt->fetchAll();
        }
    }

} catch(PDOException $e) {
    die("<script>alert('系统错误，请稍后再试'); history.back();</script>");
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($product['name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="stylesheet" href="../assets/css/theme-v2.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'PingFang SC', sans-serif;
            background: #f0f5ff; color: #1e293b; line-height: 1.5;
            padding-bottom: 80px; animation: fadeInUp 0.5s ease-out;
        }
        @keyframes fadeInUp { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }
        .app-container { max-width: 100%; margin: 0 auto; }
        .swiper-container { width: 100%; background: #fff; margin-bottom: 10px; position: relative; }
        .swiper { width: 100%; aspect-ratio: 1 / 1; }
        .product-img { width: 100%; height: 100%; object-fit: contain; }
        .swiper-pagination-bullet { background: rgba(59,130,246,0.3); opacity: 1; }
        .swiper-pagination-bullet-active { background: #3b82f6; width: 20px; border-radius: 8px; }
        .lottery-tag {
            position: absolute; top: 12px; left: 12px; z-index: 5;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #fff; padding: 5px 12px; border-radius: 20px;
            font-size: 12px; font-weight: 700;
            box-shadow: 0 2px 8px rgba(245,158,11,0.4);
            display: flex; align-items: center; gap: 4px;
            animation: tagPulse 2s ease-in-out infinite;
        }
        @keyframes tagPulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.05); } }
        .card {
            background: #fff; border-radius: 16px; padding: 18px;
            margin: 0 12px 12px; box-shadow: 0 2px 10px rgba(59,130,246,0.06);
            border: 1px solid rgba(59,130,246,0.06);
        }
        .price-section { display: flex; align-items: baseline; margin-bottom: 10px; }
        .price { color: #2563eb; font-size: 26px; font-weight: 800; line-height: 1; display: flex; align-items: baseline; }
        .price-symbol { font-size: 16px; margin-right: 2px; font-weight: 600; }
        .original-price { font-size: 13px; color: #94a3b8; text-decoration: line-through; margin-left: 10px; }
        .sold-count { font-size: 12px; color: #94a3b8; margin-left: auto; }
        .product-name { font-size: 18px; font-weight: 700; margin-bottom: 6px; color: #1e293b; line-height: 1.3; }
        .chinese-name { font-size: 13px; color: #64748b; margin-bottom: 12px; }
        .tags { display: flex; flex-wrap: wrap; gap: 6px; }
        .tag { padding: 4px 10px; background: #eff6ff; color: #2563eb; font-size: 11px; font-weight: 600; border-radius: 12px; }
        .tag.lottery { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #b45309; }
        .prize-section { margin: 0 12px 12px; }
        .prize-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; padding: 0 4px; }
        .prize-title { font-size: 15px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 6px; }
        .prize-title i { color: #f59e0b; }
        .prize-badge { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: 600; }
        .prize-scroll {
            background: #fff; border-radius: 14px; padding: 12px;
            box-shadow: 0 2px 10px rgba(59,130,246,0.06);
            border: 1px solid rgba(59,130,246,0.06);
            overflow: hidden; position: relative;
        }
        .prize-scroll::before, .prize-scroll::after {
            content: ''; position: absolute; top: 0; bottom: 0; width: 30px; z-index: 2; pointer-events: none;
        }
        .prize-scroll::before { left: 0; background: linear-gradient(90deg, #fff, transparent); }
        .prize-scroll::after { right: 0; background: linear-gradient(270deg, #fff, transparent); }
        .prize-track { display: flex; gap: 10px; animation: prizeScroll 20s linear infinite; width: max-content; }
        .prize-scroll:hover .prize-track { animation-play-state: paused; }
        @keyframes prizeScroll { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }
        .prize-item { flex-shrink: 0; width: 80px; text-align: center; }
        .prize-img {
            width: 60px; height: 60px; border-radius: 12px;
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 6px; font-size: 24px; border: 2px solid #bfdbfe; overflow: hidden;
        }
        .prize-img img { width: 100%; height: 100%; object-fit: cover; }
        .prize-name { font-size: 11px; color: #475569; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .desc-title { font-size: 15px; font-weight: 700; margin-bottom: 12px; color: #1e293b; display: flex; align-items: center; gap: 8px; }
        .desc-title i { color: #3b82f6; }
        .desc-content { font-size: 14px; line-height: 1.7; color: #475569; }
        .desc-content img { max-width: 100%; height: auto; border-radius: 10px; margin: 10px 0; }
        .desc-content p { margin-bottom: 10px; }
        .action-bar {
            position: fixed; bottom: 0; left: 0; right: 0; background: #fff;
            display: flex; align-items: center; padding: 10px 14px; gap: 12px; z-index: 100;
            border-top: 1px solid #e2e8f0; padding-bottom: calc(10px + env(safe-area-inset-bottom));
        }
        .action-left { flex-shrink: 0; display: flex; flex-direction: column; align-items: center; text-decoration: none; color: #475569; gap: 2px; }
        .action-left i { font-size: 20px; color: #3b82f6; }
        .action-left span { font-size: 10px; font-weight: 500; }
        .action-bar form { flex: 1; margin: 0; }
        .buy-btn {
            width: 100%; height: 44px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: #fff; border: none; border-radius: 22px;
            font-size: 15px; font-weight: 700; cursor: pointer;
            transition: all 0.2s; box-shadow: 0 4px 14px rgba(59,130,246,0.3); letter-spacing: 1px;
        }
        .buy-btn:active { transform: scale(0.97); box-shadow: 0 2px 8px rgba(59,130,246,0.25); }
        .buy-btn.lottery-btn { background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 4px 14px rgba(245,158,11,0.35); }
        .wheel-modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.75); z-index: 9999;
            justify-content: center; align-items: center; flex-direction: column;
        }
        .wheel-modal.active { display: flex; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .wheel-container { position: relative; width: 280px; height: 280px; margin-bottom: 20px; }
        .wheel-pointer {
            position: absolute; top: -10px; left: 50%; transform: translateX(-50%);
            width: 0; height: 0; z-index: 10;
            border-left: 14px solid transparent; border-right: 14px solid transparent;
            border-top: 24px solid #ef4444; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
        }
        .wheel-canvas {
            width: 100%; height: 100%; border-radius: 50%;
            box-shadow: 0 0 0 8px #fff, 0 0 0 12px #3b82f6, 0 8px 30px rgba(0,0,0,0.3);
            transition: transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99);
        }
        .wheel-center {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            width: 60px; height: 60px; border-radius: 50%;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 800; font-size: 14px;
            box-shadow: 0 4px 12px rgba(245,158,11,0.4); z-index: 5; cursor: pointer;
        }
        .wheel-result {
            background: #fff; border-radius: 16px; padding: 24px; text-align: center;
            max-width: 300px; width: 80%; animation: scaleIn 0.4s cubic-bezier(0.34,1.56,0.64,1);
        }
        @keyframes scaleIn { from { opacity:0; transform:scale(0.8); } to { opacity:1; transform:scale(1); } }
        .wheel-result .icon { font-size: 48px; margin-bottom: 10px; }
        .wheel-result h3 { font-size: 18px; color: #1e293b; margin-bottom: 6px; }
        .wheel-result p { font-size: 14px; color: #64748b; margin-bottom: 16px; }
        .wheel-result .prize-name-text {
            font-size: 20px; font-weight: 800;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text; margin-bottom: 16px;
        }
        .wheel-close {
            padding: 10px 30px; border: none; border-radius: 20px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: #fff; font-size: 14px; font-weight: 600; cursor: pointer;
        }
        .wheel-tip { color: rgba(255,255,255,0.8); font-size: 13px; margin-top: 10px; }
        @media (max-width: 480px) {
            .card { padding: 14px; margin: 0 10px 10px; }
            .price { font-size: 22px; }
            .product-name { font-size: 16px; }
            .wheel-container { width: 240px; height: 240px; }
        }
    </style>
</head>
<body>
<div class="v2-loader-overlay" id="v2Loader">
    <div class="v2-loader-logo-wrap">
        <div class="v2-loader-ring"></div><div class="v2-loader-ring"></div><div class="v2-loader-ring"></div>
        <div class="v2-loader-logo">小</div>
    </div>
    <div class="v2-loader-text"><span>加</span><span>载</span><span>中</span><span>...</span></div>
    <div class="v2-loader-progress"><div class="v2-loader-progress-bar"></div></div>
</div>

<div class="app-container">
    <div class="swiper-container">
        <?php if ($is_lottery): ?>
        <div class="lottery-tag"><i class="fas fa-gift"></i> 抽奖商品</div>
        <?php endif; ?>
        <div class="swiper">
            <div class="swiper-wrapper">
                <?php foreach ($images as $image): ?>
                <div class="swiper-slide">
                    <img src="../<?php echo htmlspecialchars($image); ?>" class="product-img" alt="<?php echo htmlspecialchars($product['name']); ?>">
                </div>
                <?php endforeach; ?>
            </div>
            <div class="swiper-pagination"></div>
        </div>
    </div>

    <div class="card">
        <div class="price-section">
            <div class="price">
                <span class="price-symbol">¥</span><?php echo number_format($product['price'], 2); ?>
                <?php if($original_price > 0): ?>
                <span class="original-price">¥<?php echo number_format($original_price, 2); ?></span>
                <?php endif; ?>
            </div>
            <div class="sold-count">已售<?php echo $sold_count; ?></div>
        </div>
        <h1 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h1>
        <?php if(!empty($product['chinese_name'])): ?>
        <div class="chinese-name"><?php echo htmlspecialchars($product['chinese_name']); ?></div>
        <?php endif; ?>
        <div class="tags">
            <?php if ($is_lottery): ?><span class="tag lottery">🎁 下单抽奖</span><?php endif; ?>
            <?php foreach($tags as $tag): ?>
            <span class="tag"><?php echo htmlspecialchars($tag); ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($is_lottery): ?>
    <div class="prize-section">
        <div class="prize-header">
            <div class="prize-title"><i class="fas fa-gift"></i> 奖品池</div>
            <span class="prize-badge">下单必抽</span>
        </div>
        <div class="prize-scroll">
            <div class="prize-track">
                <?php
                // 如果没有奖品，生成8个空白占位符保持滚动效果
                if (empty($lottery_prizes)) {
                    $lottery_prizes = array_fill(0, 8, ['name' => '敬请期待', 'image' => '']);
                }
                $prize_list = array_merge($lottery_prizes, $lottery_prizes);
                foreach ($prize_list as $p):
                ?>
                <div class="prize-item">
                    <div class="prize-img">
                        <?php if (!empty($p['image'])): ?>
                        <img src="../<?php echo htmlspecialchars($p['image']); ?>" alt="">
                        <?php else: ?>
                        🎁
                        <?php endif; ?>
                    </div>
                    <div class="prize-name"><?php echo htmlspecialchars($p['name']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="desc-title"><i class="fas fa-info-circle"></i> 商品详情</div>
        <div class="desc-content">
            <?php
            $allowed_tags = '<p><a><strong><em><span><div><img><ul><ol><li><br><hr>';
            $clean = strip_tags(htmlspecialchars_decode($product['description']), $allowed_tags);
            echo $clean;
            ?>
        </div>
    </div>
</div>

<div class="action-bar">
    <a href="customer_service.php" class="action-left">
        <i class="fas fa-headset"></i>
        <span>客服</span>
    </a>
    <form id="orderForm" method="get" action="goods.php">
        <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
        <button type="submit" class="buy-btn <?php echo $is_lottery ? 'lottery-btn' : ''; ?>">
            <?php echo $is_lottery ? '立即下单抽奖' : '立即下单'; ?>
        </button>
    </form>
</div>

<div class="wheel-modal" id="wheelModal">
    <div class="wheel-container" id="wheelContainer" style="display:none;">
        <div class="wheel-pointer"></div>
        <canvas class="wheel-canvas" id="wheelCanvas" width="560" height="560"></canvas>
        <div class="wheel-center" id="wheelCenter">抽奖</div>
    </div>
    <div class="wheel-result" id="wheelResult" style="display:none;">
        <div class="icon">🎉</div>
        <h3>恭喜获得</h3>
        <div class="prize-name-text" id="resultPrizeName">-</div>
        <p>奖品将随订单一起发放，请耐心等待</p>
        <button class="wheel-close" onclick="closeWheel()">确定</button>
    </div>
    <div class="wheel-tip" id="wheelTip">正在准备抽奖...</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
var swiper = new Swiper('.swiper', {
    loop: true, autoplay: { delay: 4000, disableOnInteraction: false },
    pagination: { el: '.swiper-pagination', clickable: true }
});

var isLottery = <?php echo $is_lottery ? 'true' : 'false'; ?>;
var productId = <?php echo $product_id; ?>;
var prizes = <?php echo json_encode($lottery_prizes, JSON_UNESCAPED_UNICODE); ?>;

function drawWheel(prizeList) {
    var canvas = document.getElementById('wheelCanvas');
    var ctx = canvas.getContext('2d');
    var centerX = 280, centerY = 280, radius = 270;
    var n = prizeList.length;
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
        var name = prizeList[i].name.length > 6 ? prizeList[i].name.substring(0, 6) : prizeList[i].name;
        ctx.fillText(name, radius - 20, 6);
        ctx.restore();
    }
    ctx.beginPath();
    ctx.arc(centerX, centerY, 50, 0, 2 * Math.PI);
    ctx.fillStyle = '#fff';
    ctx.fill();
}

function spinWheel(prizeList, targetIndex) {
    var canvas = document.getElementById('wheelCanvas');
    var n = prizeList.length;
    var arc = 360 / n;
    var targetAngle = 360 * 5 + (360 - (targetIndex * arc + arc / 2));
    canvas.style.transform = 'rotate(' + targetAngle + 'deg)';
}

function showWheel() {
    if (!isLottery || prizes.length === 0) return;
    var modal = document.getElementById('wheelModal');
    var container = document.getElementById('wheelContainer');
    var result = document.getElementById('wheelResult');
    var tip = document.getElementById('wheelTip');
    container.style.display = 'block';
    result.style.display = 'none';
    tip.style.display = 'block';
    tip.textContent = '点击中心按钮开始抽奖';
    modal.classList.add('active');
    drawWheel(prizes);
    document.getElementById('wheelCenter').onclick = function() {
        this.style.pointerEvents = 'none';
        tip.textContent = '抽奖中...';
        var targetIndex = Math.floor(Math.random() * prizes.length);
        spinWheel(prizes, targetIndex);
        setTimeout(function() {
            container.style.display = 'none';
            result.style.display = 'block';
            tip.style.display = 'none';
            document.getElementById('resultPrizeName').textContent = prizes[targetIndex].name;
        }, 4200);
    };
}

function closeWheel() {
    document.getElementById('wheelModal').classList.remove('active');
}

window.addEventListener('load', function() {
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('lottery_done') === '1') {
        setTimeout(showWheel, 500);
    }
});
</script>
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
