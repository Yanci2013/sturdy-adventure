<?php
/**
 * AJAX查询订单状态
 */
header('Content-Type: application/json');
session_start();

require_once '../includes/common.php';
require_once '../includes/session_manager.php';

// 检查登录
if (!is_h5_logged_in()) {
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

$orderNo = isset($_GET['order_no']) ? $_GET['order_no'] : '';

if (empty($orderNo)) {
    echo json_encode(['success' => false, 'error' => '订单号不能为空']);
    exit;
}

try {
    // 查询订单
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_no = ?");
    $stmt->execute([$orderNo]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        // 检查订单是否已支付
        $isPaid = $order['order_status'] >= 2;
        
        echo json_encode([
            'success' => true,
            'paid' => $isPaid,
            'status' => $order['order_status'],
            'order_no' => $order['order_no'],
            'total_amount' => $order['total_amount'] ?? 0
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => '未找到订单']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => '查询失败: ' . $e->getMessage()]);
}
