<?php
require '../includes/session_manager.php';
include("../includes/common.php");
require_once("../includes/log_helper.php");

header('Content-Type: application/json');

// 检查用户是否登录
if (!is_h5_logged_in()) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit();
}

try {
    global $pdo;
    if (!$pdo) {
        throw new Exception('数据库连接失败');
    }

    // 获取POST数据
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // 验证数据
    if (empty($data['productId']) || empty($data['productName']) || empty($data['productPrice'])) {
        throw new Exception('订单信息不完整');
    }

    $userId = get_h5_user_id();
    $productId = intval($data['productId']);
    $productName = trim($data['productName']);
    $productPrice = floatval($data['productPrice']);
    $gameRegion = isset($data['gameRegion']) ? trim($data['gameRegion']) : '';
    $hunterOption = isset($data['hunterOption']) ? trim($data['hunterOption']) : 'system';
    $hunterId = isset($data['hunterId']) ? intval($data['hunterId']) : null;
    $paymentType = isset($data['paymentType']) ? trim($data['paymentType']) : 'alipay';
    $inputs = isset($data['inputs']) ? $data['inputs'] : [];

    // 生成订单号
    $orderNo = date("YmdHis") . mt_rand(100, 999);

    // 开始事务
    $pdo->beginTransaction();

    // 如果是余额支付，检查并扣除余额
    if ($paymentType === 'balance') {
        // 检查用户余额
        $stmt = $pdo->prepare("SELECT balance FROM h5user WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception('用户不存在');
        }
        
        if ($user['balance'] < $productPrice) {
            throw new Exception('余额不足');
        }
        
        // 扣除余额
        $updateStmt = $pdo->prepare("UPDATE h5user SET balance = balance - ? WHERE id = ?");
        if (!$updateStmt->execute([$productPrice, $userId])) {
            throw new Exception('扣除余额失败');
        }
    }
    
    // 创建订单记录
    $stmt = $pdo->prepare("INSERT INTO orders (
        order_no, 
        user_id,
        product_id, 
        product_name, 
        price, 
        game_region, 
        hunter_option, 
        hunter_id, 
        payment_type,
        payment_amount,
        input_data, 
        order_status, 
        fadan_id, 
        create_time
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $orderStatus = $paymentType === 'balance' ? 2 : 0; // 2-待服务, 0-待支付
    $inputData = json_encode($inputs, JSON_UNESCAPED_UNICODE);
    $fadanId = 1; // 系统发单
    
    $stmt->execute([
        $orderNo,
        $userId,
        $productId,
        $productName,
        $productPrice,
        $gameRegion,
        $hunterOption,
        $hunterId,
        $paymentType,
        $productPrice,
        $inputData,
        $orderStatus,
        $fadanId
    ]);
    
    $orderId = $pdo->lastInsertId();
    
    // 获取用户信息用于日志
    $user_stmt = $pdo->prepare("SELECT username FROM h5user WHERE id = ?");
    $user_stmt->execute([$userId]);
    $user = $user_stmt->fetch();
    
    // 记录订单创建日志
    log_order_action(
        $pdo,
        $orderId,
        $orderNo,
        'create',
        $paymentType === 'balance' ? '订单已创建并支付成功' : '订单已创建，待支付',
        $userId,
        $user['username'] ?? '未知用户',
        'customer',
        null,
        null,
        json_encode($inputs, JSON_UNESCAPED_UNICODE)
    );
    
    // 如果是余额支付，记录余额变动
    if ($paymentType === 'balance') {
        $stmt = $pdo->prepare("INSERT INTO balance_logs (
            user_id,
            order_id,
            amount,
            balance,
            type,
            remark,
            created_at
        ) VALUES (?, ?, ?, ?, '消费', ?, NOW())");
        
        $remark = "购买商品: {$productName}";
        $newBalance = $user['balance'] - $productPrice;
        
        $stmt->execute([$userId, $orderId, -$productPrice, $newBalance, $remark]);
    }
    
    // 提交事务
    $pdo->commit();
    
    // 根据支付类型返回不同结果
    if ($paymentType === 'balance') {
        // 余额支付成功
        echo json_encode([
            'success' => true,
            'orderId' => $orderId
        ]);
    } else {
        // 第三方支付，生成支付URL
        $paymentUrl = "../plugins/paypal/yhpay/epayapi.php?order_no={$orderNo}&type={$paymentType}";
        echo json_encode([
            'success' => true,
            'paymentUrl' => $paymentUrl
        ]);
    }
} catch (Exception $e) {
    // 回滚事务
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('订单提交错误: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
