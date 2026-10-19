<?php
ob_start();
require_once __DIR__.'/../../includes/session_manager.php';
require_once __DIR__.'/../../includes/common.php';
require_once __DIR__.'/../../includes/log_helper.php';

if (!is_admin_logged_in()) {
    ob_end_clean();
    header("Location: ../login.php");
    exit();
}

$current_url = basename($_SERVER['PHP_SELF']);

$parentCategories = [];
$stmt = $pdo->query("SELECT id, name FROM product_categories WHERE level = 1 OR level IS NULL ORDER BY sort_order DESC, name");
if ($stmt) {
    $parentCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name'] ?? '');
        $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
        $is_flash_sale = isset($_POST['is_flash_sale']) ? 1 : 0;
        $flash_sale_start = !empty($_POST['flash_sale_start']) ? $_POST['flash_sale_start'] : null;
        $flash_sale_end = !empty($_POST['flash_sale_end']) ? $_POST['flash_sale_end'] : null;
        $sort_order = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;
        
        if (empty($name)) {
            throw new Exception("分类名称不能为空");
        }
        
        $level = $parent_id ? 2 : 1;
        
        if ($is_flash_sale) {
            if (empty($flash_sale_start) || empty($flash_sale_end)) {
                throw new Exception("秒杀分类必须设置开始和结束时间");
            }
            if (strtotime($flash_sale_start) >= strtotime($flash_sale_end)) {
                throw new Exception("秒杀结束时间必须晚于开始时间");
            }
        }
        
        $logo_path = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../assets/images/products/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = basename($_FILES['logo']['name']);
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($fileExt, $allowedExts)) {
                $newFileName = 'category_' . time() . '.' . $fileExt;
                $uploadPath = $uploadDir . $newFileName;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadPath)) {
                    $logo_path = 'assets/images/products/' . $newFileName;
                } else {
                    throw new Exception("Logo上传失败，请检查目录权限");
                }
            } else {
                throw new Exception("只允许上传JPG, PNG, GIF格式的图片");
            }
        }
        
        $sql = "INSERT INTO product_categories (
            name, logo, parent_id, level, is_flash_sale, 
            flash_sale_start, flash_sale_end, sort_order, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
        
        $stmt = $pdo->prepare($sql);
        $params = [
            $name, 
            $logo_path,
            $parent_id ?: null,
            $level,
            $is_flash_sale,
            $flash_sale_start,
            $flash_sale_end,
            $sort_order
        ];
        
        if ($stmt->execute($params)) {
            // 获取新插入的分类ID
            $category_id = $pdo->lastInsertId();
            
            // 记录管理员操作日志
            $admin_id = $_SESSION['admin_id'] ?? null;
            $admin_name = $_SESSION['admin_username'] ?? '未知管理员';
            $operation_details = "创建分类：ID=" . $category_id . ", 名称=" . $name . ", 父分类=" . ($parent_id ? $parent_id : '无');
            log_admin_operation($pdo, $admin_id, $admin_name, 'create_category', $operation_details);
            
            $success = "商品分类创建成功！";
            $_POST = array();
        } else {
            throw new Exception("分类创建失败: " . implode(", ", $stmt->errorInfo()));
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>创建分类 - 后台管理系统</title>
    <link href="https://fonts.googleapis.com/css2?family=Ma+Shan+Zheng&family=Noto+Serif+SC:wght@400;500;600;700&family=ZCOOL+XiaoWei&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: #ffffff;
      color: #333;
      line-height: 1.5;
      padding: 20px;
      font-size: 13px;
    }

    h1 {
      font-size: 18px;
      font-weight: 600;
      margin-bottom: 20px;
      color: #000;
    }

    h2 {
      font-size: 14px;
      font-weight: 600;
      margin: 20px 0 10px 0;
      color: #333;
    }

    .btn-group {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
    }

    .btn {
      display: inline-block;
      padding: 8px 16px;
      background: #f5f5f5;
      color: #333;
      border: none;
      border-radius: 4px;
      font-size: 12px;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .btn:hover {
      background: #e0e0e0;
    }

    .btn-primary {
      background: #1976d2;
      color: #fff;
    }

    .btn-primary:hover {
      background: #1565c0;
    }

    .alert {
      padding: 10px 12px;
      border-radius: 4px;
      margin-bottom: 10px;
      font-size: 12px;
    }

    .alert-danger {
      background: #ffebee;
      color: #d32f2f;
    }

    .alert-success {
      background: #e8f5e9;
      color: #388e3c;
    }

    .form-row {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      margin-bottom: 20px;
    }

    .form-col {
      flex: 1;
      min-width: 280px;
    }

    .form-label {
      display: block;
      font-size: 13px;
      font-weight: 400;
      color: #666;
      margin-bottom: 8px;
    }

    .required-field::after {
      content: " *";
      color: #d32f2f;
    }

    .form-control {
      width: 100%;
      padding: 8px 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 13px;
      color: #333;
      background: #ffffff;
    }

    .form-control:focus {
      outline: none;
      border-color: #1976d2;
    }

    .form-control::placeholder {
      color: #999;
    }

    .form-select {
      width: 100%;
      padding: 8px 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 13px;
      color: #333;
      background: #ffffff;
      cursor: pointer;
    }

    .form-select:focus {
      outline: none;
      border-color: #1976d2;
    }

    .form-text {
      font-size: 11px;
      color: #999;
      margin-top: 4px;
    }

    .form-check {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 20px;
    }

    .form-check-input {
      width: 16px;
      height: 16px;
      cursor: pointer;
    }

    .form-check-label {
      font-size: 13px;
      color: #666;
      cursor: pointer;
    }

    .upload-container {
      margin-bottom: 8px;
    }

    .upload-preview {
      margin-top: 12px;
    }

    .upload-preview img {
      max-width: 100px;
      max-height: 100px;
      border-radius: 4px;
      border: 1px solid #ddd;
      padding: 4px;
    }

    .button-group {
      display: flex;
      gap: 8px;
      margin-top: 24px;
    }

    .flash-sale-fields {
      margin-top: 20px;
      padding: 16px;
      background: #f8f8f8;
      border-radius: 4px;
    }
    </style>
  <link href="../res/admin-white.css" rel="stylesheet">
</head>
<body>
  <h1>创建商品分类</h1>

  <div class="btn-group">
    <a href="categories.php" class="btn">
      <i class="fas fa-arrow-left"></i> 返回分类列表
    </a>
  </div>
  
  <?php if (isset($error)): ?>
  <div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
  </div>
  <?php endif; ?>
  
  <?php if (isset($success)): ?>
  <div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
  </div>
  <?php endif; ?>
  
  <form method="post" action="<?php echo htmlspecialchars($current_url); ?>" enctype="multipart/form-data">
    <div class="form-row">
      <div class="form-col">
        <div class="mb-3">
          <label for="name" class="form-label required-field">分类名称</label>
          <input type="text" class="form-control" id="name" name="name" required 
                 value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
          <div class="form-text">请输入分类名称，如"游戏陪玩"、"代练服务"等</div>
        </div>
        
        <div class="mb-3">
          <label for="parent_id" class="form-label">父级分类</label>
          <select class="form-select" id="parent_id" name="parent_id">
            <option value="">无（一级分类）</option>
            <?php foreach ($parentCategories as $category): ?>
              <option value="<?php echo $category['id']; ?>"
                  <?php echo (isset($_POST['parent_id']) && $_POST['parent_id'] == $category['id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($category['name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">选择父级分类将自动设置为二级分类</div>
        </div>
        
        <div class="mb-3">
          <label class="form-label">分类LOGO</label>
          <div class="upload-container">
            <label class="btn">
              <i class="fas fa-upload"></i> 选择图片
              <input type="file" name="logo" accept="image/*" style="position: absolute; opacity: 0; cursor: pointer;">
            </label>
            <div class="upload-preview" id="imagePreview">
              <?php if (isset($_POST['logo_path']) && !empty($_POST['logo_path'])): ?>
                <img src="<?php echo htmlspecialchars($_POST['logo_path']); ?>">
              <?php endif; ?>
            </div>
            <div class="form-text">建议尺寸：200x200像素，支持JPG, PNG, GIF格式</div>
          </div>
        </div>
      </div>
      
      <div class="form-col">
        <div class="mb-3">
          <label for="sort_order" class="form-label">排序权重</label>
          <input type="number" class="form-control" id="sort_order" name="sort_order" 
                 value="<?php echo isset($_POST['sort_order']) ? htmlspecialchars($_POST['sort_order']) : '0'; ?>" min="0" max="999">
          <div class="form-text">数字越大排序越靠前，范围0-999</div>
        </div>
        
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="is_flash_sale" name="is_flash_sale" 
                 <?php echo isset($_POST['is_flash_sale']) ? 'checked' : ''; ?>>
          <label class="form-check-label" for="is_flash_sale">秒杀分类</label>
        </div>
        
        <div id="flash-sale-fields" class="flash-sale-fields" style="<?php echo !isset($_POST['is_flash_sale']) ? 'display:none;' : ''; ?>">
          <div class="mb-3">
            <label for="flash_sale_start" class="form-label">秒杀开始时间</label>
            <input type="datetime-local" class="form-control" id="flash_sale_start" name="flash_sale_start" 
                   value="<?php echo isset($_POST['flash_sale_start']) ? htmlspecialchars($_POST['flash_sale_start']) : ''; ?>">
          </div>
          
          <div class="mb-3">
            <label for="flash_sale_end" class="form-label">秒杀结束时间</label>
            <input type="datetime-local" class="form-control" id="flash_sale_end" name="flash_sale_end" 
                   value="<?php echo isset($_POST['flash_sale_end']) ? htmlspecialchars($_POST['flash_sale_end']) : ''; ?>">
          </div>
        </div>
      </div>
    </div>
    
    <div class="button-group">
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save"></i> 创建分类
      </button>
      <button type="reset" class="btn">
        <i class="fas fa-undo"></i> 重置
      </button>
    </div>
  </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const flashSaleCheckbox = document.getElementById('is_flash_sale');
            const flashSaleFields = document.getElementById('flash-sale-fields');

            if (flashSaleCheckbox && flashSaleFields) {
                flashSaleCheckbox.addEventListener('change', function() {
                    flashSaleFields.style.display = this.checked ? 'block' : 'none';
                });
            }

            const fileInput = document.querySelector('input[type="file"][name="logo"]');
            const imagePreview = document.getElementById('imagePreview');

            if (fileInput && imagePreview) {
                fileInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            imagePreview.innerHTML = '<img src="' + e.target.result + '">';
                        };
                        reader.readAsDataURL(file);
                    } else {
                        imagePreview.innerHTML = '';
                    }
                });
            }

            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const isFlashSale = document.getElementById('is_flash_sale').checked;
                    
                    if (isFlashSale) {
                        const startTime = document.getElementById('flash_sale_start').value;
                        const endTime = document.getElementById('flash_sale_end').value;
                        
                        if (!startTime || !endTime) {
                            alert('秒杀分类必须设置开始和结束时间');
                            e.preventDefault();
                            return false;
                        }
                        
                        if (new Date(startTime) >= new Date(endTime)) {
                            alert('秒杀结束时间必须晚于开始时间');
                            e.preventDefault();
                            return false;
                        }
                    }
                    
                    return true;
                });
            }
        });
    </script>
</body>
</html>
