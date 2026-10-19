<?php
include 'header.php';

function generatePeihuKey($length = 16) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $key = '';
    for ($i = 0; $i < $length; $i++) {
        $key .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $key;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_key') {
    try {
        $key_code = '';
        $maxAttempts = 10;
        $attempt = 0;
        
        do {
            $key_code = generatePeihuKey();
            $stmt = $pdo->prepare("SELECT id FROM peihu_keys WHERE key_code = ?");
            $stmt->execute([$key_code]);
            $exists = $stmt->fetch();
            $attempt++;
        } while ($exists && $attempt < $maxAttempts);
        
        if ($attempt >= $maxAttempts) {
            throw new Exception('生成密钥失败，请重试');
        }
        
        $generator_type = ($userData['user_role'] == 2) ? 'eadmin' : 'user';
        $stmt = $pdo->prepare("INSERT INTO peihu_keys (key_code, generator_type, generator_id, generator_name, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
        $stmt->execute([$key_code, $generator_type, $user_id, $userData['username']]);
        
        $success = '密钥生成成功：' . $key_code;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_key' && isset($_POST['key_id'])) {
    try {
        $key_id = intval($_POST['key_id']);
        $generator_type = ($userData['user_role'] == 2) ? 'eadmin' : 'user';
        
        $stmt = $pdo->prepare("UPDATE peihu_keys SET status = 'canceled' WHERE id = ? AND generator_type = ? AND generator_id = ? AND status = 'active'");
        $stmt->execute([$key_id, $generator_type, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            $success = '密钥已作废';
        } else {
            $error = '密钥作废失败或密钥不存在';
        }
    } catch (Exception $e) {
        $error = '操作失败';
    }
}

$generator_type = ($userData['user_role'] == 2) ? 'eadmin' : 'user';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM peihu_keys WHERE generator_type = ? AND generator_id = ?");
$stmt->execute([$generator_type, $user_id]);
$total = $stmt->fetchColumn();
$totalPages = ceil($total / $perPage);

$stmt = $pdo->prepare("SELECT * FROM peihu_keys WHERE generator_type = ? AND generator_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute([$generator_type, $user_id, $perPage, $offset]);
$keys = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>陪护密钥管理</title>
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
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2); }
        .btn-danger { background: #ff4d4f; color: white; }
        .btn-danger:hover { background: #ff7875; }
        .btn-sm { padding: 8px 14px; font-size: 13px; }
        .key-item {
            padding: 16px;
            border: 1px solid #f0f0f0;
            border-radius: 12px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .key-info { flex: 1; }
        .key-code {
            font-size: 18px;
            font-weight: 700;
            color: #667eea;
            font-family: monospace;
            letter-spacing: 2px;
        }
        .key-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 8px;
            font-size: 12px;
            color: #666;
        }
        .key-meta-item { display: flex; align-items: center; gap: 4px; }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-active { background: #e8f5e9; color: #4cd964; }
        .status-used { background: #e3f2fd; color: #2196f3; }
        .status-canceled { background: #ffebee; color: #ff4d4f; }
        .status-expired { background: #fff3e0; color: #ff9800; }
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
            <h1>陪护密钥管理</h1>
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
                <i class="fas fa-plus-circle" style="color: #667eea;"></i>
                生成新密钥
            </div>
            <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                生成陪护密钥后，陪护人员可以使用该密钥注册账号，注册后由您管理。
            </p>
            <form method="post" action="">
                <input type="hidden" name="action" value="generate_key">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-key"></i> 生成陪护密钥
                </button>
            </form>
        </div>

        <div class="card">
            <div class="card-title">
                <i class="fas fa-list" style="color: #52c41a;"></i>
                我的密钥
            </div>
            
            <?php if (empty($keys)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>暂无密钥记录</p>
                </div>
            <?php else: ?>
                <?php foreach ($keys as $key): 
                    $statusMap = [
                        'active' => ['class' => 'status-active', 'text' => '可用'],
                        'used' => ['class' => 'status-used', 'text' => '已使用'],
                        'canceled' => ['class' => 'status-canceled', 'text' => '已作废'],
                        'expired' => ['class' => 'status-expired', 'text' => '已过期']
                    ];
                    $statusInfo = $statusMap[$key['status']] ?? $statusMap['active'];
                ?>
                    <div class="key-item">
                        <div class="key-info">
                            <div class="key-code"><?php echo htmlspecialchars($key['key_code']); ?></div>
                            <div class="key-meta">
                                <div class="key-meta-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo date('Y-m-d H:i', strtotime($key['created_at'])); ?></span>
                                </div>
                                <span class="status-badge <?php echo $statusInfo['class']; ?>">
                                    <?php echo $statusInfo['text']; ?>
                                </span>
                                <?php if ($key['status'] === 'used' && $key['used_at']): ?>
                                    <div class="key-meta-item">
                                        <i class="fas fa-check"></i>
                                        <span>使用时间：<?php echo date('Y-m-d H:i', strtotime($key['used_at'])); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($key['status'] === 'active'): ?>
                            <form method="post" action="" style="margin-left: 10px;">
                                <input type="hidden" name="action" value="cancel_key">
                                <input type="hidden" name="key_id" value="<?php echo $key['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('确定要作废这个密钥吗？');">
                                    <i class="fas fa-times"></i> 作废
                                </button>
                            </form>
                        <?php endif; ?>
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
