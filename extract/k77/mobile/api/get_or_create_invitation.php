<?php
session_start();

include("../../includes/common.php");

header('Content-Type: application/json');

// 获取参数
$eadminId = isset($_GET['eadmin_id']) ? intval($_GET['eadmin_id']) : 0;

if ($eadminId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => '参数错误'
    ]);
    exit;
}

try {
    // 首先查询该管事是否已有可用的邀请码
    $stmt = $pdo->prepare("
        SELECT code 
        FROM invitations 
        WHERE generator_id = ? 
        AND generator_type = 'eadmin'
        AND register_type = 1
        AND status = 'active'
        AND (user_id IS NULL OR user_id = 0)
        LIMIT 1
    ");
    $stmt->execute([$eadminId]);
    $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($invitation) {
        // 已有可用邀请码，直接返回
        echo json_encode([
            'success' => true,
            'code' => $invitation['code'],
            'message' => '获取邀请码成功'
        ]);
        exit;
    }
    
    // 没有可用邀请码，生成一个新的
    // 生成16位邀请码
    $code = generateInvitationCode();
    
    // 插入新的邀请码
    $stmt = $pdo->prepare("
        INSERT INTO invitations 
        (code, generator_type, generator_id, register_type, status, created_at)
        VALUES (?, 'eadmin', ?, 1, 'active', NOW())
    ");
    $stmt->execute([$code, $eadminId]);
    
    echo json_encode([
        'success' => true,
        'code' => $code,
        'message' => '生成邀请码成功'
    ]);
    
} catch (PDOException $e) {
    error_log("Get or create invitation error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => '获取邀请码失败: ' . $e->getMessage()
    ]);
}

// 生成16位邀请码的函数
function generateInvitationCode() {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < 16; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}
