<?php
require_once 'header.php';

// 检查用户登录状态
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 获取打手ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: hunter_list.php");
    exit;
}
$hunter_id = intval($_GET['id']);

// 获取打手信息
$hunter = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM hunters WHERE id = ?");
    $stmt->execute([$hunter_id]);
    $hunter = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$hunter) {
        header("Location: hunter_list.php");
        exit;
    }
} catch (PDOException $e) {
    error_log('获取打手信息失败: ' . $e->getMessage());
    header("Location: hunter_list.php");
    exit;
}

// 获取打手动态
$dynamics = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM hunter_dynamics WHERE hunter_id = ? AND status = 1 ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$hunter_id]);
    $dynamics = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('获取打手动态失败: ' . $e->getMessage());
}

// 检查用户是否有该打手的有效订单
$hasActiveOrder = false;
try {
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE user_id = ? AND hunter_id = ? AND status IN ('pending', 'in_progress', 'completed')");
    $stmt->execute([$_SESSION['user_id'], $hunter_id]);
    $hasActiveOrder = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    error_log('检查有效订单失败: ' . $e->getMessage());
}

// 获取打赏记录
$rewards = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM hunter_rewards WHERE user_id = ? AND hunter_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id'], $hunter_id]);
    $rewards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('获取打赏记录失败: ' . $e->getMessage());
}

