<?php
require_once 'header.php';

// 检查用户登录状态
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 获取筛选和排序参数
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'online';
$online_only = isset($_GET['online']) ? $_GET['online'] : '1';

// 验证排序参数
$valid_sorts = ['online', 'orders', 'rating'];
if (!in_array($sort_by, $valid_sorts)) {
    $sort_by = 'online';
}

// 构建排序SQL
$sort_sql = '';
switch ($sort_by) {
    case 'orders':
        $sort_sql = 'ORDER BY total_orders DESC';
        break;
    case 'rating':
        $sort_sql = 'ORDER BY rating DESC';
        break;
    default:
        $sort_sql = 'ORDER BY online_status DESC, last_active DESC';
}

// 在线状态筛选
$online_where = $online_only ? 'AND online_status = 1' : '';

// 获取打手列表
try {
    $stmt = $pdo->prepare("SELECT u.id, u.username, u.avatar, u.hunter_level, u.rating, u.total_orders, 
                           u.online_status, u.last_active, u.description, u.tags, u.latest_dynamic, 
                           u.dynamic_time
                    FROM users u
                    WHERE u.is_hunter = 1 $online_where
                    $sort_sql");
    $stmt->execute();
    $hunters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 为每个打手获取标签
    foreach ($hunters as &$hunter) {
        try {
            $stmt = $pdo->prepare("SELECT ut.* FROM user_tags ut JOIN user_tag_relations utr ON ut.id = utr.tag_id WHERE utr.user_id = ? AND ut.status = 1 ORDER BY ut.sort_order ASC");
            $stmt->execute([$hunter['id']]);
            $hunter['user_tags'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $hunter['user_tags'] = [];
        }
    }
    unset($hunter);
} catch (PDOException $e) {
    error_log('获取打手列表失败: ' . $e->getMessage());
    $hunters = [];
}

// 获取打手评价预览
function getHunterReviews($pdo, $hunter_id) {
    try {
        $stmt = $pdo->prepare("SELECT content, created_at FROM reviews 
                           WHERE hunter_id = ? AND rating >= 4
                           ORDER BY created_at DESC LIMIT 2");
        $stmt->execute([$hunter_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('获取打手评价失败: ' . $e->getMessage());
        return [];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>打手列表</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .hunter-card {
            transition: all 0.3s ease;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .hunter-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        .status-indicator {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .online {
            background-color: rgba(16, 185, 129, 0.15);
            color: #059669;
        }
        .offline {
            background-color: rgba(156, 163, 175, 0.15);
            color: #6b7280;
        }
        .hunter-tags .tag {
            display: inline-block;
            padding: 4px 10px;
            background: linear-gradient(135deg, #FF6B8B, #FF5277);
            color: white;
            border-radius: 14px;
            font-size: 12px;
            margin-right: 6px;
            margin-bottom: 6px;
            font-weight: 500;
        }
        .review-preview {
            position: absolute;
            bottom: 100%;
            left: 0;
            right: 0;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            padding: 10px;
            z-index: 10;
            display: none;
        }
        .hunter-card:hover .review-preview {
            display: block;
        }
        .dynamic-text {
            font-size: 12px;
            color: #6b7280;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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

    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>寻找打手</h2>
            <div class="d-flex gap-2">
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <?php switch($sort_by):
                            case 'orders': ?>接单量从高到低<?php break;
                            case 'rating': ?>好评率从高到低<?php break;
                            default: ?>在线状态<?php endswitch; ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?sort=online&online=<?php echo $online_only; ?>">在线状态</a></li>
                            <li><a class="dropdown-item" href="?sort=orders&online=<?php echo $online_only; ?>">接单量从高到低</a></li>
                            <li><a class="dropdown-item" href="?sort=rating&online=<?php echo $online_only; ?>">好评率从高到低</a></li>
                        </ul>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="onlineSwitch" <?php echo $online_only ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="onlineSwitch">仅显示在线</label>
                    </div>
                </div>
            </div>
        </div>

        <?php if(empty($hunters)): ?>
            <div class="text-center py-5">
                <div class="text-muted mb-3">暂无可用打手</div>
                <button class="btn btn-primary" onclick="window.location.reload()">刷新</button>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach($hunters as $hunter):
                    $reviews = getHunterReviews($pdo, $hunter['id']);
                    $tags = $hunter['tags'] ? explode(',', $hunter['tags']) : [];
                ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card hunter-card position-relative h-100">
                            <div class="status-indicator <?php echo $hunter['online_status'] ? 'online' : 'offline'; ?>">
                                <?php echo $hunter['online_status'] ? '在线' : '离线'; ?>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <img src="<?php echo $hunter['avatar'] ?: '../assets/images/user/default.png'; ?>" alt="<?php echo htmlspecialchars($hunter['username']); ?>" class="rounded-circle me-3" width="60" height="60">
                                    <div>
                                        <h5 class="card-title mb-1"><?php echo htmlspecialchars($hunter['username']); ?></h5>
                                        <div class="text-muted small"><?php echo htmlspecialchars($hunter['hunter_level']); ?></div>
                                    </div>
                                </div>

                                <div class="hunter-tags mb-2">
                                    <?php foreach($hunter['user_tags'] as $tag): ?>
                                        <span class="tag"><?php echo htmlspecialchars($tag['name']); ?></span>
                                    <?php endforeach; ?>
                                </div>

                                <p class="card-text text-truncate mb-2" title="<?php echo htmlspecialchars($hunter['description']); ?>">
                                    <?php echo htmlspecialchars($hunter['description']); ?>
                                </p>

                                <div class="d-flex justify-content-between text-sm mb-3">
                                    <div>累计接单: <?php echo $hunter['total_orders']; ?></div>
                                    <div>好评率: <?php echo number_format($hunter['rating'], 1); ?>/5.0</div>
                                </div>

                                <?php if(!empty($hunter['latest_dynamic'])): ?>
                                    <div class="dynamic-text mb-2" title="<?php echo htmlspecialchars($hunter['latest_dynamic']); ?>">
                                        <?php echo htmlspecialchars($hunter['latest_dynamic']); ?>
                                    </div>
                                    <div class="text-xs text-muted mb-3">
                                        <?php echo date('m-d H:i', strtotime($hunter['dynamic_time'])); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="review-preview">
                                    <div class="text-sm fw-bold mb-1">最近评价:</div>
                                    <?php if(!empty($reviews)): ?>
                                        <?php foreach($reviews as $review): ?>
                                            <div class="text-xs text-muted mb-1">
                                                <?php echo htmlspecialchars($review['content']); ?>
                                            </div>
                                            <div class="text-xs text-muted mb-2">
                                                <?php echo date('m-d', strtotime($review['created_at'])); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-xs text-muted">暂无评价</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent d-flex justify-content-center">
                                <a href="hunter_detail.php?id=<?php echo $hunter['id']; ?>"
                                   class="btn btn-primary w-100 <?php echo !$hunter['online_status'] ? 'disabled' : ''; ?>">
                                    <?php echo $hunter['online_status'] ? '查看详情' : '打手离线'; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 在线状态筛选切换
        document.getElementById('onlineSwitch').addEventListener('change', function() {
            const online = this.checked ? '1' : '0';
            window.location.href = `?sort=<?php echo $sort_by; ?>&online=${online}`;
        });
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>