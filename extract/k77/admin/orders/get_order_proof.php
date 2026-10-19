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

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => '订单ID无效']);
    exit;
}

try {
    // 查询订单信息
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :order_id");
    $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('订单不存在');
    }
    
    // 检查订单是否已提交结单材料
    if ($order['order_status'] < 3) {
        throw new Exception('订单未提交结单材料');
    }
    
    // 检查是否有结单材料
    $hasProof = false;
    if (!empty($order['completion_proof']) || !empty($order['completion_images']) || !empty($order['proof_images']) || 
        !empty($order['completion_note']) || !empty($order['completion_remark']) || !empty($order['proof_note']) ||
        !empty($order['submit_images']) || !empty($order['submit_notes'])) {
        $hasProof = true;
    }
    
    if (!$hasProof) {
        throw new Exception('订单未提交结单材料');
    }
    
    // 获取结单证明材料
    $proofImages = [];
    $proofNote = '';
    
    // 检查是否有结单图片
    if (!empty($order['completion_proof'])) {
        $proofImages = explode(',', $order['completion_proof']);
    } elseif (!empty($order['completion_images'])) {
        $proofImages = explode(',', $order['completion_images']);
    } elseif (!empty($order['proof_images'])) {
        $proofImages = explode(',', $order['proof_images']);
    } elseif (!empty($order['submit_images'])) {
        // 尝试解析JSON格式的submit_images
        $submitImages = json_decode($order['submit_images'], true);
        if (is_array($submitImages)) {
            $proofImages = $submitImages;
        } else {
            $proofImages = explode(',', $order['submit_images']);
        }
    }
    
    // 检查是否有结单备注
    if (!empty($order['completion_note'])) {
        $proofNote = $order['completion_note'];
    } elseif (!empty($order['completion_remark'])) {
        $proofNote = $order['completion_remark'];
    } elseif (!empty($order['proof_note'])) {
        $proofNote = $order['proof_note'];
    } elseif (!empty($order['submit_notes'])) {
        $proofNote = $order['submit_notes'];
    }
    
    // 处理图片路径
    $processedImages = [];
    foreach ($proofImages as $image) {
        $image = trim($image);
        if (!empty($image)) {
            // 检查是否是完整URL
            if (!filter_var($image, FILTER_VALIDATE_URL)) {
                // 检查是否已经包含assets路径
                if (strpos($image, 'assets') === 0) {
                    // 如果已经包含assets路径，添加相对路径前缀
                    $image = '../../' . $image;
                } else {
                    // 检查是否是submit_images字段中的图片（通常存储在orders目录）
                    if (!empty($order['submit_images'])) {
                        $image = '../../assets/images/orders/' . $image;
                    } else {
                        // 其他情况，使用order_proof目录
                        $image = '../../assets/images/order_proof/' . $image;
                    }
                }
            }
            $processedImages[] = $image;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => '获取结单证明材料成功',
        'data' => [
            'images' => $processedImages,
            'note' => $proofNote
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>