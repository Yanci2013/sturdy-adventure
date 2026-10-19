<?php
/**
 * 聊天会话API
 * 处理会话的创建和获取
 */

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 引入配置文件和session管理
require __DIR__ . '/../../../includes/session_manager.php';
require __DIR__ . '/../../../config.php';

// 数据库连接
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    $pdo->exec("SET NAMES 'utf8mb4'");
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '数据库连接失败']);
    exit;
}

// 验证用户登录
if (!is_h5_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

$currentUserId = get_h5_user_id();
$currentUserType = 'h5user';

// 获取输入数据
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// 获取参数
$target_id = isset($input['target_id']) ? intval($input['target_id']) : 0;
$target_type = isset($input['target_type']) ? trim($input['target_type']) : '';
$order_id = isset($input['order_id']) ? intval($input['order_id']) : 0;

if ($target_id <= 0 || empty($target_type)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '缺少必要的参数']);
    exit;
}

try {
    // 检查是否已存在会话
    $stmt = $pdo->prepare("
        SELECT cs.session_id 
        FROM chat_sessions cs
        INNER JOIN chat_participants cp1 ON cs.session_id = cp1.session_id AND cp1.user_id = ? AND cp1.user_type = ?
        INNER JOIN chat_participants cp2 ON cs.session_id = cp2.session_id AND cp2.user_id = ? AND cp2.user_type = ?
        WHERE cs.session_type = 'private' OR cs.session_type IS NULL
        LIMIT 1
    ");
    $stmt->execute([$currentUserId, $currentUserType, $target_id, $target_type]);
    $existingSession = $stmt->fetch();

    if ($existingSession) {
        // 返回已存在的会话
        echo json_encode([
            'success' => true,
            'session_id' => $existingSession['session_id'],
            'is_new' => false
        ]);
        exit;
    }

    // 创建新会话
    $session_id = 'chat_' . uniqid() . '_' . time();
    
    $pdo->beginTransaction();
    
    // 插入会话记录
    $stmt = $pdo->prepare("
        INSERT INTO chat_sessions (session_id, creator_id, created_at, last_activity_at) 
        VALUES (?, ?, NOW(), NOW())
    ");
    $stmt->execute([$session_id, $currentUserId]);
    
    // 添加当前用户为参与者
    $stmt = $pdo->prepare("
        INSERT INTO chat_participants (session_id, user_id, user_type, joined_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$session_id, $currentUserId, $currentUserType]);
    
    // 添加目标用户为参与者
    $stmt->execute([$session_id, $target_id, $target_type]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'session_id' => $session_id,
        'is_new' => true
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('创建会话失败: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '创建会话失败']);
}
?>
