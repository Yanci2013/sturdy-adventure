<?php
require_once("../includes/session_manager.php");

// 只清除User相关的session
clear_user_session();

// 重定向到登录页面
header('Location: login.php');
exit();
?>
