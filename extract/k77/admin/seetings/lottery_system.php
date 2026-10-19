<?php
require_once __DIR__.'/../../includes/session_manager.php';
require_once __DIR__.'/../../includes/common.php';

if (!is_admin_logged_in()) {
    $_SESSION['error_msg'] = "请先登录管理员账号";
    header("Location: ../login.php");
    exit();
}

function checkTables($pdo) {
    try {
        // settings
        if ($pdo->query("SHOW TABLES LIKE 'lottery_settings'")->rowCount() == 0) {
            $pdo->exec("CREATE TABLE lottery_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                enabled TINYINT(1) DEFAULT 0,
                daily_limit INT DEFAULT 100,
                limit_message VARCHAR(255) DEFAULT '今日已上限',
                payment_amount DECIMAL(10,2) DEFAULT 0.10,
                show_winners TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("INSERT INTO lottery_settings (id, enabled, daily_limit, limit_message, payment_amount, show_winners) VALUES (1, 0, 100, '今日已上限', 0.10, 0)");
        }
        // prizes - 纯自定义，不关联商品
        if ($pdo->query("SHOW TABLES LIKE 'lottery_prizes'")->rowCount() == 0) {
            $pdo->exec("CREATE TABLE lottery_prizes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL COMMENT '奖品名称（自定义）',
                probability DECIMAL(10,2) DEFAULT 0.00 COMMENT '中奖概率%，仅后台可见',
                status TINYINT(1) DEFAULT 1 COMMENT '0禁用 1启用',
                sort_order INT DEFAULT 0 COMMENT '排序',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='大转盘奖品表（自定义）'");
        }
        // records
        if ($pdo->query("SHOW TABLES LIKE 'lottery_records'")->rowCount() == 0) {
            $pdo->exec("CREATE TABLE lottery_records (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                prize_id INT DEFAULT 0,
                prize_name VARCHAR(255) DEFAULT '',
                is_win TINYINT(1) DEFAULT 0,
                game_area VARCHAR(50) DEFAULT '',
                game_nickname VARCHAR(100) DEFAULT '',
                game_id VARCHAR(100) DEFAULT '',
                remark VARCHAR(500) DEFAULT '',
                payment_order_id VARCHAR(100) DEFAULT '',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        // winners
        if ($pdo->query("SHOW TABLES LIKE 'lottery_winners'")->rowCount() == 0) {
            $pdo->exec("CREATE TABLE lottery_winners (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                prize_id INT NOT NULL,
                prize_name VARCHAR(255) NOT NULL,
                order_id INT DEFAULT 0,
                record_id INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    } catch (Exception $e) {
        error_log('Check tables error: ' . $e->getMessage());
    }
}

checkTables($pdo);

// AJAX 中奖记录
if (isset($_GET['action']) && $_GET['action'] === 'get_winners') {
    $winners = $pdo->query("
        SELECT lw.*, u.username, o.order_no 
        FROM lottery_winners lw 
        LEFT JOIN users u ON lw.user_id = u.id 
        LEFT JOIN orders o ON lw.order_id = o.id
        ORDER BY lw.id DESC LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($winners)) {
        echo '<tr><td colspan="5" style="text-align:center;padding:40px;color:#999">暂无中奖记录</td></tr>';
    } else {
        foreach ($winners as $w) {
            $u = htmlspecialchars($w['username'] ?? '用户'.$w['user_id']);
            $t = date('Y-m-d H:i', strtotime($w['created_at']));
            $p = htmlspecialchars($w['prize_name']);
            $o = $w['order_no'] ? '<a href="orders.php?search='.htmlspecialchars($w['order_no']).'" target="_blank">'.htmlspecialchars($w['order_no']).'</a>' : '-';
            echo "<tr><td style='padding:10px 8px;white-space:nowrap'>$u</td><td style='padding:10px 8px;white-space:nowrap'>$t</td><td style='padding:10px 8px'>$p</td><td style='padding:10px 8px;white-space:nowrap'>$o</td><td style='padding:10px 8px'><span class='badge badge-success'>已发布</span></td></tr>";
        }
    }
    exit;
}

// AJAX 抽奖记录
if (isset($_GET['action']) && $_GET['action'] === 'get_records') {
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 20; $offset = ($page - 1) * $limit;
    $total = $pdo->query("SELECT COUNT(*) as cnt FROM lottery_records")->fetch()['cnt'];
    $records = $pdo->query("SELECT lr.*, u.username FROM lottery_records lr LEFT JOIN users u ON lr.user_id = u.id ORDER BY lr.id DESC LIMIT $offset, $limit")->fetchAll(PDO::FETCH_ASSOC);
    $html = '';
    if (empty($records)) {
        $html = '<tr><td colspan="7" style="text-align:center;padding:40px;color:#999">暂无抽奖记录</td></tr>';
    } else {
        foreach ($records as $r) {
            $html .= '<tr>';
            $html .= '<td style="padding:10px 8px">' . htmlspecialchars($r['username'] ?? '用户'.$r['user_id']) . '</td>';
            $html .= '<td style="padding:10px 8px">' . htmlspecialchars($r['prize_name']) . '</td>';
            $html .= '<td style="padding:10px 8px">' . ($r['is_win'] ? '<span class="badge badge-success">中奖</span>' : '<span class="badge badge-secondary">未中</span>') . '</td>';
            $html .= '<td style="padding:10px 8px">' . htmlspecialchars($r['game_area']) . '</td>';
            $html .= '<td style="padding:10px 8px">' . htmlspecialchars($r['game_nickname']) . '</td>';
            $html .= '<td style="padding:10px 8px">' . htmlspecialchars($r['game_id']) . '</td>';
            $html .= '<td style="padding:10px 8px;white-space:nowrap">' . date('Y-m-d H:i', strtotime($r['created_at'])) . '</td>';
            $html .= '</tr>';
        }
    }
    echo json_encode(['html' => $html, 'total' => intval($total), 'page' => $page, 'pages' => ceil($total / $limit)]);
    exit;
}

// 表单处理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 保存设置
    if (isset($_POST['save_settings'])) {
        $enabled = isset($_POST['enabled']) && $_POST['enabled'] == '1' ? 1 : 0;
        $daily_limit = intval($_POST['daily_limit'] ?? 100);
        $limit_message = $_POST['limit_message'] ?? '今日已上限';
        $payment_amount = floatval($_POST['payment_amount'] ?? 0.10);
        $show_winners = isset($_POST['show_winners']) && $_POST['show_winners'] == '1' ? 1 : 0;
        $pdo->prepare("UPDATE lottery_settings SET enabled=?, daily_limit=?, limit_message=?, payment_amount=?, show_winners=? WHERE id=1")
            ->execute([$enabled, $daily_limit, $limit_message, $payment_amount, $show_winners]);
        $_SESSION['success'] = '设置保存成功';
        header('Location: lottery_system.php'); exit();
    }
    // 添加奖品
    if (isset($_POST['add_prize'])) {
        $name = trim($_POST['prize_name'] ?? '');
        $probability = floatval($_POST['probability'] ?? 0);
        $sort_order = intval($_POST['sort_order'] ?? 0);
        if (empty($name)) $_SESSION['error'] = '奖品名称不能为空';
        elseif ($probability < 0 || $probability > 100) $_SESSION['error'] = '概率必须在0-100之间';
        else {
            $pdo->prepare("INSERT INTO lottery_prizes (name, probability, sort_order) VALUES (?, ?, ?)")
                ->execute([$name, $probability, $sort_order]);
            $_SESSION['success'] = '奖品添加成功';
        }
        header('Location: lottery_system.php'); exit();
    }
    // 删除奖品
    if (isset($_POST['delete_prize'])) {
        $pdo->prepare("DELETE FROM lottery_prizes WHERE id = ?")->execute([intval($_POST['prize_id'])]);
        $_SESSION['success'] = '奖品删除成功';
        header('Location: lottery_system.php'); exit();
    }
    // 编辑奖品
    if (isset($_POST['edit_prize'])) {
        $id = intval($_POST['prize_id']);
        $name = trim($_POST['prize_name'] ?? '');
        $probability = floatval($_POST['probability'] ?? 0);
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $status = isset($_POST['status']) && $_POST['status'] == '1' ? 1 : 0;
        if (empty($name)) $_SESSION['error'] = '奖品名称不能为空';
        elseif ($probability < 0 || $probability > 100) $_SESSION['error'] = '概率必须在0-100之间';
        else {
            $pdo->prepare("UPDATE lottery_prizes SET name=?, probability=?, sort_order=?, status=? WHERE id=?")
                ->execute([$name, $probability, $sort_order, $status, $id]);
            $_SESSION['success'] = '奖品编辑成功';
        }
        header('Location: lottery_system.php'); exit();
    }
    // 批量更新概率
    if (isset($_POST['batch_update_prob'])) {
        foreach ($_POST['probabilities'] ?? [] as $id => $prob) {
            $prob = floatval($prob);
            if ($prob >= 0 && $prob <= 100) {
                $pdo->prepare("UPDATE lottery_prizes SET probability = ? WHERE id = ?")
                    ->execute([$prob, intval($id)]);
            }
        }
        $_SESSION['success'] = '概率批量更新成功';
        header('Location: lottery_system.php'); exit();
    }
}

$settings = $pdo->query("SELECT * FROM lottery_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: ['enabled'=>0,'daily_limit'=>100,'limit_message'=>'今日已上限','payment_amount'=>0.10,'show_winners'=>0];
$prizes = $pdo->query("SELECT * FROM lottery_prizes ORDER BY sort_order DESC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
$totalProb = 0; foreach ($prizes as $p) $totalProb += floatval($p['probability']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>大转盘抽奖管理</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="../res/layui/css/layui.css" rel="stylesheet">
<style>
body{font-family:'Microsoft YaHei',Arial,sans-serif;background:#f5f7fa;margin:0;padding:20px}
.container{max-width:1100px;margin:0 auto}
.card{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,0.06);padding:24px;margin-bottom:20px;border:none}
.card-title{font-size:17px;font-weight:700;margin-bottom:18px;color:#1a1a1a;display:flex;align-items:center;gap:8px}
.card-title i{color:#ff6b35}
.form-label{font-weight:600;color:#444;font-size:13px}
.form-control,.form-select{border-radius:8px;border:1px solid #e0e0e0;padding:10px 12px;font-size:14px}
.form-control:focus,.form-select:focus{border-color:#ff6b35;box-shadow:0 0 0 3px rgba(255,107,53,0.08)}
.btn{border-radius:8px;font-size:13px;font-weight:600;padding:7px 18px}
.btn-primary{background:#ff6b35;border:none}
.btn-primary:hover{background:#e55a2b}
.btn-success{background:#52c41a;border:none}
.btn-danger{background:#ff4d4f;border:none}
.btn-info{background:#1890ff;border:none;color:#fff}
.btn-warning{background:#faad14;border:none;color:#fff}
.table th{background:#f8f9fa;font-weight:700;color:#333;font-size:13px;border-bottom:2px solid #eee}
.table td{font-size:13px;color:#555;padding:10px 8px;vertical-align:middle}
.table tr:hover{background:#fafafa}
.badge{padding:3px 10px;border-radius:6px;font-size:12px;font-weight:600}
.badge-success{background:#f6ffed;color:#52c41a;border:1px solid #b7eb8f}
.badge-secondary{background:#f5f5f5;color:#999;border:1px solid #d9d9d9}
.alert{border-radius:8px;padding:10px 14px;font-size:13px}
.alert-success{background:#f6ffed;border:1px solid #b7eb8f;color:#389e0d}
.alert-error{background:#fff1f0;border:1px solid #ffa39e;color:#cf1322}
.stat-card{background:linear-gradient(135deg,#ff6b35,#ff8f5a);color:#fff;border-radius:12px;padding:18px;text-align:center}
.stat-card .num{font-size:28px;font-weight:800;margin-bottom:2px}
.stat-card .lab{font-size:12px;opacity:0.9}
.prob-inp{width:70px;text-align:center;padding:5px 6px;border:1px solid #e0e0e0;border-radius:6px;font-size:13px}
.prob-inp:focus{border-color:#ff6b35;outline:none}
.prob-total{font-size:15px;font-weight:800;color:#ff6b35}
.tips{color:#999;font-size:12px;margin-top:5px}
.tips i{color:#faad14;margin-right:3px}
@media (max-width:768px){.container{padding:10px}.card{padding:16px}}
</style>
</head>
<body>
<div class="container">
<h3 style="margin-bottom:20px;font-weight:800"><i class="fas fa-dharmachakra" style="color:#ff6b35;margin-right:8px"></i>大转盘抽奖管理</h3>

<?php if (isset($_SESSION['success'])): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
<div class="alert alert-error"><i class="fas fa-times-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>

<div class="row mb-4">
<div class="col-md-3 col-6 mb-3"><div class="stat-card"><div class="num"><?php echo count($prizes); ?></div><div class="lab">奖品总数</div></div></div>
<div class="col-md-3 col-6 mb-3"><div class="stat-card" style="background:linear-gradient(135deg,#52c41a,#73d13d)"><div class="num"><?php echo number_format($totalProb,2); ?>%</div><div class="lab">总概率</div></div></div>
<div class="col-md-3 col-6 mb-3"><div class="stat-card" style="background:linear-gradient(135deg,#1890ff,#40a9ff)"><div class="num"><?php echo $settings['enabled']?'开启':'关闭'; ?></div><div class="lab">活动状态</div></div></div>
<div class="col-md-3 col-6 mb-3"><div class="stat-card" style="background:linear-gradient(135deg,#722ed1,#9254de)"><div class="num">¥<?php echo $settings['payment_amount']; ?></div><div class="lab">单次价格</div></div></div>
</div>

<div class="card">
<div class="card-title"><i class="fas fa-cog"></i> 基础设置</div>
<form method="POST">
<div class="row">
<div class="col-md-3 mb-3"><label class="form-label">活动开关</label><select class="form-select" name="enabled"><option value="0" <?php echo $settings['enabled']==0?'selected':''; ?>>关闭</option><option value="1" <?php echo $settings['enabled']==1?'selected':''; ?>>开启</option></select></div>
<div class="col-md-3 mb-3"><label class="form-label">每日上限</label><input type="number" class="form-control" name="daily_limit" value="<?php echo $settings['daily_limit']; ?>" min="1"></div>
<div class="col-md-3 mb-3"><label class="form-label">单次金额(元)</label><input type="number" class="form-control" name="payment_amount" value="<?php echo $settings['payment_amount']; ?>" step="0.01" min="0"></div>
<div class="col-md-3 mb-3"><label class="form-label">跑马灯</label><select class="form-select" name="show_winners"><option value="0" <?php echo $settings['show_winners']==0?'selected':''; ?>>关闭</option><option value="1" <?php echo $settings['show_winners']==1?'selected':''; ?>>开启</option></select></div>
</div>
<div class="row">
<div class="col-md-6 mb-3"><label class="form-label">上限提示</label><input type="text" class="form-control" name="limit_message" value="<?php echo htmlspecialchars($settings['limit_message']); ?>"></div>
</div>
<button type="submit" name="save_settings" class="btn btn-primary"><i class="fas fa-save"></i> 保存设置</button>
</form>
</div>

<div class="card">
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
<div class="card-title mb-0"><i class="fas fa-gift"></i> 奖品管理 <span style="font-size:13px;color:#999;font-weight:400">（自定义奖品，不关联商品库）</span></div>
<div>
<span class="me-3" style="font-size:13px;color:#666">总概率: <span class="prob-total"><?php echo number_format($totalProb,2); ?>%</span> <span style="color:#999">（剩余 <?php echo number_format(max(0,100-$totalProb),2); ?>% = 谢谢参与）</span></span>
<button class="btn btn-success" onclick="openAdd()"><i class="fas fa-plus"></i> 添加奖品</button>
<button class="btn btn-info" onclick="viewWinners()" style="margin-left:6px"><i class="fas fa-trophy"></i> 中奖</button>
<button class="btn btn-warning" onclick="viewRecords()" style="margin-left:6px"><i class="fas fa-list"></i> 记录</button>
</div>
</div>

<form method="POST" id="batchForm">
<input type="hidden" name="batch_update_prob" value="1">
<div class="table-responsive">
<table class="table">
<thead><tr><th style="width:50px">排序</th><th>奖品名称</th><th style="width:110px">概率(%)</th><th style="width:70px">状态</th><th style="width:110px">操作</th></tr></thead>
<tbody>
<?php if (empty($prizes)): ?>
<tr><td colspan="5" style="text-align:center;padding:40px;color:#999">暂无奖品，点击"添加奖品"</td></tr>
<?php else: ?>
<?php foreach ($prizes as $prize): ?>
<tr>
<td><?php echo $prize['sort_order']; ?></td>
<td><strong><?php echo htmlspecialchars($prize['name']); ?></strong></td>
<td><input type="number" class="prob-inp" name="probabilities[<?php echo $prize['id']; ?>]" value="<?php echo $prize['probability']; ?>" step="0.01" min="0" max="100"></td>
<td><?php echo $prize['status']==1?'<span class="badge badge-success">启用</span>':'<span class="badge badge-secondary">禁用</span>'; ?></td>
<td>
<button type="button" class="btn btn-sm btn-primary" onclick="openEdit(<?php echo $prize['id']; ?>)"><i class="fas fa-edit"></i></button>
<button type="button" class="btn btn-sm btn-danger" onclick="delPrize(<?php echo $prize['id']; ?>)" style="margin-left:4px"><i class="fas fa-trash"></i></button>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
<?php if (!empty($prizes)): ?>
<div class="mt-2">
<button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-sync"></i> 批量更新概率</button>
<span class="tips"><i class="fas fa-info-circle"></i> 概率仅后台可见，前端大转盘不显示任何概率。总概率建议≤100%，超出部分自动归为"谢谢参与"。</span>
</div>
<?php endif; ?>
</form>
</div>
</div>

<!-- 添加 -->
<div id="addModal" style="display:none">
<div style="padding:22px">
<h5 style="margin-bottom:18px;font-weight:700"><i class="fas fa-plus-circle" style="color:#52c41a;margin-right:6px"></i>添加奖品</h5>
<form method="POST">
<input type="hidden" name="add_prize" value="1">
<div class="row">
<div class="col-md-7 mb-3">
<label class="form-label">奖品名称 *</label>
<input type="text" class="form-control" name="prize_name" placeholder="如：传说皮肤、荣耀水晶、50元代金券" required>
</div>
<div class="col-md-5 mb-3">
<label class="form-label">中奖概率(%) *</label>
<input type="number" class="form-control" name="probability" placeholder="0.00 - 100.00" step="0.01" min="0" max="100" required>
<div class="tips"><i class="fas fa-eye-slash"></i> 仅后台可见，前端不显示</div>
</div>
</div>
<div class="row">
<div class="col-md-6 mb-3">
<label class="form-label">排序</label>
<input type="number" class="form-control" name="sort_order" value="0">
<div class="tips">数字越大越靠前</div>
</div>
</div>
<div class="text-end">
<button type="button" class="btn btn-secondary" onclick="layer.closeAll()" style="margin-right:6px">取消</button>
<button type="submit" class="btn btn-success">确定添加</button>
</div>
</form>
</div>
</div>

<!-- 编辑 -->
<div id="editModal" style="display:none">
<div style="padding:22px">
<h5 style="margin-bottom:18px;font-weight:700"><i class="fas fa-edit" style="color:#1890ff;margin-right:6px"></i>编辑奖品</h5>
<form method="POST">
<input type="hidden" name="edit_prize" value="1">
<input type="hidden" name="prize_id" id="eid">
<div class="row">
<div class="col-md-7 mb-3">
<label class="form-label">奖品名称 *</label>
<input type="text" class="form-control" name="prize_name" id="ename" required>
</div>
<div class="col-md-5 mb-3">
<label class="form-label">中奖概率(%) *</label>
<input type="number" class="form-control" name="probability" id="eprob" step="0.01" min="0" max="100" required>
</div>
</div>
<div class="row">
<div class="col-md-4 mb-3">
<label class="form-label">排序</label>
<input type="number" class="form-control" name="sort_order" id="esort">
</div>
<div class="col-md-4 mb-3">
<label class="form-label">状态</label>
<select class="form-select" name="status" id="estatus"><option value="1">启用</option><option value="0">禁用</option></select>
</div>
</div>
<div class="text-end">
<button type="button" class="btn btn-secondary" onclick="layer.closeAll()" style="margin-right:6px">取消</button>
<button type="submit" class="btn btn-primary">保存修改</button>
</div>
</form>
</div>
</div>

<!-- 中奖记录 -->
<div id="winModal" style="display:none">
<div style="padding:12px;max-height:60vh;overflow-y:auto">
<table class="table" style="font-size:13px"><thead><tr><th>用户</th><th>时间</th><th>奖品</th><th>订单</th><th>状态</th></tr></thead><tbody id="winList"></tbody></table>
</div>
</div>

<!-- 抽奖记录 -->
<div id="recModal" style="display:none">
<div style="padding:12px;max-height:60vh;overflow-y:auto">
<table class="table" style="font-size:13px"><thead><tr><th>用户</th><th>奖品</th><th>结果</th><th>大区</th><th>昵称</th><th>ID</th><th>时间</th></tr></thead><tbody id="recList"></tbody></table>
<div id="recPage" class="mt-2 text-center"></div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../res/layui/layui.js"></script>
<script>
var prizesData = <?php echo json_encode($prizes); ?>;
layui.use(['layer'], function(){
var layer = layui.layer;
window.openAdd = function(){
$('#addModal form')[0].reset();
layer.open({type:1,title:'添加奖品',area:['520px','auto'],content:$('#addModal')});
};
window.openEdit = function(id){
var p = prizesData.find(function(x){return x.id==id;});
if(!p) return;
$('#eid').val(p.id); $('#ename').val(p.name); $('#eprob').val(p.probability);
$('#esort').val(p.sort_order||0); $('#estatus').val(p.status);
layer.open({type:1,title:'编辑奖品',area:['520px','auto'],content:$('#editModal')});
};
window.delPrize = function(id){
layer.confirm('确定删除此奖品？', {btn:['确定','取消']}, function(){
var f = $('<form method="POST"><input type="hidden" name="delete_prize" value="1"><input type="hidden" name="prize_id" value="'+id+'"></form>');
$('body').append(f); f.submit();
});
};
window.viewWinners = function(){
$.get('lottery_system.php?action=get_winners', function(d){
$('#winList').html(d);
layer.open({type:1,title:'中奖记录',area:['780px','70%'],content:$('#winModal')});
});
};
window.viewRecords = function(page){
page = page || 1;
$.getJSON('lottery_system.php?action=get_records&page='+page, function(r){
$('#recList').html(r.html);
var pg = '';
for(var i=1;i<=r.pages;i++) pg += '<button class="btn btn-sm '+(i==r.page?'btn-primary':'btn-outline-primary')+'" onclick="viewRecords('+i+')" style="margin:0 2px">'+i+'</button>';
$('#recPage').html(pg);
layer.open({type:1,title:'抽奖记录',area:['90%','70%'],content:$('#recModal')});
});
};
});
</script>
</body>
</html>
