<?php
// 开启输出缓冲
ob_start();

require_once __DIR__.'/../../includes/session_manager.php';
require_once __DIR__.'/../../includes/common.php';
require_once __DIR__.'/../../includes/log_helper.php';

// 处理删除请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    
    if ($user_id > 0) {
        try {
            // 先获取用户信息用于日志
            $user_info_stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $user_info_stmt->execute([$user_id]);
            $user_info = $user_info_stmt->fetch(PDO::FETCH_ASSOC);
            
            $pdo->beginTransaction();
            
            // 临时禁用外键检查
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // 删除关联订单
            $stmt = $pdo->prepare("DELETE FROM orders WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // 清空用户会员等级
            $stmt = $pdo->prepare("UPDATE users SET member_level_id = NULL WHERE id = ?");
            $stmt->execute([$user_id]);
            
            // 删除用户余额变动日志
            $stmt = $pdo->prepare("DELETE FROM user_balance_logs WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // 删除提现记录
            $stmt = $pdo->prepare("DELETE FROM withdrawals WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // 删除用户
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            
            // 重新启用外键检查
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            // 记录管理员操作日志
            $admin_id = $_SESSION['admin_id'] ?? null;
            $admin_name = $_SESSION['admin_username'] ?? '未知管理员';
            $operation_details = "删除打手：ID=" . $user_id . ", 用户名=" . ($user_info['username'] ?? '未知');
            log_admin_operation($pdo, $admin_id, $admin_name, 'delete_playmate', $operation_details);
            
            $pdo->commit();
            $_SESSION['success_message'] = '用户已删除';
        } catch (PDOException $e) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            $pdo->rollBack();
            $_SESSION['error_message'] = '删除失败: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = '无效的用户ID';
    }
    
    header('Location: list.php');
    exit();
}

// 处理封禁/解封用户
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_banned'])) {
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    
    if ($user_id > 0) {
        try {
            $sql = "SELECT is_banned, username FROM users WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $current_status = $result['is_banned'];
            $username = $result['username'];
            
            $new_status = $current_status ? 0 : 1;
            $update_sql = "UPDATE users SET is_banned = ?, banned_at = ?, banned_reason = ? WHERE id = ?";
            $stmt = $pdo->prepare($update_sql);
            $stmt->execute([$new_status, $new_status ? date('Y-m-d H:i:s') : null, $new_status ? '管理员封禁' : null, $user_id]);
            
            // 记录管理员操作日志
            $admin_id = $_SESSION['admin_id'] ?? null;
            $admin_name = $_SESSION['admin_username'] ?? '未知管理员';
            $operation_details = "切换打手封禁状态：ID=" . $user_id . ", 用户名=" . ($username ?? '未知') . ", 新状态=" . ($new_status ? '已封禁' : '正常');
            log_admin_operation($pdo, $admin_id, $admin_name, 'toggle_playmate_banned', $operation_details);
            
            $_SESSION['success_message'] = $new_status ? '用户已封禁' : '用户已解封';
        } catch (PDOException $e) {
            $_SESSION['error_message'] = '操作失败: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = '无效的用户ID';
    }
    
    header('Location: list.php');
    exit();
}

// 处理切换接单状态
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_available'])) {
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    
    if ($user_id > 0) {
        try {
            $sql = "SELECT available_for_orders, username FROM users WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $current_status = $result['available_for_orders'];
            $username = $result['username'];
            
            $new_status = $current_status ? 0 : 1;
            $update_sql = "UPDATE users SET available_for_orders = ? WHERE id = ?";
            $stmt = $pdo->prepare($update_sql);
            $stmt->execute([$new_status, $user_id]);
            
            // 记录管理员操作日志
            $admin_id = $_SESSION['admin_id'] ?? null;
            $admin_name = $_SESSION['admin_username'] ?? '未知管理员';
            $operation_details = "切换打手接单状态：ID=" . $user_id . ", 用户名=" . ($username ?? '未知') . ", 新状态=" . ($new_status ? '可接单' : '不可接单');
            log_admin_operation($pdo, $admin_id, $admin_name, 'toggle_playmate_status', $operation_details);
            
            $_SESSION['success_message'] = '接单状态已更新';
        } catch (PDOException $e) {
            $_SESSION['error_message'] = '操作失败: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = '无效的用户ID';
    }
    
    header('Location: list.php');
    exit();
}

// 获取搜索参数
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_field = isset($_GET['search_field']) ? $_GET['search_field'] : 'id';

// 分页参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10; // 每页显示10条记录
$offset = ($page - 1) * $per_page;

// 检查会员相关字段是否存在
$has_member_fields = false;
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'member_level_id'");
    $has_member_fields = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    // 忽略错误
}

// 构建基础SQL查询
if ($has_member_fields) {
    $base_sql = "SELECT u.id, u.username, u.phone, u.deposit, u.balance, u.available_for_orders, u.created_at, 
                   COALESCE(u.user_role, 1) as user_role,
                   COALESCE(u.last_login, '从未登录') as last_login,
                   COALESCE(ml.name, '') as member_level_name,
                   u.member_level_id, u.member_end_time,
                   COALESCE(u.is_banned, 0) as is_banned
            FROM users u
            LEFT JOIN member_levels ml ON u.member_level_id = ml.id";
} else {
    $base_sql = "SELECT u.id, u.username, u.phone, u.deposit, u.balance, u.available_for_orders, u.created_at, 
                   COALESCE(u.user_role, 1) as user_role,
                   COALESCE(u.last_login, '从未登录') as last_login,
                   u.member_level as member_level_name,
                   COALESCE(u.is_banned, 0) as is_banned
            FROM users u";
}
$count_sql = "SELECT COUNT(*) as total FROM users u";
$search_conditions = "";
$search_params = [];

// 如果有搜索查询，构建搜索条件
if (!empty($search_query)) {
    switch ($search_field) {
        case 'id':
            $search_conditions = " WHERE u.id = :search_id";
            $search_params[':search_id'] = $search_query;
            break;
        case 'username':
            $search_conditions = " WHERE u.username LIKE :search_term";
            $search_params[':search_term'] = "%$search_query%";
            break;
        case 'phone':
            $search_conditions = " WHERE u.phone LIKE :search_term";
            $search_params[':search_term'] = "%$search_query%";
            break;
    }
}

try {
    // 获取总用户数
    $stmt = $pdo->prepare($count_sql . $search_conditions);
    
    // 绑定搜索参数
    foreach ($search_params as $key => $value) {
        $param_type = ($key == ':search_id') ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $param_type);
    }
    
    $stmt->execute();
    $total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 计算总页数
    $total_pages = ceil($total_users / $per_page);
    
    // 构建数据查询SQL
    $sql = $base_sql . $search_conditions . " ORDER BY u.created_at DESC LIMIT :per_page OFFSET :offset";
    
    // 准备并执行查询
    $stmt = $pdo->prepare($sql);
    
    // 绑定搜索参数
    foreach ($search_params as $key => $value) {
        $param_type = ($key == ':search_id') ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $param_type);
    }
    
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("查询用户失败: " . $e->getMessage());
}

