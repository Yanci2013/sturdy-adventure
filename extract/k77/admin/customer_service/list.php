<?php
require_once __DIR__.'/../../includes/session_manager.php';
require_once __DIR__.'/../../includes/common.php';

if (!is_admin_logged_in()) {
    header('Location: ../login.php');
    exit;
}

$customer_services = [];
try {
    $stmt = $pdo->query("SELECT * FROM customer_services ORDER BY sort_order ASC, id DESC");
    $customer_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Error fetching customer services: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>客服管理</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #ffffff; color: #333; line-height: 1.5; padding: 20px; font-size: 13px; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { font-size: 18px; font-weight: 600; margin-bottom: 16px; color: #000; }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
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
        
        .card {
            padding: 16px;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            background: #ffffff;
            margin-bottom: 16px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e5e5e5;
        }
        
        .card-header h2 {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }
        
        .card-header span {
            color: #999;
            font-size: 12px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        
        th {
            background: #f8f8f8;
            color: #666;
            font-weight: 500;
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #f5f5f5;
            color: #333;
        }
        
        tr:hover td {
            background: #fafafa;
        }
        
        .status {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
        }
        
        .status.active {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .status.inactive {
            background: #ffebee;
            color: #d32f2f;
        }
        
        .actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
        }
        
        .btn-edit {
            background: #e3f2fd;
            color: #1976d2;
            border-color: #bbdefb;
        }
        
        .btn-edit:hover {
            background: #bbdefb;
        }
        
        .btn-delete {
            background: #ffebee;
            color: #d32f2f;
            border-color: #ffcdd2;
        }
        
        .btn-delete:hover {
            background: #ffcdd2;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 32px;
            margin-bottom: 12px;
        }
        
        @media (max-width: 768px) {
            table {
                font-size: 11px;
            }
            
            th, td {
                padding: 8px 10px;
            }
            
            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1>客服管理</h1>
            <a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> 添加客服</a>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>客服列表</h2>
                <span style="color:var(--text-tertiary);font-size:13px">共 <?php echo count($customer_services); ?> 个客服</span>
            </div>
            <div class="card-body">
                <?php if(empty($customer_services)): ?>
                    <div class="empty-state">
                        <i>📋</i>
                        <p>暂无客服，点击上方按钮添加</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>名称</th>
                                <th>类型</th>
                                <th>链接</th>
                                <th>排序</th>
                                <th>状态</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($customer_services as $cs): ?>
                                <tr>
                                    <td><?php echo $cs['id']; ?></td>
                                    <td><?php echo htmlspecialchars($cs['name']); ?></td>
                                    <td><?php echo htmlspecialchars($cs['type']); ?></td>
                                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo htmlspecialchars($cs['link']); ?>">
                                        <?php echo htmlspecialchars($cs['link']); ?>
                                    </td>
                                    <td><?php echo $cs['sort_order']; ?></td>
                                    <td>
                                        <span class="status <?php echo $cs['status'] == 1 ? 'active' : 'inactive'; ?>">
                                            <?php echo $cs['status'] == 1 ? '启用' : '禁用'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="edit.php?id=<?php echo $cs['id']; ?>" class="btn btn-sm btn-edit"><i class="fas fa-edit"></i> 编辑</a>
                                            <a href="delete.php?id=<?php echo $cs['id']; ?>" class="btn btn-sm btn-delete" onclick="return confirm('确定要删除这个客服吗？')"><i class="fas fa-trash"></i> 删除</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>