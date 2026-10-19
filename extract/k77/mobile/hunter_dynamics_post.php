<?php
require_once 'header.php';

// 检查登录状态和打手身份
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'hunter') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// 处理动态发布
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['content'] ?? '');
    $image_files = $_FILES['images'] ?? [];

    // 验证内容
    if (mb_strlen($content) > 50) {
        $error = '动态内容不能超过50个字';
    } elseif (empty($content) && empty($image_files['name'][0])) {
        $error = '动态内容和图片不能同时为空';
    } else {
        // 处理图片上传
        $image_urls = [];
        $upload_dir = '../uploads/hunter_dynamics/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // 检查图片数量
        $image_count = 0;
        foreach ($image_files['name'] as $name) {
            if (!empty($name)) $image_count++;
        }
        if ($image_count > 3) {
            $error = '最多只能上传3张图片';
        } else {
            // 上传图片
            for ($i = 0; $i < $image_count; $i++) {
                if ($image_files['error'][$i] == UPLOAD_ERR_OK) {
                    $file_ext = pathinfo($image_files['name'][$i], PATHINFO_EXTENSION);
                    $new_filename = 'hunter_' . $user_id . '_' . time() . '_' . $i . '.' . $file_ext;
                    $dest_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($image_files['tmp_name'][$i], $dest_path)) {
                        $image_urls[] = $dest_path;
                    } else {
                        $error = '图片上传失败，请重试';
                        break;
                    }
                }
            }

            if (!$error) {
                // 保存动态到数据库（待审核状态）
                try {
                    $stmt = $pdo->prepare("INSERT INTO hunter_dynamics (hunter_id, content, image_urls, status, created_at)
                                           VALUES (?, ?, ?, 'pending', NOW())");
                    $stmt->execute([
                        $user_id,
                        $content,
                        implode(',', $image_urls)
                    ]);
                    $success = '动态发布成功，等待管理员审核';
                    // 清空表单
                    $content = '';
                    $image_urls = [];
                } catch (PDOException $e) {
                    error_log('动态发布失败: ' . $e->getMessage());
                    $error = '动态发布失败，请稍后再试';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>发布动态 - 打手中心</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .image-upload-container {
            border: 2px dashed #ced4da;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .image-upload-container:hover {
            border-color: #80bdff;
        }
        .image-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        .preview-item {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 4px;
            overflow: hidden;
        }
        .preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .preview-remove {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            cursor: pointer;
            border: 2px solid white;
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

    <div class="container py-4">
        <div class="row">
            <div class="col-md-3">
                <?php include 'sidebar.php'; ?>
            </div>
            <div class="col-md-9">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-pencil-alt me-2"></i>发布动态</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo $success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="post" enctype="multipart/form-data">
                            <div class="mb-4">
                                <label for="content" class="form-label">动态内容</label>
                                <textarea class="form-control" id="content" name="content" rows="3" maxlength="50" placeholder="分享你的最新动态吧..."><?php echo htmlspecialchars($content ?? ''); ?></textarea>
                                <div class="form-text"><span id="contentCount"><?php echo strlen($content ?? ''); ?></span>/50 字</div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">上传图片（可选，最多3张）</label>
                                <div class="image-upload-container" id="imageUploadContainer">
                                    <input type="file" id="imageInput" name="images[]" accept="image/*" multiple style="display: none;">
                                    <i class="fas fa-cloud-upload-alt text-muted" style="font-size: 36px;"></i>
                                    <p class="text-muted mt-2">点击或拖拽图片到此处上传</p>
                                    <p class="text-muted small">支持JPG、PNG格式</p>
                                </div>
                                <div class="image-preview" id="imagePreview"></div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="button" class="btn btn-secondary me-md-2" onclick="history.back()">取消</button>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i>发布动态</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 字数统计
        document.getElementById('content').addEventListener('input', function() {
            document.getElementById('contentCount').textContent = this.value.length;
        });

        // 图片上传预览
        const imageInput = document.getElementById('imageInput');
        const imageUploadContainer = document.getElementById('imageUploadContainer');
        const imagePreview = document.getElementById('imagePreview');
        const previewImages = [];

        // 点击上传区域触发文件选择
        imageUploadContainer.addEventListener('click', function() {
            imageInput.click();
        });

        // 拖拽上传
        imageUploadContainer.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = '#80bdff';
            this.style.backgroundColor = 'rgba(0,123,255,0.05)';
        });

        imageUploadContainer.addEventListener('dragleave', function() {
            this.style.borderColor = '#ced4da';
            this.style.backgroundColor = 'transparent';
        });

        imageUploadContainer.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.borderColor = '#ced4da';
            this.style.backgroundColor = 'transparent';

            if (e.dataTransfer.files.length) {
                if (previewImages.length + e.dataTransfer.files.length > 3) {
                    alert('最多只能上传3张图片');
                    return;
                }
                handleFiles(e.dataTransfer.files);
            }
        });

        // 文件选择变化
        imageInput.addEventListener('change', function() {
            if (this.files.length) {
                if (previewImages.length + this.files.length > 3) {
                    alert('最多只能上传3张图片');
                    this.value = ''; // 清空选择
                    return;
                }
                handleFiles(this.files);
            }
        });

        // 处理上传的文件
        function handleFiles(files) {
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                if (!file.type.startsWith('image/')) continue;

                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewItem = document.createElement('div');
                    previewItem.className = 'preview-item';
                    previewItem.innerHTML = `
                        <img src="${e.target.result}" alt="预览图片">
                        <div class="preview-remove" data-index="${previewImages.length}"><i class="fas fa-times"></i></div>
                    `;
                    imagePreview.appendChild(previewItem);
                    previewImages.push(file);

                    // 绑定删除事件
                    previewItem.querySelector('.preview-remove').addEventListener('click', function() {
                        const index = parseInt(this.getAttribute('data-index'));
                        previewImages.splice(index, 1);
                        imagePreview.removeChild(previewItem);
                        // 更新剩余预览项的索引
                        updatePreviewIndices();
                        // 重置file input
                        imageInput.value = '';
                    });
                };
                reader.readAsDataURL(file);
            }
        }

        // 更新预览项索引
        function updatePreviewIndices() {
            document.querySelectorAll('.preview-remove').forEach((el, index) => {
                el.setAttribute('data-index', index);
            });
        }
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>