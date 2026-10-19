<?php
require_once '../includes/session_manager.php';
require_once '../config.php';

if (!is_h5_logged_in()) {
    header("Location: login.php");
    exit;
}

$user_id = get_h5_user_id();

try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

    // 获取系统通知
    $stmt = $db->query("SELECT * FROM system_notices WHERE status = 1 ORDER BY created_at DESC LIMIT 20");
    $notices = $stmt->fetchAll();

    // 获取用户信息
    $stmt = $db->prepare("SELECT nickname, username FROM h5user WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

} catch (PDOException $e) {
    $notices = [];
    $user = ['nickname' => '用户', 'username' => ''];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>消息</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/theme-v2.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
body {
    font-family: -apple-system, BlinkMacSystemFont, 'PingFang SC', sans-serif;
    background: #f0f5ff; min-height: 100vh; padding-bottom: 70px;
}
.page-header {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: #fff; padding: 16px; text-align: center;
    font-size: 17px; font-weight: 600;
    box-shadow: 0 2px 12px rgba(59,130,246,0.2);
    position: sticky; top: 0; z-index: 10;
}
.msg-list { padding: 12px; }
.msg-item {
    background: #fff; border-radius: 14px; padding: 14px;
    margin-bottom: 10px; display: flex; align-items: center; gap: 12px;
    box-shadow: 0 2px 10px rgba(59,130,246,0.05);
    border: 1px solid rgba(59,130,246,0.05);
    text-decoration: none; color: inherit;
    transition: transform 0.15s;
}
.msg-item:active { transform: scale(0.98); }
.msg-icon {
    width: 48px; height: 48px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; flex-shrink: 0;
}
.msg-icon.service {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #fff;
}
.msg-icon.notice {
    background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff;
}
.msg-icon.order {
    background: linear-gradient(135deg, #10b981, #059669); color: #fff;
}
.msg-content { flex: 1; min-width: 0; }
.msg-title { font-size: 15px; font-weight: 600; color: #1e293b; margin-bottom: 3px; }
.msg-desc { font-size: 12px; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.msg-arrow { color: #cbd5e1; font-size: 14px; flex-shrink: 0; }
.msg-badge {
    background: #ef4444; color: #fff; font-size: 10px;
    min-width: 16px; height: 16px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    padding: 0 5px; flex-shrink: 0;
}
.section-title {
    font-size: 13px; color: #64748b; font-weight: 600;
    padding: 8px 4px; margin-top: 8px;
}
.notice-item {
    background: #fff; border-radius: 12px; padding: 12px 14px;
    margin-bottom: 8px; box-shadow: 0 2px 8px rgba(59,130,246,0.04);
}
.notice-title { font-size: 14px; font-weight: 600; color: #1e293b; margin-bottom: 4px; }
.notice-time { font-size: 11px; color: #94a3b8; }
.notice-content { font-size: 13px; color: #475569; margin-top: 6px; line-height: 1.5; }
.empty-state {
    text-align: center; padding: 60px 20px; color: #94a3b8;
}
.empty-state i { font-size: 48px; margin-bottom: 12px; opacity: 0.5; }
</style>
</head>
<body>
<div class="v2-loader-overlay" id="v2Loader">
    <div class="v2-loader-logo-wrap">
        <div class="v2-loader-ring"></div><div class="v2-loader-ring"></div><div class="v2-loader-ring"></div>
        <div class="v2-loader-logo">小</div>
    </div>
    <div class="v2-loader-text"><span>加</span><span>载</span><span>中</span><span>...</span></div>
    <div class="v2-loader-progress"><div class="v2-loader-progress-bar"></div></div>
</div>

<div class="page-header">消息</div>

<div class="msg-list">
    <!-- 在线客服入口 -->
    <a href="customer_service.php" class="msg-item">
        <div class="msg-icon service"><i class="fas fa-headset"></i></div>
        <div class="msg-content">
            <div class="msg-title">在线客服</div>
            <div class="msg-desc">有问题随时联系我们，在线为您解答</div>
        </div>
        <i class="fas fa-chevron-right msg-arrow"></i>
    </a>

    <!-- 订单消息 -->
    <a href="order_list.php" class="msg-item">
        <div class="msg-icon order"><i class="fas fa-receipt"></i></div>
        <div class="msg-content">
            <div class="msg-title">订单消息</div>
            <div class="msg-desc">查看您的订单状态和进度</div>
        </div>
        <i class="fas fa-chevron-right msg-arrow"></i>
    </a>

    <div class="section-title">系统通知</div>

    <?php if (empty($notices)): ?>
    <div class="empty-state">
        <i class="fas fa-bell-slash"></i>
        <div>暂无系统通知</div>
    </div>
    <?php else: ?>
    <?php foreach ($notices as $notice): ?>
    <div class="notice-item">
        <div class="notice-title"><?php echo htmlspecialchars($notice['title'] ?? '系统通知'); ?></div>
        <div class="notice-time"><?php echo date('Y-m-d H:i', strtotime($notice['created_at'] ?? 'now')); ?></div>
        <div class="notice-content"><?php echo htmlspecialchars(mb_substr($notice['content'] ?? '', 0, 100)); ?></div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
