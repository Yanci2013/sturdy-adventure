<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/header.php';

$user_id = get_user_user_id() ?? 0;
$user = getUserById($pdo, $user_id);

$settings = $pdo->query("SELECT appid, appkey FROM settings LIMIT 1")->fetch();
$appid = $settings['appid'] ?? '';
$appkey = $settings['appkey'] ?? '';

if(isset($_GET['wx_callback']) && $_GET['wx_callback'] == '1' && isset($_GET['openid'])) {
    $openid = trim($_GET['openid']);
    
    if(!preg_match('/^[a-zA-Z0-9_-]{28,32}$/', $openid)) {
        die("<script>alert('无效的微信OpenID格式');location.href='profile_edit.php';</script>");
    }
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE wechat_openid = ? AND id != ?");
    $stmt->execute([$openid, $user_id]);
    if($stmt->rowCount() > 0) {
        die("<script>alert('该微信账号已绑定其他用户');location.href='profile_edit.php';</script>");
    }
    
    $stmt = $pdo->prepare("UPDATE users SET wechat_openid = ? WHERE id = ?");
    if($stmt->execute([$openid, $user_id])) {
        $user = getUserById($pdo, $user_id);
        echo "<script>alert('微信绑定成功');location.href='profile_edit.php';</script>";
        exit;
    } else {
        die("<script>alert('微信绑定失败');location.href='profile_edit.php';</script>");
    }
}

try {
    $errors = [];
    $success = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $real_name = trim($_POST['real_name'] ?? '');
        $id_card = trim($_POST['id_card'] ?? '');
        $wechat_openid = trim($_POST['wechat_openid'] ?? '');
        
        $avatar = $user['avatar'];
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = dirname(__DIR__) . '/assets/images/userds/';
            
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    $errors[] = "无法创建头像目录";
                }
            }
            
            if (!is_writable($upload_dir)) {
                $errors[] = "头像目录不可写，请检查权限";
            }
            
            $allowed_types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($file_info, $_FILES['avatar']['tmp_name']);
            
            $max_size = 2 * 1024 * 1024;
            if ($_FILES['avatar']['size'] > $max_size) {
                $errors[] = "头像文件大小不能超过2MB";
            }
            
            if (array_key_exists($mime_type, $allowed_types) && empty($errors)) {
                $ext = $allowed_types[$mime_type];
                $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
                $destination = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
                    if ($avatar !== 'default_avatar.png' && file_exists($upload_dir . $avatar)) {
                        unlink($upload_dir . $avatar);
                    }
                    $avatar = $filename;
                } else {
                    $errors[] = "头像上传失败";
                }
            } elseif (!array_key_exists($mime_type, $allowed_types)) {
                $errors[] = "只允许上传JPEG、PNG或GIF格式的图片";
            }
            
            finfo_close($file_info);
        }
        
        if (empty($username)) {
            $errors[] = "用户名不能为空";
        } elseif (strlen($username) < 3 || strlen($username) > 20) {
            $errors[] = "用户名长度必须在3-20个字符之间";
        }
        
        if (empty($phone)) {
            $errors[] = "手机号不能为空";
        } elseif (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            $errors[] = "请输入有效的手机号码";
        }
        
        if (!empty($password)) {
            if (strlen($password) < 6) {
                $errors[] = "密码长度不能少于6位";
            }
        }
        
        if (!empty($wechat_openid) && !preg_match('/^[a-zA-Z0-9_-]{28,32}$/', $wechat_openid)) {
            $errors[] = "微信OpenID格式不正确";
        }
        
        if ($phone !== $user['phone']) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            if ($stmt->rowCount() > 0) {
                $errors[] = "该手机号已被注册";
            }
        }
        
        if ($username !== $user['username']) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->rowCount() > 0) {
                $errors[] = "该用户名已被使用";
            }
        }
        
        if (!empty($wechat_openid) && $wechat_openid !== $user['wechat_openid']) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE wechat_openid = ?");
            $stmt->execute([$wechat_openid]);
            if ($stmt->rowCount() > 0) {
                $errors[] = "该微信账号已绑定其他用户";
            }
        }
        
        if (empty($errors)) {
            $pdo->beginTransaction();
            try {
                $update_fields = ["avatar = :avatar"];
                $params = [':avatar' => $avatar]; 
                
                if ($username !== $user['username']) {
                    $update_fields[] = "username = :username";
                    $params[':username'] = $username;
                }
                
                if ($phone !== $user['phone']) {
                    $update_fields[] = "phone = :phone";
                    $params[':phone'] = $phone;
                }
                
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $update_fields[] = "password = :password";
                    $params[':password'] = $hashed_password;
                }
                
                if (!empty($real_name) && empty($user['real_name'])) {
                    $update_fields[] = "real_name = :real_name";
                    $params[':real_name'] = $real_name;
                }
                
                if (!empty($id_card) && empty($user['id_card'])) {
                    $update_fields[] = "id_card = :id_card";
                    $params[':id_card'] = $id_card;
                }
                
                if (!empty($wechat_openid)) {
                    $update_fields[] = "wechat_openid = :wechat_openid";
                    $params[':wechat_openid'] = $wechat_openid;
                }
                
                $sql = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = :id";
                $params[':id'] = $user_id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                $pdo->commit();
                $success = true;
                
                $_SESSION['username'] = $username;
                $user = getUserById($pdo, $user_id);
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errors[] = "更新个人信息失败：" . $e->getMessage();
            }
        }
    }
} catch (PDOException $e) {
    die("数据库错误: " . $e->getMessage());
}

