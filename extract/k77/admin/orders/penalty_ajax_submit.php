<?php
require_once __DIR__.'/../../includes/session_manager.php';
require_once __DIR__.'/../../includes/common.php';

header('Content-Type: application/json');

if (!is_admin_logged_in()) {
    echo json_encode(['success' => false, 'message' => '请先登录管理员账号']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方式']);
    exit;
}

define('FUND_SOURCE_BALANCE', 'balance');
define('FUND_SOURCE_DEPOSIT', 'deposit');
define('FUND_DESTINATION_PENALTY_SYSTEM', 'penalty_system');

function recordFundDetail($pdo, $userId, $partnerId, $source, $destination, $amount, $relatedId = null, $note = '', $orderNo = null) {
    try {
        $sql = "INSERT INTO fund_details 
                (user_id, partner_id, source, destination, amount, related_id, note, order_no)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $userId,
            $partnerId,
            $source,
            $destination,
            $amount,
            $relatedId,
            $note,
            $orderNo
        ]);
    } catch (PDOException $e) {
        error_log("记录资金明细失败: " . $e->getMessage());
        return false;
    }
}

try {
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $reason_id = isset($_POST['reason_id']) ? (int)$_POST['reason_id'] : 0;
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    if ($order_id <= 0) {
        throw new Exception("无效的订单ID");
    }
    if ($reason_id <= 0) {
        throw new Exception("请选择罚款原因");
    }
    if ($amount <= 0) {
        throw new Exception("罚款金额必须大于0");
    }

    $pdo->beginTransaction();

    $sql = "SELECT o.*, hu.username as hunter_username,
                   hu.balance as hunter_balance, hu.deposit as hunter_deposit
            FROM orders o 
            LEFT JOIN users hu ON o.hunter_user_id = hu.id
            WHERE o.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("订单不存在");
    }

    if (!$order['hunter_user_id']) {
        throw new Exception("该订单没有打手，无法开罚单");
    }

    if ($amount > $order['price']) {
        throw new Exception("罚款金额不能大于订单金额");
    }

    $hunter_balance = (float)$order['hunter_balance'];
    $hunter_deposit = (float)$order['hunter_deposit'];

    $deduction_balance = 0;
    $deduction_deposit = 0;

    if ($hunter_balance >= $amount) {
        $deduction_balance = $amount;
    } else {
        $deduction_balance = $hunter_balance;
        $remaining = $amount - $hunter_balance;
        
        if ($hunter_deposit >= $remaining) {
            $deduction_deposit = $remaining;
        } else {
            $deduction_deposit = $hunter_deposit;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO penalty_records 
                          (initiator_id, handler_id, reason_id, amount, penalized_id, status, order_no, created_at, status_time, deduction_balance, deduction_deposit, review_reply)
                          VALUES (?, ?, ?, ?, ?, 'completed', ?, NOW(), NOW(), ?, ?, ?)");
    
    $stmt->execute([
        get_admin_id(),
        get_admin_id(),
        $reason_id,
        $amount,
        $order['hunter_user_id'],
        $order['order_no'],
        $deduction_balance,
        $deduction_deposit,
        $notes
    ]);

    $penalty_id = $pdo->lastInsertId();

    $new_balance = $hunter_balance - $deduction_balance;
    $new_deposit = $hunter_deposit - $deduction_deposit;

    $stmt = $pdo->prepare("UPDATE users SET balance = ?, deposit = ? WHERE id = ?");
    $stmt->execute([$new_balance, $new_deposit, $order['hunter_user_id']]);

    if ($deduction_balance > 0) {
        recordFundDetail(
            $pdo,
            $order['hunter_user_id'],
            null,
            FUND_SOURCE_BALANCE,
            FUND_DESTINATION_PENALTY_SYSTEM,
            -$deduction_balance,
            $penalty_id,
            "罚款扣除 - 罚单ID: $penalty_id",
            "PENALTY_$penalty_id"
        );
    }

    if ($deduction_deposit > 0) {
        recordFundDetail(
            $pdo,
            $order['hunter_user_id'],
            null,
            FUND_SOURCE_DEPOSIT,
            FUND_DESTINATION_PENALTY_SYSTEM,
            -$deduction_deposit,
            $penalty_id,
            "保证金扣除 - 罚单ID: $penalty_id",
            "PENALTY_$penalty_id"
        );
    }

    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'message' => "罚单创建成功，罚单ID: {$penalty_id}，已扣除¥{$amount}",
        'penalty_id' => $penalty_id
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
