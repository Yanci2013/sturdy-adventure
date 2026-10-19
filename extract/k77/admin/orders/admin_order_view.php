<?php
// 开启输出缓冲
ob_start();

// 引入数据库连接文件
require_once __DIR__.'/../../includes/common.php';

// 开启会话
session_start();

// Define function to format datetime
function formatDateTime($datetime) {
    return $datetime ? date('Y-m-d H:i', strtotime($datetime)) : '未设置';
}

// Get order ID parameter
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 处理取消订单请求
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['admin_cancel_order'])) {
    try {
        $cancel_reason = filter_input(INPUT_POST, 'admin_cancel_reason', FILTER_SANITIZE_STRING);
        
        if (empty($cancel_reason)) {
            $_SESSION['error_msg'] = "请填写取消原因";
            header("Location: admin_order_view.php?id=$order_id");
            exit();
        }
        
        // 更新订单状态
        $update_sql = "UPDATE orders SET 
                        order_status = 7, 
                        cancel_time = NOW(),
                        cancel_reason = :cancel_reason,
                        cancel_by = :admin_id,
                        cancel_review_status = 'approved',
                        cancel_review_by = :admin_id,
                        cancel_review_time = NOW(),
                        close_time = NOW()
                      WHERE id = :order_id";
        
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([
            ':cancel_reason' => $cancel_reason,
            ':admin_id' => $_SESSION['admin_id'] ?? 1,
            ':order_id' => $order_id
        ]);
        
        $_SESSION['success_msg'] = "订单已成功取消";
        header("Location: admin_order_view.php?id=$order_id");
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "取消订单失败: " . $e->getMessage();
        header("Location: admin_order_view.php?id=$order_id");
        exit();
    }
}

