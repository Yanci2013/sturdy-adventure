<?php
// 开启错误报告以便调试
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 开启会话
session_start();

// 数据库连接配置
include("../includes/common.php");

// 如果用户已经登录，跳转到仪表盘
if (isset($_SESSION['partner_id'])) {
    header('Location: index.php');
    exit();
}

// 处理微信授权回调
if (isset($_GET['type']) && isset($_GET['code'])) {
    $type = $_GET['type'];
    $code = $_GET['code'];
    
    $suyanw_appid = '3527';
    $suyanw_appkey = '3af0521a7f9afbcb6c637c7533530bbe';
    
    if (empty($suyanw_appid) || empty($suyanw_appkey)) {
        $_SESSION['error_msg'] = "聚合登录配置未完成，请联系管理员";
        header("Location: register.php");
        exit();
    }
    
    try {
        $callback_url = "https://u.suyanw.cn/connect.php?act=callback&appid={$suyanw_appid}&appkey={$suyanw_appkey}&type={$type}&code={$code}";
        $response = file_get_contents($callback_url);
        $result = json_decode($response, true);
        
        if ($result['code'] !== 0) {
            $_SESSION['error_msg'] = "获取用户信息失败: " . ($result['msg'] ?? '未知错误');
            header("Location: register.php");
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
        header("Location: register.php");
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error_msg'] = "绑定失败: " . $e->getMessage();
        header("Location: register.php");
        exit();
    }
}

// 从会话中获取微信信息
$wechat_openid = '';
$wechat_nickname = '';
$wechat_avatar = '';

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
    $wechat_auth_url = "wechat_bind.php?from=register";
    $auth_enabled = true;
}

