<?php
require_once("../../includes/session_manager.php");
include("../../includes/common.php");
require_once("../../includes/log_helper.php");

// 检查管理员登录
if (!is_admin_logged_in()) {
    header("Location: ../login.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';
$error = '';
$announcement = null;

// 获取公告信息
if ($id > 0) {
    try {
        global $pdo;
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
            $stmt->execute([$id]);
            $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$announcement) {
                $error = '公告不存在';
            }
        }
    } catch (Exception $e) {
        $error = '获取公告失败：' . $e->getMessage();
    }
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $announcement) {
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $status = isset($_POST['status']) ? 1 : 0;
    
    if (empty($title)) {
        $error = '请输入公告标题';
    } elseif (empty($content)) {
        $error = '请输入公告内容';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE announcements SET title = ?, content = ?, status = ? WHERE id = ?");
            
            if ($stmt->execute([$title, $content, $status, $id])) {
                $message = '公告更新成功！';
                
                // 记录管理员操作日志
                $admin_id = $_SESSION['admin_id'] ?? null;
                $admin_name = $_SESSION['admin_username'] ?? '未知管理员';
                $operation_details = "编辑公告: ID=" . $id . ", 标题=" . $title;
                log_admin_operation($pdo, $admin_id, $admin_name, 'edit_announcement', $operation_details);
                
                // 更新本地数据
                $announcement['title'] = $title;
                $announcement['content'] = $content;
                $announcement['status'] = $status;
            } else {
                $error = '更新失败';
            }
        } catch (Exception $e) {
            $error = '更新失败：' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>编辑公告</title>
  <meta name="renderer" content="webkit">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #ffffff; color: #333; line-height: 1.5; padding: 20px; font-size: 13px; }
    .container { max-width: 1200px; margin: 0 auto; }
    h2 { font-size: 18px; font-weight: 600; margin-bottom: 16px; color: #000; }
    
    .form-container {
      padding: 16px;
      border: 1px solid #e5e5e5;
      border-radius: 8px;
      background: #ffffff;
    }
    
    .alert {
      padding: 12px 14px;
      border: 1px solid #e5e5e5;
      margin-bottom: 16px;
      font-size: 12px;
      display: flex;
      align-items: center;
      gap: 8px;
      border-radius: 8px;
    }
    
    .alert-success {
      border-color: #e8f5e9;
      background: #e8f5e9;
      color: #388e3c;
    }
    
    .alert-danger {
      border-color: #ffebee;
      background: #ffebee;
      color: #d32f2f;
    }
    
    .form-group {
      margin-bottom: 16px;
    }
    
    .form-group label {
      display: block;
      font-size: 12px;
      color: #666;
      margin-bottom: 6px;
    }
    
    .form-control {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid #e5e5e5;
      font-size: 13px;
      color: #333;
      transition: all 0.2s ease;
      border-radius: 8px;
      background: #ffffff;
    }
    
    .form-control:focus {
      outline: none;
      border-color: #1976d2;
      box-shadow: 0 0 0 2px rgba(25, 118, 210, 0.1);
    }
    
    .form-control::placeholder {
      color: #999;
    }
    
    textarea.form-control {
      min-height: 200px;
      resize: vertical;
    }
    
    .form-checkbox {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .form-checkbox input[type="checkbox"] {
      width: 16px;
      height: 16px;
    }
    
    .form-actions {
      display: flex;
      gap: 12px;
      margin-top: 20px;
    }
    
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 16px;
      background: #f5f5f5;
      color: #333;
      text-decoration: none;
      border-radius: 8px;
      font-size: 13px;
      transition: all 0.2s;
      border: 1px solid #e0e0e0;
      cursor: pointer;
    }
    
    .btn:hover {
      background: #e0e0e0;
    }
    
    .btn-primary {
      background: #1976d2;
      color: #ffffff;
      border-color: #1976d2;
    }
    
    .btn-primary:hover {
      background: #1565c0;
      border-color: #1565c0;
    }
    
    .empty {
      text-align: center;
      padding: 50px;
    }
    
    .empty i {
      font-size: 48px;
      color: #999;
      margin-bottom: 15px;
    }
    
    .empty p {
      color: #666;
      margin-bottom: 20px;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>编辑公告</h2>
    
    <div class="form-container">
      <?php if ($message): ?>
        <div class="alert alert-success">
          <i class="fas fa-check-circle"></i> <?php echo $message; ?>
        </div>
      <?php endif; ?>
      
      <?php if ($error): ?>
        <div class="alert alert-danger">
          <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
      <?php endif; ?>
      
      <?php if ($announcement): ?>
        <form method="post" action="">
          <div class="form-group">
            <label>公告标题</label>
            <input type="text" name="title" required placeholder="请输入公告标题" 
                   autocomplete="off" class="form-control" value="<?php echo htmlspecialchars($announcement['title']); ?>">
          </div>
          
          <div class="form-group">
            <label>公告内容</label>
            <textarea name="content" required placeholder="请输入公告内容，支持HTML" 
                      class="form-control" rows="10"><?php echo htmlspecialchars($announcement['content']); ?></textarea>
          </div>
          
          <div class="form-group">
            <label>发布状态</label>
            <div class="form-checkbox">
              <input type="checkbox" name="status" <?php echo $announcement['status'] == 1 ? 'checked' : ''; ?> value="1">
              <span><?php echo $announcement['status'] == 1 ? '发布' : '下架'; ?></span>
            </div>
          </div>
          
          <div class="form-actions">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save"></i> 保存修改
            </button>
            <a href="list.php" class="btn">返回列表</a>
          </div>
        </form>
      <?php else: ?>
        <div class="empty">
          <i class="fas fa-exclamation-circle"></i>
          <p>公告不存在或已被删除</p>
          <a href="list.php" class="btn">返回列表</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script src="../res/layui/layui.js"></script>
  <script>
    layui.use(['form'], function(){
      var form = layui.form;
    });
  </script>
</body>
</html>