try {
    // Get order details using PDO
    $sql = "SELECT o.*, 
                   u.username as issuer_username, u.avatar as issuer_avatar,
                   hu.username as hunter_username, hu.avatar as hunter_avatar,
                   p.name as partner_name
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.id 
            LEFT JOIN users hu ON o.hunter_user_id = hu.id
            LEFT JOIN partners p ON o.fadan_id = p.id
            WHERE o.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$order_id]);
    
    if ($stmt->rowCount() == 0) {
        $_SESSION['error_msg'] = "订单不存在";
        header("Location: orders.php");
        exit();
    }

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    // Status mapping
    $statusText = [
        1 => '待接单',
        2 => '处理中',
        3 => '待审核',
        4 => '已完成',
        5 => '关闭中',
        6 => '客服介入',
        7 => '已撤销'
    ];

    // Parse JSON formatted submission images
    $submit_images = [];
    if (!empty($order['submit_images'])) {
        $submit_images = json_decode($order['submit_images'], true) ?: [];
    }

    // Parse cancel images if exists
    $cancel_images = [];
    if (!empty($order['cancel_images'])) {
        $cancel_images = json_decode($order['cancel_images'], true) ?: [];
    }

    // 获取罚款原因列表
    $reasons = [];
    try {
        $stmt = $pdo->query("SELECT * FROM penalty_reasons ORDER BY id");
        $reasons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $reasons = [];
    }
} catch (PDOException $e) {
    $_SESSION['error_msg'] = "数据库错误: " . $e->getMessage();
    header("Location: orders.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>订单详情 #<?php echo $order['id']; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <!-- 引入 Layui CSS -->
    <link href="../res/layui/css/layui.css" rel="stylesheet">
    <!-- 引入 AdminUI CSS -->
    <link href="../res/adminui/dist/css/admin.css" rel="stylesheet">
    <style>
        .order-detail-card {
            margin-bottom: 15px;
            border-radius: 2px;
            box-shadow: 0 1px 2px 0 rgba(0,0,0,.05);
        }
        .order-detail-header {
            border-bottom: 1px solid #f6f6f6;
            padding: 15px;
        }
        .order-detail-body {
            padding: 20px;
        }
        .detail-label {
            font-weight: bold;
            color: #666;
        }
        .detail-value {
            padding: 8px 0;
            border-bottom: 1px dashed #eee;
        }
        .user-avatar {
            vertical-align: middle;
            margin-right: 5px;
        }
        .image-thumbnail {
            margin-bottom: 10px;
            border: 1px solid #eee;
            transition: all .3s;
            cursor: pointer;
        }
        .image-thumbnail:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 12px 0 rgba(0,0,0,.1);
        }
        .action-buttons {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #f6f6f6;
        }
        .image-preview-modal img {
            max-width: 100%;
            display: block;
            margin: 0 auto;
        }
    </style>
</head>
<body>
<div class="layui-fluid">
    <div class="layui-card order-detail-card">
        <div class="layui-card-header order-detail-header">
            <h2>订单详情 #<?php echo $order['id']; ?></h2>
        </div>
        
        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="layui-card-body">
                <div class="layui-alert layui-alert-danger">
                    <i class="layui-icon layui-icon-close"></i>
                    <?php echo $_SESSION['error_msg']; ?>
                    <?php unset($_SESSION['error_msg']); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_msg'])): ?>
            <div class="layui-card-body">
                <div class="layui-alert layui-alert-success">
                    <i class="layui-icon layui-icon-ok"></i>
                    <?php echo $_SESSION['success_msg']; ?>
                    <?php unset($_SESSION['success_msg']); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="layui-card order-detail-card">
            <div class="layui-card-header order-detail-header">
                <div class="layui-row">
                    <div class="layui-col-md6">
                        <h3 class="layui-card-title">订单信息
                            <?php if ($order['hunter_user_id']): ?>
                                <!--<button type="button" -->
                                <!--   class="layui-btn layui-btn-xs layui-btn-danger" -->
                                <!--   style="margin-left: 15px; background: #FF5722;"-->
                                <!--   onclick="showPenaltyModal()">-->
                                <!--    <i class="layui-icon layui-icon-face-cry"></i> 开罚单-->
                                <!--</button>-->
                            <?php endif; ?>
                        </h3>
                    </div>
                    <div class="layui-col-md6" style="text-align: right;">
                        <span class="layui-badge layui-bg-<?php 
                            echo $order['order_status'] == 3 ? 'orange' : 
                                ($order['order_status'] == 4 ? 'green' : 
                                ($order['order_status'] == 5 ? 'red' : 'blue')); 
                        ?>"><?php echo $statusText[$order['order_status']]; ?></span>
                    </div>
                </div>
            </div>
            <div class="layui-card-body order-detail-body">
                <div class="layui-row">
                    <div class="layui-col-md6">
                        <div class="layui-form-item">
                            <label class="layui-form-label detail-label">订单编号:</label>
                            <div class="layui-input-block">
                                <div class="layui-input layui-bg-gray detail-value"><?php echo htmlspecialchars($order['order_no']); ?></div>
                            </div>
                        </div>
                        
                        <div class="layui-form-item">
                            <label class="layui-form-label detail-label">商品名称:</label>
                            <div class="layui-input-block">
                                <div class="layui-input layui-bg-gray detail-value"><?php echo htmlspecialchars($order['product_name']); ?></div>
                            </div>
                        </div>
                        
                        <div class="layui-form-item">
                            <label class="layui-form-label detail-label">分类:</label>
                            <div class="layui-input-block">
                                <div class="layui-input layui-bg-gray detail-value"><?php echo htmlspecialchars($order['category_name'] ?? '无'); ?></div>
                            </div>
                        </div>
                        
                        <div class="layui-form-item">
                            <label class="layui-form-label detail-label">游戏大区:</label>
                            <div class="layui-input-block">
                                <div class="layui-input layui-bg-gray detail-value"><?php echo htmlspecialchars($order['game_region'] ?? '未设置'); ?></div>
                            </div>
                        </div>
                        
                        <div class="layui-form-item">
                            <label class="layui-form-label detail-label">数字ID:</label>
                            <div class="layui-input-block">
                                <div class="layui-input layui-bg-gray detail-value"><?php echo htmlspecialchars($order['digital_id'] ?? '未设置'); ?></div>
                            </div>
                        </div>
                        
                        <div class="layui-form-item">
                            <label class="layui-form-label detail-label">中文ID:</label>
                            <div class="layui-input-block">
                                <div class="layui-input layui-bg-gray detail-value"><?php echo htmlspecialchars($order['chinese_id'] ?? '未设置'); ?></div>
                            </div>
                        </div>
                        
                        <?php if (!empty($order['input_fields'])): 
                            $input_fields = json_decode($order['input_fields'], true);
                            if ($input_fields && is_array($input_fields)): ?>
                                <?php foreach ($input_fields as $field => $value): ?>
                                    <div class="layui-form-item">
                                        <label class="layui-form-label detail-label"><?php echo htmlspecialchars($field); ?>:</label>
                                        <div class="layui-input-block">
                                            <div class="layui-input layui-bg-gray detail-value"><?php echo htmlspecialchars($value); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="layui-col-md6">
                        <div class="layui-form-item">
                            <label class="layui-form-label detail-label">订单价格:</label>
                            <div class="layui-input-block">
                                <div class="layui-input layui-bg-gray detail-value">¥<?php echo number_format($order['price'], 2); ?></div>
                            </div>
                        </div>
                        
                        <div class="layui-form-item">
                            <label class="layui-form-label detail-label">支付方式:</label>
                            <div class="layui-input-block">
                                <div class="layui-input layui-bg-gray detail-value"><?php echo htmlspecialchars($order['payment_type'] ?? '未支付'); ?></div>
                            </div>
                        </div>
                        
                        <div class="layui-form-item">
                            <label class="layui-form-label detail-label">支付状态:</label>
                            <div class="layui-input-block">
                                <div class="layui-input layui-bg-gray detail-value"><?php echo htmlspecialchars($order['payment_status'] ?? '未支付'); ?></div>
                            </div>
                        </div>
                        
                        <div class="layui-form-item">
                            <label class="layui-form-label detail-label">支付时间:</label>
                            <div class="layui-input-block">
                                <div class="layui-input layui-bg-gray detail-value"><?php echo formatDateTime($order['payment_time']); ?></div>
                            </div>
                        </div>
                        
                        <div class="layui-form-item">
                            <label class="layui-form-label detail-label">发单人:</label>
                            <div class="layui-input-block">
                                <div class="layui-input layui-bg-gray detail-value"><?php echo htmlspecialchars($order['fadan_name'] ?? '系统发单'); ?></div>
                            </div>
                        </div>
                        
                        <div class="layui-form-item">
                            <label class="layui-form-label detail-label">客户:</label>
                            <div class="layui-input-block">
                                <div class="layui-input layui-bg-gray detail-value">
                                    <?php if ($order['user_id']): ?>
                                        <img src="<?php echo $order['issuer_avatar'] ? '../assets/images/avatar/'.$order['issuer_avatar'] : 'https://via.placeholder.com/40'; ?>" 
                                             class="user-avatar layui-circle" style="width:30px;height:30px;">
                                        <?php echo htmlspecialchars($order['issuer_username']); ?>
                                    <?php else: ?>
                                        系统订单
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="layui-form-item">
                            <label class="layui-form-label detail-label">打手:</label>
                            <div class="layui-input-block">
                                <div class="layui-input layui-bg-gray detail-value">
                                    <?php if ($order['hunter_user_id']): ?>
                                        <img src="<?php echo $order['hunter_avatar'] ? '../../assets/images/users/'.$order['hunter_avatar'] : 'https://via.placeholder.com/40'; ?>" 
                                             class="user-avatar layui-circle" style="width:30px;height:30px;">
                                        <?php echo htmlspecialchars($order['hunter_username']); ?>
                                    <?php else: ?>
                                        未接单
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <hr class="layui-bg-gray">
                
                <div class="layui-row">
                    <div class="layui-col-md6">
                        <div class="layui-form-item">
                            <label class="layui-form-label detail-label">创建时间:</label>
                            <div class="layui-input-block">
                                <div class="layui-input layui-bg-gray detail-value"><?php echo formatDateTime($order['create_time']); ?></div>
                            </div>
                        </div>
                        
                        <div class="layui-form-item">
                            <label class="layui-form-label detail-label">接单时间:</label>
                            <div class="layui-input-block">
                                <div class="layui-input layui-bg-gray detail-value"><?php echo formatDateTime($order['accept_time']); ?></div>
                            </div>
                        </div>
                        
                        <div class="layui-form-item">
                            <label class="layui-form-label detail-label">开始服务时间:</label>
                            <div class="layui-input-block">
                                <div class="layui-input layui-bg-gray detail-value"><?php echo formatDateTime($order['service_time']); ?></div>
                            </div>
                        </div>
                        
                        <div class="layui-form-item">
                            <label class="layui-form-label detail-label">提交时间:</label>
                            <div class="layui-input-block">
                                <div class="layui-input layui-bg-gray detail-value"><?php echo formatDateTime($order['submit_time']); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="layui-col-md6">
                        <div class="layui-form-item">
                            <label class="layui-form-label detail-label">完成时间:</label>
                            <div class="layui-input-block">
                                <div class="layui-input layui-bg-gray detail-value"><?php echo formatDateTime($order['complete_time']); ?></div>
                            </div>
                        </div>
                        
                        <div class="layui-form-item">
                            <label class="layui-form-label detail-label">审核时间:</label>
                            <div class="layui-input-block">
                                <div class="layui-input layui-bg-gray detail-value"><?php echo formatDateTime($order['review_time']); ?></div>
                            </div>
                        </div>
                        
                        <div class="layui-form-item">
                            <label class="layui-form-label detail-label">取消时间:</label>
                            <div class="layui-input-block">
                                <div class="layui-input layui-bg-gray detail-value"><?php echo formatDateTime($order['cancel_time']); ?></div>
                            </div>
                        </div>
                        
                        <div class="layui-form-item">
                            <label class="layui-form-label detail-label">关闭时间:</label>
                            <div class="layui-input-block">
                                <div class="layui-input layui-bg-gray detail-value"><?php echo formatDateTime($order['close_time']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($order['submit_notes'])): ?>
                    <div class="layui-form-item">
                        <label class="layui-form-label detail-label">提交内容:</label>
                        <div class="layui-input-block">
                            <div class="layui-textarea layui-bg-gray detail-value"><?php echo htmlspecialchars($order['submit_notes']); ?></div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($submit_images)): ?>
                    <div class="layui-form-item">
                        <label class="layui-form-label detail-label">提交图片:</label>
                        <div class="layui-input-block">
                            <div class="layui-row">
                                <?php foreach ($submit_images as $image): ?>
                                    <div class="layui-col-xs6 layui-col-sm4 layui-col-md3">
                                        <div class="layui-upload-list">
                                            <img src="../../assets/images/orders/<?php echo htmlspecialchars($image); ?>" 
                                                 alt="提交图片" 
                                                 class="image-thumbnail" 
                                                 style="max-height: 150px;"
                                                 onclick="showImagePreview('../../assets/images/orders/<?php echo htmlspecialchars($image); ?>')">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($order['cancel_reason'])): ?>
                    <div class="layui-form-item">
                        <label class="layui-form-label detail-label">取消原因:</label>
                        <div class="layui-input-block">
                            <div class="layui-textarea layui-bg-gray detail-value"><?php echo htmlspecialchars($order['cancel_reason']); ?></div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($cancel_images)): ?>
                    <div class="layui-form-item">
                        <label class="layui-form-label detail-label">取消相关图片:</label>
                        <div class="layui-input-block">
                            <div class="layui-row">
                                <?php foreach ($cancel_images as $image): ?>
                                    <div class="layui-col-xs6 layui-col-sm4 layui-col-md3">
                                        <div class="layui-upload-list">
                                            <img src="uploads/<?php echo htmlspecialchars($image); ?>" 
                                                 alt="取消图片" 
                                                 class="image-thumbnail" 
                                                 style="max-height: 150px;"
                                                 onclick="showImagePreview('uploads/<?php echo htmlspecialchars($image); ?>')">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- 底部操作按钮 -->
                <div class="action-buttons">
                    <div class="layui-form-item">
                        <div class="layui-input-block">
                            <a href="orders.php" class="layui-btn layui-btn-primary">
                                <i class="layui-icon layui-icon-return"></i> 返回列表
                            </a>
                            
                            <?php if ($order['order_status'] == 3): ?>
                                <button class="layui-btn layui-btn-normal" onclick="reviewOrder(<?php echo $order['id']; ?>, 'approve')">
                                    <i class="layui-icon layui-icon-ok"></i> 审核通过
                                </button>
                                <button class="layui-btn layui-btn-danger" onclick="reviewOrder(<?php echo $order['id']; ?>, 'reject')">
                                    <i class="layui-icon layui-icon-close"></i> 拒绝通过
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($order['order_status'] == 5 && $order['cancel_review_status'] == 'pending'): ?>
                                <a href="orders-approved.php?id=<?php echo $order['id']; ?>" class="layui-btn layui-btn-normal">
                                    <i class="layui-icon layui-icon-edit"></i> 处理取消审核
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($order['order_status'] == 6): ?>
                                <button class="layui-btn layui-btn-warm" id="interveneBtn">
                                    <i class="layui-icon layui-icon-service"></i> 处理介入
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($order['hunter_user_id']): ?>
                                <button type="button" class="layui-btn layui-btn-danger" style="background: #FF5722;" onclick="showPenaltyModal()">
                                    <i class="layui-icon layui-icon-face-cry"></i> 开罚单
                                </button>
                            <?php endif; ?>
                            
                            <?php if (in_array($order['order_status'], [1, 2, 3, 8])): ?>
                                <button class="layui-btn layui-btn-danger" onclick="showAdminCancelModal()">
                                    <i class="layui-icon layui-icon-close"></i> 直接取消订单
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 图片预览弹窗 -->
<div id="imagePreviewModal" style="display: none; padding: 20px;">
    <img id="previewImage" src="" alt="预览图片" style="max-width: 100%; max-height: 80vh;">
