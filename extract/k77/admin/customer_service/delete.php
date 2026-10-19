<?php
require_once __DIR__.'/../../includes/session_manager.php';
require_once __DIR__.'/../../includes/common.php';
require_once __DIR__.'/../../includes/log_helper.php';

if (!is_admin_logged_in()) {
    header('Location: ../login.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    try {
        // 先获取要删除的客服信息
        $stmt = $pdo->prepare("SELECT name FROM customer_services WHERE id = ?");
        $stmt->execute([$id]);
        $service_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 删除客服
        $stmt = $pdo->prepare("DELETE FROM customer_services WHERE id = ?");
        $stmt->execute([$id]);
        
        // 记录管理员操作日志
        $admin_id = $_SESSION['admin_id'] ?? null;
        $admin_name = $_SESSION['admin_username'] ?? '未知管理员';
        $operation_details = "删除客服：ID=" . $id . ", 名称=" . ($service_info['name'] ?? '未知');
        log_admin_operation($pdo, $admin_id, $admin_name, 'delete_customer_service', $operation_details);
        
    } catch (Exception $e) {
        error_log('Error deleting customer service: ' . $e->getMessage());
    }
}

header('Location: list.php');
exit;