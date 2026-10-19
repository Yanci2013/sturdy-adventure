<?php
ob_start();

require_once __DIR__.'/../../includes/common.php';

// 处理审核操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_user'])) {
    $user_id = $_POST['user_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    $review_note = $_POST['review_note'] ?? '';

    if ($user_id && in_array($action, ['approve', 'reject'])) {
        try {
            $pdo->beginTransaction();

            $approval_status = ($action === 'approve') ? 1 : 2;
            $update_sql = "UPDATE users SET approval_status = ?, approved_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($update_sql);
            $stmt->execute([$approval_status, $user_id]);

            $admin_id = $_SESSION['admin_id'] ?? null;
            $admin_type = $_SESSION['admin_type'] ?? 'admin';

            $user_stmt = $pdo->prepare("SELECT username, phone, nickname, avatar FROM users WHERE id = ?");
            $user_stmt->execute([$user_id]);
            $user_info = $user_stmt->fetch(PDO::FETCH_ASSOC);

            $check_record_sql = "SELECT id FROM user_approval_records WHERE user_id = ? ORDER BY id DESC LIMIT 1";
            $check_record_stmt = $pdo->prepare($check_record_sql);
            $check_record_stmt->execute([$user_id]);
            $existing_record = $check_record_stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing_record) {
                $update_record_sql = "UPDATE user_approval_records SET status = ?, reviewer_id = ?, reviewer_type = ?, review_note = ?, reviewed_at = NOW(), updated_at = NOW() WHERE id = ?";
                $update_record_stmt = $pdo->prepare($update_record_sql);
                $update_record_stmt->execute([$approval_status, $admin_id, $admin_type, $review_note, $existing_record['id']]);
            } else {
                $insert_record_sql = "INSERT INTO user_approval_records (user_id, username, phone, nickname, avatar, status, reviewer_id, reviewer_type, review_note, reviewed_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                $insert_record_stmt = $pdo->prepare($insert_record_sql);
                $insert_record_stmt->execute([
                    $user_id,
                    $user_info['username'] ?? '',
                    $user_info['phone'] ?? '',
                    $user_info['nickname'] ?? '',
                    $user_info['avatar'] ?? '',
                    $approval_status,
                    $admin_id,
                    $admin_type,
                    $review_note
                ]);
            }

            $pdo->commit();
            $action_text = ($action === 'approve') ? '通过' : '拒绝';
            $_SESSION['success_message'] = "用户审核{$action_text}成功！";
            header("Location: approval_list.php?status=" . (isset($_GET['status']) ? $_GET['status'] : 'pending'));
            exit();
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("用户审核失败: " . $e->getMessage());
            $_SESSION['error_message'] = "用户审核失败，请稍后重试";
            header("Location: approval_list.php?status=" . (isset($_GET['status']) ? $_GET['status'] : 'pending'));
            exit();
        }
    }
}

// 获取审核状态筛选
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';

// 分页参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

try {
    // 构建查询条件
    $where_conditions = [];
    
    if ($status_filter === 'pending') {
        $where_conditions[] = "u.approval_status = 0";
    } elseif ($status_filter === 'approved') {
        $where_conditions[] = "u.approval_status = 1";
    } elseif ($status_filter === 'rejected') {
        $where_conditions[] = "u.approval_status = 2";
    }
    
    $where_sql = '';
    if (!empty($where_conditions)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    // 获取总数
    $count_sql = "SELECT COUNT(*) as total FROM users u {$where_sql}";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute();
    $total_users = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_users / $per_page);
    
    // 获取用户列表
    $sql = "SELECT u.id, u.username, u.phone, u.nickname, u.avatar, u.created_at, 
            u.approval_status, u.submitted_for_approval_at, u.approved_at,
            ar.reviewer_id, ar.reviewer_type, ar.review_note, ar.reviewed_at
            FROM users u
            LEFT JOIN (
                SELECT id, user_id, reviewer_id, reviewer_type, review_note, reviewed_at
                FROM user_approval_records 
                WHERE id IN (
                    SELECT MAX(id) FROM user_approval_records GROUP BY user_id
                )
            ) ar ON u.id = ar.user_id
            {$where_sql}
            ORDER BY u.created_at DESC 
            LIMIT :per_page OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 获取各状态数量
    $stmt_pending = $pdo->query("SELECT COUNT(*) as count FROM users WHERE approval_status = 0");
    $pending_count = $stmt_pending->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt_approved = $pdo->query("SELECT COUNT(*) as count FROM users WHERE approval_status = 1");
    $approved_count = $stmt_approved->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt_rejected = $pdo->query("SELECT COUNT(*) as count FROM users WHERE approval_status = 2");
    $rejected_count = $stmt_rejected->fetch(PDO::FETCH_ASSOC)['count'];
    
} catch (PDOException $e) {
    die("查询失败: " . $e->getMessage());
}

