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
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => '订单ID无效']);
    exit;
}

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => '罚单金额必须大于0']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // 查询订单信息
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :order_id");
    $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('订单不存在');
    }
    
    if (!$order['hunter_user_id']) {
        throw new Exception('该订单暂无接单打手');
    }
    
    // 查询打手信息
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $order['hunter_user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $hunter = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$hunter) {
        throw new Exception('打手电不存在');
    }
    
    // 检查打手余额是否足够
    $currentBalance = floatval($hunter['balance'] ?? 0);
    if ($currentBalance < $amount) {
        throw new Exception('打手电余额不足');
    }
    
    // 扣除罚单金额
    $stmt = $pdo->prepare("UPDATE users SET balance = balance - :amount WHERE id = :user_id");
    $stmt->bindParam(':amount', $amount, PDO::PARAM_STR);
    $stmt->bindParam(':user_id', $order['hunter_user_id'], PDO::PARAM_INT);
    $stmt->execute();
    
    // 记录罚单信息
    $stmt = $pdo->prepare("INSERT INTO penalty_records (initiator_id, handler_id, reason_id, amount, penalized_id, status, order_no, created_at, status_time, deduction_balance, deduction_deposit, review_reply) VALUES (:initiator_id, :handler_id, :reason_id, :amount, :penalized_id, :status, :order_no, NOW(), NOW(), :deduction_balance, :deduction_deposit, :review_reply)");
    $initiator_id = 0; // 系统发单
    $handler_id = 0; // 系统处理
    $penalized_id = $order['hunter_user_id'];
    $reason_id = 1; // 默认原因
    $status = 'completed';
    $order_no = $order['order_no'] ?? '';
    $deduction_balance = $amount;
    $deduction_deposit = 0;
    $review_reply = '系统开罚单';
    $stmt->bindParam(':initiator_id', $initiator_id, PDO::PARAM_INT);
    $stmt->bindParam(':handler_id', $handler_id, PDO::PARAM_INT);
    $stmt->bindParam(':reason_id', $reason_id, PDO::PARAM_INT);
    $stmt->bindParam(':amount', $amount, PDO::PARAM_STR);
    $stmt->bindParam(':penalized_id', $penalized_id, PDO::PARAM_INT);
    $stmt->bindParam(':status', $status, PDO::PARAM_STR);
    $stmt->bindParam(':order_no', $order_no, PDO::PARAM_STR);
    $stmt->bindParam(':deduction_balance', $deduction_balance, PDO::PARAM_STR);
    $stmt->bindParam(':deduction_deposit', $deduction_deposit, PDO::PARAM_STR);
    $stmt->bindParam(':review_reply', $review_reply, PDO::PARAM_STR);
    $stmt->execute();
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => '开罚单成功，已扣除打手电余额 ¥' . number_format($amount, 2)]);
    
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>