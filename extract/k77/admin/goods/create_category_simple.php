<?php
// 简化版创建分类页面
ob_start();
// 确保在最开始启动 session（无任何输出前）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__.'/../../includes/common.php';

// 检查管理员登录
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

$message = '';
$message_type = '';

// 获取一级分类
$parent_categories = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM product_categories WHERE level = 1 OR level IS NULL ORDER BY name");
    $parent_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = '获取分类失败: ' . $e->getMessage();
    $message_type = 'error';
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name'] ?? '');
        $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
        $level = $parent_id > 0 ? 2 : 1;
        
        if (empty($name)) {
            throw new Exception('分类名称不能为空');
        }
        
        // 插入分类
        $stmt = $pdo->prepare("INSERT INTO product_categories (name, parent_id, level, status, sort_order, created_at) VALUES (?, ?, ?, 1, 0, NOW())");
        $stmt->execute([$name, $parent_id, $level]);
        
        $message = '分类创建成功！';
        $message_type = 'success';
    } catch (Exception $e) {
        $message = '创建分类失败: ' . $e->getMessage();
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>创建分类 - 简化版</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            margin-bottom: 30px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .btn {
            margin-right: 10px;
        }
        .alert {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>创建商品分类</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label for="name">分类名称 <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="parent_id">父级分类</label>
                <select class="form-control" id="parent_id" name="parent_id">
                    <option value="0">无（一级分类）</option>
                    <?php foreach ($parent_categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">创建分类</button>
                <a href="categories.php" class="btn btn-secondary">返回列表</a>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>