// 页面标题
$pageTitle = '用户审核管理';
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
        /* 白色主题 - 用户审核管理 */
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
            border: none;
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

        /* 统计卡片 */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: #ffffff;
            border: none;
            border-radius: 12px;
            padding: 20px;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .stat-card.active {
            border: none;
            background: linear-gradient(135deg, #f0f7ff 0%, #ffffff 100%);
        }

        .stat-card.pending {
            border-left: none;
        }

        .stat-card.approved {
            border-left: none;
        }

        .stat-card.rejected {
            border-left: none;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 12px;
        }

        .stat-card.pending .stat-icon {
            background: #fff3cd;
            color: #856404;
        }

        .stat-card.approved .stat-icon {
            background: #d4edda;
            color: #155724;
        }

        .stat-card.rejected .stat-icon {
            background: #f8d7da;
            color: #721c24;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 13px;
            color: #666;
        }

        /* 标签页 */
        .nav-tabs {
            display: flex;
            border-bottom: 1px solid #eaeaea;
            margin-bottom: 0;
            padding: 0 20px;
        }

        .nav-item {
            list-style: none;
            margin-right: 4px;
        }

        .nav-link {
            display: block;
            padding: 10px 16px;
            border: 1px solid transparent;
            border-bottom: none;
            color: #666;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.3s ease;
            border-radius: 12px 12px 0 0;
        }

        .nav-link:hover {
            color: #333;
            background: #f8f9fa;
        }

        .nav-link.active {
            color: #007bff;
            background: #ffffff;
            border-color: #eaeaea;
            border-bottom-color: #ffffff;
            font-weight: 500;
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
            color: #666;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        /* 用户头像 */
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #999;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-detail .username {
            font-weight: 500;
            color: #333;
            margin-bottom: 2px;
        }

        .user-detail .user-id {
            font-size: 12px;
            color: #999;
        }

        /* 状态徽章 */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
            border: none;
        }

        .badge-approved {
            background: #d4edda;
            color: #155724;
            border: none;
        }

        .badge-rejected {
            background: #f8d7da;
            color: #721c24;
            border: none;
        }

        /* 按钮样式 */
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

        /* 提示消息 */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid #eaeaea;
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

        .alert-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: inherit;
            margin-left: auto;
        }

        /* 空状态 */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            color: #ddd;
        }

        .empty-state h4 {
            font-size: 16px;
            color: #666;
            margin-bottom: 8px;
        }

        /* 分页 */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 20px;
        }

        .page-link {
            padding: 8px 14px;
            border: 1px solid #eaeaea;
            background: #ffffff;
            color: #666;
            font-size: 14px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .page-link:hover {
            background: #f8f9fa;
            border-color: #999;
        }

        .page-link.active {
            background: #007bff;
            border-color: #007bff;
            color: #ffffff;
        }

        .page-link.disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        /* 模态框 */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-dialog {
            width: 90%;
            max-width: 480px;
        }

        .modal-content {
            background: #ffffff;
            border: none;
            border-radius: 16px;
            overflow: hidden;
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #eaeaea;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h5 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-body p {
            margin-bottom: 16px;
            color: #666;
        }

        .modal-body textarea {
            width: 100%;
            min-height: 100px;
            padding: 12px;
            border: 1px solid #eaeaea;
            border-radius: 12px;
            font-size: 14px;
            resize: vertical;
        }

        .modal-body textarea:focus {
            outline: none;
            border-color: #007bff;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #eaeaea;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .text-muted {
            color: #999;
        }

        /* 响应式 */
        @media screen and (max-width: 768px) {
            body {
                padding: 16px;
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
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <!-- 显示成功或错误消息 -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo $_SESSION['success_message']; ?>
        <button type="button" class="alert-close" onclick="this.parentElement.remove()">×</button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $_SESSION['error_message']; ?>
        <button type="button" class="alert-close" onclick="this.parentElement.remove()">×</button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- 统计卡片 -->
    <div class="stats-row">
        <a href="approval_list.php?status=pending" class="stat-card pending <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-value"><?php echo $pending_count; ?></div>
            <div class="stat-label">待审核</div>
        </a>
        <a href="approval_list.php?status=approved" class="stat-card approved <?php echo $status_filter === 'approved' ? 'active' : ''; ?>">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-value"><?php echo $approved_count; ?></div>
            <div class="stat-label">已通过</div>
        </a>
        <a href="approval_list.php?status=rejected" class="stat-card rejected <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>">
            <div class="stat-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-value"><?php echo $rejected_count; ?></div>
            <div class="stat-label">已拒绝</div>
        </a>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>
                <?php
                $status_text = '';
                if ($status_filter === 'pending') $status_text = '待审核用户';
                elseif ($status_filter === 'approved') $status_text = '已通过用户';
                elseif ($status_filter === 'rejected') $status_text = '已拒绝用户';
                else $status_text = '全部用户';
                echo $status_text;
                ?>
                <small style="color: #999; font-weight: normal; margin-left: 8px;">共 <?php echo $total_users; ?> 条记录</small>
            </h3>
        </div>

        <div class="card-body">
            <?php if (empty($users)): ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <h4>暂无用户</h4>
                <p>当前没有需要审核的用户</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>用户信息</th>
                            <th>手机号</th>
                            <th>注册时间</th>
                            <th>审核状态</th>
                            <th>审核信息</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="user-detail">
                                        <div class="username"><?php echo htmlspecialchars($user['nickname'] ?: $user['username']); ?></div>
                                        <div class="user-id">ID: <?php echo $user['id']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($user['phone'] ?: '-'); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                            <td>
                                <?php
                                $approval_status = $user['approval_status'] ?? 0;
                                $status_class = '';
                                $status_text = '';
                                switch ($approval_status) {
                                    case 0:
                                        $status_class = 'badge-pending';
                                        $status_text = '待审核';
                                        break;
                                    case 1:
                                        $status_class = 'badge-approved';
                                        $status_text = '已通过';
                                        break;
                                    case 2:
                                        $status_class = 'badge-rejected';
                                        $status_text = '已拒绝';
                                        break;
                                }
                                ?>
                                <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                            </td>
                            <td>
                                <?php if ($user['review_note']): ?>
                                <div style="font-size: 12px; color: #666;">
                                    <strong>备注：</strong><?php echo htmlspecialchars($user['review_note']); ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($user['reviewed_at']): ?>
                                <div style="font-size: 12px; color: #999; margin-top: 4px;">
                                    审核时间：<?php echo date('Y-m-d H:i', strtotime($user['reviewed_at'])); ?>
                                </div>
                                <?php elseif ($user['submitted_for_approval_at']): ?>
                                <div style="font-size: 12px; color: #999; margin-top: 4px;">
                                    提交时间：<?php echo date('Y-m-d H:i', strtotime($user['submitted_for_approval_at'])); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <?php if ($approval_status == 0): ?>
                                    <button type="button" class="btn btn-primary btn-sm"
                                            onclick="openApprovalModal('<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['nickname'] ?: $user['username']); ?>', 'approve')">
                                        <i class="fas fa-check"></i> 通过
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm"
                                            onclick="openApprovalModal('<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['nickname'] ?: $user['username']); ?>', 'reject')">
                                        <i class="fas fa-times"></i> 拒绝
                                    </button>
                                    <?php else: ?>
                                    <span class="text-muted" style="font-size: 12px;">已处理</span>
                                    <?php endif; ?>
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
                <a href="approval_list.php?status=<?php echo $status_filter; ?>&page=<?php echo $page - 1; ?>" class="page-link">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);

                if ($start > 1): ?>
                <a href="approval_list.php?status=<?php echo $status_filter; ?>&page=1" class="page-link">1</a>
                <?php if ($start > 2): ?>
                <span class="page-link disabled">...</span>
                <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                <a href="approval_list.php?status=<?php echo $status_filter; ?>&page=<?php echo $i; ?>"
                   class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>

                <?php if ($end < $total_pages): ?>
                <?php if ($end < $total_pages - 1): ?>
                <span class="page-link disabled">...</span>
                <?php endif; ?>
                <a href="approval_list.php?status=<?php echo $status_filter; ?>&page=<?php echo $total_pages; ?>" class="page-link">
                    <?php echo $total_pages; ?>
                </a>
                <?php endif; ?>

                <?php if ($page < $total_pages): ?>
                <a href="approval_list.php?status=<?php echo $status_filter; ?>&page=<?php echo $page + 1; ?>" class="page-link">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 审核模态框 -->
<div class="modal" id="approvalModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="approvalModalTitle">
                    <i class="fas fa-user-check"></i> 用户审核
                </h5>
                <button type="button" class="modal-close" onclick="closeApprovalModal()">×</button>
            </div>
            <div class="modal-body">
                <p id="approvalModalText"></p>
                <textarea id="reviewNote" placeholder="请输入审核备注（可选）..."></textarea>
            </div>
            <div class="modal-footer">
                <form method="POST" action="approval_list.php?status=<?php echo $status_filter; ?>" id="approvalForm">
                    <input type="hidden" name="user_id" id="approvalUserId">
                    <input type="hidden" name="action" id="approvalAction">
                    <input type="hidden" name="approve_user" value="1">
                    <button type="button" class="btn" onclick="closeApprovalModal()">
                        <i class="fas fa-times"></i> 取消
                    </button>
                    <button type="submit" class="btn btn-primary" id="approvalSubmitBtn">
                        <i class="fas fa-check"></i> 确认
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function openApprovalModal(userId, userName, action) {
        var actionText = action === 'approve' ? '通过' : '拒绝';
        var icon = action === 'approve' ? 'fa-check' : 'fa-times';

        document.getElementById('approvalModalTitle').innerHTML = '<i class="fas ' + icon + '"></i> 用户审核 - ' + actionText;
        document.getElementById('approvalModalText').innerHTML = '您确定要' + actionText + '用户 <strong>' + userName + '</strong> (ID: ' + userId + ') 吗？';
        document.getElementById('approvalUserId').value = userId;
        document.getElementById('approvalAction').value = action;
        document.getElementById('reviewNote').value = '';

        var submitBtn = document.getElementById('approvalSubmitBtn');
        if (action === 'approve') {
            submitBtn.className = 'btn btn-primary';
            submitBtn.innerHTML = '<i class="fas fa-check"></i> 确认通过';
        } else {
            submitBtn.className = 'btn btn-danger';
            submitBtn.innerHTML = '<i class="fas fa-times"></i> 确认拒绝';
        }

        document.getElementById('approvalModal').classList.add('show');
    }

    function closeApprovalModal() {
        document.getElementById('approvalModal').classList.remove('show');
    }

    document.getElementById('approvalModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeApprovalModal();
        }
    });

    document.getElementById('approvalForm').addEventListener('submit', function() {
        var submitBtn = document.getElementById('approvalSubmitBtn');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 处理中...';
        submitBtn.disabled = true;
        return true;
    });

    // 自动关闭警告消息
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            alert.remove();
        });
    }, 5000);
</script>
</body>
</html>
<?php ob_end_flush(); ?>
