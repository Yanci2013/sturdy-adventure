<?php
require_once '../includes/session_manager.php';

$site_logo = "assets/images/logo.png";
$customer_service_link = "#";
$h5_announcement_text = "";
$customer_services = [];
$lottery_enabled = false;
$lottery_settings = [];
$prizes = [];
$products = [];
$game_regions = [];
$has_db_connection = false;

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
        try {
            $stmt = $db->query("SELECT site_logo, enterprise_customer_link, h5_announcement FROM settings WHERE id = 1");
            $settings_result = $stmt->fetch();
            if($settings_result) {
                if(!empty($settings_result['site_logo'])) {
                    $site_logo = $settings_result['site_logo'];
                }
                $customer_service_link = $settings_result['enterprise_customer_link'] ?: '#';
                $h5_announcement_text = $settings_result['h5_announcement'] ?: '';
            }
        } catch (Exception $e) {
            error_log('Error loading settings: ' . $e->getMessage());
        }
        
        try {
            $customer_stmt = $db->query("SELECT * FROM customer_services WHERE status = 1 ORDER BY id DESC");
            $customer_services = $customer_stmt->fetchAll();
        } catch (Exception $e) {
            error_log('Error loading customer services: ' . $e->getMessage());
        }
        
        try {
            $game_regions_stmt = $db->query("SELECT id, name FROM game_regions WHERE status = 1 ORDER BY sort_order DESC, id ASC");
            $game_regions = $game_regions_stmt->fetchAll();
        } catch (Exception $e) {
            error_log('Error loading game regions: ' . $e->getMessage());
            $game_regions = [];
        }
        
        try {
            $lottery_stmt = $db->query("SELECT * FROM lottery_settings WHERE id = 1");
            $lottery_settings = $lottery_stmt->fetch();
            if ($lottery_settings) {
                $lottery_enabled = $lottery_settings['enabled'] == 1;
            }
        } catch (Exception $e) {
            error_log('Error loading lottery settings: ' . $e->getMessage());
            $lottery_enabled = false;
        }
        
        try {
            $prizes = $db->query("SELECT * FROM lottery_prizes WHERE status = 1 ORDER BY sort_order DESC, id DESC")->fetchAll();
        } catch (Exception $e) {
            error_log('Error loading prizes: ' . $e->getMessage());
            $prizes = [];
        }
    }
    
} catch(PDOException $e) {
    error_log("数据库错误: ".$e->getMessage());
}

if (!$lottery_enabled) {
    header('Location: index.php');
    exit();
}

