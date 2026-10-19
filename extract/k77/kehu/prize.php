<?php
require_once '../config.php';
session_start();

if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
if ($orderId <= 0) {
    die("无效的订单ID");
}

try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", 
                 DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $stmt = $db->prepare("
        SELECT o.*, p.wheel_prize, p.name as product_name
        FROM orders o
        JOIN products p ON o.product_id = p.id
        WHERE o.id = ? AND o.user_id = ? AND o.has_lottery = 0
        LIMIT 1
    ");
    $stmt->execute([$orderId, $_SESSION['user_id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        die("订单不存在、无权查看或已抽过奖");
    }
    
    $prizeConfig = json_decode($order['wheel_prize'], true);
    if (!$prizeConfig || empty($prizeConfig['prizes'])) {
        die("该商品没有配置抽奖活动");
    }
    
    $prizes = $prizeConfig['prizes'];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            die("无效的请求");
        }
        
        $result = drawPrize($prizes);
        
        $stmt = $db->prepare("
            UPDATE orders 
            SET has_lottery = 1, 
                lottery_prize = ?,
                lottery_time = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            json_encode($result, JSON_UNESCAPED_UNICODE),
            $orderId
        ]);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'prize' => $result,
            'order_id' => $orderId
        ]);
        exit;
    }
    
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
} catch (PDOException $e) {
    die("数据库错误: ".$e->getMessage());
}

