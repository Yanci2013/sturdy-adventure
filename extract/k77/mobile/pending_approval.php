<?php
require_once("../includes/session_manager.php");
require_once("../includes/common.php");

// 检查用户是否已登录
if (!is_user_logged_in()) {
    header("Location: login.php");
    exit();
}

$user_id = get_user_user_id();

// 获取用户信息并检查审核状态
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        clear_user_session();
        header("Location: login.php");
        exit();
    }
    
    // 如果用户不是待审核状态，重定向
    if ($user['approval_status'] == 1) {
        // 已审核通过，跳转到首页
        header("Location: index.php");
        exit();
    } elseif ($user['approval_status'] == 2) {
        // 审核拒绝，跳转到登录页
        clear_user_session();
        header("Location: login.php?error=" . urlencode('您的账户审核未通过，请联系管理员'));
        exit();
    }
} catch (PDOException $e) {
    error_log("获取用户信息错误: " . $e->getMessage());
    $error = '系统错误，请稍后重试';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>账户审核中</title>
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
            background: url('../assets/1.png') no-repeat center center fixed;
            background-size: cover;
            color: #1d1d1f;
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            -webkit-font-smoothing: antialiased;
            animation: fadeInUp 0.6s ease-out;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: -1;
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

        .container {
            width: 100%;
            max-width: 420px;
        }

        .card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
            padding: 40px 24px;
            text-align: center;
        }

        .icon-wrapper {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(255, 215, 0, 0.4);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 0 0 15px rgba(255, 215, 0, 0);
            }
        }

        .icon-wrapper i {
            font-size: 48px;
            color: white;
        }

        .title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #1d1d1f;
        }

        .subtitle {
            font-size: 15px;
            color: #666;
            line-height: 1.6;
            margin-bottom: 28px;
        }

        .info-box {
            background: rgba(255, 215, 0, 0.1);
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .info-box p {
            font-size: 13px;
            color: #666;
            margin: 0;
        }

        .btn {
            width: 100%;
            padding: 14px;
            border: 1px solid #eaeaea;
            background: white;
            color: #1d1d1f;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border-radius: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn:hover {
            background: #f9f9f9;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #f5f5f5;
            margin-top: 12px;
        }

        .btn-secondary:hover {
            background: #eaeaea;
        }

        @media (max-width: 480px) {
            body {
                padding: 12px;
            }

            .card {
                padding: 32px 20px;
            }

            .icon-wrapper {
                width: 80px;
                height: 80px;
            }

            .icon-wrapper i {
                font-size: 36px;
            }

            .title {
                font-size: 20px;
            }

            .subtitle {
                font-size: 14px;
            }
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

    <div class="container">
        <div class="card">
            <div class="icon-wrapper">
                <i class="fas fa-clock"></i>
            </div>
            <h1 class="title">您的账户正在审核中</h1>
            <p class="subtitle">请耐心等待管理员审核通过，审核通过后您将可以使用全部功能。</p>
            
            <div class="info-box">
                <p><i class="fas fa-info-circle"></i> 如有疑问，请联系管理员</p>
            </div>
            
            <a href="logout.php" class="btn btn-secondary">
                <i class="fas fa-sign-out-alt"></i> 退出登录
            </a>
        </div>
    </div>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
