<?php
$siteTitle = '个人中心 - 商家端';
include 'header.php';

$error = '';
$success = '';
$stats = [
    'balance' => '0.00',
    'deposit' => '0.00',
    'order_count' => '0',
    'order_status_1' => '0',
    'order_status_2' => '0',
    'order_status_3' => '0',
    'order_status_4' => '0',
    'order_status_5' => '0',
    'order_total' => '0'
];

$partner_wechat_openid = '';
$partner_wechat_nickname = '';

$settings = getSystemSettings($pdo);

$suyanw_appid = '3527';
$suyanw_appkey = '3af0521a7f9afbcb6c637c7533530bbe';

try {
    $partner_id = $_SESSION['partner_id'];

    $stmt = $pdo->prepare("SELECT id, name, contact_person, contact_phone, email, balance, deposit, avatar, is_verified, wechat_openid, wechat_nickname, wechat_avatar FROM partners WHERE id = ?");
    $stmt->execute([$partner_id]);
    $partnerData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$partnerData) {
        throw new Exception("商家信息查询失败");
    }

    $partner_wechat_openid = $partnerData['wechat_openid'] ?? '';
    $partner_wechat_nickname = $partnerData['wechat_nickname'] ?? '';

    $stmt = $pdo->prepare("SELECT COUNT(id) AS count FROM orders WHERE fadan_id = ?");
    $stmt->execute([$partner_id]);
    $orderCount = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['order_count'] = $orderCount['count'] ?? '0';

    $orderStatuses = [1, 2, 3, 4, 5];
    foreach ($orderStatuses as $status) {
        $stmt = $pdo->prepare("SELECT COUNT(id) AS count FROM orders WHERE fadan_id = ? AND order_status = ?");
        $stmt->execute([$partner_id, $status]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['order_status_' . $status] = $count['count'] ?? '0';
    }

    $stats['order_total'] = $stats['order_count'];
    $stats['balance'] = number_format((float)$partnerData['balance'], 2, '.', '');
    $stats['deposit'] = number_format((float)$partnerData['deposit'], 2, '.', '');

} catch (Exception $e) {
    $error = "数据加载失败: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['type']) && isset($_GET['code'])) {
    $bind_type = $_POST['bind_type'] ?? ($_GET['type'] ?? '');
    $code = $_POST['code'] ?? ($_GET['code'] ?? '');
    $state = $_POST['state'] ?? ($_SESSION['suyanw_state'] ?? '');

    if ($bind_type === 'wx' && !empty($code)) {
        if (empty($suyanw_appid) || empty($suyanw_appkey)) {
            $_SESSION['error_msg'] = "聚合登录配置未完成，请联系管理员";
            header("Location: profile.php");
            exit();
        }

        try {
            $callback_url = "https://u.suyanw.cn/connect.php?act=callback&appid={$suyanw_appid}&appkey={$suyanw_appkey}&type=wx&code={$code}";
            $response = file_get_contents($callback_url);
            $result = json_decode($response, true);

            if ($result['code'] !== 0) {
                $_SESSION['error_msg'] = "获取用户信息失败: " . ($result['msg'] ?? '未知错误');
                header("Location: profile.php");
                exit();
            }

            $social_uid = $result['social_uid'];
            $nickname = $result['nickname'] ?? '微信用户';
            $faceimg = $result['faceimg'] ?? '';

            $stmt = $pdo->prepare("SELECT id FROM partners WHERE wechat_openid = ? AND id != ?");
            $stmt->execute([$social_uid, $partner_id]);
            if ($stmt->fetch()) {
                $_SESSION['error_msg'] = "该微信已被其他商家绑定";
                header("Location: profile.php");
                exit();
            }

            $stmt = $pdo->prepare("UPDATE partners SET wechat_openid = ?, wechat_nickname = ?, wechat_avatar = ? WHERE id = ?");
            $stmt->execute([$social_uid, $nickname, $faceimg, $partner_id]);

            $_SESSION['success_msg'] = "微信绑定成功";
            header("Location: profile.php");
            exit();

        } catch (Exception $e) {
            $_SESSION['error_msg'] = "绑定失败: " . $e->getMessage();
            header("Location: profile.php");
            exit();
        }
    }

    if (isset($_POST['unbind_wechat'])) {
        try {
            $stmt = $pdo->prepare("UPDATE partners SET wechat_openid = NULL, wechat_nickname = NULL, wechat_avatar = NULL WHERE id = ?");
            $stmt->execute([$partner_id]);

            $_SESSION['success_msg'] = "微信解绑成功";
            header("Location: profile.php");
            exit();

        } catch (Exception $e) {
            $_SESSION['error_msg'] = "解绑失败: " . $e->getMessage();
            header("Location: profile.php");
            exit();
        }
    }
}