</div>

<!-- 引入 Layui JS -->
<script src="../res/layui/layui.js"></script>
<script>
layui.use(['layer', 'form', 'jquery'], function(){
    var layer = layui.layer;
    var form = layui.form;
    var $ = layui.jquery;
    
    var penaltyLayerIndex = null;
    var orderPrice = <?php echo $order['price']; ?>;
    var orderId = <?php echo $order['id']; ?>;
    var reasons = <?php echo json_encode($reasons); ?>;

    window.showPenaltyModal = function() {
        var reasonOptions = '<option value="">请选择罚款原因</option>';
        for (var i = 0; i < reasons.length; i++) {
            reasonOptions += '<option value="' + reasons[i].id + '">' + reasons[i].reason_name + '</option>';
        }
        
        var formHtml = '<div class="layui-form" style="padding: 20px;">' +
            '<div class="layui-form-item">' +
            '<label class="layui-form-label">罚款原因 <span style="color: red;">*</span></label>' +
            '<div class="layui-input-block">' +
            '<select name="reason_id" lay-verify="required" lay-search id="penaltyReason">' +
            reasonOptions +
            '</select>' +
            '</div>' +
            '</div>' +
            '<div class="layui-form-item">' +
            '<label class="layui-form-label">罚款金额 <span style="color: red;">*</span></label>' +
            '<div class="layui-input-block">' +
            '<div class="layui-input-wrap">' +
            '<div class="layui-input-prefix">¥</div>' +
            '<input type="number" name="amount" class="layui-input" ' +
            'step="0.01" min="0.01" max="' + orderPrice + '" ' +
            'placeholder="最高¥' + orderPrice.toFixed(2) + '" ' +
            'lay-verify="required|number" id="penaltyAmount">' +
            '</div>' +
            '</div>' +
            '</div>' +
            '<div class="layui-form-item">' +
            '<label class="layui-form-label">备注说明</label>' +
            '<div class="layui-input-block">' +
            '<textarea name="notes" class="layui-textarea" ' +
            'placeholder="请输入备注说明（选填）" rows="3" id="penaltyNotes"></textarea>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        penaltyLayerIndex = layer.open({
            type: 1,
            title: '<i class="layui-icon layui-icon-face-cry"></i> 开罚单',
            area: ['550px', '500px'],
            content: formHtml,
            btn: ['确认开罚单', '取消'],
            yes: function(index, layero){
                submitPenalty();
            },
            btn2: function(index, layero){
                layer.close(index);
            },
            success: function(layero, index){
                form.render();
            }
        });
    };

    function submitPenalty() {
        var reasonId = $('#penaltyReason').val();
        var amount = parseFloat($('#penaltyAmount').val());
        var notes = $('#penaltyNotes').val();
        
        if (!reasonId) {
            layer.msg('请选择罚款原因', {icon: 2});
            return;
        }
        
        if (!amount || amount <= 0) {
            layer.msg('请输入有效的罚款金额', {icon: 2});
            return;
        }
        
        if (amount > orderPrice) {
            layer.msg('罚款金额不能大于订单金额¥' + orderPrice.toFixed(2), {icon: 2});
            return;
        }
        
        layer.load(2);
        
        $.ajax({
            url: 'penalty_ajax_submit.php',
            type: 'POST',
            data: {
                order_id: orderId,
                reason_id: reasonId,
                amount: amount,
                notes: notes
            },
            dataType: 'json',
            success: function(res){
                layer.closeAll('loading');
                if (res.success) {
                    layer.msg(res.message, {icon: 1});
                    layer.close(penaltyLayerIndex);
                } else {
                    layer.msg(res.message, {icon: 2});
                }
            },
            error: function(xhr, status, error){
                layer.closeAll('loading');
                layer.msg('请求失败: ' + error, {icon: 2});
            }
        });
    }
});

