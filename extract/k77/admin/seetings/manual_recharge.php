<?php
// 开启会话
session_start();

// 开启输出缓冲
ob_start();

require_once __DIR__.'/../../includes/common.php';

// 检查是否有权限访问
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    // 重定向到登录页面而不是直接die
    header('Location: ../login.php');
    exit;
}

$message = '';
$message_type = '';

// 处理充值请求
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $user_id = intval($_POST['user_id']);
        $amount = floatval($_POST['amount']);
        $remark = trim($_POST['remark']);
        
        // 验证输入
        if ($user_id <= 0) {
            throw new Exception('请输入有效的用户ID');
        }
        
        if ($amount <= 0 || $amount > 100000) {
            throw new Exception('充值金额必须在0-100000之间');
        }
        
        // 开始事务
        $pdo->beginTransaction();
        
        // 检查用户是否存在
        $check_user_stmt = $pdo->prepare("SELECT id FROM h5user WHERE id = ?");
        $check_user_stmt->execute([$user_id]);
        if ($check_user_stmt->rowCount() === 0) {
            throw new Exception('用户不存在');
        }
        
        // 检查并添加h5user表中的充值相关字段（如果不存在）
        $result = $pdo->query("SHOW COLUMNS FROM h5user LIKE 'total_recharges'");
        if ($result->rowCount() === 0) {
            $pdo->exec("ALTER TABLE h5user ADD COLUMN total_recharges DECIMAL(10,2) DEFAULT 0.00");
            $pdo->exec("ALTER TABLE h5user ADD COLUMN recharge_count INT DEFAULT 0");
            $pdo->exec("ALTER TABLE h5user ADD COLUMN last_recharge_time DATETIME DEFAULT NULL");
        }
        
        // 检查并创建充值日志表（如果不存在）
        $checkTable = $pdo->query("SHOW TABLES LIKE 'recharge_logs'");
        if ($checkTable->rowCount() === 0) {
            $pdo->exec("CREATE TABLE recharge_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                create_time DATETIME NOT NULL,
                status VARCHAR(20) NOT NULL,
                remark VARCHAR(255),
                INDEX idx_user_id (user_id),
                INDEX idx_create_time (create_time)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        
        // 更新用户余额（兼容处理，根据字段是否存在决定更新哪些字段）
        $hasRechargeFields = true;
        try {
            $result = $pdo->query("SHOW COLUMNS FROM h5user LIKE 'total_recharges'");
            $hasRechargeFields = $result->rowCount() > 0;
        } catch (Exception $e) {
            $hasRechargeFields = false;
        }
        
        if ($hasRechargeFields) {
            $update_user_stmt = $pdo->prepare("        
                UPDATE h5user SET 
                    balance = ROUND(balance + ?, 2),
                    total_recharges = ROUND(total_recharges + ?, 2),
                    recharge_count = recharge_count + 1,
                    last_recharge_time = NOW()
                WHERE id = ?
            ");
            $update_user_stmt->execute([$amount, $amount, $user_id]);
        } else {
            // 如果没有充值相关字段，只更新余额
            $update_user_stmt = $pdo->prepare("        
                UPDATE h5user SET 
                    balance = ROUND(balance + ?, 2)
                WHERE id = ?
            ");
            $update_user_stmt->execute([$amount, $user_id]);
        }
        
        // 添加充值日志
        $add_log_stmt = $pdo->prepare("        
            INSERT INTO recharge_logs (user_id, amount, create_time, status, remark)
            VALUES (?, ?, NOW(), 'success', ?)
        ");
        $add_log_stmt->execute([$user_id, $amount, '管理员手动充值：' . $remark]);
        
        // 提交事务
        $pdo->commit();
        
        $message = '充值成功！用户余额已增加 ¥' . number_format($amount, 2);
        $message_type = 'success';
        
    } catch (Exception $e) {
        // 回滚事务
        $pdo->rollBack();
        $message = '充值失败：' . $e->getMessage();
        $message_type = 'error';
    }
}

// 查询用户信息
$user_info = null;
if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT id, username, nickname, balance FROM h5user WHERE id = ?");
        $stmt->execute([intval($_GET['user_id'])]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // 忽略查询错误
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>手动充值 - 后台管理</title>
    <link rel="stylesheet" href="../res/layui/css/layui.css">
    <style>
        body {
            padding: 20px;
            background-color: #f5f5f5;
            font-family: 'Microsoft YaHei', Arial, sans-serif;
        }
        .header {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            color: #333;
            font-size: 24px;
        }
        .form-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 0 auto;
        }
        .layui-form-item {
            margin-bottom: 20px;
        }
        .layui-form-label {
            width: 100px;
        }
        .layui-input-block {
            margin-left: 130px;
        }
        .btn-group {
            margin-top: 30px;
            text-align: center;
        }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
        }
        .message.success {
            background-color: #f0f9eb;
            color: #67c23a;
            border: 1px solid #e1f3d8;
        }
        .message.error {
            background-color: #fef0f0;
            color: #f56c6c;
            border: 1px solid #fde2e2;
        }
        .user-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .user-info-item {
            margin-bottom: 10px;
        }
        .user-info-label {
            font-weight: bold;
            color: #606266;
            margin-right: 10px;
        }
        .user-balance {
            color: #f5222d;
            font-weight: bold;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>手动充值</h1>
    </div>
    
    <div class="form-container">
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form class="layui-form" method="post" onsubmit="return validateForm();">
            <div class="layui-form-item">
                <label class="layui-form-label">用户ID</label>
                <div class="layui-input-block">
                    <input type="number" name="user_id" value="<?php echo isset($user_info) ? $user_info['id'] : ''; ?>" placeholder="请输入用户ID" lay-verify="required" class="layui-input" id="user_id">
                </div>
            </div>
            
            <div class="layui-form-item">
                <div class="layui-input-block">
                    <button type="button" class="layui-btn layui-btn-primary" onclick="searchUser()">
                        <i class="layui-icon layui-icon-search"></i> 查询用户信息
                    </button>
                </div>
            </div>
            
            <?php if ($user_info): ?>
                <div class="user-info">
                    <div class="user-info-item">
                        <span class="user-info-label">用户名：</span>
                        <span><?php echo htmlspecialchars($user_info['username']); ?></span>
                    </div>
                    <div class="user-info-item">
                        <span class="user-info-label">昵称：</span>
                        <span><?php echo htmlspecialchars($user_info['nickname'] ?: '-'); ?></span>
                    </div>
                    <div class="user-info-item">
                        <span class="user-info-label">当前余额：</span>
                        <span class="user-balance">¥<?php echo number_format($user_info['balance'], 2); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="layui-form-item">
                <label class="layui-form-label">充值金额</label>
                <div class="layui-input-block">
                    <input type="number" name="amount" value="" placeholder="请输入充值金额" lay-verify="required" class="layui-input" min="0.01" max="100000" step="0.01" id="amount">
                </div>
            </div>
            
            <div class="layui-form-item">
                <label class="layui-form-label">充值备注</label>
                <div class="layui-input-block">
                    <textarea name="remark" placeholder="请输入充值备注（选填）" class="layui-textarea"></textarea>
                </div>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="layui-btn layui-btn-normal" lay-submit>
                    <i class="layui-icon layui-icon-ok"></i> 确认充值
                </button>
                <button type="button" class="layui-btn layui-btn-primary" onclick="window.location.href='recharge_logs.php'">
                    <i class="layui-icon layui-icon-list"></i> 查看充值记录
                </button>
            </div>
        </form>
    </div>
    
    <script src="../res/layui/layui.js"></script>
    <script>
        // 验证表单
        function validateForm() {
            var user_id = document.getElementById('user_id').value;
            var amount = document.getElementById('amount').value;
            
            if (!user_id || user_id <= 0) {
                alert('请输入有效的用户ID');
                return false;
            }
            
            if (!amount || amount <= 0 || amount > 100000) {
                alert('充值金额必须在0-100000之间');
                return false;
            }
            
            if (!confirm('确定要为用户ID ' + user_id + ' 充值 ¥' + amount + ' 吗？')) {
                return false;
            }
            
            return true;
        }
        
        // 查询用户信息
        function searchUser() {
            var user_id = document.getElementById('user_id').value;
            if (!user_id || user_id <= 0) {
                alert('请输入有效的用户ID');
                return;
            }
            
            window.location.href = 'manual_recharge.php?user_id=' + user_id;
        }
        
        layui.use(['form'], function() {
            var form = layui.form;
            // 初始化表单
            form.render();
        });
    </script>
</body>
</html>