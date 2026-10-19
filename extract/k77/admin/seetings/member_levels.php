<?php
// 开启输出缓冲
ob_start();

// 引入数据库连接文件
require_once __DIR__.'/../../includes/session_manager.php';
require_once __DIR__.'/../../includes/common.php';
require_once __DIR__.'/../../includes/log_helper.php';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
            // 先获取要删除的会员等级信息
            $stmt = $pdo->prepare("SELECT name FROM member_levels WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $level_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 删除会员等级
            $stmt = $pdo->prepare("DELETE FROM member_levels WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            
            // 删除关联的分类
            $stmt = $pdo->prepare("DELETE FROM member_level_categories WHERE member_level_id = ?");
            $stmt->execute([$_POST['id']]);
            
            // 记录管理员操作日志
            $admin_id = $_SESSION['admin_id'] ?? null;
            $admin_name = $_SESSION['admin_username'] ?? '未知管理员';
            $operation_details = "删除会员等级：ID=" . $_POST['id'] . ", 名称=" . ($level_info['name'] ?? '未知');
            log_admin_operation($pdo, $admin_id, $admin_name, 'delete_member_level', $operation_details);
            
            $_SESSION['message'] = "会员等级删除成功";
        } else {
            // 添加或更新会员等级
            $data = [
                'name' => $_POST['name'],
                'description' => $_POST['description'],
                'trial_supported' => isset($_POST['trial_supported']) ? 1 : 0,
                'daily_price' => $_POST['daily_price'],
                'weekly_price' => $_POST['weekly_price'],
                'monthly_price' => $_POST['monthly_price'],
                'quarterly_price' => $_POST['quarterly_price'],
                'yearly_price' => $_POST['yearly_price'],
                'upgrade_price' => $_POST['upgrade_price'],
                'status' => isset($_POST['status']) ? 1 : 0,
                'level' => 1 // 添加level字段，设置默认值为1
            ];
            
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                // 更新会员等级
                $data['id'] = $_POST['id'];
                $stmt = $pdo->prepare("UPDATE member_levels SET 
                    name = :name, 
                    description = :description, 
                    trial_supported = :trial_supported, 
                    daily_price = :daily_price, 
                    weekly_price = :weekly_price, 
                    monthly_price = :monthly_price, 
                    quarterly_price = :quarterly_price, 
                    yearly_price = :yearly_price, 
                    upgrade_price = :upgrade_price, 
                    status = :status, 
                    level = :level 
                    WHERE id = :id");
                $stmt->execute($data);
                $member_level_id = $_POST['id'];
                
                // 删除旧的关联分类
                $stmt = $pdo->prepare("DELETE FROM member_level_categories WHERE member_level_id = ?");
                $stmt->execute([$member_level_id]);
                
                // 记录管理员操作日志
                $admin_id = $_SESSION['admin_id'] ?? null;
                $admin_name = $_SESSION['admin_username'] ?? '未知管理员';
                $operation_details = "编辑会员等级：ID=" . $_POST['id'] . ", 名称=" . $_POST['name'];
                log_admin_operation($pdo, $admin_id, $admin_name, 'edit_member_level', $operation_details);
                
                $_SESSION['message'] = "会员等级更新成功";
            } else {
                // 添加新会员等级
                $stmt = $pdo->prepare("INSERT INTO member_levels 
                    (name, description, trial_supported, daily_price, weekly_price, monthly_price, quarterly_price, yearly_price, upgrade_price, status, level) 
                    VALUES 
                    (:name, :description, :trial_supported, :daily_price, :weekly_price, :monthly_price, :quarterly_price, :yearly_price, :upgrade_price, :status, :level)");
                $stmt->execute($data);
                $member_level_id = $pdo->lastInsertId();
                
                // 记录管理员操作日志
                $admin_id = $_SESSION['admin_id'] ?? null;
                $admin_name = $_SESSION['admin_username'] ?? '未知管理员';
                $operation_details = "创建会员等级：ID=" . $member_level_id . ", 名称=" . $_POST['name'];
                log_admin_operation($pdo, $admin_id, $admin_name, 'create_member_level', $operation_details);
                
                $_SESSION['message'] = "会员等级添加成功";
            }
            
            // 添加新的关联分类
            if (isset($_POST['categories']) && is_array($_POST['categories'])) {
                $stmt = $pdo->prepare("INSERT INTO member_level_categories (member_level_id, category_id) VALUES (?, ?)");
                foreach ($_POST['categories'] as $category_id) {
                    $stmt->execute([$member_level_id, $category_id]);
                }
            }
        }
        
        $pdo->commit();
        header("Location: member_levels.php");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "操作失败: " . $e->getMessage();
    }
}

