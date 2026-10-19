<?php
// 开启输出缓冲
ob_start();

// 添加错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__.'/../../includes/session_manager.php';
require_once __DIR__.'/../../includes/common.php';
require_once __DIR__.'/../../includes/log_helper.php';

// 获取用户类型和ID并验证
$user_type = isset($_GET['type']) ? $_GET['type'] : '';
$user_id = isset($_GET['id']) ? $_GET['id'] : '';  // 不要转换成 int，保持原样

// 定义默认值常量
define('DEFAULT_ID_CARD', '未设置');
define('DEFAULT_ALIPAY', '未设置');
define('DEFAULT_QQ', '未设置');
define('DEFAULT_PHONE', '未设置');

// 修复：添加partners和eadmins类型支持
$valid_types = ['h5user', 'users', 'partners', 'eadmins'];
if (!in_array($user_type, $valid_types)) {
    header('Location: list.php');
    exit();
}

if (empty($user_id)) {
    header('Location: list.php');
    exit();
}

// 获取用户信息
$sql = "SELECT * FROM {$user_type} WHERE id = :id";

try {
    $stmt = $pdo->prepare($sql);
    // 根据ID的类型决定绑定参数的类型
    if (is_numeric($user_id)) {
        $stmt->bindValue(':id', $user_id, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':id', $user_id, PDO::PARAM_STR);
    }
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        header('Location: list.php');
        exit();
    }

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 获取表结构，了解有哪些字段
    $columns_stmt = $pdo->query("SHOW COLUMNS FROM {$user_type}");
    $table_columns = [];
    $column_types = [];
    while ($col = $columns_stmt->fetch(PDO::FETCH_ASSOC)) {
        $table_columns[] = $col['Field'];
        $column_types[$col['Field']] = $col['Type'];
    }
    
} catch (PDOException $e) {
    die("查询用户失败: " . $e->getMessage());
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = '';
    $update_fields = [];
    
    // 处理新的多标签格式
    if (isset($_POST['user_tags_json']) && in_array('user_tag', $table_columns)) {
        $tags_json = $_POST['user_tags_json'];
        $tags = json_decode($tags_json, true);
        if (is_array($tags)) {
            // 存储为JSON格式
            $update_fields['user_tag'] = $tags_json;
            // 如果有颜色字段，存储第一个标签的颜色作为默认颜色
            if (in_array('user_tag_color', $table_columns) && !empty($tags)) {
                $update_fields['user_tag_color'] = $tags[0]['color'] ?? '#ff6b00';
            }
        }
    }
    
    // 根据用户类型和表结构动态构建更新字段
        foreach ($_POST as $field => $value) {
            // 跳过非数据字段
            if (in_array($field, ['submit', 'user_tags_json'])) {
                continue;
            }
            
            // 跳过已处理的标签字段
            if ($field === 'user_tag' || $field === 'user_tag_color') {
                continue;
            }
            
            // 跳过状态字段
            if (strpos($field, 'status') !== false) {
                continue;
            }
            
            // 只更新表中存在的字段
            if (in_array($field, $table_columns)) {
                // 只更新值发生变化的字段
                $original_value = isset($user[$field]) ? $user[$field] : '';
                $new_value = trim($value);
                
                // 对于不同类型的字段进行适当比较
                if (strpos($field, 'balance') !== false || strpos($field, 'deposit') !== false || strpos($field, 'earnings') !== false) {
                    // 金额字段
                    if ((string)$original_value !== (string)$new_value) {
                        $update_fields[$field] = $new_value;
                    }
                } elseif (strpos($field, 'level') !== false || strpos($field, 'role') !== false || strpos($field, 'gender') !== false) {
                    // 整数字段（处理空值情况）
                    $new_val = empty($new_value) ? null : intval($new_value);
                    $original_val = empty($original_value) ? null : intval($original_value);
                    if ($original_val !== $new_val) {
                        $update_fields[$field] = $new_val;
                    }
                } else {
                    // 字符串字段
                    if ($original_value !== $new_value) {
                        $update_fields[$field] = $new_value;
                    }
                }
            }
        }
    
    // 检查手机号唯一性（如果表中有这些字段且值有变化）
    if (in_array('phone', $table_columns) && isset($update_fields['phone']) && $update_fields['phone'] != ($user['phone'] ?? '')) {
        $check_sql = "SELECT id FROM {$user_type} WHERE phone = ? AND id != ?";
        $stmt = $pdo->prepare($check_sql);
        $stmt->execute([$update_fields['phone'], $user_id]);
        
        if ($stmt->rowCount() > 0) {
            $error = "该手机号已被其他用户使用";
        }
    }
    
    // 检查联系电话唯一性（商家表）
    if (empty($error) && in_array('contact_phone', $table_columns) && isset($update_fields['contact_phone']) && $update_fields['contact_phone'] != ($user['contact_phone'] ?? '')) {
        $check_sql = "SELECT id FROM {$user_type} WHERE contact_phone = ? AND id != ?";
        $stmt = $pdo->prepare($check_sql);
        $stmt->execute([$update_fields['contact_phone'], $user_id]);
        
        if ($stmt->rowCount() > 0) {
            $error = "该联系电话已被其他商家使用";
        }
    }
    
    if (empty($error) && !empty($update_fields)) {
        // 构建更新SQL - 只更新存在的字段
        $update_sql = "UPDATE {$user_type} SET ";
        $params = [];
        
        foreach ($update_fields as $field => $value) {
            $update_sql .= "{$field} = ?, ";
            $params[] = $value;
        }
        
        $update_sql = rtrim($update_sql, ', ') . " WHERE id = ?";
        $params[] = $user_id;
        
        try {
            $stmt = $pdo->prepare($update_sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                // 记录管理员操作日志
                $admin_id = $_SESSION['admin_id'] ?? null;
                $admin_name = $_SESSION['admin_username'] ?? '未知管理员';
                $user_name = $user['username'] ?? $user['name'] ?? '未知用户';
                $operation_details = "编辑" . ($page_title) . "：ID=" . $user_id . ", 用户名=" . $user_name . ", 更新字段=" . implode(',', array_keys($update_fields));
                log_admin_operation($pdo, $admin_id, $admin_name, 'edit_user', $operation_details);
                
                $_SESSION['success_msg'] = "用户 #$user_id 更新成功！";
                // 重定向时保留正确的参数
                $redirect_url = "user_edit.php?type=" . urlencode($user_type) . "&id=" . urlencode($user_id);
                header("Location: " . $redirect_url);
                exit();
            } else {
                $errorInfo = $stmt->errorInfo();
                $error = "用户更新失败: " . ($errorInfo[2] ?? '未知错误');
            }
        } catch (PDOException $e) {
            $error = "用户更新失败: " . $e->getMessage();
        }
    } elseif (empty($error) && empty($update_fields)) {
        $error = "没有需要更新的字段";
    }
}

