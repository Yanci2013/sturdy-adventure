<?php
require '../includes/session_manager.php';
require '../config.php';

$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

$isLoggedIn = is_h5_logged_in();
$user_id = get_h5_user_id();

if(!$isLoggedIn || !$user_id){
    header("Location: login.php?redirect=" . urlencode($currentUrl));
    exit();
}

try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", 
                 DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $stmt = $db->prepare("SELECT * FROM h5user WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if(!$user){
        clear_h5_session();
        die("<script>alert('登录信息已过期，请重新登录'); location.href='login.php?redirect=" . urlencode($currentUrl) . "';</script>");
    }
} catch(PDOException $e) {
    die("数据库错误: ".htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>设置</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-bg: #fef9e7;
            --card-bg: rgba(255,255,255,0.8);
            --text-primary: #333333;
            --text-secondary: #666666;
            --text-tertiary: #999999;
            --accent-gold: #d4a84b;
        }

        body {
            font-family: 'Noto Sans SC', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: url('../assets/1.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: var(--text-primary);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            padding-bottom: 80px;
        }

        /* 顶部导航 */
        .page-header {
            padding: 16px 20px;
            background: rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .back-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--card-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: var(--text-primary);
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .page-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            flex: 1;
            text-align: center;
            margin-right: 52px;
        }

        /* 主内容 */
        .main-content {
            padding: 0 16px;
        }

        /* 设置卡片 */
        .settings-card {
            background: var(--card-bg);
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .settings-item {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            text-decoration: none;
            color: var(--text-primary);
            border-bottom: 1px solid #f5f5f5;
            transition: background 0.2s ease;
        }

        .settings-item:last-child {
            border-bottom: none;
        }

        .settings-item:active {
            background: #f9f9f9;
        }

        .settings-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 14px;
            font-size: 16px;
            color: var(--text-secondary);
        }

        .settings-text {
            flex: 1;
            font-size: 15px;
            font-weight: 500;
        }

        .settings-arrow {
            color: var(--text-tertiary);
            font-size: 14px;
        }

        .settings-value {
            font-size: 14px;
            color: var(--text-tertiary);
            margin-right: 8px;
        }

        /* 退出登录按钮 */
        .logout-btn {
            display: block;
            width: 100%;
            padding: 16px;
            background: var(--card-bg);
            border: none;
            border-radius: 16px;
            font-size: 15px;
            font-weight: 600;
            color: #e74c3c;
            text-align: center;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            margin-top: 20px;
        }

        .logout-btn:active {
            background: #f9f9f9;
        }

        /* 版本号 */
        .version {
            text-align: center;
            padding: 30px 20px;
            font-size: 12px;
            color: var(--text-tertiary);
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

    <!-- 顶部导航 -->
    <div class="page-header">
        <a href="my.php" class="back-btn">
            <i class="fas fa-chevron-left"></i>
        </a>
        <h1 class="page-title">设置</h1>
    </div>

    <!-- 主内容 -->
    <main class="main-content">
        <!-- 账号设置 -->
        <div class="settings-card">
            <a href="password.php" class="settings-item">
                <div class="settings-icon"><i class="fas fa-lock"></i></div>
                <span class="settings-text">修改密码</span>
                <i class="fas fa-chevron-right settings-arrow"></i>
            </a>
        </div>

        <!-- 退出登录 -->
        <a href="logout.php" class="logout-btn">退出登录</a>
    </main>

    <?php include 'footer.php'; ?>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
