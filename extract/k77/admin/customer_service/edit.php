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
$customer_service = null;

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: list.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM customer_services WHERE id = ?");
    $stmt->execute([$id]);
    $customer_service = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer_service) {
        header('Location: list.php');
        exit;
    }
} catch (Exception $e) {
    header('Location: list.php');
    exit;
}

$edit_channel = '';
$edit_link = $customer_service['link'];
// 最多只分割第一个冒号，防止链接内部含有冒号（url带端口等）
if (strpos($customer_service['link'], ':') !== false) {
    list($edit_channel, $edit_link) = explode(':', $customer_service['link'], 2);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $channel = trim($_POST['channel'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $status = isset($_POST['status']) ? 1 : 0;

    if (empty($name)) {
        $error = '请输入客服名称';
    } elseif (empty($channel)) {
        $error = '请输入渠道';
    } elseif (empty($link)) {
        $error = '请输入客服链接';
    } else {
        try {
            $full_link = $channel . ':' . $link;
            $stmt = $pdo->prepare("UPDATE customer_services SET name = ?, type = ?, link = ?, status = ? WHERE id = ?");
            $stmt->execute([$name, 'custom', $full_link, $status, $id]);
            
            // 记录管理员操作日志
            $admin_id = $_SESSION['admin_id'] ?? null;
            $admin_name = $_SESSION['admin_username'] ?? '未知管理员';
            $operation_details = "编辑客服：ID=" . $id . ", 名称=" . $name . ", 渠道=" . $channel;
            log_admin_operation($pdo, $admin_id, $admin_name, 'edit_customer_service', $operation_details);
            
            $success = '客服更新成功';

            $stmt = $pdo->prepare("SELECT * FROM customer_services WHERE id = ?");
            $stmt->execute([$id]);
            $customer_service = $stmt->fetch(PDO::FETCH_ASSOC);

            // 重新解析回填表单
            $edit_channel = '';
            $edit_link = $customer_service['link'];
            if (strpos($customer_service['link'], ':') !== false) {
                list($edit_channel, $edit_link) = explode(':', $customer_service['link'], 2);
            }
        } catch (Exception $e) {
            $error = '更新失败：' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑客服</title>
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
            <h1>编辑客服</h1>
        </div>

        <div class="card">
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label>客服名称 *</label>
                    <input type="text" name="name" class="form-control" placeholder="例如：客服1号" required value="<?php echo htmlspecialchars($customer_service['name']); ?>">
                </div>

                <div class="form-group">
                    <label>渠道 *</label>
                    <input type="text" name="channel" class="form-control" placeholder="例如：http、QQ、Telegram等" required value="<?php echo htmlspecialchars($edit_channel); ?>">
                </div>

                <div class="form-group">
                    <label>链接 *</label>
                    <input type="text" name="link" class="form-control" placeholder="请输入完整客服链接" required value="<?php echo htmlspecialchars($edit_link); ?>">
                </div>

                <div class="form-group">
                    <div class="form-checkbox">
                        <input type="checkbox" name="status" id="status" <?php echo $customer_service['status'] == 1 ? 'checked' : ''; ?>>
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