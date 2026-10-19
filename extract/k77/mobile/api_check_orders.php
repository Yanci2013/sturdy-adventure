<?php
// 纪元商户端 - 独立检测新订单接口（不需要登录）
header('Content-Type: application/json; charset=utf-8');

// 允许跨域
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

try {
    // 1. 直接加载配置文件（不通过header.php，避免登录检查）
    $configPath = __DIR__ . '/../config.php';
    if (file_exists($configPath)) {
        require_once $configPath;
    } else {
        echo json_encode([
            'success' => false,
            'error' => '找不到配置文件'
        ]);
        exit;
    }
    
    // 2. 直接连接数据库（使用常量）
    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 1. 检查最近60秒内创建的订单（1分钟内）
        $stmt = $pdo->prepare("SELECT order_no, product_name, create_time, order_status FROM orders WHERE create_time >= DATE_SUB(NOW(), INTERVAL 60 SECOND) ORDER BY create_time DESC LIMIT 5");
        $stmt->execute();
        $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 2. 检查所有待接单的订单总数（order_status = 1）
        $stmt2 = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE order_status = 1");
        $result2 = $stmt2->fetch();
        $totalPending = (int)$result2['total'];
        
        echo json_encode([
            'success' => true,
            'count' => count($recentOrders),
            'orders' => $recentOrders,
            'total_pending' => $totalPending,
            'server_time' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => '数据库配置不完整'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>

