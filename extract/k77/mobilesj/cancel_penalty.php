<?php
require_once '../includes/common.php';

// 强制设置为纯文本响应
header('Content-Type: text/plain; charset=utf-8');

// 确保没有任何输出在header之前
ob_start();

// 检查会话
session_start();
if (empty($_SESSION['partner_id'])) {
    die("ERROR: 未登录");
}

// 获取罚单ID
$penalty_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($penalty_id <= 0) {
    die("ERROR: 无效的罚单ID");
}

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    $pdo->beginTransaction();

    // 检查罚单是否属于当前商家
    $check_stmt = $pdo->prepare("SELECT id, status FROM penalty_records WHERE id = ? AND initiator_id = ? LIMIT 1");
    $check_stmt->execute([$penalty_id, $_SESSION['partner_id']]);
    $penalty = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$penalty) {
        $pdo->rollBack();
        die("ERROR: 无权操作此罚单");
    }
    
    // 检查罚单状态
    if (!in_array($penalty['status'], ['pending', 'processing'])) {
        $pdo->rollBack();
        die("ERROR: 当前状态的罚单不能撤销");
    }
    
    // 更新罚单状态
    $update_stmt = $pdo->prepare("UPDATE penalty_records SET status = 'closed', updated_at = NOW() WHERE id = ?");
    $result = $update_stmt->execute([$penalty_id]);
    
    if (!$result) {
        $pdo->rollBack();
        die("ERROR: 更新罚单状态失败");
    }

    $pdo->commit();
    
    die("SUCCESS: 罚单已成功撤销");
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("撤销罚单失败: " . $e->getMessage());
    die("ERROR: 系统错误 - " . $e->getMessage());
}

// 清除缓冲区
ob_end_flush();
?>