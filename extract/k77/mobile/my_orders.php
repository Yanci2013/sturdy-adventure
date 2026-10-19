<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require __DIR__ . '/header.php';

$user_id = get_user_user_id() ?? 0;
$user = getUserById($pdo, $user_id);
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 获取自己的订单
$orders = getHunterOrders($pdo, $user_id, $status, $search);

// 获取被共享的订单
$sharedOrders = [];
try {
    $sharedSql = "SELECT o.*, os.owner_user_id as shared_from_user_id, u.username as shared_from_username
                  FROM order_shares os
                  JOIN orders o ON os.order_id = o.id
                  LEFT JOIN users u ON os.owner_user_id = u.id
                  WHERE os.shared_user_id = ?";
    $sharedParams = [$user_id];

    if ($status !== 'all') {
        if ($status == '2') {
            $sharedSql .= " AND o.order_status IN (2, 5)";
        } elseif ($status == '3') {
            $sharedSql .= " AND o.order_status = 8";
        } elseif ($status == '4') {
            $sharedSql .= " AND o.order_status = 4";
        }
    }

    if ($search) {
        $sharedSql .= " AND (o.order_no LIKE ? OR o.product_name LIKE ?)";
        $searchPattern = "%{$search}%";
        $sharedParams[] = $searchPattern;
        $sharedParams[] = $searchPattern;
    }

    $sharedSql .= " ORDER BY o.id DESC";
    $sharedStmt = $pdo->prepare($sharedSql);
    $sharedStmt->execute($sharedParams);
    $sharedOrders = $sharedStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($sharedOrders as &$sharedOrder) {
        $sharedOrder['is_shared'] = true;
    }
    unset($sharedOrder);

} catch (PDOException $e) {
    error_log("获取共享订单失败: " . $e->getMessage());
}

// 获取自己共享出去的订单列表
$sharedOutOrders = [];
try {
    $sharedOutSql = "SELECT os.order_id, GROUP_CONCAT(u.username) as shared_to_users, COUNT(*) as share_count
                      FROM order_shares os
                      LEFT JOIN users u ON os.shared_user_id = u.id
                      WHERE os.owner_user_id = ?
                      GROUP BY os.order_id";
    $sharedOutStmt = $pdo->prepare($sharedOutSql);
    $sharedOutStmt->execute([$user_id]);
    $sharedOutRows = $sharedOutStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($sharedOutRows as $row) {
        $sharedOutOrders[$row['order_id']] = [
            'count' => $row['share_count'],
            'users' => $row['shared_to_users']
        ];
    }
} catch (PDOException $e) {
    error_log("获取共享出去订单失败: " . $e->getMessage());
}

// 合并去重排序
$allOrders = array_merge($orders, $sharedOrders);
$seen = [];
$allOrders = array_filter($allOrders, function($order) use (&$seen) {
    if (isset($seen[$order['id']])) return false;
    $seen[$order['id']] = true;
    return true;
});
usort($allOrders, function($a, $b) {
    $timeA = strtotime($a['accept_time'] ?? $a['id']);
    $timeB = strtotime($b['accept_time'] ?? $b['id']);
    return $timeB - $timeA;
});
$sharedCount = count($sharedOrders);

// 开始服务提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_service'])) {
    $order_id = intval($_POST['order_id']);
    $stmt = $pdo->prepare("UPDATE orders SET order_status = 2, service_time = NOW() WHERE id = ? AND hunter_user_id = ? AND (order_status = 1 OR service_time IS NULL)");
    try {
        $pdo->beginTransaction();
        $stmt->execute([$order_id, $user_id]);
        $affectedRows = $stmt->rowCount();
        $pdo->commit();
        $_SESSION['success'] = $affectedRows > 0 ? "订单服务已开始！" : "订单状态不符合要求";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "操作失败: " . $e->getMessage();
    }
    header("Location: my_orders.php?status=".$status . ($search ? "&search=" . urlencode($search) : ""));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order']) && isset($_POST['order_id'])) {
    $order_id = $_POST['order_id'];
    $notes = $_POST['notes'];
    $error = '';
    if (empty($error)) {
        header("Location: my_orders.php?status=".$status . ($search ? "&search=" . urlencode($search) : ""));
        exit();
    }
    $_SESSION['error'] = $error;
    $_SESSION['form_data'] = ['order_id' => $order_id, 'notes' => $notes];
    header("Location: my_orders.php?status=".$status . ($search ? "&search=" . urlencode($search) : ""));
    exit();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>我的订单</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
