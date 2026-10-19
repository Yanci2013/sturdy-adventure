<?php
session_start();
include("../includes/common.php");

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$errors = [];
$invitationCode = '';
$wechat_openid = '';
$wechat_nickname = '';
$wechat_avatar = '';

// 处理微信授权回调
if (isset($_GET['type']) && isset($_GET['code'])) {
    $type = $_GET['type'];
    $code = $_GET['code'];
    
    $suyanw_appid = '3527';
    $suyanw_appkey = '3af0521a7f9afbcb6c637c7533530bbe';
    
    if (empty($suyanw_appid) || empty($suyanw_appkey)) {
        $_SESSION['error_msg'] = "聚合登录配置未完成，请联系管理员";
        header("Location: gsreg.php");
        exit();
    }
    
    try {
        $callback_url = "https://u.suyanw.cn/connect.php?act=callback&appid={$suyanw_appid}&appkey={$suyanw_appkey}&type={$type}&code={$code}";
        $response = file_get_contents($callback_url);
        $result = json_decode($response, true);
        
        if ($result['code'] !== 0) {
            $_SESSION['error_msg'] = "获取用户信息失败: " . ($result['msg'] ?? '未知错误');
            header("Location: gsreg.php");
            exit();
        }
        
        $wechat_openid = $result['social_uid'];
        $wechat_nickname = $result['nickname'] ?? '微信用户';
        $wechat_avatar = $result['faceimg'] ?? '';
        
        // 存储微信信息到会话
        $_SESSION['wechat_openid'] = $wechat_openid;
        $_SESSION['wechat_nickname'] = $wechat_nickname;
        $_SESSION['wechat_avatar'] = $wechat_avatar;
        
        $_SESSION['success_msg'] = "微信绑定成功";
        header("Location: gsreg.php");
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error_msg'] = "绑定失败: " . $e->getMessage();
        header("Location: gsreg.php");
        exit();
    }
}

// 从会话中获取微信信息
if (isset($_SESSION['wechat_openid'])) {
    $wechat_openid = $_SESSION['wechat_openid'];
    $wechat_nickname = $_SESSION['wechat_nickname'];
    $wechat_avatar = $_SESSION['wechat_avatar'];
}

// 检查聚合登录配置
$suyanw_appid = '3527';
$suyanw_appkey = '3af0521a7f9afbcb6c637c7533530bbe';

