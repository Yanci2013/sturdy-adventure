<?php
require_once 'header.php';

// 检查登录状态和打手身份
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'hunter') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$current_status = 'offline';
$is_busy = false;

// 获取当前状态
function getHunterStatus($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT online_status FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (bool)$result['online_status'] : false;
}

// 检查是否有进行中的订单
function checkActiveOrder($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE hunter_user_id = ? AND order_status = 2 LIMIT 1");
    $stmt->execute([$user_id]);
    return $stmt->rowCount() > 0;
}

// 更新状态
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $new_status = $_POST['status'] === 'online' ? 1 : 0;
    try {
        $stmt = $pdo->prepare("UPDATE users SET online_status = ? WHERE id = ?");
        $stmt->execute([$new_status, $user_id]);
        $success = '状态更新成功';
        $current_status = $new_status ? 'online' : 'offline';
    } catch (PDOException $e) {
        error_log('更新状态失败: ' . $e->getMessage());
        $error = '状态更新失败，请稍后再试';
    }
} else {
    $current_status = getHunterStatus($pdo, $user_id) ? 'online' : 'offline';
}

$is_busy = checkActiveOrder($pdo, $user_id);

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>状态管理 - 打手中心</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .status-card {
            border-radius: 12px;
            transition: all 0.3s;
        }
        .status-online {
            border-color: #10b981;
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.1);
        }
        .status-offline {
            border-color: #9ca3af;
        }
        .status-busy {
            border-color: #f59e0b;
            box-shadow: 0 0 15px rgba(245, 158, 11, 0.1);
        }
        .status-indicator {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .indicator-online {
            background-color: #10b981;
        }
        .indicator-offline {
            background-color: #9ca3af;
        }
        .indicator-busy {
            background-color: #f59e0b;
        }
        .status-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        .status-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #10b981;
        }
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        .status-tip {
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
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

    <div class="container py-4">
        <div class="row">
            <div class="col-md-3">
                <?php include 'sidebar.php'; ?>
            </div>
            <div class="col-md-9">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-signal me-2"></i>状态管理</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo $success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <?php if ($is_busy): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle me-2"></i>您有进行中的订单，暂时无法切换在线状态。
                            </div>
                        <?php endif; ?>

                        <div class="card status-card <?php echo $is_busy ? 'status-busy' : ($current_status === 'online' ? 'status-online' : 'status-offline'); ?>">
                            <div class="card-body text-center">
                                <h4 class="card-title mb-4">当前状态</h4>
                                <div class="mb-5">
                                    <?php if ($is_busy): ?>
                                        <h2><span class="status-indicator indicator-busy"></span>接单中</h2>
                                        <p class="text-muted mt-2">您有进行中的订单，完成后将自动恢复在线状态</p>
                                    <?php else: ?>
                                        <h2><span class="status-indicator <?php echo $current_status === 'online' ? 'indicator-online' : 'indicator-offline'; ?>"></span><?php echo $current_status === 'online' ? '在线' : '离线'; ?></h2>
                                        <p class="text-muted mt-2">
                                            <?php echo $current_status === 'online' ? '您当前处于在线状态，可接收新订单' : '您当前处于离线状态，用户端将隐藏您的信息'; ?>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <?php if (!$is_busy): ?>
                                    <div class="d-flex align-items-center justify-content-center">
                                        <span class="me-3"><?php echo $current_status === 'online' ? '在线' : '离线'; ?></span>
                                        <label class="status-switch">
                                            <form id="statusForm" method="post">
                                                <input type="hidden" name="status" value="<?php echo $current_status === 'online' ? 'offline' : 'online'; ?>">
                                                <input type="checkbox" id="statusToggle" <?php echo $current_status === 'online' ? 'checked' : ''; ?> <?php echo $is_busy ? 'disabled' : ''; ?>>
                                                <span class="slider"></span>
                                            </form>
                                        </label>
                                        <span class="ms-3"><?php echo $current_status === 'online' ? '离线' : '在线'; ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="alert alert-info status-tip">
                            <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>状态说明</h6>
                            <ul class="mb-0">
                                <li><strong>在线状态</strong>：用户端可见，可接收新订单</li>
                                <li><strong>离线状态</strong>：用户端隐藏，无法接收新订单</li>
                                <li><strong>接单中状态</strong>：系统自动设置，完成当前订单后恢复在线状态</li>
                                <li>离线状态下，您仍可查看历史订单和消息，但无法接收新订单</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 状态切换表单提交
        document.getElementById('statusToggle').addEventListener('change', function() {
            document.getElementById('statusForm').submit();
        });

        // 实时状态检查（每30秒）
        setInterval(function() {
            fetch('check_status.php')
                .then(response => response.json())
                .then(data => {
                    if (data.is_busy !== <?php echo $is_busy ? 'true' : 'false'; ?>) {
                        location.reload();
                    }
                })
                .catch(error => console.error('状态检查失败:', error));
        }, 30000);
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>