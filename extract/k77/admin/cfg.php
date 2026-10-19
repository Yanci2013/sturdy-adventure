<?php
// 引入公共文件
$common_file = '../includes/common.php';
if (!file_exists($common_file)) {
    die("错误：公共文件不存在：".$common_file);
}
include_once $common_file;

$msg = '';
$msgType = '';

// 保存配置
if (isset($_POST['saveAll'])) {
    $updateList = [
        ['home_bg', trim($_POST['home_bg'] ?? '')],
        ['show_home_bg', intval($_POST['show_home_bg'] ?? 0)],
        ['home_notice', trim($_POST['home_notice'] ?? '')],
        ['show_home_notice', intval($_POST['show_home_notice'] ?? 0)],
        ['show_qrcode', intval($_POST['show_qrcode'] ?? 0)],
        ['show_game', intval($_POST['show_game'] ?? 0)],
        ['show_nickname', intval($_POST['show_nickname'] ?? 0)],
        ['show_phone', intval($_POST['show_phone'] ?? 0)],
        ['show_password', intval($_POST['show_password'] ?? 0)],
        ['show_sex', intval($_POST['show_sex'] ?? 0)],
        ['show_age', intval($_POST['show_age'] ?? 0)],
        ['show_wx', intval($_POST['show_wx'] ?? 0)],
        ['show_id_front', intval($_POST['show_id_front'] ?? 0)],
        ['show_id_back', intval($_POST['show_id_back'] ?? 0)],
        ['notice_text', trim($_POST['notice_text'] ?? '')],
        ['game_list', trim($_POST['game_list'] ?? '')]
    ];
    try {
        foreach ($updateList as $item) {
            $sql = "UPDATE daren_register_config SET cfg_val=? WHERE cfg_key=?";
            $stmt = $DB->prepare($sql);
            $stmt->execute([$item[1], $item[0]]);
        }
        $msg = '全部配置保存成功';
        $msgType = 'success';
    } catch (Exception $e) {
        $msg = '保存失败：'.$e->getMessage();
        $msgType = 'danger';
    }
}

// 上传二维码
$qrUpload = '../uploads/daren_qr/';
if (!is_dir($qrUpload)) mkdir($qrUpload, 0777, true);
if ($_FILES['qr_file']['error'] === 0) {
    $ext = strtolower(pathinfo($_FILES['name'], PATHINFO_EXTENSION));
    $allow = ['jpg','png','jpeg','webp'];
    if (!in_array($ext, $allow)) {
        $msg = '仅支持图片';$msgType='danger';
    } elseif ($_FILES['qr_file']['size'] > 3*1024*1024) {
        $msg = '图片不能超过3M';$msgType='danger';
    } else {
        $fname = date('YmdHis').'.'.$ext;
        move_uploaded_file($_FILES['qr_file']['tmp_name'], $qrUpload.$fname);
        $savePath = 'uploads/daren_qr/'.$fname;
        $DB->exec("UPDATE daren_register_config SET cfg_val='$savePath' WHERE cfg_key='qrcode_img'");
        $msg = '二维码上传成功';$msgType='success';
    }
}

// 上传主页背景
$bgUpload = '../uploads/home_bg/';
if (!is_dir($bgUpload)) mkdir($bgUpload, 0777, true);
if ($_FILES['bg_file']['error'] === 0) {
    $ext = strtolower(pathinfo($_FILES['name'], PATHINFO_EXTENSION));
    $allow = ['jpg','png','jpeg','webp'];
    if (!in_array($ext, $allow)) {
        $msg = '仅支持图片';$msgType='danger';
    } elseif ($_FILES['bg_file']['size'] > 5*1024*1024) {
        $msg = '背景图不能超过5M';$msgType='danger';
    } else {
        $fname = date('YmdHis').'.'.$ext;
        move_uploaded_file($_FILES['bg_file']['tmp_name'], $bgUpload.$fname);
        $savePath = 'uploads/home_bg/'.$fname;
        $DB->exec("UPDATE daren_register_config SET cfg_val='$savePath' WHERE cfg_key='home_bg'");
        $msg = '主页背景上传成功';$msgType='success';
    }
}

// 删除二维码
if (isset($_GET['delQr'])) {
    $row = $DB->query("SELECT cfg_val FROM daren_register_config WHERE cfg_key='qrcode_img'")->fetch();
    if (!empty($row['cfg_val']) && file_exists('../'.$row['cfg_val'])) {
        unlink('../'.$row['cfg_val']);
    }
    $DB->exec("UPDATE daren_register_config SET cfg_val='' WHERE cfg_key='qrcode_img'");
    header("Location: cfg.php");exit;
}

// 删除背景图
if (isset($_GET['delBg'])) {
    $row = $DB->query("SELECT cfg_val FROM daren_register_config WHERE cfg_key='home_bg'")->fetch();
    if (!empty($row['cfg_val']) && file_exists('../'.$row['cfg_val'])) {
        unlink('../'.$row['cfg_val']);
    }
    $DB->exec("UPDATE daren_register_config SET cfg_val='' WHERE cfg_key='home_bg'");
    header("Location: cfg.php");exit;
}

// 读取所有配置
$cfgData = [];
$res = $DB->query("SELECT cfg_key,cfg_val FROM daren_register_config")->fetchAll();
foreach ($res as $row) {
    $cfgData[$row['cfg_key']] = $row['cfg_val'];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>打手全局配置</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:Microsoft YaHei;}