// 注册处理
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 接收表单数据
    $phone = trim($_POST['phone']);
    $invitation_code = trim($_POST['invitation_code']);
    $name = trim($_POST['name']);
    $password = trim($_POST['password']);
    
    // 验证输入
    if (empty($phone) || empty($invitation_code) || empty($name) || empty($password)) {
        $error = "所有字段都必须填写";
    } elseif (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
        $error = "请输入有效的手机号码";
    } elseif (strlen($password) < 8) {
        $error = "密码长度至少需要8个字符";
    } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/', $password)) {
        $error = "密码必须包含字母和数字";
    } else {
        try {
            // 检查手机号是否已注册
            $check_phone = $pdo->prepare("SELECT id FROM partners WHERE contact_phone = ?");
            $check_phone->execute([$phone]);
            $phone_exists = $check_phone->fetch(PDO::FETCH_ASSOC);
            
            if ($phone_exists) {
                $error = "该手机号已被注册";
            } else {
                // 验证邀请码，同时获取邀请者信息
                $stmt = $pdo->prepare("SELECT id, register_type, inviter_id, inviter_type FROM invitations WHERE code = ? AND status = 'active'");
                $stmt->execute([$invitation_code]);
                $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($invitation) {
                    // 检查邀请码类型是否为商家注册(register_type=2)
                    if ($invitation['register_type'] == 2) {
                        // 记录密码哈希生成过程
                        error_log("为新用户生成密码哈希，手机号: {$phone}");
                        // 检查password_hash函数是否可用
                        if (!function_exists('password_hash')) {
                            error_log("警告: password_hash函数不可用，这是PHP 5.5+的函数");
                        }
                        // 使用bcrypt算法哈希密码，设置适当的成本因子
                        $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
                        error_log("密码哈希生成成功，哈希值开头: " . (strlen($hashed_password) > 10 ? substr($hashed_password, 0, 10) : $hashed_password));
                        // 立即验证生成的哈希
                        if (password_verify($password, $hashed_password)) {
                            error_log("密码哈希自验证成功，算法工作正常");
                        } else {
                            error_log("错误: 密码哈希自验证失败，可能存在服务器配置问题");
                        }
                        
                        // 生成唯一的partner_id
                        $stmt = $pdo->prepare("SELECT MAX(id) as max_id FROM partners");
                        $stmt->execute();
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        $max_id = $result['max_id'];
                        
                        if (empty($max_id)) {
                            $partner_id = 'P001';
                        } else {
                            $num = intval(substr($max_id, 1)) + 1;
                            $partner_id = 'P' . str_pad($num, 3, '0', STR_PAD_LEFT);
                        }
                        
                        // 生成商家专属邀请码
                        $merchant_invitation_code = substr(md5($partner_id . time() . mt_rand()), 0, 10);
                        
                        // 开始事务
                        $pdo->beginTransaction();
                        
                        try {
                            // 检查partners表是否包含微信相关字段
                            try {
                                $stmt = $pdo->query("SHOW COLUMNS FROM partners LIKE 'wechat_openid'");
                                if ($stmt->rowCount() === 0) {
                                    $pdo->exec("ALTER TABLE partners ADD COLUMN wechat_openid VARCHAR(255) DEFAULT NULL");
                                }
                                
                                $stmt = $pdo->query("SHOW COLUMNS FROM partners LIKE 'wechat_nickname'");
                                if ($stmt->rowCount() === 0) {
                                    $pdo->exec("ALTER TABLE partners ADD COLUMN wechat_nickname VARCHAR(255) DEFAULT NULL");
                                }
                                
                                $stmt = $pdo->query("SHOW COLUMNS FROM partners LIKE 'wechat_avatar'");
                                if ($stmt->rowCount() === 0) {
                                    $pdo->exec("ALTER TABLE partners ADD COLUMN wechat_avatar VARCHAR(500) DEFAULT NULL");
                                }
                            } catch (PDOException $e) {
                                error_log("修改表结构失败: " . $e->getMessage());
                            }
                            
                            // 插入商家数据 - 确保参数顺序完全匹配SQL语句
                        $insert_stmt = $pdo->prepare("INSERT INTO partners (id, name, username, password, contact_phone, status, invitation_code, wechat_openid, wechat_nickname, wechat_avatar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        
                        // 记录参数详情
                        $params = [$partner_id, $name, $phone, $hashed_password, $phone, 'active', $merchant_invitation_code, $wechat_openid, $wechat_nickname, $wechat_avatar];
                        error_log("准备执行INSERT语句，参数数量: " . count($params));
                        
                        try {
                            $insert_success = $insert_stmt->execute($params);
                            error_log("INSERT语句执行成功，影响行数: " . $insert_stmt->rowCount());
                        } catch (PDOException $e) {
                            error_log("INSERT语句执行失败: " . $e->getMessage());
                            throw $e;
                        }
                        
                        error_log("用户注册成功: ID=$partner_id, 名称=$name, 手机号=$phone");
                        
                        // 插入后立即验证密码哈希
                        $check_stmt = $pdo->prepare("SELECT password FROM partners WHERE id = ?");
                        $check_stmt->execute([$partner_id]);
                        $stored_hash = $check_stmt->fetchColumn();
                        
                        error_log("从数据库获取的哈希值开头: " . (strlen($stored_hash) > 10 ? substr($stored_hash, 0, 10) : $stored_hash));
                        error_log("原始哈希值开头: " . (strlen($hashed_password) > 10 ? substr($hashed_password, 0, 10) : $hashed_password));
                        
                        if (password_verify($password, $stored_hash)) {
                            error_log("密码哈希验证成功，用户注册正常");
                        } else {
                            error_log("警告：密码哈希验证失败，可能存在存储或配置问题");
                            error_log("存储哈希长度: " . strlen($stored_hash) . ", 原始哈希长度: " . strlen($hashed_password));
                        }
                            
                            if ($insert_success) {
                                // 更新邀请码状态 - 对于partner类型的用户，不在user_id中存储(因为user_id是int类型)，而是记录到日志中
                                $update_invitation = $pdo->prepare("UPDATE invitations SET status = 'used', used_at = NOW() WHERE id = ?");
                                $update_invitation->execute([$invitation['id']]);
                                // 记录使用邀请码的partner_id信息到日志
                                error_log("邀请码 {$invitation_code} 被商家ID: {$partner_id} 使用");
                                
                                // 如果存在邀请者信息，记录邀请关系
                                if (!empty($invitation['inviter_id']) && !empty($invitation['inviter_type'])) {
                                    // 注意：partner_service_relations表用于商家-客服/管事-打手关系，不适合存储邀请者-商家关系
                                    // 且该表的inviter_id和invitee_id字段为int类型，无法存储字符串类型的partner_id
                                    
                                    // 记录邀请日志
                                    // 注意：logs表user_id字段为int类型，只记录数字ID的邀请者
                                    if (is_numeric($invitation['inviter_id'])) {
                                        $stmt = $pdo->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, 'invite_partner', ?)");
                                        $details = json_encode([
                                            'inviter_id' => $invitation['inviter_id'],
                                            'inviter_type' => $invitation['inviter_type'],
                                            'invitee_id' => $partner_id,
                                            'invitee_name' => $name,
                                            'invitation_code' => $invitation_code,
                                            'invite_time' => date('Y-m-d H:i:s')
                                        ]);
                                        $stmt->execute([$invitation['inviter_id'], $details]);
                                    } else {
                                        // 对于非数字ID的邀请者，使用日志文件记录
                                        error_log("邀请日志 - 邀请者ID: {$invitation['inviter_id']}, 类型: {$invitation['inviter_type']}, 被邀请商家ID: {$partner_id}, 名称: {$name}, 邀请码: {$invitation_code}");
                                    }
                                }
                                
                                // 提交事务
                                $pdo->commit();
                                
                                // 清除微信会话信息
                                unset($_SESSION['wechat_openid']);
                                unset($_SESSION['wechat_nickname']);
                                unset($_SESSION['wechat_avatar']);
                                
                                // 设置会话变量
                                $_SESSION['partner_id'] = $partner_id;
                                $_SESSION['partner_name'] = $name;
                                $_SESSION['partner_username'] = $phone;
                                $_SESSION['partner_info'] = [
                                    'id' => $partner_id,
                                    'name' => $name,
                                    'username' => $phone,
                                    'status' => 'active',
                                    'invitation_code' => $merchant_invitation_code
                                ];
                                
                                // 重定向到仪表盘
                                header("Location: index.php");
                                exit();
                            } else {
                                $pdo->rollBack();
                                $error = "注册失败，请稍后再试";
                            }
                        } catch (Exception $e) {
                            // 回滚事务
                            $pdo->rollBack();
                            throw $e;
                        }
                    } else {
                        $error = "邀请码类型错误，请使用商家注册邀请码";
                    }
                } else {
                    $error = "邀请码无效或已被使用";
                }
            }
        } catch (PDOException $e) {
            $error = "注册过程中发生错误: " . $e->getMessage();
            error_log("注册异常: " . $e->getMessage());
        }
    }
}

