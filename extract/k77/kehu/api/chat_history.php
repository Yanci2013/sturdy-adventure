<?php
/**
 * 获取聊天历史 API
 */
require_once dirname(dirname(__DIR__)) . '/includes/session_manager.php';
require_once dirname(dirname(__DIR__)) . '/config.php';

header('Content-Type: application/json');

if (!is_h5_logged_in()) {
    echo json_encode(['success' => false, 'messages' => []]);
    exit;
}

$session_id = $_GET['session_id'] ?? '';
$last_id = $_GET['last_id'] ?? 0;

if (empty($session_id)) {
    echo json_encode(['success' => false, 'messages' => []]);
    exit;
}

try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

    $stmt = $db->prepare("SELECT * FROM chat_messages WHERE session_id = ? ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([$session_id]);
    $messages = array_reverse($stmt->fetchAll());

    echo json_encode(['success' => true, 'messages' => $messages]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'messages' => []]);
}
