<?php
/**
 * 发送聊天消息 API
 */
require_once dirname(dirname(__DIR__)) . '/includes/session_manager.php';
require_once dirname(dirname(__DIR__)) . '/config.php';

header('Content-Type: application/json');

if (!is_h5_logged_in()) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

$session_id = $_POST['session_id'] ?? '';
$user_id = intval($_POST['user_id'] ?? get_h5_user_id());
$content = trim($_POST['content'] ?? '');
$is_admin = isset($_POST['is_admin']) && $_POST['is_admin'] == '1';

if (empty($session_id) || empty($content)) {
    echo json_encode(['success' => false, 'message' => '参数不完整']);
    exit;
}

try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

    $message_id = uniqid('msg_', true);
    $sender_type = $is_admin ? 'admin' : 'h5user';

    $stmt = $db->prepare("INSERT INTO chat_messages (message_id, session_id, sender_id, sender_type, content, message_type, created_at) VALUES (?, ?, ?, ?, ?, 'text', NOW())");
    $stmt->execute([$message_id, $session_id, $user_id, $sender_type, $content]);

    echo json_encode(['success' => true, 'message_id' => $message_id]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => '发送失败']);
}
