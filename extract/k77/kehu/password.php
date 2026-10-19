<?php
require '../includes/session_manager.php';
require '../config.php';

$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

$isLoggedIn = is_h5_logged_in();
$user_id = get_h5_user_id();

if (!$isLoggedIn || !$user_id) {
    header("Location: login.php?redirect=" . urlencode($currentUrl));
    exit();
}

$message = '';
$error = '';

try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

    // 处理表单提交
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $oldPassword = $_POST['old_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($oldPassword)) {
            $error = '请输入原密码';
        } elseif (empty($newPassword)) {
            $error = '请输入新密码';
        } elseif (strlen($newPassword) < 6) {
            $error = '新密码长度不能少于6位';
        } elseif ($newPassword !== $confirmPassword) {
            $error = '两次输入的新密码不一致';
        } else {
            // 验证原密码
            $stmt = $db->prepare("SELECT password FROM h5user WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($oldPassword, $user['password'])) {
                $error = '原密码错误';
            } else {
                // 更新密码
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE h5user SET password = ? WHERE id = ?");
                
                if ($stmt->execute([$hashedPassword, $user_id])) {
                    $message = '密码修改成功！请使用新密码重新登录';
                } else {
                    $error = '密码修改失败';
                }
            }
        }
    }
} catch (PDOException $e) {
    die("数据库错误: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>修改密码</title>
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
            --card-bg: #ffffff;
            --text-primary: #333333;
            --text-secondary: #666666;
            --text-tertiary: #999999;
            --accent-gold: #d4a84b;
        }

        body {
            font-family: 'Noto Sans SC', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--primary-bg);
            color: var(--text-primary);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            padding-bottom: 80px;
        }

        /* 顶部导航 */
        .page-header {
            padding: 16px 20px;
            background: var(--primary-bg);
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
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
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

        /* 表单卡片 */
        .form-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 20px;
            margin-top: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .form-input-wrapper {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            padding-right: 50px;
            border: 1px solid #e8e8e8;
            border-radius: 12px;
            font-size: 15px;
            color: var(--text-primary);
            background: #fafafa;
            outline: none;
            transition: border-color 0.2s ease;
        }

        .form-input:focus {
            border-color: var(--accent-gold);
            background: #fff;
        }

        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-tertiary);
            cursor: pointer;
            font-size: 18px;
        }

        /* 提示信息 */
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .alert-error {
            background: #ffebee;
            color: #c62828;
        }

        /* 保存按钮 */
        .save-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #d4a84b 0%, #f5c842 100%);
            color: #fff;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
        }

        .save-btn:active {
            opacity: 0.9;
        }

        /* 提示文字 */
        .tips {
            margin-top: 20px;
            padding: 16px;
            background: #fff9e6;
            border-radius: 12px;
        }

        .tips-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .tips-list {
            list-style: none;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .tips-list li {
            margin-bottom: 4px;
            padding-left: 16px;
            position: relative;
        }

        .tips-list li::before {
            content: '•';
            position: absolute;
            left: 4px;
            color: var(--accent-gold);
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
        <a href="settings.php" class="back-btn">
            <i class="fas fa-chevron-left"></i>
        </a>
        <h1 class="page-title">修改密码</h1>
    </div>

    <!-- 主内容 -->
    <main class="main-content">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- 表单 -->
        <form class="form-card" method="post" action="">
            <div class="form-group">
                <label class="form-label">原密码</label>
                <div class="form-input-wrapper">
                    <input type="password" name="old_password" class="form-input" placeholder="请输入原密码" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword(this)"></i>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">新密码</label>
                <div class="form-input-wrapper">
                    <input type="password" name="new_password" class="form-input" placeholder="请输入新密码（至少6位）" required minlength="6">
                    <i class="fas fa-eye toggle-password" onclick="togglePassword(this)"></i>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">确认新密码</label>
                <div class="form-input-wrapper">
                    <input type="password" name="confirm_password" class="form-input" placeholder="请再次输入新密码" required minlength="6">
                    <i class="fas fa-eye toggle-password" onclick="togglePassword(this)"></i>
                </div>
            </div>

            <button type="submit" class="save-btn">确认修改</button>
        </form>

        <!-- 提示 -->
        <div class="tips">
            <div class="tips-title">密码安全提示</div>
            <ul class="tips-list">
                <li>密码长度建议6-20位</li>
                <li>建议包含字母和数字组合</li>
                <li>请勿使用过于简单的密码</li>
                <li>定期更换密码可提高安全性</li>
            </ul>
        </div>
    </main>

    <script>
        function togglePassword(icon) {
            const input = icon.previousElementSibling;
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
