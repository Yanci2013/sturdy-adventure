<?php
// 确保在输出前设置响应头
header('Content-Type: application/json; charset=utf-8');

// 禁用所有可能输出缓冲
if (ob_get_level()) ob_end_clean();

// 错误处理函数
function jsonResponse($status, $message, $code = 200, $extra = []) {
    http_response_code($code);
    $response = array_merge([
        'status' => $status,
        'message' => $message,
        'timestamp' => time()
    ], $extra);
    die(json_encode($response));
}

try {
    // 确保没有前导空白
    if (headers_sent()) {
        throw new Exception('Headers already sent');
    }

    require __DIR__ . '/header.php';

    // 验证商家登录
    if (!isset($_SESSION['partner_id'])) {
        jsonResponse('error', 'Unauthorized: Partner login required', 401);
    }

    // 只接受POST请求
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Method Not Allowed', 405);
    }

    // 获取输入数据（兼容表单和JSON）
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?: $_POST;

    // 验证必需参数
    $required = ['session_id', 'message'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            jsonResponse('error', "Missing required field: $field", 400);
        }
    }

    // 自动填充商家发送者信息
    $data['sender_id'] = $_SESSION['partner_id'];
    $data['sender_type'] = 'partner';

    // CSRF验证
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    if (empty($data['csrf_token']) || $data['csrf_token'] !== $_SESSION['csrf_token']) {
        jsonResponse('error', 'Invalid CSRF token', 403);
    }

    // 数据库操作
    $pdo->beginTransaction();

    // 验证会话权限（商家必须是会话参与者）
    $stmt = $pdo->prepare("
        SELECT 1 
        FROM chat_participants 
        WHERE session_id=? AND user_id=? AND user_type=?
        LIMIT 1
    ");
    $stmt->execute([$data['session_id'], $data['sender_id'], $data['sender_type']]);
    if (!$stmt->fetch()) {
        jsonResponse('error', 'No permission in this session', 403);
    }

    // 插入消息
    $stmt = $pdo->prepare("
        INSERT INTO chat_messages 
        SET session_id=?, sender_id=?, sender_type=?, content=?, created_at=NOW()
    ");
    $stmt->execute([
        $data['session_id'], 
        $data['sender_id'], 
        $data['sender_type'], 
        trim($data['message'])
    ]);
    
    $message_id = $pdo->lastInsertId();

    // 更新会话时间
    $stmt = $pdo->prepare("
        UPDATE chat_sessions 
        SET updated_at=NOW() 
        WHERE session_id=?
    ");
    $stmt->execute([$data['session_id']]);

    // 获取接收者信息（用于推送通知）
    $stmt = $pdo->prepare("
        SELECT user_id, user_type 
        FROM chat_participants 
        WHERE session_id=? 
        AND (user_id!=? OR user_type!=?)
    ");
    $stmt->execute([
        $data['session_id'], 
        $data['sender_id'], 
        $data['sender_type']
    ]);
    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdo->commit();

    // 这里可以添加推送通知逻辑
    // 例如：sendPushNotification($recipients, $data['message']);

    jsonResponse('success', 'Message sent', 200, [
        'message_id' => $message_id,
        'new_token' => $_SESSION['csrf_token'],
        'recipients' => $recipients
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse('error', 'Server Error: '.$e->getMessage(), 500, [
        'debug' => $e->getTraceAsString()
    ]);
}