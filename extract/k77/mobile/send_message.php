<?php
// 确保在脚本的最开始执行
ob_start();

// 清理任何之前可能的输出
function clearOutput() {
    if (ob_get_length() > 0) {
        ob_clean();
    }
}

// 错误处理函数 - 确保JSON格式正确
function jsonResponse($status, $message, $code = 200, $extra = []) {
    // 清理任何可能的输出缓冲区
    clearOutput();
    
    // 设置响应头
    header_remove(); // 移除所有可能的之前设置的头
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    
    // 构建响应数组
    $response = array_merge([
        'status' => $status,
        'message' => $message,
        'timestamp' => time()
    ], $extra);
    
    // 确保响应是有效的JSON
    $json = json_encode($response, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        // 如果JSON编码失败，发送一个简单的错误响应
        echo json_encode([
            'status' => 'error',
            'message' => '服务器JSON编码错误',
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    echo $json;
    exit;
}

try {
    // 确保环境设置正确
    if (ini_get('output_buffering')) {
        ob_implicit_flush(false);
    }
    
    // 引入头部文件 - 但确保它不会输出任何内容
    ob_start(); // 捕获header.php可能的输出
    require __DIR__ . '/header.php';
    ob_end_clean(); // 丢弃任何捕获的输出
    
    // 检查是否有数据库连接
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('数据库连接不可用');
    }
    
    // 只接受POST请求
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', '方法不允许', 405);
    }
    
    // 获取输入数据 - 同时支持表单提交和JSON提交
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?: $_POST;
    
    // 验证必需参数
    $required = ['session_id', 'message'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            jsonResponse('error', "缺少必需参数: $field", 400);
        }
    }
    
    // 自动填充发送者信息
    $data['sender_id'] = $_SESSION['user_id'];
    $data['sender_type'] = $_SESSION['user_type'];
    
    // 清理输入数据
    $session_id = trim($data['session_id']);
    $sender_id = $data['sender_id'];
    $sender_type = $data['sender_type'];
    $message = trim($data['message']);
    
    // CSRF验证
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    if (isset($data['csrf_token']) && !empty($data['csrf_token'])) {
        if ($data['csrf_token'] !== $_SESSION['csrf_token']) {
            jsonResponse('error', '无效的CSRF令牌', 403);
        }
    }
    
    // 数据库事务处理
    try {
        $pdo->beginTransaction();
        
        // 验证会话权限 - 使用参数化查询防止SQL注入
        $stmt = $pdo->prepare("SELECT 1 FROM chat_participants WHERE session_id=? AND user_id=? AND user_type=? LIMIT 1");
        $stmt->execute([$session_id, $sender_id, $sender_type]);
        if (!$stmt->fetch()) {
            $pdo->rollBack();
            jsonResponse('error', '没有权限访问此会话', 403);
        }
        
        // 插入消息 - 使用参数化查询
        $stmt = $pdo->prepare("INSERT INTO chat_messages (session_id, sender_id, sender_type, content_type, content, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$session_id, $sender_id, $sender_type, 'text', $message]);
        
        $message_id = $pdo->lastInsertId();
        
        // 更新会话时间
        $stmt = $pdo->prepare("UPDATE chat_sessions SET last_activity_at=NOW() WHERE session_id=?");
        $stmt->execute([$session_id]);
        
        $pdo->commit();
        
        // 成功响应
        jsonResponse('success', '消息发送成功', 200, [
            'message_id' => $message_id,
            'new_token' => $_SESSION['csrf_token']
        ]);
        
    } catch (PDOException $pdoError) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // 记录错误但返回用户友好消息
        error_log('数据库错误: ' . $pdoError->getMessage());
        jsonResponse('error', '数据库操作失败，请稍后重试', 500);
    }
    
} catch (Throwable $e) {
    // 处理其他所有异常
    error_log('发送消息错误: ' . $e->getMessage());
    jsonResponse('error', '服务器处理请求时出错', 500);
}