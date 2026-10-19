<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../../includes/session_manager.php';
require_once __DIR__.'/../../includes/common.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$invitation_code = $data['invitation_code'] ?? '';

if (empty($invitation_code)) {
    echo json_encode(['success' => false, 'message' => '邀请码不能为空']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM invitations WHERE code = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$invitation_code]);
    $invitation = $stmt->fetch();
    
    if (!$invitation) {
        echo json_encode(['success' => false, 'message' => '邀请码无效或已被使用']);
        exit();
    }
    
    if ($invitation['register_type'] != 1 && $invitation['register_type'] != 3) {
        echo json_encode(['success' => false, 'message' => '邀请码类型错误']);
        exit();
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => '用户不存在']);
        exit();
    }
    
    if (!empty($user['invitation_code'])) {
        echo json_encode(['success' => false, 'message' => '您已经绑定过邀请码']);
        exit();
    }
    
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("UPDATE users SET invitation_code = ? WHERE id = ?");
    $stmt->execute([$invitation_code, $user_id]);
    
    $stmt = $pdo->prepare("UPDATE invitations SET status = 'used', user_id = ?, used_at = NOW() WHERE code = ?");
    $stmt->execute([$user_id, $invitation_code]);
    
    $inviter_type = $invitation['generator_type'];
    
    $stmt = $pdo->prepare("
        INSERT INTO partner_service_relations 
        (inviter_id, inviter_type, invitee_id, invitee_type, invitation_code, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $invitation['generator_id'],
        $inviter_type,
        $user_id,
        'customer',
        $invitation_code
    ]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => '绑定成功']);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Bind eadmin error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '绑定失败，请重试']);
}
?>
