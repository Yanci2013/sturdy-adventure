<?php
require_once '../includes/session_manager.php';

// 只清除H5相关的session
clear_h5_session();

// 跳转到登录页面
header("Location: login.php");
exit();
?>