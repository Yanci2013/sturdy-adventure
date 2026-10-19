<?php
require '../includes/session_manager.php';
require '../config.php';

$error = '';
$success = '';

try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
} catch(PDOException $e) {
    $error = '数据库连接失败，请检查配置';
}

// 读取登录方式配置
$login_config = ['account' => 1, 'phone' => 0, 'wechat' => 1];
try {
    $stmt = $db->query("SELECT login_methods FROM settings WHERE id = 1");
    $row = $stmt->fetch();
    if ($row && !empty($row['login_methods'])) {
        $login_config = json_decode($row['login_methods'], true);
    }
} catch (PDOException $e) {
    // 字段不存在时使用默认配置
}
// 确保至少有一种登录方式开启
if (empty($login_config['account']) && empty($login_config['phone']) && empty($login_config['wechat'])) {
    $login_config['account'] = 1;
}

try {
    $checkFields = [
        'social_uid' => "ALTER TABLE h5user ADD COLUMN social_uid VARCHAR(255) DEFAULT NULL",
        'social_type' => "ALTER TABLE h5user ADD COLUMN social_type VARCHAR(50) DEFAULT NULL",
        'avatar' => "ALTER TABLE h5user ADD COLUMN avatar VARCHAR(255) DEFAULT NULL",
        'nickname' => "ALTER TABLE h5user ADD COLUMN nickname VARCHAR(255) DEFAULT NULL",
        'user_idu' => "ALTER TABLE h5user ADD COLUMN user_idu INT DEFAULT 0",
        'reg_ip' => "ALTER TABLE h5user ADD COLUMN reg_ip VARCHAR(100) DEFAULT NULL",
        'invite_code' => "ALTER TABLE h5user ADD COLUMN invite_code VARCHAR(50) NOT NULL DEFAULT ''",
        'invite_uid' => "ALTER TABLE h5user ADD COLUMN invite_uid INT NOT NULL DEFAULT 0"
    ];
    foreach ($checkFields as $field => $sql) {
        $stmt = $db->query("SHOW COLUMNS FROM h5user LIKE '{$field}'");
        if ($stmt->rowCount() === 0) {
            $db->exec($sql);
        }
    }
} catch (PDOException $e) {
    error_log('字段补全失败：'.$e->getMessage());
}

