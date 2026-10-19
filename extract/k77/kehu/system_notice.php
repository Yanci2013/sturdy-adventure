<?php
require_once '../includes/session_manager.php';
require_once '../config.php';

if (!is_h5_logged_in()) {
    header("Location: login.php");
    exit;
}
$user_id = get_h5_user_id();
$notice_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$notice = [];
$notice_list = [];

try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

    if($notice_id > 0) {
        $stmt = $db->prepare("SELECT id, title, content, created_at FROM announcements WHERE id=?");
        $stmt->execute([$notice_id]);
        $notice = $stmt->fetch();

        if (!empty($notice)) {
            $checkRead = $db->prepare("SELECT id FROM user_announcements WHERE announcement_id=? AND user_id=?");
            $checkRead->execute([$notice_id, $user_id]);
            if (!$checkRead->fetch()) {
                $markRead = $db->prepare("INSERT INTO user_announcements (announcement_id, user_id, viewed_at) VALUES (?, ?, NOW())");
                $markRead->execute([$notice_id, $user_id]);
            }
        }
    } else {
        $stmt = $db->prepare("SELECT id, title, content, created_at FROM announcements ORDER BY created_at DESC");
        $stmt->execute();
        $notice_list = $stmt->fetchAll();
    }

} catch (Exception $e) {
    $notice = [];
    $notice_list = [];
}

function is_read($db, $announcement_id, $user_id) {
    try {
        $stmt = $db->prepare("SELECT id FROM user_announcements WHERE announcement_id=? AND user_id=?");
        $stmt->execute([$announcement_id, $user_id]);
        return $stmt->fetch() ? true : false;
    } catch (Exception $e) {
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title><?= $notice_id > 0 && !empty($notice) ? '公告详情' : '系统公告' ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
body{background:#f5f5f5;font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft Yahei",sans-serif;color:#333;min-height:100vh;padding-bottom:30px;}
a{text-decoration:none;color:inherit;}

/* ========== 列表页 ========== */
.navbar{
    background:#fff;
    padding:0 16px;
    height:48px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-bottom:1px solid #e8e8e8;
    position:sticky;
    top:0;
    z-index:10;
}
.navbar-title{
    font-size:17px;
    font-weight:600;
    color:#1a1a1a;
}
.notice-container{
    max-width:700px;
    margin:0 auto;
    padding:12px 16px;
}
.notice-item{
    background:#fff;
    border-radius:8px;
    padding:16px;
    margin-bottom:10px;
    display:flex;
    align-items:center;
    gap:12px;
    border:1px solid #eee;
    transition:background 0.15s;
    position:relative;
}
.notice-item:active{
    background:#fafafa;
}
.notice-info{
    flex:1;
    min-width:0;
}
.notice-info .info-title{
    font-size:15px;
    font-weight:500;
    color:#1a1a1a;
    line-height:1.4;
    margin-bottom:6px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}
.notice-info .info-meta{
    display:flex;
    align-items:center;
    gap:10px;
    font-size:12px;
    color:#999;
}
.notice-info .info-preview{
    font-size:12px;
    color:#b0b0b0;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    margin-top:4px;
}
.notice-arrow{
    color:#ccc;
    font-size:16px;
    flex-shrink:0;
}
.unread-dot{
    width:8px;
    height:8px;
    background:#e74c3c;
    border-radius:50%;
    flex-shrink:0;
}
.empty-state{
    text-align:center;
    padding:80px 20px;
    color:#b0b0b0;
    font-size:15px;
}

/* ========== 详情页 ========== */
.detail-container{
    max-width:700px;
    margin:0 auto;
    padding:12px 16px;
}
.back-bar{
    padding:10px 0;
}
.back-btn{
    display:inline-flex;
    align-items:center;
    gap:4px;
    color:#666;
    font-size:14px;
}
.back-btn:active{opacity:0.6;}
.detail-card{
    background:#fff;
    border-radius:8px;
    padding:24px 20px;
    border:1px solid #eee;
}
.detail-time{
    font-size:13px;
    color:#b0b0b0;
    margin-bottom:16px;
}
.detail-title{
    font-size:20px;
    font-weight:600;
    color:#1a1a1a;
    line-height:1.4;
    margin-bottom:16px;
}
.detail-divider{
    height:1px;
    background:#f0f0f0;
    margin:16px 0;
}
.detail-content{
    font-size:15px;
    color:#555;
    line-height:1.8;
    word-break:break-all;
}
.detail-footer{
    text-align:center;
    color:#c0c0c0;
    font-size:13px;
    margin-top:24px;
}
</style>
    <link rel="stylesheet" href="../assets/css/theme-v2.css">
</head>
<body>
<!-- V2 加载动画 -->
<div class="v2-loader-overlay" id="v2Loader">
    <div class="v2-loader-logo-wrap">
        <div class="v2-loader-ring"></div>
        <div class="v2-loader-ring"></div>
        <div class="v2-loader-logo">小</div>
    </div>
    <div class="v2-loader-text">
        <span>加</span><span>载</span><span>中</span><span>...</span>
    </div>
    <div class="v2-loader-progress">
        <div class="v2-loader-progress-bar"></div>
    </div>
</div>


<?php if($notice_id > 0): ?>
    <!-- ========== 详情页 ========== -->
    <div class="navbar">
        <span class="navbar-title">公告详情</span>
    </div>
    <div class="detail-container">
        <?php if(!empty($notice)): ?>
            <div class="back-bar">
                <a href="system_notice.php" class="back-btn">
                    &lt; 返回
                </a>
            </div>
            <div class="detail-card">
                <div class="detail-time"><?= date('Y-m-d H:i', strtotime($notice['created_at'])) ?></div>
                <div class="detail-title"><?= htmlspecialchars($notice['title']) ?></div>
                <div class="detail-divider"></div>
                <div class="detail-content"><?= nl2br(htmlspecialchars($notice['content'])) ?></div>
            </div>
            <div class="detail-footer">- END -</div>
        <?php else: ?>
            <div class="back-bar">
                <a href="system_notice.php" class="back-btn">
                    &lt; 返回
                </a>
            </div>
            <div class="empty-state">公告不存在或已删除</div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- ========== 列表页 ========== -->
    <div class="navbar">
        <span class="navbar-title">系统公告</span>
    </div>

    <div class="notice-container">
        <?php if(empty($notice_list)): ?>
            <div class="empty-state">暂无公告</div>
        <?php else: ?>
            <?php foreach($notice_list as $item): 
                $read = is_read($db, $item['id'], $user_id);
            ?>
                <a href="system_notice.php?id=<?= $item['id'] ?>" class="notice-item">
                    <?php if(!$read): ?>
                        <div class="unread-dot"></div>
                    <?php endif; ?>
                    <div class="notice-info">
                        <div class="info-title"><?= htmlspecialchars($item['title']) ?></div>
                        <div class="info-meta">
                            <span><?= date('Y-m-d H:i', strtotime($item['created_at'])) ?></span>
                        </div>
                        <div class="info-preview"><?= htmlspecialchars(mb_substr(strip_tags($item['content']), 0, 50)) ?></div>
                    </div>
                    <div class="notice-arrow">&gt;</div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php endif; ?>

    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>