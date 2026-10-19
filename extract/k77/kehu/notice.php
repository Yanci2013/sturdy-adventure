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
    die('数据库连接失败');
}

// 兼容登录函数
if(!function_exists('is_h5_logged_in')){
    function is_h5_logged_in(){
        return !empty($_SESSION['h5_user_id']);
    }
}
if(!function_exists('get_h5_user_id')){
    function get_h5_user_id(){
        return $_SESSION['h5_user_id'] ?? 0;
    }
}

// 未登录跳转
if(!is_h5_logged_in()) {
    header("Location: login.php");
    exit();
}
$user_id = get_h5_user_id();

// ===================== 差额领取佣金逻辑 =====================
if(isset($_POST['receive_commission'])){
    $invitee_uid = intval($_POST['invitee_uid']);

    // 1. 获取下级当前全部累计已支付金额
    $stmt_pay = $db->prepare("SELECT SUM(payment_amount) as total_pay FROM orders WHERE user_id = ? AND payment_status = 'paid'");
    $stmt_pay->execute([$invitee_uid]);
    $pay_data = $stmt_pay->fetch();
    $total_pay = floatval($pay_data['total_pay'] ?? 0);
    $total_all_commission = $total_pay * 0.1;

    // 2. 查询之前已经领取过的总佣金
    $stmt_receive = $db->prepare("SELECT receive_total_money FROM user_invite_receive WHERE inviter_uid = ? AND invitee_uid = ?");
    $stmt_receive->execute([$user_id,$invitee_uid]);
    $receive_data = $stmt_receive->fetch();
    $old_receive = floatval($receive_data['receive_total_money'] ?? 0);

    // 3. 计算本次可领取差额佣金
    $now_commission = $total_all_commission - $old_receive;

    if($now_commission <= 0){
        echo "<script>alert('暂无新增可领取佣金');history.go(-1);</script>";
        exit;
    }

    // 4. 更新领取记录，累加已领取总额
    if($receive_data){
        // 已有记录，更新总额
        $stmt_update = $db->prepare("
            UPDATE user_invite_receive 
            SET total_pay_money = ?, receive_total_money = ?, receive_time = NOW()
            WHERE inviter_uid = ? AND invitee_uid = ?
        ");
        $stmt_update->execute([$total_pay, $total_all_commission, $user_id, $invitee_uid]);
    }else{
        // 首次领取，新增记录
        $stmt_insert = $db->prepare("
            INSERT INTO user_invite_receive 
            (inviter_uid,invitee_uid,total_pay_money,receive_money,receive_total_money,receive_time) 
            VALUES (?,?,?,?,?,NOW())
        ");
        $stmt_insert->execute([$user_id,$invitee_uid,$total_pay,$now_commission,$total_all_commission]);
    }

    // 5. 余额增加本次差额佣金
    $stmt_up = $db->prepare("UPDATE h5user SET balance = balance + ? WHERE id = ?");
    $stmt_up->execute([$now_commission,$user_id]);

    echo "<script>alert('领取成功！本次领取¥".number_format($now_commission,2)."');history.go(-1);</script>";
    exit;
}

// ===================== 获取当前用户信息 =====================
$stmt = $db->prepare("SELECT invite_code,nickname,balance FROM h5user WHERE id = ? LIMIT 1");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$invite_code = $user['invite_code'];
$now_balance = $user['balance'];

// 生成邀请链接
$invite_link = "https://ysxt.xiaoxiakjwl.com/kehu/register_invite.php?invite=".$invite_code;

// 统计总邀请人数
$stmt_count = $db->prepare("SELECT COUNT(*) as total FROM h5user WHERE invite_uid = ?");
$stmt_count->execute([$user_id]);
$invite_total = $stmt_count->fetch()['total'];

// 统计全局所有可领取总收益
$stmt_all_earn = $db->prepare("
    SELECT (SUM(o.payment_amount)*0.1) - IFNULL(SUM(r.receive_total_money),0) as total_earn
    FROM h5user u
    LEFT JOIN orders o ON u.id = o.user_id AND o.payment_status = 'paid'
    LEFT JOIN user_invite_receive r ON u.id = r.invitee_uid AND r.inviter_uid = ?
    WHERE u.invite_uid = ?
");
$stmt_all_earn->execute([$user_id,$user_id]);
$total_earn = floatval($stmt_all_earn->fetch()['total_earn'] ?? 0);

// ===================== 下级列表：计算剩余可领取差额 =====================
$stmt_invite_list = $db->prepare("
    SELECT 
        u.id,
        u.username,
        SUM(o.payment_amount) as invitee_total_pay,
        IFNULL(r.receive_total_money,0) as already_receive_money
    FROM h5user u
    LEFT JOIN orders o ON u.id = o.user_id AND o.payment_status = 'paid'
    LEFT JOIN user_invite_receive r ON u.id = r.invitee_uid AND r.inviter_uid = ?
    WHERE u.invite_uid = ?
    GROUP BY u.id
");
$stmt_invite_list->execute([$user_id,$user_id]);
$invite_list = $stmt_invite_list->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>邀请好友</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{background:#f5f7fa;font-family:system-ui;padding:15px;padding-bottom:100px;}
        .page-title{font-size:26px;font-weight:bold;text-align:center;margin:20px 0;color:#222;}
        .card{background:#fff;border-radius:16px;padding:20px;margin-bottom:15px;box-shadow:0 2px 10px rgba(0,0,0,0.05);}
        .info-item{display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid #eee;}
        .info-item:last-child{border:none;}
        .info-label{color:#666;font-size:15px;}
        .info-value{font-weight:bold;color:#222;font-size:16px;}
        .link-box{background:#f5f5f5;border-radius:12px;padding:15px;margin:20px 0;word-break:break-all;}
        .invite-link{color:#4096ff;font-size:14px;}
        .copy-btn{width:100%;background:#d4a84b;color:#fff;border:none;border-radius:12px;padding:14px;font-size:16px;font-weight:bold;margin-top:10px;}
        .tip-text{text-align:center;color:#999;font-size:13px;margin-top:15px;line-height:1.6;}

        .list-title{font-size:18px;font-weight:bold;margin:20px 0 10px;color:#222;}
        .list-item{display:flex;justify-content:space-between;align-items:center;padding:15px;border-bottom:1px solid #eee;}
        .list-item:last-child{border:none;}
        .list-left{flex:1;}
        .list-name{font-weight:bold;color:#222;font-size:15px;}
        .list-pay{color:#e63946;font-weight:bold;margin:8px 0;}
        .list-can-get{color:#28a745;font-size:13px;margin-top:4px;}
        .receive-btn{padding:8px 16px;border-radius:8px;border:none;background:#d4a84b;color:#fff;font-size:14px;}
        .no-get-btn{padding:8px 16px;border-radius:8px;border:none;background:#ccc;color:#fff;font-size:14px;}
        .empty-tip{text-align:center;color:#999;padding:30px 0;}
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

    <div class="page-title">邀请好友</div>

    <div class="card">
        <div class="info-item">
            <span class="info-label">我的邀请人数</span>
            <span class="info-value"><?php echo $invite_total; ?> 人</span>
        </div>
        <div class="info-item">
            <span class="info-label">累计可领取收益</span>
            <span class="info-value">¥<?php echo number_format($total_earn,2); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">当前账户余额</span>
            <span class="info-value">¥<?php echo number_format($now_balance,2); ?></span>
        </div>
    </div>

    <div class="card">
        <p style="color:#333;font-weight:bold;margin-bottom:10px;font-size:18px;">我的专属邀请链接</p>
        <div class="link-box">
            <span class="invite-link" id="inviteLink"><?php echo $invite_link; ?></span>
        </div>
        <button class="copy-btn" onclick="copyLink()">复制邀请链接</button>
    </div>

    <div class="list-title">我的邀请好友列表</div>
    <div class="card">
        <?php if(empty($invite_list)): ?>
            <div class="empty-tip">暂无邀请好友</div>
        <?php else: ?>
            <?php foreach($invite_list as $item): ?>
            <?php
                $total_commission = $item['invitee_total_pay'] * 0.1;
                $can_get = $total_commission - $item['already_receive_money'];
            ?>
            <div class="list-item">
                <div class="list-left">
                    <div class="list-name">用户名：<?php echo htmlspecialchars($item['username']); ?></div>
                    <div class="list-pay">累计下单金额：¥<?php echo number_format($item['invitee_total_pay'] ?? 0,2); ?></div>
                    <div class="list-can-get">可领取差额佣金：¥<?php echo number_format($can_get,2); ?></div>
                </div>
                <div class="list-right">
                    <?php if($can_get > 0): ?>
                        <form method="post" style="margin:0;">
                            <input type="hidden" name="invitee_uid" value="<?php echo $item['id']; ?>">
                            <button type="submit" name="receive_commission" class="receive-btn">领取佣金</button>
                        </form>
                    <?php else: ?>
                        <button class="no-get-btn">暂无收益</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="tip-text">
        好友下单金额实时更新<br>
        仅可领取**新增下单差额佣金**，不会重复领取旧收益<br>
        领取后余额实时到账
    </div>

<?php include 'footer.php'; ?>
<script>
function copyLink(){
    const link = document.getElementById('inviteLink').innerText;
    navigator.clipboard.writeText(link).then(()=>{
        alert('复制成功！');
    }).catch(()=>{
        alert('复制失败，请手动复制');
    })
}
</script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>