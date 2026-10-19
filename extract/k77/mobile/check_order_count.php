<?php
// 纪元商户端 - 检查新订单接口
header('Content-Type: application/json; charset=utf-8');

try {
    // 引入header.php（包含数据库连接）
    require __DIR__ . '/header.php';
    
    if (isset($pdo)) {
        // 方案1: 检查最近10秒内创建的订单（最准确）
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM orders WHERE create_time >= DATE_SUB(NOW(), INTERVAL 10 SECOND)");
        $stmt->execute();
        $result = $stmt->fetch();
        $newOrderCount = (int)$result['cnt'];
        
        // 方案2: 同时检查所有待服务的订单（状态=2）
        $stmt2 = $pdo->query("SELECT COUNT(*) as cnt FROM orders WHERE order_status = 2");
        $result2 = $stmt2->fetch();
        $pendingOrderCount = (int)$result2['cnt'];
        
        // 获取最新的订单信息（用于显示）
        $stmt3 = $pdo->prepare("SELECT order_no, product_name, create_time FROM orders WHERE create_time >= DATE_SUB(NOW(), INTERVAL 10 SECOND) ORDER BY create_time DESC LIMIT 1");
        $stmt3->execute();
        $latestOrder = $stmt3->fetch();
        
        echo json_encode([
            'success' => true,
            'new_order_count' => $newOrderCount,
            'pending_order_count' => $pendingOrderCount,
            'latest_order' => $latestOrder,
            'time' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'new_order_count' => 0,
            'pending_order_count' => 0,
            'error' => '数据库连接失败'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'new_order_count' => 0,
        'pending_order_count' => 0,
        'error' => $e->getMessage()
    ]);
}
?>

