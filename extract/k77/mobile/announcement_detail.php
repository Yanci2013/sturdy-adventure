<?php
include 'header.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

try {
    $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ? AND status = 1");
    $stmt->execute([$id]);
    $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$announcement) {
        throw new Exception("公告不存在或已被删除");
    }
    
    $pdo->prepare("UPDATE announcements SET view_count = view_count + 1 WHERE id = ?")->execute([$id]);
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>公告详情</title>
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
            line-height: 1.6;
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

        .detail-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .detail-title {
            font-size: 20px;
            font-weight: 700;
            color: #000000;
            line-height: 1.4;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .top-badge {
            background: #f5b400;
            color: #ffffff;
            padding: 4px 10px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }

        .detail-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f0f0f0;
            margin-bottom: 16px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #999999;
        }

        .meta-item i {
            font-size: 14px;
        }

        .keywords-container {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 12px;
        }

        .keyword-tag {
            background: #f5f5f5;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 11px;
            color: #666666;
        }

        .detail-content {
            font-size: 15px;
            color: #333333;
            line-height: 1.8;
        }

        .detail-content img {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 16px auto;
            border-radius: 12px;
        }

        .detail-content p {
            margin-bottom: 12px;
        }

        .detail-content h1,
        .detail-content h2,
        .detail-content h3 {
            margin: 20px 0 12px;
            color: #000000;
            font-weight: 600;
        }

        .detail-content ul,
        .detail-content ol {
            margin: 12px 0;
            padding-left: 24px;
        }

        .detail-content li {
            margin-bottom: 6px;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        .alert i {
            font-size: 18px;
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
            <a href="announcements.php" class="back-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <h1 class="header-title">公告详情</h1>
            <div class="header-right"></div>
        </div>
    </header>

    <main class="app-container">
        <div class="content-section">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($announcement)): ?>
                <div class="detail-card">
                    <h1 class="detail-title">
                        <?php echo htmlspecialchars($announcement['title']); ?>
                        <?php if ($announcement['is_top'] == 1): ?>
                            <span class="top-badge">置顶</span>
                        <?php endif; ?>
                    </h1>
                    
                    <div class="detail-meta">
                        <div class="meta-item">
                            <i class="far fa-clock"></i>
                            <span><?php echo date('Y-m-d H:i', strtotime($announcement['created_at'])); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="far fa-user"></i>
                            <span>管理员</span>
                        </div>
                        <div class="meta-item">
                            <i class="far fa-eye"></i>
                            <span><?php echo $announcement['view_count']; ?> 次浏览</span>
                        </div>
                    </div>
                    
                    <?php if (!empty($announcement['keywords'])): ?>
                        <div class="keywords-container">
                            <?php 
                            $keywords = explode(',', $announcement['keywords']);
                            foreach ($keywords as $kw): 
                                if (trim($kw)): ?>
                                    <span class="keyword-tag"><?php echo htmlspecialchars(trim($kw)); ?></span>
                                <?php endif; 
                            endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="detail-content">
                        <?php echo $announcement['content']; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'footer.php'; ?>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