:root{
    --main:#d49329;
    --main-light:#fff5e3;
    --bg:#f9f7f2;
    --white:#ffffff;
    --text1:#222222;
    --text2:#666666;
    --text3:#999999;
    --green:#39b54a;
    --purple:#9358d8;
    --blue:#3483eb;
    --border:#e9e6dd;
}
body{background:var(--bg);font-family:PingFang SC,Microsoft YaHei,sans-serif;padding-bottom:100px;}

/*顶部固定导航 */
.top-nav{
    position:sticky;top:0;background:var(--white);padding:18px 16px;display:flex;justify-content:space-between;align-items:center;
    border-bottom:1px solid var(--border);z-index:99;
}
.top-nav h2{font-size:20px;color:var(--text1);font-weight:500;}
.top-user{font-size:13px;color:var(--text2);display:flex;align-items:center;gap:6px;}

/*标签分类栏 */
.tab-bar{background:var(--white);padding:14px 16px 16px;border-bottom:1px solid var(--border);display:flex;gap:12px;overflow-x:auto;}
.tab-bar::-webkit-scrollbar{height:0;}
.tab-item{
    padding:8px 20px;border-radius:99px;border:1px solid var(--border);background:var(--bg);color:var(--text2);
    font-size:14px;text-decoration:none;white-space:nowrap;transition:0.2s;
}
.tab-item.active{background:var(--main);color:#fff;border-color:var(--main);}

/*订单容器 */
.order-wrap{padding:16px;}
.order-card{
    background:var(--white);border-radius:20px;padding:20px;margin-bottom:16px;border:1px solid var(--border);
}
/*订单头部：单号+状态 */
.card-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;}
.orderno{font-size:13px;color:var(--text3);}
.tag-status{padding:5px 14px;border-radius:99px;font-size:13px;}
.tag-serve{background:#e6f9e9;color:var(--green);}
.tag-wait{background:#f4ecff;color:var(--purple);}
.tag-finish{background:#eaf3ff;color:var(--blue);}

/*商品名称价格左右布局 */
.good-row{display:flex;justify-content:space-between;margin-bottom:12px;}
.good-name{font-size:16px;font-weight:500;color:var(--text1);}
.good-price{font-size:24px;color:var(--main);font-weight:bold;}

/*详情信息区块 */
.info-box{background:var(--bg);border-radius:14px;padding:12px;margin-bottom:14px;}
.info-item{display:flex;justify-content:space-between;font-size:13px;color:var(--text2);padding:4px 0;}

/*底部按钮 */
.btn-box{display:flex;justify-content:flex-end;gap:10px;}
.btn-see{padding:7px 20px;background:var(--main);color:#fff;border:none;border-radius:99px;font-size:13px;cursor:pointer;}
.btn-start{padding:7px 20px;background:var(--green);color:#fff;border:none;border-radius:99px;font-size:13px;cursor:pointer;}
.btn-disable{padding:7px 20px;background:#eee;color:#999;border:none;border-radius:99px;font-size:13px;}

/*提示 */
.alert{margin:12px 16px;padding:12px;border-radius:12px;font-size:14px;}
.alert-success{background:#e6f9e9;color:var(--green);}
.alert-error{background:#ffe9e9;color:#e64340;}
.empty{text-align:center;padding:80px 20px;color:var(--text3);font-size:15px;}
.share-tag{font-size:11px;padding:2px 7px;border-radius:6px;color:#fff;margin-left:6px;}
.share-in{background:var(--purple);}
.share-out{background:var(--main);}
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

<div class="top-nav">
    <h2>我的订单
        <?php if($sharedCount||count($sharedOutOrders)):?>
        <span style="font-size:11px;">
            <?php if($sharedCount):?><span class="share-tag share-in">收<?=$sharedCount?></span><?php endif;?>
            <?php if(count($sharedOutOrders)):?><span class="share-tag share-out">发<?=count($sharedOutOrders)?></span><?php endif;?>
        </span>
        <?php endif;?>
    </h2>
    <div class="top-user"><i class="fas fa-user"></i> <?=htmlspecialchars($user['username']??'未登录')?></div>
</div>

<div class="tab-bar">
    <a href="my_orders.php?status=all<?=$search?'&search='.urlencode($search):''?>" class="tab-item <?=$status=='all'?'active':''?>">全部(<?=count(getHunterOrders($pdo,$user_id,'all',$search))?>)</a>
    <a href="my_orders.php?status=2<?=$search?'&search='.urlencode($search):''?>" class="tab-item <?=$status=='2'?'active':''?>">服务中(<?=count(getHunterOrders($pdo,$user_id,'2',$search))?>)</a>
    <a href="my_orders.php?status=3<?=$search?'&search='.urlencode($search):''?>" class="tab-item <?=$status=='3'?'active':''?>">待结算(<?=count(getHunterOrders($pdo,$user_id,'3',$search))?>)</a>
    <a href="my_orders.php?status=4<?=$search?'&search='.urlencode($search):''?>" class="tab-item <?=$status=='4'?'active':''?>">已完成(<?=count(getHunterOrders($pdo,$user_id,'4',$search))?>)</a>
</div>

<?php if(isset($_SESSION['success'])):?>
<div class="alert alert-success"><?=$_SESSION['success'];unset($_SESSION['success'])?></div>
<?php endif;?>
<?php if(isset($_SESSION['error'])):?>
<div class="alert alert-error"><?=$_SESSION['error'];unset($_SESSION['error'])?></div>
<?php endif;?>

<div class="order-wrap">
<?php if(empty($allOrders)):?>
    <div class="empty">📦 <?=$search?'无搜索结果':'暂无订单'?></div>
<?php else:foreach($allOrders as $order):
$statusMap=[
    2=>['cls'=>'tag-serve','txt'=>'服务中'],
    3=>['cls'=>'tag-wait','txt'=>'待结算'],
    4=>['cls'=>'tag-finish','txt'=>'已完成'],
    5=>['cls'=>'tag-wait','txt'=>'售后中'],
    8=>['cls'=>'tag-wait','txt'=>'待客户结单']
];
$st=$statusMap[$order['order_status']]??['cls'=>'tag-serve','txt'=>'进行中'];
$isShare=!empty($order['is_shared']);
$isShareOut=isset($sharedOutOrders[$order['id']]);
$inField=json_decode($order['input_fields']??'{}',true);
$cname=$inField['中文ID']??$inField['chinese_id']??'';
$cid=$inField['数字ID']??$inField['numeric_id']??'';
$remark=$inField['备注']??$inField['remark']??'';
$region=$order['game_region']??'';
$cate=$order['category_name']??'未分类';
?>
<div class="order-card">
    <div class="card-head">
        <span class="orderno">订单：<?=htmlspecialchars($order['order_no'])?>
        <?php if($isShare):?><span class="share-tag share-in">共享给我</span><?php endif;?>
        <?php if($isShareOut):?><span class="share-tag share-out">已共享</span><?php endif;?>
        </span>
        <span class="tag-status <?=$st['cls']?>"><?=$st['txt']?></span>
    </div>

    <div class="good-row">
        <div class="good-name"><?=htmlspecialchars($order['product_name'])?></div>
        <div class="good-price">¥<?=number_format($order['price'],2)?></div>
    </div>

    <div class="info-box">
        <?php if($cname||$cid):?>
        <div class="info-item"><span>老板名称</span><span><?=$cname?$cname:$cid?></span></div>
        <?php endif;?>
        <?php if($remark):?>
        <div class="info-item"><span>备注</span><span><?=htmlspecialchars($remark)?></span></div>
        <?php endif;?>
        <div class="info-item"><span>大区</span><span><?=htmlspecialchars($region)?></span></div>
        <div class="info-item"><span>分类</span><span><?=htmlspecialchars($cate)?></span></div>
        <div class="info-item"><span>接/开/完</span><span>
        <?=!empty($order['accept_time'])?date('m-d H:i',strtotime($order['accept_time'])):'--'?> |
        <?=!empty($order['service_time'])?date('m-d H:i',strtotime($order['service_time'])):'--'?> |
        <?=!empty($order['complete_time'])?date('m-d H:i',strtotime($order['complete_time'])):'--'?>
        </span></div>
    </div>

    <div class="btn-box">
        <a href="order_detail.php?id=<?=$order['id']?><?=$search?'&search='.urlencode($search):''?>"><button class="btn-see">查看详情</button></a>
        <?php if(!$isShare):?>
            <?php if($order['order_status']==1||($order['order_status']==2&&empty($order['service_time']))):?>
            <form method="post" style="display:inline">
                <input type="hidden" name="order_id" value="<?=$order['id']?>">
                <input type="hidden" name="status" value="<?=$status?>">
                <?=$search?'<input type="hidden" name="search" value="'.urlencode($search).'">':''?>
                <button type="submit" name="start_service" class="btn-start">开始服务</button>
            </form>
            <?php endif;?>
        <?php else:?>
        <span class="btn-disable">仅可查看</span>
        <?php endif;?>
    </div>
</div>
<?php endforeach;endif;?>
</div>

<?php require __DIR__ . '/footer.php';?>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>