// 获取打手评价
$reviews = [];
try {
    $stmt = $pdo->prepare("SELECT r.*, u.username FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.hunter_id = ? ORDER BY r.created_at DESC LIMIT 3");
    $stmt->execute([$hunter_id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('获取打手评价失败: ' . $e->getMessage());
}

// 获取商品列表（用于选择下单）
$products = [];
try {
    $stmt = $pdo->query("SELECT id, name, price, image, is_lottery FROM products WHERE status = 1 ORDER BY sort_order ASC, id DESC LIMIT 50");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('获取商品列表失败: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($hunter['username']); ?> - 打手详情</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .status-online {
            background-color: #28a745;
        }
        .status-busy {
            background-color: #ffc107;
        }
        .status-offline {
            background-color: #6c757d;
        }
        .tag {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            margin-right: 5px;
            margin-bottom: 5px;
        }
        .dynamic-item {
            border-radius: 10px;
            overflow: hidden;
        }
        .rating-stars i {
            color: #ffc107;
        }
        /* 选择下单按钮 */
        .order-now-btn {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(59,130,246,0.3);
            width: 100%;
        }
        .order-now-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59,130,246,0.4);
        }
        .order-now-btn:active {
            transform: translateY(0);
        }
        /* 商品选择弹窗 */
        .product-modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15,23,42,0.5);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.25s ease;
        }
        .product-modal-overlay.show { display: flex; }
        .product-modal-box {
            width: 100%;
            max-width: 500px;
            max-height: 80vh;
            background: #fff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(59,130,246,0.2);
            animation: modalPop 0.3s cubic-bezier(0.34,1.56,0.64,1);
            display: flex;
            flex-direction: column;
        }
        @keyframes modalPop {
            from { transform: scale(0.9) translateY(20px); opacity: 0; }
            to { transform: scale(1) translateY(0); opacity: 1; }
        }
        .product-modal-head {
            padding: 18px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(135deg, #eff6ff, #f0f9ff);
        }
        .product-modal-head h3 {
            font-size: 18px;
            font-weight: 700;
            color: #2563eb;
            margin: 0;
        }
        .product-modal-close {
            border: none;
            background: none;
            font-size: 24px;
            color: #94a3b8;
            cursor: pointer;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s;
        }
        .product-modal-close:hover {
            background: #e2e8f0;
            color: #475569;
        }
        .product-modal-body {
            padding: 16px;
            overflow-y: auto;
            flex: 1;
        }
        .product-select-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 2px solid #e2e8f0;
            background: #fff;
        }
        .product-select-item:hover {
            border-color: #3b82f6;
            background: #f0f7ff;
            transform: translateX(4px);
        }
        .product-select-item img {
            width: 56px;
            height: 56px;
            border-radius: 10px;
            object-fit: cover;
            margin-right: 12px;
            flex-shrink: 0;
        }
        .product-select-info {
            flex: 1;
            min-width: 0;
        }
        .product-select-name {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .product-select-price {
            font-size: 16px;
            font-weight: 700;
            color: #ef4444;
        }
        .product-select-tag {
            display: inline-block;
            padding: 2px 8px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: #fff;
            font-size: 11px;
            border-radius: 6px;
            margin-left: 8px;
            font-weight: 600;
        }
        .product-empty {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }
        .product-empty i {
            font-size: 48px;
            margin-bottom: 12px;
            opacity: 0.5;
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

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="hunter_list.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>返回列表</a>
            <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-home me-1"></i>首页</a>
        </div>

        <!-- 打手信息卡片 -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 text-center">
                        <img src="<?php echo $hunter['avatar'] ? $hunter['avatar'] : 'https://img.icons8.com/ios-filled/100/000000/user.png'; ?>" alt="<?php echo htmlspecialchars($hunter['username']); ?>" class="rounded-circle mb-3" width="120" height="120">
                        <div class="mb-3">
                            <span class="status-indicator <?php echo $hunter['online_status'] ? 'status-online' : ($hunter['order_status'] ? 'status-busy' : 'status-offline'); ?>"></span>
                            <span class="fs-5">
                                <?php echo $hunter['online_status'] ? '在线' : ($hunter['order_status'] ? '接单中' : '离线'); ?>
                            </span>
                        </div>
                        <button class="order-now-btn" onclick="openProductModal()">
                            <i class="fas fa-shopping-cart me-1"></i>选择下单
                        </button>
                        <?php if ($hasActiveOrder): ?>
                            <button class="btn btn-warning w-100" data-bs-toggle="modal" data-bs-target="#rewardModal">
                                <i class="fas fa-gift me-1"></i>打赏
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-9">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h3 class="mb-1"><?php echo htmlspecialchars($hunter['username']); ?></h3>
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-primary me-2"><?php echo number_format($hunter['rating'], 1); ?> <i class="fas fa-star"></i></span>
                                    <span class="text-muted me-3"><?php echo $hunter['order_count']; ?> 单完成</span>
                                    <span class="text-muted"><?php echo $hunter['success_rate']; ?>% 好评率</span>
                                </div>
                            </div>
                            <div class="text-end">
                                <button class="btn btn-outline-secondary me-2"><i class="fas fa-share-alt me-1"></i>分享</button>
                                <button class="btn btn-outline-secondary"><i class="fas fa-heart me-1"></i>关注</button>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h5 class="mb-2">个人简介</h5>
                            <p class="bg-light p-3 rounded"><?php echo htmlspecialchars($hunter['introduction']) ?: '该打手暂无简介'; ?></p>
                        </div>

                        <?php if (!empty($rewards)): ?>
                        <div class="mb-4">
                            <h5 class="mb-2">打赏记录</h5>
                            <div class="list-group">
                                <?php foreach ($rewards as $reward): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted"><?php echo date('Y-m-d H:i', strtotime($reward['created_at'])); ?></small>
                                        <p class="mb-0"><?php echo htmlspecialchars($reward['notes'] ?: '无备注'); ?></p>
                                    </div>
                                    <span class="text-primary fw-bold">+<?php echo $reward['amount']; ?>元</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div>
                            <h5 class="mb-2">擅长游戏</h5>
                            <div>
                                <?php $tags = explode(',', $hunter['tags']); ?>
                                <?php foreach ($tags as $tag): ?>
                                    <?php if ($tag): ?>
                                        <span class="tag bg-light text-dark"><?php echo htmlspecialchars($tag); ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 打手动态 -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-newspaper me-2"></i>最新动态</h5>
            </div>
            <div class="card-body">
                <?php if (empty($dynamics)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-file-alt fa-3x mb-3"></i>
                        <p>该打手暂无动态</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($dynamics as $dynamic): ?>
                            <div class="col-12 mb-4">
                                <div class="card dynamic-item">
                                    <div class="card-body">
                                        <p class="mb-3"><?php echo htmlspecialchars($dynamic['content']); ?></p>
                                        <?php if ($dynamic['images']): ?>
                                            <div class="d-flex mb-3">
                                                <?php $images = explode(',', $dynamic['images']); ?>
                                                <?php foreach ($images as $img): ?>
                                                    <img src="<?php echo htmlspecialchars($img); ?>" alt="动态图片" class="me-2 mb-2" width="100" height="100" style="object-fit: cover;">
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted"><?php echo date('Y-m-d H:i', strtotime($dynamic['created_at'])); ?></small>
                                            <div>
                                                <button class="btn btn-sm btn-outline-secondary me-2"><i class="fas fa-thumbs-up me-1"></i>点赞</button>
                                                <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-comment me-1"></i>评论</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 评价展示 -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-comment-dots me-2"></i>用户评价</h5>
            </div>
            <div class="card-body">
                <?php if (empty($reviews)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-comment-slash fa-3x mb-3"></i>
                        <p>暂无评价</p>
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($reviews as $review): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between mb-2">
                                    <strong><?php echo htmlspecialchars($review['username']); ?></strong>
                                    <small class="text-muted"><?php echo date('Y-m-d', strtotime($review['created_at'])); ?></small>
                                </div>
                                <div class="rating-stars mb-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?php echo $i > $review['rating'] ? '-o' : ''; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="mb-0"><?php echo htmlspecialchars($review['content']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 打赏模态框 -->
    <div class="modal fade" id="rewardModal" tabindex="-1" aria-labelledby="rewardModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rewardModalLabel">打赏打手 - <?php echo htmlspecialchars($hunter['username']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="rewardForm">
                        <input type="hidden" name="hunter_id" value="<?php echo $hunter_id; ?>">
                        <div class="mb-3">
                            <label class="form-label">选择金额 (元)</label>
                            <div class="row g-2 mb-3">
                                <?php $amounts = [10, 20, 50, 100, 200, 500]; ?>
                                <?php foreach ($amounts as $amount): ?>
                                <div class="col-4">
                                    <button type="button" class="btn btn-outline-primary w-100 reward-amount-btn" data-amount="<?php echo $amount; ?>"><?php echo $amount; ?></button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text">自定义金额</span>
                                <input type="number" class="form-control" name="amount" min="1" placeholder="其他金额">
                                <span class="input-group-text">元</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">备注信息</label>
                            <textarea class="form-control" name="notes" rows="2" placeholder="输入打赏备注"></textarea>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                            <button type="submit" class="btn btn-warning">确认打赏</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 点单模态框 -->
    <!-- 商品选择弹窗 -->
    <div class="product-modal-overlay" id="productModal">
        <div class="product-modal-box">
            <div class="product-modal-head">
                <h3><i class="fas fa-box me-2"></i>选择商品下单</h3>
                <button class="product-modal-close" onclick="closeProductModal()">&times;</button>
            </div>
            <div class="product-modal-body">
                <?php if (empty($products)): ?>
                    <div class="product-empty">
                        <i class="fas fa-box-open"></i>
                        <p>暂无可用商品</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $prod): ?>
                        <div class="product-select-item" onclick="selectProduct(<?php echo $prod['id']; ?>)">
                            <img src="<?php echo $prod['image'] ? $prod['image'] : 'https://img.icons8.com/ios-filled/100/3b82f6/box.png'; ?>" alt="<?php echo htmlspecialchars($prod['name']); ?>">
                            <div class="product-select-info">
                                <div class="product-select-name">
                                    <?php echo htmlspecialchars($prod['name']); ?>
                                    <?php if (!empty($prod['is_lottery'])): ?>
                                        <span class="product-select-tag">抽奖</span>
                                    <?php endif; ?>
                                </div>
                                <div class="product-select-price">¥<?php echo number_format($prod['price'], 2); ?></div>
                            </div>
                            <i class="fas fa-chevron-right" style="color:#cbd5e1;font-size:14px;"></i>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openProductModal() {
            document.getElementById('productModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        function closeProductModal() {
            document.getElementById('productModal').classList.remove('show');
            document.body.style.overflow = '';
        }
        function selectProduct(productId) {
            // 跳转到下单页，自动指定当前打手
            window.location.href = 'goods.php?id=' + productId + '&hunter_id=<?php echo $hunter_id; ?>';
        }
        // 点击遮罩关闭
        document.getElementById('productModal').addEventListener('click', function(e) {
            if (e.target === this) closeProductModal();
        });
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>