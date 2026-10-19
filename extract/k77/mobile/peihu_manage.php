<?php
include 'header.php';

$error = '';
$success = '';

$generator_type = ($userData['user_role'] == 2) ? 'eadmin' : 'user';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM peihu_users WHERE manager_type = ? AND manager_id = ?");
$stmt->execute([$generator_type, $user_id]);
$total = $stmt->fetchColumn();
$totalPages = ceil($total / $perPage);

$stmt = $pdo->prepare("SELECT * FROM peihu_users WHERE manager_type = ? AND manager_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute([$generator_type, $user_id, $perPage, $offset]);
$peihus = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>陪护管理</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            padding-bottom: 80px;
            padding-top: 10px;
        }
        .app-container { max-width: 100%; margin: 0 auto; padding: 0 12px; }
        .header {
            background: white;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            margin-bottom: 15px;
            border-radius: 16px;
        }
        .header .back-btn {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
            text-decoration: none;
        }
        .header h1 { font-size: 18px; color: #333; }
        .alert { padding: 14px; border-radius: 12px; margin-bottom: 15px; font-size: 14px; }
        .alert-error { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffa39e; }
        .alert-success { background: #f6ffed; color: #52c41a; border: 1px solid #b7eb8f; }
        .card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .peihu-item {
            padding: 16px;
            border: 1px solid #f0f0f0;
            border-radius: 12px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .peihu-info { flex: 1; }
        .peihu-name {
            font-size: 17px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        .peihu-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 12px;
            color: #666;
        }
        .peihu-meta-item { display: flex; align-items: center; gap: 4px; }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-active { background: #e8f5e9; color: #4cd964; }
        .status-disabled { background: #ffebee; color: #ff4d4f; }
        .peihu-actions { display: flex; gap: 8px; }
        .btn {
            padding: 8px 14px;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover { transform: translateY(-2px); }
        .btn-danger { background: #ff4d4f; color: white; }
        .btn-danger:hover { background: #ff7875; }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .empty-state i { font-size: 64px; margin-bottom: 16px; }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 20px 0;
        }
        .pagination a, .pagination span {
            padding: 10px 16px;
            border-radius: 10px;
            background: white;
            color: #333;
            text-decoration: none;
            font-size: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .pagination .active { background: #667eea; color: white; }
        .pagination .disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
    <link rel="stylesheet" href="../assets/css/theme-v2.css">
</head>
<body>
<!-- V2 加载动画 -->
<div class="v2-loader-overlay" id="v2Loader">
    <div class="v2-loader-logo-wrap">
        <div class="v2-loader-ring"></div>
        <div class="v2-loader-ring"></div>
        <div class="v2-loader-logo">小</div>
    </div>
    <div class="v2-loader-text">
        <span>加</span><span>载</span><span>中</span><span>...</span>
    </div>
    <div class="v2-loader-progress">
        <div class="v2-loader-progress-bar"></div>
    </div>
</div>

    <div class="app-container">
        <div class="header">
            <a href="profile2.php" class="back-btn"><i class="fas fa-chevron-left"></i></a>
            <h1>陪护管理</h1>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-title">
                <i class="fas fa-users" style="color: #52c41a;"></i>
                我的陪护
            </div>
            
            <?php if (empty($peihus)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>暂无陪护人员</p>
                    <p style="font-size: 13px; margin-top: 10px;">请先在「陪护密钥」中生成密钥</p>
                </div>
            <?php else: ?>
                <?php foreach ($peihus as $peihu): 
                    $statusMap = [
                        'active' => ['class' => 'status-active', 'text' => '正常'],
                        'disabled' => ['class' => 'status-disabled', 'text' => '禁用']
                    ];
                    $statusInfo = $statusMap[$peihu['status']] ?? $statusMap['active'];
                ?>
                    <div class="peihu-item">
                        <div class="peihu-info">
                            <div class="peihu-name"><?php echo htmlspecialchars($peihu['username']); ?></div>
                            <div class="peihu-meta">
                                <div class="peihu-meta-item">
                                    <i class="fas fa-id-card"></i>
                                    <span>ID: <?php echo $peihu['id']; ?></span>
                                </div>
                                <div class="peihu-meta-item">
                                    <i class="fas fa-wallet"></i>
                                    <span>余额: ¥<?php echo number_format($peihu['balance'], 2); ?></span>
                                </div>
                                <div class="peihu-meta-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo date('Y-m-d', strtotime($peihu['created_at'])); ?></span>
                                </div>
                                <span class="status-badge <?php echo $statusInfo['class']; ?>">
                                    <?php echo $statusInfo['text']; ?>
                                </span>
                            </div>
                            <?php if ($peihu['real_name'] || $peihu['phone']): ?>
                                <div class="peihu-meta" style="margin-top: 6px;">
                                    <?php if ($peihu['real_name']): ?>
                                        <div class="peihu-meta-item">
                                            <i class="fas fa-user"></i>
                                            <span><?php echo htmlspecialchars($peihu['real_name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($peihu['phone']): ?>
                                        <div class="peihu-meta-item">
                                            <i class="fas fa-phone"></i>
                                            <span><?php echo htmlspecialchars($peihu['phone']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="peihu-actions">
                            <button class="btn btn-primary" onclick="alert('功能开发中');">
                                <i class="fas fa-edit"></i> 详情
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>"><i class="fas fa-chevron-left"></i></a>
                        <?php else: ?>
                            <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                        <?php endif; ?>
                        
                        <span class="active"><?php echo $page; ?></span>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>"><i class="fas fa-chevron-right"></i></a>
                        <?php else: ?>
                            <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php require __DIR__ . '/footer.php'; ?>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