if (empty($suyanw_appid) || empty($suyanw_appkey)) {
    $wechat_auth_url = '#';
    $auth_enabled = false;
} else {
    $_SESSION['suyanw_state'] = bin2hex(random_bytes(16));
    $wechat_auth_url = "wechat_bind.php?from=gsreg";
    $auth_enabled = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $invitationCode = trim($_POST['invitation_code']);

    if (empty($username)) {
        $errors[] = "用户名不能为空";
    } elseif (strlen($username) > 50) {
        $errors[] = "用户名长度不能超过50个字符";
    }

    if (empty($phone)) {
        $errors[] = "手机号不能为空";
    } elseif (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
        $errors[] = "请输入有效的手机号";
    }

    if (empty($password)) {
        $errors[] = "密码不能为空";
    } elseif (strlen($password) < 8) {
        $errors[] = "密码长度至少为8个字符";
    } elseif ($password !== $confirm_password) {
        $errors[] = "两次输入的密码不一致";
    }

    if (empty($invitationCode)) {
        $errors[] = "邀请码为必填项，请输入邀请码";
    } else {
        if (strlen($invitationCode) !== 16) {
            $errors[] = "邀请码格式不正确（需16位英文数字组合）";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, status, user_id, register_type FROM invitations WHERE code = ? AND status = 'active'");
                $stmt->execute([$invitationCode]);
                $invitation = $stmt->fetch();
                
                if (!$invitation) {
                    $errors[] = "邀请码无效或已被使用";
                } else {
                    if ($invitation['user_id'] !== NULL) {
                        $errors[] = "邀请码已被使用";
                    }
                    if ($invitation['register_type'] != 3) {
                        $errors[] = "邀请码类型错误，请使用管事注册邀请码";
                    }
                }
            } catch (PDOException $e) {
                $errors[] = "数据库查询失败";
                error_log("Invitation code check error: " . $e->getMessage());
            }
        }
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = "用户名已被注册";
        }
    } catch (PDOException $e) {
        $errors[] = "数据库查询失败";
        error_log("Username check error: " . $e->getMessage());
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            $errors[] = "该手机号已被注册";
        }
    } catch (PDOException $e) {
        $errors[] = "数据库查询失败";
        error_log("Phone check error: " . $e->getMessage());
    }

    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $pdo->beginTransaction();
            
            // 检查users表是否包含微信相关字段
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'wechat_openid'");
                if ($stmt->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN wechat_openid VARCHAR(255) DEFAULT NULL");
                }
                
                $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'wechat_nickname'");
                if ($stmt->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN wechat_nickname VARCHAR(255) DEFAULT NULL");
                }
                
                $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'wechat_avatar'");
                if ($stmt->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN wechat_avatar VARCHAR(500) DEFAULT NULL");
                }
            } catch (PDOException $e) {
                error_log("修改表结构失败: " . $e->getMessage());
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO users (username, phone, password, invitation_code, user_role, approval_status, is_first_login, submitted_for_approval_at, created_at)
                VALUES (?, ?, ?, ?, 2, 0, 1, NOW(), NOW())
            ");
            $stmt->execute([$username, $phone, $hashedPassword, $invitationCode]);
            $user_id = $pdo->lastInsertId();
            
            // 添加到用户审核记录表
            $stmt = $pdo->prepare("
                INSERT INTO user_approval_records (user_id, username, phone, status, created_at)
                VALUES (?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$user_id, $username, $phone]);
            
            // 如果有微信信息，更新到用户表
            if (!empty($wechat_openid)) {
                $stmt = $pdo->prepare("
                    UPDATE users SET wechat_openid = ?, wechat_nickname = ?, wechat_avatar = ? WHERE id = ?
                ");
                $stmt->execute([$wechat_openid, $wechat_nickname, $wechat_avatar, $user_id]);
            }
            
            $stmt = $pdo->prepare("
                UPDATE invitations SET status = 'used', user_id = ? WHERE code = ?
            ");
            $stmt->execute([$user_id, $invitationCode]);
            
            $pdo->commit();
            
            // 清除微信会话信息
            unset($_SESSION['wechat_openid']);
            unset($_SESSION['wechat_nickname']);
            unset($_SESSION['wechat_avatar']);
            
            header('Location: login.php?registered=1');
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "注册失败，请重试";
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>管事注册</title>
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

        .register-container {
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
        }

        .register-card {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
        }

        .register-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            background: rgba(255, 255, 255, 0.7);
        }

        .register-header {
            padding: 40px 24px 24px;
            text-align: center;
        }

        .register-logo {
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

        .register-logo:hover {
            background: #eeeeee;
            transform: scale(1.05);
        }

        .register-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #000000;
        }

        .register-subtitle {
            font-size: 14px;
            color: #8e8e93;
            font-weight: 400;
        }

        .register-form {
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

        .error-message {
            background: #fff5f5;
            border: 1px solid #ffe0e0;
            color: #ff3b30;
            padding: 14px 16px;
            margin-bottom: 20px;
            border-radius: 12px;
            font-size: 13px;
            animation: fadeIn 0.3s ease;
        }

        .error-message p {
            margin: 5px 0;
            display: flex;
            align-items: center;
        }

        .error-message i {
            margin-right: 8px;
            font-size: 14px;
            flex-shrink: 0;
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

        .password-hint {
            font-size: 12px;
            color: #8e8e93;
            margin-top: 8px;
            display: flex;
            align-items: center;
        }

        .password-hint i {
            margin-right: 5px;
            color: #8e8e93;
            font-size: 11px;
        }

        .invitation-hint {
            font-size: 12px;
            color: #8e8e93;
            margin-top: 8px;
            display: flex;
            align-items: center;
        }

        .invitation-hint i {
            margin-right: 5px;
            color: #8e8e93;
            font-size: 11px;
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

        .register-footer {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #f0f0f0;
            text-align: center;
        }

        .register-footer a {
            color: #666666;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s ease;
            padding: 6px 12px;
            border-radius: 16px;
        }

        .register-footer a:hover {
            color: #000000;
            background: #f5f5f5;
        }

        @media (max-width: 480px) {
            body {
                padding: 12px;
            }

            .register-header {
                padding: 32px 20px 20px;
            }

            .register-logo {
                width: 56px;
                height: 56px;
                font-size: 24px;
                margin-bottom: 16px;
            }

            .register-title {
                font-size: 22px;
            }

            .register-subtitle {
                font-size: 13px;
            }

            .register-form {
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

            .register-footer {
                padding-top: 20px;
            }
        }

        @media (max-width: 360px) {
            .register-header {
                padding: 28px 16px 16px;
            }

            .register-logo {
                width: 48px;
                height: 48px;
                font-size: 20px;
            }

            .register-title {
                font-size: 20px;
            }

            .register-form {
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

    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <div class="register-logo">
                    <i class="fas fa-user-tie"></i>
                </div>
                <h1 class="register-title">管事注册</h1>
                <p class="register-subtitle">请填写以下信息完成注册</p>
            </div>
            
            <div class="register-form">
            <?php if (isset($_SESSION['success_msg'])): ?>
                <div class="error-message" style="background: #f0fff4; border-color: #c8e6c9; color: #2e7d32;">
                    <p><i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></p>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_msg'])): ?>
                <div class="error-message">
                    <p><i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <?php foreach ($errors as $error): ?>
                        <p><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="" id="register-form">
                <div class="form-group">
                    <div class="form-input-container">
                        <input type="text" id="username" name="username" class="form-input" placeholder="请输入用户名" required value="<?php echo htmlspecialchars($username ?? ''); ?>">
                        <i class="fas fa-user form-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="form-input-container">
                        <input type="tel" id="phone" name="phone" class="form-input" placeholder="请输入11位手机号" required value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                        <i class="fas fa-phone form-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="form-input-container">
                        <input type="password" id="password" name="password" class="form-input" placeholder="请输入密码（至少8个字符）" required>
                        <i class="fas fa-lock form-icon"></i>
                        <i class="fas fa-eye toggle-password" id="toggle-password-1"></i>
                    </div>
                    <div class="password-hint">
                        <i class="fas fa-info-circle"></i> 密码长度至少8个字符，建议使用字母、数字和符号组合
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="form-input-container">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="请再次输入密码" required>
                        <i class="fas fa-lock form-icon"></i>
                        <i class="fas fa-eye toggle-password" id="toggle-password-2"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="form-input-container">
                        <input type="text" id="invitation_code" name="invitation_code" class="form-input" 
                               placeholder="请输入16位管事邀请码" required 
                               value="<?php echo htmlspecialchars($invitationCode ?? ''); ?>">
                        <i class="fas fa-id-card form-icon"></i>
                    </div>
                    <div class="invitation-hint">
                        <i class="fas fa-key"></i> 管事邀请码为16位英文数字组合，必须填写
                    </div>
                </div>
                
                <div class="form-group">
                    <?php if (!empty($wechat_openid)): ?>
                        <div style="padding: 12px; background: #f0fff4; border: 1px solid #c8e6c9; border-radius: 12px; margin-bottom: 8px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class="fab fa-weixin" style="color: #07C160; font-size: 18px;"></i>
                                <span style="font-weight: 500; color: #2e7d32;">微信已绑定: <?php echo htmlspecialchars($wechat_nickname); ?></span>
                            </div>
                        </div>
                        <?php if ($auth_enabled): ?>
                            <a href="<?php echo htmlspecialchars($wechat_auth_url); ?>" class="btn" style="width: 100%; margin-bottom: 8px;">
                                <i class="fab fa-weixin" style="color: #07C160;"></i> 重新绑定微信
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($auth_enabled): ?>
                            <a href="<?php echo htmlspecialchars($wechat_auth_url); ?>" class="btn" style="width: 100%; margin-bottom: 8px;">
                                <i class="fab fa-weixin" style="color: #07C160;"></i> 绑定微信（授权后可一键登录）
                            </a>
                        <?php else: ?>
                            <button type="button" class="btn" disabled style="width: 100%; margin-bottom: 8px;">
                                <i class="fas fa-exclamation-triangle"></i> 暂未配置登录功能
                            </button>
                        <?php endif; ?>
                        <div class="invitation-hint" style="margin-top: 8px;">
                            <i class="fas fa-info-circle"></i> 绑定微信后，下次登录可直接使用微信一键登录
                        </div>
                    <?php endif; ?>
                </div>
                
                <input type="hidden" id="wechat_openid" name="wechat_openid" value="<?php echo htmlspecialchars($wechat_openid ?? ''); ?>">
                <input type="hidden" id="wechat_nickname" name="wechat_nickname" value="<?php echo htmlspecialchars($wechat_nickname ?? ''); ?>">
                <input type="hidden" id="wechat_avatar" name="wechat_avatar" value="<?php echo htmlspecialchars($wechat_avatar ?? ''); ?>">
                
                <button type="submit" class="btn btn-primary" id="register-btn">
                    <i class="fas fa-user-tie"></i> 注册成为管事
                </button>
            </form>
            </div>
            
            <div class="register-footer">
                已有账号？ <a href="login.php"><i class="fas fa-sign-in-alt"></i> 立即登录</a>
                <span style="color: #eaeaea; margin: 0 8px;">|</span>
                <a href="register.php"><i class="fas fa-fist-raised"></i> 成为打手</a>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.toggle-password').forEach((toggle, index) => {
            toggle.addEventListener('click', () => {
                const inputId = index === 0 ? 'password' : 'confirm_password';
                const passwordInput = document.getElementById(inputId);
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                toggle.classList.toggle('fa-eye');
                toggle.classList.toggle('fa-eye-slash');
            });
        });
        
        const registerForm = document.getElementById('register-form');
        const registerBtn = document.getElementById('register-btn');
        
        registerForm.addEventListener('submit', (e) => {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const invitationCode = document.getElementById('invitation_code').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('两次输入的密码不一致，请重新输入');
                return;
            }
            
            if (invitationCode.length !== 16) {
                e.preventDefault();
                alert('管事邀请码必须是16位字符，请检查输入');
                return;
            }
            
            registerBtn.classList.add('loading');
            registerBtn.innerHTML = '';
            registerBtn.disabled = true;
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            const usernameInput = document.getElementById('username');
            if (usernameInput) {
                setTimeout(() => {
                    usernameInput.focus();
                }, 300);
            }
            
            const inputs = document.querySelectorAll('.form-input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.style.borderColor = '#000000';
                    this.style.background = '#ffffff';
                });
                
                input.addEventListener('blur', function() {
                    if (!this.value) {
                        this.style.borderColor = '#eaeaea';
                        this.style.background = '#fafafa';
                    }
                });
                
                input.addEventListener('input', function() {
                    if (this.value) {
                        this.style.borderColor = '#000000';
                        this.style.background = '#ffffff';
                    } else {
                        this.style.borderColor = '#eaeaea';
                        this.style.background = '#fafafa';
                    }
                });
            });

            const invitationInput = document.getElementById('invitation_code');
        invitationInput.addEventListener('input', function() {
            const code = this.value;
            const formatHint = document.querySelector('.invitation-hint');
            
            if (code.length > 0 && code.length !== 16) {
                formatHint.innerHTML = '<i class="fas fa-exclamation-triangle" style="color: #ff6b6b;"></i> 管事邀请码必须是16位字符';
                formatHint.style.color = '#ff6b6b';
                this.style.borderColor = '#ff6b6b';
                this.style.boxShadow = '0 0 0 2px rgba(255, 107, 107, 0.2)';
            } else if (code.length === 16) {
                formatHint.innerHTML = '<i class="fas fa-check-circle" style="color: #68d391;"></i> 邀请码格式正确';
                formatHint.style.color = '#68d391';
                this.style.borderColor = '#68d391';
                this.style.boxShadow = '0 0 0 2px rgba(104, 211, 145, 0.2)';
            } else {
                formatHint.innerHTML = '<i class="fas fa-key"></i> 管事邀请码为16位英文数字组合，必须填写';
                formatHint.style.color = '#8e8e93';
                this.style.borderColor = '#eaeaea';
                this.style.boxShadow = 'none';
            }
        });
        
    });
</script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
