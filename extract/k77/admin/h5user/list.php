<?php
// 开启输出缓冲
ob_start();

require_once __DIR__.'/../../includes/common.php';

// 处理删除请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    
    if ($user_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM h5user WHERE id = ?");
            $stmt->execute([$user_id]);
            
            $_SESSION['success_message'] = '用户已删除';
        } catch (PDOException $e) {
            $_SESSION['error_message'] = '删除失败: ' . $e->getMessage();
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

// 构建基础SQL查询
$base_sql = "SELECT id, username, phone, nickname, status, balance, deposit, created_at FROM h5user";
$count_sql = "SELECT COUNT(*) as total FROM h5user";
$search_conditions = "";
$search_params = [];

// 如果有搜索查询，构建搜索条件
if (!empty($search_query)) {
    switch ($search_field) {
        case 'id':
            $search_conditions = " WHERE id = :search_id";
            $search_params[':search_id'] = $search_query;
            break;
        case 'username':
            $search_conditions = " WHERE username LIKE :search_term";
            $search_params[':search_term'] = "%$search_query%";
            break;
        case 'phone':
            $search_conditions = " WHERE phone LIKE :search_term";
            $search_params[':search_term'] = "%$search_query%";
            break;
        case 'nickname':
            $search_conditions = " WHERE nickname LIKE :search_term";
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
    $sql = $base_sql . $search_conditions . " ORDER BY created_at DESC LIMIT :per_page OFFSET :offset";
    
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
$pageTitle = 'H5用户列表';
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
        /* 白色主题 - H5用户列表 */
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

        .status-0 {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        .status-1 {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .status-2 {
            background: #fff3cd;
            color: #856404;
            border-color: #ffeaa7;
        }

        /* 用户头像 */
        .user-avatar {
            width: 40px;
            height: 40px;
            border: 1px solid #eaeaea;
            object-fit: cover;
            border-radius: 50%;
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
                    <small>管理H5用户账号和信息</small>
                </div>
                
                <!-- 搜索框 -->
                <div class="card-body">
                    <form class="row" action="list.php" method="get">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="输入ID、用户名、手机号或昵称..." 
                                       value="<?php echo htmlspecialchars($search_query); ?>" 
                                       required>
                                <span class="input-group-text">
                                    <select name="search_field" style="border: none; background: transparent; outline: none;">
                                        <option value="id" <?php echo $search_field == 'id' ? 'selected' : ''; ?>>ID</option>
                                        <option value="username" <?php echo $search_field == 'username' ? 'selected' : ''; ?>>用户名</option>
                                        <option value="phone" <?php echo $search_field == 'phone' ? 'selected' : ''; ?>>手机号</option>
                                        <option value="nickname" <?php echo $search_field == 'nickname' ? 'selected' : ''; ?>>昵称</option>
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
                            <h4>没有找到匹配的用户记录</h4>
                            <p>请尝试修改搜索条件</p>
                        </div>
                    <?php elseif (empty($users)): ?>
                        <div class="no-results">
                            <i class="fas fa-user-slash"></i>
                            <h4>暂无H5用户</h4>
                            <p>暂无H5用户数据</p>
                        </div>
                    <?php else: ?>
                        <!-- 用户表格 -->
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                         <th>ID</th>
                                         <th>用户名</th>
                                         <th>手机号</th>
                                         <th>昵称</th>
                                         <th>状态</th>
                                         <th>余额</th>
                                         <th>保证金</th>
                                         <th>注册时间</th>
                                         <th>操作</th>
</tr>
                                  </thead>
                                  <tbody>
                                      <?php foreach ($users as $user): ?>
                                      <tr>
                                          <td><?php echo $user['id']; ?></td>
                                          <td><?php echo htmlspecialchars($user['username']); ?></td>
                                          <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                                          <td><?php echo htmlspecialchars($user['nickname'] ?? '-'); ?></td>
                                          <td>
                                              <span class="status-badge status-<?php echo $user['status']; ?>">
                                                  <?php 
                                                  switch ($user['status']) {
                                                      case 0: echo '禁用'; break;
                                                      case 1: echo '正常'; break;
                                                      case 2: echo '冻结'; break;
                                                      default: echo '未知'; break;
                                                  }
                                                  ?>
                                              </span>
                                          </td>
                                          <td>¥<?php echo number_format($user['balance'], 2); ?></td>
                                          <td>¥<?php echo number_format($user['deposit'], 2); ?></td>
                                          <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                                          <td>
                                              <div class="btn-group">
                                                  <a href="../user/user_edit.php?type=h5user&id=<?php echo $user['id']; ?>" 
                                                     class="btn btn-sm">
                                                      <i class="fas fa-edit"></i> 编辑
                                                  </a>
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