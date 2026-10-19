<?php
include("../includes/common.php");
session_start();
global $pdo;

$topId = isset($_GET['top']) ? intval($_GET['top']) : 0;
$leftId = isset($_GET['left']) ? intval($_GET['left']) : 0;

// 查询一级分类（顶部Tab level=1）
$stmtTop = $pdo->query("SELECT id,name,sort_order FROM product_categories WHERE level=1 AND status=1 ORDER BY sort_order DESC");
$topCats = $stmtTop->fetchAll(PDO::FETCH_ASSOC);
if($topId <= 0 && !empty($topCats)){
    $topId = $topCats[0]['id'];
}

// 查询二级分类（左侧菜单 level=2）
$stmtLeft = $pdo->prepare("SELECT id,name,sort_order FROM product_categories WHERE level=2 AND parent_id=? AND status=1 ORDER BY sort_order DESC");
$stmtLeft->execute([$topId]);
$leftCats = $stmtLeft->fetchAll(PDO::FETCH_ASSOC);
if($leftId <= 0 && !empty($leftCats)){
    $leftId = $leftCats[0]['id'];
}

// 查询当前二级分类商品
$stmtGoods = $pdo->prepare("SELECT id,name,images FROM products WHERE category_id=? AND status=1 ORDER BY sort_order DESC");
$stmtGoods->execute([$leftId]);
$goodsList = $stmtGoods->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>装备分类</title>
    <style>
        *{
            margin:0;padding:0;box-sizing:border-box;
            font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", sans-serif;
            -webkit-tap-highlight-color: transparent;
        }
        body{
            background: linear-gradient(180deg, #fef9f0 0%, #f7f8fc 25%) !important;
            color:#1a1a2e;font-size:13px;padding-bottom:80px;min-height:100vh;
        }
        .top-tab-wrap{
            display:flex;border-bottom:1px solid rgba(0,0,0,0.05);
            background: rgba(255,255,255,0.92);backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);position:sticky;top:0;z-index:100;
            padding: 0 6px;box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }
        .top-tab-item{
            flex:1;padding:14px 4px;font-size:14px;color:#9ca3af;text-align:center;
            text-decoration:none;position:relative;white-space:nowrap;overflow:hidden;
            text-overflow:ellipsis;font-weight:500;transition: all 0.25s ease;
        }
        .top-tab-item.active{color:#1a1a2e;font-weight:800;}
        .top-tab-item.active::after{
            content:"";position:absolute;bottom:0;left:50%;transform:translateX(-50%);
            width:24px;height:3px;background: linear-gradient(90deg, #ffd93d, #ffb347);
            border-radius:3px;box-shadow: 0 2px 8px rgba(255,217,61,0.4);
        }
        .main-container{display:flex;min-height:calc(100vh - 52px);}
        .left-sidebar{width:90px;background: transparent;flex-shrink:0;padding: 12px 0;}
        .left-menu-item{
            padding:14px 8px;font-size:12px;color:#6b7280;text-decoration:none;
            display:block;position:relative;text-align:center;line-height:1.4;
            font-weight:500;transition: all 0.25s ease;margin: 2px 8px;border-radius: 12px;
        }
        .left-menu-item.active{
            color:#1a1a2e;background:#fff;font-weight:700;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .left-menu-item.active::before{
            content:"";position:absolute;left:0;top:50%;transform: translateY(-50%);
            width:4px;height:24px;background: linear-gradient(180deg, #ffd93d, #ffb347);
            border-radius:4px;box-shadow: 0 0 8px rgba(255,217,61,0.5);
        }
        .right-content{flex:1;padding:16px 12px;background: transparent;}
        .section-title{
            font-size:17px;font-weight:800;margin-bottom:14px;color:#1a1a2e;
            padding-left:12px;position:relative;
        }
        .section-title::before{
            content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);
            width:4px;height:18px;background: linear-gradient(180deg, #ffd93d, #ffb347);border-radius:4px;
        }
        .goods-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;}
        .goods-item{
            text-decoration:none;color:#333;animation: goodsIn 0.4s ease backwards;
        }
        .goods-item:nth-child(1){animation-delay:0.03s}
        .goods-item:nth-child(2){animation-delay:0.06s}
        .goods-item:nth-child(3){animation-delay:0.09s}
        .goods-item:nth-child(4){animation-delay:0.12s}
        .goods-item:nth-child(5){animation-delay:0.15s}
        .goods-item:nth-child(6){animation-delay:0.18s}
        @keyframes goodsIn{from{opacity:0;transform:translateY(15px)}to{opacity:1;transform:translateY(0)}}
        .goods-card{
            width:100%;aspect-ratio:1/1;border-radius:14px;overflow:hidden;
            background:linear-gradient(135deg,#fff5d6,#ffe4a3);
            display:flex;align-items:center;justify-content:center;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);transition: all 0.25s ease;
        }
        .goods-item:active .goods-card{transform: scale(0.94);box-shadow: 0 2px 8px rgba(0,0,0,0.1);}
        .goods-card img{width:100%;height:100%;object-fit:cover;transition: transform 0.3s ease;}
        .goods-item:active .goods-card img{transform: scale(1.08);}
        .goods-card-text{font-size:12px;color:#1a1a2e;text-align:center;padding:0 4px;font-weight:600;}
        .goods-name{margin-top:8px;font-size:12px;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#374151;font-weight:600;}
        .empty{padding:60px 20px;text-align:center;color:#9ca3af;font-size:14px;}
        .empty::before{content:'📦';display:block;font-size:48px;margin-bottom:12px;opacity:0.5;}
        @media(max-width:360px){.left-sidebar{width:78px;}.goods-grid{grid-template-columns:repeat(2,1fr);}}
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

<!-- 顶部一级分类Tab -->
<div class="top-tab-wrap">
    <?php foreach($topCats as $top): ?>
    <a class="top-tab-item <?= $top['id'] == $topId ? 'active' : '' ?>" href="?top=<?=$top['id']?>">
        <?= htmlspecialchars($top['name']) ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="main-container">
    <!-- 左侧二级菜单 -->
    <div class="left-sidebar">
        <?php foreach($leftCats as $left): ?>
        <a class="left-menu-item <?= $left['id'] == $leftId ? 'active' : '' ?>" href="?top=<?=$topId?>&left=<?=$left['id']?>">
            <?= htmlspecialchars($left['name']) ?>
        </a>
        <?php endforeach; ?>
    </div>
    <!-- 右侧商品列表 -->
    <div class="right-content">
        <?php if(empty($goodsList)): ?>
            <div class="empty">暂无商品</div>
        <?php else: ?>
            <div class="section-title"><?= htmlspecialchars($leftCats[0]['name']) ?></div>
            <div class="goods-grid">
                <?php foreach($goodsList as $item):
                    $imgData = json_decode($item['images'] ?? '[]', true);
                    $showName = htmlspecialchars($item['name']);
                    $firstImg = !empty($imgData[0]) ? $imgData[0] : '';
                ?>
                <a href="product_detail.php?id=<?=$item['id']?>" class="goods-item">
                    <div class="goods-card">
                        <?php if($firstImg): ?>
                            <img src="<?= htmlspecialchars($firstImg) ?>" alt="<?=$showName?>">
                        <?php else: ?>
                            <div class="goods-card-text"><?=$showName?></div>
                        <?php endif; ?>
                    </div>
                    <div class="goods-name"><?=$showName?></div>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 恢复底部导航footer -->
<?php include 'footer.php'; ?>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>