// 处理支付请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'lottery_pay') {
    $game_area = $_POST['game_area'] ?? '';
    $game_nickname = $_POST['game_nickname'] ?? '';
    $game_id = $_POST['game_id'] ?? '';
    
    if (empty($game_area) || empty($game_nickname) || empty($game_id)) {
        echo json_encode(['error' => '请填写所有必填信息']);
        exit();
    }
    
    // 这里可以添加支付逻辑
    // 例如生成订单、调用支付接口等
    
    echo json_encode([
        'success' => true,
        'message' => '支付成功，正在抽奖...',
        'game_info' => [
            'area' => $game_area,
            'nickname' => $game_nickname,
            'id' => $game_id
        ]
    ]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="开盲盒 - 电竞平台">
    <meta name="keywords" content="盲盒,抽奖,电竞,奖品">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>幸运盲盒</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;600;700&display=swap" rel="preload" as="font" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--primary-teal:#3baaa3;--primary-teal-dark:#2a8a84;--gold:#c9a45c;--gold-dark:#a8843d;--red:#e74c3c;--orange:#f39c12;--purple:#9b59b6;--blue:#3498db;--green:#2ecc71;--pink:#e91e63}
        body{font-family:'Noto Sans SC',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%);background-size:cover;background-position:center;background-repeat:no-repeat;background-attachment:fixed;color:#333;line-height:1.5;-webkit-font-smoothing:antialiased;min-height:100vh;position:relative;overflow-x:hidden}
        body::before{content:'';position:fixed;top:0;left:0;right:0;bottom:0;background:url('../assets/1.png');background-size:cover;background-position:center;opacity:0.3;pointer-events:none;z-index:0}
        .app-container{max-width:100%;margin:0 auto;padding:0 12px 80px;position:relative;z-index:1}
        .header{padding:12px;background:rgba(255,255,255,0.95);backdrop-filter:blur(10px);position:sticky;top:0;z-index:100;box-shadow:0 4px 20px rgba(0,0,0,.15)}
        .header-content{display:flex;align-items:center;justify-content:space-between;gap:12px}
        .header-title{font-size:18px;font-weight:700;color:#333;flex:1;text-align:center}
        .back-btn{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,var(--gold) 0%,var(--gold-dark) 100%);border:none;color:#000;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px;text-decoration:none;transition:all .3s ease;box-shadow:0 4px 15px rgba(201,164,92,.4)}
        .back-btn:hover{transform:scale(1.1)}
        .lottery-container{background:linear-gradient(145deg,rgba(255,255,255,0.95) 0%,rgba(255,255,255,0.85) 100%);backdrop-filter:blur(20px);border-radius:24px;box-shadow:0 20px 60px rgba(0,0,0,.3),inset 0 1px 0 rgba(255,255,255,.5);padding:30px;margin:20px 0;border:1px solid rgba(255,255,255,.2)}
        .banner-section{text-align:center;margin-bottom:40px;position:relative}
        .banner-section h1{font-size:24px;font-weight:700;color:#ff9900;margin-bottom:10px}
        .banner-section p{font-size:14px;color:#666}
        .button-section{text-align:center;margin:30px 0}
        .open-button{background:linear-gradient(135deg,#ffcc00 0%,#ff9900 100%);color:#000;border:none;border-radius:40px;padding:20px 60px;font-size:24px;font-weight:700;cursor:pointer;transition:all .3s ease;box-shadow:0 8px 30px rgba(255,204,0,.4);position:relative;overflow:hidden;display:inline-flex;align-items:center;gap:10px}
        .open-button:hover{transform:scale(1.05);box-shadow:0 12px 40px rgba(255,204,0,.6)}
        .open-button::before{content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.3),transparent);transition:all .6s ease}
        .open-button:hover::before{left:100%}
        .button-price{font-size:20px;font-weight:600;color:#ff4444}
        .products-section{margin-top:40px}
        .products-section h2{font-size:20px;font-weight:600;color:#333;margin-bottom:20px;text-align:center;display:flex;align-items:center;justify-content:center;gap:10px}
        .products-section h2 i{color:var(--gold)}
        .products-scroll{overflow:hidden;position:relative}
        .products-container{display:flex;gap:20px;animation:scroll 30s linear infinite}
        @keyframes scroll{0%{transform:translateX(0)}100%{transform:translateX(-50%)}}
        .product-card{flex:0 0 200px;background:linear-gradient(145deg,#fff 0%,#f8f8f8 100%);border-radius:12px;padding:16px;box-shadow:0 4px 15px rgba(0,0,0,.08);transition:all .3s ease;border:1px solid rgba(255,255,255,.5);text-align:center}
        .product-card:hover{transform:translateY(-5px);box-shadow:0 8px 25px rgba(255,140,0,.2);border-color:var(--gold)}
        .product-image{width:100%;height:120px;object-fit:contain;margin-bottom:12px;border-radius:8px}
        .product-name{font-size:14px;font-weight:600;color:#333;margin-bottom:8px;line-height:1.4;min-height:40px}
        .product-price{font-size:16px;font-weight:700;color:#ff4444}
        .no-prizes{text-align:center;padding:40px;color:#999;font-size:16px}
        .game-modal{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.8);backdrop-filter:blur(5px);display:flex;align-items:center;justify-content:center;z-index:1000;opacity:0;visibility:hidden;transition:all .4s ease}
        .game-modal.active{opacity:1;visibility:visible}
        .game-modal-content{background:linear-gradient(145deg,#fff 0%,#f8f8f8 100%);border-radius:24px;padding:30px;max-width:90%;width:340px;box-shadow:0 25px 60px rgba(0,0,0,.4);transform:scale(0.8);transition:all .4s ease}
        .game-modal.active .game-modal-content{transform:scale(1)}
        .game-modal h3{font-size:18px;font-weight:700;color:#333;margin-bottom:20px;text-align:center}
        .form-group{margin-bottom:20px}
        .form-group label{display:block;font-size:14px;font-weight:600;color:#666;margin-bottom:8px}
        .form-group input{width:100%;padding:12px 16px;border:1px solid #ddd;border-radius:8px;font-size:14px}
        .form-group input:focus{outline:none;border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,164,92,.1)}
        .radio-group{display:flex;gap:20px;margin-bottom:10px}
        .radio-option{display:flex;align-items:center;gap:8px}
        .radio-option input[type="radio"]{width:auto}
        .modal-buttons{display:flex;gap:12px;margin-top:30px}
        .modal-buttons button{flex:1;padding:14px;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;transition:all .3s ease}
        .btn-cancel{background:#f8f9fa;color:#333;border:1px solid #ddd}
        .btn-cancel:hover{background:#e9ecef}
        .btn-submit{background:linear-gradient(135deg,#ffcc00 0%,#ff9900 100%);color:#000;border:none}
        .btn-submit:hover{transform:scale(1.05)}
        .result-modal{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.8);backdrop-filter:blur(5px);display:flex;align-items:center;justify-content:center;z-index:1000;opacity:0;visibility:hidden;transition:all .4s ease}
        .result-modal.active{opacity:1;visibility:visible}
        .modal-content{background:linear-gradient(145deg,#fff 0%,#f8f8f8 100%);border-radius:24px;padding:40px;max-width:90%;width:340px;box-shadow:0 25px 60px rgba(0,0,0,.4);text-align:center;transform:scale(0.8);transition:all .4s ease}
        .result-modal.active .modal-content{transform:scale(1)}
        .modal-icon{font-size:80px;margin-bottom:20px;animation:bounce 1s ease infinite}
        @keyframes bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
        .modal-title{font-size:24px;font-weight:700;color:#333;margin-bottom:12px}
        .modal-message{font-size:16px;color:#666;margin-bottom:30px;line-height:1.6}
        .modal-button{padding:14px 40px;background:linear-gradient(135deg,var(--gold) 0%,var(--gold-dark) 100%);color:#000;border:none;border-radius:30px;font-size:16px;font-weight:600;cursor:pointer;transition:all .3s ease;box-shadow:0 5px 20px rgba(201,164,92,.4)}
        .modal-button:hover{transform:scale(1.05);box-shadow:0 8px 30px rgba(201,164,92,.6)}
        footer p{font-size:22px !important;font-weight:600;background:linear-gradient(135deg,var(--gold) 0%,var(--gold-dark) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;text-align:center;margin:30px 0 20px;letter-spacing:6px}
        @media (max-width:480px){.product-card{flex:0 0 150px;padding:12px}.product-image{height:100px}.open-button{padding:16px 40px;font-size:20px}.button-price{font-size:18px}}
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
            <a href="index.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="header-title">幸运盲盒</h1>
            <div style="width: 42px;"></div>
        </div>
    </header>

    <main class="app-container">
        <div class="lottery-container">
            <div class="banner-section">
                <h1>🎁 幸运盲盒</h1>
                <p>开启惊喜，赢取豪礼！</p>
            </div>
            
            <div class="button-section">
                <button class="open-button" id="openButton">
                    开盲盒
                    <span class="button-price">¥<?php echo $lottery_settings['payment_amount'] ?? 0.1; ?></span>
                </button>
            </div>
            
            <div class="products-section">
                <h2><i class="fas fa-gift"></i> 奖品池</h2>
                <?php if (empty($prizes)): ?>
                    <div class="no-prizes">
                        <i class="fas fa-box-open" style="font-size: 48px; margin-bottom: 16px; color: #ccc;"></i>
                        <p>暂无抽奖商品</p>
                    </div>
                <?php else: ?>
                    <div class="products-scroll">
                        <div class="products-container">
                            <?php 
                            foreach ($prizes as $prize): 
                            ?>
                                <div class="product-card">
                                    <div style="width: 100%; height: 120px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 8px; margin-bottom: 12px;">
                                        <i class="fas fa-gift" style="font-size: 48px; color: var(--gold);"></i>
                                    </div>
                                    <div class="product-name"><?php echo htmlspecialchars($prize['name']); ?></div>
                                    <div class="product-price">中奖率: <?php echo $prize['probability']; ?>%</div>
                                </div>
                            <?php endforeach; ?>
                            <?php 
                            foreach ($prizes as $prize): 
                            ?>
                                <div class="product-card">
                                    <div style="width: 100%; height: 120px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 8px; margin-bottom: 12px;">
                                        <i class="fas fa-gift" style="font-size: 48px; color: var(--gold);"></i>
                                    </div>
                                    <div class="product-name"><?php echo htmlspecialchars($prize['name']); ?></div>
                                    <div class="product-price">中奖率: <?php echo $prize['probability']; ?>%</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer>
        <p>开启惊喜，收获幸运</p>
    </footer>

    <!-- 游戏信息弹窗 -->
    <div class="game-modal" id="gameModal">
        <div class="game-modal-content">
            <h3>请先填写游戏信息</h3>
            <form id="gameForm">
                <div class="form-group">
                    <label>*游戏大区</label>
                    <div class="radio-group">
                        <?php if (!empty($game_regions)): ?>
                            <?php foreach ($game_regions as $index => $region): ?>
                                <label class="radio-option">
                                    <input type="radio" name="game_area" value="<?php echo htmlspecialchars($region['name']); ?>" <?php echo $index === 0 ? 'checked' : ''; ?>>
                                    <?php echo htmlspecialchars($region['name']); ?>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <label class="radio-option">
                                <input type="radio" name="game_area" value="v区" checked>
                                v区
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="game_area" value="q区">
                                q区
                            </label>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>*游戏昵称</label>
                    <input type="text" name="game_nickname" placeholder="请输入游戏昵称" required>
                </div>
                <div class="form-group">
                    <label>*游戏ID</label>
                    <input type="text" name="game_id" placeholder="请输入游戏ID" required>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeGameModal()">返回</button>
                    <button type="submit" class="btn-submit">去开盲盒</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const openButton = document.getElementById('openButton');
            const gameModal = document.getElementById('gameModal');
            const gameForm = document.getElementById('gameForm');
            let isOpening = false;
            
            openButton.addEventListener('click', function() {
                if (isOpening) return;
                gameModal.classList.add('active');
            });
            
            gameForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(gameForm);
                const gameArea = formData.get('game_area');
                const gameNickname = formData.get('game_nickname');
                const gameId = formData.get('game_id');
                
                if (!gameArea || !gameNickname || !gameId) {
                    alert('请填写所有必填信息');
                    return;
                }
                
                // 跳转到收银台支付
                const paymentAmount = <?php echo json_encode(floatval($lottery_settings['payment_amount'] ?? 0.1)); ?>;
                const orderId = 'lottery_' + Date.now();
                const orderDesc = '幸运盲盒抽奖';
                
                // 创建表单并POST提交
                const paymentForm = document.createElement('form');
                paymentForm.method = 'POST';
                paymentForm.action = 'payment_select.php';
                
// 添加参数
                 const params = {
                     order_id: orderId,
                     amount: paymentAmount,
                     description: orderDesc,
                     type: 'lottery',
                     game_area: gameArea,
                     game_nickname: gameNickname,
                     game_id: gameId
                 };
                 
                 for (const [key, value] of Object.entries(params)) {
                     const input = document.createElement('input');
                     input.type = 'hidden';
                     input.name = key;
                     input.value = value;
                     paymentForm.appendChild(input);
                 }
                 
                 // 提交表单
                 document.body.appendChild(paymentForm);
                 paymentForm.submit();
             });
             
             window.closeGameModal = function() {
                 gameModal.classList.remove('active');
             }
             
             gameModal.addEventListener('click', function(e) {
                 if (e.target === gameModal) {
                     closeGameModal();
                 }
             });
         });
     </script>
     
<script src="../assets/js/loader-v2.js"></script>
</body>
 </html>