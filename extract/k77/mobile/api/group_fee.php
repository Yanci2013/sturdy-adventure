<?php
/**
 * 团费相关API接口
 */
require_once __DIR__ . '/../../includes/common.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

if (!$user_id) {
    echo json_encode(['success' => false, 'msg' => '请先登录']);
    exit;
}

switch ($action) {
    case 'check_pending':
        // 检查是否有待缴纳的团费
        try {
            // 先检查是否有进行中的批次（调试用）
            $stmt = $pdo->query("SELECT COUNT(*) FROM group_fee_batches WHERE status = 1");
            $active_batches = $stmt->fetchColumn();
            
            // 检查当前用户是否在目标列表中（调试用）
            $stmt = $pdo->prepare("SELECT b.id, b.batch_name, t.user_id 
                FROM group_fee_batches b 
                LEFT JOIN group_fee_targets t ON b.id = t.batch_id AND t.user_id = ?
                WHERE b.status = 1 AND b.target_type = 2");
            $stmt->execute([$user_id]);
            $user_targets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 检查是否有进行中的团费批次（只检查被指定的用户）
            $stmt = $pdo->prepare("SELECT b.*, p.id as payment_id, p.status 
                FROM group_fee_batches b 
                LEFT JOIN group_fee_payments p ON b.id = p.batch_id AND p.user_id = ?
                WHERE b.status = 1 
                AND (p.id IS NULL OR p.status = 0)
                AND (
                    b.target_type = 1 
                    OR (b.target_type = 2 AND EXISTS (
                        SELECT 1 FROM group_fee_targets t 
                        WHERE t.batch_id = b.id AND t.user_id = ?
                    ))
                )
                ORDER BY b.created_at DESC 
                LIMIT 1");
            $stmt->execute([$user_id, $user_id]);
            $pending_fee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 如果有待缴纳的团费，强制关闭接单状态
            if ($pending_fee) {
                // 检查用户当前接单状态
                $stmt = $pdo->prepare("SELECT available_for_orders FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // 如果正在接单，强制关闭
                if ($user && $user['available_for_orders'] == 1) {
                    $stmt = $pdo->prepare("UPDATE users SET available_for_orders = 0 WHERE id = ?");
                    $stmt->execute([$user_id]);
                }
                
                echo json_encode([
                    'success' => true,
                    'has_pending' => true,
                    'force_disabled' => true,
                    'data' => [
                        'batch_id' => $pending_fee['id'],
                        'batch_name' => $pending_fee['batch_name'],
                        'amount' => floatval($pending_fee['amount']),
                        'description' => $pending_fee['description'],
                        'payment_id' => $pending_fee['payment_id'] ?? null
                    ]
                ]);
            } else {
                // 返回调试信息
                echo json_encode([
                    'success' => true, 
                    'has_pending' => false,
                    'debug' => [
                        'user_id' => $user_id,
                        'active_batches' => $active_batches,
                        'user_targets' => $user_targets
                    ]
                ]);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'msg' => '查询失败']);
        }
        break;
        
    case 'create_payment':
        // 创建团费缴纳记录
        $batch_id = intval($_POST['batch_id'] ?? 0);
        $payment_type = $_POST['payment_type'] ?? '';
        
        if (!$batch_id || !in_array($payment_type, ['wechat', 'alipay', 'balance'])) {
            echo json_encode(['success' => false, 'msg' => '参数错误']);
            exit;
        }
        
        try {
            // 获取批次信息（检查用户是否在目标列表中）
            $stmt = $pdo->prepare("SELECT b.* FROM group_fee_batches b 
                WHERE b.id = ? AND b.status = 1
                AND (
                    b.target_type = 1 
                    OR (b.target_type = 2 AND EXISTS (
                        SELECT 1 FROM group_fee_targets t 
                        WHERE t.batch_id = b.id AND t.user_id = ?
                    ))
                )");
            $stmt->execute([$batch_id, $user_id]);
            $batch = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$batch) {
                echo json_encode(['success' => false, 'msg' => '团费批次不存在或您不在缴纳名单中']);
                exit;
            }
            
            // 检查是否已有待支付记录
            $stmt = $pdo->prepare("SELECT * FROM group_fee_payments WHERE batch_id = ? AND user_id = ? AND status = 0");
            $stmt->execute([$batch_id, $user_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                $payment_id = $existing['id'];
            } else {
                // 创建新记录
                $stmt = $pdo->prepare("INSERT INTO group_fee_payments (batch_id, user_id, amount, payment_type, status) VALUES (?, ?, ?, ?, 0)");
                $stmt->execute([$batch_id, $user_id, $batch['amount'], $payment_type]);
                $payment_id = $pdo->lastInsertId();
            }
            
            // 余额支付直接处理
            if ($payment_type === 'balance') {
                // 检查余额
                $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user['balance'] < $batch['amount']) {
                    echo json_encode(['success' => false, 'msg' => '余额不足']);
                    exit;
                }
                
                // 扣除余额
                $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$batch['amount'], $user_id]);
                
                // 更新支付记录
                $trade_no = 'GF' . date('YmdHis') . rand(1000, 9999);
                $stmt = $pdo->prepare("UPDATE group_fee_payments SET status = 1, trade_no = ?, paid_at = NOW() WHERE id = ?");
                $stmt->execute([$trade_no, $payment_id]);
                
                // 记录资金明细
                $stmt = $pdo->prepare("INSERT INTO fund_details (user_id, source, destination, amount, related_id, note, order_no, create_time) VALUES (?, 'balance', 'system_income', ?, ?, '团费缴纳', ?, NOW())");
                $stmt->execute([$user_id, -$batch['amount'], $payment_id, $trade_no]);
                
                echo json_encode(['success' => true, 'msg' => '支付成功', 'paid' => true]);
                exit;
            }
            
            // 微信支付或支付宝支付，返回支付参数
            $order_no = 'GF' . date('YmdHis') . rand(1000, 9999);
            $stmt = $pdo->prepare("UPDATE group_fee_payments SET trade_no = ? WHERE id = ?");
            $stmt->execute([$order_no, $payment_id]);
            
            echo json_encode([
                'success' => true,
                'payment_id' => $payment_id,
                'order_no' => $order_no,
                'amount' => $batch['amount'],
                'payment_type' => $payment_type
            ]);
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'msg' => '创建支付失败']);
        }
        break;
        
    case 'check_payment_status':
        // 检查支付状态
        $payment_id = intval($_GET['payment_id'] ?? 0);
        
        try {
            $stmt = $pdo->prepare("SELECT status FROM group_fee_payments WHERE id = ? AND user_id = ?");
            $stmt->execute([$payment_id, $user_id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($payment) {
                echo json_encode([
                    'success' => true,
                    'paid' => $payment['status'] == 1
                ]);
            } else {
                echo json_encode(['success' => false, 'msg' => '记录不存在']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'msg' => '查询失败']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'msg' => '未知操作']);
}
?>