$redirect_uri = urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?wx_callback=1');
$wechat_login_url = "https://auths.acloudn.com/connect.php?act=login&appid={$appid}&appkey={$appkey}&type=wx&redirect_uri={$redirect_uri}";
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>个人信息修改</title>
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

        .card {
            background: #ffffff;
            border-radius: 16px;
            padding: 16px;
            margin: 12px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: #000000;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title i {
            color: #f5b400;
        }

        .avatar-section {
            text-align: center;
            padding: 20px 0;
        }

        .user-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #f5b400;
            margin-bottom: 12px;
        }

        .upload-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #f5f5f5;
            border: none;
            border-radius: 12px;
            color: #666666;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .upload-btn:hover {
            background: #e8e8e8;
            transform: translateY(-2px);
        }

        .upload-tip {
            font-size: 12px;
            color: #999999;
            margin-top: 8px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 12px;
        }

        .info-item {
            background: #fafafa;
            padding: 12px;
            border-radius: 12px;
            text-align: center;
        }

        .info-label {
            font-size: 12px;
            color: #999999;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: #000000;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #000000;
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #eaeaea;
            border-radius: 12px;
            font-size: 14px;
            color: #000000;
            background: #fafafa;
            transition: all 0.2s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #f5b400;
            box-shadow: 0 0 0 3px rgba(245, 180, 0, 0.1);
            background: #ffffff;
        }

        .form-input:disabled {
            background: #f5f5f5;
            color: #999999;
            cursor: not-allowed;
        }

        .form-input::placeholder {
            color: #cccccc;
        }

        .form-text {
            font-size: 12px;
            color: #999999;
            margin-top: 6px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: #000000;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #333333;
            transform: translateY(-2px);
        }

        .btn-block {
            width: 100%;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 14px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #86efac;
            color: #16a34a;
        }

        .alert i {
            font-size: 18px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .alert ul {
            margin: 6px 0 0 20px;
        }

        .alert li {
            margin-bottom: 4px;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-success {
            background: #f0fdf4;
            color: #16a34a;
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
            <a href="profile2.php" class="back-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <h1 class="header-title">个人信息修改</h1>
            <div class="header-right"></div>
        </div>
    </header>

    <main class="app-container">
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                个人信息更新成功！
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>提交修改时遇到问题：</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-title">
                <i class="fas fa-user"></i>
                个人信息概览
            </div>
            
            <div class="avatar-section">
                <img src="../assets/images/userds/<?php echo htmlspecialchars($user['avatar']); ?>" 
                     onerror="this.src='../assets/images/user.png'" 
                     class="user-avatar" 
                     id="avatarPreview"
                     alt="用户头像">
                
                <div>
                    <?php if (!empty($user['wechat_openid'])): ?>
                        <span class="badge badge-success">
                            <i class="fab fa-weixin"></i> 已绑定微信
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">会员等级</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['member_level']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">账号状态</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['status']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">账户余额</div>
                    <div class="info-value">¥<?php echo number_format($user['balance'], 2); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">保证金</div>
                    <div class="info-value">¥<?php echo number_format($user['deposit'], 2); ?></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-title">
                <i class="fas fa-edit"></i>
                修改个人信息
            </div>
            
            <form method="post" action="profile_edit.php" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">头像</label>
                    <div>
                        <button type="button" class="upload-btn" onclick="document.getElementById('avatarInput').click()">
                            <i class="fas fa-upload"></i> 上传头像
                        </button>
                        <input type="file" name="avatar" id="avatarInput" style="display: none;" accept="image/*" onchange="previewAvatar(event)">
                        <div class="upload-tip">支持JPG、PNG或GIF格式，最大2MB</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">用户名</label>
                    <input type="text" name="username" required 
                           value="<?php echo htmlspecialchars($user['username']); ?>" 
                           class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">手机号</label>
                    <input type="tel" name="phone" required 
                           value="<?php echo htmlspecialchars($user['phone']); ?>" 
                           class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">修改密码</label>
                    <input type="password" name="password" 
                           placeholder="留空则不修改" 
                           class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">真实姓名</label>
                    <?php if (!empty($user['real_name'])): ?>
                        <input type="text" class="form-input" disabled 
                               value="<?php echo htmlspecialchars($user['real_name']); ?>">
                        <div class="form-text">实名信息只能设置一次</div>
                    <?php else: ?>
                        <input type="text" name="real_name" 
                               value="<?php echo htmlspecialchars($_POST['real_name'] ?? ''); ?>" 
                               placeholder="选填" 
                               class="form-input">
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label class="form-label">身份证号</label>
                    <?php if (!empty($user['id_card'])): ?>
                        <input type="text" class="form-input" disabled 
                               value="<?php echo substr_replace($user['id_card'], '********', 6, 8); ?>">
                        <div class="form-text">实名信息只能设置一次</div>
                    <?php else: ?>
                        <input type="text" name="id_card" 
                               value="<?php echo htmlspecialchars($_POST['id_card'] ?? ''); ?>" 
                               placeholder="选填" 
                               class="form-input">
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-check"></i>
                    保存修改
                </button>
            </form>
        </div>
    </main>

    <script>
        function previewAvatar(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatarPreview').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        }
    </script>

    <?php include 'footer.php'; ?>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
