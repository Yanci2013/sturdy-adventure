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

// 获取搜索参数
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_field = isset($_GET['search_field']) ? $_GET['search_field'] : 'user_id';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// 分页参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20; // 每页显示20条记录
$offset = ($page - 1) * $per_page;

// 构建搜索条件
$search_conditions = "";
$search_params = [];
$params_index = 1;

// 用户ID或用户名搜索
if (!empty($search_query)) {
    if ($search_field == 'user_id') {
        $search_conditions = " WHERE r.user_id = ?";
        $search_params[] = intval($search_query);
    } elseif ($search_field == 'username') {
        $search_conditions = " WHERE h.username LIKE ?";
        $search_params[] = '%' . $search_query . '%';
    }
}

// 日期范围搜索
if (!empty($start_date)) {
    if (empty($search_conditions)) {
        $search_conditions = " WHERE";
    } else {
        $search_conditions .= " AND";
    }
    $search_conditions .= " r.create_time >= ?";
    $search_params[] = $start_date . ' 00:00:00';
}

if (!empty($end_date)) {
    if (empty($search_conditions)) {
        $search_conditions = " WHERE";
    } else {
        $search_conditions .= " AND";
    }
    $search_conditions .= " r.create_time <= ?";
    $search_params[] = $end_date . ' 23:59:59';
}

// 构建SQL查询
$base_sql = "SELECT r.*, h.username, h.nickname 
            FROM recharge_logs r 
            LEFT JOIN h5user h ON r.user_id = h.id";

$count_sql = "SELECT COUNT(*) as total 
             FROM recharge_logs r 
             LEFT JOIN h5user h ON r.user_id = h.id";

$base_sql .= $search_conditions;
$count_sql .= $search_conditions;

// 添加排序和分页
$base_sql .= " ORDER BY r.create_time DESC LIMIT ? OFFSET ?";
$search_params[] = $per_page;
$search_params[] = $offset;

try {
    // 获取总记录数
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($search_params);
    $total_row = $stmt->fetchColumn();
    $total_pages = ceil($total_row / $per_page);
    
    // 获取充值记录数据
    $stmt = $pdo->prepare($base_sql);
    
    // 绑定参数并指定类型
    $paramCount = count($search_params);
    for ($i = 0; $i < $paramCount; $i++) {
        // 最后两个参数是LIMIT和OFFSET，需要指定为整数类型
        if ($i >= $paramCount - 2) {
            $stmt->bindValue($i + 1, intval($search_params[$i]), PDO::PARAM_INT);
        } else {
            $stmt->bindValue($i + 1, $search_params[$i], PDO::PARAM_STR);
        }
    }
    
    $stmt->execute();
    $recharge_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // 如果表不存在，创建表
    if (strpos($e->getMessage(), "Table 'recharge_logs' doesn't exist") !== false) {
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
        $recharge_logs = [];
        $total_row = 0;
        $total_pages = 0;
    } else {
        die("数据库查询错误: " . $e->getMessage());
    }
}