body{background:#f5f7fa;padding:24px;}
h2{margin-bottom:20px;color:#111;}
.card{background:#fff;padding:22px;border-radius:12px;margin-bottom:24px;box-shadow:0 2px 10px #00000008;}
h3{margin-bottom:16px;color:#222;border-bottom:1px #eee;padding-bottom:8px;}
.alert{padding:10px 14px;border-radius:8px;margin-bottom:16px;}
.success{background:#e6f9ed;color:#198754;}
.danger{background:#ffe9e9;color:#dc3545;}
.row{display:flex;align-items:center;margin-bottom:14px;gap:10px;}
.row label{width:260px;}
input[type="checkbox"]{width:16px;height:16px;}
textarea,input[type="text"]{width:100%;padding:10px;border:1px #ddd solid;border-radius:8px;margin:8px 0;}
img{max-width:260px;border:1px #eee solid;border-radius:8px;margin:10px 0;}
button{padding:10px 20px;border:none;border-radius:8px;cursor:pointer;font-weight:bold;}
.save{background:#ffd700;color:#000;}
.del{background:#dc3545;color:#fff;text-decoration:none;display:inline-block;padding:8px 14px;border-radius:6px;font-size:13px;margin-top:8px;}
</style>
  <link href="./res/admin-white.css" rel="stylesheet">
</head>
<body>
<h2>打手入驻+个人主页全局配置</h2>
<?php if($msg): ?>
<div class="alert <?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="card">
    <h3>打手入驻客服二维码</h3>
    <form method="post" enctype="multipart/form-data">
        <div class="row">
            <label>是否展示二维码</label>
            <input type="checkbox" name="show_qrcode" <?= $cfgData['show_qrcode']==1?'checked':'' ?>> 开启
        </div>
        <div class="row">
            <label>上传二维码</label>
            <input type="file" name="qr_file" accept="image/*">
        </div>
        <?php if(!empty($cfgData['qrcode_img'])): ?>
        <div>
            <img src="<?= $cfgData['qrcode_img'] ?>"><br>
            <a href="?delQr=1" class="del" onclick="return confirm('删除？')">删除图片</a>
        </div>
        <?php else: ?>
        <p>暂无图片</p>
        <?php endif; ?>
        <button type="submit" name="saveAll" class="save">保存全部设置</button>
    </form>
</div>

<div class="card">
    <h3>打手个人主页设置</h3>
    <form method="post" enctype="multipart/form-data">
        <div class="row">
            <label>启用主页背景图</label>
            <input type="checkbox" name="show_home_bg" <?= $cfgData['show_home_bg']==1?'checked':'' ?>> 开启
        </div>
        <div class="row">
            <label>上传背景图</label>
            <input type="file" name="bg_file" accept="image/*">
        </div>
        <?php if(!empty($cfgData['home_bg'])): ?>
        <div>
            <img src="<?= $cfgData['home_bg'] ?>"><br>
            <a href="?delBg=1" class="del" onclick="return confirm('删除？')">删除背景</a>
        </div>
        <?php endif; ?>
        <div class="row">
            <label>显示主页公告</label>
            <input type="checkbox" name="show_home_notice" <?= $cfgData['show_home_notice']==1?'checked':'' ?>> 开启
        </div>
        <label>主页公告文字</label>
        <textarea name="home_notice" rows="4"><?= htmlspecialchars($cfgData['home_notice']) ?></textarea>
        <button type="submit" name="saveAll" class="save">保存全部设置</button>
    </form>
</div>

<div class="card">
    <h3>入驻页面表单开关</h3>
    <form method="post">
        <div class="row"><label>游戏选择</label><input type="checkbox" name="show_game" <?= $cfgData['show_game']==1?'checked':'' ?>></div>
        <div class="row"><label>昵称</label><input type="checkbox" name="show_nickname" <?= $cfgData['show_nickname']==1?'checked':'' ?>></div>
        <div class="row"><label>手机号</label><input type="checkbox" name="show_phone" <?= $cfgData['show_phone']==1?'checked':'' ?>></div>
        <div class="row"><label>密码</label><input type="checkbox" name="show_password" <?= $cfgData['show_password']==1 ?>></div>
        <div class="row"><label>性别</label><input type="checkbox" name="show_sex" <?= $cfgData['show_sex']==1 ?>></div>
        <div class="row"><label>年龄</label><input type="checkbox" name="show_age" <?= $cfgData['show_age']==1 ?>></div>
        <div class="row"><label>微信号</label><input type="checkbox" name="show_wx" <?= $cfgData['show_wx']==1 ?>></div>
        <div class="row"><label>身份证正面</label><input type="checkbox" name="show_id_front" <?= $cfgData['show_id_front'] ?>></div>
        <div class="row"><label>身份证反面</label><input type="checkbox" name="show_id_back" <?= $cfgData['show_id_back'] ?>></div>
        <label>入驻公告</label>
        <textarea name="notice_text" rows="3"><?= htmlspecialchars($cfgData['notice_text']) ?></textarea>
        <label>游戏列表(逗号分隔)</label>
        <input type="text" name="game_list" value="<?= htmlspecialchars($cfgData['game_list']) ?>">
        <button type="submit" name="saveAll" class="save">保存全部设置</button>
    </form>
</div>
</body>
</html>