<?php
require_once("../includes/session_manager.php");
require_once("../includes/common.php");

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// 检查用户是否已经有打手或管事身份
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header('Location: login.php');
        exit();
    }
    
    // 如果用户已经是打手或管事，跳转到首页
    if ($user['user_role'] == 1 || $user['user_role'] == 2) {
        header('Location: index.php');
        exit();
    }
    
    // 如果用户已经绑定过管事，跳转到首页
    if (!empty($user['invitation_code'])) {
        header('Location: index.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    header('Location: login.php');
    exit();
}

// 处理绑定请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eadmin_id = $_POST['eadmin_id'] ?? '';
    
    if (empty($eadmin_id)) {
        $error = "请选择上级管事";
    } else {
        // 获取邀请码
        try {
            $stmt = $pdo->prepare("SELECT * FROM invitations WHERE generator_id = ? AND generator_type = 'eadmin' AND register_type = 1 AND status = 'active' LIMIT 1");
            $stmt->execute([$eadmin_id]);
            $invitation = $stmt->fetch();
            
            if (!$invitation) {
                // 如果没有找到邀请码，尝试创建一个
                $code = bin2hex(random_bytes(8));
                $stmt = $pdo->prepare("INSERT INTO invitations (code, generator_id, generator_type, register_type, status, created_at) VALUES (?, ?, 'eadmin', 1, 'active', NOW())");
                $stmt->execute([$code, $eadmin_id]);
                $invitation = ['code' => $code];
            }
            
            $invitation_code = $invitation['code'];
            
            // 更新用户邀请码
            $stmt = $pdo->prepare("UPDATE users SET invitation_code = ? WHERE id = ?");
            $stmt->execute([$invitation_code, $user_id]);
            
            // 创建关系记录
            $stmt = $pdo->prepare("
                INSERT INTO partner_service_relations 
                (inviter_id, inviter_type, invitee_id, invitee_type, invitation_code, created_at)
                VALUES (?, 'eadmin', ?, 'customer', ?, NOW())
            ");
            $stmt->execute([$eadmin_id, $user_id, $invitation_code]);
            
            header('Location: index.php');
            exit();
            
        } catch (PDOException $e) {
            error_log("Bind error: " . $e->getMessage());
            $error = "绑定失败，请重试";
        }
    }
}

// 获取管事列表
$eadminList = [];
try {
    $stmt = $pdo->query("SELECT id, username, real_name FROM users WHERE user_role = 2 AND status = '正常'");
    $eadminList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching eadmins: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>绑定管事</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: url('../assets/1.png') no-repeat center center fixed;
            background-size: cover;
            color: #1d1d1f;
            line-height: 1.5;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: -1;
        }

        .container {
            max-width: 420px;
            margin: 0 auto;
            padding: 20px;
            padding-top: 60px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 36px;
            color: #07C160;
        }

        .header-title {
            font-size: 24px;
            font-weight: 600;
            color: #1d1d1f;
            margin-bottom: 8px;
        }

        .header-subtitle {
            font-size: 14px;
            color: #666;
        }

        .card {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
        }

        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: #1d1d1f;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title i {
            color: #07C160;
        }

        .eadmin-item {
            display: flex;
            align-items: center;
            padding: 14px;
            border: 1px solid #eaeaea;
            border-radius: 12px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #fafafa;
        }

        .eadmin-item:last-child {
            margin-bottom: 0;
        }

        .eadmin-item:hover {
            border-color: #07C160;
            background: #f0fff4;
        }

        .eadmin-item.selected {
            border-color: #07C160;
            background: #f0fff4;
            box-shadow: 0 0 0 2px rgba(7, 193, 96, 0.2);
        }

        .eadmin-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #e8e8e8;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 20px;
            color: #999;
        }

        .eadmin-info {
            flex: 1;
        }

        .eadmin-name {
            font-size: 15px;
            font-weight: 500;
            color: #1d1d1f;
        }

        .eadmin-username {
            font-size: 13px;
            color: #999;
        }

        .eadmin-item.selected .eadmin-avatar {
            background: #07C160;
            color: white;
        }

        .error-message {
            background: #fff5f5;
            border: 1px solid #ffe0e0;
            color: #ff3b30;
            padding: 14px 16px;
            border-radius: 12px;
            font-size: 13px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn {
            width: 100%;
            padding: 16px;
            background: #07C160;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn:hover {
            background: #06a050;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(7, 193, 96, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn.loading {
            pointer-events: none;
        }

        .btn.loading::before {
            content: '';
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .skip-btn {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #999;
            font-size: 14px;
            text-decoration: none;
            padding: 10px;
        }

        .skip-btn:hover {
            color: #666;
        }

        @media (max-width: 480px) {
            .container {
                padding: 16px;
                padding-top: 40px;
            }

            .header-icon {
                width: 64px;
                height: 64px;
                font-size: 28px;
            }

            .header-title {
                font-size: 20px;
            }

            .eadmin-item {
                padding: 12px;
            }

            .eadmin-avatar {
                width: 44px;
                height: 44px;
                font-size: 18px;
            }
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

    <div class="container">
        <div class="header">
            <div class="header-icon">
                <i class="fas fa-user-tie"></i>
            </div>
            <h1 class="header-title">绑定上级管事</h1>
            <p class="header-subtitle">请选择您的上级管事，绑定后才能进入抢单大厅</p>
        </div>

        <?php if (isset($error)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form method="post" action="" id="bindForm">
            <div class="card">
                <div class="card-title">
                    <i class="fas fa-users"></i>
                    选择上级管事
                </div>
                
                <?php if (empty($eadminList)): ?>
                <div style="text-align: center; padding: 20px; color: #999;">
                    <i class="fas fa-info-circle" style="margin-bottom: 8px;"></i>
                    <p>暂无可用管事，请联系客服</p>
                </div>
                <?php else: ?>
                <div id="eadminList">
                    <?php foreach ($eadminList as $eadmin): ?>
                    <div class="eadmin-item" data-id="<?php echo $eadmin['id']; ?>">
                        <div class="eadmin-avatar">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="eadmin-info">
                            <div class="eadmin-name"><?php echo htmlspecialchars($eadmin['real_name'] ?: $eadmin['username']); ?></div>
                            <div class="eadmin-username"><?php echo htmlspecialchars($eadmin['username']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <input type="hidden" name="eadmin_id" id="eadminId" value="">

            <button type="submit" class="btn" id="bindBtn" disabled>
                <i class="fas fa-check"></i>
                确认绑定
            </button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const eadminItems = document.querySelectorAll('.eadmin-item');
            const eadminIdInput = document.getElementById('eadminId');
            const bindBtn = document.getElementById('bindBtn');
            const bindForm = document.getElementById('bindForm');

            eadminItems.forEach(item => {
                item.addEventListener('click', function() {
                    eadminItems.forEach(i => i.classList.remove('selected'));
                    this.classList.add('selected');
                    eadminIdInput.value = this.dataset.id;
                    bindBtn.disabled = false;
                });
            });

            bindForm.addEventListener('submit', function() {
                if (!eadminIdInput.value) {
                    alert('请选择上级管事');
                    return false;
                }
                bindBtn.classList.add('loading');
                bindBtn.innerHTML = '';
                bindBtn.disabled = true;
                return true;
            });
        });
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
