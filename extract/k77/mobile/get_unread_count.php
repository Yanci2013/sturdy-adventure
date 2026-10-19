<?php
// 设置响应格式为JSON
header('Content-Type: application/json');

// 使用绝对路径更可靠
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// 确保在最开始启动 session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 默认响应
$response = ['count' => 0];

// 只处理已登录用户的真实请求，不使用任何测试数据
$user_id = get_user_user_id() ?? 0;
if ($user_id) {
    
    try {
        // 连接数据库并获取真实的未读通知数量
        require_once APP_ROOT . '/config.php';
        
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        
        // 查询未读通知数量 - 只使用真实数据库数据
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $unread_count = (int)$stmt->fetchColumn();
        
        // 更新响应为真实数据库中的未读数量
        $response['count'] = $unread_count;
    } catch (Exception $e) {
        // 数据库连接失败时，返回0，表示没有未读通知
        // 不使用任何测试数据或默认值
        $response['count'] = 0;
    }
}

// 返回JSON响应
echo json_encode($response);