// 显示管理员取消订单模态框
function showAdminCancelModal() {
    layui.use(['layer', 'form'], function(){
        var layer = layui.layer;
        var form = layui.form;
        
        layer.open({
            type: 1,
            title: '取消订单',
            area: ['500px', '380px'],
            content: '<form id="adminCancelForm" method="post" style="padding: 20px;">' +
                     '<input type="hidden" name="admin_cancel_order" value="1">' +
                     '<div class="layui-form-item">' +
                     '  <label class="layui-form-label">取消原因</label>' +
                     '  <div class="layui-input-block">' +
                     '    <textarea name="admin_cancel_reason" required lay-verify="required" placeholder="请填写取消原因" class="layui-textarea" style="min-height: 120px;"></textarea>' +
                     '  </div>' +
                     '</div>' +
                     '<div class="layui-form-item">' +
                     '  <div class="layui-input-block">' +
                     '    <button type="submit" class="layui-btn layui-btn-danger" lay-submit>确认取消</button>' +
                     '    <button type="button" class="layui-btn layui-btn-primary" onclick="layer.closeAll()">取消</button>' +
                     '  </div>' +
                     '</div>' +
                     '</form>',
            success: function(layero, index){
                // 渲染表单
                form.render();
                
                // 监听表单提交
                form.on('submit', function(data){
                    return true;
                });
            }
        });
    });
}

