<?php
$siteTitle = '常用说明';
require 'header.php';

if (empty($_SESSION['partner_id'])) {
    header('Location: partner_login.php');
    exit;
}

try {
    $stmt = $pdo->query("SELECT * FROM common_notes WHERE is_active = 1 ORDER BY sort_order ASC, id DESC");
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $notes = [];
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
        .card{background:rgba(255,255,255,0.8);border-radius:16px;padding:20px;margin-bottom:16px;box-shadow:0 4px 15px rgba(0,0,0,.08)}
        .card-header{display:flex;align-items:center;gap:12px;margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #f0f0f0}
        .card-icon{width:44px;height:44px;border-radius:12px;background:#fff7e6;display:flex;align-items:center;justify-content:center;color:#f5b400;font-size:20px;flex-shrink:0}
        .card-title{font-size:16px;font-weight:700;color:#333}
        .card-content{font-size:14px;color:#666;line-height:1.8}
        .card-content p{margin-bottom:12px}
        .card-content p:last-child{margin-bottom:0}
        .empty-state{text-align:center;padding:60px 20px;background:rgba(255,255,255,0.8);border-radius:16px;border:1px solid #e0e0e0}
        .empty-icon{font-size:48px;color:#ccc;margin-bottom:16px}
        .empty-message{font-size:16px;font-weight:600;color:#666}
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
            <div class="header-title">常用说明</div>
            <a href="index.php" class="header-btn">
                <i class="fas fa-home"></i>
            </a>
        </div>
    </header>

    <main class="app-container">
        <h1 class="page-title"><i class="fas fa-sticky-note"></i> 常用说明</h1>

        <?php if (empty($notes)): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-file-alt"></i></div>
            <div class="empty-message">暂无说明文档</div>
        </div>
        <?php else: ?>
        <?php foreach ($notes as $index => $note): ?>
        <div class="card" style="opacity:0;transform:translateY(10px);transition:opacity 0.3s ease,transform 0.3s ease;transition-delay:<?php echo $index * 0.05; ?>s">
            <div class="card-header">
                <div class="card-icon">
                    <i class="fas fa-<?php echo !empty($note['icon']) ? htmlspecialchars($note['icon']) : 'file-alt'; ?>"></i>
                </div>
                <div class="card-title"><?php echo htmlspecialchars($note['title'] ?? '说明文档'); ?></div>
            </div>
            <div class="card-content">
                <?php echo nl2br(htmlspecialchars($note['content'] ?? '')); ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <footer>
        <p>到底啦！</p>
    </footer>

    <?php require 'footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.card');
        cards.forEach((card, index) => {
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100 + index * 50);
        });
    });
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>