if (empty($suyanw_appid) || empty($suyanw_appkey)) {
    $wechat_auth_url = '#';
    $auth_enabled = false;
} else {
    $_SESSION['suyanw_state'] = bin2hex(random_bytes(16));
    $wechat_auth_url = "wechat_bind.php";
    $auth_enabled = true;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="个人中心">
    <title><?php echo $siteTitle; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--primary-teal:#3baaa3;--primary-teal-dark:#2a8a84;--gold:#c9a45c;--gold-dark:#a8843d}
        body{font-family:'Noto Sans SC',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:url('../assets/1.png');background-size:cover;background-position:center;background-repeat:no-repeat;background-attachment:fixed;color:#333;line-height:1.5;-webkit-font-smoothing:antialiased;min-height:100vh;position:relative}
        .app-container{max-width:100%;margin:0 auto;padding:0 12px 80px;position:relative;z-index:1}
        .header{padding:12px;background:rgba(255,255,255,0.8);position:sticky;top:0;z-index:100;box-shadow:0 2px 10px rgba(0,0,0,.08)}
        .header-content{display:flex;align-items:center;justify-content:space-between;gap:12px}
        .header-title{font-size:18px;font-weight:700;color:#333;flex-shrink:0}
        .header-btn{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#ffcc00 0%,#ff9900 100%);border:none;color:#000;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px;text-decoration:none;transition:all .2s ease;box-shadow:0 3px 10px rgba(255,204,0,.4)}
        .header-btn:hover{transform:scale(1.1)}
        .alert{padding:14px 16px;border-radius:12px;margin:16px 0;font-size:14px;display:flex;align-items:center;gap:8px;background:rgba(255,255,255,0.9);border:1px solid #e0e0e0}
        .alert-success{background:rgba(232,245,233,0.9)!important;border-color:#c8e6c9!important;color:#2e7d32}
        .alert-error{background:rgba(255,235,238,0.9)!important;border-color:#ffcdd2!important;color:#c62828}
        .profile-card{background:rgba(255,255,255,0.8);border-radius:16px;padding:24px;margin:16px 0;box-shadow:0 4px 15px rgba(0,0,0,.08)}
        .profile-header{display:flex;align-items:center;gap:16px;margin-bottom:20px}
        .user-avatar{width:64px;height:64px;border-radius:50%;background:rgba(255,255,255,0.9);display:flex;align-items:center;justify-content:center;overflow:hidden;border:3px solid #ffcc00;box-shadow:0 4px 12px rgba(255,204,0,.3)}
        .user-avatar img{width:100%;height:100%;object-fit:cover}
        .user-avatar i{font-size:32px;color:#ccc}
        .user-details{flex:1}
        .user-name{font-size:18px;font-weight:700;color:#333;margin-bottom:8px;display:flex;align-items:center;gap:10px}
        .user-role{display:inline-block;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600;background:linear-gradient(135deg,#ffcc00 0%,#ff9900 100%);color:#000}
        .user-id{font-size:13px;color:#999;margin-bottom:8px}
        .user-balance{display:flex;flex-wrap:wrap;gap:16px}
        .balance-item{font-size:13px;color:#666}
        .balance-item strong{color:#333;font-weight:700}
        .wechat-bind-card{background:rgba(255,255,255,0.95);border:1px solid #e0e0e0;border-radius:16px;padding:20px;margin:16px 0}
        .wechat-bind-header{display:flex;align-items:center;gap:12px;margin-bottom:16px}
        .wechat-icon{width:48px;height:48px;border-radius:12px;background:#07c160;display:flex;align-items:center;justify-content:center;color:#fff;font-size:24px}
        .wechat-info{flex:1}
        .wechat-title{font-size:16px;font-weight:700;color:#333;margin-bottom:4px}
        .wechat-desc{font-size:13px;color:#666}
        .wechat-status{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:20px;font-size:12px;font-weight:600}
        .wechat-status.binded{background:#e8f5e9;color:#388e3c}
        .wechat-status.unbinded{background:#fff3e0;color:#f57c00}
        .wechat-user{display:flex;align-items:center;gap:12px;padding:12px;background:#f5f5f5;border-radius:12px;margin-bottom:16px}
        .wechat-avatar{width:48px;height:48px;border-radius:50%;background:#07c160;display:flex;align-items:center;justify-content:center;color:#fff;font-size:24px;overflow:hidden}
        .wechat-avatar img{width:100%;height:100%;object-fit:cover}
        .wechat-nickname{font-size:14px;font-weight:600;color:#333}
        .wechat-actions{display:flex;gap:12px}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 20px;border:none;border-radius:25px;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;transition:all .2s ease}
        .btn-wechat{background:#07c160;color:#fff;box-shadow:0 4px 12px rgba(7,193,96,.3)}
        .btn-wechat:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(7,193,96,.4)}
        .btn-unbind{background:#ffebee;color:#d32f2f}
        .btn-unbind:hover{background:#ffcdd2}
        .stats-card{background:rgba(255,255,255,0.8);border-radius:16px;padding:20px;margin:16px 0;box-shadow:0 4px 15px rgba(0,0,0,.08)}
        .stats-title{font-size:16px;font-weight:700;color:#333;margin-bottom:16px;display:flex;align-items:center;gap:8px}
        .stats-title i{color:#ffcc00}
        .stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
        .stat-item{background:rgba(255,255,255,0.9);padding:16px;border-radius:12px;text-align:center;border:1px solid #e0e0e0;transition:all .3s ease;text-decoration:none;display:block;color:inherit}
        .stat-item:hover{border-color:#ffcc00;transform:translateY(-2px);box-shadow:0 4px 15px rgba(255,204,0,.2)}
        .stat-value{font-size:22px;font-weight:700;color:#333;margin-bottom:4px}
        .stat-label{font-size:12px;color:#666}
        .console-card{background:rgba(255,255,255,0.8);border-radius:16px;padding:20px;margin:16px 0;box-shadow:0 4px 15px rgba(0,0,0,.08)}
        .console-title{font-size:16px;font-weight:700;color:#333;margin-bottom:16px;display:flex;align-items:center;gap:8px}
        .console-title i{color:#ffcc00}
        .console-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
        .console-item{display:flex;flex-direction:column;align-items:center;gap:10px;padding:16px 8px;background:rgba(255,255,255,0.9);border:1px solid #e0e0e0;border-radius:12px;text-decoration:none;color:inherit;transition:all .3s ease}
        .console-item:hover{border-color:#ffcc00;transform:translateY(-2px);box-shadow:0 4px 15px rgba(255,204,0,.2)}
        .console-icon{width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#ffcc00 0%,#ff9900 100%);display:flex;align-items:center;justify-content:center;color:#000;font-size:18px}
        .console-text{font-size:12px;font-weight:600;color:#666;text-align:center}
        footer p{font-size:22px !important;font-weight:600;background:linear-gradient(135deg,var(--gold) 0%,var(--gold-dark) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;text-align:center;margin:30px 0 20px;letter-spacing:6px}
        @media (max-width:480px){.console-grid{grid-template-columns:repeat(3,1fr)}.console-item{padding:12px 4px}.console-icon{width:36px;height:36px;font-size:16px}.console-text{font-size:10px}}
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

    <header class="header">
        <div class="header-content">
            <div class="header-title">个人中心</div>
            <a href="index.php" class="header-btn">
                <i class="fas fa-home"></i>
            </a>
        </div>
    </header>

    <main class="app-container">
        <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
        </div>
        <?php endif; ?>

        <div class="profile-card">
            <div class="profile-header">
                <div class="user-avatar">
                    <?php
                    $avatarPath = !empty($partnerData['avatar']) ? 'uploads/avatars/' . $partnerData['avatar'] : '../assets/images/user.png';
                    ?>
                    <img src="<?php echo htmlspecialchars($avatarPath); ?>" onerror="this.src='../assets/images/user.png'" alt="商家头像">
                </div>
                <div class="user-details">
                    <div class="user-name">
                        <span><?php echo htmlspecialchars($partnerData['name'] ?? '未命名商家'); ?></span>
                        <span class="user-role">商家</span>
                    </div>
                    <div class="user-id">ID: <?php echo $partner_id; ?></div>
                    <div class="user-balance">
                        <span class="balance-item">余额: <strong>¥<?php echo $stats['balance']; ?></strong></span>
                        <span class="balance-item">保证金: <strong>¥<?php echo $stats['deposit']; ?></strong></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="wechat-bind-card">
            <div class="wechat-bind-header">
                <div class="wechat-icon">
                    <i class="fab fa-weixin"></i>
                </div>
                <div class="wechat-info">
                    <div class="wechat-title">微信绑定</div>
                    <div class="wechat-desc">绑定微信后可直接扫码登录</div>
                </div>
                <div class="wechat-status <?php echo !empty($partner_wechat_openid) ? 'binded' : 'unbinded'; ?>">
                    <i class="fas fa-<?php echo !empty($partner_wechat_openid) ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo !empty($partner_wechat_openid) ? '已绑定' : '未绑定'; ?>
                </div>
            </div>

            <?php if (!empty($partner_wechat_openid)): ?>
            <div class="wechat-user">
                <div class="wechat-avatar">
                    <?php if (!empty($partnerData['wechat_avatar'])): ?>
                    <img src="<?php echo htmlspecialchars($partnerData['wechat_avatar']); ?>" alt="微信头像">
                    <?php else: ?>
                    <i class="fab fa-weixin"></i>
                    <?php endif; ?>
                </div>
                <div class="wechat-nickname"><?php echo htmlspecialchars($partner_wechat_nickname ?: '微信用户'); ?></div>
            </div>
            <form method="post" action="" style="display:inline;">
                <input type="hidden" name="unbind_wechat" value="1">
                <button type="submit" class="btn btn-unbind" onclick="return confirm('确定要解除微信绑定吗？')">
                    <i class="fas fa-unlink"></i> 解除绑定
                </button>
            </form>
            <?php else: ?>
            <div class="wechat-actions">
                <?php if ($auth_enabled): ?>
                <a href="<?php echo htmlspecialchars($wechat_auth_url); ?>" class="btn btn-wechat">
                    <i class="fab fa-weixin"></i> 立即绑定微信
                </a>
                <?php else: ?>
                <button class="btn btn-wechat" disabled>
                    <i class="fas fa-exclamation-triangle"></i> 暂未配置登录功能
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="stats-card">
            <h3 class="stats-title"><i class="fas fa-chart-bar"></i> 订单统计</h3>
            <div class="stats-grid">
                <a href="orders.php?status=1" class="stat-item">
                    <div class="stat-value"><?php echo $stats['order_status_1']; ?></div>
                    <div class="stat-label">待接单</div>
                </a>
                <a href="orders.php?status=2" class="stat-item">
                    <div class="stat-value"><?php echo $stats['order_status_2']; ?></div>
                    <div class="stat-label">进行中</div>
                </a>
                <a href="orders.php?status=3" class="stat-item">
                    <div class="stat-value"><?php echo $stats['order_status_3']; ?></div>
                    <div class="stat-label">待结算</div>
                </a>
                <a href="orders.php?status=4" class="stat-item">
                    <div class="stat-value"><?php echo $stats['order_status_4']; ?></div>
                    <div class="stat-label">已完成</div>
                </a>
                <a href="orders.php?status=5" class="stat-item">
                    <div class="stat-value"><?php echo $stats['order_status_5']; ?></div>
                    <div class="stat-label">已取消</div>
                </a>
                <a href="orders.php" class="stat-item">
                    <div class="stat-value"><?php echo $stats['order_total']; ?></div>
                    <div class="stat-label">全部订单</div>
                </a>
            </div>
        </div>

        <div class="console-card">
            <h3 class="console-title"><i class="fas fa-th-large"></i> 服务功能</h3>
            <div class="console-grid">
                <a href="wallet.php" class="console-item">
                    <div class="console-icon"><i class="fas fa-wallet"></i></div>
                    <div class="console-text">我的钱包</div>
                </a>
                <a href="view-profile.php" class="console-item">
                    <div class="console-icon"><i class="fas fa-user-cog"></i></div>
                    <div class="console-text">个人资料</div>
                </a>
                <a href="partner_penalty_list.php" class="console-item">
                    <div class="console-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="console-text">罚单记录</div>
                </a>
                <a href="order_create.php" class="console-item">
                    <div class="console-icon"><i class="fas fa-plus-circle"></i></div>
                    <div class="console-text">发布订单</div>
                </a>
            </div>
        </div>

        <div class="console-card">
            <h3 class="console-title"><i class="fas fa-cog"></i> 系统</h3>
            <div class="console-grid">
                <a href="orders.php" class="console-item">
                    <div class="console-icon"><i class="fas fa-list"></i></div>
                    <div class="console-text">订单管理</div>
                </a>
                <a href="common_notes.php" class="console-item">
                    <div class="console-icon"><i class="fas fa-sticky-note"></i></div>
                    <div class="console-text">常用说明</div>
                </a>
                <a href="logout.php" class="console-item">
                    <div class="console-icon" style="background:#ffebee;color:#d32f2f;"><i class="fas fa-sign-out-alt"></i></div>
                    <div class="console-text">退出登录</div>
                </a>
            </div>
        </div>
    </main>

    <footer>
        <p>到底啦！</p>
    </footer>

    <?php require __DIR__ . '/footer.php'; ?>