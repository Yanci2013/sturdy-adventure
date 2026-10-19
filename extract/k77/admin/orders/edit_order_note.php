<?php
require_once '../../config.php';

// 数据库连接
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => '数据库连接失败：' . $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请求方法错误']);
    exit;
}

$orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
$newNote = isset($_POST['note']) ? trim($_POST['note']) : '';

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => '订单ID无效']);
    exit;
}

try {
    // 查询订单信息
    $stmt = $pdo->prepare("SELECT input_fields FROM orders WHERE id = :order_id");
    $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('订单不存在');
    }
    
    // 解析现有的input_fields
    $inputFields = json_decode($order['input_fields'] ?? '{}', true);
    
    // 更新备注字段
    $inputFields['备注'] = $newNote;
    $inputFields['note'] = $newNote; // 兼容处理
    
    // 将更新后的input_fields保存回数据库
    $updatedInputFields = json_encode($inputFields, JSON_UNESCAPED_UNICODE);
    
    $stmt = $pdo->prepare("UPDATE orders SET input_fields = :input_fields WHERE id = :order_id");
    $stmt->bindParam(':input_fields', $updatedInputFields, PDO::PARAM_STR);
    $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => '订单备注修改成功']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>