<?php
// 开启输出缓冲
ob_start();

// 引入会话管理和数据库连接文件
require_once __DIR__.'/../../includes/session_manager.php';
require_once __DIR__.'/../../includes/common.php';
require_once __DIR__.'/../../includes/log_helper.php';

// 检查用户权限
if (!is_admin_logged_in()) {
    $_SESSION['error_msg'] = "请先登录管理员账号";
    header("Location: ../login.php");
    exit();
}

// 获取订单ID
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($order_id == 0) {
    $_SESSION['error_msg'] = "无效的订单ID";
    header("Location: orders-qxsh.php");
    exit();
}

try {
    // 获取订单详情
    $sql = "SELECT o.*, 
                   u.username as issuer_username, u.avatar as issuer_avatar,
                   hu.username as hunter_username, hu.avatar as hunter_avatar,
                   p.username as fadan_username
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.id 
            LEFT JOIN users hu ON o.hunter_user_id = hu.id
            LEFT JOIN partners p ON o.fadan_id = p.id
            WHERE o.id = ? AND o.order_status = 5 AND o.cancel_review_status = 'pending'";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$order_id]);
    
    if ($stmt->rowCount() == 0) {
        $_SESSION['error_msg'] = "订单不存在或无需审核";
        header("Location: orders-qxsh.php");
        exit();
    }

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    // 状态映射
    $statusText = [
        1 => '待接单',
        2 => '处理中',
        3 => '待结算',
        4 => '已完成',
        5 => '关闭中',
        6 => '客服介入',
        7 => '已撤销',
        8 => '待客户结单'
    ];

    // 状态颜色映射
    $statusColors = [
        1 => 'orange',
        2 => 'blue',
        3 => 'cyan',
        4 => 'green',
        5 => 'gray',
        6 => 'red',
        7 => 'black',
        8 => 'yellow'
    ];

    // 解析取消图片
    $cancel_images = [];
    if (!empty($order['cancel_images'])) {
        $cancel_images = json_decode($order['cancel_images'], true) ?: [];
    }

    // 处理表单提交
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $action = $_POST['action'] ?? '';
        $reply_content = $_POST['reply_content'] ?? '';
        $new_status = isset($_POST['new_status']) ? intval($_POST['new_status']) : 0;
        $reply_images = [];

        // 处理上传的图片
        if (!empty($_FILES['reply_images']['name'][0])) {
            $upload_dir = '../../assets/images/orders/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            foreach ($_FILES['reply_images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['reply_images']['error'][$key] == UPLOAD_ERR_OK) {
                    $file_name = uniqid() . '_' . basename($_FILES['reply_images']['name'][$key]);
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $reply_images[] = $file_name;
                    }
                }
            }
        }

        // 验证操作类型
        if (!in_array($action, ['approve', 'reject'])) {
            $_SESSION['error_msg'] = "无效的操作类型";
            header("Location: orders-approved.php?id=" . $order_id);
            exit();
        }

        // 验证新状态
        if (!array_key_exists($new_status, $statusText)) {
            $_SESSION['error_msg'] = "无效的订单状态";
            header("Location: orders-approved.php?id=" . $order_id);
            exit();
        }

        // 开始事务处理
        $pdo->beginTransaction();

        try {
            // 更新订单状态
            $update_sql = "UPDATE orders SET 
                          cancel_review_status = :status,
                          cancel_review_by = :admin_id,
                          cancel_review_time = NOW(),
                          cancel_reply = :reply_content,
                          cancel_reply_images = :reply_images,
                          cancel_reply_by = :admin_id,
                          cancel_reply_time = NOW(),
                          order_status = :new_status,
                          close_time = NOW()
                          WHERE id = :order_id";

            $update_stmt = $pdo->prepare($update_sql);
            
            $update_data = [
                ':status' => $action == 'approve' ? 'approved' : 'rejected',
                ':admin_id' => get_admin_id(),
                ':reply_content' => $reply_content,
                ':reply_images' => !empty($reply_images) ? json_encode($reply_images) : null,
                ':order_id' => $order_id,
                ':new_status' => $new_status
            ];

            $update_stmt->execute($update_data);

            // 如果是批准取消，则退还金额到用户余额
            if ($action == 'approve' && $order['user_id'] && $order['payment_amount'] > 0) {
                // 更新用户余额
                $refund_sql = "UPDATE h5user SET 
                              balance = balance + :amount,
                              updated_at = NOW()
                              WHERE id = :user_id";
                
                $refund_stmt = $pdo->prepare($refund_sql);
                $refund_stmt->execute([
                    ':amount' => $order['payment_amount'],
                    ':user_id' => $order['user_id']
                ]);

                // 记录退款日志到fund_details表
                $log_sql = "INSERT INTO fund_details 
                           (user_id, source, destination, amount, related_id, note, create_time, order_no)
                           VALUES 
                           (:user_id, 'order_refund', 'user_balance', :amount, :order_id, '订单取消退款', NOW(), :order_no)";
                
                $log_stmt = $pdo->prepare($log_sql);
                $log_stmt->execute([
                    ':user_id' => $order['user_id'],
                    ':amount' => $order['payment_amount'],
                    ':order_id' => $order['id'],
                    ':order_no' => $order['order_no']
                ]);
            }

            // 获取管理员信息
            $admin_id = get_admin_id();
            $admin_name = $_SESSION['admin_username'] ?? '未知管理员';

            // 记录订单日志
            $order_action = $action == 'approve' ? 'cancel_approved' : 'cancel_rejected';
            $order_desc = $action == 'approve' ? '订单取消申请已批准' : '订单取消申请已拒绝';
            log_order_action(
                $pdo,
                $order_id,
                $order['order_no'],
                $order_action,
                $order_desc,
                $admin_id,
                $admin_name,
                'admin',
                null,
                null,
                $reply_content
            );

            // 记录管理员操作日志
            $admin_op_desc = '管理员处理了订单取消申请：ID=' . $order_id . ', 订单号=' . $order['order_no'] . ', 操作=' . ($action == 'approve' ? '批准' : '拒绝');
            log_admin_operation(
                $pdo,
                $admin_id,
                $admin_name,
                $action == 'approve' ? 'approve_cancel' : 'reject_cancel',
                $admin_op_desc
            );

            // 提交事务
            $pdo->commit();

            $_SESSION['success_msg'] = "订单取消申请已" . ($action == 'approve' ? "批准" : "拒绝");
            header("Location: orders-qxsh.php");
            exit();
        } catch (Exception $e) {
            // 回滚事务
            $pdo->rollBack();
            $_SESSION['error_msg'] = "操作失败: " . $e->getMessage();
            header("Location: orders-approved.php?id=" . $order_id);
            exit();
        }
    }
} catch (PDOException $e) {
    $_SESSION['error_msg'] = "数据库错误: " . $e->getMessage();
    header("Location: orders-qxsh.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>处理取消订单申请</title>
    <!-- 引入 Layui CSS -->
    <link href="../res/layui/css/layui.css" rel="stylesheet">
    <!-- 引入 AdminUI CSS -->
    <link href="../res/adminui/dist/css/admin.css" rel="stylesheet">
    <style>
        .cancel-detail-box {
            background-color: #f8f8f8;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 3px solid #ff5722;
        }
        
        .cancel-images-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .cancel-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 4px;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .cancel-image:hover {
            transform: scale(1.05);
        }
        
        .reply-images-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .reply-image-item {
            position: relative;
            width: 120px;
            height: 120px;
        }
        
        .reply-image-preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .remove-image-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(0,0,0,0.5);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .status-option {
            display: inline-block;
            padding: 0 10px;
            border-radius: 2px;
            margin-right: 5px;
        }
    </style>
</head>
<body>
<div class="layui-fluid">
    <div class="layui-card">
        <div class="layui-card-header">
            <h1>处理取消订单申请 - #<?php echo $order['id']; ?></h1>
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
        
        <div class="layui-card-body">
            <div class="layui-row">
                <div class="layui-col-md6">
                    <div class="layui-form-item">
                        <label class="layui-form-label">订单编号:</label>
                        <div class="layui-form-mid"><?php echo $order['order_no']; ?></div>
                    </div>
                    
                    <div class="layui-form-item">
                        <label class="layui-form-label">订单名称:</label>
                        <div class="layui-form-mid"><?php echo htmlspecialchars($order['product_name']); ?></div>
                    </div>
                    
                    <div class="layui-form-item">
                        <label class="layui-form-label">订单价格:</label>
                        <div class="layui-form-mid">¥<?php echo number_format($order['price'], 2); ?></div>
                    </div>
                    
                    <div class="layui-form-item">
                        <label class="layui-form-label">客户:</label>
                        <div class="layui-form-mid">
                            <?php if ($order['user_id']): ?>
                                <img src="<?php echo $order['issuer_avatar'] ? '../../assets/images/avatar/'.$order['issuer_avatar'] : 'https://via.placeholder.com/40'; ?>" 
                                 class="layui-circle" style="width:30px;height:30px;vertical-align:middle;">
                                <?php echo htmlspecialchars($order['issuer_username']); ?>
                            <?php else: ?>
                                系统订单
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="layui-col-md6">
                    <div class="layui-form-item">
                        <label class="layui-form-label">订单状态:</label>
                        <div class="layui-form-mid">
                            <span class="layui-badge layui-bg-<?php echo $statusColors[$order['order_status']] ?? 'blue'; ?>">
                                <?php echo $statusText[$order['order_status']] ?? '未知状态'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="layui-form-item">
                        <label class="layui-form-label">发单人:</label>
                        <div class="layui-form-mid">
                            <?php echo htmlspecialchars($order['fadan_name'] ?? '系统发单'); ?>
                        </div>
                    </div>
                    
                    <div class="layui-form-item">
                        <label class="layui-form-label">打手:</label>
                        <div class="layui-form-mid">
                            <?php if ($order['hunter_user_id']): ?>
                                <img src="<?php echo $order['hunter_avatar'] ? '../../assets/images/avatar/'.$order['hunter_avatar'] : 'https://sydj.bcorn.cn/assets/images/user.png'; ?>" 
                                 class="layui-circle" style="width:30px;height:30px;vertical-align:middle;">
                                <?php echo htmlspecialchars($order['hunter_username']); ?>
                            <?php else: ?>
                                未接单
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="layui-form-item">
                        <label class="layui-form-label">取消时间:</label>
                        <div class="layui-form-mid"><?php echo date('Y-m-d H:i', strtotime($order['cancel_time'])); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="cancel-detail-box">
                <h3>取消原因</h3>
                <p><?php echo nl2br(htmlspecialchars($order['cancel_reason'])); ?></p>
                
                <?php if (!empty($cancel_images)): ?>
                    <h4 style="margin-top: 15px;">取消相关图片</h4>
                    <div class="cancel-images-container">
                        <?php foreach ($cancel_images as $image): ?>
                            <img src="../../<?php echo htmlspecialchars($image); ?>" 
                                 class="cancel-image" 
                                 onclick="window.open('../../<?php echo htmlspecialchars($image); ?>', '_blank')">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <form class="layui-form" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                
                <div class="layui-form-item">
                    <label class="layui-form-label">处理操作</label>
                    <div class="layui-input-block">
                        <input type="radio" name="action" value="approve" title="批准取消" checked>
                        <input type="radio" name="action" value="reject" title="拒绝取消">
                    </div>
                </div>
                
                <div class="layui-form-item">
                    <label class="layui-form-label">新订单状态</label>
                    <div class="layui-input-block">
                        <select name="new_status" class="layui-select">
                            <?php foreach ($statusText as $id => $name): ?>
                                <option value="<?php echo $id; ?>" <?php echo $id == 7 ? 'selected' : ''; ?>>
                                    <span class="status-option layui-bg-<?php echo $statusColors[$id] ?? 'blue'; ?>">
                                        <?php echo $name; ?>
                                    </span>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="layui-form-item layui-form-text">
                    <label class="layui-form-label">回复内容</label>
                    <div class="layui-input-block">
                        <textarea name="reply_content" class="layui-textarea" placeholder="请输入回复内容，说明批准或拒绝的原因"></textarea>
                    </div>
                </div>
                
                <div class="layui-form-item">
                    <label class="layui-form-label">回复图片</label>
                    <div class="layui-input-block">
                        <div class="layui-upload">
                            <button type="button" class="layui-btn layui-btn-normal" id="uploadImages">
                                <i class="layui-icon layui-icon-upload"></i>上传图片
                            </button>
                            <input type="file" name="reply_images[]" id="replyImages" style="display: none;" multiple accept="image/*">
                            <div class="layui-upload-list">
                                <div class="reply-images-preview" id="imagesPreview"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="layui-form-item">
                    <div class="layui-input-block">
                        <button class="layui-btn" lay-submit>提交处理</button>
                        <a href="orders-qxsh.php" class="layui-btn layui-btn-primary">返回列表</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 引入 Layui JS -->
<script src="../res/layui/layui.js"></script>
<script>
layui.use(['form', 'upload', 'layer'], function(){
    var form = layui.form;
    var upload = layui.upload;
    var layer = layui.layer;
    var $ = layui.$;
    
    // 图片上传按钮点击事件
    $('#uploadImages').on('click', function(){
        $('#replyImages').click();
    });
    
    // 图片选择变化事件
    $('#replyImages').on('change', function(e){
        var files = e.target.files;
        var preview = $('#imagesPreview');
        preview.empty();
        
        if (files.length > 5) {
            layer.msg('最多只能上传5张图片', {icon: 2});
            return;
        }
        
        for (var i = 0; i < files.length; i++) {
            var file = files[i];
            
            if (!file.type.match('image.*')) {
                continue;
            }
            
            var reader = new FileReader();
            reader.onload = (function(file) {
                return function(e) {
                    var previewItem = $('<div class="reply-image-item">' +
                        '<img src="' + e.target.result + '" class="reply-image-preview">' +
                        '<div class="remove-image-btn" data-index="' + i + '">×</div>' +
                        '</div>');
                    
                    preview.append(previewItem);
                };
            })(file);
            reader.readAsDataURL(file);
        }
    });
    
    // 图片删除按钮点击事件
    $(document).on('click', '.remove-image-btn', function(){
        var index = $(this).data('index');
        var files = $('#replyImages')[0].files;
        var newFiles = [];
        
        // 创建新的FileList
        for (var i = 0; i < files.length; i++) {
            if (i != index) {
                newFiles.push(files[i]);
            }
        }
        
        // 更新文件输入
        var dataTransfer = new DataTransfer();
        for (var i = 0; i < newFiles.length; i++) {
            dataTransfer.items.add(newFiles[i]);
        }
        $('#replyImages')[0].files = dataTransfer.files;
        
        // 重新渲染预览
        $('#replyImages').trigger('change');
    });
    
    // 表单提交验证
    form.on('submit', function(data){
        return true;
    });
    
    // 根据操作类型动态设置默认状态
    $('input[name="action"]').on('change', function(){
        var action = $(this).val();
        var $select = $('select[name="new_status"]');
        
        if (action == 'approve') {
            $select.val('7'); // 默认设置为已撤销
        } else {
            $select.val('2'); // 默认设置为处理中
        }
        form.render('select');
    });
});
</script>
</body>
</html>