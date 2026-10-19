<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 引入头部文件（确保已包含 PDO 连接）
require __DIR__ . '/header.php';

// 获取用户信息
$user_id = $_SESSION['user_id'];
$user = getUserById($pdo, $user_id);

// 从数据库获取appid和appkey
$settings = $pdo->query("SELECT appid, appkey FROM settings LIMIT 1")->fetch();
$appid = $settings['appid'] ?? '';
$appkey = $settings['appkey'] ?? '';

// Step3: 处理回调，获取Authorization Code
if(isset($_GET['type']) && $_GET['type'] == 'wx' && isset($_GET['code'])) {
    $code = trim($_GET['code']);
    
    // Step4: 通过Authorization Code获取用户信息
    $api_url = "https://login.acloudn.com/connect.php?act=callback&appid={$appid}&appkey={$appkey}&type=wx&code={$code}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if($response === false) {
        die(json_encode(['code' => -1, 'msg' => '获取用户信息失败']));
    }
    
    $user_info = json_decode($response, true);
    
    if(!$user_info || $user_info['code'] != 0) {
        die(json_encode(['code' => -1, 'msg' => '获取用户信息失败: '.($user_info['msg'] ?? '')]));
    }
    
    // 保存用户信息到数据库
    $openid = $user_info['social_uid'] ?? '';
    if(empty($openid)) {
        die(json_encode(['code' => -1, 'msg' => '未获取到有效的OpenID']));
    }
    
    // 检查该openid是否已被其他账号使用
    $stmt = $pdo->prepare("SELECT id FROM users WHERE wechat_openid = ? AND id != ?");
    $stmt->execute([$openid, $user_id]);
    if($stmt->rowCount() > 0) {
        die(json_encode(['code' => -1, 'msg' => '该微信账号已绑定其他用户']));
    }
    
    // 更新当前用户的wechat_openid字段（仅更新此字段）
    $stmt = $pdo->prepare("UPDATE users SET wechat_openid = ? WHERE id = ?");
    if($stmt->execute([$openid, $user_id])) {
        // 更新session中的用户信息
        $user = getUserById($pdo, $user_id);
        echo "<script>alert('微信绑定成功');location.href='profile_edit.php';</script>";
        exit;
    }
    
    die(json_encode(['code' => -1, 'msg' => '微信绑定失败']));
}

// Step1: 获取微信登录URL
$redirect_uri = urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?type=wx');
$wechat_auth_api = "https://auths.acloudn.com/connect.php?act=login&appid={$appid}&appkey={$appkey}&type=wx&redirect_uri={$redirect_uri}";

// Step2: 获取登录地址
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $wechat_auth_api);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
curl_close($ch);

if($response === false) {
    die(json_encode(['code' => -1, 'msg' => '无法连接微信认证服务']));
}

$auth_data = json_decode($response, true);

if(!$auth_data || $auth_data['code'] != 0) {
    die(json_encode(['code' => -1, 'msg' => '获取微信登录URL失败: '.($auth_data['msg'] ?? '')]));
}

$target_url = $auth_data['url'] ?? '';
if(empty($target_url)) {
    die(json_encode(['code' => -1, 'msg' => '未获取到有效的登录URL']));
}

// 使用草料API生成二维码
$qrcode_api_url = "https://api.2dcode.biz/v1/create-qr-code?data=" . urlencode($target_url);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>微信OpenID绑定</title>
    <link rel="stylesheet" href="../assets/layui/css/layui.css">
    <style>
        body {
            padding: 20px;
            text-align: center;
            font-family: "Microsoft YaHei", sans-serif;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        .qrcode-container {
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        #qrcodeImg {
            width: 200px;
            height: 200px;
            margin: 0 auto;
            display: block;
            border: 1px solid #eee;
        }
        .api-info {
            margin: 20px 0;
            padding: 15px;
            background: #f0f9ff;
            border: 1px solid #d0e3ff;
            border-radius: 5px;
            text-align: left;
            word-break: break-all;
        }
        .status-info {
            margin: 15px 0;
            color: #666;
        }
        .json-key {
            color: #1e88e5;
            font-weight: bold;
        }
        .json-value {
            color: #43a047;
        }
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0,0,0,.3);
            border-radius: 50%;
            border-top-color: #1E9FFF;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>微信OpenID绑定</h2>
        
        <div class="api-info">
            <h3>API信息：</h3>
            <p><span class="json-key">微信认证API:</span> <span class="json-value"><?php echo htmlspecialchars($wechat_auth_api); ?></span></p>
            <p><span class="json-key">获取到的登录URL:</span> <span class="json-value"><?php echo htmlspecialchars($target_url); ?></span></p>
            <p><span class="json-key">回调地址:</span> <span class="json-value"><?php echo htmlspecialchars($redirect_uri); ?></span></p>
        </div>
        
        <div class="qrcode-container">
            <p>请使用微信扫描下方二维码</p>
            <div id="qrcodeLoading" class="loading" style="margin:20px auto;"></div>
            <img id="qrcodeImg" src="<?php echo htmlspecialchars($qrcode_api_url); ?>" 
                 style="display:none;" 
                 onload="document.getElementById('qrcodeLoading').style.display='none';this.style.display='block'" 
                 onerror="handleQrcodeError()">
            <div class="status-info" id="statusInfo">等待扫码中...</div>
        </div>
    </div>

    <script src="../assets/layui/layui.js"></script>
    <script>
        function handleQrcodeError() {
            document.getElementById('qrcodeLoading').style.display = 'none';
            document.getElementById('statusInfo').innerHTML = 
                '<span style="color:red">二维码加载失败，请<a href="javascript:location.reload()">刷新页面</a>重试</span>';
            console.error('二维码加载失败');
        }

        // 轮询检查绑定状态
        setInterval(function(){
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>?check_status=1')
                .then(response => response.json())
                .then(data => {
                    if(data.code === 0 && data.bound) {
                        document.getElementById('statusInfo').innerHTML = 
                            '<span style="color:green">绑定成功！即将跳转...</span>';
                        setTimeout(() => {
                            window.location.href = 'profile_edit.php';
                        }, 1000);
                    }
                })
                .catch(error => {
                    console.error('检查绑定状态失败:', error);
                });
        }, 2000);
    </script>
</body>
</html>