// 获取所有会员等级
$member_levels = [];
try {
    $stmt = $pdo->query("SELECT * FROM member_levels ORDER BY id DESC");
    $member_levels = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "获取会员等级失败: " . $e->getMessage();
}

// 获取所有商品分类（从product_categories表获取一级分类）
$categories = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM product_categories WHERE level = 1 AND status = 1 ORDER BY sort_order, name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "获取商品分类失败: " . $e->getMessage();
}

// 获取编辑的会员等级信息
$edit_level = null;
$edit_categories = [];
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM member_levels WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit_level = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($edit_level) {
            $stmt = $pdo->prepare("SELECT category_id FROM member_level_categories WHERE member_level_id = ?");
            $stmt->execute([$_GET['edit']]);
            $edit_categories = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        }
    } catch (PDOException $e) {
        $error = "获取会员等级信息失败: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>会员等级管理</title>
    <link href="https://fonts.googleapis.com/css2?family=Ma+Shan+Zheng&family=Noto+Serif+SC:wght@400;500;600;700&family=ZCOOL+XiaoWei&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #ffffff;
            color: #333;
            line-height: 1.5;
            padding: 20px;
            font-size: 13px;
        }

        .app-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            padding: 16px;
            background: #ffffff;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .header-title {
            font-size: 18px;
            font-weight: 600;
            color: #000;
        }

        .alert {
            padding: 10px 16px;
            margin: 12px 0;
            border-radius: 4px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-success {
            background: #f0f9ff;
            border: 1px solid #e0f2fe;
            color: #0369a1;
        }

        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fee2e2;
            color: #b91c1c;
        }

        .card {
            background: #ffffff;
            border-radius: 4px;
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid #eee;
        }

        .card-header {
            font-size: 16px;
            font-weight: 600;
            color: #000;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #eee;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #666;
            margin-bottom: 6px;
        }

        .form-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            color: #333;
            background: #ffffff;
        }

        .form-input:focus {
            outline: none;
            border-color: #1E9FFF;
            box-shadow: 0 0 0 2px rgba(30, 159, 255, 0.1);
        }

        .form-textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            color: #333;
            background: #ffffff;
            min-height: 100px;
            resize: vertical;
        }

        .form-textarea:focus {
            outline: none;
            border-color: #1E9FFF;
            box-shadow: 0 0 0 2px rgba(30, 159, 255, 0.1);
        }

        .price-input {
            max-width: 200px;
        }

        .form-select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            color: #333;
            background: #ffffff;
            cursor: pointer;
        }

        .form-select:focus {
            outline: none;
            border-color: #1E9FFF;
            box-shadow: 0 0 0 2px rgba(30, 159, 255, 0.1);
        }

        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .checkbox-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            font-size: 13px;
            color: #666;
            padding: 6px 12px;
            background: #f8f8f8;
            border-radius: 16px;
            transition: all 0.3s;
        }

        .checkbox-item:hover {
            background: #f0f0f0;
        }

        .checkbox-item input {
            display: none;
        }

        .checkbox-item input:checked + span {
            color: #1E9FFF;
            font-weight: 500;
        }

        .checkbox-item:has(input:checked) {
            background: #e6f7ff;
            color: #1E9FFF;
        }

        .checkbox-item:has(input:checked) span {
            color: #1E9FFF;
        }

        .radio-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .radio-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            font-size: 13px;
            color: #666;
            padding: 8px 16px;
            background: #ffffff;
            border: 1px solid #ddd;
            border-radius: 16px;
            transition: all 0.3s;
        }

        .radio-item:hover {
            border-color: #1E9FFF;
            background: #f0f9ff;
        }

        .radio-item input {
            display: none;
        }

        .radio-item:has(input:checked) {
            background: #1E9FFF;
            border-color: #1E9FFF;
            color: white;
        }

        .switch {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .switch input {
            display: none;
        }

        .switch-slider {
            position: relative;
            width: 48px;
            height: 24px;
            background: #e0e0e0;
            border-radius: 12px;
            transition: all 0.3s;
        }

        .switch-slider::before {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            background: #ffffff;
            border-radius: 50%;
            top: 2px;
            left: 2px;
            transition: all 0.3s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .switch input:checked + .switch-slider {
            background: #1E9FFF;
        }

        .switch input:checked + .switch-slider::before {
            left: 26px;
        }

        .switch-label {
            font-size: 13px;
            color: #666;
        }

        .btn {
            display: inline-block;
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff;
            color: #333;
            text-decoration: none;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn:hover {
            background: #f0f0f0;
        }

        .btn-primary {
            background: #1E9FFF;
            color: white;
            border-color: #1E9FFF;
        }

        .btn-primary:hover {
            background: #1C86EE;
            border-color: #1C86EE;
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #666;
        }

        .btn-secondary:hover {
            background: #e8e8e8;
        }

        .btn-danger {
            background: #FF4D4F;
            color: white;
            border-color: #FF4D4F;
        }

        .btn-danger:hover {
            background: #CF1322;
            border-color: #CF1322;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 11px;
        }

        .btn-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .table th {
            background: #f8f8f8;
            color: #666;
            font-weight: 500;
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .table td {
            padding: 8px 12px;
            border-bottom: 1px solid #f0f0f0;
            color: #666;
        }

        .table tbody tr:hover {
            background: #f9f9f9;
        }

        .badge {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 500;
        }

        .badge-success {
            background: #f0fdf4;
            color: #16a34a;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-secondary {
            background: #f3f4f6;
            color: #4b5563;
        }

        .text-center {
            text-align: center;
        }

        .commission-settings {
            margin-top: 12px;
            padding: 16px;
            background: #f8f8f8;
            border-radius: 4px;
        }

        .sub-card {
            background: #f8f8f8;
            border-radius: 4px;
            padding: 16px;
            margin-top: 12px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-icon {
            font-size: 48px;
            color: #cccccc;
            margin-bottom: 12px;
        }

        .empty-message {
            color: #999;
            font-size: 15px;
        }

        ::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: #dddddd;
            border-radius: 2px;
        }

        @media (max-width: 768px) {
            body {
                padding: 16px;
            }

            .header-title {
                font-size: 16px;
            }

            .card {
                padding: 14px;
            }

            .form-input,
            .form-textarea,
            .form-select {
                padding: 8px 12px;
                font-size: 13px;
            }

            .price-input {
                max-width: 100%;
            }

            .table {
                font-size: 11px;
            }

            .table th,
            .table td {
                padding: 6px 8px;
            }

            .btn {
                padding: 8px 16px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <h1 class="header-title">🎮 会员等级管理</h1>
    </header>

    <main class="app-container">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <span>✓</span>
                <?= htmlspecialchars($_SESSION['message']) ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <span>✕</span>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><?= isset($edit_level) ? '✏️ 编辑会员等级' : '➕ 添加新会员等级' ?></div>
            
            <form method="post" action="member_levels.php">
                <?php if (isset($edit_level)): ?>
                    <input type="hidden" name="id" value="<?= htmlspecialchars($edit_level['id']) ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label class="form-label">会员等级名称</label>
                    <input type="text" name="name" placeholder="请输入会员等级名称" 
                        value="<?= isset($edit_level) ? htmlspecialchars($edit_level['name']) : '' ?>" 
                        class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">会员等级描述</label>
                    <textarea name="description" placeholder="请输入会员等级描述" class="form-textarea"><?= isset($edit_level) ? htmlspecialchars($edit_level['description']) : '' ?></textarea>
                </div>
                
                <div class="sub-card">
                    <div class="card-header" style="border-bottom: none; padding-bottom: 0; margin-bottom: 12px;">💰 价格设置</div>
                    
                    <div class="form-group">
                        <label class="form-label">日付价格</label>
                        <input type="number" name="daily_price" step="0.01" min="0" 
                            value="<?= isset($edit_level) ? htmlspecialchars($edit_level['daily_price']) : '0.00' ?>" 
                            class="form-input price-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">周付价格</label>
                        <input type="number" name="weekly_price" step="0.01" min="0" 
                            value="<?= isset($edit_level) ? htmlspecialchars($edit_level['weekly_price']) : '0.00' ?>" 
                            class="form-input price-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">月付价格</label>
                        <input type="number" name="monthly_price" step="0.01" min="0" 
                            value="<?= isset($edit_level) ? htmlspecialchars($edit_level['monthly_price']) : '0.00' ?>" 
                            class="form-input price-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">季度价格</label>
                        <input type="number" name="quarterly_price" step="0.01" min="0" 
                            value="<?= isset($edit_level) ? htmlspecialchars($edit_level['quarterly_price']) : '0.00' ?>" 
                            class="form-input price-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">年度价格</label>
                        <input type="number" name="yearly_price" step="0.01" min="0" 
                            value="<?= isset($edit_level) ? htmlspecialchars($edit_level['yearly_price']) : '0.00' ?>" 
                            class="form-input price-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">体验升级价格</label>
                        <input type="number" name="upgrade_price" step="0.01" min="0" 
                            value="<?= isset($edit_level) ? htmlspecialchars($edit_level['upgrade_price']) : '0.00' ?>" 
                            class="form-input price-input">
                    </div>
                </div>
                
                <div class="sub-card">
                    <div class="card-header" style="border-bottom: none; padding-bottom: 0; margin-bottom: 12px;">⚙️ 其他设置</div>
                    
                    <div class="form-group">
                        <label class="form-label">支持体验会员</label>
                        <label class="switch">
                            <input type="checkbox" name="trial_supported" <?= (isset($edit_level) && $edit_level['trial_supported'] == 1) ? 'checked' : '' ?>>
                            <span class="switch-slider"></span>
                            <span class="switch-label"><?= (isset($edit_level) && $edit_level['trial_supported'] == 1) ? '已支持' : '不支持' ?></span>
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">启用状态</label>
                        <label class="switch">
                            <input type="checkbox" name="status" <?= (!isset($edit_level) || (isset($edit_level) && $edit_level['status'] == 1)) ? 'checked' : '' ?>>
                            <span class="switch-slider"></span>
                            <span class="switch-label"><?= (!isset($edit_level) || (isset($edit_level) && $edit_level['status'] == 1)) ? '已启用' : '已禁用' ?></span>
                        </label>
                    </div>
                </div>
                
                <div class="sub-card">
                    <div class="card-header" style="border-bottom: none; padding-bottom: 0; margin-bottom: 12px;">🏷️ 关联商品分类</div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <?php foreach ($categories as $category): ?>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="categories[]" id="category_<?= $category['id'] ?>" value="<?= htmlspecialchars($category['id']) ?>"
                                        <?= (isset($edit_level) && in_array($category['id'], $edit_categories)) ? 'checked' : '' ?>>
                                    <span><?= htmlspecialchars($category['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                            <?php if (empty($categories)): ?>
                                <div style="color: #999; padding: 8px;">暂无分类</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary"><?= isset($edit_level) ? '✓ 更新会员等级' : '➕ 添加会员等级' ?></button>
                    <?php if (isset($edit_level)): ?>
                        <a href="member_levels.php" class="btn btn-secondary">✕ 取消编辑</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">📋 会员等级列表</div>
            
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>等级名称</th>
                            <th>支持体验</th>
                            <th>月付价格</th>
                            <th>年付价格</th>
                            <th>返佣设置</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($member_levels as $level): ?>
                            <tr>
                                <td><?= htmlspecialchars($level['id']) ?></td>
                                <td><strong><?= htmlspecialchars($level['name']) ?></strong></td>
                                <td>
                                    <span class="badge <?= $level['trial_supported'] ? 'badge-success' : 'badge-secondary' ?>">
                                        <?= $level['trial_supported'] ? '是' : '否' ?>
                                    </span>
                                </td>
                                <td>¥<?= htmlspecialchars($level['monthly_price']) ?></td>
                                <td>¥<?= htmlspecialchars($level['yearly_price']) ?></td>
                                <td>
                                    <?php if ($level['commission_enabled']): ?>
                                        <?php if ($level['commission_type'] == 1): ?>
                                            <span class="badge badge-success">比例: <?= $level['commission_rate'] ?>%</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">固定: ¥<?= $level['fixed_commission_amount'] ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">未启用</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $level['status'] ? 'badge-success' : 'badge-warning' ?>">
                                        <?= $level['status'] ? '启用' : '禁用' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="member_levels.php?edit=<?= htmlspecialchars($level['id']) ?>" class="btn btn-secondary btn-sm">编辑</a>
                                        <form method="post" action="member_levels.php" style="display: inline-block;">
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($level['id']) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('确定要删除这个会员等级吗？');">删除</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($member_levels)): ?>
                            <tr>
                                <td colspan="8" class="text-center">
                                    <div class="empty-state">
                                        <div class="empty-icon">📋</div>
                                        <div class="empty-message">暂无会员等级数据</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        // 监听返佣类型切换
        document.querySelectorAll('input[name="commission_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value == '1') {
                    document.getElementById('commission-rate-settings').style.display = 'block';
                    document.getElementById('fixed-commission-settings').style.display = 'none';
                } else {
                    document.getElementById('commission-rate-settings').style.display = 'none';
                    document.getElementById('fixed-commission-settings').style.display = 'block';
                }
            });
        });

        // 监听开关状态，更新标签文字
        document.querySelectorAll('.switch input[type="checkbox"]').forEach(switchInput => {
            const updateLabel = () => {
                const label = switchInput.parentElement.querySelector('.switch-label');
                if (label) {
                    const parentFormGroup = switchInput.closest('.form-group');
                    const formLabel = parentFormGroup.querySelector('.form-label')?.textContent || '';
                    
                    if (formLabel.includes('启用返佣')) {
                        label.textContent = switchInput.checked ? '已启用' : '未启用';
                    } else if (formLabel.includes('支持体验')) {
                        label.textContent = switchInput.checked ? '已支持' : '不支持';
                    } else if (formLabel.includes('启用状态')) {
                        label.textContent = switchInput.checked ? '已启用' : '已禁用';
                    }
                }
            };
            
            switchInput.addEventListener('change', updateLabel);
            updateLabel();
        });


    </script>
</body>
</html>
