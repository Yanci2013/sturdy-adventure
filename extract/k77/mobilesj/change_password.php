<?php
$siteTitle = '修改密码';
require 'header.php';

if (empty($_SESSION['partner_id'])) {
    header('Location: partner_login.php');
    exit;
}

$error_msg = '';
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_msg = "所有字段都必须填写";
    } elseif ($new_password !== $confirm_password) {
        $error_msg = "新密码和确认密码不匹配";
    } elseif (strlen($new_password) < 6) {
        $error_msg = "密码长度至少需要6个字符";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT password FROM partners WHERE id = ?");
            $stmt->execute([$_SESSION['partner_id']]);
            $partner = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$partner) {
                throw new Exception("商家账户不存在");
            }

            if (!password_verify($current_password, $partner['password'])) {
                throw new Exception("当前密码不正确");
            }

            if (password_verify($new_password, $partner['password'])) {
                throw new Exception("新密码不能与旧密码相同");
            }

            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            $update_stmt = $pdo->prepare("UPDATE partners SET password = ?, updated_at = NOW() WHERE id = ?");
            $update_stmt->execute([$hashed_password, $_SESSION['partner_id']]);

            $success_msg = "密码修改成功！";

        } catch (Exception $e) {
            $error_msg = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
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
        .page-title{margin:20px 0 16px;font-size:18px;font-weight:700;color:#333;display:flex;align-items:center;gap:8px}
        .page-title i{color:#ffcc00}
        .alert{padding:14px 16px;border-radius:12px;margin-bottom:16px;font-size:14px;display:flex;align-items:center;gap:8px;background:rgba(255,255,255,0.9);border:1px solid #e0e0e0}
        .alert-success{background:rgba(232,245,233,0.9)!important;border-color:#c8e6c9!important;color:#2e7d32}
        .alert-error{background:rgba(255,235,238,0.9)!important;border-color:#ffcdd2!important;color:#c62828}
        .card{background:rgba(255,255,255,0.8);border-radius:16px;padding:20px;margin-bottom:16px;box-shadow:0 4px 15px rgba(0,0,0,.08)}
        .form-group{margin-bottom:20px}
        .form-label{display:block;font-size:13px;color:#333;margin-bottom:8px;font-weight:600}
        .form-control{width:100%;padding:12px 16px;border:1px solid #e0e0e0;background:rgba(255,255,255,0.9);font-size:14px;color:#333;border-radius:12px;transition:all .2s ease}
        .form-control:focus{outline:none;border-color:#ffcc00;box-shadow:0 0 0 3px rgba(255,204,0,.2)}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:14px 24px;border:none;border-radius:12px;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;transition:all .2s ease;width:100%}
        .btn-primary{background:linear-gradient(135deg,#ffcc00 0%,#ff9900 100%);color:#000;box-shadow:0 4px 12px rgba(255,204,0,.3)}
        .btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(255,204,0,.4)}
        .btn-secondary{background:#f5f5f5;color:#333;margin-top:10px}
        .btn-secondary:hover{background:#e0e0e0}
        .password-hint{font-size:12px;color:#999;margin-top:6px}
        footer p{font-size:22px !important;font-weight:600;background:linear-gradient(135deg,var(--gold) 0%,var(--gold-dark) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;text-align:center;margin:30px 0 20px;letter-spacing:6px}
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
            <div class="header-title">修改密码</div>
            <a href="view-profile.php" class="header-btn">
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>
    </header>

    <main class="app-container">
        <h1 class="page-title"><i class="fas fa-key"></i> 修改密码</h1>

        <?php if ($error_msg): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_msg); ?>
        </div>
        <?php endif; ?>

        <?php if ($success_msg): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success_msg); ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <form method="post" action="">
                <div class="form-group">
                    <label class="form-label">当前密码</label>
                    <input type="password" name="current_password" class="form-control" placeholder="请输入当前密码" required>
                </div>

                <div class="form-group">
                    <label class="form-label">新密码</label>
                    <input type="password" name="new_password" class="form-control" placeholder="请输入新密码" required>
                    <div class="password-hint">密码长度至少6个字符</div>
                </div>

                <div class="form-group">
                    <label class="form-label">确认新密码</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="请再次输入新密码" required>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> 确认修改
                </button>
            </form>
        </div>

        <a href="view-profile.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> 返回个人资料
        </a>
    </main>

    <footer>
        <p>到底啦！</p>
    </footer>

    <?php require 'footer.php'; ?>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>