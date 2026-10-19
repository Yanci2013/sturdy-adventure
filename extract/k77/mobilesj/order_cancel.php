<?php
require_once 'header.php';

// 检查商家是否登录
if (!isset($_SESSION['partner_info'])) {
    $_SESSION['error_msg'] = "请先登录商家账号";
    header("Location: partner_login.php");
    exit;
}

$partner = $_SESSION['partner_info'];
$partner_id = $partner['id'];

// 获取订单ID和状态参数
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$status = $_GET['status'] ?? 'all';

// 设置上传目录为上一级的assets/uploads
$upload_dir = '../assets/uploads/cancel_images/';

// 确保上传目录存在
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        $_SESSION['error_msg'] = "无法创建上传目录，请检查权限";
        header("Location: order_cancel.php?id=$order_id&status=$status");
        exit;
    }
}

// 获取商家常用说明
try {
    $notesStmt = $pdo->prepare("SELECT common_notes FROM partners WHERE id = ?");
    $notesStmt->execute([$partner_id]);
    $notesData = $notesStmt->fetch(PDO::FETCH_ASSOC);
    $commonNotes = $notesData ? json_decode($notesData['common_notes'], true) : [];
} catch (PDOException $e) {
    $commonNotes = [];
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cancel_reason = trim($_POST['cancel_reason']);
    $cancel_images = [];
    
    // 处理上传的图片
    if (!empty($_FILES['cancel_images']['name'][0])) {
        foreach ($_FILES['cancel_images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['cancel_images']['error'][$key] === UPLOAD_ERR_OK) {
                // 验证文件类型
                $file_type = $_FILES['cancel_images']['type'][$key];
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                
                if (in_array($file_type, $allowed_types)) {
                    $original_name = basename($_FILES['cancel_images']['name'][$key]);
                    $file_ext = pathinfo($original_name, PATHINFO_EXTENSION);
                    $file_name = uniqid() . '_' . md5($original_name) . '.' . $file_ext;
                    $upload_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($tmp_name, $upload_path)) {
                        // 存储相对路径（去掉../）
                        $cancel_images[] = 'assets/uploads/cancel_images/' . $file_name;
                    } else {
                        $_SESSION['error_msg'] = "文件上传失败，请检查目录权限";
                        header("Location: order_cancel.php?id=$order_id&status=$status");
                        exit;
                    }
                }
            }
        }
    }
    
    try {
        // 验证订单属于当前商家且状态为待接单(1)或处理中(2)
        $check_sql = "SELECT id, order_no, order_status FROM orders 
                     WHERE id = ? AND fadan_id = ? AND order_status IN (1, 2)";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$order_id, $partner_id]);
        $order_info = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order_info) {
            $_SESSION['error_msg'] = "订单不存在、无权操作或状态不允许取消";
            header("Location: orders.php?status=$status");
            exit();
        }
        
        $current_status = $order_info['order_status'];
        $order_no = $order_info['order_no'];
        
        // 验证取消原因
        if (empty($cancel_reason)) {
            $_SESSION['error_msg'] = "请填写取消原因";
            header("Location: order_cancel.php?id=$order_id&status=$status");
            exit();
        }
        
        // 开始事务
        $pdo->beginTransaction();
        
        // 更新订单状态为已关闭(9)
        $update_sql = "UPDATE orders SET 
                      order_status = 9, 
                      cancel_reason = ?,
                      cancel_images = ?,
                      cancel_by = ?,
                      cancel_time = NOW(),
                      update_time = NOW()
                      WHERE id = ?";
        
        $cancel_images_json = json_encode($cancel_images);
        $stmt = $pdo->prepare($update_sql);
        $stmt->execute([$cancel_reason, $cancel_images_json, $partner_id, $order_id]);
        
        // 记录操作日志
        $log_sql = "INSERT INTO order_logs 
                   (order_id, order_no, action, status_before, status_after, 
                   operator_id, operator_type, remark, create_time)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $status_text = [
            1 => '待接单',
            2 => '处理中',
            3 => '待结算',
            4 => '已完成',
            5 => '取消待审核中',
            6 => '客服介入',
            7 => '已撤销',
            9 => '已关闭'
        ];
        
        $log_details = "商家取消订单，原因: $cancel_reason";
        $operator_type = 'admin'; // 商家操作视为管理员类型
        
        $log_stmt = $pdo->prepare($log_sql);
        $log_stmt->execute([
            $order_id,
            $order_no,
            'cancel',
            $status_text[$current_status],
            '已关闭',
            $partner_id,
            $operator_type,
            $log_details
        ]);
        
        $pdo->commit();

        // 发送订单下架通知
        $notification_title = '订单下架通知';
        $notification_content = "你的订单#{$order['order_no']}已被下架，如有问题联系管理，资金已返还钱包";
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, content, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        $stmt->execute([$order['user_id'], $notification_title, $notification_content, 'order']);

        $_SESSION['success_msg'] = "订单 #$order_no 已关闭";
        header("Location: orders.php?status=$status");
        exit();
        
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollback();
        }
        $_SESSION['error_msg'] = "取消订单时出错: " . $e->getMessage();
        header("Location: order_cancel.php?id=$order_id&status=$status");
        exit();
    }
}

