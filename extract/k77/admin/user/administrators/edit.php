<?php
// 开启输出缓冲
ob_start();

require_once __DIR__.'/../../../includes/session_manager.php';
include __DIR__.'/../../../includes/common.php';
// 检查log_manager.php是否存在，如果不存在则跳过
$log_manager_path = __DIR__.'/../../../includes/log_manager.php';
if (file_exists($log_manager_path)) {
    require_once $log_manager_path;
}

// 定义日志记录函数的替代实现
if (!function_exists('log_admin_action')) {
    function log_admin_action($pdo, $admin_id, $admin_name, $action, $result) {
        return true;
    }
}

if (!function_exists('log_admin_login')) {
    function log_admin_login($pdo, $admin_id, $admin_name, $result) {
        return true;
    }
}

// 获取管理员ID
$admin_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($admin_id === 0) {
    die('无效的管理员ID');
}

// 获取管理员信息
$admin = null;
try {
    $stmt = $pdo->prepare("SELECT id, username FROM admins WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        die('管理员不存在');
    }
} catch (PDOException $e) {
    die('查询管理员失败: ' . $e->getMessage());
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
    
    // 验证表单
    if (empty($username)) {
        $error = '用户名不能为空';
    } elseif (!empty($password) && $password !== $confirm_password) {
        $error = '两次输入的密码不一致';
    } else {
        try {
            // 检查用户名是否已存在（排除当前管理员）
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ? AND id != ?");
            $stmt->execute([$username, $admin_id]);
            if ($stmt->rowCount() > 0) {
                $error = '用户名已存在';
            } else {
                // 构建更新SQL
                if (!empty($password)) {
                    // 加密密码
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE admins SET username = ?, password = ? WHERE id = ?");
                    $result = $stmt->execute([$username, $hashed_password, $admin_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE admins SET username = ? WHERE id = ?");
                    $result = $stmt->execute([$username, $admin_id]);
                }
                
                if ($result) {
                    $success = '管理员更新成功';
                    // 更新当前变量
                    $admin['username'] = $username;

                } else {
                    $error = '更新管理员失败';

                }
            }
        } catch (PDOException $e) {
            $error = '数据库错误: ' . $e->getMessage();
        }
    }
}

// 页面标题
$pageTitle = '编辑管理员';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Ma+Shan+Zheng&family=Noto+Serif+SC:wght@400;500;600;700&family=ZCOOL+XiaoWei&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 白色主题 - 编辑管理员 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', sans-serif;
            background: #ffffff;
            color: #333;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            padding: 24px;
        }

        .container-fluid {
            max-width: 800px;
            margin: 0 auto;
            padding: 0;
        }

        /* 卡片样式 */
        .card {
            background: #ffffff;
            border: 1px solid #eaeaea;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .card-header {
            border-bottom: 1px solid #eaeaea;
            padding: 16px 20px;
            background: #ffffff;
        }

        .card-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin: 0 0 4px 0;
        }

        .card-header small {
            font-size: 12px;
            color: #999;
        }

        .card-body {
            padding: 20px;
        }

        /* 表单样式 */
        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #eaeaea;
            font-size: 14px;
            color: #333;
            transition: all 0.3s ease;
            border-radius: 12px;
            background: #ffffff;
        }

        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.2);
        }

        .form-control::placeholder {
            color: #cccccc;
        }

        .btn {
            padding: 10px 16px;
            border: 1px solid #eaeaea;
            background: #ffffff;
            color: #666;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            border-radius: 12px;
        }

        .btn:hover {
            background: #f8f9fa;
            border-color: #999;
            color: #333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .btn-primary {
            background: #007bff;
            border-color: #007bff;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #0069d9;
            border-color: #0069d9;
            color: #ffffff;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 8px;
        }

        .btn-group {
            display: flex;
            gap: 8px;
            margin-top: 20px;
        }

        /* 消息提示 */
        .alert {
            padding: 12px 16px;
            border: 1px solid #eaeaea;
            border-radius: 12px;
            margin-bottom: 16px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        /* 提示信息 */
        .form-text {
            font-size: 12px;
            color: #999;
            margin-top: 4px;
        }

        /* 响应式设计 */
        @media screen and (max-width: 768px) {
            body {
                padding: 20px;
            }

            .card-header h3 {
                font-size: 16px;
            }

            .card-body {
                padding: 16px;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media screen and (max-width: 480px) {
            body {
                padding: 16px;
            }

            .card-header {
                padding: 14px 16px;
            }

            .card-body {
                padding: 14px;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3><?php echo $pageTitle; ?></h3>
                    <small>编辑后台管理员账号</small>
                </div>
                
                <div class="card-body">
                    <!-- 消息提示 -->
                    <?php if (isset($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- 表单 -->
                    <form action="edit.php?id=<?php echo $admin_id; ?>" method="post">
                        <div class="form-group">
                            <label class="form-label" for="username">用户名</label>
                            <input type="text" id="username" name="username" class="form-control" 
                                   placeholder="请输入用户名" 
                                   value="<?php echo htmlspecialchars($admin['username']); ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="password">密码</label>
                            <input type="password" id="password" name="password" class="form-control" 
                                   placeholder="请输入新密码（不修改请留空）">
                            <div class="form-text">不修改密码请留空</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="confirm_password">确认密码</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                   placeholder="请再次输入新密码（不修改请留空）">
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> 保存
                            </button>
                            <a href="list.php" class="btn">
                                <i class="fas fa-times"></i> 取消
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php
// 关闭输出缓冲
ob_end_flush();
?>