function drawPrize($prizes) {
    $totalProbability = array_sum(array_column($prizes, 'probability'));
    $rand = mt_rand(1, $totalProbability * 100) / 100;
    $currentProbability = 0;
    
    foreach ($prizes as $prize) {
        $currentProbability += $prize['probability'];
        if ($rand <= $currentProbability) {
            return [
                'prize_name' => $prize['name'],
                'prize_type' => $prize['type'] ?? '普通奖品',
                'prize_value' => $prize['value'] ?? 0,
                'draw_time' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    return [
        'prize_name' => $prizes[0]['name'],
        'prize_type' => $prizes[0]['type'] ?? '普通奖品',
        'prize_value' => $prizes[0]['value'] ?? 0,
        'draw_time' => date('Y-m-d H:i:s')
    ];
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>订单抽奖 - <?= htmlspecialchars($order['order_no']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #ebf1ff;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --warning: #f8961e;
            --danger: #f72585;
            --dark: #1a1a2e;
            --light: #f8f9fa;
            --gray: #6c757d;
            --border-radius: 16px;
            --box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            --box-shadow-sm: 0 4px 12px rgba(0,0,0,0.05);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            --glass-bg: rgba(255, 255, 255, 0.9);
            --glass-border: 1px solid rgba(255, 255, 255, 0.3);
            --glass-shadow: 0 8px 32px rgba(31, 38, 135, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f5f7ff, #e6e9ff);
            color: #2b2d42;
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 500px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header h1 i {
            color: var(--primary);
        }

        .back-btn {
            padding: 10px 16px;
            background: var(--primary-light);
            border-radius: 8px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            font-size: 14px;
        }

        .back-btn:hover {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.2);
        }

        .order-info {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--glass-shadow);
            border: var(--glass-border);
            text-align: center;
        }

        .order-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark);
        }

        .order-no {
            color: var(--gray);
            font-size: 14px;
        }

        /* 大转盘样式 - 根据图片优化 */
        .wheel-container {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 30px;
        }

        .wheel-wrapper {
            position: relative;
            width: 320px;
            height: 320px;
            margin: 0 auto;
        }

        .wheel {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            overflow: hidden;
            box-shadow: 0 0 0 12px white, 0 0 0 14px #e9ecef, 0 15px 40px rgba(0,0,0,0.15);
            transition: transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99);
            transform: rotate(0deg);
        }

        .wheel.spinning {
            transform: rotate(3600deg);
        }

        .wheel-pointer {
            position: absolute;
            top: -24px;
            left: 50%;
            transform: translateX(-50%);
            width: 48px;
            height: 48px;
            z-index: 10;
            filter: drop-shadow(0 2px 8px rgba(0,0,0,0.2));
        }

        .wheel-pointer:before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 18px solid transparent;
            border-right: 18px solid transparent;
            border-top: 36px solid var(--danger);
        }

        .wheel-pointer:after {
            content: '';
            position: absolute;
            top: 6px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 12px solid transparent;
            border-right: 12px solid transparent;
            border-top: 24px solid white;
        }

        .wheel-item {
            position: absolute;
            width: 50%;
            height: 50%;
            transform-origin: 100% 100%;
            left: 0;
            top: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
            box-sizing: border-box;
            padding-bottom: 110px;
            padding-right: 60px;
            text-align: center;
            font-size: 16px;
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
            overflow: visible;
        }

        .wheel-item-inner {
            transform: rotate(90deg);
            width: 160px;
            text-align: center;
            word-break: keep-all;
            white-space: nowrap;
            line-height: 1.5;
            display: flex;
            flex-direction: column;
            justify-content: center;
            height: 100%;
            padding: 0 5px;
        }

        .spin-btn-container {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 5;
            width: 100px;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .spin-btn {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.3);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            z-index: 6;
        }

        .spin-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.5s;
        }

        .spin-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 30px rgba(67, 97, 238, 0.4);
        }

        .spin-btn:hover::before {
            left: 100%;
        }

        .spin-btn:disabled {
            background: var(--gray);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .spin-btn i {
            font-size: 24px;
            margin-bottom: 5px;
        }

        /* 结果弹窗 */
        .result-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }

        .result-modal.active {
            opacity: 1;
            visibility: visible;
        }

        .result-content {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 400px;
            padding: 30px;
            box-shadow: var(--glass-shadow);
            border: var(--glass-border);
            text-align: center;
            transform: translateY(-20px);
            transition: all 0.3s;
        }

        .result-modal.active .result-content {
            transform: translateY(0);
        }

        .result-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 36px;
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.3);
        }

        .result-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--dark);
        }

        .result-prize {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 20px;
            padding: 10px;
            background: rgba(67, 97, 238, 0.1);
            border-radius: 8px;
            line-height: 1.3;
            word-break: break-word;
        }

        .result-desc {
            color: var(--gray);
            margin-bottom: 25px;
            font-size: 15px;
        }

        .result-btn {
            padding: 14px 25px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.2);
        }

        .result-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(67, 97, 238, 0.3);
        }

        @media (max-width: 576px) {
            .wheel-wrapper {
                width: 280px;
                height: 280px;
            }
            
            .wheel-item {
                font-size: 14px;
                padding-bottom: 100px;
                padding-right: 50px;
            }
            
            .wheel-item-inner {
                width: 140px;
            }
            
            .spin-btn {
                width: 70px;
                height: 70px;
                font-size: 14px;
            }
            
            .spin-btn i {
                font-size: 20px;
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

    <div class="container">
        <div class="header">
            <h1><i class="fas fa-gift"></i> 订单抽奖</h1>
            <a href="order_detail.php?id=<?= $orderId ?>" class="back-btn"><i class="fas fa-arrow-left"></i> 返回</a>
        </div>
        
        <div class="order-info">
            <div class="order-name">基础保底100w 转盘奖励200-1000w保底</div>
            <div class="order-no">订单号: <?= htmlspecialchars($order['order_no']) ?></div>
        </div>
        
        <!-- 大转盘模式 -->
        <div class="wheel-container">
            <div class="wheel-wrapper">
                <div class="wheel" id="wheel">
                    <?php 
                    $angle = 0;
                    $angleStep = 360 / count($prizes);
                    $colors = [
                        'linear-gradient(135deg, #4361ee, #3a0ca3)',
                        'linear-gradient(135deg, #4cc9f0, #4895ef)',
                        'linear-gradient(135deg, #3f37c9, #560bad)',
                        'linear-gradient(135deg, #4895ef, #4361ee)',
                        'linear-gradient(135deg, #560bad, #3a0ca3)',
                        'linear-gradient(135deg, #3a0ca3, #4361ee)'
                    ];
                    
                    foreach ($prizes as $index => $prize): 
                        $bgColor = $colors[$index % count($colors)];
                    ?>
                    <div class="wheel-item" 
                         style="transform: rotate(<?= $angle ?>deg); background: <?= $bgColor ?>;">
                        <div class="wheel-item-inner">
                            <?= htmlspecialchars($prize['name']) ?>
                        </div>
                    </div>
                    <?php 
                        $angle += $angleStep;
                    endforeach; 
                    ?>
                </div>
                <div class="wheel-pointer"></div>
            </div>
            
            <div class="spin-btn-container">
                <button class="spin-btn" id="spinBtn">
                    <i class="fas fa-play"></i>
                    <span>抽奖</span>
                </button>
            </div>
        </div>
        
        <!-- 结果弹窗 -->
        <div class="result-modal" id="resultModal">
            <div class="result-content">
                <div class="result-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="result-title">恭喜您获得</div>
                <div class="result-prize" id="resultPrize"></div>
                <div class="result-desc">奖品已自动存入您的账户</div>
                <button class="result-btn" id="closeResultBtn">
                    <i class="fas fa-check"></i> 确定
                </button>
            </div>
        </div>
    </div>
    
    <script>
        // 奖品数据
        const prizes = <?= json_encode($prizes) ?>;
        const orderId = <?= $orderId ?>;
        const csrfToken = '<?= $_SESSION['csrf_token'] ?>';
        
        // 大转盘抽奖逻辑
        const wheel = document.getElementById('wheel');
        const spinBtn = document.getElementById('spinBtn');
        let isSpinning = false;
        
        spinBtn.addEventListener('click', function() {
            if (isSpinning) return;
            
            isSpinning = true;
            spinBtn.disabled = true;
            
            // 添加旋转动画类
            wheel.classList.add('spinning');
            
            // 随机选择奖品
            const totalProbability = prizes.reduce((sum, prize) => sum + prize.probability, 0);
            const random = Math.random() * totalProbability;
            let currentProbability = 0;
            let selectedIndex = 0;
            
            for (let i = 0; i < prizes.length; i++) {
                currentProbability += prizes[i].probability;
                if (random <= currentProbability) {
                    selectedIndex = i;
                    break;
                }
            }
            
            // 计算旋转角度 (5圈 + 奖品位置)
            const angleStep = 360 / prizes.length;
            const targetAngle = 360 * 5 + (360 - (selectedIndex * angleStep + angleStep / 2));
            
            wheel.style.transform = `rotate(${targetAngle}deg)`;
            
            // 发送抽奖结果到服务器
            setTimeout(() => {
                fetch('prize.php?order_id=' + orderId, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `csrf_token=${encodeURIComponent(csrfToken)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showResultModal(prizes[selectedIndex].name);
                    } else {
                        alert('抽奖失败: ' + (data.message || '未知错误'));
                        resetWheel();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('抽奖过程中出错');
                    resetWheel();
                });
            }, 4000);
        });
        
        // 显示结果弹窗
        function showResultModal(prizeName) {
            document.getElementById('resultPrize').textContent = prizeName;
            document.getElementById('resultModal').classList.add('active');
        }
        
        // 关闭结果弹窗
        document.getElementById('closeResultBtn').addEventListener('click', function() {
            document.getElementById('resultModal').classList.remove('active');
            window.location.href = 'order_detail.php?id=' + orderId;
        });
        
        // 重置转盘
        function resetWheel() {
            isSpinning = false;
            spinBtn.disabled = false;
            wheel.classList.remove('spinning');
            wheel.style.transform = 'rotate(0deg)';
        }
        
        // 转盘动画结束事件
        wheel.addEventListener('transitionend', function() {
            wheel.classList.remove('spinning');
        });
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>