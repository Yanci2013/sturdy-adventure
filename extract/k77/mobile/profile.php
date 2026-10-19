<?php
// 引入头部文件
include 'header.php';
$error = '';
$success = '';

// ========== 初始化变量，防止未定义导致 JS 语法错误 ==========
$userData = [
    'username' => '',
    'balance' => '0.00',
    'deposit' => '0.00',
    'user_role' => 0,
    'avatar' => null,
    'available_for_orders' => 0
];
$stats = [
    'balance' => '0.00',
    'deposit' => '0.00',
    'order_all' => '0',
    'order_service' => '0',
    'order_settle' => '0',
    'order_finish' => '0'
];
$is_available = 0;
$currentRole = $currentRole ?? 0;

// 退出登录
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    clear_user_session();
    header("Location: login.php");
    exit();
}

// 头像上传提交处理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_avatar') {
    try {
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('请选择要上传的头像文件');
        }
        $uploadDir = '../assets/images/userds/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
        $fileInfo = pathinfo($_FILES['avatar']['name']);
        $extension = strtolower($fileInfo['extension']);
        $allowedExtensions = ['jpg','jpeg','png','gif'];
        if (!in_array($extension, $allowedExtensions)) throw new Exception('仅支持 JPG/PNG/GIF 格式');
        $newFileName = 'avatar_' . $user_id . '_' . time() . '.' . $extension;
        $uploadPath = $uploadDir . $newFileName;
        if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadPath)) throw new Exception('文件上传失败');
        $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        $stmt->execute([$newFileName, $user_id]);
        $success = '头像修改成功！';
        header('Refresh:0;url=profile.php');
        exit;
    } catch (Exception $e) {
        $error = '头像上传失败: ' . $e->getMessage();
    }
}

// 修改昵称后端
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_name') {
    try {
        $newName = trim($_POST['username'] ?? '');
        if (empty($newName)) throw new Exception('昵称不能为空');
        if (mb_strlen($newName) > 20) throw new Exception('昵称不能超过20个字符');
        $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
        $stmt->execute([$newName, $user_id]);
        $success = '昵称修改成功！';
        header('Refresh:0;url=profile.php');
        exit;
    } catch (Exception $e) {
        $error = '修改失败：' . $e->getMessage();
    }
}

// 接单开关提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_available') {
    try {
        $newStatus = $_POST['available'] === 'on' ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE users SET available_for_orders = ? WHERE id = ?");
        $stmt->execute([$newStatus, $user_id]);
        $success = '接单状态切换成功！';
        header('Refresh:0;url=profile.php');
        exit;
    } catch (Exception $e) {
        $error = '状态修改失败：'.$e->getMessage();
    }
}

define('AVATAR_DIR', '../assets/images/userds/');
define('DEFAULT_AVATAR', 'default_avatar.png');

// ========== 头部模糊背景图 读取后台配置表 ==========
$banner_bg_image = "https://p3-flow-image-sign.byteimg.com/ocean-cloud-tos/9a004030839341389d41424210022311~tplv-a9rns2rl98-image.image";
try {
    $bgStmt = $pdo->query("SELECT banner_bg FROM site_config LIMIT 1");
    $bgRow = $bgStmt->fetch(PDO::FETCH_ASSOC);
    if (!empty($bgRow['banner_bg'])) {
        $banner_bg_image = $bgRow['banner_bg'];
    }
}catch(Exception $ee){}

