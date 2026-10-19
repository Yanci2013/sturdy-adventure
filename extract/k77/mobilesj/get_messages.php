<?php
require __DIR__ . '/header.php';

header('Content-Type: application/json');

// 验证用户登录
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    echo json_encode(['status' => 'error', 'message' => '未登录']);
    exit;
}

// 获取参数
$session_id = $_GET['session_id'] ?? '';
$last_message_id = $_GET['last_message_id'] ?? 0;

if (empty($session_id)) {
    echo json_encode(['status' => 'error', 'message' => '缺少会话ID']);
    exit;
}

// 获取新消息
$stmt = $pdo->prepare("
    SELECT 
        cm.message_id,
        cm.sender_id,
        cm.sender_type,
        cm.content_type,
        cm.content,
        cm.created_at,
        CASE 
            WHEN cm.sender_type = 'admin' THEN a.username
            WHEN cm.sender_type = 'partner' THEN p.name
            WHEN cm.sender_type = 'user' THEN u.username
            WHEN cm.sender_type = 'h5user' THEN h.nickname
            WHEN cm.sender_type = 'eadmins' THEN e.username
            ELSE '未知用户'
        END as sender_name,
        CASE 
            WHEN cm.sender_type = 'admin' THEN a.avatar
            WHEN cm.sender_type = 'partner' THEN p.avatar
            WHEN cm.sender_type = 'user' THEN u.avatar
            WHEN cm.sender_type = 'h5user' THEN h.avatar
            WHEN cm.sender_type = 'eadmins' THEN e.avatar
            ELSE 'default_avatar.png'
        END as sender_avatar
    FROM chat_messages cm
    LEFT JOIN admins a ON cm.sender_type = 'admin' AND cm.sender_id = a.id
    LEFT JOIN partners p ON cm.sender_type = 'partner' AND cm.sender_id = p.id
    LEFT JOIN users u ON cm.sender_type = 'user' AND cm.sender_id = u.id
    LEFT JOIN h5user h ON cm.sender_type = 'h5user' AND cm.sender_id = h.id
    LEFT JOIN eadmins e ON cm.sender_type = 'eadmins' AND cm.sender_id = e.id
    WHERE cm.session_id = ?
    AND cm.message_id > ?
    ORDER BY cm.created_at ASC
");
$stmt->execute([$session_id, $last_message_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['status' => 'success', 'messages' => $messages]);