try {
    // 获取订单信息
    $sql = "SELECT id, order_no, product_name, user_id FROM orders WHERE id = ? AND fadan_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$order_id, $partner_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        $_SESSION['error_msg'] = "订单不存在或无权访问";
        header("Location: orders.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error_msg'] = "获取订单信息时出错: " . $e->getMessage();
    header("Location: orders.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>取消订单</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .container {
            max-width: 800px;
            margin: 30px auto;
            padding: 20px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .card-title {
            font-size: 1.5rem;
            margin: 0;
            color: #2d3748;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .form-label {
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 1rem;
        }
        
        textarea.form-control {
            min-height: 120px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background: #4299e1;
            color: white;
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .btn-danger {
            background: #f56565;
            color: white;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.875rem;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .file-upload {
            margin-bottom: 15px;
        }
        
        .file-upload-label {
            display: block;
            margin-bottom: 8px;
        }
        
        .file-input {
            display: none;
        }
        
        .file-preview {
            margin-top: 15px;
        }
        
        .file-preview-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .file-preview-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 10px;
        }
        
        .file-preview-item-name {
            flex: 1;
            color: #4a5568;
        }
        
        .file-preview-item-remove {
            color: #f56565;
            cursor: pointer;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background: rgba(245, 101, 101, 0.1);
            border-left: 4px solid #f56565;
            color: #c53030;
        }
        
        .common-notes-container {
            margin-bottom: 15px;
        }
        
        .common-notes-title {
            font-weight: 600;
            margin-bottom: 8px;
            color: #4a5568;
        }
        
        .common-notes-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }
        
        .common-note-item {
            background: #edf2f7;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .common-note-item:hover {
            background: #e2e8f0;
        }
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

<div class="container">
    <div class="card">
        <div class="card-header">
            <h1 class="card-title">取消订单 #<?php echo $order['order_no']; ?></h1>
        </div>
        <div class="card-body">
            <?php if (isset($_SESSION['error_msg'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error_msg']; ?>
                    <?php unset($_SESSION['error_msg']); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="order_cancel.php?id=<?php echo $order_id; ?>&status=<?php echo $status; ?>" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="cancel_reason" class="form-label">取消原因 <span class="text-danger">*</span></label>
                    
                    <?php if (!empty($commonNotes['notes'])): ?>
                    <div class="common-notes-container">
                        <div class="common-notes-title">常用说明 (点击快速输入)</div>
                        <div class="common-notes-list">
                            <?php foreach ($commonNotes['notes'] as $note): ?>
                                <div class="common-note-item" onclick="insertNote('<?php echo addslashes($note['content']); ?>')">
                                    <?php echo htmlspecialchars($note['title']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <textarea class="form-control" id="cancel_reason" name="cancel_reason" required></textarea>
                </div>
                
                <div class="file-upload">
                    <label class="file-upload-label">上传相关图片(可选)</label>
                    <input type="file" id="cancel_images" name="cancel_images[]" class="file-input" multiple accept="image/*">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('cancel_images').click()">
                        <i class="fas fa-upload"></i> 选择文件
                    </button>
                    <div class="file-preview" id="file-preview"></div>
                </div>
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="orders.php?status=<?php echo $status; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> 返回
                    </a>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times-circle"></i> 确认取消
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// 文件上传预览
document.getElementById('cancel_images').addEventListener('change', function(e) {
    const preview = document.getElementById('file-preview');
    preview.innerHTML = '';
    
    if (this.files) {
        Array.from(this.files).forEach(file => {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'file-preview-item';
                div.innerHTML = `
                    <img src="${e.target.result}" alt="${file.name}">
                    <div class="file-preview-item-name">${file.name}</div>
                    <div class="file-preview-item-remove" onclick="this.parentNode.remove()">
                        <i class="fas fa-trash"></i>
                    </div>
                `;
                preview.appendChild(div);
            }
            
            reader.readAsDataURL(file);
        });
    }
});

// 插入常用说明到文本区域
function insertNote(content) {
    const textarea = document.getElementById('cancel_reason');
    const currentValue = textarea.value;
    
    // 如果当前有内容，先添加换行
    if (currentValue.length > 0 && !currentValue.endsWith('\n')) {
        textarea.value += '\n';
    }
    
    textarea.value += content;
    textarea.focus();
    
    // 可选：添加一个小的视觉反馈
    const originalColor = textarea.style.backgroundColor;
    textarea.style.backgroundColor = '#f0fff4';
    setTimeout(() => {
        textarea.style.backgroundColor = originalColor;
    }, 300);
}
</script>

<?php require_once 'footer.php'; ?>