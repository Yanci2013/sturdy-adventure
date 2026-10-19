<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/header.php';

$user_id = get_user_user_id() ?? 0;
$user = getUserById($pdo, $user_id);

$order_id = $_GET['id'] ?? 0;
if (!$order_id) {
    header('Location: my_orders.php');
    exit();
}

$order = getOrderById($pdo, $order_id);

if (!$order || ($order['user_id'] !== $user_id && $order['hunter_user_id'] !== $user_id)) {
    header('Location: my_orders.php');
    exit();
}

if (!in_array($order['order_status'], [2, 3, 5])) {
    header('Location: order_detail.php?id=' . $order_id);
    exit();
}

$success = $error = '';
$saved_images = [];
$show_submit_button = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_images'])) {
        $notes = $_POST['notes'] ?? '';
        $images = [];
        
        if (!empty($_FILES['images']['name'][0])) {
            $upload_dir = __DIR__ . '/../assets/images/orders/temp/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            array_map('unlink', glob($upload_dir . $user_id . '_' . $order_id . '_*'));
            
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) {
                    $error = "文件上传出错: " . $_FILES['images']['name'][$key];
                    continue;
                }
                
                if ($_FILES['images']['size'][$key] > 50 * 1024 * 1024) {
                    $error = "图片大小不能超过50MB: " . $_FILES['images']['name'][$key];
                    continue;
                }
                
                $file_name = $_FILES['images']['name'][$key];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                if (in_array($file_ext, $allowed_extensions)) {
                    $new_file_name = $user_id . '_' . $order_id . '_' . uniqid() . '.' . $file_ext;
                    $destination = $upload_dir . $new_file_name;
                    
                    if (move_uploaded_file($tmp_name, $destination)) {
                        $images[] = $new_file_name;
                    } else {
                        $error = "文件移动失败: " . $file_name;
                    }
                } else {
                    $error = "不支持的文件类型: " . $file_ext;
                }
            }
        }
        
        if (empty($images) && !empty($order['submit_images'])) {
            $images = json_decode($order['submit_images'], true);
        }
        
        if (empty($images)) {
            $error = $error ?: "请上传至少一张有效的完成凭证图片";
        } else {
            $stmt = $pdo->prepare("UPDATE orders SET 
                                submit_images = ?,
                                submit_notes = ?,
                                submit_time = NOW()
                                WHERE id = ?");
            
            try {
                $pdo->beginTransaction();
                $stmt->execute([json_encode($images), $notes, $order_id]);
                $pdo->commit();
                $saved_images = $images;
                $show_submit_button = true;
                $success = '图片保存成功，请点击提交按钮完成订单提交';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = '图片保存失败: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['submit_order'])) {
        $new_status = ($order['order_status'] == 2) ? 8 : 3;
        
        $temp_dir = __DIR__ . '/../assets/images/orders/temp/';
        $final_dir = __DIR__ . '/../assets/images/orders/';
        $final_images = [];
        
        if (!empty($order['submit_images'])) {
            $temp_images = json_decode($order['submit_images'], true);
            
            if (!file_exists($final_dir)) {
                mkdir($final_dir, 0777, true);
            }
            
            foreach ($temp_images as $temp_image) {
                $temp_path = $temp_dir . $temp_image;
                $final_image = str_replace($user_id . '_' . $order_id . '_', '', $temp_image);
                $final_path = $final_dir . $final_image;
                
                if (file_exists($temp_path) && rename($temp_path, $final_path)) {
                    $final_images[] = $final_image;
                }
            }
        }
        
        if (!empty($final_images)) {
            $order_price = $order['price'];
            
            $settings_stmt = $pdo->prepare("SELECT service_fee_rate FROM settings WHERE id = 1");
            $settings_stmt->execute();
            $settings = $settings_stmt->fetch(PDO::FETCH_ASSOC);
            $service_fee_rate = $settings['service_fee_rate'] ?? 30.00;
            
            $service_fee = $order_price * ($service_fee_rate / 100);
            $commission = $order_price - $service_fee;
            
            $order_no = $order['order_no'];
            
            $stmt = $pdo->prepare("UPDATE orders SET 
                                  order_status = 3, 
                                  submit_images = ?, 
                                  submit_time = NOW() 
                                  WHERE id = ?");
            
            try {
                $pdo->beginTransaction();
                
                $stmt->execute([json_encode($final_images), $order_id]);
                
                $pdo->commit();
                
                header('Location: order_detail.php?id=' . $order_id . '&success=1');
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = '订单提交失败: ' . $e->getMessage();
            }
        } else {
            $error = '没有找到有效的图片，请重新上传';
        }
    }
}

if (!$show_submit_button && !empty($order['submit_images'])) {
    $saved_images = json_decode($order['submit_images'], true);
    $show_submit_button = !empty($saved_images);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>提交订单</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #1d1d1f;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .app-container {
            max-width: 100%;
            margin: 0 auto;
            padding: 0 12px;
            padding-bottom: 80px;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            padding: 12px 12px;
            background: #ffffff;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .back-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f5f5f5;
            border: none;
            color: #1d1d1f;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .back-btn:hover {
            background: #e8e8e8;
            transform: scale(1.1);
        }

        .header-title {
            flex: 1;
            text-align: center;
            font-size: 18px;
            font-weight: 600;
            color: #000000;
        }

        .header-right {
            width: 40px;
        }

        .content-section {
            margin: 12px 0;
        }

        .card {
            background: #ffffff;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: #000000;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .order-info {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .order-info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .order-info-label {
            font-size: 14px;
            color: #666666;
        }

        .order-info-value {
            font-size: 14px;
            color: #000000;
            font-weight: 500;
        }

        .order-info-value.price {
            color: #ff4444;
            font-size: 18px;
            font-weight: 700;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #000000;
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #eaeaea;
            border-radius: 12px;
            font-size: 14px;
            color: #000000;
            background: #ffffff;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #f5b400;
            box-shadow: 0 0 0 3px rgba(245, 180, 0, 0.1);
        }

        .form-control::placeholder {
            color: #cccccc;
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .upload-area {
            border: 2px dashed #eaeaea;
            border-radius: 16px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #fafafa;
        }

        .upload-area:hover {
            border-color: #f5b400;
            background: #fffef5;
        }

        .upload-area.active {
            border-color: #f5b400;
            background: #fffef5;
        }

        .upload-icon {
            font-size: 48px;
            color: #f5b400;
            margin-bottom: 12px;
        }

        .upload-text {
            font-size: 15px;
            font-weight: 500;
            color: #000000;
            margin-bottom: 4px;
        }

        .upload-hint {
            font-size: 13px;
            color: #999999;
        }

        .file-input {
            display: none;
        }

        .image-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 16px;
        }

        .image-preview-item {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .image-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .remove-btn {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 28px;
            height: 28px;
            background: #ff4444;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #ffffff;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .remove-btn:hover {
            transform: scale(1.1);
            background: #e03e3e;
        }

        .saved-images-section {
            margin-top: 20px;
            padding: 16px;
            background: #fafafa;
            border-radius: 12px;
        }

        .saved-images-title {
            font-size: 14px;
            font-weight: 600;
            color: #000000;
            margin-bottom: 12px;
        }

        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .btn {
            flex: 1;
            padding: 14px 24px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
            border: none;
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #666666;
        }

        .btn-secondary:hover {
            background: #e8e8e8;
        }

        .btn-primary {
            background: #000000;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #333333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .btn-success {
            background: #f5b400;
            color: #ffffff;
        }

        .btn-success:hover {
            background: #e6a800;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 180, 0, 0.3);
        }

        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
        }

        .alert i {
            font-size: 18px;
        }

        ::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: #dddddd;
            border-radius: 2px;
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

    <header class="header">
        <div class="header-content">
            <a href="order_detail.php?id=<?php echo $order_id; ?>" class="back-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <h1 class="header-title">提交订单</h1>
            <div class="header-right"></div>
        </div>
    </header>

    <main class="app-container">
        <div class="content-section">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <h3 class="card-title">
                    <i class="fas fa-receipt"></i>
                    订单信息
                </h3>
                <div class="order-info">
                    <div class="order-info-item">
                        <span class="order-info-label">订单编号</span>
                        <span class="order-info-value"><?php echo htmlspecialchars($order['order_no']); ?></span>
                    </div>
                    <div class="order-info-item">
                        <span class="order-info-label">商品名称</span>
                        <span class="order-info-value"><?php echo htmlspecialchars($order['product_name']); ?></span>
                    </div>
                    <div class="order-info-item">
                        <span class="order-info-label">游戏大区</span>
                        <span class="order-info-value"><?php echo htmlspecialchars($order['game_region']); ?></span>
                    </div>
                    <div class="order-info-item">
                        <span class="order-info-label">订单金额</span>
                        <span class="order-info-value price">¥<?php echo number_format($order['price'], 2); ?></span>
                    </div>
                    <div class="order-info-item">
                        <span class="order-info-label">当前状态</span>
                        <span class="order-info-value"><?php echo getOrderStatusText($order['order_status']); ?></span>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 class="card-title">
                    <i class="fas fa-edit"></i>
                    完成凭证
                </h3>
                <form method="post" action="orderstj.php?id=<?php echo $order_id; ?>" enctype="multipart/form-data" id="submitForm">
                    <div class="form-group">
                        <label class="form-label">备注信息</label>
                        <textarea name="notes" class="form-control" placeholder="请填写订单完成情况说明" required rows="3"><?php echo htmlspecialchars($order['submit_notes'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">上传凭证图片</label>
                        <div class="upload-area" id="uploadArea">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="upload-text">点击或拖拽文件到此处上传</div>
                            <div class="upload-hint">支持JPG, PNG格式，每张图片不超过50MB</div>
                            <input type="file" name="images[]" id="imageUpload" class="file-input" multiple accept="image/*">
                        </div>
                        
                        <div class="image-preview" id="imagePreview"></div>
                        
                        <?php if (!empty($saved_images)): ?>
                            <div class="saved-images-section">
                                <div class="saved-images-title">
                                    <i class="fas fa-images"></i> 已保存的图片
                                </div>
                                <div class="image-preview">
                                    <?php foreach ($saved_images as $image): ?>
                                        <div class="image-preview-item">
                                            <img src="../assets/images/orders/temp/<?php echo htmlspecialchars($image); ?>" alt="已保存图片">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="btn-group">
                        <a href="order_detail.php?id=<?php echo $order_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            取消
                        </a>
                        <button type="submit" name="save_images" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            保存图片
                        </button>
                        <?php if ($show_submit_button): ?>
                            <button type="submit" name="submit_order" class="btn btn-success">
                                <i class="fas fa-check"></i>
                                提交订单
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const uploadArea = document.getElementById('uploadArea');
        const imageUpload = document.getElementById('imageUpload');
        const imagePreview = document.getElementById('imagePreview');
        
        uploadArea.addEventListener('click', function(e) {
            e.stopPropagation();
            imageUpload.click();
        });
        
        imageUpload.addEventListener('change', function(e) {
            if (!this.files || this.files.length === 0) return;
            
            imagePreview.innerHTML = '';
            
            Array.from(this.files).forEach(file => {
                if (!file.type.match('image.*')) return;
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewItem = document.createElement('div');
                    previewItem.className = 'image-preview-item';
                    
                    previewItem.innerHTML = `
                        <img src="${e.target.result}" alt="预览图片">
                        <button type="button" class="remove-btn"><i class="fas fa-times"></i></button>
                    `;
                    
                    previewItem.querySelector('.remove-btn').addEventListener('click', () => {
                        previewItem.remove();
                        
                        const dataTransfer = new DataTransfer();
                        Array.from(imageUpload.files)
                            .filter(f => f !== file)
                            .forEach(f => dataTransfer.items.add(f));
                        imageUpload.files = dataTransfer.files;
                    });
                    
                    imagePreview.appendChild(previewItem);
                };
                reader.readAsDataURL(file);
            });
        });
        
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('active');
        });
        
        uploadArea.addEventListener('dragleave', function() {
            this.classList.remove('active');
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('active');
            
            if (e.dataTransfer.files.length > 0) {
                const dataTransfer = new DataTransfer();
                Array.from(e.dataTransfer.files)
                    .filter(file => file.type.match('image.*'))
                    .forEach(file => dataTransfer.items.add(file));
                
                imageUpload.files = dataTransfer.files;
                
                const event = new Event('change');
                imageUpload.dispatchEvent(event);
            }
        });
    });
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
