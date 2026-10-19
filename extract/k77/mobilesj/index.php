<?php
$siteTitle = '商家首页';
require_once 'header.php';
$partner_id = $_SESSION['partner_id'];

try {
    $stmt = $pdo->query("SELECT * FROM partner_carousel WHERE is_active = 1 ORDER BY sort_order ASC, id DESC");
    $carousels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(price) as total FROM orders WHERE fadan_id = ? AND order_status = 3");
    $stmt->execute([$partner_id]);
    $pending_settlement = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(price) as total FROM orders WHERE fadan_id = ? AND order_status = 4");
    $stmt->execute([$partner_id]);
    $completed_settlement = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE fadan_id = ?");
    $stmt->execute([$partner_id]);
    $published_orders = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM penalty_records WHERE initiator_id = ?");
    $stmt->execute([$partner_id]);
    $penalty_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    $announcement = $settings['h5_announcement'] ?? '';

    $stmt = $pdo->query("SELECT * FROM customer_services WHERE status = 1 ORDER BY id DESC");
    $customer_services = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $carousels = [];
    $pending_settlement = ['count' => 0, 'total' => 0];
    $completed_settlement = ['count' => 0, 'total' => 0];
    $published_orders = 0;
    $penalty_count = 0;
    $announcement = '';
    $customer_services = [];
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="商家首页">
    <title>商家首页</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--primary-teal:#3baaa3;--primary-teal-dark:#2a8a84;--gold:#c9a45c;--gold-dark:#a8843d}
        body{font-family:'Noto Sans SC',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:url('../assets/1.png');background-size:cover;background-position:center;background-repeat:no-repeat;background-attachment:fixed;color:#333;line-height:1.5;-webkit-font-smoothing:antialiased;min-height:100vh;position:relative}
        .app-container{max-width:100%;margin:0 auto;padding:0 12px 80px;position:relative;z-index:1}
        .header{padding:12px;background:rgba(255,255,255,0.8);position:sticky;top:0;z-index:100;box-shadow:0 2px 10px rgba(0,0,0,.08)}
        .header-content{display:flex;align-items:center;justify-content:space-between;gap:12px}
        .header-title{font-size:18px;font-weight:700;color:#333;flex-shrink:0}
        .header-icons{display:flex;align-items:center;gap:12px}
        .icon-btn{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#ffcc00 0%,#ff9900 100%);border:none;color:#000;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px;text-decoration:none;transition:all .2s ease;box-shadow:0 3px 10px rgba(255,204,0,.4)}
        .icon-btn:hover{transform:scale(1.1)}
        .customer-service-modal{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:1000;opacity:0;visibility:hidden;transition:all .3s ease}
        .customer-service-modal.active{opacity:1;visibility:visible}
        .modal-content{background:rgba(255,255,255,0.95);border-radius:16px;padding:24px;max-width:90%;width:400px;box-shadow:0 10px 30px rgba(0,0,0,.3)}
        .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
        .modal-title{font-size:18px;font-weight:700;color:#333}
        .close-btn{background:none;border:none;font-size:24px;cursor:pointer;color:#999;transition:color .3s ease}
        .close-btn:hover{color:#333}
        .customer-list{display:flex;flex-direction:column;gap:12px}
        .customer-item{display:flex;align-items:center;justify-content:space-between;padding:16px;border:1px solid #e0e0e0;border-radius:12px;transition:all .3s ease;cursor:pointer;background:rgba(255,255,255,0.8)}
        .customer-item:hover{border-color:#ffcc00;box-shadow:0 4px 15px rgba(255,204,0,.2);background:rgba(255,255,255,0.9)}
        .customer-info{display:flex;align-items:center}
        .customer-details h4{font-size:16px;font-weight:600;color:#333;margin-bottom:4px}
        .customer-details p{font-size:14px;color:#666}
        .customer-action{padding:8px 16px;background:linear-gradient(135deg,#ffcc00 0%,#ff9900 100%);color:#000;border:none;border-radius:20px;font-size:14px;font-weight:600;cursor:pointer;transition:all .3s ease}
        .customer-action:hover{transform:scale(1.05)}
        .hero-section{margin:20px 0 16px;border-radius:16px;overflow:hidden;background:rgba(255,255,255,0.8);box-shadow:0 4px 15px rgba(0,0,0,.08);position:relative}
        .swiper{width:calc(100% - 36px);height:180px;border-radius:4px;margin:8px auto}
        .swiper-slide{display:flex;align-items:center;justify-content:center;background:rgba(248,248,248,0.8);border-radius:4px;overflow:hidden}
        .swiper-slide img{width:100%;height:100%;object-fit:cover}
        .swiper-pagination-bullet{background:rgba(201,164,92,0.5);opacity:1}
        .swiper-pagination-bullet-active{background:linear-gradient(135deg,var(--gold) 0%,var(--gold-dark) 100%);width:24px;border-radius:12px}
        .announcement-banner{background:rgba(255,255,255,0.8);padding:8px 16px;margin:8px 0;border-radius:16px;overflow:hidden;display:flex;align-items:center;box-shadow:0 4px 15px rgba(0,0,0,.08);position:relative;border:1px solid #e0e0e0}
        .announcement-icon{color:#ff9900;font-size:20px;flex-shrink:0;margin-right:12px}
        .announcement-scroll-container{flex:1;overflow:hidden;white-space:nowrap}
        .announcement-content{display:inline-block;font-size:14px;color:#ff9900;line-height:1.6;animation:scroll-left 10s linear infinite;font-weight:500}
        @keyframes scroll-left{0%{transform:translateX(100%)}100%{transform:translateX(-100%)}}
        .stats-section{background:rgba(255,255,255,0.8);padding:20px;border-radius:16px;margin-bottom:16px;box-shadow:0 4px 15px rgba(0,0,0,.08)}
        .stats-title{font-size:18px;font-weight:700;color:#333;margin-bottom:16px;display:flex;align-items:center;gap:8px}
        .stats-title i{color:#ffcc00}
        .stats-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:16px}
        .stat-card{background:rgba(255,255,255,0.9);padding:16px;border-radius:12px;text-align:center;border:1px solid #e0e0e0;transition:all .3s ease}
        .stat-card:hover{border-color:#ffcc00;transform:translateY(-2px);box-shadow:0 4px 15px rgba(255,204,0,.2)}
        .stat-value{font-size:24px;font-weight:700;color:#333;margin-bottom:4px}
        .stat-value.highlight{color:#ff9900}
        .stat-label{font-size:12px;color:#666;font-weight:500}
        .action-section{margin:16px 0}
        .action-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
        .action-card{display:block;padding:20px 16px;border-radius:16px;text-decoration:none;transition:all .3s ease;position:relative;overflow:hidden}
        .action-card-left{background:linear-gradient(135deg,#ffcc00 0%,#ff9900 100%);color:#000}
        .action-card-right{background:linear-gradient(135deg,#1a1a1a 0%,#333 100%);color:#fff}
        .action-card:hover{transform:translateY(-4px);box-shadow:0 8px 25px rgba(0,0,0,.2)}
        .action-card-title{font-size:16px;font-weight:700;margin-bottom:8px;display:flex;align-items:center;gap:8px}
        .action-card-desc{font-size:13px;opacity:0.8;margin-bottom:12px}
        .action-card-btn{padding:8px 16px;background:rgba(255,255,255,0.9);color:#000;border-radius:8px;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px}
        .action-card-right .action-card-btn{background:rgba(255,255,255,0.2);color:#fff}
        .action-card-icon{width:36px;height:36px;background:rgba(0,0,0,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;margin-bottom:12px}
        .action-card-right .action-card-icon{background:rgba(255,255,255,0.2)}
        footer p{font-size:22px !important;font-weight:600;background:linear-gradient(135deg,var(--gold) 0%,var(--gold-dark) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;text-align:center;margin:30px 0 20px;letter-spacing:6px}
        @media (max-width:480px){.stats-grid{gap:12px}.stat-value{font-size:20px}.action-card{padding:16px 12px}}
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
            <div class="header-title">商家中心</div>
            <div class="header-icons">
                <button class="icon-btn" id="customerServiceBtn">
                    <i class="fas fa-headphones-alt"></i>
                </button>
            </div>

            <div class="customer-service-modal" id="customerServiceModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">联系客服</h3>
                        <button class="close-btn" id="closeModalBtn">&times;</button>
                    </div>
                    <div class="customer-list">
                        <?php if(empty($customer_services)): ?>
                            <div class="customer-item" style="justify-content:center;color:#999;">
                                <p>暂无客服</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($customer_services as $cs): ?>
                                <?php
                                    $display_channel = '客服';
                                    if (strpos($cs['link'], ':') !== false) {
                                        $parts = explode(':', $cs['link'], 2);
                                        if (!empty($parts[0])) {
                                            $display_channel = htmlspecialchars($parts[0]);
                                        }
                                    }
                                ?>
                                <div class="customer-item" onclick="openCustomerService('<?php echo htmlspecialchars($cs['link']); ?>')">
                                    <div class="customer-info">
                                        <div class="customer-details">
                                            <h4><?php echo htmlspecialchars($cs['name']); ?></h4>
                                            <p><?php echo $display_channel; ?></p>
                                        </div>
                                    </div>
                                    <button class="customer-action" onclick="event.stopPropagation(); openCustomerService('<?php echo htmlspecialchars($cs['link']); ?>')">打开</button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="app-container">
        <?php if (!empty($carousels)): ?>
        <section class="hero-section">
            <div class="swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($carousels as $carousel): ?>
                    <div class="swiper-slide">
                        <a href="<?php echo htmlspecialchars($carousel['link_url'] ?? '#'); ?>" target="_blank">
                            <img src="../<?php echo htmlspecialchars($carousel['image_path']); ?>" alt="轮播图">
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </section>
        <?php endif; ?>

        <?php if(!empty($announcement)): ?>
        <div class="announcement-banner">
            <div class="announcement-icon">
                <i class="fas fa-bullhorn"></i>
            </div>
            <div class="announcement-scroll-container">
                <div class="announcement-content">
                    <?php echo htmlspecialchars($announcement); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="stats-section">
            <h3 class="stats-title"><i class="fas fa-tasks"></i> 我的待办</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $pending_settlement['count']; ?></div>
                    <div class="stat-label">待结算订单</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value highlight"><?php echo number_format($pending_settlement['total'] ?? 0, 2); ?></div>
                    <div class="stat-label">待结算金额(元)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($completed_settlement['total'] ?? 0, 2); ?></div>
                    <div class="stat-label">已结算金额(元)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $published_orders; ?></div>
                    <div class="stat-label">已发布订单</div>
                </div>
            </div>
        </div>

        <div class="action-section">
            <div class="action-grid">
                <a href="order_create.php" class="action-card action-card-left">
                    <div class="action-card-icon">
                        <i class="fas fa-plus" style="font-size: 18px;"></i>
                    </div>
                    <div class="action-card-title">发布订单</div>
                    <div class="action-card-desc">发布常规游戏任务</div>
                    <div class="action-card-btn">
                        现在就去 <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
                <a href="orders.php" class="action-card action-card-right">
                    <div class="action-card-icon">
                        <i class="fas fa-list" style="font-size: 18px;"></i>
                    </div>
                    <div class="action-card-title">查看订单</div>
                    <div class="action-card-desc">查看已发布订单</div>
                    <div class="action-card-btn">
                        立即查看 <i class="fas fa-arrow-right"></i>
                    </div>
                </a>
            </div>
        </div>
    </main>

    <footer>
        <p>到底啦！</p>
    </footer>

    <?php require 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var swiper = new Swiper('.swiper', {
            loop: true,
            autoplay: {
                delay: 3000,
                disableOnInteraction: false,
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            effect: 'fade',
            fadeEffect: {
                crossFade: true
            },
            speed: 400,
        });

        const customerServiceBtn = document.getElementById('customerServiceBtn');
        const customerServiceModal = document.getElementById('customerServiceModal');
        const closeModalBtn = document.getElementById('closeModalBtn');

        if (customerServiceBtn && customerServiceModal && closeModalBtn) {
            customerServiceBtn.addEventListener('click', function() {
                customerServiceModal.classList.add('active');
            });

            closeModalBtn.addEventListener('click', function() {
                customerServiceModal.classList.remove('active');
            });

            customerServiceModal.addEventListener('click', function(e) {
                if (e.target === customerServiceModal) {
                    customerServiceModal.classList.remove('active');
                }
            });
        }
    });

    function openCustomerService(link) {
        if (link && link !== '#') {
            var prefix = '';
            var actualLink = link;
            var prefixes = ['企业微信:', '微信:', 'QQ:', '钉钉:', 'Telegram:'];
            for (var i = 0; i < prefixes.length; i++) {
                if (link.indexOf(prefixes[i]) === 0) {
                    actualLink = link.substring(prefixes[i].length);
                    break;
                }
            }
            window.open(actualLink, '_blank');
        } else {
            alert('暂无可用客服');
        }

        const customerServiceModal = document.getElementById('customerServiceModal');
        if (customerServiceModal) {
            customerServiceModal.classList.remove('active');
        }
    }
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>