<?php
session_start();
require_once __DIR__.'/../config.php';
$adminDefaultPwd = 'admin123';

//管理员登录验证
if (!isset($_SESSION['admin_login'])) {
    if (isset($_POST['admin_login_submit'])) {
        $inputPwd = trim($_POST['admin_pwd']);
        if ($inputPwd === $adminDefaultPwd) {
            $_SESSION['admin_login'] = true;
        } else {
            $_SESSION['msg_tip'] = '管理员密码错误';
        }
    }
    //登录页
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>盲盒抽奖后台登录</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:"Microsoft YaHei",PingFang SC;}
body{background:#f0f2f5;height:100vh;display:flex;align-items:center;justify-content:center;}
.login-card{width:350px;background:#fff;padding:35px;border-radius:16px;box-shadow:0 2px 20px rgba(0,0,0,0.08);}
h2{text-align:center;margin-bottom:24px;color:#222;}
.form-item{margin-bottom:20px;}
input{width:100%;padding:13px 15px;border:1px #e5e7eb solid;border-radius:10px;font-size:15px;}
button{width:100%;padding:13px;background:#2563eb;color:#fff;border:none;border-radius:10px;font-size:15px;cursor:pointer;}
.error-tip{color:#ef4444;text-align:center;margin-bottom:12px;font-size:14px;}
</style>
</head>
<body>
<div class="login-card">
    <h2>盲盒抽奖系统后台</h2>
    <?php if(!empty($_SESSION['msg_tip'])){echo '<div class="error-tip">'.$_SESSION['msg_tip'].'</div>';unset($_SESSION['msg_tip']);}?>
    <form method="POST">
        <div class="form-item">
            <input type="password" name="admin_pwd" placeholder="输入管理员密码" required>
        </div>
        <button name="admin_login_submit">登录管理后台</button>
    </form>
</div>
</body>
</html>
<?php exit;
}

$alertMsg = '';
$uploadLotteryPath = '../assets/images/lottery/';
$uploadPrizePath = '../assets/images/prize/';
!is_dir($uploadLotteryPath) && mkdir($uploadLotteryPath,0755,true);
!is_dir($uploadPrizePath) && mkdir($uploadPrizePath,0755,true);
$allowImgExt = ['jpg','jpeg','png','gif','webp'];

//=====1、全局抽奖基础设置保存=====
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_base_setting'])){
    $lottery_enabled = isset($_POST['lottery_enabled']) ? 1 : 0;
    $title = trim($_POST['lottery_title']);
    $desc = trim($_POST['lottery_desc']);
    $link = trim($_POST['lottery_link']);
    $oldImg = $_POST['old_lottery_img'] ?? '';
    $saveImg = $oldImg;

    //上传封面图
    if(!empty($_FILES['lottery_img']['name'])){
        $ext = strtolower(pathinfo($_FILES['lottery_img']['name'],PATHINFO_EXTENSION));
        if(in_array($ext,$allowImgExt)){
            $newName = 'lot_'.time().'.'.$ext;
            $fullPath = $uploadLotteryPath.$newName;
            if(move_uploaded_file($_FILES['lottery_img']['tmp_name'],$fullPath)){
                @unlink('../'.$oldImg);
                $saveImg = 'assets/images/lottery/'.$newName;
            }
        }
    }
    $db->prepare("UPDATE lottery_settings SET lottery_enabled=?,title=?,description=?,jump_link=?,banner_img=? WHERE id=1")
        ->execute([$lottery_enabled,$title,$desc,$link,$saveImg]);
    $alertMsg = '基础配置保存成功';
}

//=====2、奖品新增/编辑保存=====
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prize'])){
    $pid = intval($_POST['prize_id']);
    $pName = trim($_POST['prize_name']);
    $pProb = floatval($_POST['probability']);
    $pSort = intval($_POST['sort_num']);
    $pStatus = isset($_POST['prize_status']) ? 1 : 0;
    $oldPrizeImg = $_POST['old_prize_img'] ?? '';
    $savePrizeImg = $oldPrizeImg;

    if(!empty($_FILES['prize_upload_img']['name'])){
        $ext = strtolower(pathinfo($_FILES['prize_upload_img']['name'],PATHINFO_EXTENSION));
        if(in_array($ext,$allowImgExt)){
            $newName = 'prize_'.time().'.'.$ext;
            $fullPath = $uploadPrizePath.$newName;
            if(move_uploaded_file($_FILES['prize_upload_img']['tmp_name'],$fullPath)){
                @unlink('../'.$oldPrizeImg);
                $savePrizeImg = 'assets/images/prize/'.$newName;
            }
        }
    }
    if($pid>0){
        //编辑奖品
        $db->prepare("UPDATE lottery_prizes SET prize_name=?,prize_img=?,probability=?,sort=?,status=? WHERE id=?")
            ->execute([$pName,$savePrizeImg,$pProb,$pSort,$pStatus,$pid]);
        $alertMsg = '奖品编辑成功';
    }else{
        //新增奖品
        $db->prepare("INSERT INTO lottery_prizes(prize_name,prize_img,probability,sort,status)VALUES(?,?,?,?,?)")
            ->execute([$pName,$savePrizeImg,$pProb,$pSort,$pStatus]);
        $alertMsg = '奖品新增成功';
    }
}

//=====3、删除奖品=====
if(isset($_GET['del_prize_id'])){
    $delId = intval($_GET['del_prize_id']);
    $delImg = $db->query("SELECT prize_img FROM lottery_prizes WHERE id=$delId")->fetchColumn();
    $db->exec("DELETE FROM lottery_prizes WHERE id=$delId");
    @unlink('../'.$delImg);
    $alertMsg = '奖品已删除';
}

//=====读取数据=====
$baseSet = $db->query("SELECT * FROM lottery_settings WHERE id=1")->fetch();
$prizeList = $db->query("SELECT * FROM lottery_prizes ORDER BY sort DESC,id DESC")->fetchAll();
$editPrizeData = [];
if(isset($_GET['edit_prize'])){
    $editPrizeData = $db->query("SELECT * FROM lottery_prizes WHERE id=".intval($_GET['edit_prize']))->fetch();
}
$totalAllProb = 0;
foreach($prizeList as $item)$totalAllProb += $item['probability'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0">
<title>盲盒抽奖后台管理</title>
<link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:"Microsoft YaHei",PingFang SC;}
body{background:#f5f7fa;padding:26px;}
.container{max-width:1150px;margin:0 auto;}
.top-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;}
.top-header h1{font-size:23px;color:#111;}
.logout-btn{padding:9px 16px;background:#ef4444;color:#fff;border:none;border-radius:9px;text-decoration:none;font-size:14px;}
.card-box{background:#fff;border-radius:15px;padding:24px;margin-bottom:22px;box-shadow:0 1px 12px rgba(0,0,0,0.05);}
.card-title{font-size:18px;margin-bottom:18px;font-weight:600;color:#222;border-left:4px solid #2563eb;padding-left:10px;}
.form-row{margin-bottom:19px;}
.form-row label{display:block;margin-bottom:7px;font-size:14px;color:#333;}
.form-input{width:100%;padding:12px 14px;border:1px #e2e8f0 solid;border-radius:9px;font-size:15px;}
.form-textarea{min-height:95px;resize:vertical;}
.btn{padding:11px 20px;border:none;border-radius:9px;cursor:pointer;font-size:14px;}
.btn-blue{background:#2563eb;color:#fff;}
.btn-black{background:#222;color:#fff;}
.btn-red{background:#ef4444;color:#fff;}
.alert-success{padding:13px;background:#dcfce7;color:#15803d;border-radius:9px;margin-bottom:18px;}
.img-preview{width:52px;height:52px;border-radius:7px;object-fit:cover;margin-top:8px;}
table{width:100%;border-collapse:collapse;margin-top:10px;}
th,td{border-bottom:1px #eee solid;padding:13px;font-size:14px;text-align:left;}
th{background:#f8fafc;}
.operate-group{display:flex;gap:9px;}
.warn-tips{padding:11px;background:#fffbeb;border-radius:9px;margin-bottom:15px;color:#92400e;font-size:14px;}
@media(max-width:720px){body{padding:16px;}.card-box{padding:18px;}}
</style>
</head>
<body>
<div class="container">
    <div class="top-header">
        <h1><i class="fas fa-gift"></i> 盲盒抽奖一体化管理后台</h1>
        <a href="?logout=1" class="logout-btn" onclick="return confirm('确认退出登录？')"><i class="fas fa-sign-out-alt"></i> 退出</a>
        <?php if(isset($_GET['logout'])){session_destroy();header('Location:lottery_settings.php');exit;}?>
    </div>
    <?php if(!empty($alertMsg)){echo '<div class="alert-success"><i class="fas fa-check-circle"></i> '.$alertMsg.'</div>';}?>

    <!-- 基础全局配置区域 -->
    <div class="card-box">
        <div class="card-title">抽奖全局基础设置</div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="old_lottery_img" value="<?=htmlspecialchars($baseSet['banner_img']??'')?>">
            <div class="form-row">
                <label><input type="checkbox" name="lottery_enabled" <?=($baseSet['lottery_enabled']??0)==1?'checked':''?>> 启用前端盲盒抽奖功能</label>
            </div>
            <div class="form-row">
                <label>活动标题</label>
                <input class="form-input" name="lottery_title" value="<?=htmlspecialchars($baseSet['title']??'幸运盲盒抽奖活动')?>">
            </div>
            <div class="form-row">
                <label>活动简介描述</label>
                <textarea class="form-input form-textarea" name="lottery_desc"><?=htmlspecialchars($baseSet['description']??'海量奖品随机掉落，可自选大神打手陪玩开局')?></textarea>
            </div>
            <div class="form-row">
                <label>活动跳转链接</label>
                <input class="form-input" name="lottery_link" value="<?=htmlspecialchars($baseSet['jump_link']??'#')?>">
            </div>
            <div class="form-row">
                <label>首页顶部活动封面图</label>
                <input class="form-input" type="file" accept="image/*" name="lottery_img">
                <?php if(!empty($baseSet['banner_img'])):?>
                    <img src="../<?=$baseSet['banner_img']?>" class="img-preview">
                <?php endif;?>
            </div>
            <button name="save_base_setting" class="btn btn-black"><i class="fas fa-save"></i> 保存全局配置</button>
        </form>
    </div>

    <!-- 奖品新增/编辑表单 -->
    <div class="card-box">
        <div class="card-title"><?=empty($editPrizeData)?'新增抽奖奖品':'编辑现有奖品'?></div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="prize_id" value="<?=$editPrizeData['id']??0?>">
            <input type="hidden" name="old_prize_img" value="<?=htmlspecialchars($editPrizeData['prize_img']??'')?>">
            <div class="form-row">
                <label>奖品名称</label>
                <input class="form-input" name="prize_name" required value="<?=htmlspecialchars($editPrizeData['prize_name']??'')?>">
            </div>
            <div class="form-row">
                <label>中奖概率（%，仅后台可见，前端完全隐藏此字段，例：2.5=2.5%）</label>
                <input class="form-input" type="number" step="0.01" min="0.01" max="100" required name="probability" value="<?=$editPrizeData['probability']??1.00?>">
            </div>
            <div class="form-row">
                <label>前端排序权重（数值越大，奖品在奖品池列表越靠前）</label>
                <input class="form-input" type="number" name="sort_num" value="<?=$editPrizeData['sort']??0?>">
            </div>
            <div class="form-row">
                <label>奖品展示图片（可选，不上传使用默认图标）</label>
                <input class="form-input" type="file" accept="image/*" name="prize_upload_img">
                <?php if(!empty($editPrizeData['prize_img'])):?>
                    <img src="../<?=$editPrizeData['prize_img']?>" class="img-preview">
                <?php endif;?>
            </div>
            <div class="form-row">
                <label><input type="checkbox" name="prize_status" <?=($editPrizeData['status']??1)==1?'checked':''?>> 启用该奖品（禁用后抽奖无法开出）</label>
            </div>
            <div style="display:flex;gap:12px;">
                <button name="save_prize" class="btn btn-blue"><i class="fas fa-save"></i> 保存奖品信息</button>
                <?php if(!empty($editPrizeData)):?>
                    <a href="lottery_settings.php"><button type="button" class="btn btn-black">取消编辑</button></a>
                <?php endif;?>
            </div>
        </form>
    </div>

    <!-- 奖品列表 -->
    <div class="card-box">
        <div class="warn-tips">当前全部启用奖品概率合计：<b><?=$totalAllProb?>%</b>，建议总概率≤100%，概率字段仅后台查看，前端页面全程不展示</div>
        <div class="card-title">全部奖品列表</div>
        <table>
            <thead>
                <tr>
                    <th>奖品图片</th>
                    <th>奖品名称</th>
                    <th>后台配置中奖概率</th>
                    <th>排序权重</th>
                    <th>启用状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($prizeList as $item):?>
                <tr>
                    <td><?php if(!empty($item['prize_img'])){?><img src="../<?=$item['prize_img']?>" class="img-preview"><?php }?></td>
                    <td><?=htmlspecialchars($item['prize_name'])?></td>
                    <td><?=$item['probability']?> %</td>
                    <td><?=$item['sort']?></td>
                    <td><?=$item['status']?'<span style="color:#16a34a">已启用</span>':'<span style="color:#999">已禁用</span>'?></td>
                    <td class="operate-group">
                        <a href="?edit_prize=<?=$item['id']?>"><button class="btn btn-blue">编辑</button></a>
                        <a onclick="return confirm('确定删除奖品？同步删除服务器图片文件')" href="?del_prize_id=<?=$item['id']?>"><button class="btn btn-red">删除</button></a>
                    </td>
                </tr>
                <?php endforeach;if(empty($prizeList)):?>
                <tr><td colspan="6" align="center">暂无奖品，请在上方表单新增奖品</td></tr>
                <?php endif;?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>