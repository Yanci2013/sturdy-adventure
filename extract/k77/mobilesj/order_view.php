<?php
require_once 'header.php';

// 检查商家是否登录
if (empty($_SESSION['partner_id'])) {
    header("Location: partner_login.php");
    exit;
}

// 定义格式化时间的函数
function formatDateTime($datetime) {
    return $datetime ? date('Y-m-d H:i', strtotime($datetime)) : '未设置';
}

// 获取订单ID和状态参数
$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

$status = $_GET['status'] ?? 'all';

// 获取当前商家ID
$partner_id = $_SESSION['partner_id'];

if (!$order_id) {
    $_SESSION['error_msg'] = "无效的订单ID";
    header("Location: orders.php");
    exit;
}

// 处理取消订单请求
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_order'])) {
    try {
        // 验证订单属于当前商家
        $check_sql = "SELECT id, hunter_user_id, order_status FROM orders WHERE id = :order_id AND fadan_id = :partner_id";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
        $check_stmt->bindParam(':partner_id', $partner_id, PDO::PARAM_STR);
        $check_stmt->execute();
        
        $order_info = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order_info) {
            $_SESSION['error_msg'] = "订单不存在或无权操作";
            header("Location: orders.php?status=$status");
            exit;
        }
        
        // 检查订单状态是否允许取消
        if (!in_array($order_info['order_status'], [1, 2])) {
            $_SESSION['error_msg'] = "当前订单状态不允许取消";
            header("Location: orders.php?status=$status");
            exit;
        }
        
        // 获取取消原因
        $cancel_reason = filter_input(INPUT_POST, 'cancel_reason', FILTER_SANITIZE_STRING);
        
        if (empty($cancel_reason)) {
            $_SESSION['error_msg'] = "请填写取消原因";
            header("Location: order_view.php?id=$order_id");
            exit;
        }
        
        // 更新订单状态为取消待审核
        $update_sql = "UPDATE orders SET 
                        order_status = 5, 
                        cancel_time = NOW(),
                        cancel_reason = :cancel_reason,
                        cancel_by = :partner_id,
                        cancel_review_status = 'pending'
                      WHERE id = :order_id";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
        $update_stmt->bindParam(':cancel_reason', $cancel_reason);
        $update_stmt->bindParam(':partner_id', $partner_id, PDO::PARAM_STR);
        
        if ($update_stmt->execute()) {
            $_SESSION['success_msg'] = "订单 #$order_id 已提交取消申请，等待总后台审核";
        } else {
            $_SESSION['error_msg'] = "取消订单时出错";
        }
        
        // 刷新页面避免重新提交
        header("Location: orders.php?status=$status");
        exit;
        
    } catch (PDOException $e) {
        error_log("数据库错误: " . $e->getMessage());
        $_SESSION['error_msg'] = "系统错误，请稍后再试";
        header("Location: orders.php?status=$status");
        exit;
    }
}
try {
    // 获取订单详情 - 确保只查询属于当前商家的订单
    $sql = "SELECT o.*, 
                   u.username as issuer_username, u.avatar as issuer_avatar,
                   hu.username as hunter_username, hu.avatar as hunter_avatar
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.id 
            LEFT JOIN users hu ON o.hunter_user_id = hu.id
            WHERE o.id = :order_id AND o.fadan_id = :partner_id";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    $stmt->bindParam(':partner_id', $partner_id, PDO::PARAM_STR);
    $stmt->execute();

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $_SESSION['error_msg'] = "订单不存在或无权查看";
        header("Location: orders.php");
        exit;
    }

    // 状态映射
    $statusText = [
        1 => '待接单',
        2 => '处理中',
        3 => '待审核',
        4 => '已完成',
        5 => '关闭中',
        6 => '客服介入',
        7 => '已撤销'
    ];

    // 检查订单是否可编辑
    $can_edit = ($order['order_status'] == 1 || $order['order_status'] == 2) && empty($order['hunter_user_id']);

    // 检查订单是否可取消（状态1或2都可以取消，不管是否已接单）
    $can_cancel = in_array($order['order_status'], [1, 2]);

    // 检查是否可以开罚单（有打手且状态为1、2、3、8都可以开）
    $can_penalize = !empty($order['hunter_user_id']) && in_array($order['order_status'], [1, 2, 3, 8]);

    // 解析JSON格式的提交图片
    $submit_images = [];
    if (!empty($order['submit_images'])) {
        $submit_images = json_decode($order['submit_images'], true) ?: [];
    }

} catch (PDOException $e) {
    error_log("数据库错误: " . $e->getMessage());
    $_SESSION['error_msg'] = "系统错误，请稍后再试";
    header("Location: orders.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>订单详情</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--primary-teal:#3baaa3;--primary-teal-dark:#2a8a84;--gold:#c9a45c;--gold-dark:#a8843d}
        body{font-family:'Noto Sans SC',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:url('../assets/1.png');background-size:cover;background-position:center;background-repeat:no-repeat;background-attachment:fixed;color:#333;line-height:1.5;-webkit-font-smoothing:antialiased;min-height:100vh;position:relative;padding:20px 12px 80px}
        .container-fluid{max-width:1000px;margin:0 auto;position:relative;z-index:1}
        h1{font-size:20px;font-weight:700;color:#333;margin-bottom:20px}
        
        /* 消息提示 */
        .alert{padding:16px;background:rgba(255,255,255,0.8);color:#333;font-size:14px;display:flex;align-items:center;gap:10px;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,.08);margin-bottom:20px;border:1px solid #e0e0e0}
        .alert i{color:#ff9900}
        
        /* 卡片样式 */
        .card{background:rgba(255,255,255,0.8);border:1px solid #e0e0e0;border-radius:16px;box-shadow:0 4px 15px rgba(0,0,0,.08);overflow:hidden;transition:all .3s ease}
        .card:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(0,0,0,.15)}
        .card-header{border-bottom:1px solid #e0e0e0;padding:16px 20px;display:flex;justify-content:space-between;align-items:center;background:rgba(255,255,255,0.9)}
        .card-title{font-size:16px;font-weight:700;color:#333;margin:0}
        .card-body{padding:20px}
        
        /* 表单标签 */
        .form-label{display:block;font-size:12px;color:#999;margin-bottom:6px}
        p{font-size:14px;color:#666;margin:0}
        strong{font-weight:600;color:#333}
        
        /* 网格布局 */
        .row{display:flex;flex-wrap:wrap;margin:-8px}
        .col-md-6{flex:0 0 50%;max-width:50%;padding:8px}
        .col-md-3{flex:0 0 25%;max-width:25%;padding:8px}
        @media screen and (max-width:768px){.col-md-6,.col-md-3{flex:0 0 100%;max-width:100%}}
        .mb-3{margin-bottom:16px}
        .mt-4{margin-top:24px}
        hr{border:none;border-top:1px solid #e0e0e0;margin:16px 0}
        
        /* 用户头像 */
        .user-avatar{width:36px;height:36px;border:1px solid #e0e0e0;border-radius:50%;object-fit:cover;margin-right:10px}
        
        /* 状态徽章 */
        .badge{display:inline-block;padding:6px 12px;background:linear-gradient(135deg,#ffcc00 0%,#ff9900 100%);color:#000;font-size:12px;font-weight:600;border-radius:20px;box-shadow:0 2px 8px rgba(255,204,0,.3)}
        
        /* 图片缩略图 */
        .img-thumbnail{width:100%;border:1px solid #e0e0e0;border-radius:8px;cursor:pointer;transition:all .3s ease}
        .img-thumbnail:hover{opacity:0.9;transform:scale(1.02);box-shadow:0 4px 15px rgba(0,0,0,.1)}
        
        /* 按钮样式 */
        .btn{display:inline-flex;align-items:center;gap:8px;padding:12px 20px;border:none;background:linear-gradient(135deg,#ffcc00 0%,#ff9900 100%);color:#000;font-size:14px;font-weight:600;text-decoration:none;cursor:pointer;transition:all .3s ease;border-radius:12px;box-shadow:0 3px 10px rgba(255,204,0,.4)}
        .btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(255,204,0,.6)}
        .btn i{color:#000}
        .btn-secondary{background:rgba(255,255,255,0.9);color:#333;border:1px solid #e0e0e0;box-shadow:none}
        .btn-secondary:hover{background:#f5f5f5;color:#333;transform:translateY(-1px)}
        .d-flex{display:flex}
        .justify-content-between{justify-content:space-between}
        .align-items-center{align-items:center}
        .d-inline{display:inline-block}
        
        /* 响应式设计 */
        @media screen and (max-width:768px){body{padding:16px 12px 80px}
        h1{font-size:18px}
        .card-header{padding:14px 16px}
        .card-body{padding:16px}
        .d-flex.justify-content-between{flex-direction:column;gap:12px}
        .btn{width:100%;justify-content:center}}
        @media screen and (max-width:480px){.card-header{padding:12px 14px}
        .card-body{padding:14px}
        .form-label{font-size:11px}
        p{font-size:13px}}
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

<div class="container-fluid">
    <h1>订单详情</h1>
    
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($_SESSION['error_msg']); ?>
            <?php unset($_SESSION['error_msg']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($_SESSION['success_msg']); ?>
            <?php unset($_SESSION['success_msg']); ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">订单信息</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">订单编号</label>
                        <p><?php echo htmlspecialchars($order['order_no']); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">商品名称</label>
                        <p><?php echo htmlspecialchars($order['product_name']); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">分类</label>
                        <p><?php echo htmlspecialchars($order['category_name'] ?? '无'); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">游戏大区</label>
                        <p><?php echo htmlspecialchars($order['game_region'] ?? '未设置'); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">数字ID</label>
                        <p><?php echo htmlspecialchars($order['digital_id'] ?? '未设置'); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">中文ID</label>
                        <p><?php echo htmlspecialchars($order['chinese_id'] ?? '未设置'); ?></p>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">订单价格</label>
                        <p style="color: #222222; font-weight: 500;">¥<?php echo number_format($order['price'], 2); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">订单状态</label>
                        <p>
                            <span class="badge">
                                <?php echo $statusText[$order['order_status']]; ?>
                            </span>
                        </p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">发单人</label>
                        <p><?php echo htmlspecialchars($order['fadan_name'] ?? '系统发单'); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">客户</label>
                        <p style="display: flex; align-items: center;">
                            <?php if ($order['user_id']): ?>
                                <img src="<?php echo !empty($order['issuer_avatar']) ? 'uploads/avatars/'.htmlspecialchars($order['issuer_avatar']) : 'https://via.placeholder.com/40'; ?>" 
                                     class="user-avatar">
                                <?php echo htmlspecialchars($order['issuer_username']); ?>
                            <?php else: ?>
                                系统订单
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">打手</label>
                        <p style="display: flex; align-items: center;">
                            <?php if ($order['hunter_user_id']): ?>
                                <img src="<?php echo !empty($order['hunter_avatar']) ? 'uploads/avatars/'.htmlspecialchars($order['hunter_avatar']) : 'https://via.placeholder.com/40'; ?>" 
                                     class="user-avatar">
                                <?php echo htmlspecialchars($order['hunter_username']); ?>
                            <?php else: ?>
                                未接单
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">创建时间</label>
                        <p><?php echo formatDateTime($order['create_time']); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">接单时间</label>
                        <p><?php echo formatDateTime($order['accept_time']); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">开始服务时间</label>
                        <p><?php echo formatDateTime($order['service_time']); ?></p>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">完成时间</label>
                        <p><?php echo formatDateTime($order['complete_time']); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">提交时间</label>
                        <p><?php echo formatDateTime($order['submit_time']); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">审核时间</label>
                        <p><?php echo formatDateTime($order['review_time']); ?></p>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($order['submit_notes'])): ?>
                <div class="mb-3">
                    <label class="form-label">提交内容</label>
                    <p><?php echo htmlspecialchars($order['submit_notes']); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($submit_images)): ?>
                <div class="mb-3">
                    <label class="form-label">提交图片</label>
                    <div class="row">
                        <?php foreach ($submit_images as $image): ?>
                            <div class="col-md-3 mb-3">
                                <img src="../assets/images/orders/<?php echo htmlspecialchars($image); ?>" 
                                     alt="提交图片" 
                                     class="img-thumbnail"
                                     onclick="window.open('../assets/images/orders/<?php echo htmlspecialchars($image); ?>', '_blank')">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="d-flex justify-content-between mt-4">
                <a href="orders.php" class="btn">
                    <i class="fas fa-arrow-left"></i> 返回列表
                </a>
                
                <div>
                    <?php if ($order['order_status'] == 3): ?>
                        <a href="order_review.php?id=<?php echo $order['id']; ?>" class="btn">
                            <i class="fas fa-check-circle"></i> 审核订单
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($can_penalize): ?>
                        <a href="fkfd.php?order_id=<?php echo $order['id']; ?>&partner_id=<?php echo urlencode($_SESSION['partner_id']); ?>&hunter_id=<?php echo $order['hunter_user_id']; ?>" class="btn">
                            <i class="fas fa-exclamation-triangle"></i> 开罚单
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($can_cancel): ?>
                        <button type="button" class="btn" onclick="showCancelModal()">
                            <i class="fas fa-times-circle"></i> 取消订单
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 取消订单模态框 -->
<div id="cancelModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; padding:24px; max-width:500px; width:90%;">
        <h3 style="margin:0 0 16px 0; font-size:18px; color:#222;">取消订单</h3>
        <form id="cancelForm" method="post">
            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:12px; color:#999; margin-bottom:4px;">取消原因</label>
                <textarea id="cancelReason" name="cancel_reason" required 
                    style="width:100%; padding:10px; border:1px solid #eaeaea; font-size:14px; min-height:100px; resize:vertical;"
                    placeholder="请填写取消原因..."></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:12px;">
                <button type="button" onclick="hideCancelModal()" 
                    style="padding:10px 20px; border:1px solid #eaeaea; background:#fff; color:#666; cursor:pointer;">
                    取消
                </button>
                <button type="submit" name="cancel_order" value="1"
                    style="padding:10px 20px; border:1px solid #eaeaea; background:#fff; color:#666; cursor:pointer;">
                    确认取消
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showCancelModal() {
    document.getElementById('cancelModal').style.display = 'flex';
}

function hideCancelModal() {
    document.getElementById('cancelModal').style.display = 'none';
    document.getElementById('cancelReason').value = '';
}

document.getElementById('cancelForm').addEventListener('submit', function(e) {
    var reason = document.getElementById('cancelReason').value.trim();
    if (!reason) {
        alert('请填写取消原因');
        e.preventDefault();
        return false;
    }
    if (!confirm('确定要提交取消订单申请吗？此操作需要总后台审核。')) {
        e.preventDefault();
        return false;
    }
});

// 点击遮罩层关闭模态框
document.getElementById('cancelModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideCancelModal();
    }
});
</script>

<?php require_once 'footer.php'; ?>