// 初始化 Layui 模块
layui.use(['layer', 'form'], function(){
    var layer = layui.layer;
    var form = layui.form;
    
    // 处理介入按钮点击事件
    $('#interveneBtn').on('click', function(){
        layer.open({
            type: 1,
            title: '客服介入处理',
            area: ['600px', '400px'],
            content: '<form class="layui-form" action="admin_order_action.php" method="post">' +
                     '<input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">' +
                     '<input type="hidden" name="action" value="intervene">' +
                     '<div class="layui-form-item">' +
                     '  <label class="layui-form-label">处理方式</label>' +
                     '  <div class="layui-input-block">' +
                     '    <select name="intervene_action" lay-verify="required">' +
                     '      <option value="complete">强制完成订单</option>' +
                     '      <option value="cancel">强制取消订单</option>' +
                     '      <option value="refund">退款处理</option>' +
                     '    </select>' +
                     '  </div>' +
                     '</div>' +
                     '<div class="layui-form-item">' +
                     '  <label class="layui-form-label">处理说明</label>' +
                     '  <div class="layui-input-block">' +
                     '    <textarea name="intervene_notes" class="layui-textarea" rows="3" required></textarea>' +
                     '  </div>' +
                     '</div>' +
                     '<div class="layui-form-item">' +
                     '  <div class="layui-input-block">' +
                     '    <button class="layui-btn" lay-submit>提交处理</button>' +
                     '    <button type="button" class="layui-btn layui-btn-primary" onclick="layer.closeAll()">取消</button>' +
                     '  </div>' +
                     '</div>' +
                     '</form>',
            success: function(layero, index){
                // 渲染表单
                form.render();
            }
        });
    });
});

// 显示图片预览弹窗
function showImagePreview(imageUrl) {
    layui.use('layer', function(){
        var layer = layui.layer;
        
        layer.open({
            type: 1,
            title: '图片预览',
            shadeClose: true,
            shade: 0.8,
            area: ['90%', '90%'],
            content: '<div style="text-align:center;"><img src="' + imageUrl + '" style="max-width:100%; max-height:100%;" /></div>'
        });
    });
}

async function reviewOrder(orderId, action) {
    try {
        const response = await fetch(`ajax_orders.php?id=${orderId}&action=${action}`);
        
        // 获取原始响应文本
        const rawText = await response.text();
        console.log("原始响应:", rawText);
        
        // 尝试解析JSON
        const data = JSON.parse(rawText); 
        
        if(data.success) {
            layui.use('layer', function(){
                var layer = layui.layer;
                layer.msg('操作成功', {icon: 1});
                setTimeout(function(){
                    location.reload();
                }, 1500);
            });
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('完整错误:', error);
        layui.use('layer', function(){
            var layer = layui.layer;
            layer.msg(`操作失败（但可能已生效）: ${error.message}`, {icon: 2});
        });
    }
}
</script>
</body>
</html>