<?php
/**
 * 游戏大区管理
 */

// 安全启动Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 引入session管理器
require_once __DIR__ . '/../../includes/session_manager.php';

// 检查管理员登录
if (!is_admin_logged_in()) {
    header('Location: ../login.php');
    exit;
}

// 引入配置文件
require_once __DIR__ . '/../../config.php';

// 数据库连接
$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

// 处理添加大区
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = trim($_POST['region_name'] ?? '');
    $sort_order = intval($_POST['sort_order'] ?? 0);
    
    if (empty($name)) {
        $error = "大区名称不能为空";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO game_regions (name, sort_order, status) VALUES (?, ?, 1)");
            $stmt->execute([$name, $sort_order]);
            $success = "游戏大区添加成功";
        } catch (PDOException $e) {
            $error = "添加失败: " . $e->getMessage();
        }
    }
}

// 处理删除大区
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM game_regions WHERE id = ?");
            $stmt->execute([$id]);
            $success = "游戏大区删除成功";
        } catch (PDOException $e) {
            $error = "删除失败: " . $e->getMessage();
        }
    }
}

// 处理更新大区
if (isset($_POST['action']) && $_POST['action'] === 'update') {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['region_name'] ?? '');
    $sort_order = intval($_POST['sort_order'] ?? 0);
    
    if ($id > 0 && !empty($name)) {
        try {
            $stmt = $pdo->prepare("UPDATE game_regions SET name = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$name, $sort_order, $id]);
            $success = "游戏大区更新成功";
        } catch (PDOException $e) {
            $error = "更新失败: " . $e->getMessage();
        }
    }
}

// 处理切换状态
if (isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $id = intval($_POST['id'] ?? 0);
    
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE game_regions SET status = 1 - status WHERE id = ?");
            $stmt->execute([$id]);
            $success = "状态切换成功";
        } catch (PDOException $e) {
            $error = "状态切换失败: " . $e->getMessage();
        }
    }
}

// 获取所有大区
$regions = $pdo->query("SELECT * FROM game_regions ORDER BY sort_order DESC, id ASC")->fetchAll();

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>游戏大区管理</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/layui/2.8.17/layui.min.css">
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

        h1 {
            font-size: 18px;
            color: #000;
            margin-bottom: 20px;
            font-weight: 600;
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

        .btn-success {
            background: #52C41A;
            color: white;
            border-color: #52C41A;
        }

        .btn-success:hover {
            background: #389E0D;
            border-color: #389E0D;
        }

        .btn-warning {
            background: #FAAD14;
            color: white;
            border-color: #FAAD14;
        }

        .btn-warning:hover {
            background: #D48806;
            border-color: #D48806;
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

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fee2e2;
            color: #b91c1c;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            color: #333;
            background: #ffffff;
        }

        .form-control:focus {
            outline: none;
            border-color: #1E9FFF;
            box-shadow: 0 0 0 2px rgba(30, 159, 255, 0.1);
        }

        .form-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-right: 16px;
        }

        .form-group label {
            font-size: 13px;
            color: #666;
            white-space: nowrap;
        }

        .form-inline {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 24px;
        }

        th {
            background: #f8f8f8;
            color: #666;
            font-weight: 500;
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        td {
            padding: 8px 12px;
            border-bottom: 1px solid #f0f0f0;
        }

        tr:hover {
            background: #f9f9f9;
        }

        .status-badge {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 500;
        }

        .status-active {
            background: #f0fdf4;
            color: #16a34a;
        }

        .status-inactive {
            background: #fef2f2;
            color: #b91c1c;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 4px;
            padding: 20px;
            width: 90%;
            max-width: 400px;
        }

        .modal-header {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #333;
        }

        .modal-footer {
            margin-top: 16px;
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-group label {
            display: block;
            margin-bottom: 4px;
        }

        .form-group .form-control {
            width: 100%;
        }
    </style>
  <link href="../res/admin-white.css" rel="stylesheet">
</head>
<body>
    <h1><i class="fas fa-globe"></i> 游戏大区管理</h1>
    
    <?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div style="margin-bottom: 20px;">
        <form method="POST" class="form-inline">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>大区名称:</label>
                <input type="text" name="region_name" class="form-control" placeholder="例如: QQ区" required>
            </div>
            <div class="form-group">
                <label>排序:</label>
                <input type="number" name="sort_order" class="form-control" value="0" placeholder="数字越大越靠前">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus"></i> 添加大区
            </button>
        </form>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>大区名称</th>
                <th>排序</th>
                <th>状态</th>
                <th>创建时间</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
                        <?php if (empty($regions)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #999;">暂无大区数据</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($regions as $region): ?>
                        <tr>
                            <td><?php echo $region['id']; ?></td>
                            <td><?php echo htmlspecialchars($region['name']); ?></td>
                            <td><?php echo $region['sort_order']; ?></td>
                            <td>
                                <span class="status-badge <?php echo $region['status'] == 1 ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $region['status'] == 1 ? '启用' : '禁用'; ?>
                                </span>
                            </td>
                            <td><?php echo $region['created_at']; ?></td>
                            <td class="actions">
                                <button class="btn btn-warning btn-sm" onclick="editRegion(<?php echo htmlspecialchars(json_encode($region)); ?>)">
                                    <i class="fas fa-edit"></i> 编辑
                                </button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?php echo $region['id']; ?>">
                                    <button type="submit" class="btn btn-sm <?php echo $region['status'] == 1 ? 'btn-danger' : 'btn-success'; ?>">
                                        <?php echo $region['status'] == 1 ? '<i class="fas fa-ban"></i> 禁用' : '<i class="fas fa-check"></i> 启用'; ?>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除这个大区吗？')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $region['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i> 删除
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- 编辑弹窗 -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-edit"></i> 编辑游戏大区
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editId">
                <div class="form-group" style="margin-bottom: 15px; width: 100%;">
                    <label style="width: 80px;">大区名称:</label>
                    <input type="text" name="region_name" id="editName" class="form-control" style="flex: 1;" required>
                </div>
                <div class="form-group" style="margin-bottom: 15px; width: 100%;">
                    <label style="width: 80px;">排序:</label>
                    <input type="number" name="sort_order" id="editSort" class="form-control" style="flex: 1;">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeEditModal()" style="background: #999; color: white;">取消</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> 保存</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function editRegion(data) {
            document.getElementById('editId').value = data.id;
            document.getElementById('editName').value = data.name;
            document.getElementById('editSort').value = data.sort_order;
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    </script>
</body>
</html>