try {
    $stmt = $pdo->prepare("SELECT id, username, balance, deposit, user_role, avatar, available_for_orders FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$userData) {
        $userData = [
            'username' => '',
            'balance' => '0.00',
            'deposit' => '0.00',
            'user_role' => 0,
            'avatar' => null,
            'available_for_orders' => 0
        ];
    }
    $stmt = $pdo->prepare("SELECT COUNT(id) FROM orders WHERE hunter_user_id=?");
    $stmt->execute([$user_id]);
    $stats['order_all'] = $stmt->fetchColumn() ?:0;
    $stmt = $pdo->prepare("SELECT COUNT(id) FROM orders WHERE hunter_user_id=? AND order_status=2");
    $stmt->execute([$user_id]);
    $stats['order_service'] = $stmt->fetchColumn() ?:0;
    $stmt = $pdo->prepare("SELECT COUNT(id) FROM orders WHERE hunter_user_id=? AND order_status=3");
    $stmt->execute([$user_id]);
    $stats['order_settle'] = $stmt->fetchColumn() ?:0;
    $stmt = $pdo->prepare("SELECT COUNT(id) FROM orders WHERE hunter_user_id=? AND order_status=4");
    $stmt->execute([$user_id]);
    $stats['order_finish'] = $stmt->fetchColumn() ?:0;
    $stats['balance'] = number_format((float)$userData['balance'],2,'.','');
    $stats['deposit'] = number_format((float)$userData['deposit'],2,'.','');
    $userData['avatar'] = $userData['avatar'] ?? DEFAULT_AVATAR;
    if(!file_exists(AVATAR_DIR.$userData['avatar'])) $userData['avatar'] = DEFAULT_AVATAR;
} catch (Exception $e) {
    $error = '数据加载异常：'.$e->getMessage();
}
$is_available = intval($userData['available_for_orders'] ?? 0);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>打手中心</title>
<link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:"PingFang SC",Microsoft YaHei,system-ui,sans-serif;}
body{background:#ffffff;padding-bottom:40px;font-size:14px;}
.wrap{max-width:720px;margin:0 auto;padding:0 14px;}
.msg-tip{padding:12px 16px;border-radius:14px;margin:12px 0;font-size:14px;}
.success-msg{background:#dcf7e7;color:#089451;}
.err-msg{background:#ffe9e9;color:#d63031;}
.nav-bar{display:flex;align-items:center;height:50px;position:relative;}
.nav-back{font-size:22px;color:#000;text-decoration:none;}
.nav-title{position:absolute;left:50%;transform:translateX(-50%);font-size:19px;font-weight:500;color:#000;}
.user-banner{height:140px;border-radius:16px;position:relative;overflow:hidden;margin-bottom:16px;display:flex;align-items:center;padding:0 16px;}
.banner-bg{position:absolute;left:0;top:0;width:100%;height:100%;background:url("<?=htmlspecialchars($banner_bg_image)?>") center/cover no-repeat;filter: blur(6px);transform: scale(1.1);z-index:1;}
.banner-mask{position:absolute;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.35);z-index:2;}
.banner-content{position:relative;z-index:3;display:flex;align-items:center;width:100%;}
.user-avatar-box{width:76px;height:76px;border-radius:50%;overflow:hidden;border:2px solid #fff;position:relative;cursor:pointer;flex-shrink:0;}
.user-avatar-box img{width:100%;height:100%;object-fit:cover;}
#avatarUploadBox{position:absolute;width:100%;height:100%;opacity:0;left:0;top:0;cursor:pointer;}
.user-info{margin-left:14px;color:#fff;flex:1;min-width:0;}
.uname{font-size:22px;font-weight:600;cursor:pointer;display:inline-block;border-bottom: 1px solid rgba(255,255,255,0.3);}
.uid{font-size:13px;margin-top:6px;opacity:0.9;}
.banner-btn-group{margin-top:8px;display:flex;gap:8px;}
.banner-btn{position:absolute;right:16px;top:50%;transform:translateY(-50%);z-index:4;}
.yellow-capsule{background:#ffdd00;border:1px solid #222;border-radius:99px;padding:6px 16px;font-size:14px;font-weight:500;color:#000;cursor:pointer;transition: transform 0.1s, opacity 0.2s;}
.yellow-capsule:active{transform: scale(0.96);opacity:0.85;}
.card{background:#fff;border:1px solid #e6e6e6;border-radius:16px;padding:20px;margin-bottom:16px;}
.card-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;}
.card-title{font-size:17px;font-weight:500;color:#000;}
.grid-2row-3col{display:grid;grid-template-columns:repeat(3,1fr);gap:16px 8px;}
.grid-item{text-align:center;}
.grid-num{font-size:26px;font-weight:bold;color:#000;}
.grid-label{font-size:12px;color:#757575;margin-top:4px;}
.radio-group{display:grid;grid-template-columns:repeat(3,1fr);gap:16px 0;}
.radio-item-last{grid-column:1/2;}
.radio-label{display:flex;align-items:center;gap:8px;cursor:pointer;}
.radio-input{appearance:none;width:20px;height:20px;border:2px solid #c8c8c8;border-radius:50%;position:relative;}
.radio-input:checked{border-color:#0968E8;}
.radio-input:checked::after{content:"";position:absolute;left:50%;top:50%;width:10px;height:10px;background:#0968E8;border-radius:50%;transform:translate(-50%,-50%);}
.grid-1row-3col{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;}
.more-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:24px 6px;}
.more-item{display:flex;flex-direction:column;align-items:center;gap:9px;text-decoration:none;color:#333;transition: transform 0.1s;}
.more-item:active{transform: scale(0.95);}
.more-icon{width:48px;height:48px;border-radius:15px;background:#f6f6f6;display:flex;align-items:center;justify-content:center;font-size:21px;}
.more-label{font-size:13px;}
.notice-text{font-size:14px;color:#222;line-height:1.6;}
.notice-red{color:#d80000;}
.copyright-box{text-align:center;margin-top:25px;}
.copyright-top{font-size:26px;font-weight:bold;letter-spacing:3px;color:#111;}
.copyright-middle{font-size:13px;color:#555;margin:6px 0;}
.copyright-bottom{display:inline-block;padding:6px 22px;border:1px solid #f26522;color:#f26522;border-radius:9px;font-size:13px;cursor:pointer;transition: all 0.2s;}
.copyright-bottom:active{background:#f26522;color:#fff;}
.pop-mask{position:fixed;top:0;left:0;right:0;bottom:0;background:#00000055;display:none;z-index:999;align-items:center;justify-content:center;}
.pop-box{width:82%;max-width:360px;background:#fff;border-radius:20px;padding:26px;}
.pop-title{text-align:center;font-size:18px;font-weight:550;margin-bottom:22px;}
.pop-item{padding:15px 0;text-align:center;border-bottom:1px #eee solid;font-size:16px;cursor:pointer;transition: background 0.15s;}
.pop-item:last-child{border:none;}
.pop-item:active{background:#f5f5f5;}
.pop-close{margin-top:18px;width:100%;padding:13px;border-radius:15px;border:0;background:#eee;font-size:15px;cursor:pointer;transition: background 0.15s;}
.pop-close:active{background:#ddd;}
.pop-close.gray{background:#f4f4f4;margin-top:10px;}
.pop-close.gray:active{background:#e0e0e0;}
.hide-form{display:none;}
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

<div class="wrap">
    <div class="nav-bar">
        <a href="javascript:history.back()" class="nav-back"><i class="fas fa-chevron-left"></i></a>
        <div class="nav-title">打手中心</div>
    </div>
    <?php if($success):?>
    <div class="msg-tip success-msg"><?=$success?></div>
    <?php endif;?>
    <?php if($error):?>
    <div class="msg-tip err-msg"><?=$error?></div>
    <?php endif;?>
    <form id="avatarForm" method="post" enctype="multipart/form-data" class="hide-form">
        <input type="hidden" name="action" value="upload_avatar">
    </form>
    <form id="nameForm" method="post" class="hide-form">
        <input type="hidden" name="action" value="update_name">
        <input type="hidden" id="nameVal" name="username">
    </form>
    <form id="statusForm" method="post" class="hide-form">
        <input type="hidden" name="action" value="toggle_available">
        <input type="hidden" id="availVal" name="available">
    </form>
    <div class="user-banner">
        <div class="banner-bg"></div>
        <div class="banner-mask"></div>
        <div class="banner-content">
            <div class="user-avatar-box">
                <img src="<?=htmlspecialchars(AVATAR_DIR.$userData['avatar'])?>" onerror="this.src='../assets/images/user.png'">
                <input type="file" id="avatarUploadBox" name="avatar" accept="image/*" form="avatarForm">
            </div>
            <div class="user-info">
                <div class="uname" onclick="openNamePopup()"><?=htmlspecialchars($userData['username'])?></div>
                <div class="uid">用户ID：<?=$user_id?></div>
                <div class="banner-btn-group">
                    <button type="button" class="yellow-capsule" onclick="switchStatus()"><?=$is_available?'接单中':'休息中'?></button>
                </div>
            </div>
            <div class="banner-btn">
                <button type="button" class="yellow-capsule" onclick="location.href='rank.php'">排行榜</button>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><div class="card-title">我的接单</div></div>
        <div class="grid-2row-3col">
            <div class="grid-item"><div class="grid-num"><?=$stats['order_all']?></div><div class="grid-label">全部</div></div>
            <div class="grid-item"><div class="grid-num"><?=$stats['order_service']?></div><div class="grid-label">服务中</div></div>
            <div class="grid-item"><div class="grid-num"><?=$stats['order_settle']?></div><div class="grid-label">待结单</div></div>
            <div class="grid-item"><div class="grid-num"><?=$stats['order_finish']?></div><div class="grid-label">已完成</div></div>
            <div class="grid-item"><div class="grid-num">0</div><div class="grid-label">售后中</div></div>
            <div class="grid-item"><div class="grid-num">0</div><div class="grid-label">已退款</div></div>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><div class="card-title">接单游戏</div></div>
        <div class="radio-group">
            <label class="radio-label"><input type="radio" name="game_type" class="radio-input" checked><span>地铁专区</span></label>
            <label class="radio-label"><input type="radio" name="game_type" class="radio-input"><span>装备专区</span></label>
            <label class="radio-label"><input type="radio" name="game_type" class="radio-input"><span>女陪专区</span></label>
            <label class="radio-label radio-item-last"><input type="radio" name="game_type" class="radio-input"><span>考核入驻</span></label>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><div class="card-title">接单大厅</div><button type="button" class="yellow-capsule" onclick="location.href='index.php'">去接单</button></div>
        <div class="grid-2row-3col">
            <div class="grid-item"><div class="grid-num"><?=$stats['order_all']?></div><div class="grid-label">全部单</div></div>
            <div class="grid-item"><div class="grid-num">0</div><div class="grid-label">微信区</div></div>
            <div class="grid-item"><div class="grid-num">0</div><div class="grid-label">QQ区</div></div>
            <div class="grid-item"><div class="grid-num">0</div><div class="grid-label">指定单</div></div>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><div class="card-title">我的资金</div><button type="button" class="yellow-capsule" onclick="location.href='bzj/index.php'">充值保证金</button></div>
        <div class="grid-1row-3col">
            <div class="grid-item"><div class="grid-num"><?=$stats['deposit']?></div><div class="grid-label">保证金</div></div>
            <div class="grid-item"><div class="grid-num"><?=$stats['balance']?></div><div class="grid-label">可用余额</div></div>
            <div class="grid-item"><div class="grid-num">0.00</div><div class="grid-label">冻结余额</div></div>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><div class="card-title">更多功能</div></div>
        <div class="more-grid">
            <a href="zjmx.php?type=commission" class="more-item"><div class="more-icon"><i class="fas fa-file-invoice-dollar"></i></div><span class="more-label">佣金记录</span></a>
            <a href="withdrawal_apply.php" class="more-item"><div class="more-icon"><i class="fas fa-cash-register"></i></div><span class="more-label">提现记录</span></a>
            <a href="withdrawal.php" class="more-item"><div class="more-icon"><i class="fas fa-piggy-bank"></i></div><span class="more-label">我的钱包</span></a>
            <a href="bzj/index.php" class="more-item"><div class="more-icon"><i class="fas fa-shield-alt"></i></div><span class="more-label">保证金</span></a>
            <a href="purchase_member.php" class="more-item"><div class="more-icon"><i class="fas fa-gem"></i></div><span class="more-label">开通会员</span></a>
            <a href="penalty_records.php" class="more-item"><div class="more-icon"><i class="fas fa-ban"></i></div><span class="more-label">罚款记录</span></a>
            <a href="member_center.php" class="more-item"><div class="more-icon"><i class="fas fa-id-card"></i></div><span class="more-label">会员中心</span></a>
            <?php if ($currentRole == 2): ?>
            <a href="my_hunters.php" class="more-item"><div class="more-icon"><i class="fas fa-user-friends"></i></div><span class="more-label">我的下级</span></a>
            <?php endif; ?>
            <a href="javascript:void(0)" class="more-item" onclick="openSelectPop(); return false;"><div class="more-icon"><i class="fas fa-exchange-alt"></i></div><span class="more-label">切换身份</span></a>
            <a href="profile.php?action=logout" class="more-item" onclick="return confirm('确定退出登录？');"><div class="more-icon"><i class="fas fa-sign-out-alt"></i></div><span class="more-label">退出登录</span></a>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><div class="card-title">公告内容</div></div>
        <div class="notice-text">
            1. 打手接单请遵守平台规则，禁止私下转账、线下交易；<br>
            2. 服务超时、态度恶劣将扣除保证金并罚款；<br>
            <span class="notice-red">3. 所有提现申请T+1处理，节假日顺延。</span>
        </div>
    </div>
    <div class="copyright-box">
        <div class="copyright-top">小熠网络</div>
        <div class="copyright-middle">-广州小熠科技网络提供技术服务-</div>
        <div class="copyright-bottom" onclick="alert('小熠网络')">做俱乐部就找小熠网络 ＞</div>
    </div>
    <div class="pop-mask" id="selectPop">
        <div class="pop-box">
            <div class="pop-title">选择身份</div>
            <div class="pop-item" onclick="location.href='../kehu/index.php'">老板</div>
            <div class="pop-item" onclick="location.href='index.php'">打手</div>
            <div class="pop-item" onclick="location.href='../mobilesj/index.php'">商家</div>
            <button type="button" class="pop-close" onclick="closeSelectPop()">关闭</button>
        </div>
    </div>
    <div class="pop-mask" id="namePopup">
        <div class="pop-box">
            <div class="pop-title">修改昵称</div>
            <div style="margin-bottom:16px;">
                <input id="newNickInput" style="width:100%;padding:11px 12px;border:1px #ddd solid;border-radius:12px;font-size:15px;" maxlength="20" value="<?=htmlspecialchars($userData['username'])?>" placeholder="填写新昵称">
            </div>
            <button type="button" class="pop-close" onclick="saveNick()">确认修改</button>
            <button type="button" class="pop-close gray" onclick="closeNamePopup()">取消</button>
        </div>
    </div>
</div>
<?php require __DIR__.'/footer.php';?>
<script>
document.getElementById('avatarUploadBox').onchange = function(){
    document.getElementById('avatarForm').submit();
};
function switchStatus(){
    let now = <?=intval($is_available)?>;
    document.getElementById('availVal').value = now ? '' : 'on';
    document.getElementById('statusForm').submit();
}
const nameMask = document.getElementById('namePopup');
function openNamePopup(){nameMask.style.display='flex';}
function closeNamePopup(){nameMask.style.display='none';}
nameMask.onclick = e=>{if(e.target === nameMask) closeNamePopup();}
function saveNick(){
    let nick = document.getElementById('newNickInput').value.trim();
    if(!nick){alert('昵称不能为空');return;}
    document.getElementById('nameVal').value = nick;
    document.getElementById('nameForm').submit();
}
const selMask = document.getElementById('selectPop');
function openSelectPop(){selMask.style.display='flex';}
function closeSelectPop(){selMask.style.display='none';}
selMask.onclick = e=>{if(e.target===selMask)closeSelectPop();}
</script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
