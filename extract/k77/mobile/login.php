<?php
require_once("../includes/session_manager.php");
if (is_user_logged_in()) {
    header('Location: index.php');
    exit();
}
require_once("../includes/common.php");

$error = '';
$success = '';

// 处理获取登录地址
if (isset($_GET['action']) && $_GET['action'] === 'get_login_url' && isset($_GET['type'])) {
    $type = $_GET['type'];
    
    $appid = '3527';
    $appkey = '3af0521a7f9afbcb6c637c7533530bbe';
    $redirect_uri = urlencode('https://' . $_SERVER['HTTP_HOST'] . '/mobile/login.php');
    
    try {
        $url = "https://u.suyanw.cn/connect.php?act=login&appid={$appid}&appkey={$appkey}&type={$type}&redirect_uri={$redirect_uri}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }
        
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if ($data && $data['code'] === 0 && $data['url']) {
            header("Location: " . $data['url']);
            exit();
        } else {
            throw new Exception('获取登录地址失败: ' . ($data['msg'] ?? '未知错误'));
        }
    } catch (Exception $e) {
        $error = '登录服务暂时不可用，请稍后重试';
        header("Location: login.php?error=" . urlencode($error));
        exit();
    }
}

// 处理授权回调
if (isset($_GET['type']) && isset($_GET['code'])) {
    $type = $_GET['type'];
    $code = $_GET['code'];
    
    $appid = '3527';
    $appkey = '3af0521a7f9afbcb6c637c7533530bbe';
    
    $url = "https://u.suyanw.cn/connect.php?act=callback&appid={$appid}&appkey={$appkey}&type={$type}&code={$code}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        curl_close($ch);
        header("Location: login.php?error=" . urlencode('授权登录失败'));
        exit();
    }
    
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($data && $data['code'] === 0) {
        $social_uid = $data['social_uid'];
        $nickname = $data['nickname'];
        $faceimg = $data['faceimg'];
        
        // 下载微信头像到本地
        $avatar_filename = 'default_avatar.png';
        if (!empty($faceimg)) {
            $avatar_dir = $_SERVER['DOCUMENT_ROOT'] . '/assets/images/userds/';
            if (!is_dir($avatar_dir)) {
                mkdir($avatar_dir, 0755, true);
            }
            $ext = pathinfo(parse_url($faceimg, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $avatar_filename = 'avatar_wx_' . substr($social_uid, 0, 8) . '_' . time() . '.' . $ext;
            $avatar_filepath = $avatar_dir . $avatar_filename;
            
            $ch_avatar = curl_init($faceimg);
            curl_setopt_array($ch_avatar, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 10
            ]);
            $avatar_data = curl_exec($ch_avatar);
            curl_close($ch_avatar);
            
            if (!$avatar_data || !file_put_contents($avatar_filepath, $avatar_data)) {
                $avatar_filename = 'default_avatar.png';
            }
        }
        
        try {
            if (!isset($pdo)) {
                throw new PDOException("数据库连接未建立");
            }
            
            // 检查users表是否包含社交登录所需的字段
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'social_uid'");
                if ($stmt->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN social_uid VARCHAR(255) DEFAULT NULL");
                }
                
                $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'social_type'");
                if ($stmt->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN social_type VARCHAR(50) DEFAULT NULL");
                }
                
                $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'avatar'");
                if ($stmt->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL");
                }
                
                $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'nickname'");
                if ($stmt->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN nickname VARCHAR(255) DEFAULT NULL");
                }
            } catch (PDOException $e) {
                error_log("修改表结构失败: " . $e->getMessage());
            }
            
            // 检查用户是否已存在（先检查社交登录ID，再检查微信openid）
            $stmt = $pdo->prepare("SELECT * FROM users WHERE social_uid = ? AND social_type = ? LIMIT 1");
            $stmt->execute([$social_uid, $type]);
            $user = $stmt->fetch();
            
            // 如果没有找到，检查是否通过注册绑定了微信
            if (!$user) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE wechat_openid = ? LIMIT 1");
                $stmt->execute([$social_uid]);
                $user = $stmt->fetch();
                
                // 如果找到通过注册绑定的微信用户，更新社交登录信息
                if ($user) {
                    $stmt = $pdo->prepare("UPDATE users SET social_uid = ?, social_type = ?, nickname = ?, avatar = ? WHERE id = ?");
                    $stmt->execute([$social_uid, $type, $nickname, $avatar_filename, $user['id']]);
                }
            }
            
            if ($user) {
                // 用户已存在，更新昵称和头像后检查审核状态
                $stmt = $pdo->prepare("UPDATE users SET nickname = ?, avatar = ? WHERE id = ?");
                $stmt->execute([$nickname, $avatar_filename, $user['id']]);
                
                // 检查用户审核状态
                if ($user['approval_status'] == 0) {
                    // 待审核状态，跳转到待审核页面
                    set_user_session($user['id']);
                    header("Location: pending_approval.php");
                    exit();
                } elseif ($user['approval_status'] == 2) {
                    // 审核拒绝
                    header("Location: login.php?error=" . urlencode('您的账户审核未通过，请联系管理员'));
                    exit();
                }
                
                // 更新首次登录状态
                if ($user['is_first_login'] == 1) {
                    $stmt = $pdo->prepare("UPDATE users SET is_first_login = 0 WHERE id = ?");
                    $stmt->execute([$user['id']]);
                }
                
                set_user_session($user['id']);
                header("Location: index.php");
                exit();
            } else {
                // 用户不存在，创建新用户，用昵称作为用户名
                $password = password_hash(uniqid(), PASSWORD_DEFAULT);
                $username = $nickname;
                
                // 检查用户名是否已存在，如果存在则追加随机数
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->rowCount() > 0) {
                    $username = $nickname . '_' . mt_rand(1000, 9999);
                }

                try {
                    // 创建新用户，设置为待审核状态
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, nickname, avatar, social_uid, social_type, user_role, approval_status, is_first_login, submitted_for_approval_at, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, 0, 1, NOW(), NOW())");
                    $stmt->execute([$username, $password, $nickname, $avatar_filename, $social_uid, $type]);
                    $user_id = $pdo->lastInsertId();
                    
                    // 添加到审核记录表
                    $stmt = $pdo->prepare("INSERT INTO user_approval_records (user_id, username, nickname, avatar, status, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
                    $stmt->execute([$user_id, $username, $nickname, $avatar_filename]);
                } catch (PDOException $e) {
                    error_log("创建用户失败: " . $e->getMessage());
                    header("Location: login.php?error=" . urlencode('创建账户失败'));
                    exit();
                }

                set_user_session($user_id);
                header("Location: pending_approval.php");
                exit();
            }
            
        } catch (PDOException $e) {
            error_log("数据库错误: ".$e->getMessage());
            header("Location: login.php?error=" . urlencode('系统错误，请稍后重试'));
            exit();
        }
    } else {
        header("Location: login.php?error=" . urlencode('获取用户信息失败'));
        exit();
    }
}



