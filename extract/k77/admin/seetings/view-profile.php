<?php
// 开启输出缓冲
ob_start();

// 引入数据库连接文件
require_once __DIR__.'/../../includes/common.php';

// 获取当前管理员信息
$admin_id = 1; // 假设我们只编辑ID为1的管理员
$admin = [];
try {
    $sql = "SELECT * FROM admins WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        die("管理员不存在");
    }
} catch (PDOException $e) {
    die("查询管理员信息失败: " . $e->getMessage());
}

// 处理表单提交
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_profile'])) {
            // 更新用户名
            $username = trim($_POST['username']);
            
            // 验证输入
            if (empty($username)) {
                $message = "用户名不能为空";
                $message_type = "danger";
            } else {
                $sql = "UPDATE admins SET username = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                
                if ($stmt->execute([$username, $admin_id])) {
                    $message = "用户名更新成功";
                    $message_type = "success";
                    // 更新本地数据
                    $admin['username'] = $username;
                } else {
                    $message = "更新失败";
                    $message_type = "danger";
                }
            }
        } elseif (isset($_POST['update_password'])) {
            // 更新密码
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // 验证输入
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $message = "所有密码字段都必须填写";
                $message_type = "danger";
            } elseif ($new_password !== $confirm_password) {
                $message = "新密码和确认密码不匹配";
                $message_type = "danger";
            } elseif (!password_verify($current_password, $admin['password'])) {
                $message = "当前密码不正确";
                $message_type = "danger";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE admins SET password = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                
                if ($stmt->execute([$hashed_password, $admin_id])) {
                    $message = "密码更新成功";
                    $message_type = "success";
                } else {
                    $message = "密码更新失败";
                    $message_type = "danger";
                }
            }
        }
    } catch (PDOException $e) {
        $message = "操作失败: " . $e->getMessage();
        $message_type = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员信息编辑</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #ffffff; color: #333; line-height: 1.5; padding: 20px; font-size: 13px; }
        .container { max-width: 800px; margin: 0 auto; }
        h1 { font-size: 18px; font-weight: 600; margin-bottom: 16px; color: #000; }
        h2 { font-size: 14px; font-weight: 600; color: #333; margin: 0; }
        
        .page-header {
            margin-bottom: 16px;
        }
        
        .card {
            padding: 16px;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            background: #ffffff;
        }
        
        .card-header {
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e5e5e5;
        }
        
        .card-body {
            padding: 0;
        }
        
        .nav-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            border-bottom: 1px solid #e5e5e5;
        }
        
        .nav-link {
            padding: 8px 16px;
            border: none;
            background: transparent;
            color: #666;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border-radius: 8px 8px 0 0;
            border-bottom: 2px solid transparent;
        }
        
        .nav-link:hover {
            color: #333;
        }
        
        .nav-link.active {
            color: #1976d2;
            border-bottom-color: #1976d2;
            font-weight: 600;
        }
        
        .tab-content {
            padding: 16px 0;
        }
        
        .tab-pane {
            display: none;
        }
        
        .tab-pane.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-bottom: 6px;
        }
        
        .text-danger {
            color: #d32f2f;
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
        
        .position-relative {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            cursor: pointer;
            font-size: 14px;
            transition: color 0.2s ease;
        }
        
        .toggle-password:hover {
            color: #333;
        }
        
        .mb-3 {
            margin-bottom: 16px;
        }
        
        .mt-4 {
            margin-top: 20px;
        }
        
        .d-flex {
            display: flex;
        }
        
        .justify-content-end {
            justify-content: flex-end;
        }
        
        .gap-3 {
            gap: 12px;
        }
        
        .btn {
            padding: 10px 16px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background: #f5f5f5;
            color: #333;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
        
        @media screen and (max-width: 768px) {
            body {
                padding: 16px;
            }
            
            h1 {
                font-size: 16px;
            }
            
            .card {
                padding: 12px;
            }
            
            .nav-link {
                padding: 6px 12px;
                font-size: 12px;
            }
            
            .btn {
                padding: 8px 12px;
                font-size: 12px;
            }
        }
        
        @media screen and (max-width: 480px) {
            body {
                padding: 12px;
            }
            
            h1 {
                font-size: 14px;
            }
            
            .nav-tabs {
                flex-direction: column;
                gap: 4px;
                border-bottom: none;
            }
            
            .nav-link {
                width: 100%;
                text-align: center;
                border-radius: 8px;
                border-bottom: 1px solid #e5e5e5;
            }
            
            .nav-link.active {
                border-bottom: 1px solid #1976d2;
            }
            
            .d-flex.justify-content-end {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <h1 class="page-title">管理员信息编辑</h1>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">管理员账号设置</h2>
        </div>
        
        <div class="card-body">
            <ul class="nav-tabs">
                <li class="nav-link active" data-tab="profile">个人信息</li>
                <li class="nav-link" data-tab="password">修改密码</li>
            </ul>
            
            <div class="tab-content">
                <div id="profile" class="tab-pane active">
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="username" class="form-label">用户名 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($admin['username'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-3 mt-4">
                            <button type="reset" class="btn">取消</button>
                            <button type="submit" name="update_profile" class="btn btn-primary">保存更改</button>
                        </div>
                    </form>
                </div>
                
                <div id="password" class="tab-pane">
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">当前密码 <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                <span class="toggle-password" data-target="current_password">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">新密码 <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <span class="toggle-password" data-target="new_password">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">确认新密码 <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <span class="toggle-password" data-target="confirm_password">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-3 mt-4">
                            <button type="reset" class="btn">取消</button>
                            <button type="submit" name="update_password" class="btn btn-primary">更改密码</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // 标签页切换
    document.querySelectorAll('.nav-link').forEach(tab => {
        tab.addEventListener('click', function() {
            // 移除所有active类
            document.querySelectorAll('.nav-link').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
            
            // 添加active类
            this.classList.add('active');
            document.getElementById(this.dataset.tab).classList.add('active');
        });
    });
    
    // 密码显示/隐藏切换
    document.querySelectorAll('.toggle-password').forEach(toggle => {
        toggle.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
    
    // 卡片入场动画
    document.addEventListener('DOMContentLoaded', function() {
        const card = document.querySelector('.card');
        if (card) {
            card.style.opacity = '0';
            card.style.transform = 'translateY(10px)';
            
            setTimeout(() => {
                card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 50);
        }
    });
</script>
</body>
</html>