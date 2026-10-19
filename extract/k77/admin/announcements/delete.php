<?php
require_once("../../includes/session_manager.php");
include("../../includes/common.php");
require_once("../../includes/log_helper.php");

// 检查管理员登录
if (!is_admin_logged_in()) {
    header("Location: ../login.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    try {
        global $pdo;
        if ($pdo) {
            // 先获取被删除的公告信息
            $announcement_info = null;
            try {
                $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
                $stmt->execute([$id]);
                $announcement_info = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                // 获取信息失败时继续删除操作
            }
            
            $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
            
            if ($stmt->execute([$id])) {
                // 记录管理员操作日志
                $admin_id = $_SESSION['admin_id'] ?? null;
                $admin_name = $_SESSION['admin_username'] ?? '未知管理员';
                $operation_details = "删除公告: ID=" . $id . ", 标题=" . ($announcement_info['title'] ?? '未知');
                log_admin_operation($pdo, $admin_id, $admin_name, 'delete_announcement', $operation_details);
                
                // 删除成功，跳转回列表
                header("Location: list.php?msg=deleted");
                exit();
            } else {
                header("Location: list.php?msg=error");
                exit();
            }
        }
    } catch (Exception $e) {
        error_log('Error deleting announcement: ' . $e->getMessage());
        header("Location: list.php?msg=error");
        exit();
    }
} else {
    header("Location: list.php");
    exit();
}
