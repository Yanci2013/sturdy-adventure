<?php
// 使用绝对路径更可靠（添加防护）
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// 加载session管理
require_once APP_ROOT . '/includes/session_manager.php';

// 生成CSRF令牌
if (empty($_SESSION['user_csrf_token'])) {
    if (function_exists('random_bytes')) {
        $_SESSION['user_csrf_token'] = bin2hex(random_bytes(32));
    } else {
        $_SESSION['user_csrf_token'] = md5(uniqid(rand(), true));
    }
}

// 更严格的登录检查
if (!is_user_logged_in()) {
    header('Location: login.php');
    exit;
}

// 确保先加载基础配置和函数库
require_once APP_ROOT . '/includes/common.php';

// 检查用户审核状态（排除pending_approval.php页面本身和index.php）
$current_script = basename($_SERVER['PHP_SELF']);
if ($current_script !== 'pending_approval.php' && $current_script !== 'index.php') {
    require_user_approved($pdo);
}

// 获取当前用户数据
$user_id = get_user_user_id();
$userData = getUserById($pdo, $user_id);

if (!$userData) {
    // 用户不存在，清除session并重定向
    clear_user_session();
    header('Location: login.php');
    exit;
}

// 将用户数据存入session避免重复查询
$_SESSION['user_user_role'] = $userData['user_role'];

// 设置默认页面标题
$pageTitle = $pageTitle ?? '发单抢单系统 - 用户端';

// 获取系统设置
$settings = getSystemSettings($pdo);

// 开始输出缓冲
ob_start();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- 添加CSRF令牌的meta标签 -->
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['user_csrf_token']); ?>">
    <title><?= htmlspecialchars($settings['site_title'] ?? '俱乐部打手端') ?></title>
    <link rel="stylesheet" href="../assets/layui/css/layui.css">