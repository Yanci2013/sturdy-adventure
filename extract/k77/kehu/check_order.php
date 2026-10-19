<?php
/**
 * 查询订单状态页面
 */
session_start();

require_once '../includes/common.php';
require_once '../includes/session_manager.php';
require_once '../includes/log_helper.php';

// 检查登录
if (!is_h5_logged_in()) {
    header('Location: login.php');
    exit;
}

$orderNo = isset($_GET['order_no']) ? $_GET['order_no'] : '';
$message = '';
$messageType = 'info';

if (empty($orderNo)) {
    header('Location: index.php');
    exit;
}

try {
    // 查询订单
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_no = ?");
    $stmt->execute([$orderNo]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        // 检查订单状态
        if ($order['order_status'] >= 2) {
            // 订单已支付或已完成
            $message = '订单支付成功！';
            $messageType = 'success';
            
            // 如果订单还没有被标记为已支付，我们在这里处理
            if ($order['order_status'] == 1) {
                $updateStmt = $pdo->prepare("UPDATE orders SET order_status = 2, pay_time = NOW() WHERE id = ?");
                $updateStmt->execute([$order['id']]);
                
                // 记录订单日志
                log_order_action($pdo, $order['id'], $orderNo, 'manual_paid', '手动确认订单已支付', null, null, 'system');
            }
            
            // 延迟2秒后跳转到首页
            header('refresh:2;url=index.php');
        } else {
            $message = '订单还未支付，请继续完成支付';
            $messageType = 'warning';
        }
    } else {
        $message = '未找到该订单，请检查订单编号';
        $messageType = 'error';
    }
    
} catch (Exception $e) {
    $message = '查询订单时出错: ' . $e->getMessage();
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>订单查询</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 16px;
            padding: 30px;
            width: 100%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        
        .icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        
        .icon.success {
            color: #4caf50;
        }
        
        .icon.warning {
            color: #ff9800;
        }
        
        .icon.error {
            color: #f44336;
        }
        
        .icon.info {
            color: #2196f3;
        }
        
        .title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .message {
            font-size: 14px;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            margin-bottom: 10px;
        }
        
        .btn-primary {
            background: #2196f3;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1976d2;
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: #666;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .order-info {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: left;
        }
        
        .order-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 13px;
        }
        
        .order-row:last-child {
            margin-bottom: 0;
        }
        
        .order-label {
            color: #666;
        }
        
        .order-value {
            color: #333;
            font-weight: 500;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #2196f3;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .redirect-tip {
            font-size: 13px;
            color: #999;
            margin-top: 15px;
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
        <div class="icon <?php echo $messageType; ?>">
            <?php if ($messageType == 'success'): ?>
                <i class="fas fa-check-circle"></i>
            <?php elseif ($messageType == 'warning'): ?>
                <i class="fas fa-exclamation-triangle"></i>
            <?php elseif ($messageType == 'error'): ?>
                <i class="fas fa-times-circle"></i>
            <?php else: ?>
                <i class="fas fa-info-circle"></i>
            <?php endif; ?>
        </div>
        
        <div class="title">订单查询结果</div>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
        
        <?php if (isset($order) && $order): ?>
        <div class="order-info">
            <div class="order-row">
                <span class="order-label">订单编号</span>
                <span class="order-value"><?php echo htmlspecialchars($order['order_no']); ?></span>
            </div>
            <div class="order-row">
                <span class="order-label">订单状态</span>
                <span class="order-value">
                    <?php
                    $statusMap = [
                        0 => '待支付',
                        1 => '已支付',
                        2 => '处理中',
                        3 => '已完成',
                        4 => '已取消'
                    ];
                    echo htmlspecialchars($statusMap[$order['order_status']] ?? '未知');
                    ?>
                </span>
            </div>
            <?php if ($order['total_amount']): ?>
            <div class="order-row">
                <span class="order-label">订单金额</span>
                <span class="order-value">¥<?php echo number_format($order['total_amount'], 2); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($messageType == 'success'): ?>
        <div class="redirect-tip">
            <i class="fas fa-spinner fa-spin"></i> 页面即将跳转到首页...
        </div>
        <a href="index.php" class="btn btn-primary">立即前往</a>
        <?php else: ?>
        <a href="payment_select.php?order_no=<?php echo urlencode($orderNo); ?>" class="btn btn-primary">继续支付</a>
        <a href="index.php" class="btn btn-secondary">返回首页</a>
        <?php endif; ?>
    </div>
    
    <script>
        // 每5秒自动刷新一次
        <?php if ($messageType == 'warning'): ?>
        setTimeout(function() {
            window.location.reload();
        }, 5000);
        <?php endif; ?>
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