$invite_code = isset($_GET['invite']) ? trim($_GET['invite']) : '';
$invite_uid = 0;
if (!empty($invite_code)) {
    $stmt = $db->prepare("SELECT id FROM h5user WHERE invite_code = ? LIMIT 1");
    $stmt->execute([$invite_code]);
    $inviteUser = $stmt->fetch();
    if ($inviteUser) {
        $invite_uid = $inviteUser['id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $repwd = trim($_POST['repwd']);

    if (empty($username) || empty($password) || empty($repwd)) {
        $error = '请填写完整注册信息';
    } elseif (strlen($username) < 3 || strlen($username) > 20) {
        $error = '用户名长度3-20位';
    } elseif ($password !== $repwd) {
        $error = '两次输入密码不一致';
    } elseif (strlen($password) < 6) {
        $error = '密码长度至少6位';
    } else {
        $stmt = $db->prepare("SELECT id FROM h5user WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            $error = '该用户名已被占用';
        } else {
            $pwd_hash = password_hash($password, PASSWORD_DEFAULT);
            $user_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $new_idu = mt_rand(100000, 999999);
            $user_invite_code = strtoupper(md5(uniqid(mt_rand(), true)));
            $stmt = $db->prepare("INSERT INTO h5user (username,password,nickname,user_idu,reg_ip,invite_code,invite_uid,created_at) VALUES (?,?,?,?,?,?,?,NOW())");
            $stmt->execute([$username, $pwd_hash, $username, $new_idu, $user_ip, $user_invite_code, $invite_uid]);
            $success = '注册成功，请登录';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    if (empty($username) || empty($password)) {
        $error = '请输入用户名和密码';
    } else {
        $stmt = $db->prepare("SELECT * FROM h5user WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            set_h5_session($user['id'], $user);
            header("Location: my.php");
            exit();
        } else {
            $error = '用户名或密码错误';
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'get_login_url' && isset($_GET['type'])) {
    $type = $_GET['type'];
    $appid = '3527';
    $appkey = '3af0521a7f9afbcb6c637c7533530bbe';
    $redirect_uri = urlencode('https://' . $_SERVER['HTTP_HOST'] . '/kehu/login.php');
    try {
        $url = "https://u.suyanw.cn/connect.php?act=login&appid={$appid}&appkey={$appkey}&type={$type}&redirect_uri={$redirect_uri}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        if ($data && $data['code'] === 0 && !empty($data['url'])) {
            header("Location: " . $data['url']);
            exit();
        } else {
            $error = '微信登录接口异常，请使用账号密码登录';
        }
    } catch (Exception $e) {
        $error = '第三方登录服务不可用，请使用账号密码登录';
    }
}

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
    curl_close($ch);
    $data = json_decode($response, true);
    if ($data && $data['code'] === 0) {
        $social_uid = $data['social_uid'];
        $nickname = $data['nickname'];
        $faceimg = $data['faceimg'];
        $gender = $data['gender'] ?? '';
        $stmt = $db->prepare("SELECT * FROM h5user WHERE social_uid = ? AND social_type = ? LIMIT 1");
        $stmt->execute([$social_uid, $type]);
        $user = $stmt->fetch();
        if ($user) {
            set_h5_session($user['id'], $user);
            header("Location: my.php");
            exit();
        } else {
            $user_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $new_idu = mt_rand(100000, 999999);
            $user_invite_code = strtoupper(md5(uniqid(mt_rand(), true)));
            $base_name = empty($nickname) ? 'wx_user' . substr($social_uid,0,8) : $nickname;
            $username = $base_name;
            $suffix = 1;
            while (true) {
                $tmp = $db->prepare("SELECT id FROM h5user WHERE username = ? LIMIT 1");
                $tmp->execute([$username]);
                if ($tmp->rowCount() === 0) break;
                $username = $base_name . '_' . $suffix++;
            }
            $pwd_hash = password_hash(uniqid(), PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO h5user (username,password,nickname,avatar,social_uid,social_type,user_idu,reg_ip,invite_code,invite_uid,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())");
            $stmt->execute([$username, $pwd_hash, $nickname, $faceimg, $social_uid, $type, $new_idu, $user_ip, $user_invite_code, $invite_uid]);
            $new_uid = $db->lastInsertId();
            $new_user = $db->query("SELECT * FROM h5user WHERE id = {$new_uid} LIMIT 1")->fetch();
            set_h5_session($new_uid, $new_user);
            header("Location: my.php");
            exit();
        }
    } else {
        $error = '微信授权失败，请重试';
    }
}

if(is_h5_logged_in()) {
    header("Location: my.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>用户登录</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
:root{--primary:#D4A84B;--primary-dark:#B8943A;--text:#2C2C2C;--text-light:#8A8A8A;--bg:#FAF8F5;--card:#FFFFFF;--border:#F0EDE8;--input-bg:#F7F5F2;--error:#E74C3C;--success:#27AE60}
html,body{height:100%}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Hiragino Sans GB','Microsoft YaHei',sans-serif;background:var(--bg);color:var(--text);display:flex;align-items:center;justify-content:center;padding:16px;-webkit-font-smoothing:antialiased}

/* 卡片 */
.card{width:100%;max-width:400px;background:var(--card);border-radius:24px;box-shadow:0 4px 24px rgba(0,0,0,0.04),0 1px 3px rgba(0,0,0,0.02);overflow:hidden;animation:cardIn 0.5s ease}
@keyframes cardIn{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}

/* 顶部装饰 */
.card-top{height:6px;background:linear-gradient(90deg,var(--primary),#E8C87A)}

/* 内容区 */
.card-body{padding:32px 28px 28px}

/* Logo */
.logo-area{text-align:center;margin-bottom:28px}
.logo-icon{width:64px;height:64px;margin:0 auto 14px;background:linear-gradient(135deg,var(--primary),#E8C87A);border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:28px;color:#fff;box-shadow:0 8px 24px rgba(212,168,75,0.25)}
.logo-area h1{font-size:22px;font-weight:700;color:var(--text);margin-bottom:4px}
.logo-area p{font-size:13px;color:var(--text-light)}

/* 消息提示 */
.msg{padding:12px 14px;border-radius:12px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:8px;animation:msgIn 0.3s ease}
.msg-error{background:#FEF2F2;color:var(--error);border:1px solid #FEE2E2}
.msg-success{background:#F0FDF4;color:var(--success);border:1px solid #DCFCE7}
@keyframes msgIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}

/* 标签切换 */
.tab-bar{position:relative;display:flex;margin-bottom:28px;background:var(--input-bg);border-radius:14px;padding:4px}
.tab-bar::after{content:'';position:absolute;top:4px;left:4px;width:calc(50% - 4px);height:calc(100% - 8px);background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.04);transition:transform 0.3s cubic-bezier(0.4,0,0.2,1)}
.tab-bar.register::after{transform:translateX(100%)}
.tab-item{flex:1;position:relative;z-index:1;text-align:center;padding:11px 0;font-size:15px;font-weight:600;color:var(--text-light);cursor:pointer;transition:color 0.3s;border:none;background:none}
.tab-item.active{color:var(--text)}

/* 表单 */
.form{display:none}
.form.active{display:block;animation:formIn 0.35s ease}
@keyframes formIn{from{opacity:0;transform:translateX(8px)}to{opacity:1;transform:translateX(0)}}

.input-group{margin-bottom:18px}
.input-group label{display:block;font-size:13px;font-weight:600;color:var(--text);margin-bottom:7px;padding-left:2px}
.input-wrap{position:relative}
.input-wrap i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#C4BBAF;font-size:15px}
.input-wrap input{width:100%;padding:13px 14px 13px 40px;border:1.5px solid var(--border);border-radius:14px;font-size:15px;background:var(--input-bg);color:var(--text);transition:all 0.2s}
.input-wrap input:focus{outline:none;border-color:var(--primary);background:#fff;box-shadow:0 0 0 4px rgba(212,168,75,0.08)}
.input-wrap input::placeholder{color:#C4BBAF}

.submit-btn{width:100%;padding:14px;border:none;border-radius:14px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;font-size:16px;font-weight:700;cursor:pointer;transition:all 0.2s;box-shadow:0 4px 16px rgba(212,168,75,0.25);margin-top:6px}
.submit-btn:active{transform:scale(0.98);box-shadow:0 2px 8px rgba(212,168,75,0.15)}

/* 分割线 */
.divider{display:flex;align-items:center;margin:24px 0}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--border)}
.divider span{padding:0 14px;font-size:12px;color:#C4BBAF;font-weight:500}

/* 微信登录 */
.wx-btn{width:100%;padding:13px;border:1.5px solid var(--border);border-radius:14px;background:#fff;display:flex;align-items:center;justify-content:center;gap:8px;font-size:15px;font-weight:600;color:var(--text);cursor:pointer;transition:all 0.2s}
.wx-btn i{color:#07C160;font-size:20px}
.wx-btn:active{transform:scale(0.98);background:#F6FFF9;border-color:#07C160}

/* 验证码按钮 */
.code-wrap{display:flex;gap:8px}
.code-wrap input{flex:1}
.code-btn{flex-shrink:0;padding:0 14px;border:1.5px solid var(--border);border-radius:10px;background:#fff;color:var(--primary);font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;transition:all 0.2s}
.code-btn:hover{border-color:var(--primary);background:var(--input-bg)}
.code-btn:disabled{color:var(--text-light);cursor:not-allowed;border-color:var(--border)}

/* 表单切换链接 */
.form-switch{text-align:center;margin-top:16px;font-size:13px;color:var(--text-light)}
.form-switch a{color:var(--primary);text-decoration:none;font-weight:600}
.form-switch a:hover{text-decoration:underline}

/* 底部 */
.card-footer{text-align:center;padding:16px 28px 20px;border-top:1px solid var(--border)}
.card-footer p{font-size:12px;color:#C4BBAF}

@media (max-width:400px){
.card-body{padding:24px 20px 20px}
.logo-icon{width:56px;height:56px;font-size:24px;border-radius:16px}
.logo-area h1{font-size:20px}
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


<div class="card">
<div class="card-top"></div>
<div class="card-body">

<div class="logo-area">
<div class="logo-icon"><i class="fas fa-gamepad"></i></div>
<h1>欢迎回来</h1>
<p>登录您的账号继续游戏之旅</p>
</div>

<?php if(!empty($error)):?>
<div class="msg msg-error"><i class="fas fa-circle-exclamation"></i> <?php echo htmlspecialchars($error);?></div>
<?php endif;?>
<?php if(!empty($success)):?>
<div class="msg msg-success"><i class="fas fa-circle-check"></i> <?php echo htmlspecialchars($success);?></div>
<?php endif;?>

<!-- 标签切换 -->
<?php
$login_tabs = [];
if (!empty($login_config['account'])) $login_tabs[] = ['key' => 'account', 'name' => '账号登录'];
if (!empty($login_config['phone'])) $login_tabs[] = ['key' => 'phone', 'name' => '手机登录'];
$show_tabs = count($login_tabs) > 1;
?>
<?php if ($show_tabs): ?>
<div class="tab-bar" id="tabBar">
<?php foreach ($login_tabs as $idx => $tab): ?>
<button class="tab-item <?php echo $idx === 0 ? 'active' : ''; ?>" data-tab="<?php echo $tab['key']; ?>"><?php echo $tab['name']; ?></button>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- 账号登录表单 -->
<?php if (!empty($login_config['account'])): ?>
<div class="form <?php echo $show_tabs ? ($login_tabs[0]['key'] === 'account' ? 'active' : '') : 'active'; ?>" id="form-account">
<form method="post">
<div class="input-group">
<label>用户名</label>
<div class="input-wrap">
<i class="fas fa-user"></i>
<input type="text" name="username" placeholder="请输入用户名" required>
</div>
</div>
<div class="input-group">
<label>密码</label>
<div class="input-wrap">
<i class="fas fa-lock"></i>
<input type="password" name="password" placeholder="请输入密码" required>
</div>
</div>
<button type="submit" name="login" class="submit-btn">立即登录</button>
</form>
<div class="form-switch">
还没有账号？<a href="javascript:;" onclick="switchToRegister('account')">立即注册</a>
</div>
</div>
<?php endif; ?>

<!-- 手机号登录表单 -->
<?php if (!empty($login_config['phone'])): ?>
<div class="form <?php echo $show_tabs ? ($login_tabs[0]['key'] === 'phone' ? 'active' : '') : ''; ?>" id="form-phone">
<form method="post">
<div class="input-group">
<label>手机号</label>
<div class="input-wrap">
<i class="fas fa-mobile-alt"></i>
<input type="tel" name="phone" placeholder="请输入手机号" maxlength="11" required>
</div>
</div>
<div class="input-group">
<label>验证码</label>
<div class="input-wrap code-wrap">
<i class="fas fa-shield-alt"></i>
<input type="text" name="code" placeholder="请输入验证码" maxlength="6" required>
<button type="button" class="code-btn" onclick="sendCode(this)">获取验证码</button>
</div>
</div>
<button type="submit" name="phone_login" class="submit-btn">立即登录</button>
</form>
<div class="form-switch">
还没有账号？<a href="javascript:;" onclick="switchToRegister('phone')">手机号注册</a>
</div>
</div>
<?php endif; ?>

<!-- 账号注册表单 -->
<?php if (!empty($login_config['account'])): ?>
<div class="form" id="form-account-register">
<form method="post">
<div class="input-group">
<label>用户名</label>
<div class="input-wrap">
<i class="fas fa-user"></i>
<input type="text" name="username" placeholder="3-20位字符" required>
</div>
</div>
<div class="input-group">
<label>密码</label>
<div class="input-wrap">
<i class="fas fa-lock"></i>
<input type="password" name="password" placeholder="至少6位字符" required>
</div>
</div>
<div class="input-group">
<label>确认密码</label>
<div class="input-wrap">
<i class="fas fa-shield-halved"></i>
<input type="password" name="repwd" placeholder="再次输入密码" required>
</div>
</div>
<button type="submit" name="register" class="submit-btn">立即注册</button>
</form>
<div class="form-switch">
已有账号？<a href="javascript:;" onclick="switchToLogin('account')">返回登录</a>
</div>
</div>
<?php endif; ?>

<!-- 手机号注册表单 -->
<?php if (!empty($login_config['phone'])): ?>
<div class="form" id="form-phone-register">
<form method="post">
<div class="input-group">
<label>手机号</label>
<div class="input-wrap">
<i class="fas fa-mobile-alt"></i>
<input type="tel" name="phone" placeholder="请输入手机号" maxlength="11" required>
</div>
</div>
<div class="input-group">
<label>验证码</label>
<div class="input-wrap code-wrap">
<i class="fas fa-shield-alt"></i>
<input type="text" name="code" placeholder="请输入验证码" maxlength="6" required>
<button type="button" class="code-btn" onclick="sendCode(this)">获取验证码</button>
</div>
</div>
<div class="input-group">
<label>设置密码</label>
<div class="input-wrap">
<i class="fas fa-lock"></i>
<input type="password" name="password" placeholder="至少6位字符" required>
</div>
</div>
<button type="submit" name="phone_register" class="submit-btn">立即注册</button>
</form>
<div class="form-switch">
已有账号？<a href="javascript:;" onclick="switchToLogin('phone')">返回登录</a>
</div>
</div>
<?php endif; ?>

<!-- 微信登录 -->
<?php if (!empty($login_config['wechat'])): ?>
<div class="divider"><span>其他方式</span></div>
<button class="wx-btn" onclick="wechatLogin()">
<i class="fab fa-weixin"></i>
<span>微信一键登录</span>
</button>
<?php endif; ?>

</div>

<div class="card-footer">
<p>登录即表示您同意相关服务条款</p>
</div>
</div>

<script>
const tabBar = document.getElementById('tabBar');
const tabs = document.querySelectorAll('.tab-item');

// 标签切换（账号/手机登录）
if (tabs.length > 0) {
    tabs.forEach(tab=>{
        tab.addEventListener('click',function(){
            const target = this.dataset.tab;
            tabs.forEach(t=>t.classList.remove('active'));
            this.classList.add('active');
            // 隐藏所有表单
            document.querySelectorAll('.form').forEach(f=>f.classList.remove('active'));
            // 显示对应登录表单
            const loginForm = document.getElementById('form-'+target);
            if (loginForm) loginForm.classList.add('active');
        });
    });
}

// 切换到注册
function switchToRegister(type) {
    document.querySelectorAll('.form').forEach(f=>f.classList.remove('active'));
    const regForm = document.getElementById('form-'+type+'-register');
    if (regForm) regForm.classList.add('active');
}

// 切换到登录
function switchToLogin(type) {
    document.querySelectorAll('.form').forEach(f=>f.classList.remove('active'));
    const loginForm = document.getElementById('form-'+type);
    if (loginForm) loginForm.classList.add('active');
}

// 发送验证码
function sendCode(btn) {
    const phoneInput = btn.closest('form').querySelector('input[name="phone"]');
    if (!phoneInput || !/^1[3-9]\d{9}$/.test(phoneInput.value)) {
        alert('请输入正确的手机号');
        return;
    }
    let countdown = 60;
    btn.disabled = true;
    btn.textContent = countdown + 's后重发';
    const timer = setInterval(()=>{
        countdown--;
        btn.textContent = countdown + 's后重发';
        if (countdown <= 0) {
            clearInterval(timer);
            btn.disabled = false;
            btn.textContent = '获取验证码';
        }
    }, 1000);
    // 这里可以调用发送验证码的API
    alert('验证码已发送（演示）');
}

function wechatLogin(){window.location.href='login.php?action=get_login_url&type=wx';}
</script>

    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
