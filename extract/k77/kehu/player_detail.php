<?php
// 安全启动Session
session_start();

// 初始化必要的默认变量
$player = null;
$has_db_connection = false;
$debug_output = "";

// 获取玩家ID
$player_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 尝试加载配置文件
try {
    require '../config.php';
} catch (Exception $e) {
    error_log('Failed to load config: ' . $e->getMessage());
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'xiaoyudianjing');
}

// 登录状态检查函数
function is_logged_in() {
    return (!empty($_SESSION['user_id']) && !empty($_SESSION['auth_token']));
}

$debug_output = "<script>console.log('Session debug: user_id=".(isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set')." auth_token=".(isset($_SESSION['auth_token']) ? 'set' : 'not set')."');</script>";

// 尝试数据库连接
$db = null;
try {
    if (!in_array('mysql', PDO::getAvailableDrivers())) {
        error_log('MySQL PDO driver not available');
        $has_db_connection = false;
    } else {
        $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", 
                     DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $has_db_connection = true;
    }
    
    if ($has_db_connection && $player_id > 0) {
        try {
            // 获取玩家详情
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$player_id]);
            $player = $stmt->fetch();
            
            if ($player) {
                // 确保有name字段
                if (!isset($player['name']) && isset($player['username'])) {
                    $player['name'] = $player['username'];
                } elseif (!isset($player['name']) && isset($player['user_name'])) {
                    $player['name'] = $player['user_name'];
                }
                
                // 处理头像
                if (!empty($player['avatar'])) {
                    $avatar_path = '../assets/images/userds/' . $player['avatar'];
                    if (file_exists($avatar_path)) {
                        $player['avatar_url'] = htmlspecialchars($avatar_path);
                    } else {
                        $player['avatar_url'] = '';
                    }
                } else {
                    $gender = isset($player['gender']) ? $player['gender'] : '';
                    $gender_folder = ($gender === 'female') ? 'women' : 'men';
                    $random_num = rand(1, 99);
                    $player['avatar_url'] = 'https://randomuser.me/api/portraits/' . $gender_folder . '/' . $random_num . '.jpg';
                }
                
                // 处理标签
                if (isset($player['tags']) && !empty($player['tags'])) {
                    $player['tags_array'] = is_array($player['tags']) ? $player['tags'] : explode(',', $player['tags']);
                } else {
                    $player['tags_array'] = [];
                }
                
                // 可接单状态
                $player['is_available'] = (isset($player['available_for_orders']) && $player['available_for_orders'] == 1);
                
                // 处理语音介绍
                if (!empty($player['voice_url'])) {
                    $voice_path = '../assets/voices/' . $player['voice_url'];
                    if (file_exists($voice_path)) {
                        $player['has_voice'] = true;
                        $player['voice_url'] = htmlspecialchars($voice_path);
                    } else {
                        $player['has_voice'] = false;
                    }
                } else {
                    $player['has_voice'] = false;
                }
            }
        } catch (Exception $e) {
            error_log('Error loading player: ' . $e->getMessage());
        }
    }
} catch(PDOException $e) {
    error_log("数据库错误: ".$e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="电竞 - 专业游戏陪玩服务平台">
    <meta name="keywords" content="游戏陪玩,电竞,陪玩平台,游戏代练">
    <title><?php echo $player ? htmlspecialchars($player['name']) : '陪玩详情'; ?></title>
    <?php echo $debug_output; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-teal: #3baaa3;
            --primary-teal-dark: #2a8a84;
            --gold: #c9a45c;
            --gold-dark: #a8843d;
            --text-primary: #333333;
            --text-secondary: #666666;
            --text-tertiary: #999999;
        }

        body {
            font-family: 'Noto Sans SC', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: url('../assets/1.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: var(--text-primary);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            padding-bottom: 100px;
            position: relative;
        }

        /* 顶部导航 */
        .page-header {
            padding: 16px 20px;
            background: rgba(255,255,255,0.8);
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,.08);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .back-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg,#ffcc00 0%,#ff9900 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #000;
            box-shadow: 0 3px 10px rgba(255,204,0,.4);
            transition: all 0.2s ease;
        }

        .back-btn:hover {
            transform: scale(1.1);
        }

        .page-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        /* 主内容区 */
        .main-content {
            padding: 0 12px;
        }

        /* 个人信息卡片 */
        .profile-card {
            background: rgba(255,255,255,0.8);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,.08);
            text-align: center;
            border: 1px solid #e0e0e0;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 16px;
            background: #f5f5f5;
        }

        .profile-name {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .gender-icon {
            font-size: 18px;
        }

        .gender-icon.female {
            color: #e91e63;
        }

        .gender-icon.male {
            color: #2196f3;
        }

        .profile-status {
            display: inline-block;
            padding: 6px 16px;
            font-size: 13px;
            font-weight: 500;
            border-radius: 20px;
            margin-bottom: 16px;
        }

        .profile-status.available {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .profile-status.unavailable {
            background: #ffebee;
            color: #c62828;
        }

        .profile-tags {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px;
        }

        .tag {
            font-size: 12px;
            padding: 6px 14px;
            font-weight: 400;
            color: var(--text-secondary);
            background: #f5f5f5;
            border-radius: 16px;
        }

        /* 信息区块 */
        .info-section {
            background: rgba(255,255,255,0.8);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,.08);
            border: 1px solid #e0e0e0;
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 16px;
            padding-left: 4px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f5f5f5;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .info-value {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
        }

        /* 简介区块 */
        .bio-text {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.8;
            padding: 4px;
        }

        /* 底部操作栏 */
        .action-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255,255,255,0.8);
            padding: 12px 20px 24px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
            display: flex;
            gap: 12px;
            border-top: 1px solid rgba(255,255,255,0.3);
        }

        .btn {
            flex: 1;
            padding: 14px 24px;
            border-radius: 25px;
            font-size: 15px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg,#ffcc00 0%,#ff9900 100%);
            color: #000;
            box-shadow: 0 4px 15px rgba(255,204,0,.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255,204,0,.5);
        }

        .btn-secondary {
            background: #f5f5f5;
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: #eeeeee;
        }

        /* 语音介绍区域 */
        .voice-section {
            background: linear-gradient(135deg, #fff5f5 0%, #ffffff 100%);
            border: 1px solid #ffe0e0;
        }

        .voice-player-wrapper {
            background: #ffffff;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .voice-audio-player {
            width: 100%;
            height: 44px;
            border-radius: 8px;
        }

        /* 空状态 */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: rgba(253,248,232,0.8);
            border-radius: 16px;
            margin: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,.12);
            position: relative;
            border: none;
        }

        .empty-icon {
            font-size: 48px;
            margin-bottom: 12px;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,.1));
        }

        .empty-message {
            color: var(--primary-teal-dark);
            font-size: 18px;
            font-weight: 600;
            letter-spacing: 2px;
        }

        /* 响应式 */
        @media (max-width: 380px) {
            .profile-avatar {
                width: 80px;
                height: 80px;
            }
            .profile-name {
                font-size: 18px;
            }
            .btn {
                padding: 12px 20px;
                font-size: 14px;
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

    <!-- 顶部导航 -->
    <div class="page-header">
        <a href="select.php" class="back-btn">
            <i class="fas fa-chevron-left"></i>
        </a>
        <h1 class="page-title">陪玩详情</h1>
    </div>

    <!-- 主内容 -->
    <main class="main-content">
        <?php if ($player): ?>
            <!-- 个人信息卡片 -->
            <div class="profile-card">
                <img src="<?php echo $player['avatar_url']; ?>" alt="<?php echo htmlspecialchars($player['name']); ?>" class="profile-avatar">
                <div class="profile-name">
                    <?php echo htmlspecialchars($player['name']); ?>
                    <?php if ($player['gender'] === 'female'): ?>
                        <i class="fas fa-venus gender-icon female"></i>
                    <?php elseif ($player['gender'] === 'male'): ?>
                        <i class="fas fa-mars gender-icon male"></i>
                    <?php endif; ?>
                </div>
                <div class="profile-status <?php echo $player['is_available'] ? 'available' : 'unavailable'; ?>">
                    <?php echo $player['is_available'] ? '可接单' : '休息中'; ?>
                </div>
                <?php if (!empty($player['tags_array'])): ?>
                    <div class="profile-tags">
                        <?php foreach ($player['tags_array'] as $tag): ?>
                            <span class="tag"><?php echo htmlspecialchars(trim($tag)); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 基本信息 -->
            <div class="info-section">
                <h2 class="section-title">基本信息</h2>
                <div class="info-item">
                    <span class="info-label">ID</span>
                    <span class="info-value">#<?php echo $player['id']; ?></span>
                </div>
                <?php if (!empty($player['wechat'])): ?>
                    <div class="info-item">
                        <span class="info-label">微信号</span>
                        <span class="info-value"><?php echo htmlspecialchars($player['wechat']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($player['qq'])): ?>
                    <div class="info-item">
                        <span class="info-label">QQ</span>
                        <span class="info-value"><?php echo htmlspecialchars($player['qq']); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 语音介绍 -->
            <?php if ($player['has_voice']): ?>
                <div class="info-section voice-section">
                    <h2 class="section-title"><i class="fas fa-microphone-alt" style="color: #ff6b6b; margin-right: 8px;"></i>语音介绍</h2>
                    <div class="voice-player-wrapper">
                        <audio controls class="voice-audio-player">
                            <source src="<?php echo $player['voice_url']; ?>" type="audio/webm">
                            <source src="<?php echo $player['voice_url']; ?>" type="audio/mpeg">
                            您的浏览器不支持音频播放
                        </audio>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 个人简介 -->
            <?php if (!empty($player['bio']) || !empty($player['description'])): ?>
                <div class="info-section">
                    <h2 class="section-title">个人简介</h2>
                    <p class="bio-text">
                        <?php echo nl2br(htmlspecialchars($player['bio'] ?? $player['description'] ?? '暂无简介')); ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- 注意事项 -->
            <div class="info-section" style="background: #fff9e6; border: 1px solid #ffe082;">
                <h2 class="section-title" style="color: #f57c00;">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 6px;"></i>注意事项
                </h2>
                <p class="bio-text" style="color: #e65100; font-weight: 500;">
                    <i class="fas fa-ban" style="margin-right: 6px;"></i>未满18周岁禁止下单
                </p>
                <p class="bio-text" style="color: #666; font-size: 13px; margin-top: 8px;">
                    请遵守平台规则，文明游戏，理性消费。如有任何问题请联系客服。
                </p>
            </div>

            <!-- 底部操作栏 -->
            <div class="action-bar">
                <a href="select.php" class="btn btn-primary" style="flex: 1;">返回列表</a>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">😕</div>
                <div class="empty-message">未找到该陪玩人员</div>
            </div>
        <?php endif; ?>
    </main>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>