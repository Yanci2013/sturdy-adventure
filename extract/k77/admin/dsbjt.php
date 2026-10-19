<?php
ob_start();
require_once("../includes/session_manager.php");
include("../includes/common.php");

if (!is_admin_logged_in()) {
    ob_end_clean();
    header("Location: login.php");
    exit();
}

$msg = '';
$msgType = '';

// 定义上传目录的绝对路径
$uploadDir = __DIR__ . "/../upload/banner/";
$webBasePath = "upload/banner/";

// 确保上传目录存在并设置权限
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        $msg = '无法创建上传目录：' . $uploadDir;
        $msgType = 'error';
    }
}

// 确保目录权限正确
if (file_exists($uploadDir)) {
    chmod($uploadDir, 0755);
}

$allowExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$maxFileSize = 10 * 1024 * 1024;

// 处理文件上传
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['bg_img']) || $_FILES['bg_img']['error'] === UPLOAD_ERR_NO_FILE) {
        $msg = '请选择要上传的图片文件';
        $msgType = 'error';
    } else {
        $file = $_FILES['bg_img'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $msg = '上传错误代码：' . $file['error'];
            $msgType = 'error';
        } elseif ($file['size'] > $maxFileSize) {
            $msg = '文件大小不能超过10MB';
            $msgType = 'error';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowExt)) {
                $msg = '仅支持 JPG/PNG/GIF/WebP 格式';
                $msgType = 'error';
            } else {
                // 简单的文件名
                $newName = 'banner_' . time() . '.' . $ext;
                $fullPath = $uploadDir . $newName;
                $dbPath = $webBasePath . $newName;
                
                if (move_uploaded_file($file['tmp_name'], $fullPath)) {
                    // 设置文件权限为644（所有者可读写，其他人只读）
                    chmod($fullPath, 0644);
                    
                    try {
                        // 删除旧文件
                        $oldBg = $pdo->query("SELECT banner_bg FROM site_config LIMIT 1")->fetchColumn();
                        if ($oldBg) {
                            $oldPath = __DIR__ . "/../" . $oldBg;
                            if (file_exists($oldPath)) {
                                @unlink($oldPath);
                            }
                        }
                        
                        // 更新数据库
                        $stmt = $pdo->prepare("UPDATE site_config SET banner_bg = ?");
                        $stmt->execute([$dbPath]);
                        
                        $msg = '上传成功！';
                        $msgType = 'success';
                        
                        // 重定向避免重复提交
                        header("Location: dsbjt.php?success=1");
                        exit;
                        
                    } catch (PDOException $e) {
                        $msg = '数据库错误：' . $e->getMessage();
                        $msgType = 'error';
                        @unlink($fullPath);
                    }
                } else {
                    $msg = '文件移动失败，请检查目录权限：' . $uploadDir;
                    $msgType = 'error';
                }
            }
        }
    }
}

// 读取当前配置
$currentBg = '';
$bgExists = false;

try {
    $currentBg = $pdo->query("SELECT banner_bg FROM site_config LIMIT 1")->fetchColumn();
    
    if ($currentBg) {
        $bgFullPath = __DIR__ . "/../" . $currentBg;
        $bgExists = file_exists($bgFullPath);
    }
} catch (PDOException $e) {
    $currentBg = '';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>打手中心头部背景配置</title>
<link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: #f0f2f5;
    font-family: "Microsoft YaHei", sans-serif;
    padding: 20px;
}

.container {
    max-width: 700px;
    margin: 0 auto;
}

.card {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
}

h2 {
    font-size: 22px;
    margin-bottom: 25px;
    color: #333;
}

h2 i {
    color: #f0a500;
    margin-right: 8px;
}

.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.upload-area {
    border: 2px dashed #ccc;
    border-radius: 10px;
    padding: 40px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    margin-bottom: 15px;
}

.upload-area:hover {
    border-color: #f0a500;
    background: #fffbf0;
}

.upload-area i {
    font-size: 40px;
    color: #999;
    margin-bottom: 10px;
    display: block;
}

.upload-area .text {
    font-size: 16px;
    color: #666;
}

.upload-area .hint {
    font-size: 13px;
    color: #999;
    margin-top: 5px;
}

#fileInput {
    display: none;
}

#preview {
    width: 100%;
    max-height: 200px;
    object-fit: contain;
    border-radius: 8px;
    margin-top: 10px;
    display: none;
    border: 1px solid #eee;
}

.btn {
    width: 100%;
    padding: 12px;
    background: #f0a500;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    cursor: pointer;
    font-weight: bold;
    transition: all 0.3s;
}

