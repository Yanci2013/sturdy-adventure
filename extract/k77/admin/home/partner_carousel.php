<?php
ob_start();
require_once __DIR__ . '/../../includes/common.php';
session_start();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$upload_dir = __DIR__ . '/../../assets/images/partner_carousel/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

function handleImageUpload($file, $upload_dir) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => '没有选择文件或上传错误'];
    }
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => '只支持 JPG、PNG、GIF、WEBP 格式'];
    }
    
    $max_size = 5 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => '文件大小不能超过5MB'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'partner_carousel_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename, 'path' => 'assets/images/partner_carousel/' . $filename];
    } else {
        return ['success' => false, 'message' => '文件上传失败'];
    }
}

try {
    // 检查表是否存在，不存在则创建
    $pdo->exec("CREATE TABLE IF NOT EXISTS partner_carousel (
        id INT AUTO_INCREMENT PRIMARY KEY,
        image_path VARCHAR(255) NOT NULL,
        link_url VARCHAR(255) DEFAULT '#',
        sort_order INT DEFAULT 0,
        is_show TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // 检查并添加缺失的字段
    $columns = $pdo->query("SHOW COLUMNS FROM partner_carousel")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('is_show', $columns)) {
        $pdo->exec("ALTER TABLE partner_carousel ADD COLUMN is_show TINYINT(1) DEFAULT 1");
    }
    if (!in_array('created_at', $columns)) {
        $pdo->exec("ALTER TABLE partner_carousel ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    }
    if (!in_array('updated_at', $columns)) {
        $pdo->exec("ALTER TABLE partner_carousel ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['add_carousel'])) {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                $_SESSION['error_msg'] = "安全验证失败，请重试";
                header("Location: partner_carousel.php");
                exit();
            }
            
            $image_path = '';
            if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload_result = handleImageUpload($_FILES['image_file'], $upload_dir);
                if ($upload_result['success']) {
                    $image_path = $upload_result['path'];
                } else {
                    $_SESSION['error_msg'] = $upload_result['message'];
                    header("Location: partner_carousel.php");
                    exit();
                }
            } elseif (!empty($_POST['image_path'])) {
                $image_path = trim($_POST['image_path']);
            }
            
            $link_url = !empty($_POST['link_url']) ? trim($_POST['link_url']) : '#';
            $sort_order = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;
            $is_show = isset($_POST['is_show']) ? 1 : 0;
            
            if (empty($image_path)) {
                $_SESSION['error_msg'] = "请上传图片或输入图片路径";
            } else {
                $stmt = $pdo->prepare("INSERT INTO partner_carousel (image_path, link_url, sort_order, is_show) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$image_path, $link_url, $sort_order, $is_show])) {
                    $_SESSION['success_msg'] = "商家轮播图添加成功";
                } else {
                    $_SESSION['error_msg'] = "添加商家轮播图失败";
                }
            }
            header("Location: partner_carousel.php");
            exit();
        }
        
        if (isset($_POST['delete_carousel'])) {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                $_SESSION['error_msg'] = "安全验证失败，请重试";
                header("Location: partner_carousel.php");
                exit();
            }
            
            $id = intval($_POST['id']);
            $stmt = $pdo->prepare("DELETE FROM partner_carousel WHERE id = ?");
            if ($stmt->execute([$id])) {
                $_SESSION['success_msg'] = "商家轮播图删除成功";
            } else {
                $_SESSION['error_msg'] = "删除商家轮播图失败";
            }
            header("Location: partner_carousel.php");
            exit();
        }
        
        if (isset($_POST['edit_carousel'])) {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                $_SESSION['error_msg'] = "安全验证失败，请重试";
                header("Location: partner_carousel.php");
                exit();
            }
            
            $id = intval($_POST['id']);
            $image_path = $_POST['current_image_path'] ?? '';
            
            if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload_result = handleImageUpload($_FILES['image_file'], $upload_dir);
                if ($upload_result['success']) {
                    $image_path = $upload_result['path'];
                } else {
                    $_SESSION['error_msg'] = $upload_result['message'];
                    header("Location: partner_carousel.php");
                    exit();
                }
            } elseif (!empty($_POST['image_path'])) {
                $image_path = trim($_POST['image_path']);
            }
            
            $link_url = !empty($_POST['link_url']) ? trim($_POST['link_url']) : '#';
            $sort_order = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;
            $is_show = isset($_POST['is_show']) ? 1 : 0;
            
            if (empty($image_path)) {
                $_SESSION['error_msg'] = "请上传图片或输入图片路径";
            } else {
                $stmt = $pdo->prepare("UPDATE partner_carousel SET image_path = ?, link_url = ?, sort_order = ?, is_show = ? WHERE id = ?");
                if ($stmt->execute([$image_path, $link_url, $sort_order, $is_show, $id])) {
                    $_SESSION['success_msg'] = "商家轮播图更新成功";
                } else {
                    $_SESSION['error_msg'] = "更新商家轮播图失败";
                }
            }
            header("Location: partner_carousel.php");
            exit();
        }
        
        if (isset($_POST['toggle_show'])) {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                $_SESSION['error_msg'] = "安全验证失败，请重试";
                header("Location: partner_carousel.php");
                exit();
            }
            
            $id = intval($_POST['id']);
            $is_show = intval($_POST['is_show']);
            $stmt = $pdo->prepare("UPDATE partner_carousel SET is_show = ? WHERE id = ?");
            if ($stmt->execute([$is_show, $id])) {
                $_SESSION['success_msg'] = "状态更新成功";
            } else {
                $_SESSION['error_msg'] = "状态更新失败";
            }
            header("Location: partner_carousel.php");
            exit();
        }
    }
    
    $stmt = $pdo->query("SELECT * FROM partner_carousel ORDER BY sort_order ASC, id DESC");
    $carousels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "数据库错误: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <title>商家中心轮播图 - 后台管理系统</title>
  <meta name="renderer" content="webkit">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <link href="../res/layui/css/layui.css" rel="stylesheet">
  <link href="../res/adminui/dist/css/admin.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Ma+Shan+Zheng&family=Noto+Serif+SC:wght@400;500;600;700&family=ZCOOL+XiaoWei&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #ffffff; color: #333; line-height: 1.5; padding: 20px; font-size: 13px; }
    h1 { font-size: 18px; font-weight: 600; margin-bottom: 20px; color: #000; }
    h2 { font-size: 14px; font-weight: 600; margin: 20px 0 10px 0; color: #333; }
    .form-group { margin-bottom: 12px; }
    .form-label { display: block; font-size: 12px; color: #666; margin-bottom: 4px; }
    .form-input { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px; }
    .form-input:focus { outline: none; border-color: #1976d2; }
    table { width: 100%; border-collapse: collapse; font-size: 12px; }
    th { background: #f8f8f8; color: #666; font-weight: 500; padding: 8px 12px; text-align: left; border-bottom: 1px solid #eee; }
    td { padding: 8px 12px; border-bottom: 1px solid #f5f5f5; color: #333; }
    tr:hover td { background: #fafafa; }
    .empty { color: #999; padding: 20px 0; text-align: center; }
    .btn { display: inline-block; padding: 8px 16px; background: #f5f5f5; color: #333; border: none; border-radius: 4px; font-size: 12px; cursor: pointer; text-decoration: none; }
    .btn:hover { background: #e0e0e0; }
    .btn-danger { background: #ffebee; color: #d32f2f; }
    .btn-danger:hover { background: #ffcdd2; }
    .btn-success { background: #e8f5e9; color: #388e3c; }
    .btn-success:hover { background: #c8e6c9; }
    .alert { padding: 10px 12px; border-radius: 4px; margin-bottom: 10px; font-size: 12px; }
    .alert-danger { background: #ffebee; color: #d32f2f; }
    .alert-success { background: #e8f5e9; color: #388e3c; }
    .carousel-image { width: 80px; height: 40px; object-fit: cover; border-radius: 4px; cursor: pointer; }
    .action-buttons { display: flex; gap: 8px; }
    .form-check { display: flex; align-items: center; gap: 8px; cursor: pointer; }
    .form-check-input { width: 16px; height: 16px; cursor: pointer; }
    .status-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; }
    .status-badge.show { background: #e8f5e9; color: #388e3c; }
    .status-badge.hide { background: #ffebee; color: #d32f2f; }
    .file-upload-wrapper { border: 2px dashed #ddd; border-radius: 8px; padding: 30px; text-align: center; cursor: pointer; transition: border-color 0.2s; margin-bottom: 12px; position: relative; }
    .file-upload-wrapper:hover { border-color: #1976d2; }
    .file-upload-wrapper.has-file { border-color: #388e3c; }
    .file-upload-icon { font-size: 32px; color: #ccc; margin-bottom: 8px; }
    .file-upload-text { font-size: 13px; color: #666; }
    .file-upload-hint { font-size: 11px; color: #999; margin-top: 4px; }
    .file-input { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
    .preview-image { max-width: 200px; max-height: 100px; margin-top: 10px; display: none; border-radius: 4px; }
    .preview-image.show { display: block; }
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; }
    .modal-overlay.active { display: flex; align-items: center; justify-content: center; }
    .modal { background: #fff; border-radius: 8px; width: 90%; max-width: 500px; max-height: 90vh; overflow: auto; }
    .modal-header { padding: 16px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    .modal-title { font-size: 16px; font-weight: 600; color: #333; }
    .modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: #999; }
    .modal-close:hover { color: #333; }
    .modal-body { padding: 20px; }
    .modal-footer { padding: 16px 20px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 8px; }
    .btn-primary { background: #1976d2; color: #fff; }
    .btn-primary:hover { background: #1565c0; }
  </style>
</head>
<body>
  <h1>商家中心轮播图</h1>

  <?php if (isset($_SESSION['error_msg'])): ?>
  <div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error_msg']); ?>
  </div>
  <?php unset($_SESSION['error_msg']); ?>
  <?php endif; ?>
  
  <?php if (isset($_SESSION['success_msg'])): ?>
  <div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success_msg']); ?>
  </div>
  <?php unset($_SESSION['success_msg']); ?>
  <?php endif; ?>
  
  <?php if (isset($error)): ?>
  <div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
  </div>
  <?php endif; ?>

  <h2>添加商家轮播图</h2>
  <form method="POST" action="partner_carousel.php" enctype="multipart/form-data" style="margin-bottom: 20px;">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    
    <div class="form-group">
      <label class="form-label">上传图片</label>
      <div class="file-upload-wrapper" id="addUploadWrapper">
        <div class="file-upload-icon">
          <i class="fas fa-cloud-upload-alt"></i>
        </div>
        <div class="file-upload-text">点击或拖拽上传图片</div>
        <div class="file-upload-hint">支持 JPG、PNG、GIF、WEBP 格式，最大 5MB</div>
        <input type="file" name="image_file" class="file-input" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" onchange="previewImage(this, 'addPreview', 'addUploadWrapper')">
        <img id="addPreview" class="preview-image">
      </div>
    </div>
    
    <div class="form-group">
      <label class="form-label">或输入图片路径</label>
      <input type="text" name="image_path" placeholder="例如: assets/images/partner_carousel/image.jpg（可选）" class="form-input">
    </div>
    
    <div class="form-group">
      <label class="form-label">跳转链接</label>
      <input type="text" name="link_url" placeholder="例如: https://example.com 或 #" class="form-input" value="#">
    </div>
    
    <div class="form-group">
      <label class="form-label">排序权重</label>
      <input type="number" name="sort_order" placeholder="数字越小越靠前" class="form-input" value="0">
    </div>
    
    <div class="form-group">
      <label class="form-check">
        <input type="checkbox" name="is_show" class="form-check-input" checked>
        <span class="form-check-label">立即显示</span>
      </label>
    </div>
    
    <button type="submit" name="add_carousel" class="btn btn-primary">
      <i class="fas fa-plus"></i> 添加商家轮播图
    </button>
  </form>

  <h2>商家轮播图列表</h2>

  <?php if (empty($carousels)): ?>
  <div class="empty">暂无商家轮播图数据</div>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>预览</th>
        <th>图片路径</th>
        <th>跳转链接</th>
        <th>排序</th>
        <th>状态</th>
        <th>操作</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($carousels as $carousel): ?>
      <tr>
        <td>
          <?php if (!empty($carousel['image_path'])): ?>
            <img src="../../<?php echo htmlspecialchars($carousel['image_path']); ?>" 
                 class="carousel-image"
                 onclick="window.open('../../<?php echo htmlspecialchars($carousel['image_path']); ?>', '_blank')"
                 onerror="this.style.display='none'">
          <?php endif; ?>
        </td>
        <td><?php echo htmlspecialchars($carousel['image_path']); ?></td>
        <td><?php echo htmlspecialchars($carousel['link_url']); ?></td>
        <td><?php echo $carousel['sort_order']; ?></td>
        <td>
          <span class="status-badge <?php echo $carousel['is_show'] ? 'show' : 'hide'; ?>">
            <?php echo $carousel['is_show'] ? '显示中' : '已隐藏'; ?>
          </span>
        </td>
        <td>
          <div class="action-buttons">
            <button class="btn" onclick="openEditModal(<?php echo $carousel['id']; ?>, '<?php echo htmlspecialchars($carousel['image_path'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($carousel['link_url'], ENT_QUOTES); ?>', <?php echo $carousel['sort_order']; ?>, <?php echo $carousel['is_show']; ?>)">
              <i class="fas fa-edit"></i> 编辑
            </button>
            <form method="POST" action="partner_carousel.php" style="display:inline;" onsubmit="return confirm('确定要删除这个轮播图吗？');">
              <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
              <input type="hidden" name="id" value="<?php echo $carousel['id']; ?>">
              <button type="submit" name="delete_carousel" class="btn btn-danger">
                <i class="fas fa-trash"></i> 删除
              </button>
            </form>
            <form method="POST" action="partner_carousel.php" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
              <input type="hidden" name="id" value="<?php echo $carousel['id']; ?>">
              <input type="hidden" name="is_show" value="<?php echo $carousel['is_show'] ? 0 : 1; ?>">
              <button type="submit" name="toggle_show" class="btn <?php echo $carousel['is_show'] ? 'btn-success' : ''; ?>">
                <i class="fas <?php echo $carousel['is_show'] ? 'fa-eye-slash' : 'fa-eye'; ?>"></i> <?php echo $carousel['is_show'] ? '隐藏' : '显示'; ?>
              </button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <div class="modal-overlay" id="editModalOverlay">
    <div class="modal">
      <div class="modal-header">
        <h3 class="modal-title">编辑商家轮播图</h3>
        <button class="modal-close" onclick="closeEditModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body">
        <form method="POST" action="partner_carousel.php" id="editForm" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
          <input type="hidden" name="id" id="edit_id">
          <input type="hidden" name="current_image_path" id="edit_current_image_path">
          
          <div class="form-group">
            <label class="form-label">上传新图片</label>
            <div class="file-upload-wrapper" id="editUploadWrapper">
              <div class="file-upload-icon">
                <i class="fas fa-cloud-upload-alt"></i>
              </div>
              <div class="file-upload-text">点击或拖拽上传新图片（可选）</div>
              <div class="file-upload-hint">支持 JPG、PNG、GIF、WEBP 格式，最大 5MB</div>
              <input type="file" name="image_file" class="file-input" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" onchange="previewImage(this, 'editPreview', 'editUploadWrapper')">
              <img id="editPreview" class="preview-image">
            </div>
          </div>
          
          <div class="form-group">
            <label class="form-label">或修改图片路径</label>
            <input type="text" name="image_path" id="edit_image_path" placeholder="例如: assets/images/partner_carousel/image.jpg（可选）" class="form-input">
          </div>
          
          <div class="form-group">
            <label class="form-label">跳转链接</label>
            <input type="text" name="link_url" id="edit_link_url" class="form-input">
          </div>
          
          <div class="form-group">
            <label class="form-label">排序权重</label>
            <input type="number" name="sort_order" id="edit_sort_order" class="form-input">
          </div>
          
          <div class="form-group">
            <label class="form-check">
              <input type="checkbox" name="is_show" id="edit_is_show" class="form-check-input">
              <span class="form-check-label">显示轮播图</span>
            </label>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" onclick="closeEditModal()">取消</button>
        <button type="submit" name="edit_carousel" form="editForm" class="btn btn-primary">
          <i class="fas fa-check"></i> 保存修改
        </button>
      </div>
    </div>
  </div>

  <script src="../res/layui/layui.js"></script>
  <script>
  layui.config({
      base: '../res/'
  }).use(['index'], function(){
      var $ = layui.$;
  });

  function previewImage(input, previewId, wrapperId) {
    const preview = document.getElementById(previewId);
    const wrapper = document.getElementById(wrapperId);
    
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      
      reader.onload = function(e) {
        preview.src = e.target.result;
        preview.classList.add('show');
        wrapper.classList.add('has-file');
      }
      
      reader.readAsDataURL(input.files[0]);
    } else {
      preview.classList.remove('show');
      wrapper.classList.remove('has-file');
    }
  }

  function openEditModal(id, imagePath, linkUrl, sortOrder, isShow) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_current_image_path').value = imagePath;
    document.getElementById('edit_image_path').value = imagePath;
    document.getElementById('edit_link_url').value = linkUrl;
    document.getElementById('edit_sort_order').value = sortOrder;
    document.getElementById('edit_is_show').checked = isShow == 1;
    
    const editPreview = document.getElementById('editPreview');
    const editUploadWrapper = document.getElementById('editUploadWrapper');
    editPreview.classList.remove('show');
    editUploadWrapper.classList.remove('has-file');
    
    const fileInput = document.querySelector('#editForm input[name="image_file"]');
    if (fileInput) {
      fileInput.value = '';
    }
    
    document.getElementById('editModalOverlay').classList.add('active');
  }

  function closeEditModal() {
    document.getElementById('editModalOverlay').classList.remove('active');
  }

  document.getElementById('editModalOverlay').addEventListener('click', function(e) {
    if (e.target === this) {
      closeEditModal();
    }
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeEditModal();
    }
  });
  </script>
</body>
</html>
