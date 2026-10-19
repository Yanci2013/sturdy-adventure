<?php
require_once("../includes/session_manager.php");
include("../includes/common.php");

if (!is_admin_logged_in()) {
    header("Location: ../login.php");
    exit();
}

$msg = '';
$msgType = '';

// 保存设置
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_login'])) {
    $login_account = isset($_POST['login_account']) ? 1 : 0;
    $login_phone = isset($_POST['login_phone']) ? 1 : 0;
    $login_wechat = isset($_POST['login_wechat']) ? 1 : 0;
    
    $config = json_encode([
        'account' => $login_account,
        'phone' => $login_phone,
        'wechat' => $login_wechat
    ]);
    
    try {
        // 检查字段是否存在
        $check = $pdo->query("SHOW COLUMNS FROM settings LIKE 'login_methods'");
        if (!$check->fetch()) {
            $pdo->exec("ALTER TABLE settings ADD COLUMN login_methods TEXT DEFAULT NULL");
        }
        
        $stmt = $pdo->prepare("UPDATE settings SET login_methods = ? WHERE id = 1");
        $stmt->execute([$config]);
        $msg = '登录方式设置保存成功';
        $msgType = 'success';
    } catch (Exception $e) {
        $msg = '保存失败：' . $e->getMessage();
        $msgType = 'danger';
    }
}

// 读取当前设置
$login_config = ['account' => 1, 'phone' => 0, 'wechat' => 1];
try {
    $stmt = $pdo->query("SELECT login_methods FROM settings WHERE id = 1");
    $row = $stmt->fetch();
    if ($row && !empty($row['login_methods'])) {
        $login_config = json_decode($row['login_methods'], true);
    }
} catch (Exception $e) {}

$site_title = "小熠网络";
try {
    $stmt = $pdo->query("SELECT site_title FROM settings WHERE id = 1");
    $row = $stmt->fetch();
    if ($row) $site_title = $row['site_title'];
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录方式设置 - <?php echo htmlspecialchars($site_title); ?></title>
    <link href="../res/layui/css/layui.css" rel="stylesheet">
    <link href="../res/admin-white.css" rel="stylesheet">
    <style>
        body { background: #f5f7fa; padding: 20px; }
        .setting-card {
            background: #fff;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            max-width: 700px;
            margin: 0 auto;
        }
        .setting-title {
            font-size: 18px;
            font-weight: 700;
            color: #1e40af;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 2px solid #e8f0fe;
        }
        .login-option {
            display: flex;
            align-items: center;
            padding: 16px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 12px;
            transition: all 0.2s;
            background: #f8fafc;
        }
        .login-option:hover {
            border-color: #3b82f6;
            background: #f0f7ff;
        }
        .login-option-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-right: 14px;
            flex-shrink: 0;
        }
        .icon-account { background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff; }
        .icon-phone { background: linear-gradient(135deg, #10b981, #059669); color: #fff; }
        .icon-wechat { background: linear-gradient(135deg, #07c160, #06ad56); color: #fff; }
        .login-option-info { flex: 1; }
        .login-option-name { font-size: 15px; font-weight: 600; color: #1e293b; margin-bottom: 3px; }
        .login-option-desc { font-size: 12px; color: #64748b; }
        .layui-form-switch { margin: 0 !important; }
        .save-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.2s;
        }
        .save-btn:hover { box-shadow: 0 4px 16px rgba(59,130,246,0.3); }
        .save-btn:active { transform: scale(0.98); }
        .msg-box {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .msg-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .msg-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .tip-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #1e40af;
            line-height: 1.6;
        }
        .tip-box i { margin-right: 6px; }
    </style>
</head>
<body>
    <div class="setting-card">
        <div class="setting-title"><i class="fa fa-sign-in-alt me-2"></i>登录方式设置</div>
        
        <?php if ($msg): ?>
            <div class="msg-box msg-<?php echo $msgType; ?>">
                <i class="fa fa-<?php echo $msgType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>
        
        <div class="tip-box">
            <i class="fa fa-info-circle"></i>
            可同时开启多种登录方式，用户可在登录页选择任意一种已开启的方式进行登录/注册。
        </div>
        
        <form method="post">
            <!-- 账号登录 -->
            <div class="login-option">
                <div class="login-option-icon icon-account"><i class="fa fa-user"></i></div>
                <div class="login-option-info">
                    <div class="login-option-name">账号密码登录</div>
                    <div class="login-option-desc">使用用户名/手机号 + 密码登录注册</div>
                </div>
                <input type="checkbox" name="login_account" lay-skin="switch" lay-text="开启|关闭" <?php echo !empty($login_config['account']) ? 'checked' : ''; ?>>
            </div>
            
            <!-- 手机号登录 -->
            <div class="login-option">
                <div class="login-option-icon icon-phone"><i class="fa fa-mobile-alt"></i></div>
                <div class="login-option-info">
                    <div class="login-option-name">手机号验证码登录</div>
                    <div class="login-option-desc">使用手机号 + 短信验证码登录注册（需配置短信服务）</div>
                </div>
                <input type="checkbox" name="login_phone" lay-skin="switch" lay-text="开启|关闭" <?php echo !empty($login_config['phone']) ? 'checked' : ''; ?>>
            </div>
            
            <!-- 微信登录 -->
            <div class="login-option">
                <div class="login-option-icon icon-wechat"><i class="fab fa-weixin"></i></div>
                <div class="login-option-info">
                    <div class="login-option-name">微信一键授权登录</div>
                    <div class="login-option-desc">微信扫码/公众号授权一键登录注册</div>
                </div>
                <input type="checkbox" name="login_wechat" lay-skin="switch" lay-text="开启|关闭" <?php echo !empty($login_config['wechat']) ? 'checked' : ''; ?>>
            </div>
            
            <button type="submit" name="save_login" class="save-btn">
                <i class="fa fa-save me-2"></i>保存设置
            </button>
        </form>
    </div>
    
    <script src="../res/layui/layui.js"></script>
    <script>
        layui.use(['form'], function() {
            var form = layui.form;
            form.render();
        });
    </script>
</body>
</html>
