<?php
$siteTitle = '商家钱包';
require 'header.php';

$partner_id = $_SESSION['partner_id'];

try {
    $balanceStmt = $pdo->prepare("SELECT id, balance, deposit FROM partners WHERE id = ?");
    $balanceStmt->execute([$partner_id]);
    $balanceData = $balanceStmt->fetch(PDO::FETCH_ASSOC);

    if (!$balanceData) {
        throw new Exception("未找到商家账户信息");
    }
} catch (Exception $e) {
    die("错误: " . $e->getMessage());
}

try {
    $pendingStmt = $pdo->prepare("SELECT SUM(amount) as pending_amount FROM orders WHERE issuer_id = ? AND status = 'processing'");
    $pendingStmt->execute([$partner_id]);
    $pendingData = $pendingStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pendingData = ['pending_amount' => 0];
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
        .card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
        .card-title{font-size:16px;font-weight:700;color:#333}
        .card-icon{width:48px;height:48px;border-radius:12px;background:#fff7e6;display:flex;align-items:center;justify-content:center;color:#f5b400;font-size:20px}
        .balance-info{margin-bottom:16px}
        .balance-label{font-size:13px;color:#666;margin-bottom:4px}
        .balance-amount{font-size:28px;font-weight:700;color:#333;margin-bottom:4px}
        .balance-note{font-size:12px;color:#999;display:flex;align-items:center;gap:4px}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 20px;border:none;border-radius:12px;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;transition:all .2s ease}
        .btn-primary{background:linear-gradient(135deg,#ffcc00 0%,#ff9900 100%);color:#000;box-shadow:0 4px 12px rgba(255,204,0,.3)}
        .btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(255,204,0,.4)}
        .btn-secondary{background:#f5f5f5;color:#333}
        .btn-secondary:hover{background:#e0e0e0}
        .btn-group{display:flex;gap:10px;margin-top:16px}
        .w-full{width:100%}
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:999;overflow-y:auto}
        .modal.show{display:block}
        .modal-dialog{position:relative;width:90%;max-width:500px;margin:50px auto}
        .modal-content{background:#fff;border-radius:16px;padding:20px;box-shadow:0 10px 40px rgba(0,0,0,.2)}
        .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #f0f0f0}
        .modal-header h5{font-size:18px;font-weight:600;color:#333;margin:0}
        .modal-close{background:none;border:none;font-size:24px;cursor:pointer;color:#999;width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:50%;transition:all .2s ease}
        .modal-close:hover{color:#000;background:#f5f5f5}
        .modal-body{padding:20px;max-height:70vh;overflow-y:auto}
        .modal-footer{padding:16px 20px;border-top:1px solid #f0f0f0;display:flex;justify-content:flex-end;gap:10px}
        .form-group{margin-bottom:20px}
        .form-label{display:block;font-size:14px;color:#333;margin-bottom:8px;font-weight:600}
        .form-control{width:100%;padding:12px 16px;border:2px solid #e0e0e0;background:#fafafa;font-size:14px;color:#333;border-radius:12px;transition:all .2s ease}
        .form-control:focus{outline:none;border-color:#ffcc00;box-shadow:0 0 0 3px rgba(255,204,0,.2)}
        textarea.form-control{min-height:80px;resize:vertical}
        .input-group{position:relative;width:100%}
        .input-group .prefix{position:absolute;left:16px;top:50%;transform:translateY(-50%);color:#666;font-weight:600;z-index:1;pointer-events:none}
        .input-group input{padding-left:32px}
        .form-text{font-size:12px;color:#999;margin-top:6px}
        .payment-methods{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px}
        .payment-method{border:2px solid #e0e0e0;padding:16px 12px;text-align:center;cursor:pointer;transition:all .2s ease;border-radius:12px;background:#fff}
        .payment-method:hover{border-color:#ffcc00;transform:translateY(-2px)}
        .payment-method.selected{border-color:#ffcc00;background:#fff7e6}
        .payment-method-icon{width:48px;height:48px;background:#f5f5f5;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;font-size:24px;color:#666}
        .payment-method.selected .payment-method-icon{background:#ffcc00;color:#fff}
        .payment-method p{font-size:13px;color:#666;font-weight:500}
        .payment-method.selected p{color:#000}
        footer p{font-size:22px !important;font-weight:600;background:linear-gradient(135deg,var(--gold) 0%,var(--gold-dark) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;text-align:center;margin:30px 0 20px;letter-spacing:6px}
        @media (max-width:768px){.btn-group{flex-direction:column}.modal-dialog{width:95%;margin:30px auto}.payment-methods{grid-template-columns:repeat(3,1fr)}}
        @media (max-width:480px){.balance-amount{font-size:24px}.payment-methods{grid-template-columns:1fr}.modal-footer{flex-direction:column}.modal-footer .btn{width:100%}}
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
            <div class="header-title">商家钱包</div>
            <a href="index.php" class="header-btn">
                <i class="fas fa-home"></i>
            </a>
        </div>
    </header>

    <main class="app-container">
        <h1 class="page-title"><i class="fas fa-wallet"></i> 我的钱包</h1>

        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">账户余额</div>
                </div>
                <div class="card-icon">
                    <i class="fas fa-wallet"></i>
                </div>
            </div>
            <div class="balance-info">
                <div class="balance-label">可用余额</div>
                <div class="balance-amount">¥<?php echo number_format($balanceData['balance'], 2); ?></div>
            </div>
            <div class="btn-group">
                <a href="./cz" class="btn btn-primary w-full">
                    <i class="fas fa-arrow-up"></i>
                    充值
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">保证金</div>
                </div>
                <div class="card-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
            </div>
            <div class="balance-info">
                <div class="balance-label">当前保证金</div>
                <div class="balance-amount">¥<?php echo number_format($balanceData['deposit'], 2); ?></div>
            </div>
            <div class="balance-note">
                <i class="fas fa-info-circle"></i>
                保证金可在商家中心申请退还
            </div>
        </div>
    </main>

    <footer>
        <p>到底啦！</p>
    </footer>

    <?php require 'footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(10px)';
            setTimeout(() => {
                card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 50 + index * 30);
        });
    });
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>