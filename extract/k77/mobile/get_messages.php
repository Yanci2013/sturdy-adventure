<?php
require __DIR__ . '/header.php';

header('Content-Type: application/json');

// 验证用户登录
$user_id = get_user_user_id() ?? 0;
if (!$user_id || !isset($_SESSION['user_type'])) {
    echo json_encode(['status' => 'error', 'message' => '未登录']);
    exit;
}

// 获取参数
$session_id = $_GET['session_id'] ?? '';
$last_message_id = isset($_GET['last_message_id']) && is_numeric($_GET['last_message_id']) ? intval($_GET['last_message_id']) : 0;

if (empty($session_id)) {
    echo json_encode(['status' => 'error', 'message' => '缺少会话ID']);
    exit;
}

// 验证用户是否有权访问此会话
$stmt = $pdo->prepare("SELECT 1 FROM chat_participants WHERE session_id=? AND user_id=? AND user_type=? LIMIT 1");
$stmt->execute([$session_id, $user_id, $_SESSION['user_type']]);
if (!$stmt->fetch()) {
    echo json_encode(['status' => 'error', 'message' => '没有权限访问此会话']);
    exit;
}

// 获取消息总数
$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM chat_messages WHERE session_id = ? AND message_id > ?");
$countStmt->execute([$session_id, $last_message_id]);
$total = $countStmt->fetch()['total'] ?? 0;

// 获取新消息
$stmt = $pdo->prepare("    SELECT 
        cm.message_id,
        cm.sender_id,
        cm.sender_type,
        cm.content_type,
        cm.content,
        cm.created_at,
        CASE 
            WHEN cm.sender_type = 'admin' THEN IFNULL(a.username, '管理员')
            WHEN cm.sender_type = 'partner' THEN IFNULL(p.name, '商家')
            WHEN cm.sender_type = 'user' THEN IFNULL(u.username, '用户')
            WHEN cm.sender_type = 'h5user' THEN IFNULL(h.nickname, '客户')
            WHEN cm.sender_type = 'eadmins' THEN IFNULL(e.username, '管事')
            ELSE '未知用户'
        END as sender_name,
        CASE 
            WHEN cm.sender_type = 'admin' THEN IFNULL(a.avatar, 'default_avatar.png')
            WHEN cm.sender_type = 'partner' THEN IFNULL(p.avatar, 'default_avatar.png')
            WHEN cm.sender_type = 'user' THEN IFNULL(u.avatar, 'default_avatar.png')
            WHEN cm.sender_type = 'h5user' THEN IFNULL(h.avatar, 'default_avatar.png')
            WHEN cm.sender_type = 'eadmins' THEN 'default_avatar.png'
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

// 更新未读消息状态 - 使用更安全的标记方式
$markReadStmt = $pdo->prepare("INSERT IGNORE INTO chat_message_status (session_id, message_id, user_id, user_type, status) 
    SELECT session_id, message_id, ?, ?, 'read' 
    FROM chat_messages 
    WHERE session_id = ? AND sender_id != ? AND sender_type != ?");
$markReadStmt->execute([
    $user_id, 
    $_SESSION['user_type'], 
    $session_id, 
    $user_id, 
    $_SESSION['user_type']
]);

echo json_encode(['status' => 'success', 'messages' => $messages, 'total' => $total]);