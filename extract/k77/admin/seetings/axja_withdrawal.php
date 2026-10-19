<?php
// 开启输出缓冲
ob_start();

// 引入数据库连接文件
require_once __DIR__.'/../../includes/common.php';

// 获取提现ID并验证
$withdrawal_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($withdrawal_id <= 0) {
    ob_end_clean();
    header('Location: withdrawal.php?error=invalid_id');
    exit();
}

try {
    // 获取提现记录
    $sql = "SELECT w.*, u.username, u.balance 
            FROM withdrawals w 
            LEFT JOIN users u ON w.user_id = u.id 
            WHERE w.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$withdrawal_id]);

    if ($stmt->rowCount() === 0) {
        ob_end_clean();
        header('Location: withdrawal.php?error=invalid_id');
        exit();
    }

    $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);

    // 检查提现状态
    if ($withdrawal['status'] !== 'pending') {
        ob_end_clean();
        header('Location: withdrawal.php?error=invalid_status');
        exit();
    }

    // 检查用户是否存在
    if (empty($withdrawal['username'])) {
        ob_end_clean();
        header('Location: withdrawal.php?error=user_not_found');
        exit();
    }

    // 确定是批准还是拒绝操作
    $action = basename($_SERVER['PHP_SELF']);
    $is_approve = strpos($action, 'approve') !== false;

    $pdo->beginTransaction();

    if ($is_approve) {
        // 批准提现逻辑
        // 1. 更新提现状态为已处理
        $update_sql = "UPDATE withdrawals SET status = 'processed', processed_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($update_sql);
        $stmt->execute([$withdrawal_id]);
        
        if ($stmt->rowCount() <= 0) {
            throw new Exception("更新提现状态失败");
        }
        
        // 2. 创建资金记录（支出）
        $amount = $withdrawal['amount'];
        $user_id = $withdrawal['user_id'];
        
        // 自动生成订单号
        $timestamp = date('YmdHi');
        $random = str_pad(mt_rand(1, 99), 2, '0', STR_PAD_LEFT);
        $withdrawal_id_suffix = str_pad(substr($withdrawal_id, -2), 2, '0', STR_PAD_LEFT);
        $order_no = 'W' . $timestamp . $random . $withdrawal_id_suffix;
        
        // 记录资金明细（提现金额为负数，表示支出）
        $insert_sql = "INSERT INTO fund_details 
                      (user_id, source, destination, amount, related_id, note, order_no)
                      VALUES 
                      (?, 'withdrawal', 'user_account', -?, ?, '提现成功', ?)";
        
        $stmt = $pdo->prepare($insert_sql);
        $stmt->execute([$user_id, $amount, $withdrawal_id, $order_no]);
        
        if ($stmt->rowCount() <= 0) {
            throw new Exception("记录资金明细失败");
        }
        
        $success_param = 'approved';
    } else {
        // 拒绝提现逻辑
        // 1. 更新提现状态为拒绝
        $update_sql = "UPDATE withdrawals SET status = 'rejected', processed_at = NOW(), reject_reason = '管理员拒绝' WHERE id = ?";
        $stmt = $pdo->prepare($update_sql);
        $stmt->execute([$withdrawal_id]);
        
        if ($stmt->rowCount() <= 0) {
            throw new Exception("更新提现状态失败");
        }
        
        // 2. 将提现金额加回用户余额
        $amount = $withdrawal['amount'];
        $user_id = $withdrawal['user_id'];
        
        // 检查金额是否有效
        if ($amount <= 0) {
            throw new Exception("无效的提现金额");
        }
        
        $balance_update_sql = "UPDATE users SET balance = balance + ? WHERE id = ?";
        $stmt = $pdo->prepare($balance_update_sql);
        $stmt->execute([$amount, $user_id]);
        
        if ($stmt->rowCount() <= 0) {
            throw new Exception("恢复用户余额失败");
        }
        
        // 3. 创建资金记录（收入）
        $timestamp = date('YmdHi');
        $random = str_pad(mt_rand(1, 99), 2, '0', STR_PAD_LEFT);
        $withdrawal_id_suffix = str_pad(substr($withdrawal_id, -2), 2, '0', STR_PAD_LEFT);
        $order_no = 'W' . $timestamp . $random . $withdrawal_id_suffix;
        
        // 记录资金明细（金额为正数，表示收入）
        $insert_sql = "INSERT INTO fund_details 
                      (user_id, source, destination, amount, related_id, note, order_no)
                      VALUES 
                      (?, 'withdrawal_refund', 'user_account', ?, ?, '提现被拒绝，金额已退回', ?)";
        
        $stmt = $pdo->prepare($insert_sql);
        $stmt->execute([$user_id, $amount, $withdrawal_id, $order_no]);
        
        if ($stmt->rowCount() <= 0) {
            throw new Exception("记录资金明细失败");
        }
        
        $success_param = 'rejected';
    }
    
    $pdo->commit();
    ob_end_clean();
    header('Location: withdrawal.php?success=' . $success_param);
    exit();
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ob_end_clean();
    header('Location: withdrawal.php?error=db_error&msg=' . urlencode($e->getMessage()));
    exit();
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ob_end_clean();
    header('Location: withdrawal.php?error=process_failed&msg=' . urlencode($e->getMessage()));
    exit();
}