.btn:hover {
    background: #d49400;
}

.btn:disabled {
    background: #ccc;
    cursor: not-allowed;
}

.preview-section {
    margin-top: 30px;
}

.preview-section h3 {
    font-size: 16px;
    margin-bottom: 12px;
    color: #555;
}

.preview-container {
    width: 100%;
    height: 150px;
    border-radius: 10px;
    overflow: hidden;
    position: relative;
    background: #f5f5f5;
}

.preview-bg {
    position: absolute;
    top: -10px;
    left: -10px;
    right: -10px;
    bottom: -10px;
    background-size: cover;
    background-position: center;
    filter: blur(8px);
}

.preview-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.3);
}

.no-image {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #999;
    font-size: 15px;
}

.info-box {
    margin-top: 15px;
    padding: 12px;
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 8px;
    font-size: 13px;
    color: #856404;
    font-family: monospace;
}

.info-box strong {
    display: block;
    margin-bottom: 8px;
}
</style>
</head>
<body>
<div class="container">
    <div class="card">
        <h2>
            <i class="fas fa-image"></i>
            打手中心头部背景配置
        </h2>

        <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>">
            <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            ✅ 背景图上传成功！
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <div class="upload-area" id="uploadArea">
                <i class="fas fa-cloud-upload-alt"></i>
                <div class="text">点击或拖拽上传图片</div>
                <div class="hint">支持 JPG、PNG、GIF、WebP，最大10MB</div>
            </div>

            <input type="file" id="fileInput" name="bg_img" accept=".jpg,.jpeg,.png,.gif,.webp">
            
            <img id="preview" alt="预览">

            <button type="submit" class="btn" id="submitBtn" style="margin-top: 15px;">
                <i class="fas fa-save"></i> 保存背景图
            </button>
        </form>

        <div class="preview-section">
            <h3>前台预览效果</h3>
            <div class="preview-container">
                <?php if ($bgExists && $currentBg): ?>
                    <div class="preview-bg" style="background-image: url('../<?= htmlspecialchars($currentBg) ?>');"></div>
                    <div class="preview-overlay"></div>
                <?php else: ?>
                    <div class="no-image">
                        <?php if ($currentBg && !$bgExists): ?>
                            图片文件丢失（路径：<?= htmlspecialchars($currentBg) ?>）
                        <?php else: ?>
                            暂无背景图
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($currentBg): ?>
            <div class="info-box">
                <strong>📋 诊断信息：</strong>
                数据库路径：<?= htmlspecialchars($currentBg) ?><br>
                文件是否存在：<?= $bgExists ? '✅ 是' : '❌ 否' ?><br>
                <?php if ($bgExists): ?>
                文件大小：<?= number_format(filesize(__DIR__ . "/../" . $currentBg) / 1024, 2) ?> KB<br>
                文件权限：<?= substr(sprintf('%o', fileperms(__DIR__ . "/../" . $currentBg)), -4) ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const uploadArea = document.getElementById('uploadArea');
const fileInput = document.getElementById('fileInput');
const preview = document.getElementById('preview');
const submitBtn = document.getElementById('submitBtn');
const form = document.getElementById('uploadForm');

uploadArea.addEventListener('click', () => fileInput.click());

uploadArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadArea.style.borderColor = '#f0a500';
    uploadArea.style.background = '#fffbf0';
});

uploadArea.addEventListener('dragleave', () => {
    uploadArea.style.borderColor = '#ccc';
    uploadArea.style.background = 'transparent';
});

uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.style.borderColor = '#ccc';
    uploadArea.style.background = 'transparent';
    
    if (e.dataTransfer.files.length > 0) {
        fileInput.files = e.dataTransfer.files;
        showPreview(e.dataTransfer.files[0]);
    }
});

fileInput.addEventListener('change', () => {
    if (fileInput.files.length > 0) {
        showPreview(fileInput.files[0]);
    }
});

function showPreview(file) {
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (e) => {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
        
        uploadArea.querySelector('.text').textContent = file.name;
        uploadArea.querySelector('.hint').textContent = (file.size / 1024).toFixed(2) + ' KB';
    }
}

form.addEventListener('submit', (e) => {
    if (!fileInput.files.length) {
        e.preventDefault();
        alert('请先选择图片');
        return;
    }
    
    if (fileInput.files[0].size > 10 * 1024 * 1024) {
        e.preventDefault();
        alert('文件太大，不能超过10MB');
        return;
    }
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 上传中...';
});
</script>

</body>
</html>
<?php
ob_end_flush();
?>