// 计算总充值金额
$total_recharge_stmt = $pdo->prepare("SELECT SUM(amount) as total_amount FROM recharge_logs");
$total_recharge_stmt->execute();
$total_recharge_amount = $total_recharge_stmt->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>充值记录管理 - 后台管理</title>
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
        .total-info {
            margin-top: 15px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            font-size: 16px;
        }
        .search-form {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .search-form .layui-form-item {
            margin-bottom: 15px;
        }
        .search-form .layui-inline {
            margin-right: 15px;
        }
        .data-table {
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .status-success {
            color: #52c41a;
        }
        .status-pending {
            color: #fa8c16;
        }
        .status-failed {
            color: #f5222d;
        }
        .pagination {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>充值记录管理</h1>
        <div class="total-info">
            总充值金额：<span style="color: #f5222d; font-weight: bold; font-size: 20px;">¥<?php echo number_format($total_recharge_amount, 2); ?></span>
            &nbsp;&nbsp;&nbsp;&nbsp;
            总记录数：<span style="color: #1890ff; font-weight: bold;"><?php echo $total_row; ?></span>
        </div>
    </div>
    
    <div class="search-form">
        <form class="layui-form" method="get">
            <div class="layui-form-item">
                <div class="layui-inline">
                    <label class="layui-form-label">搜索方式</label>
                    <div class="layui-input-inline">
                        <select name="search_field" lay-verify="required">
                            <option value="user_id" <?php echo $search_field == 'user_id' ? 'selected' : ''; ?>>用户ID</option>
                            <option value="username" <?php echo $search_field == 'username' ? 'selected' : ''; ?>>用户名</option>
                        </select>
                    </div>
                </div>
                
                <div class="layui-inline">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="请输入搜索内容" class="layui-input" style="width: 200px;">
                </div>
                
                <div class="layui-inline">
                    <label class="layui-form-label">开始日期</label>
                    <div class="layui-input-inline">
                        <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="layui-input">
                    </div>
                </div>
                
                <div class="layui-inline">
                    <label class="layui-form-label">结束日期</label>
                    <div class="layui-input-inline">
                        <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="layui-input">
                    </div>
                </div>
                
                <button class="layui-btn layui-btn-primary">
                    <i class="layui-icon layui-icon-search"></i> 搜索
                </button>
            </div>
        </form>
    </div>
    
    <div class="data-table">
        <table class="layui-table" style="margin: 0;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>用户ID</th>
                    <th>用户名</th>
                    <th>昵称</th>
                    <th>充值金额</th>
                    <th>状态</th>
                    <th>充值时间</th>
                    <th>备注</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recharge_logs)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 50px;">暂无充值记录</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recharge_logs as $log): ?>
                        <tr>
                            <td><?php echo $log['id']; ?></td>
                            <td><?php echo $log['user_id']; ?></td>
                            <td><?php echo htmlspecialchars($log['username'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($log['nickname'] ?: '-'); ?></td>
                            <td style="color: #f5222d; font-weight: bold;">¥<?php echo number_format($log['amount'], 2); ?></td>
                            <td>
                                <span class="status-<?php echo $log['status']; ?>">
                                    <?php 
                                        switch ($log['status']) {
                                            case 'success': echo '成功'; break;
                                            case 'pending': echo '处理中'; break;
                                            case 'failed': echo '失败'; break;
                                            default: echo $log['status'];
                                        }
                                    ?>
                                </span>
                            </td>
                            <td><?php echo $log['create_time']; ?></td>
                            <td><?php echo htmlspecialchars($log['remark'] ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- 分页 -->
        <div class="pagination">
            <div class="layui-box layui-laypage layui-laypage-default">
                <?php if ($total_pages > 1): ?>
                    <a href="?page=1&search=<?php echo urlencode($search_query); ?>&search_field=<?php echo $search_field; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="layui-laypage-prev">首页</a>
                    
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_query); ?>&search_field=<?php echo $search_field; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="layui-laypage-prev">上一页</a>
                    <?php endif; ?>
                    
                    <?php 
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $start + 4);
                        if ($end - $start < 4) {
                            $start = max(1, $end - 4);
                        }
                        
                        for ($i = $start; $i <= $end; $i++):
                    ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>&search_field=<?php echo $search_field; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="<?php echo $i == $page ? 'layui-laypage-curr' : ''; ?>">
                            <em><?php echo $i; ?></em>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_query); ?>&search_field=<?php echo $search_field; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="layui-laypage-next">下一页</a>
                    <?php endif; ?>
                    
                    <a href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search_query); ?>&search_field=<?php echo $search_field; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="layui-laypage-next">末页</a>
                    
                    <span class="layui-laypage-count">共 <?php echo $total_pages; ?> 页</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="../res/layui/layui.js"></script>
    <script>
        layui.use(['form'], function() {
            var form = layui.form;
            // 初始化表单
            form.render();
        });
    </script>
</body>
</html>