<?php
require_once '../includes/session_manager.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>隐私政策</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-bg: #f5f5f5;
            --card-bg: rgba(255,255,255,0.85);
            --text-primary: #333333;
            --text-secondary: #666666;
            --text-tertiary: #999999;
            --accent-gold: #d4a84b;
        }

        body {
            font-family: 'Noto Sans SC', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: url('../assets/1.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: var(--text-primary);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            padding-bottom: 80px;
        }

        /* 顶部返回栏 */
        .page-header {
            padding: 16px 20px;
            background: rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .back-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-primary);
            text-decoration: none;
            transition: background 0.2s ease;
        }

        .back-btn:hover {
            background: rgba(0,0,0,0.1);
        }

        .page-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
        }

        /* 主内容区 */
        .main-content {
            padding: 16px 12px;
        }

        /* 内容卡片 */
        .content-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title i {
            color: var(--accent-gold);
            font-size: 14px;
        }

        .section-content {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.8;
            margin-bottom: 16px;
        }

        .section-content p {
            margin-bottom: 10px;
        }

        .section-content ul {
            padding-left: 20px;
            margin-bottom: 10px;
        }

        .section-content li {
            margin-bottom: 6px;
        }

        /* 更新日期 */
        .update-date {
            text-align: center;
            font-size: 12px;
            color: var(--text-tertiary);
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(0,0,0,0.05);
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

    <!-- 顶部返回栏 -->
    <div class="page-header">
        <a href="my.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="page-title">隐私政策</h1>
    </div>

    <!-- 主内容 -->
    <main class="main-content">
        <div class="content-card">
            <div class="section-title">
                <i class="fas fa-info-circle"></i>
                引言
            </div>
            <div class="section-content">
                <p>本隐私政策旨在帮助您了解我们如何收集、使用、存储和保护您的个人信息。在使用我们的服务之前，请您仔细阅读并理解本政策的全部内容。</p>
            </div>
        </div>

        <div class="content-card">
            <div class="section-title">
                <i class="fas fa-database"></i>
                信息收集
            </div>
            <div class="section-content">
                <p>我们可能收集以下类型的信息：</p>
                <ul>
                    <li><strong>个人基本信息</strong>：如昵称、用户ID等</li>
                    <li><strong>交易信息</strong>：如订单记录、交易金额等</li>
                    <li><strong>设备信息</strong>：如设备型号、操作系统版本等</li>
                    <li><strong>日志信息</strong>：如访问时间、页面浏览记录等</li>
                </ul>
            </div>
        </div>

        <div class="content-card">
            <div class="section-title">
                <i class="fas fa-cogs"></i>
                信息使用
            </div>
            <div class="section-content">
                <p>我们使用收集的信息用于以下目的：</p>
                <ul>
                    <li>提供、维护和改进我们的服务</li>
                    <li>处理您的订单和交易</li>
                    <li>向您提供服务通知和更新</li>
                    <li>保障账户安全和防止欺诈行为</li>
                    <li>遵守法律法规要求</li>
                </ul>
            </div>
        </div>

        <div class="content-card">
            <div class="section-title">
                <i class="fas fa-shield-alt"></i>
                信息保护
            </div>
            <div class="section-content">
                <p>我们采取合理的技术和管理措施来保护您的个人信息安全，包括：</p>
                <ul>
                    <li>数据加密传输和存储</li>
                    <li>访问权限控制</li>
                    <li>定期安全审计</li>
                    <li>员工隐私培训</li>
                </ul>
            </div>
        </div>

        <div class="content-card">
            <div class="section-title">
                <i class="fas fa-share-alt"></i>
                信息共享
            </div>
            <div class="section-content">
                <p>除以下情形外，我们不会向第三方共享您的个人信息：</p>
                <ul>
                    <li>获得您的明确同意</li>
                    <li>法律法规要求或政府机关依法要求</li>
                    <li>为完成交易或提供服务所必需的合作伙伴</li>
                </ul>
            </div>
        </div>

        <div class="content-card">
            <div class="section-title">
                <i class="fas fa-user-cog"></i>
                您的权利
            </div>
            <div class="section-content">
                <p>您对个人信息享有以下权利：</p>
                <ul>
                    <li>访问和更新您的个人信息</li>
                    <li>删除您的账户和相关信息</li>
                    <li>撤回同意</li>
                    <li>投诉和申诉</li>
                </ul>
            </div>
        </div>

        <div class="content-card">
            <div class="section-title">
                <i class="fas fa-envelope"></i>
                联系我们
            </div>
            <div class="section-content">
                <p>如果您对本隐私政策有任何疑问或建议，请通过客服渠道与我们联系。</p>
            </div>
            <div class="update-date">
            </div>
        </div>
    </main>

    <!-- 底部导航 -->
    <?php include 'footer.php'; ?>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
