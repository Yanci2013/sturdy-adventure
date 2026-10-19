<?php
// 安全启动Session
session_start();
// 初始化必要的默认变量，即使config.php加载失败也能继续运行
$players = [];
$games = [];
$has_db_connection = false;
$debug_output = "";
$site_logo = "assets/images/logo.png";
// 尝试加载配置文件，但不中断执行
try {
    require '../config.php';
} catch (Exception $e) {
    error_log('Failed to load config: ' . $e->getMessage());
    // 使用默认数据库配置作为后备
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'xiaoyudianjing');
}
// 登录状态检查函数
function is_logged_in() {
    return (!empty($_SESSION['user_id']) && !empty($_SESSION['auth_token']));
}
// 直接调试输出（生产环境应移除）
$debug_output = "<script>console.log('Session debug: user_id=".(isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set')." auth_token=".(isset($_SESSION['auth_token']) ? 'set' : 'not set')."');</script>";
// 尝试数据库连接，但即使失败也允许页面继续加载
$db = null;
try {
    // 检查PDO MySQL驱动是否可用
    if (!in_array('mysql', PDO::getAvailableDrivers())) {
        error_log('MySQL PDO driver not available');
        $has_db_connection = false;
    } else {
        // 数据库连接
        $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", 
                     DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $has_db_connection = true;
        error_log('Database connected successfully');
    }
    
    if ($has_db_connection) {
        try {
            $stmt = $db->query("SELECT site_logo FROM settings WHERE id = 1");
            $logo_result = $stmt->fetch();
            if($logo_result && !empty($logo_result['site_logo'])) {
                $site_logo = $logo_result['site_logo'];
            }
        } catch (Exception $e) {
            error_log('Error loading site logo: ' . $e->getMessage());
        }
        
        try {
            // 获取游戏列表
            $games = $db->query("SELECT * FROM games WHERE status = 1 ORDER BY sort_order DESC, id DESC")->fetchAll();
        } catch (Exception $e) {
            error_log('Error loading games: ' . $e->getMessage());
            $games = [];
        }
        
        try {
            // 获取搜索参数
            $search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
            
            // 构建SQL查询 - 模糊搜索（支持部分匹配）
            $sql = "SELECT DISTINCT u.*, COALESCE(u.available_for_orders, 1) as available_for_orders FROM users u";
            
            if (!empty($search_query)) {
                // 使用多个占位符，因为PDO不允许重复使用相同的命名占位符
                $sql .= " WHERE u.name LIKE :search1 
                            OR u.username LIKE :search2 
                            OR u.user_name LIKE :search3 
                            OR u.nickname LIKE :search4 
                            OR u.real_name LIKE :search5";
            }
            
            $sql .= " ORDER BY u.id DESC";
            error_log('Executing user query: ' . $sql);
            
            $stmt = $db->prepare($sql);
            
            if (!empty($search_query)) {
                $search_param = "%$search_query%";
                $stmt->bindValue(':search1', $search_param);
                $stmt->bindValue(':search2', $search_param);
                $stmt->bindValue(':search3', $search_param);
                $stmt->bindValue(':search4', $search_param);
                $stmt->bindValue(':search5', $search_param);
                error_log('Search parameter: ' . $search_param);
            }
            
            $stmt->execute();
            $players = $stmt->fetchAll();
            error_log('Found ' . count($players) . ' users (search: ' . $search_query . ')');
            
            // 为每个用户获取标签
            if (!empty($players)) {
                foreach ($players as &$player) {
                    $user_id = $player['id'];
                    try {
                        $tag_sql = "SELECT ut.* FROM user_tags ut 
                                   JOIN user_tag_relations utr ON ut.id = utr.tag_id 
                                   WHERE utr.user_id = ? AND ut.status = 1 
                                   ORDER BY ut.sort_order ASC LIMIT 3";
                        $tag_stmt = $db->prepare($tag_sql);
                        $tag_stmt->execute([$user_id]);
                        $player['tags'] = $tag_stmt->fetchAll();
                    } catch (Exception $e) {
                        $player['tags'] = [];
                    }
                }
                unset($player);
            }
            
            // 为每个用户添加必要的字段
            if (!empty($players)) {
                // 使用一个数组来跟踪已经处理过的用户ID，确保不会有重复
                $processed_ids = [];
                $unique_players = [];
                
                foreach ($players as $player) {
                    // 如果这个ID已经处理过，跳过
                    if (in_array($player['id'], $processed_ids)) {
                        continue;
                    }
                    
                    // 标记这个ID为已处理
                    $processed_ids[] = $player['id'];
                    
                    // 确保用户有name字段
                    if (!isset($player['name']) && isset($player['username'])) {
                        $player['name'] = $player['username'];
                    } elseif (!isset($player['name']) && isset($player['user_name'])) {
                        $player['name'] = $player['username'];
                    }
                    
                    // 添加默认头像
                    if (!isset($player['avatar']) || empty($player['avatar'])) {
                        $player['avatar'] = '';
                    }
                    
                    // 确保user_tag字段存在
                    if (!isset($player['user_tag'])) {
                        $player['user_tag'] = '';
                    }
                    
                    $unique_players[] = $player;
                }
                
                $players = $unique_players;
                error_log('After deduplication: ' . count($players) . ' unique users');
            }
        } catch (Exception $e) {
            error_log('Error loading users: ' . $e->getMessage());
            $players = [];
        }
    }
    
} catch(PDOException $e) {
    error_log("数据库错误: ".$e->getMessage());
}
// 最终的重复数据检查（确保无论如何都不会有重复）
$final_unique_players = [];
$final_processed_ids = [];
if (!empty($players)) {
    foreach ($players as $player) {
        if (isset($player['id']) && !in_array($player['id'], $final_processed_ids)) {
            $final_processed_ids[] = $player['id'];
            $final_unique_players[] = $player;
        }
    }
    $players = $final_unique_players;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>打手专区</title>
    <?php echo $debug_output; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {margin: 0;padding: 0;box-sizing: border-box;-webkit-tap-highlight-color: transparent;}
        body {
            background: linear-gradient(180deg, #fef9f0 0%, #f7f8fc 15%) !important;
            font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", sans-serif;
            padding-bottom: 90px;min-height:100vh;
        }
        .top-bar {
            display: flex;justify-content: space-between;align-items: center;
            padding: 16px;gap:12px;
            background: rgba(255,255,255,0.92);backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            position:sticky;top:0;z-index:100;
        }
        .top-title {
            font-size: 20px;font-weight: 800;
            background: linear-gradient(135deg, #1a1a2e, #4a4a6a);
            -webkit-background-clip: text;-webkit-text-fill-color: transparent;background-clip: text;
            letter-spacing: 1px;
        }
        .search-box {width: 58%;}
        .search-input {
            width: 100%;padding: 10px 16px;border: 2px solid #e5e7eb;
            border-radius: 12px;background: #f9fafb;font-size: 14px;color:#1a1a2e;
            transition: all 0.25s ease;font-weight:500;
        }
        .search-input:focus {
            border-color: #ffd93d;background: #fff;
            box-shadow: 0 0 0 4px rgba(255,217,61,0.12);outline: none;
        }
        .container {padding: 16px;}
        .hand-card {
            display: flex;align-items: center;gap: 14px;padding: 14px;
            margin-bottom: 12px;border: none;border-radius: 18px;
            background: #fff;box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            transition: all 0.25s cubic-bezier(0.4,0,0.2,1);
            animation: cardIn 0.4s ease backwards;
            border: 1px solid rgba(0,0,0,0.04);
        }
        .hand-card:nth-child(1){animation-delay:0.05s}
        .hand-card:nth-child(2){animation-delay:0.1s}
        .hand-card:nth-child(3){animation-delay:0.15s}
        .hand-card:nth-child(4){animation-delay:0.2s}
        .hand-card:nth-child(5){animation-delay:0.25s}
        @keyframes cardIn{from{opacity:0;transform:translateY(15px)}to{opacity:1;transform:translateY(0)}}
        .hand-card:active {transform: scale(0.97);box-shadow: 0 2px 8px rgba(0,0,0,0.08);}
        .avatar {
            width: 58px;height: 58px;border-radius: 50%;object-fit: cover;
            border: 3px solid #fef3c7;box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            flex-shrink: 0;transition: transform 0.25s ease;
        }
        .hand-card:active .avatar{transform: scale(1.05);}
        .info-wrap {flex: 1;min-width: 0;}
        .name-row {display: flex;align-items: center;gap: 6px;margin-bottom: 6px;}
        .name {font-size: 16px;font-weight: 700;color:#1a1a2e;}
        .gender-icon.female {color: #ec4899;}
        .gender-icon.male {color: #3b82f6;}
        .tag-group {display: flex;gap: 5px;flex-wrap: wrap;}
        .tag {
            font-size: 10px;padding: 3px 10px;border-radius: 8px;
            color: #fff;font-weight: 600;letter-spacing: 0.3px;
        }
        .status-text.available {
            color: #22c55e;font-weight: 700;font-size: 13px;
            background: #f0fdf4;padding: 4px 10px;border-radius: 8px;
        }
        .status-text.unavailable {
            color: #ef4444;font-weight: 700;font-size: 13px;
            background: #fef2f2;padding: 4px 10px;border-radius: 8px;
        }
        .empty-tip {
            text-align: center;padding: 60px 20px;color: #9ca3af;font-size: 14px;
        }
        .empty-tip::before{content:'🎮';display:block;font-size:48px;margin-bottom:12px;opacity:0.5;}
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

    <!-- 顶部一行 打手专区+搜索 -->
    <div class="top-bar">
        <div class="top-title">打手专区</div>
        <div class="search-box">
            <form action="" method="get">
                <input class="search-input" name="search" value="<?=htmlspecialchars($search_query)?>" placeholder="输入名字搜索打手">
            </form>
        </div>
    </div>

    <!-- 打手列表 -->
    <div class="container">
        <?php if (!empty($players)): ?>
            <?php foreach ($players as $player): ?>
                <?php
                    $player_id = isset($player['id']) ? $player['id'] : 0;
                    $player_name = isset($player['name']) ? htmlspecialchars($player['name']) : '未知用户';
                    
                    // 头像处理
                    $avatar_url = '';
                    $has_custom_avatar = false;
                    if (!empty($player['avatar'])) {
                        $avatar_path = '../assets/images/userds/' . $player['avatar'];
                        if (file_exists($avatar_path)) {
                            $avatar_url = htmlspecialchars($avatar_path);
                            $has_custom_avatar = true;
                        }
                    }
                    if (!$has_custom_avatar) {
                        $gender = isset($player['gender']) ? $player['gender'] : '';
                        $gender_folder = ($gender === 'female') ? 'women' : 'men';
                        $random_num = rand(1, 99);
                        $avatar_url = 'https://randomuser.me/api/portraits/' . $gender_folder . '/' . $random_num . '.jpg';
                    }
                    
                    // 性别图标
                    $gender = isset($player['gender']) ? $player['gender'] : '';
                    $gender_icon = '';
                    if ($gender === 'female') {
                        $gender_icon = '<i class="fas fa-venus gender-icon female"></i>';
                    } elseif ($gender === 'male') {
                        $gender_icon = '<i class="fas fa-mars gender-icon male"></i>';
                    }
                    
                    // 标签拼接
                    $tags = '';
                    if (!empty($player['user_tag'])) {
                        $decoded_tags = json_decode($player['user_tag'], true);
                        if (is_array($decoded_tags) && !empty($decoded_tags)) {
                            foreach ($decoded_tags as $tag_item) {
                                $tText = htmlspecialchars($tag_item['text'] ?? '');
                                $tColor = htmlspecialchars($tag_item['color'] ?? '#ff6b00');
                                $tags .= "<span class='tag' style='background:$tColor'>$tText</span>";
                            }
                        } else {
                            $tColor = !empty($player['user_tag_color']) ? htmlspecialchars($player['user_tag_color']) : '#ff6b00';
                            $tags .= "<span class='tag' style='background:$tColor'>".htmlspecialchars($player['user_tag'])."</span>";
                        }
                    }
                    if (!empty($player['tags'])) {
                        foreach ($player['tags'] as $tag) {
                            $tags .= '<span class="tag" style="background:#9c8e7e;color:#fff;">'.htmlspecialchars($tag['name']).'</span>';
                        }
                    }
                    
                    // 接单状态
                    $is_available = (isset($player['available_for_orders']) && $player['available_for_orders'] == 1);
                    $statusText = $is_available ? '● 可接单' : '● 休息中';
                    $statusClass = $is_available ? 'available' : 'unavailable';
                ?>
                <a href="player_detail.php?id=<?=$player_id?>" class="hand-card">
                    <img class="avatar" src="<?=$avatar_url?>" alt="<?=$player_name?>">
                    <div class="info-wrap">
                        <div class="name-row">
                            <span class="name"><?=$player_name?></span>
                            <?=$gender_icon?>
                        </div>
                        <div class="tag-group">
                            <?=$tags?>
                        </div>
                    </div>
                    <div class="status-text <?=$statusClass?>">
                        <?=$statusText?>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-tip">暂无打手数据</div>
        <?php endif; ?>
    </div>

<!-- 底部导航保留 -->
<?php include 'footer.php'; ?>
<script>
    // 去除重复卡片
    document.addEventListener('DOMContentLoaded', function() {
        const playerCards = document.querySelectorAll('.hand-card');
        const playerIds = new Set();
        let duplicateCount = 0;
        playerCards.forEach(card => {
            const pid = card.getAttribute('data-player-id');
            if(playerIds.has(pid)){
                card.remove();
                duplicateCount++;
            }else{
                playerIds.add(pid);
            }
        });
    })
</script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>