// 生成商家ID函数(保留以兼容旧代码)
function generatePartnerId() {
    return substr(str_shuffle(str_repeat('0123456789', 3)), 0, 3) . rand(100, 999);
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>商家注册</title>
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
            animation: fadeInUp 0.6s ease-out;
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

        .register-links {
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

        .register-link {
            color: #666666;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s ease;
            padding: 6px 12px;
            border-radius: 16px;
        }

        .register-link:hover {
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

        /* 注册说明 */
        .register-info {
            margin-top: 24px;
            padding: 20px;
            border: none;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .register-info h6 {
            font-size: 15px;
            font-weight: 600;
            color: #000000;
            margin-bottom: 12px;
        }

        .register-info ul {
            list-style: none;
        }

        .register-info li {
            font-size: 13px;
            color: #666666;
            margin-bottom: 8px;
            padding-left: 16px;
            position: relative;
        }

        .register-info li::before {
            content: '•';
            position: absolute;
            left: 4px;
            color: #f5b400;
            font-weight: bold;
        }

        /* 复选框 */
        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
        }

        .form-check-input {
            width: 20px;
            height: 20px;
            border: 2px solid #eaeaea;
            background: #ffffff;
            appearance: none;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .form-check-input:checked {
            border-color: #000000;
            background: #000000;
            position: relative;
        }

        .form-check-input:checked::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #ffffff;
            font-size: 12px;
            font-weight: bold;
        }

        .form-check-label {
            font-size: 14px;
            color: #666666;
            cursor: pointer;
        }

        .text-primary-600 {
            color: #f5b400;
            font-weight: 500;
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

            .register-links {
                padding-top: 20px;
            }

            .link-group {
                gap: 12px;
            }

            .register-link {
                font-size: 13px;
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
                    <i class="fas fa-store"></i>
                </div>
                <h1 class="register-title">商家注册</h1>
                <p class="register-subtitle">请填写以下信息创建商家账户</p>
            </div>
            
            <div class="register-form">
            <?php if (isset($_SESSION['success_msg'])): ?>
                <div class="error-message" style="background: #f0fff4; border-color: #c8e6c9; color: #2e7d32;">
                    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_msg'])): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="register.php">
                <div class="form-group">
                    <div class="form-input-container">
                        <input type="text" class="form-input" 
                               name="name" placeholder="商家名称" required
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                        <span class="form-icon">
                            <i class="fas fa-store"></i>
                        </span>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="form-input-container">
                        <input type="tel" class="form-input" 
                               name="phone" placeholder="手机号码" required
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        <span class="form-icon">
                            <i class="fas fa-phone"></i>
                        </span>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="form-input-container">
                        <input type="text" class="form-input" 
                               id="invitation-code" name="invitation_code" placeholder="密钥" required>
                        <span class="form-icon">
                            <i class="fas fa-key"></i>
                        </span>
                    </div>
                    <span class="text-xs text-secondary-light block mt-4">密钥由系统管理员提供</span>
                </div>
                
                <div class="form-group">
                    <div class="form-input-container">
                        <input type="password" class="form-input" 
                               id="register-password" name="password" placeholder="密码(至少8位，包含字母和数字)" required>
                        <span class="form-icon">
                            <i class="fas fa-lock"></i>
                        </span>
                        <span class="toggle-password" 
                              onclick="togglePassword('#register-password')">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="form-input-container">
                        <input type="password" class="form-input" 
                               id="confirm-password" name="confirm_password" placeholder="确认密码" required>
                        <span class="form-icon">
                            <i class="fas fa-lock"></i>
                        </span>
                        <span class="toggle-password" 
                              onclick="togglePassword('#confirm-password')">
                            <i class="fas fa-eye"></i>
                        </span>
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
                                <i class="fab fa-weixin" style="color: #07C160;"></i> 绑定微信后即可注册
                            </a>
                        <?php else: ?>
                            <button type="button" class="btn" disabled style="width: 100%; margin-bottom: 8px;">
                                <i class="fas fa-exclamation-triangle"></i> 暂未配置登录功能
                            </button>
                        <?php endif; ?>
                        <div class="invitation-hint" style="margin-top: 8px; font-size: 12px; color: #8e8e93; display: flex; align-items: center;">
                            <i class="fas fa-info-circle" style="margin-right: 5px; color: #8e8e93; font-size: 11px;"></i> 绑定微信后，下次登录可直接使用微信一键登录
                        </div>
                    <?php endif; ?>
                </div>
                
                <input type="hidden" name="wechat_openid" value="<?php echo htmlspecialchars($wechat_openid); ?>">
                <input type="hidden" name="wechat_nickname" value="<?php echo htmlspecialchars($wechat_nickname); ?>">
                <input type="hidden" name="wechat_avatar" value="<?php echo htmlspecialchars($wechat_avatar); ?>">

                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="agree_terms" name="agree_terms" required>
                    <label class="form-check-label" for="agree_terms">
                        我已阅读并同意<a href="#" class="text-primary-600">《服务协议》</a>和<a href="#" class="text-primary-600">《隐私政策》</a>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary" id="registerBtn"> 
                    <i class="fas fa-user-plus"></i> 注册商家账户
                </button>

                <!-- 注册说明 -->
                <div class="register-info">
                    <h6>注册说明：</h6>
                    <ul>
                        <li>• 商家注册需要有效的商家密钥</li>
                        <li>• 注册成功后将生成您的专属密钥</li>
                        <li>• 可用于邀请客服人员加入您的团队</li>
                    </ul>
                </div>

                <div class="register-links">
                    <div class="link-group">
                        <a href="login.php" class="register-link">已有账户？立即登录</a>
                    </div>
                </div>
            </form>
            </div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 密码显示/隐藏功能
    const togglePassword = document.querySelectorAll('.toggle-password');
    
    togglePassword.forEach(function(toggle) {
        toggle.addEventListener('click', function() {
            const target = this.getAttribute('onclick').match(/togglePassword\('(.*)'\)/)[1];
            const input = document.querySelector(target);
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
    
    // 手机号输入限制
    const phoneInput = document.querySelector('input[name="phone"]');
    if (phoneInput) {
        phoneInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^\d]/g, '');
        });
    }
    
    // 表单提交前的处理
    const form = document.querySelector('form');
    const submitBtn = form.querySelector('button[type="submit"]');
    
    form.addEventListener('submit', function() {
        submitBtn.disabled = true;
        submitBtn.classList.add('loading');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 注册中...';
    });
});

function togglePassword(target) {
    const input = document.querySelector(target);
    const icon = document.querySelector(`[onclick="togglePassword('${target}')"] i`);
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}


</script>

    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>