// 处理账号密码登录
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    try {
        if (!isset($pdo)) {
            throw new PDOException("数据库连接未建立");
        }
        
        // 尝试通过用户名、手机号或ID登录
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR phone = ? OR id = ? LIMIT 1");
        $stmt->execute([$username, $username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // 检查用户审核状态
            if ($user['approval_status'] == 0) {
                // 待审核状态，跳转到待审核页面
                set_user_session($user['id']);
                header("Location: pending_approval.php");
                exit();
            } elseif ($user['approval_status'] == 2) {
                // 审核拒绝
                $error = '您的账户审核未通过，请联系管理员';
            } else {
                // 更新首次登录状态
                if ($user['is_first_login'] == 1) {
                    $stmt = $pdo->prepare("UPDATE users SET is_first_login = 0 WHERE id = ?");
                    $stmt->execute([$user['id']]);
                }
                
                set_user_session($user['id']);
                header("Location: index.php");
                exit();
            }
        } else {
            $error = '账号或密码错误';
        }
    } catch (PDOException $e) {
        error_log("登录错误: " . $e->getMessage());
        $error = '登录失败，请稍后重试';
    }
}

// 处理URL中的错误参数
if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>账户登录</title>
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

        .login-container {
            width: 100%;
            max-width: 420px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
        }

        .login-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            background: rgba(255, 255, 255, 0.7);
        }

        .login-header {
            padding: 40px 24px 24px;
            text-align: center;
        }

        .login-logo {
            width: 64px;
            height: 64px;
            background: #f5f5f5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 28px;
            color: #1d1d1f;
            transition: all 0.3s ease;
        }

        .login-logo:hover {
            background: #eeeeee;
            transform: scale(1.05);
        }

        .login-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #000000;
        }

        .login-subtitle {
            font-size: 14px;
            color: #8e8e93;
            font-weight: 400;
        }

        .login-form {
            padding: 0 24px 32px;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-input-container {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px 14px 42px;
            border: 1px solid #eaeaea;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.2s ease;
            background: #fafafa;
            color: #1d1d1f;
        }

        .form-input:focus {
            outline: none;
            border-color: #000000;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(245, 180, 0, 0.1);
        }

        .form-input::placeholder {
            color: #c7c7cc;
            font-weight: 400;
        }

        .form-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #8e8e93;
            font-size: 16px;
            transition: all 0.2s ease;
        }

        .form-input:focus + .form-icon {
            color: #000000;
        }

        .error-message,
        .success-message {
            padding: 14px 16px;
            margin-bottom: 20px;
            border-radius: 12px;
            font-size: 13px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            animation: fadeIn 0.3s ease;
        }

        .error-message {
            background: #fff5f5;
            border: 1px solid #ffe0e0;
            color: #ff3b30;
        }

        .success-message {
            background: #f0fff4;
            border: 1px solid #d4f8e0;
            color: #34c759;
        }

        .error-message i,
        .success-message i {
            font-size: 14px;
            margin-top: 2px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #8e8e93;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 16px;
            padding: 4px;
        }

        .toggle-password:hover {
            color: #1d1d1f;
        }

        .btn {
            width: 100%;
            padding: 14px;
            border: 1px solid #eaeaea;
            background: #ffffff;
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
        }

        .btn:hover {
            background: #f9f9f9;
            transform: translateY(-2px);
        }

        .btn-primary {
            background: #000000;
            color: #ffffff;
            border-color: #000000;
            margin-top: 8px;
        }

        .btn-primary:hover {
            background: #333333;
        }

        .btn.loading {
            color: transparent !important;
            position: relative;
        }

        .btn.loading::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 18px;
            height: 18px;
            margin: -9px 0 0 -9px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .login-links {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #f0f0f0;
            text-align: center;
        }

        .link-group {
            display: flex;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .login-link {
            color: #666666;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s ease;
            padding: 6px 12px;
            border-radius: 16px;
        }

        .login-link:hover {
            color: #000000;
            background: #f5f5f5;
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 24px 0;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, transparent, #e0e0e0, transparent);
        }

        .divider-text {
            padding: 0 16px;
            font-size: 13px;
            color: #999;
        }

        .social-login {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-bottom: 20px;
        }

        .social-btn {
            width: 100%;
            height: 48px;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border: 1px solid #eaeaea;
            background: #ffffff;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            font-size: 14px;
            color: #333;
        }

        .social-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* 登录选项卡 */
        .login-tabs {
            display: flex;
            margin-bottom: 24px;
            background: #f5f5f5;
            border-radius: 12px;
            padding: 4px;
        }

        .tab-btn {
            flex: 1;
            padding: 10px 16px;
            border: none;
            background: transparent;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            color: #8e8e93;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .tab-btn.active {
            background: #ffffff;
            color: #1d1d1f;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
        }

        /* 选项卡内容 */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* 记住密码复选框 */
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #666;
            cursor: pointer;
        }

        .checkbox-label input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #000000;
        }

        .social-btn i {
            font-size: 20px;
        }

        .wechat-btn i {
            color: #07C160;
        }

        .wechat-btn:hover {
            border-color: #07C160;
            background: #f0fff4;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: flex-end;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .bind-modal {
            background: white;
            border-radius: 20px 20px 0 0;
            width: 100%;
            max-width: 420px;
            max-height: 80vh;
            overflow: hidden;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(100%);
            }
            to {
                transform: translateY(0);
            }
        }

        .bind-modal-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #f0f0f0;
        }

        .bind-modal-title {
            font-size: 18px;
            font-weight: 600;
            color: #1d1d1f;
        }

        .bind-modal-close {
            position: absolute;
            right: 16px;
            top: 16px;
            width: 32px;
            height: 32px;
            border: none;
            background: #f5f5f5;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #666;
        }

        .bind-modal-body {
            padding: 20px;
            max-height: 400px;
            overflow-y: auto;
        }

        .eadmin-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border: 1px solid #eaeaea;
            border-radius: 12px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .eadmin-item:hover {
            border-color: #07C160;
            background: #f0fff4;
        }

        .eadmin-item.selected {
            border-color: #07C160;
            background: #f0fff4;
        }

        .eadmin-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 20px;
            color: #666;
        }

        .eadmin-info {
            flex: 1;
        }

        .eadmin-name {
            font-size: 15px;
            font-weight: 500;
            color: #1d1d1f;
        }

        .eadmin-username {
            font-size: 13px;
            color: #999;
        }

        .bind-modal-footer {
            padding: 16px 20px;
            border-top: 1px solid #f0f0f0;
        }

        .bind-btn {
            width: 100%;
            padding: 14px;
            background: #07C160;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .bind-btn:hover {
            background: #06a050;
        }

        .bind-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        @media (max-width: 480px) {
            body {
                padding: 12px;
            }

            .login-header {
                padding: 32px 20px 20px;
            }

            .login-logo {
                width: 56px;
                height: 56px;
                font-size: 24px;
                margin-bottom: 16px;
            }

            .login-title {
                font-size: 22px;
            }

            .login-subtitle {
                font-size: 13px;
            }

            .login-form {
                padding: 0 20px 28px;
            }

            .form-input {
                padding: 12px 14px 12px 40px;
                font-size: 14px;
            }

            .form-icon,
            .toggle-password {
                font-size: 15px;
            }

            .btn {
                padding: 12px;
                font-size: 14px;
            }

            .login-links {
                padding-top: 20px;
            }

            .link-group {
                gap: 12px;
            }

            .login-link {
                font-size: 13px;
            }
        }

        @media (max-width: 360px) {
            .login-header {
                padding: 28px 16px 16px;
            }

            .login-logo {
                width: 48px;
                height: 48px;
                font-size: 20px;
            }

            .login-title {
                font-size: 20px;
            }

            .login-form {
                padding: 0 16px 24px;
            }

            .form-group {
                margin-bottom: 16px;
            }
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

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-user-circle"></i>
                </div>
                <h1 class="login-title">账户登录</h1>
                <p class="login-subtitle">请输入您的账号信息登录系统</p>
            </div>
            
            <div class="login-form">
                <!-- 登录选项卡 -->
                <div class="login-tabs">
                    <button class="tab-btn active" onclick="switchTab('account')">账号登录</button>
                    <button class="tab-btn" onclick="switchTab('wechat')">微信快捷登录</button>
                </div>

                <!-- 账号登录表单 -->
                <form id="loginForm" method="post" action="login.php" class="tab-content active">
                    <div class="form-group">
                        <div class="form-input-container">
                            <input type="text" class="form-input" name="username" placeholder="ID/手机号/用户名" required>
                            <i class="fas fa-user form-icon"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-input-container">
                            <input type="password" class="form-input" name="password" placeholder="请输入密码" required>
                            <i class="fas fa-lock form-icon"></i>
                            <i class="fas fa-eye toggle-password" onclick="togglePassword()"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember" value="1">
                            <span>记住密码</span>
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary" id="loginBtn">登录</button>
                </form>

                <!-- 微信登录 -->
                <div class="tab-content" id="wechatTab">
                    <div class="social-login">
                        <a href="javascript:void(0);" class="social-btn wechat-btn" onclick="wechatLogin()">
                            <i class="fab fa-weixin"></i>
                            <span>微信授权一键登录</span>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="login-links">
                <div class="link-group">
                    <a href="register.php" class="login-link">成为打手</a>
                    <a href="gsreg.php" class="login-link">成为管事</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="bindModal">
        <div class="bind-modal">
            <div class="bind-modal-header">
                <h2 class="bind-modal-title">请选择您的上级管事</h2>
            </div>
            <div class="bind-modal-body">
                <div id="eadminList"></div>
            </div>
            <div class="bind-modal-footer">
                <button type="button" class="bind-btn" id="confirmBindBtn" onclick="confirmBindEadmin()" disabled>
                    确认绑定
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 页面加载完成后的初始化
        });

        function wechatLogin() {
            window.location.href = 'login.php?action=get_login_url&type=wx';
        }

        // 切换登录选项卡
        function switchTab(tab) {
            const tabBtns = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabBtns.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            document.querySelector(`.tab-btn[onclick="switchTab('${tab}')"]`).classList.add('active');
            
            if (tab === 'account') {
                document.getElementById('loginForm').classList.add('active');
            } else {
                document.getElementById('wechatTab').classList.add('active');
            }
        }

        // 切换密码显示/隐藏
        function togglePassword() {
            const passwordInput = document.querySelector('input[name="password"]');
            const toggleIcon = document.querySelector('.toggle-password');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        let selectedEadminId = null;
        let eadminData = {};

        function loadEadminList() {
            const modal = document.getElementById('bindModal');
            const listContainer = document.getElementById('eadminList');
            
            modal.classList.add('active');
            listContainer.innerHTML = '<div style="text-align: center; padding: 20px; color: #999;">加载中...</div>';
            
            fetch('api/get_eadmins_with_invitation.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data && data.data.length > 0) {
                        listContainer.innerHTML = '';
                        data.data.forEach(eadmin => {
                            eadminData[eadmin.id] = eadmin;
                            const item = document.createElement('div');
                            item.className = 'eadmin-item';
                            item.dataset.id = eadmin.id;
                            item.innerHTML = `
                                <div class="eadmin-avatar">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                                <div class="eadmin-info">
                                    <div class="eadmin-name">${eadmin.real_name || eadmin.username}</div>
                                    <div class="eadmin-username">${eadmin.username}</div>
                                </div>
                            `;
                            item.addEventListener('click', () => selectEadmin(eadmin.id));
                            listContainer.appendChild(item);
                        });
                    } else {
                        listContainer.innerHTML = '<div style="text-align: center; padding: 20px; color: #999;">暂无可用管事</div>';
                    }
                })
                .catch(error => {
                    listContainer.innerHTML = '<div style="text-align: center; padding: 20px; color: #999;">加载失败，请刷新重试</div>';
                });
        }

        function selectEadmin(id) {
            document.querySelectorAll('.eadmin-item').forEach(item => {
                item.classList.remove('selected');
            });
            document.querySelector(`.eadmin-item[data-id="${id}"]`).classList.add('selected');
            selectedEadminId = id;
            document.getElementById('confirmBindBtn').disabled = false;
        }

        function confirmBindEadmin() {
            if (!selectedEadminId) return;
            
            const confirmBtn = document.getElementById('confirmBindBtn');
            confirmBtn.disabled = true;
            confirmBtn.textContent = '绑定中...';
            
            fetch(`api/get_or_create_invitation.php?eadmin_id=${selectedEadminId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.code) {
                        fetch('api/bind_eadmin.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                invitation_code: data.code
                            })
                        })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                alert('绑定成功！');
                                closeBindModal();
                                window.location.href = 'index.php';
                            } else {
                                alert(result.message || '绑定失败，请重试');
                                confirmBtn.disabled = false;
                                confirmBtn.textContent = '确认绑定';
                            }
                        });
                    } else {
                        alert('获取邀请码失败，请重试');
                        confirmBtn.disabled = false;
                        confirmBtn.textContent = '确认绑定';
                    }
                })
                .catch(error => {
                    alert('绑定失败，请重试');
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = '确认绑定';
                });
        }

        function closeBindModal() {
            document.getElementById('bindModal').classList.remove('active');
        }

        document.getElementById('bindModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeBindModal();
            }
        });
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
