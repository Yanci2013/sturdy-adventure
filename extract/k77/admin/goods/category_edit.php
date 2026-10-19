<?php
// 开启输出缓冲
ob_start();

require_once __DIR__.'/../../includes/session_manager.php';
require_once __DIR__.'/../../includes/common.php';
require_once __DIR__.'/../../includes/log_helper.php';

if (!is_admin_logged_in()) {
    ob_end_clean();
    header("Location: ../login.php");
    exit();
}

// 获取分类ID并验证
$category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($category_id <= 0) {
    ob_end_clean();
    header('Location: categories.php');
    exit();
}

try {
    // 获取分类信息
    $sql = "SELECT * FROM product_categories WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$category_id]);
    
    if ($stmt->rowCount() == 0) {
        ob_end_clean();
        header('Location: categories.php');
        exit();
    }

    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    // 获取所有一级分类（用于设置父分类）
    $parent_categories = [];
    $parent_sql = "SELECT id, name FROM product_categories WHERE level = 1 OR level IS NULL ORDER BY sort_order DESC, name";
    $parent_stmt = $pdo->query($parent_sql);
    $parent_categories = $parent_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 初始化变量
    $name = $category['name'];
    $logo = $category['logo'];
    $parent_id = $category['parent_id'];
    $is_flash_sale = $category['is_flash_sale'];
    $flash_sale_start = $category['flash_sale_start'];
    $flash_sale_end = $category['flash_sale_end'];
    $sort_order = $category['sort_order'];
    $status = $category['status'];
    $error = '';

    // 处理表单提交
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 获取并清理表单数据
        $name = trim($_POST['name'] ?? '');
        $parent_id = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? intval($_POST['parent_id']) : null;
        $is_flash_sale = isset($_POST['is_flash_sale']) ? 1 : 0;
        $flash_sale_start = $is_flash_sale ? trim($_POST['flash_sale_start'] ?? '') : null;
        $flash_sale_end = $is_flash_sale ? trim($_POST['flash_sale_end'] ?? '') : null;
        $sort_order = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;
        $status = isset($_POST['status']) ? 1 : 0;
        
        // 处理LOGO上传
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
                    // 删除旧LOGO
                    if ($logo && file_exists(__DIR__ . '/../../' . $logo)) {
                        unlink(__DIR__ . '/../../' . $logo);
                    }
                    $logo = 'assets/images/products/' . $newFileName;
                } else {
                    $error = "Logo上传失败，请检查目录权限";
                }
            } else {
                $error = "只允许上传JPG, PNG, GIF格式的图片";
            }
        }
        
        // 处理LOGO移除
        if (isset($_POST['remove_logo']) && $_POST['remove_logo'] == 'on') {
            if ($logo && file_exists(__DIR__ . '/../../' . $logo)) {
                unlink(__DIR__ . '/../../' . $logo);
            }
            $logo = null;
        }
        
        // 验证数据
        if (empty($name)) {
            $error = "分类名称不能为空";
        } elseif ($is_flash_sale && (empty($flash_sale_start) || empty($flash_sale_end))) {
            $error = "秒杀分类必须设置开始和结束时间";
        } elseif ($is_flash_sale && strtotime($flash_sale_start) >= strtotime($flash_sale_end)) {
            $error = "秒杀结束时间必须晚于开始时间";
        } elseif ($parent_id && $parent_id == $category_id) {
            $error = "不能将自己设置为父分类";
        }
        
        if (empty($error)) {
            // 确定分类级别
            $level = $parent_id ? 2 : 1;
            
            // 准备更新SQL
            $update_sql = "UPDATE product_categories SET 
                          name = ?, 
                          logo = ?, 
                          parent_id = ?, 
                          level = ?,
                          is_flash_sale = ?, 
                          flash_sale_start = ?, 
                          flash_sale_end = ?, 
                          sort_order = ?, 
                          status = ?, 
                          updated_at = NOW()
                          WHERE id = ?";
            
            $stmt = $pdo->prepare($update_sql);
            $params = [
                $name,
                $logo,
                $parent_id,
                $level,
                $is_flash_sale,
                $flash_sale_start,
                $flash_sale_end,
                $sort_order,
                $status,
                $category_id
            ];
            
            if ($stmt->execute($params)) {
                // 记录管理员操作日志
                $admin_id = $_SESSION['admin_id'] ?? null;
                $admin_name = $_SESSION['admin_username'] ?? '未知管理员';
                $operation_details = "编辑分类：ID=" . $category_id . ", 名称=" . $name . ", 父分类=" . ($parent_id ? $parent_id : '无');
                log_admin_operation($pdo, $admin_id, $admin_name, 'edit_category', $operation_details);
                
                $_SESSION['success_msg'] = "分类 #$category_id 更新成功！";
                ob_end_clean();
                header("Location: categories.php");
                exit();
            } else {
                $error = "分类更新失败: " . implode(", ", $stmt->errorInfo());
            }
        }
    }
} catch (PDOException $e) {
    $error = "数据库错误: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑分类 - 后台管理系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #3a5ec0;
            --accent-color: #2e59d9;
            --light-color: #f8f9fc;
            --surface-color: #ffffff;
            --dark-color: #5a5c69;
            --gray-color: #858796;
            --light-gray: #dddfeb;
            --card-border: #e3e6f0;
            --card-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            --hover-shadow: 0 0.5rem 1rem rgba(78, 115, 223, 0.15);
            --transition: all 0.3s ease-in-out;
        }
        
        body {
            background-color: var(--light-color);
            color: var(--dark-color);
            font-family: 'Roboto', 'PingFang SC', 'Helvetica Neue', Arial, sans-serif;
            font-size: 0.875rem;
            line-height: 1.6;
        }
        
        .admin-container {
            max-width: 100%;
            margin: 0 auto;
            padding: 20px;
        }
        
        .admin-card {
            background-color: var(--surface-color);
            border-radius: 0.35rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 20px;
            border: 1px solid var(--card-border);
        }
        
        .admin-card-header {
            padding: 1rem 1.35rem;
            margin-bottom: 0;
            background-color: var(--surface-color);
            border-bottom: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .admin-card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }
        
        .admin-card-body {
            padding: 1.35rem;
        }
        
        .form-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            border: 1px solid var(--card-border);
            border-radius: 0.35rem;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            border-radius: 0.35rem;
            transition: var(--transition);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            transform: translateY(-1px);
            box-shadow: var(--hover-shadow);
        }
        
        .btn-outline-secondary {
            border-color: var(--card-border);
            color: var(--gray-color);
        }
        
        .btn-outline-secondary:hover {
            background-color: var(--light-gray);
            border-color: var(--card-border);
        }
        
        .alert {
            border-radius: 0.35rem;
            padding: 0.75rem 1.25rem;
            margin-bottom: 1rem;
        }
        
        .alert-danger {
            background-color: rgba(231, 74, 59, 0.1);
            border-color: rgba(231, 74, 59, 0.2);
            color: #e74a3b;
        }
        
        .alert-success {
            background-color: rgba(28, 200, 138, 0.1);
            border-color: rgba(28, 200, 138, 0.2);
            color: #1cc88a;
        }
        
        .upload-container {
            margin-bottom: 1rem;
        }
        
        .upload-preview {
            margin-top: 0.5rem;
        }
        
        .upload-preview img {
            max-width: 100px;
            max-height: 100px;
            margin-right: 10px;
            margin-bottom: 10px;
            border: 1px solid var(--card-border);
            border-radius: 0.25rem;
        }
        
        .btn-upload {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        
        .btn-upload input[type="file"] {
            position: absolute;
            top: 0;
            right: 0;
            min-width: 100%;
            min-height: 100%;
            font-size: 100px;
            text-align: right;
            filter: alpha(opacity=0);
            opacity: 0;
            outline: none;
            background: white;
            cursor: inherit;
            display: block;
        }
        
        .form-text {
            font-size: 0.75rem;
            color: var(--gray-color);
            margin-top: 0.25rem;
        }
        
        .required-field::after {
            content: " *";
            color: #e74a3b;
        }
        
        /* 自定义开关样式 */
        .form-switch .form-check-input {
            width: 3em;
            height: 1.5em;
            margin-left: 0;
            background-color: var(--light-gray);
            border-color: var(--card-border);
        }
        
        .form-switch .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        /* 响应式调整 */
        @media (max-width: 768px) {
            .admin-container {
                padding: 15px;
            }
            
            .admin-card-body {
                padding: 1rem;
            }
        }
    </style>
  <link href="../res/admin-white.css" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        <div class="admin-card">
            <div class="admin-card-header">
                <h1 class="admin-card-title">编辑分类 #<?php echo $category_id; ?></h1>
                <a href="categories.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> 返回分类列表
                </a>
            </div>
            
            <?php if (isset($error)): ?>
            <div class="admin-card-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="admin-card-body">
                <form method="post" action="category_edit.php?id=<?php echo $category_id; ?>" enctype="multipart/form-data">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label required-field">分类名称</label>
                                <input type="text" class="form-control" id="name" name="name" required 
                                       value="<?php echo htmlspecialchars($category['name']); ?>">
                                <div class="form-text">请输入分类名称，如"游戏陪玩"、"代练服务"等</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="parent_id" class="form-label">父级分类</label>
                                <select class="form-select" id="parent_id" name="parent_id">
                                    <option value="">无（一级分类）</option>
                                    <?php foreach ($parent_categories as $parent): 
                                        if ($parent['id'] == $category_id) continue; // 排除自己
                                    ?>
                                        <option value="<?php echo $parent['id']; ?>"
                                            <?php echo ($category['parent_id'] == $parent['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($parent['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">选择父级分类将自动设置为二级分类</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">分类LOGO</label>
                                <div class="upload-container">
                                    <label class="btn btn-primary btn-upload">
                                        <i class="fas fa-upload me-2"></i> 选择图片
                                        <input type="file" name="logo" accept="image/*" style="display: none;">
                                    </label>
                                    <div class="upload-preview">
                                        <?php if ($category['logo']): ?>
                                            <img src="../<?php echo htmlspecialchars($category['logo']); ?>" class="img-thumbnail">
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="checkbox" id="remove_logo" name="remove_logo">
                                                <label class="form-check-label" for="remove_logo">移除当前LOGO</label>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-text">建议尺寸：200x200像素，支持JPG, PNG, GIF格式</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="sort_order" class="form-label">排序权重</label>
                                <input type="number" class="form-control" id="sort_order" name="sort_order" 
                                       value="<?php echo htmlspecialchars($category['sort_order']); ?>">
                                <div class="form-text">数字越大排序越靠前，范围0-999</div>
                            </div>
                            
                            <div class="mb-3 form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="status" name="status" 
                                       <?php echo $category['status'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="status">启用分类</label>
                            </div>
                            
                            <div class="mb-3 form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_flash_sale" name="is_flash_sale" 
                                       <?php echo $category['is_flash_sale'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_flash_sale">秒杀分类</label>
                            </div>
                            
                            <div id="flash-sale-fields" style="<?php echo !$category['is_flash_sale'] ? 'display:none;' : ''; ?>">
                                <div class="mb-3">
                                    <label for="flash_sale_start" class="form-label">秒杀开始时间</label>
                                    <input type="datetime-local" class="form-control" id="flash_sale_start" name="flash_sale_start" 
                                           value="<?php echo $category['flash_sale_start'] ? date('Y-m-d\TH:i', strtotime($category['flash_sale_start'])) : ''; ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="flash_sale_end" class="form-label">秒杀结束时间</label>
                                    <input type="datetime-local" class="form-control" id="flash_sale_end" name="flash_sale_end" 
                                           value="<?php echo $category['flash_sale_end'] ? date('Y-m-d\TH:i', strtotime($category['flash_sale_end'])) : ''; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-save me-2"></i> 保存修改
                        </button>
                        <button type="reset" class="btn btn-outline-secondary">
                            <i class="fas fa-undo me-2"></i> 重置
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            // 秒杀分类开关事件
            $('#is_flash_sale').change(function() {
                if (this.checked) {
                    $('#flash-sale-fields').show();
                } else {
                    $('#flash-sale-fields').hide();
                }
            });
            
            // 图片预览功能
            $('input[type="file"]').change(function(e) {
                var file = e.target.files[0];
                if (file) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        $('.upload-preview').html('<img src="' + e.target.result + '" class="img-thumbnail">');
                    };
                    reader.readAsDataURL(file);
                }
            });
            
            // 表单提交验证
            $('form').submit(function() {
                var isFlashSale = $('#is_flash_sale').is(':checked');
                if (isFlashSale) {
                    var startTime = $('#flash_sale_start').val();
                    var endTime = $('#flash_sale_end').val();
                    
                    if (!startTime || !endTime) {
                        alert('秒杀分类必须设置开始和结束时间');
                        return false;
                    }
                    
                    if (new Date(startTime) >= new Date(endTime)) {
                        alert('秒杀结束时间必须晚于开始时间');
                        return false;
                    }
                }
                
                return true;
            });
        });
    </script>
</body>
</html>