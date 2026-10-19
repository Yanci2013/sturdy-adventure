<?php
session_start();
include "../includes/common.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$currentVersion = 'v1.0.0.0';
try {
    $stmt = $pdo->query("SELECT system_version FROM settings LIMIT 1");
    if ($stmt && $stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentVersion = $row['system_version'] ?? 'v1.0.0.0';
    }
} catch (PDOException $e) {
    $currentVersion = 'v1.0.0.0';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统更新</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://www.layuicdn.com/layui-v2.6.8/css/layui.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0;
        }
        .update-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .update-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        .update-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }
        .update-icon i {
            font-size: 50px;
            color: white;
        }
        .update-title {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }
        .update-subtitle {
            font-size: 16px;
            color: #666;
            margin-bottom: 30px;
        }
        .version-info {
            background: linear-gradient(135deg, #f8f9ff, #f0f4ff);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            border: 2px solid #e8ebf7;
        }
        .version-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        .version-number {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
        }
        .update-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 25px;
            padding: 15px 40px;
            font-size: 18px;
            font-weight: 600;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            width: 100%;
            max-width: 300px;
        }
        .update-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }
        .update-btn:active {
            transform: translateY(0);
        }
        .update-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        .status-box {
            display: none;
            background: linear-gradient(135deg, #fff5f5, #ffe8e8);
            border: 2px solid #ffd6d6;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .status-box.show {
            display: block;
        }
        .status-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        .status-title {
            font-size: 18px;
            font-weight: 700;
            color: #dc3545;
            margin-bottom: 5px;
        }
        .status-message {
            font-size: 14px;
            color: #666;
        }
        .loading-spinner {
            display: none;
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .loading-spinner.show {
            display: block;
        }
        @media (max-width: 768px) {
            .update-container {
                padding: 30px 20px;
            }
            .update-title {
                font-size: 24px;
            }
            .update-icon {
                width: 80px;
                height: 80px;
            }
            .update-icon i {
                font-size: 40px;
            }
        }
    </style>
</head>
<body>
    <div class="update-container">
        <div class="update-icon">
            <i class="layui-icon layui-icon-refresh"></i>
        </div>
        <h1 class="update-title">系统更新</h1>
        <p class="update-subtitle">保持系统最新，享受更好的体验</p>
        
        <div class="version-info">
            <div class="version-label">当前版本</div>
            <div class="version-number"><?php echo htmlspecialchars($currentVersion); ?></div>
        </div>
        
        <button class="update-btn" id="checkUpdateBtn" onclick="checkUpdate()">
            <i class="layui-icon layui-icon-refresh"></i> 检查更新
        </button>
        
        <div class="loading-spinner" id="loadingSpinner"></div>
        
        <div class="status-box" id="statusBox">
            <div class="status-icon">✓</div>
            <div class="status-title">已是最新版本</div>
            <div class="status-message">当前已是最高版本，无需更新</div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://www.layuicdn.com/layui-v2.6.8/layui.js"></script>
    <script>
        function checkUpdate() {
            var btn = document.getElementById('checkUpdateBtn');
            var spinner = document.getElementById('loadingSpinner');
            var statusBox = document.getElementById('statusBox');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i> 正在检查...';
            spinner.classList.add('show');
            statusBox.classList.remove('show');
            
            setTimeout(function() {
                spinner.classList.remove('show');
                statusBox.classList.add('show');
                btn.innerHTML = '<i class="layui-icon layui-icon-ok"></i> 检查完成';
                btn.disabled = false;
            }, 2000);
        }
    </script>
</body>
</html>
