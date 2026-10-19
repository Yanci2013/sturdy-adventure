<?php
require_once '../config.php';
require_once '../includes/session_manager.php';

// 确保 session 已启动
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 调试信息（生产环境请删除）
error_log('cancel_order.php - Session: ' . print_r($_SESSION, true));
error_log('cancel_order.php - is_h5_logged_in: ' . (is_h5_logged_in() ? 'true' : 'false'));
error_log('cancel_order.php - get_h5_user_id: ' . get_h5_user_id());

// 检查用户是否登录
if (!is_h5_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '未登录，请重新登录']);
    exit;
}

$current_user_id = get_h5_user_id();

// 获取订单ID
$orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
if ($orderId <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '无效的订单ID']);
    exit;
}

try {
    // 数据库连接
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", 
                 DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // 检查订单是否属于当前用户
    $stmt = $db->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $current_user_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => '订单不存在或无权操作']);
        exit;
    }
    
    // 获取取消原因
    $cancelReason = isset($_POST['cancel_reason']) ? trim($_POST['cancel_reason']) : '';
    if (empty($cancelReason)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => '请填写取消原因']);
        exit;
    }
    
    // 处理上传的图片
    $cancelImages = [];
    if (!empty($_FILES['cancel_images'])) {
        $uploadDir = '../assets/images/img/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        foreach ($_FILES['cancel_images']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['cancel_images']['error'][$key] === UPLOAD_ERR_OK) {
                $fileName = uniqid() . '_' . basename($_FILES['cancel_images']['name'][$key]);
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($tmpName, $filePath)) {
                    $cancelImages[] = 'assets/images/img/' . $fileName;
                }
            }
        }
    }
    
    // 更新订单信息 - 同时设置订单状态为关闭中(5)和取消审核状态为待审核
    $stmt = $db->prepare("
        UPDATE orders SET 
            order_status = 5,
            cancel_reason = ?,
            cancel_images = ?,
            cancel_by = ?,
            cancel_time = NOW(),
            cancel_review_status = 'pending',
            update_time = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([
        $cancelReason,
        json_encode($cancelImages, JSON_UNESCAPED_UNICODE),
        $current_user_id,
        $orderId
    ]);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => '取消申请已提交']);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '数据库错误: ' . $e->getMessage()]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
