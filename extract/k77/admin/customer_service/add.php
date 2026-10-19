<?php
require_once __DIR__.'/../../includes/session_manager.php';
require_once __DIR__.'/../../includes/common.php';
require_once __DIR__.'/../../includes/log_helper.php';

if (!is_admin_logged_in()) {
    header('Location: ../login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $status = isset($_POST['status']) ? 1 : 0;

    if (empty($name)) {
        $error = '请输入客服名称';
    } elseif (empty($link)) {
        $error = '请输入完整跳转链接或域名';
    } else {
        // 自动补充 https://
        if (!preg_match('/^https?:\/\//i', $link)) {
            $link = 'https://' . $link;
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO customer_services (name, link, status) VALUES (?, ?, ?)");
            $stmt->execute([$name, $link, $status]);
            $service_id = $pdo->lastInsertId();
            
            $admin_id = $_SESSION['admin_id'] ?? null;
            $admin_name = $_SESSION['admin_username'] ?? '未知管理员';
            $operation_details = "添加客服：ID=" . $service_id . ", 名称=" . $name;
            log_admin_operation($pdo, $admin_id, $admin_name, 'add_customer_service', $operation_details);
            
            header('Location: list.php');
            exit;
        } catch (Exception $e) {
            $error = '添加失败：' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>添加客服</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #ffffff; color: #333; line-height: 1.5; padding: 20px; font-size: 13px; }
        .container { max-width: 600px; margin: 0 auto; }
        h1 { font-size: 18px; font-weight: 600; margin-bottom: 16px; color: #000; }
        
        .page-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .back-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            background: #f5f5f5;
            border: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #333;
            transition: all 0.2s;
        }
        
        .back-btn:hover {
            background: #e0e0e0;
        }
        
        .card {
            padding: 16px;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            background: #ffffff;
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
        
        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-checkbox input[type="checkbox"] {
            width: 16px;
            height: 16px;
        }
        
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        
        .btn {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
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
        
        .tips {
            font-size: 11px;
            color: #999;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <a href="list.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
            <h1>添加客服</h1>
        </div>

        <div class="card">
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label>客服名称 *</label>
                    <input type="text" name="name" class="form-control" placeholder="例如：客服1号" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>外部跳转链接/域名 *</label>
                    <input type="text" name="link" class="form-control" placeholder="zs2.xiaoxiakjwl.com 或 https://xxx.com" required value="<?php echo htmlspecialchars($_POST['link'] ?? ''); ?>">
                    <div class="tips">可直接填写域名，系统会自动补充 https://</div>
                </div>

                <div class="form-group">
                    <div class="form-checkbox">
                        <input type="checkbox" name="status" id="status" checked>
                        <label for="status">启用此客服</label>
                    </div>
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
</body>
</html>