<?php
/**
 * 聊天消息API
 * 处理消息的发送和获取
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

// 根据请求方法处理不同操作
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // 获取消息列表
    getMessages($pdo, $currentUserId, $currentUserType);
} elseif ($method === 'POST') {
    // 发送消息
    sendMessage($pdo, $currentUserId, $currentUserType);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '不支持的请求方法']);
}

/**
 * 获取消息列表
 */
function getMessages($pdo, $currentUserId, $currentUserType) {
    $session_id = isset($_GET['session_id']) ? trim($_GET['session_id']) : '';
    $last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
    
    if (empty($session_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '缺少会话ID']);
        return;
    }
    
    try {
        // 验证用户是否在会话中
        $stmt = $pdo->prepare("
            SELECT 1 FROM chat_participants 
            WHERE session_id = ? AND user_id = ? AND user_type = ?
            LIMIT 1
        ");
        $stmt->execute([$session_id, $currentUserId, $currentUserType]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => '您无权访问此会话']);
            return;
        }
        
        // 构建查询条件
        $whereClause = "cm.session_id = ?";
        $params = [$session_id];
        
        if ($last_id > 0) {
            $whereClause .= " AND cm.message_id > ?";
            $params[] = $last_id;
        }
        
        // 获取消息列表
        $stmt = $pdo->prepare("
            SELECT 
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
                    WHEN cm.sender_type = 'admin' THEN '/uploads/avatars/default_avatar.png'
                    WHEN cm.sender_type = 'partner' THEN IFNULL(CONCAT('/uploads/avatars/', p.avatar), '/uploads/avatars/default_avatar.png')
                    WHEN cm.sender_type = 'user' THEN IFNULL(CONCAT('/uploads/avatars/', u.avatar), '/uploads/avatars/default_avatar.png')
                    WHEN cm.sender_type = 'h5user' THEN IFNULL(CONCAT('/uploads/avatars/', h.avatar), '/uploads/avatars/default_avatar.png')
                    WHEN cm.sender_type = 'eadmins' THEN IFNULL(CONCAT('/uploads/avatars/', e.avatar), '/uploads/avatars/default_avatar.png')
                    ELSE '/uploads/avatars/default_avatar.png'
                END as sender_avatar,
                (cm.sender_id = ? AND cm.sender_type = ?) as is_self
            FROM chat_messages cm
            LEFT JOIN admins a ON cm.sender_type = 'admin' AND cm.sender_id = a.id
            LEFT JOIN partners p ON cm.sender_type = 'partner' AND cm.sender_id = p.id
            LEFT JOIN users u ON cm.sender_type = 'user' AND cm.sender_id = u.id
            LEFT JOIN h5user h ON cm.sender_type = 'h5user' AND cm.sender_id = h.id
            LEFT JOIN eadmins e ON cm.sender_type = 'eadmins' AND cm.sender_id = e.id
            WHERE $whereClause
            ORDER BY cm.created_at ASC
            LIMIT 50
        ");
        
        $params[] = $currentUserId;
        $params[] = $currentUserType;
        $stmt->execute($params);
        $messages = $stmt->fetchAll();
        
        // 标记消息为已读
        if (!empty($messages)) {
            $markReadStmt = $pdo->prepare("
                INSERT INTO chat_message_status (message_id, user_id, user_type, is_read, read_at)
                SELECT cm.message_id, ?, ?, 1, NOW()
                FROM chat_messages cm
                LEFT JOIN chat_message_status cms ON cm.message_id = cms.message_id 
                    AND cms.user_id = ? AND cms.user_type = ?
                WHERE cm.session_id = ?
                AND cm.sender_id != ? AND cm.sender_type != ?
                AND (cms.is_read IS NULL OR cms.is_read = 0)
                ON DUPLICATE KEY UPDATE is_read = 1, read_at = NOW()
            ");
            $markReadStmt->execute([
                $currentUserId, $currentUserType,
                $currentUserId, $currentUserType,
                $session_id,
                $currentUserId, $currentUserType
            ]);
        }
        
        echo json_encode([
            'success' => true,
            'data' => $messages
        ]);
        
    } catch (PDOException $e) {
        error_log('获取消息失败: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '获取消息失败']);
    }
}

/**
 * 发送消息
 */
function sendMessage($pdo, $currentUserId, $currentUserType) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $session_id = isset($input['session_id']) ? trim($input['session_id']) : '';
    $content = isset($input['content']) ? trim($input['content']) : '';
    
    if (empty($session_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '缺少会话ID']);
        return;
    }
    
    if (empty($content)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '消息内容不能为空']);
        return;
    }
    
    if (mb_strlen($content) > 2000) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '消息内容过长']);
        return;
    }
    
    try {
        // 验证用户是否在会话中
        $stmt = $pdo->prepare("
            SELECT 1 FROM chat_participants 
            WHERE session_id = ? AND user_id = ? AND user_type = ?
            LIMIT 1
        ");
        $stmt->execute([$session_id, $currentUserId, $currentUserType]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => '您无权在此会话中发送消息']);
            return;
        }
        
        $pdo->beginTransaction();
        
        // 插入消息
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (session_id, sender_id, sender_type, content_type, content, created_at)
            VALUES (?, ?, ?, 'text', ?, NOW())
        ");
        $stmt->execute([$session_id, $currentUserId, $currentUserType, $content]);
        $message_id = $pdo->lastInsertId();
        
        // 更新会话最后活动时间
        $stmt = $pdo->prepare("
            UPDATE chat_sessions SET last_activity_at = NOW() WHERE session_id = ?
        ");
        $stmt->execute([$session_id]);
        
        $pdo->commit();
        
        // 获取发送者信息
        $sender_name = '未知用户';
        $sender_avatar = '/uploads/avatars/default_avatar.png';
        
        switch ($currentUserType) {
            case 'admin':
                $stmt = $pdo->prepare("SELECT username FROM admins WHERE id = ?");
                break;
            case 'partner':
                $stmt = $pdo->prepare("SELECT name as username, avatar FROM partners WHERE id = ?");
                break;
            case 'user':
                $stmt = $pdo->prepare("SELECT username, avatar FROM users WHERE id = ?");
                break;
            case 'h5user':
                $stmt = $pdo->prepare("SELECT nickname as username, avatar FROM h5user WHERE id = ?");
                break;
            case 'eadmins':
                $stmt = $pdo->prepare("SELECT username, avatar FROM eadmins WHERE id = ?");
                break;
        }
        
        if (isset($stmt)) {
            $stmt->execute([$currentUserId]);
            $sender = $stmt->fetch();
            if ($sender) {
                $sender_name = $sender['username'] ?? '用户';
                if (!empty($sender['avatar'])) {
                    $sender_avatar = '/uploads/avatars/' . $sender['avatar'];
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'message_id' => $message_id,
            'sender_id' => $currentUserId,
            'sender_type' => $currentUserType,
            'sender_name' => $sender_name,
            'sender_avatar' => $sender_avatar,
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s'),
            'is_self' => true
        ]);
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('发送消息失败: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '发送消息失败']);
    }
}
?>