// 页面标题
$page_title = '';
if ($user_type == 'h5user') {
    $page_title = 'H5用户编辑';
} elseif ($user_type == 'users') {
    $page_title = '打手编辑';
} elseif ($user_type == 'partners') {
    $page_title = '商家编辑';
} elseif ($user_type == 'eadmins') {
    $page_title = '管理员编辑';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* 纯白色简洁风格 - 用户编辑 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', sans-serif;
            background: #ffffff;
            color: #222222;
            line-height: 1.5;
            min-height: 100vh;
            padding: 24px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        /* 页面头部 */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid #eaeaea;
        }

        .page-title {
            font-size: 24px;
            font-weight: 500;
            color: #222222;
            margin: 0;
        }

        /* 卡片样式 */
        .card {
            background: #ffffff;
            border: 1px solid #eaeaea;
        }

        .card-header {
            border-bottom: 1px solid #eaeaea;
            padding: 16px 20px;
        }

        .card-title {
            font-size: 16px;
            font-weight: 500;
            color: #222222;
            margin: 0;
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

        .col-md-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 10px;
        }

        @media screen and (max-width: 768px) {
            .col-md-6 {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }

        .mb-3 {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            color: #666666;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #eaeaea;
            font-size: 14px;
            color: #222222;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #999999;
        }

        .form-control::placeholder {
            color: #cccccc;
        }

        .form-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #eaeaea;
            font-size: 14px;
            color: #666666;
            background: #ffffff;
        }

        .form-select:focus {
            outline: none;
            border-color: #999999;
        }

        .text-danger {
            color: #666666;
        }

        .text-muted {
            font-size: 11px;
            color: #999999;
            margin-top: 4px;
            display: block;
        }

        /* 按钮样式 */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px;
            border: 1px solid #eaeaea;
            background: #ffffff;
            color: #666666;
            font-size: 14px;
            font-weight: 400;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn:hover {
            background: #fafafa;
            border-color: #999999;
            color: #222222;
        }

        .btn i {
            color: #999999;
        }

        .btn:hover i {
            color: #666666;
        }

        .btn-primary {
            background: #ffffff;
        }

        .btn-secondary {
            background: #ffffff;
        }

        .d-flex {
            display: flex;
        }

        .justify-content-between {
            justify-content: space-between;
        }

        .mt-4 {
            margin-top: 24px;
        }

        /* 消息提示 */
        .alert {
            padding: 14px 16px;
            border: 1px solid #eaeaea;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-danger {
            background: #fafafa;
            color: #666666;
        }

        .alert-success {
            background: #f0f9f0;
            border-color: #d0e0d0;
            color: #2a5a2a;
        }

        .alert i {
            color: #999999;
        }

        .alert-success i {
            color: #2a5a2a;
        }

        /* 移除所有圆角 */
        .card,
        .card-header,
        .card-body,
        .form-control,
        .form-select,
        .btn,
        .alert {
            border-radius: 0;
        }

        /* 响应式设计 */
        @media screen and (max-width: 768px) {
            body {
                padding: 20px;
            }

            .page-title {
                font-size: 22px;
            }

            .card-header {
                padding: 14px 16px;
            }

            .card-body {
                padding: 16px;
            }

            .btn {
                padding: 8px 16px;
                font-size: 13px;
            }
        }

        @media screen and (max-width: 480px) {
            body {
                padding: 16px;
            }

            .page-title {
                font-size: 20px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .d-flex.justify-content-between {
                flex-direction: column;
                gap: 10px;
            }
        }

        /* 深色模式适配 */
        @media (prefers-color-scheme: dark) {
            body {
                background: #1a1a1a;
            }

            .page-header {
                border-bottom-color: #404040;
            }

            .page-title {
                color: #e0e0e0;
            }

            .card {
                background: #1a1a1a;
                border-color: #404040;
            }

            .card-header {
                border-bottom-color: #404040;
            }

            .card-title {
                color: #e0e0e0;
            }

            .form-label {
                color: #b0b0b0;
            }

            .form-control,
            .form-select {
                background: #1a1a1a;
                border-color: #404040;
                color: #e0e0e0;
            }

            .form-control:focus,
            .form-select:focus {
                border-color: #808080;
            }

            .form-control::placeholder {
                color: #606060;
            }

            .text-danger {
                color: #b0b0b0;
            }

            .text-muted {
                color: #808080;
            }

            .btn {
                background: #1a1a1a;
                border-color: #404040;
                color: #b0b0b0;
            }

            .btn:hover {
                background: #2a2a2a;
                border-color: #808080;
                color: #e0e0e0;
            }

            .btn i {
                color: #808080;
            }

            .btn:hover i {
                color: #b0b0b0;
            }

            .alert {
                background: #2a2a2a;
                border-color: #404040;
                color: #b0b0b0;
            }

            .alert i {
                color: #808080;
            }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 卡片入场动画
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
</head>
<body>
<div class="container">
    <div class="page-header">
        <h1 class="page-title"><?php echo $page_title; ?> #<?php echo htmlspecialchars($user_id); ?></h1>
        <?php
        // 根据用户类型确定返回的列表页面
        $return_url = '';
        switch ($user_type) {
            case 'h5user':
                $return_url = '../h5user/list.php';
                break;
            case 'users':
                $return_url = '../playmate/list.php';
                break;
            case 'partners':
                $return_url = '../partner/list.php';
                break;
            default:
                $return_url = 'list.php?type=' . urlencode($user_type);
        }
        ?>
        <a href="<?php echo $return_url; ?>" class="btn">
            <i class="fas fa-arrow-left"></i> 返回列表
        </a>
    </div>
    
    <?php if (isset($error) && !empty($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_msg']; ?>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">基本信息</h5>
        </div>
        <div class="card-body">
            <form method="post" action="user_edit.php?type=<?php echo urlencode($user_type); ?>&id=<?php echo urlencode($user_id); ?>">
                <?php if ($user_type == 'h5user'): ?>
                    <!-- H5用户编辑表单 - 根据表结构动态生成 -->
                    <div class="row">
                        <div class="col-md-6">
                            <?php if (in_array('username', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="username" class="form-label">用户名</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            
                            <?php if (in_array('user_tag', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="user_tag" class="form-label">用户标签</label>
                                <input type="text" class="form-control" id="user_tag" name="user_tag" 
                                       value="<?php echo htmlspecialchars($user['user_tag'] ?? ''); ?>"
                                       placeholder="例如：金牌打手、官方认证">
                                <small class="text-muted">设置后将在用户名字下方显示此标签</small>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (in_array('phone', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="phone" class="form-label">手机号</label>
                                <input type="text" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            
                            <?php if (in_array('real_name', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="real_name" class="form-label">真实姓名</label>
                                <input type="text" class="form-control" id="real_name" name="real_name" 
                                       value="<?php echo htmlspecialchars($user['real_name'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            
                            <?php if (in_array('nickname', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="nickname" class="form-label">昵称</label>
                                <input type="text" class="form-control" id="nickname" name="nickname" 
                                       value="<?php echo htmlspecialchars($user['nickname'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            
                            <?php if (in_array('qq', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="qq" class="form-label">QQ号</label>
                                <input type="text" class="form-control" id="qq" name="qq" 
                                       value="<?php echo htmlspecialchars($user['qq'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">

                            
                            <?php if (in_array('gender', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="gender" class="form-label">性别</label>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="0" <?php echo ($user['gender'] ?? '') == '0' ? 'selected' : ''; ?>>未知</option>
                                    <option value="1" <?php echo ($user['gender'] ?? '') == '1' ? 'selected' : ''; ?>>男</option>
                                    <option value="2" <?php echo ($user['gender'] ?? '') == '2' ? 'selected' : ''; ?>>女</option>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (in_array('balance', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="balance" class="form-label">余额</label>
                                <input type="number" step="0.01" class="form-control" id="balance" name="balance" 
                                       value="<?php echo htmlspecialchars($user['balance'] ?? 0); ?>">
                            </div>
                            <?php endif; ?>
                            
                            <?php if (in_array('deposit', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="deposit" class="form-label">保证金</label>
                                <input type="number" step="0.01" class="form-control" id="deposit" name="deposit" 
                                       value="<?php echo htmlspecialchars($user['deposit'] ?? 0); ?>">
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                <?php elseif ($user_type == 'users'): ?>
                    <!-- 打手用户编辑表单 - 根据表结构动态生成 -->
                    <div class="row">
                        <div class="col-md-6">
                            <?php if (in_array('username', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="username" class="form-label">用户名</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            
                            <?php if (in_array('user_tag', $table_columns)): ?>
                            <div class="mb-3">
                                <label class="form-label">用户标签</label>
                                <div class="tag-input-area" style="border: 1px solid #eaeaea; padding: 12px; background: #fafafa;">
                                    <!-- 已添加的标签列表 -->
                                    <div id="tags_container" style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; min-height: 30px;">
                                        <?php
                                        $existing_tags = [];
                                        if (!empty($user['user_tag'])) {
                                            // 尝试解析JSON格式的标签
                                            $decoded = json_decode($user['user_tag'], true);
                                            if (is_array($decoded)) {
                                                $existing_tags = $decoded;
                                            } else {
                                                // 兼容旧格式，单个标签
                                                $existing_tags = [['text' => $user['user_tag'], 'color' => $user['user_tag_color'] ?? '#ff6b00']];
                                            }
                                        }
                                        foreach ($existing_tags as $index => $tag):
                                        ?>
                                        <span class="user-tag-item" data-index="<?php echo $index; ?>" style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; color: #fff; background: <?php echo htmlspecialchars($tag['color'] ?? '#ff6b00'); ?>;">
                                            <?php echo htmlspecialchars($tag['text']); ?>
                                            <i class="fas fa-times" onclick="removeTag(<?php echo $index; ?>)" style="cursor: pointer; font-size: 10px;"></i>
                                        </span>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- 添加新标签的输入区 -->
                                    <div style="display: flex; gap: 8px; align-items: center;">
                                        <input type="text" class="form-control" id="new_tag_input" 
                                               placeholder="输入标签名称" style="flex: 1;">
                                        <input type="color" id="new_tag_color" value="#ff6b00"
                                               style="width: 40px; height: 36px; border: 1px solid #eaeaea; cursor: pointer; padding: 2px;">
                                        <button type="button" class="btn" onclick="addTag()" style="padding: 8px 16px; white-space: nowrap;">
                                            <i class="fas fa-plus"></i> 添加
                                        </button>
                                    </div>
                                </div>
                                <small class="text-muted">输入标签名称，选择颜色，点击添加按钮。可添加多个标签。</small>
                                
                                <!-- 隐藏字段，存储标签数据 -->
                                <input type="hidden" id="user_tags_json" name="user_tags_json" value='<?php echo json_encode($existing_tags); ?>'>
                                <!-- 兼容旧字段 -->
                                <input type="hidden" id="user_tag" name="user_tag" value="<?php echo htmlspecialchars($user['user_tag'] ?? ''); ?>">
                                <input type="hidden" id="user_tag_color" name="user_tag_color" value="<?php echo htmlspecialchars($user['user_tag_color'] ?? '#ff6b00'); ?>">
                            </div>
                            <script>
                                let tags = <?php echo json_encode($existing_tags); ?>;
                                
                                function renderTags() {
                                    const container = document.getElementById('tags_container');
                                    container.innerHTML = '';
                                    
                                    tags.forEach((tag, index) => {
                                        const tagSpan = document.createElement('span');
                                        tagSpan.className = 'user-tag-item';
                                        tagSpan.setAttribute('data-index', index);
                                        tagSpan.style.cssText = 'display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; color: #fff; background: ' + (tag.color || '#ff6b00') + ';';
                                        tagSpan.innerHTML = tag.text + ' <i class="fas fa-times" onclick="removeTag(' + index + ')" style="cursor: pointer; font-size: 10px;"></i>';
                                        container.appendChild(tagSpan);
                                    });
                                    
                                    // 更新隐藏字段
                                    document.getElementById('user_tags_json').value = JSON.stringify(tags);
                                    
                                    // 兼容旧字段 - 取第一个标签
                                    if (tags.length > 0) {
                                        document.getElementById('user_tag').value = JSON.stringify(tags);
                                        document.getElementById('user_tag_color').value = tags[0].color || '#ff6b00';
                                    } else {
                                        document.getElementById('user_tag').value = '';
                                        document.getElementById('user_tag_color').value = '#ff6b00';
                                    }
                                }
                                
                                function addTag() {
                                    const input = document.getElementById('new_tag_input');
                                    const colorInput = document.getElementById('new_tag_color');
                                    const tagText = input.value.trim();
                                    
                                    if (!tagText) {
                                        alert('请输入标签名称');
                                        return;
                                    }
                                    
                                    // 检查是否已存在相同标签
                                    if (tags.some(tag => tag.text === tagText)) {
                                        alert('该标签已存在');
                                        return;
                                    }
                                    
                                    tags.push({
                                        text: tagText,
                                        color: colorInput.value
                                    });
                                    
                                    input.value = '';
                                    renderTags();
                                }
                                
                                function removeTag(index) {
                                    tags.splice(index, 1);
                                    renderTags();
                                }
                                
                                // 回车键添加标签
                                document.getElementById('new_tag_input').addEventListener('keypress', function(e) {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        addTag();
                                    }
                                });
                            </script>
                            <?php endif; ?>
                            
                            <?php if (in_array('phone', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="phone" class="form-label">手机号</label>
                                <input type="text" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            
                            <?php if (in_array('real_name', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="real_name" class="form-label">真实姓名</label>
                                <input type="text" class="form-control" id="real_name" name="real_name" 
                                       value="<?php echo htmlspecialchars($user['real_name'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            
                            <?php if (in_array('id_card', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="id_card" class="form-label">身份证号</label>
                                <input type="text" class="form-control" id="id_card" name="id_card" 
                                       value="<?php echo htmlspecialchars($user['id_card'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            
                            <?php if (in_array('alipay_account', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="alipay_account" class="form-label">支付宝账号</label>
                                <input type="text" class="form-control" id="alipay_account" name="alipay_account" 
                                       value="<?php echo htmlspecialchars($user['alipay_account'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">


                            <?php if (in_array('user_role', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="user_role" class="form-label">用户权限</label>
                                <select class="form-select" id="user_role" name="user_role">
                                    <option value="1" <?php echo ($user['user_role'] ?? '') == '1' ? 'selected' : ''; ?>>打手</option>
                                    <option value="2" <?php echo ($user['user_role'] ?? '') == '2' ? 'selected' : ''; ?>>管事</option>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (in_array('balance', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="balance" class="form-label">余额</label>
                                <input type="number" step="0.01" class="form-control" id="balance" name="balance" 
                                       value="<?php echo htmlspecialchars($user['balance'] ?? 0); ?>">
                            </div>
                            <?php endif; ?>
                            
                            <?php if (in_array('deposit', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="deposit" class="form-label">保证金</label>
                                <input type="number" step="0.01" class="form-control" id="deposit" name="deposit" 
                                       value="<?php echo htmlspecialchars($user['deposit'] ?? 0); ?>">
                            </div>
                            <?php endif; ?>
                            
                            <?php if (in_array('member_level_id', $table_columns) || in_array('member_level', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="member_level_id" class="form-label">会员等级</label>
                                <select class="form-select" id="member_level_id" name="member_level_id">
                                    <option value="" <?php echo empty($user['member_level_id']) ? 'selected' : ''; ?>>选择会员等级</option>
                                    <?php
                                    // 查询系统中已有的会员等级
                                    $level_stmt = $pdo->query("SELECT id, name FROM member_levels WHERE status = 1 ORDER BY id ASC");
                                    $levels = $level_stmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($levels as $level):
                                    ?>
                                    <option value="<?php echo $level['id']; ?>" <?php echo ($user['member_level_id'] ?? '') == $level['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($level['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (in_array('member_end_time', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="member_end_time" class="form-label">会员到期时间</label>
                                <input type="datetime-local" class="form-control" id="member_end_time" name="member_end_time" 
                                       value="<?php echo !empty($user['member_end_time']) ? date('Y-m-d\TH:i', strtotime($user['member_end_time'])) : ''; ?>">
                                <small class="text-muted">设置会员到期时间</small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                <?php elseif ($user_type == 'partners'): ?>
                    <!-- 商家编辑表单 - 根据表结构动态生成，修复status字段问题 -->
                    <div class="row">
                        <div class="col-md-6">
                            <?php if (in_array('name', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="name" class="form-label">商家名称</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            
                            <?php if (in_array('contact_person', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="contact_person" class="form-label">联系人</label>
                                <input type="text" class="form-control" id="contact_person" name="contact_person" 
                                       value="<?php echo htmlspecialchars($user['contact_person'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            
                            <?php if (in_array('contact_phone', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="contact_phone" class="form-label">联系电话</label>
                                <input type="text" class="form-control" id="contact_phone" name="contact_phone" 
                                       value="<?php echo htmlspecialchars($user['contact_phone'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            
                            <?php if (in_array('email', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="email" class="form-label">邮箱</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">

                            
                            <?php if (in_array('balance', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="balance" class="form-label">余额</label>
                                <input type="number" step="0.01" class="form-control" id="balance" name="balance"
                                       value="<?php echo htmlspecialchars($user['balance'] ?? 0); ?>">
                            </div>
                            <?php endif; ?>
                            
                            <?php if (in_array('total_earnings', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="total_earnings" class="form-label">总收益</label>
                                <input type="number" step="0.01" class="form-control" id="total_earnings" name="total_earnings" 
                                       value="<?php echo htmlspecialchars($user['total_earnings'] ?? 0); ?>">
                            </div>
                            <?php endif; ?>
                            
                            <?php if (in_array('created_at', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="created_at" class="form-label">创建时间</label>
                                <input type="text" class="form-control" id="created_at" name="created_at" 
                                       value="<?php echo htmlspecialchars($user['created_at'] ?? ''); ?>" readonly disabled>
                                <small class="text-muted">创建时间不可修改</small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                <?php elseif ($user_type == 'eadmins'): ?>
                    <!-- 管理员编辑表单 - 根据表结构动态生成 -->
                    <div class="row">
                        <div class="col-md-6">
                            <?php if (in_array('username', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="username" class="form-label">用户名</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            
                            <?php if (in_array('real_name', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="real_name" class="form-label">真实姓名</label>
                                <input type="text" class="form-control" id="real_name" name="real_name" 
                                       value="<?php echo htmlspecialchars($user['real_name'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            
                            <?php if (in_array('email', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="email" class="form-label">邮箱</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <?php if (in_array('phone', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="phone" class="form-label">手机号</label>
                                <input type="text" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            

                            
                            <?php if (in_array('role', $table_columns)): ?>
                            <div class="mb-3">
                                <label for="role" class="form-label">角色</label>
                                <input type="text" class="form-control" id="role" name="role" 
                                       value="<?php echo htmlspecialchars($user['role'] ?? '管理员'); ?>">
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="d-flex justify-content-between mt-4">
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> 保存修改
                    </button>
                    <a href="<?php echo $return_url; ?>" class="btn">
                        <i class="fas fa-times"></i> 取消
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
<?php
// 关闭输出缓冲
ob_end_flush();
?>