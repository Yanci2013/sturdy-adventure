<?php
include 'header.php';

$user_id = get_user_user_id() ?? 0;
if (!$user_id) {
    header('Location: login.php');
    exit;
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$orderBy = 'is_top DESC, ';
switch ($sort) {
    case 'oldest':
        $orderBy .= 'created_at ASC';
        break;
    case 'views':
        $orderBy .= 'view_count DESC';
        break;
    default:
        $orderBy .= 'created_at DESC';
}

try {
    $where = 'WHERE status = 1';
    $params = [];
    
    if (!empty($keyword)) {
        $where .= " AND (title LIKE :keyword OR keywords LIKE :keyword)";
        $params[':keyword'] = "%$keyword%";
    }
    
    $countSql = "SELECT COUNT(*) FROM announcements $where";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    $totalPages = ceil($total / $perPage);
    
    $sql = "SELECT id, title, keywords, view_count, created_at, is_top 
            FROM announcements $where 
            ORDER BY $orderBy 
            LIMIT :offset, :limit";
    
    $params[':offset'] = ($page - 1) * $perPage;
    $params[':limit'] = $perPage;
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $paramType = (strpos($key, 'offset') !== false || strpos($key, 'limit') !== false) 
            ? PDO::PARAM_INT 
            : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $paramType);
    }
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "数据库查询失败: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>公告列表</title>
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

        .search-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 16px;
            margin: 12px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 12px;
        }

        .search-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #eaeaea;
            border-radius: 12px;
            font-size: 14px;
            color: #000000;
            background: #fafafa;
            transition: all 0.2s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #f5b400;
            box-shadow: 0 0 0 3px rgba(245, 180, 0, 0.1);
            background: #ffffff;
        }

        .search-input::placeholder {
            color: #cccccc;
        }

        .search-btn {
            padding: 12px 20px;
            background: #000000;
            border: none;
            border-radius: 12px;
            color: #ffffff;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .search-btn:hover {
            background: #333333;
            transform: translateY(-2px);
        }

        .sort-tabs {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 4px 0;
        }

        .sort-tabs::-webkit-scrollbar {
            display: none;
        }

        .sort-tab {
            padding: 8px 16px;
            background: #f5f5f5;
            border: none;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            color: #666666;
            cursor: pointer;
            white-space: nowrap;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .sort-tab:hover {
            background: #e8e8e8;
        }

        .sort-tab.active {
            background: #000000;
            color: #ffffff;
        }

        .content-section {
            margin: 12px 0;
        }

        .announcement-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
        }

        .announcement-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }

        .announcement-card.top {
            border: 2px solid #f5b400;
            background: #fffef5;
        }

        .announcement-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .announcement-title {
            font-size: 15px;
            font-weight: 600;
            color: #000000;
            flex: 1;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .top-badge {
            background: #f5b400;
            color: #ffffff;
            padding: 4px 10px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }

        .keywords-container {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 10px;
        }

        .keyword-tag {
            background: #f5f5f5;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 11px;
            color: #666666;
        }

        .announcement-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #999999;
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
            <a href="javascript:history.back()" class="back-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <h1 class="header-title">公告列表</h1>
            <div class="header-right"></div>
        </div>
    </header>

    <main class="app-container">
        <div class="search-card">
            <form class="search-form" method="get">
                <input type="text" name="keyword" class="search-input" placeholder="搜索公告标题或关键词" value="<?php echo htmlspecialchars($keyword); ?>">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i>
                </button>
            </form>
            <div class="sort-tabs">
                <a href="?keyword=<?php echo urlencode($keyword); ?>&sort=newest" class="sort-tab <?php echo $sort === 'newest' ? 'active' : ''; ?>">最新发布</a>
                <a href="?keyword=<?php echo urlencode($keyword); ?>&sort=oldest" class="sort-tab <?php echo $sort === 'oldest' ? 'active' : ''; ?>">最早发布</a>
                <a href="?keyword=<?php echo urlencode($keyword); ?>&sort=views" class="sort-tab <?php echo $sort === 'views' ? 'active' : ''; ?>">最多浏览</a>
            </div>
        </div>

        <div class="content-section">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($announcements)): ?>
                <?php foreach ($announcements as $announcement): ?>
                    <div class="announcement-card <?php echo $announcement['is_top'] == 1 ? 'top' : ''; ?>" onclick="location.href='announcement_detail.php?id=<?php echo $announcement['id']; ?>'">
                        <div class="announcement-header">
                            <div class="announcement-title">
                                <?php echo htmlspecialchars($announcement['title']); ?>
                                <?php if ($announcement['is_top'] == 1): ?>
                                    <span class="top-badge">置顶</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($announcement['keywords'])): ?>
                            <div class="keywords-container">
                                <?php foreach (explode(',', $announcement['keywords']) as $kw): ?>
                                    <?php if (trim($kw)): ?>
                                        <span class="keyword-tag"><?php echo htmlspecialchars(trim($kw)); ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="announcement-footer">
                            <span><i class="far fa-clock"></i> <?php echo date('Y-m-d', strtotime($announcement['created_at'])); ?></span>
                            <span><i class="far fa-eye"></i> <?php echo $announcement['view_count']; ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&keyword=<?php echo urlencode($keyword); ?>&sort=<?php echo $sort; ?>" class="page-btn">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-btn disabled">
                                <i class="fas fa-chevron-left"></i>
                            </span>
                        <?php endif; ?>

                        <?php 
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        
                        if ($start > 1) {
                            echo '<a href="?page=1&keyword=' . urlencode($keyword) . '&sort=' . $sort . '" class="page-btn">1</a>';
                            if ($start > 2) {
                                echo '<span class="page-btn disabled">...</span>';
                            }
                        }
                        
                        for ($i = $start; $i <= $end; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="page-btn active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&keyword=<?php echo urlencode($keyword); ?>&sort=<?php echo $sort; ?>" class="page-btn"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor;
                        
                        if ($end < $totalPages) {
                            if ($end < $totalPages - 1) {
                                echo '<span class="page-btn disabled">...</span>';
                            }
                            echo '<a href="?page=' . $totalPages . '&keyword=' . urlencode($keyword) . '&sort=' . $sort . '" class="page-btn">' . $totalPages . '</a>';
                        }
                        ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&keyword=<?php echo urlencode($keyword); ?>&sort=<?php echo $sort; ?>" class="page-btn">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-btn disabled">
                                <i class="fas fa-chevron-right"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📢</div>
                    <div class="empty-title">暂无公告</div>
                    <div class="empty-message">暂无相关公告</div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'footer.php'; ?>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
