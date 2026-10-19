<?php
require __DIR__ . '/header.php';

$user_id = get_user_user_id() ?? 0;
if (!$user_id) {
    header('Location: login.php');
    exit;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page_size = 20;
$offset = ($page - 1) * $page_size;
$total_notifications = 0;
$unread_count = 0;
$notifications = [];
$type = isset($_GET['type']) ? $_GET['type'] : 'all';

try {
    $unreadSql = "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0";
    $unreadParams = [$user_id];
    if ($type !== 'all') {
        $unreadSql .= " AND type = ?";
        $unreadParams[] = $type;
    }
    $stmt = $pdo->prepare($unreadSql);
    $stmt->execute($unreadParams);
    $unread_count = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_notifications = (int)$stmt->fetchColumn();
    
    $sql = "SELECT id, title, content, created_at AS time, is_read, type FROM notifications WHERE user_id = :user_id";
    if ($type !== 'all') {
        $sql .= " AND type = :type";
    }
    $sql .= " ORDER BY created_at DESC LIMIT :page_size OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    if ($type !== 'all') {
        $stmt->bindValue(':type', $type, PDO::PARAM_STR);
    }
    $stmt->bindValue(':page_size', $page_size, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $notification_ids = array_column($notifications, 'id');
    if (!empty($notification_ids)) {
        $placeholders = implode(',', array_fill(0, count($notification_ids), '?'));
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id IN ($placeholders)");
        $stmt->execute($notification_ids);
    }
} catch (Exception $e) {
    $error = '获取通知失败: ' . $e->getMessage();
}

$total_pages = ceil($total_notifications / $page_size);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>通知中心</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #1d1d1f;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .app-container {
            max-width: 100%;
            margin: 0 auto;
            padding: 0 12px;
            padding-bottom: 80px;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            padding: 12px 12px;
            background: #ffffff;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .back-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f5f5f5;
            border: none;
            color: #1d1d1f;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .back-btn:hover {
            background: #e8e8e8;
            transform: scale(1.1);
        }

        .header-title {
            flex: 1;
            text-align: center;
            font-size: 18px;
            font-weight: 600;
            color: #000000;
        }

        .header-right {
            width: 40px;
        }

        .content-section {
            margin: 12px 0;
        }

        .tabs-container {
            background: #ffffff;
            border-radius: 16px;
            padding: 8px;
            margin-bottom: 12px;
            display: flex;
            gap: 8px;
        }

        .tab-btn {
            flex: 1;
            padding: 10px 16px;
            background: transparent;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            color: #666666;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            text-align: center;
        }

        .tab-btn:hover {
            background: #f5f5f5;
        }

        .tab-btn.active {
            background: #000000;
            color: #ffffff;
        }

        .notification-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
        }

        .notification-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }

        .notification-card.unread {
            background: #fffef5;
            border: 2px solid #f5b400;
        }

        .notification-card.unread::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: #f5b400;
            border-radius: 16px 0 0 16px;
        }

        .notification-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .notification-title {
            font-size: 15px;
            font-weight: 600;
            color: #000000;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .notification-type {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .type-order {
            background: rgba(245, 180, 0, 0.1);
            color: #f5b400;
        }

        .type-system {
            background: rgba(0, 0, 0, 0.05);
            color: #000000;
        }

        .type-report {
            background: rgba(255, 68, 68, 0.1);
            color: #ff4444;
        }

        .type-settlement {
            background: rgba(82, 196, 26, 0.1);
            color: #52c41a;
        }

        .type-admin {
            background: rgba(156, 39, 176, 0.1);
            color: #9C27B0;
        }

        .notification-time {
            font-size: 12px;
            color: #999999;
        }

        .notification-content {
            font-size: 14px;
            color: #666666;
            line-height: 1.6;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #ffffff;
            border-radius: 16px;
            margin: 20px 0;
            animation: fadeInUp 0.6s ease-out;
        }

        .empty-icon {
            font-size: 64px;
            color: #cccccc;
            margin-bottom: 16px;
        }

        .empty-title {
            font-size: 18px;
            font-weight: 600;
            color: #000000;
            margin-bottom: 8px;
        }

        .empty-message {
            color: #999999;
            font-size: 14px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
        }

        .page-btn {
            width: 40px;
            height: 40px;
            border-radius: 20px;
            background: #ffffff;
            border: 1px solid #eaeaea;
            color: #666666;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .page-btn:hover {
            background: #f5f5f5;
            border-color: #cccccc;
        }

        .page-btn.active {
            background: #000000;
            color: #ffffff;
            border-color: #000000;
        }

        .page-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .alert {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 12px;
            color: #856404;
            font-size: 14px;
        }

        .alert-danger {
            background: #f8d7da;
            border-color: #ff4444;
            color: #721c24;
        }

        ::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: #dddddd;
            border-radius: 2px;
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

    <header class="header">
        <div class="header-content">
            <a href="javascript:history.back()" class="back-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <h1 class="header-title">通知中心</h1>
            <div class="header-right"></div>
        </div>
    </header>

    <main class="app-container">
        <div class="content-section">
            <div class="tabs-container">
                <a href="?type=all" class="tab-btn <?php echo $type === 'all' ? 'active' : ''; ?>">
                    全部
                    <?php if ($unread_count > 0): ?>
                        <span style="background:#f5b400;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:6px;"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?type=order" class="tab-btn <?php echo $type === 'order' ? 'active' : ''; ?>">订单</a>
                <a href="?type=system" class="tab-btn <?php echo $type === 'system' ? 'active' : ''; ?>">系统</a>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🔔</div>
                    <div class="empty-title">暂无通知</div>
                    <div class="empty-message">当有新的通知时会在这里显示</div>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-card <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                        <div class="notification-header">
                            <div class="notification-title">
                                <span class="notification-type type-<?php echo htmlspecialchars($notification['type']); ?>">
                                    <?php 
                                    $typeMap = [
                                        'order' => '订单',
                                        'system' => '系统',
                                        'report' => '举报',
                                        'settlement' => '结算',
                                        'admin' => '管理'
                                    ];
                                    echo isset($typeMap[$notification['type']]) ? $typeMap[$notification['type']] : '通知';
                                    ?>
                                </span>
                                <?php echo htmlspecialchars($notification['title']); ?>
                            </div>
                            <div class="notification-time">
                                <?php 
                                    $time = strtotime($notification['time']);
                                    $now = time();
                                    $diff = $now - $time;
                                    
                                    if ($diff < 60) {
                                        echo '刚刚';
                                    } elseif ($diff < 3600) {
                                        echo floor($diff / 60) . '分钟前';
                                    } elseif ($diff < 86400) {
                                        echo floor($diff / 3600) . '小时前';
                                    } elseif ($diff < 604800) {
                                        echo floor($diff / 86400) . '天前';
                                    } else {
                                        echo date('Y-m-d H:i', $time);
                                    }
                                ?>
                            </div>
                        </div>
                        <div class="notification-content">
                            <?php echo htmlspecialchars($notification['content']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&type=<?php echo $type; ?>" class="page-btn">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-btn disabled">
                                <i class="fas fa-chevron-left"></i>
                            </span>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="page-btn active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&type=<?php echo $type; ?>" class="page-btn"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&type=<?php echo $type; ?>" class="page-btn">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-btn disabled">
                                <i class="fas fa-chevron-right"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'footer.php'; ?>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