// 页面标题
$pageTitle = '陪玩列表';
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
        /* 白色主题 - 陪玩列表 */
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
            max-width: 1400px;
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
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: -10px;
        }

        .col-12 {
            width: 100%;
            padding: 10px;
        }

        .col-md-6 {
            width: 50%;
            padding: 10px;
        }

        @media screen and (max-width: 768px) {
            .col-md-6 {
                width: 100%;
            }
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

        .input-group {
            display: flex;
            align-items: center;
            border: 1px solid #eaeaea;
            border-radius: 12px;
            overflow: hidden;
        }

        .input-group .form-control {
            border: none;
            flex: 1;
            border-radius: 0;
        }

        .input-group-text {
            padding: 10px 12px;
            background: #f8f9fa;
            color: #666;
            border-left: 1px solid #eaeaea;
            font-size: 14px;
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

        .btn-success {
            background: #28a745;
            border-color: #28a745;
            color: #ffffff;
        }

        .btn-success:hover {
            background: #218838;
            border-color: #218838;
            color: #ffffff;
        }

        .btn-danger {
            background: #dc3545;
            border-color: #dc3545;
            color: #ffffff;
        }

        .btn-danger:hover {
            background: #c82333;
            border-color: #c82333;
            color: #ffffff;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 8px;
        }

        .btn-group {
            display: flex;
            gap: 4px;
        }

        /* 徽章样式 */
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border: 1px solid #eaeaea;
            font-size: 11px;
            color: #666;
            border-radius: 8px;
        }

        .status-enabled {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .status-disabled {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        .online-status {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
        }

        .online {
            background: #28a745;
        }

        .offline {
            background: #6c757d;
        }

        /* 表格样式 */
        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .table th {
            background: #f8f9fa;
            color: #666;
            font-weight: 500;
            padding: 14px 12px;
            border-bottom: 1px solid #eaeaea;
            text-align: left;
        }

        .table td {
            padding: 14px 12px;
            border-bottom: 1px solid #eaeaea;
            color: #333;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        /* 金额样式 */
        .amount {
            font-weight: 600;
            color: #28a745;
        }

        /* 空状态 */
        .no-results {
            text-align: center;
            padding: 60px 20px;
            background: #ffffff;
            border-radius: 12px;
            margin: 20px 0;
        }

        .no-results i {
            font-size: 48px;
            color: #cccccc;
            margin-bottom: 16px;
        }

        .no-results h4 {
            font-size: 16px;
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
        }

        .no-results p {
            font-size: 13px;
            color: #999;
        }

        /* 分页样式 */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 4px;
            margin-top: 24px;
            list-style: none;
        }

        .page-item {
            list-style: none;
        }

        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 8px;
            border: 1px solid #eaeaea;
            background: #ffffff;
            color: #666;
            font-size: 13px;
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 8px;
        }

        .page-link:hover {
            background: #f8f9fa;
            border-color: #999;
            color: #333;
            transform: translateY(-2px);
        }

        .page-item.active .page-link {
            background: #007bff;
            color: #ffffff;
            border-color: #007bff;
            font-weight: 500;
        }

        .page-item.disabled .page-link {
            opacity: 0.5;
            pointer-events: none;
        }

        /* 消息提示 */
        .alert-fixed {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            padding: 16px 20px;
            border: 1px solid #eaeaea;
            background: #ffffff;
            color: #333;
            font-size: 13px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
            animation: slideIn 0.3s ease-out;
            border-radius: 12px;
        }

        .alert-success {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .btn-close {
            float: right;
            background: none;
            border: none;
            font-size: 16px;
            cursor: pointer;
            color: #999;
        }

        .btn-close:hover {
            color: #333;
        }

        /* 响应式设计 */
        @media screen and (max-width: 768px) {
            body {
                padding: 20px;
            }

            .card-header h3 {
                font-size: 16px;
            }

            .table {
                font-size: 12px;
            }

            .table th,
            .table td {
                padding: 10px 8px;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn-sm {
                padding: 4px 8px;
                font-size: 11px;
            }

            .page-link {
                min-width: 32px;
                height: 32px;
                font-size: 12px;
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
                padding: 16px;
            }

            .table {
                display: block;
                overflow-x: auto;
            }

            .pagination {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <!-- 显示成功或错误消息 -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert-fixed alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo $_SESSION['success_message']; ?>
        <button type="button" class="btn-close" onclick="this.parentElement.remove()">×</button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert-fixed alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $_SESSION['error_message']; ?>
        <button type="button" class="btn-close" onclick="this.parentElement.remove()">×</button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3><?php echo $pageTitle; ?></h3>
                    <small>管理陪玩账号和信息，可操作接单状态和提现状态</small>
                </div>
                
                <!-- 搜索框 -->
                <div class="card-body">
                    <form class="row" action="list.php" method="get">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="输入ID、用户名或手机号..." 
                                       value="<?php echo htmlspecialchars($search_query); ?>" 
                                       required>
                                <span class="input-group-text">
                                    <select name="search_field" style="border: none; background: transparent; outline: none;">
                                        <option value="id" <?php echo $search_field == 'id' ? 'selected' : ''; ?>>ID</option>
                                        <option value="username" <?php echo $search_field == 'username' ? 'selected' : ''; ?>>用户名</option>
                                        <option value="phone" <?php echo $search_field == 'phone' ? 'selected' : ''; ?>>手机号</option>
                                    </select>
                                </span>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> 搜索
                                </button>
                                <?php if (!empty($search_query)): ?>
                                <a href="list.php" class="btn">
                                    <i class="fas fa-times"></i> 重置
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- 显示搜索结果信息 -->
                <?php if (!empty($search_query)): ?>
                <div class="card-body">
                    <div style="padding: 12px 16px; border: 1px solid #eaeaea; background: #f8f9fa;">
                        <i class="fas fa-search"></i>
                        搜索 "<span style="color: #333;"><?php echo htmlspecialchars($search_query); ?></span>" 
                        共找到 <?php echo $total_users; ?> 条记录
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="card-body">
                    <?php if (empty($users) && !empty($search_query)): ?>
                        <div class="no-results">
                            <i class="fas fa-user-slash"></i>
                            <h4>没有找到匹配的陪玩记录</h4>
                            <p>请尝试修改搜索条件</p>
                        </div>
                    <?php elseif (empty($users)): ?>
                        <div class="no-results">
                            <i class="fas fa-user-slash"></i>
                            <h4>暂无陪玩用户</h4>
                            <p>暂无陪玩用户数据</p>
                        </div>
                    <?php else: ?>
                        <!-- 陪玩表格 -->
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>昵称</th>
                                        <th>手机号码</th>
                                        <th>保证金余额</th>
                                        <th>用户余额</th>
                                        <th>陪玩会员</th>
                                        <th>用户权限</th>
                                        <th>接单状态</th>
                                        <th>加入时间</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                                        <td class="amount">¥<?php echo number_format($user['deposit'], 2); ?></td>
                                        <td class="amount">¥<?php echo number_format($user['balance'] ?? 0, 2); ?></td>
                                        <td>
                                            <?php 
                                                if (isset($user['member_level_id']) && $user['member_level_id'] && 
                                                    isset($user['member_end_time']) && !empty($user['member_end_time']) && 
                                                    strtotime($user['member_end_time']) >= time() && 
                                                    !empty($user['member_level_name'])) {
                                                    echo htmlspecialchars($user['member_level_name']) . '<br>';
                                                    echo '<small style="color: #999; font-size: 11px;">';
                                                    echo '到期: ' . date('Y-m-d', strtotime($user['member_end_time']));
                                                    echo '</small>';
                                                } else {
                                                    echo '暂未开通';
                                                }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                                // 根据用户权限字段显示角色，使用整数比较
                                                $role = intval($user['user_role'] ?? 0) == 1 ? '打手' : '管事';
                                                echo htmlspecialchars($role);
                                            ?>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $user['available_for_orders'] ? 'status-enabled' : 'status-disabled'; ?>">
                                                <?php echo $user['available_for_orders'] ? '可接单' : '不可接单'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <form method="post" action="list.php" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="toggle_available" value="1">
                                                    <button type="submit" class="btn btn-sm <?php echo $user['available_for_orders'] ? 'btn-danger' : 'btn-success'; ?>">
                                                        <i class="fas <?php echo $user['available_for_orders'] ? 'fa-times' : 'fa-check'; ?>"></i> 
                                                        <?php echo $user['available_for_orders'] ? '禁用接单' : '启用接单'; ?>
                                                    </button>
                                                </form>
                                                <a href="../user/user_edit.php?type=users&id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-sm">
                                                    <i class="fas fa-edit"></i> 编辑
                                                </a>
                                                <form method="post" action="list.php" style="display: inline;" onsubmit="return confirm('<?php echo $user['is_banned'] ? '确定要解封此用户吗？' : '确定要封禁此用户吗？封禁后用户将无法访问抢单大厅！'; ?>')">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="toggle_banned" value="1">
                                                    <button type="submit" class="btn btn-sm <?php echo $user['is_banned'] ? 'btn-success' : 'btn-danger'; ?>">
                                                        <i class="fas <?php echo $user['is_banned'] ? 'fa-unlock' : 'fa-ban'; ?>"></i> <?php echo $user['is_banned'] ? '解封' : '封禁'; ?>
                                                    </button>
                                                </form>
                                                <form method="post" action="list.php" style="display: inline;" onsubmit="return confirm('确定要删除此用户吗？删除后将无法恢复！');">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="delete_user" value="1">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i> 删除
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- 分页 -->
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="list.php?page=<?php echo $page - 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) . '&search_field=' . urlencode($search_field) : ''; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link">
                                    <i class="fas fa-chevron-left"></i>
                                </span>
                            </li>
                            <?php endif; ?>
                            
                            <?php 
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $start_page + 4);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="list.php?page=<?php echo $i; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) . '&search_field=' . urlencode($search_field) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="list.php?page=<?php echo $page + 1; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) . '&search_field=' . urlencode($search_field) : ''; ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link">
                                    <i class="fas fa-chevron-right"></i>
                                </span>
                            </li>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
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