<?php
// 开启错误报告以便调试
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 引入数据库连接
require __DIR__ . '/header.php';

// 检查用户是否登录
if (!isset($_SESSION['partner_id'])) {
    echo json_encode(['status' => 'error', 'message' => '未登录']);
    exit;
}

$partner_id = $_SESSION['partner_id'];
$response = ['status' => 'success'];

try {
    // 检查是否是标记单个通知为已读
    if (isset($_POST['notification_id'])) {
        $notification_id = (int)$_POST['notification_id'];
        
        // 更新数据库，标记为已读
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $partner_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('通知不存在或无权操作');
        }
    }
    // 检查是否是标记多个通知为已读
    else if (isset($_POST['notification_ids']) && isset($_POST['all']) && $_POST['all'] === 'true') {
        $notification_ids = json_decode($_POST['notification_ids'], true);
        
        if (!is_array($notification_ids) || empty($notification_ids)) {
            throw new Exception('无效的通知ID列表');
        }
        
        // 过滤并验证所有ID
        $valid_ids = [];
        foreach ($notification_ids as $id) {
            $valid_id = (int)$id;
            if ($valid_id > 0) {
                $valid_ids[] = $valid_id;
            }
        }
        
        if (empty($valid_ids)) {
            throw new Exception('没有有效的通知ID');
        }
        
        // 创建占位符字符串
        $placeholders = implode(',', array_fill(0, count($valid_ids), '?'));
        
        // 更新数据库，标记所有指定通知为已读
        $sql = "UPDATE notifications SET is_read = 1 WHERE id IN ($placeholders) AND user_id = ?";
        $stmt = $pdo->prepare($sql);
        
        // 绑定参数
        $params = array_merge($valid_ids, [$partner_id]);
        $stmt->execute($params);
    }
    // 或者标记所有未读通知为已读
    else if (isset($_POST['all']) && $_POST['all'] === 'true') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$partner_id]);
    }
    else {
        throw new Exception('无效的请求参数');
    }
    
    // 获取更新后的未读通知数量
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$partner_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['unread_count'] = (int)$result['count'];
    
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

// 返回JSON响应
header('Content-Type: application/json');
echo json_encode($response);