<?php
require_once("../includes/session_manager.php");
include("../includes/common.php");
require_once("../includes/log_helper.php");

// 获取当前管理员信息
$admin_id = $_SESSION['admin_id'] ?? null;
$admin_name = $_SESSION['admin_username'] ?? '未知管理员';

// 记录登出日志
if ($admin_id && $pdo) {
    log_admin_operation(
        $pdo,
        $admin_id,
        $admin_name,
        'logout',
        '管理员' . $admin_name . '退出了系统'
    );
}

// 只清除Admin相关的session
clear_admin_session();

header('Location: login.php');
exit();
?>