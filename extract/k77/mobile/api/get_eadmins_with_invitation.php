<?php
session_start();

include("../../includes/common.php");

header('Content-Type: application/json');

try {
    // 从users表中查询user_role=2的管事
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.username,
            u.real_name,
            u.avatar
        FROM users u
        WHERE u.user_role = 2
        AND u.status = '正常'
        ORDER BY u.id ASC
    ");
    $stmt->execute();
    $eadmins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 返回管事列表（不再检查是否有邀请码，选择时会动态生成）
    $result = [];
    foreach ($eadmins as $eadmin) {
        $result[] = [
            'id' => $eadmin['id'],
            'username' => $eadmin['username'],
            'real_name' => $eadmin['real_name'],
            'avatar' => $eadmin['avatar'] ?? 'default_avatar.png',
            'invitation_code' => null // 选择时会动态获取或生成
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
} catch (PDOException $e) {
    error_log("Get eadmins error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => '获取管事列表失败: ' . $e->getMessage()
    ]);
}
