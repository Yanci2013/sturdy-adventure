<?php
require '../includes/session_manager.php';
require '../config.php';

// 初始化数据库
try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
} catch (PDOException $e) {
    die('数据库异常');
}

// 兼容项目登录函数
if(!function_exists('set_h5_session')){
    function set_h5_session($uid,$userinfo){
        $_SESSION['h5_user_id'] = $uid;
        $_SESSION['h5_user_info'] = $userinfo;
    }
}

// 每次打开页面 / 点击刷新 都重新生成全新验证码，不会复用旧码
if (!isset($_SESSION['code']) || isset($_GET['refresh_code'])) {
    $code = rand(1000, 9999);
    $_SESSION['code'] = $code;
    // 刷新后跳转回注册页，去掉url参数
    if(isset($_GET['refresh_code'])){
        header("Location: register_invite.php?invite=".urlencode($_GET['invite'] ?? ''));
        exit();
    }
}

// 获取邀请码
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

$error = '';

// 注册提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $repwd = trim($_POST['repwd']);
    $verify_code = trim($_POST['verify_code']);

    // 验证码校验
    if (empty($verify_code)) {
        $error = '请填写验证码';
    } elseif ($verify_code != $_SESSION['code']) {
        $error = '验证码错误';
        // 验证码错误，立即换新验证码
        unset($_SESSION['code']);
    } elseif (empty($username) || empty($password) || empty($repwd)) {
        $error = '请填写完整信息';
    } elseif (strlen($username) < 3 || strlen($username) > 20) {
        $error = '用户名3-20位字符';
    } elseif ($password !== $repwd) {
        $error = '两次密码不一致';
    } elseif (strlen($password) < 6) {
        $error = '密码至少6位';
    } else {
        // 校验用户名是否重复
        $stmt = $db->prepare("SELECT id FROM h5user WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            $error = '用户名已被占用';
        } else {
            $pwd_hash = password_hash($password, PASSWORD_DEFAULT);
            $user_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            // 自增user_idu，杜绝重复主键报错
            $stmt_max = $db->query("SELECT MAX(user_idu) AS max_id FROM h5user");
            $max_res = $stmt_max->fetch();
            $new_idu = intval($max_res['max_id']) + 1;

            $user_invite_code = strtoupper(md5(uniqid(mt_rand(), true)));

            // 插入用户数据，绑定上级邀请人
            $stmt = $db->prepare("INSERT INTO h5user 
(username,password,nickname,user_idu,reg_ip,invite_code,invite_uid,created_at) 
VALUES (?,?,?,?,?,?,?,NOW())");
            $stmt->execute([
                $username,
                $pwd_hash,
                $username,
                $new_idu,
                $user_ip,
                $user_invite_code,
                $invite_uid
            ]);

            // 注册成功自动登录
            $new_uid = $db->lastInsertId();
            set_h5_session($new_uid, [
                'id' => $new_uid,
                'username' => $username
            ]);

            // 注册成功销毁验证码
            unset($_SESSION['code']);
            header("Location: my.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>受邀快速注册</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:system-ui;}
        body{background:#f5f7fa;padding:20px;min-height:100vh;display:flex;align-items:center;justify-content:center;}
        .box{width:100%;max-width:400px;background:#fff;border-radius:16px;padding:30px;box-shadow:0 2px 12px rgba(0,0,0,0.05);}
        .title{font-size:22px;font-weight:bold;text-align:center;margin-bottom:25px;color:#222;}
        .tip{text-align:center;color:#999;font-size:13px;margin-bottom:20px;}
        .item{margin-bottom:18px;}
        .item input{width:100%;padding:14px 16px;border:1px solid #eee;border-radius:12px;font-size:15px;}
        .code-row{display:flex;gap:10px;align-items:center;}
        .code-input{flex:1;}
        .code-box{
            padding:14px 16px;
            background:#222;
            color:#fff;
            letter-spacing:3px;
            font-size:18px;
            font-weight:bold;
            border-radius:12px;
            min-width:100px;
            text-align:center;
            cursor:pointer;
            user-select:none;
        }
        .code-box:hover{
            background:#444;
        }
        .btn{width:100%;padding:15px;background:#d4a84b;color:#fff;border:none;border-radius:12px;font-size:16px;font-weight:bold;}
        .err{color:#f53f3f;font-size:13px;margin-bottom:15px;text-align:center;}
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

    <div class="box">
        <div class="title">好友邀请注册</div>
        <div class="tip">点击验证码可刷新 | 每次打开链接验证码自动换新</div>

        <?php if (!empty($error)): ?>
            <div class="err"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="item">
                <input type="text" name="username" placeholder="请设置用户名" required>
            </div>
            <div class="item">
                <input type="password" name="password" placeholder="请设置密码" required>
            </div>
            <div class="item">
                <input type="password" name="repwd" placeholder="请确认密码" required>
            </div>
            <!-- 验证码区域：点击即可刷新 -->
            <div class="item code-row">
                <input type="text" name="verify_code" class="code-input" placeholder="请输入验证码" required>
                <div class="code-box" onclick="refreshCode()">
                    <?php echo $_SESSION['code']; ?>
                </div>
            </div>
            <button class="btn" type="submit">立即注册并登录</button>
        </form>
    </div>

<script>
// 点击刷新验证码，保留当前邀请参数
function refreshCode(){
    let invite = "<?php echo htmlspecialchars($invite_code); ?>";
    window.location.href = "register_invite.php?invite="+invite+"&refresh_code=1";
}
</script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>