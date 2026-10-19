<?php
// 安全启动Session
session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

// 引入配置文件和通用函数
require __DIR__ . '/../../includes/common.php';

// 数据库连接函数
function connectDB() {
    global $pdo;
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("数据库连接失败: " . $e->getMessage());
    }
}

// 获取管理员信息的函数
function getAdminById($pdo, $admin_id) {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$admin_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// 连接数据库
$pdo = connectDB();

// 检测是否是AJAX请求，对于AJAX请求不输出HTML头部
$is_ajax_request = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$is_create_chat_action = isset($_POST['action']) && $_POST['action'] == 'create_chat';

// 如果不是AJAX请求，才输出HTML头部
if (!$is_ajax_request || !$is_create_chat_action) {
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理后台 - 聊天</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #343a40;
            color: white;
            padding: 10px 0;
            margin-bottom: 20px;
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .user-info {
            display: flex;
            align-items: center;
        }
        .user-name {
            margin-right: 15px;
        }
        .logout-btn {
            color: white;
            text-decoration: none;
        }
        .logout-btn:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container header-content">
            <div class="logo">chat</div>
            <div class="user-info">
                <span class="user-name">管理员</span>
                <a href="logout.php" class="logout-btn">退出登录</a>
            </div>
        </div>
    </div>
    
    <div class="container">
<?php } ?>