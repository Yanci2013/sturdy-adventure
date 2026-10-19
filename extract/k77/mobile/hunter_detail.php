<?php
require_once 'header.php';

// 检查用户登录状态
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 获取打手ID
$hunter_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$hunter_id) {
    die("无效的打手ID");
}

// 获取打手信息
try {
    $stmt = $pdo->prepare("SELECT u.*, COUNT(o.id) as total_orders, AVG(r.rating) as avg_rating
                           FROM users u
                           LEFT JOIN orders o ON u.id = o.hunter_user_id AND o.order_status = 4
                           LEFT JOIN reviews r ON u.id = r.hunter_id
                           WHERE u.id = ? AND u.is_hunter = 1
                           GROUP BY u.id");
    $stmt->execute([$hunter_id]);
    $hunter = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$hunter) {
        die("打手不存在或不是有效打手");
    }

    // 获取打手标签
    try {
        $stmt = $pdo->prepare("SELECT ut.* FROM user_tags ut JOIN user_tag_relations utr ON ut.id = utr.tag_id WHERE utr.user_id = ? AND ut.status = 1 ORDER BY ut.sort_order ASC");
        $stmt->execute([$hunter_id]);
        $hunter_tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $hunter_tags = [];
    }

    // 获取打手动态
    $stmt = $pdo->prepare("SELECT content, image_urls, created_at FROM hunter_dynamics
                           WHERE hunter_id = ? AND status = 'approved'
                           ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$hunter_id]);
    $dynamics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 获取用户对该打手的有效订单
    $stmt = $pdo->prepare("SELECT id FROM orders
                           WHERE user_id = ? AND hunter_user_id = ?
                           AND order_status IN (2, 3, 4)
                           ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id'], $hunter_id]);
    $active_order = $stmt->fetch();

    // 获取打赏记录
    $stmt = $pdo->prepare("SELECT amount, created_at, remarks FROM rewards
                           WHERE user_id = ? AND hunter_id = ?
                           ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id'], $hunter_id]);
    $rewards = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('获取打手详情失败: ' . $e->getMessage());
    die("系统错误，请稍后再试");
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
        .status-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 14px;
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
        .busy {
            background-color: rgba(245, 158, 11, 0.15);
            color: #c2410c;
        }
        .hunter-tag {
            display: inline-block;
            padding: 4px 12px;
            background: linear-gradient(135deg, #FF6B8B, #FF5277);
            color: white;
            border-radius: 16px;
            font-size: 13px;
            margin-right: 8px;
            margin-bottom: 8px;
            font-weight: 500;
        }
        .dynamic-item {
            border-radius: 12px;
            overflow: hidden;
        }
        .reward-btn {
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #f97316;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.4);
            border: none;
            z-index: 100;
        }
        .action-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: white;
            padding: 12px 20px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
            z-index: 100;
        }
        .modal-backdrop {
            backdrop-filter: blur(4px);
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
        <!-- 打手基本信息 -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex flex-column md:flex-row">
                    <img src="<?php echo $hunter['avatar'] ?: '../assets/images/user/default.png'; ?>" alt="<?php echo htmlspecialchars($hunter['username']); ?>" class="rounded-circle mx-auto md:mx-0 mb-3 md:mb-0 md:mr-4" width="100" height="100">
                    <div class="text-center md:text-left">
                        <div class="d-flex align-items-center justify-content-center md:justify-content-start mb-2">
                            <h2 class="mb-0 me-3"><?php echo htmlspecialchars($hunter['username']); ?></h2>
                            <span class="status-badge <?php 
                                if ($hunter['online_status']) echo 'online';
                                else echo 'offline';
                            ?>">
                                <?php echo $hunter['online_status'] ? '在线' : '离线'; ?>
                            </span>
                        </div>
                        <div class="text-muted mb-3"><?php echo htmlspecialchars($hunter['hunter_level']); ?></div>
                        <div class="mb-3">
                            <?php foreach($hunter_tags as $tag): ?>
                                <span class="hunter-tag"><?php echo htmlspecialchars($tag['name']); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="d-flex justify-content-center md:justify-content-start gap-4">
                            <div class="text-center">
                                <div class="h5 mb-0"><?php echo $hunter['total_orders']; ?></div>
                                <div class="text-muted small">累计接单</div>
                            </div>
                            <div class="text-center">
                                <div class="h5 mb-0"><?php echo number_format($hunter['avg_rating'] ?? 0, 1); ?></div>
                                <div class="text-muted small">好评率</div>
                            </div>
                            <div class="text-center">
                                <div class="h5 mb-0"><?php echo $hunter['completed_orders'] ?? 0; ?></div>
                                <div class="text-muted small">完成订单</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 打手简介 -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">个人简介</h5>
            </div>
            <div class="card-body">
                <p class="card-text"><?php echo nl2br(htmlspecialchars($hunter['description'] ?: '暂无简介')); ?></p>
            </div>
        </div>

        <!-- 打手动态 -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">最新动态</h5>
                <a href="hunter_dynamics.php?id=<?php echo $hunter_id; ?>" class="text-primary small">查看全部</a>
            </div>
            <div class="card-body">
                <?php if(empty($dynamics)): ?>
                    <div class="text-center py-4 text-muted">暂无动态</div>
                <?php else: ?>
                    <div class="dynamic-list">
                        <?php foreach($dynamics as $dynamic):
                            $images = $dynamic['image_urls'] ? explode(',', $dynamic['image_urls']) : [];
                        ?>
                            <div class="dynamic-item border rounded p-3 mb-3">
                                <p class="mb-3"><?php echo htmlspecialchars($dynamic['content']); ?></p>
                                <?php if(!empty($images)): ?>
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        <?php foreach($images as $img): ?>
                                            <img src="<?php echo htmlspecialchars($img); ?>" alt="动态图片" class="rounded" style="width: 80px; height: 80px; object-fit: cover;">
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="text-muted small"><?php echo date('Y-m-d H:i', strtotime($dynamic['created_at'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 打赏记录 -->
        <div class="card mb-5">
            <div class="card-header">
                <h5 class="mb-0">我的打赏记录</h5>
            </div>
            <div class="card-body">
                <?php if(empty($rewards)): ?>
                    <div class="text-center py-4 text-muted">暂无打赏记录</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>金额</th>
                                    <th>时间</th>
                                    <th>备注</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($rewards as $reward): ?>
                                    <tr>
                                        <td>¥<?php echo number_format($reward['amount'], 2); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($reward['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($reward['remarks'] ?: '无'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 操作栏 -->
    <div class="action-bar">
        <?php if($hunter['online_status']): ?>
            <a href="create_order.php?hunter_id=<?php echo $hunter_id; ?>"
               class="btn btn-primary w-100 py-3">
                <i class="fas fa-plus me-2"></i>指定点单
            </a>
        <?php else: ?>
            <button class="btn btn-secondary w-100 py-3" disabled>
                <i class="fas fa-user-clock me-2"></i>打手当前离线
            </button>
        <?php endif; ?>
    </div>

    <!-- 打赏按钮 (只有有有效订单时显示) -->
    <?php if($active_order): ?>
        <button class="reward-btn" data-bs-toggle="modal" data-bs-target="#rewardModal">
            <i class="fas fa-gift"></i>
        </button>
    <?php endif; ?>

    <!-- 打赏模态框 -->
    <div class="modal fade" id="rewardModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">打赏打手</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="rewardForm">
                        <input type="hidden" name="hunter_id" value="<?php echo $hunter_id; ?>">
                        <input type="hidden" name="order_id" value="<?php echo $active_order['id']; ?>">

                        <div class="mb-4">
                            <label class="form-label">选择金额</label>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <?php $amounts = [10, 20, 50, 100, 200, 500]; ?>
                                <?php foreach($amounts as $amount): ?>
                                    <button type="button" class="reward-amount-btn btn btn-outline-primary"
                                            data-amount="<?php echo $amount; ?>">
                                        ¥<?php echo $amount; ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text">¥</span>
                                <input type="number" class="form-control" id="customAmount" name="amount"
                                       min="1" placeholder="自定义金额">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="rewardRemarks" class="form-label">备注</label>
                            <textarea class="form-control" id="rewardRemarks" name="remarks"
                                      rows="2" maxlength="50"></textarea>
                            <div class="form-text">最多50个字</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" id="confirmReward">确认打赏</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 金额选择
        document.querySelectorAll('.reward-amount-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // 移除其他按钮的选中状态
                document.querySelectorAll('.reward-amount-btn').forEach(b => {
                    b.classList.remove('btn-primary');
                    b.classList.add('btn-outline-primary');
                });
                // 设置当前按钮为选中状态
                this.classList.remove('btn-outline-primary');
                this.classList.add('btn-primary');
                // 清空自定义金额
                document.getElementById('customAmount').value = '';
            });
        });

        // 自定义金额输入时取消预设金额选中
        document.getElementById('customAmount').addEventListener('input', function() {
            if(this.value) {
                document.querySelectorAll('.reward-amount-btn').forEach(btn => {
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-outline-primary');
                });
            }
        });

        // 确认打赏
        document.getElementById('confirmReward').addEventListener('click', function() {
            const form = document.getElementById('rewardForm');
            const formData = new FormData(form);
            let amount = formData.get('amount');

            // 获取选中的金额
            document.querySelectorAll('.reward-amount-btn').forEach(btn => {
                if(btn.classList.contains('btn-primary')) {
                    amount = btn.dataset.amount;
                }
            });

            if(!amount || amount <= 0) {
                alert('请选择或输入打赏金额');
                return;
            }

            // 发送打赏请求
            fetch('submit_reward.php', {
                method: 'POST',
                body: JSON.stringify({
                    hunter_id: formData.get('hunter_id'),
                    order_id: formData.get('order_id'),
                    amount: amount,
                    remarks: formData.get('remarks')
                }),
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    alert('打赏成功！');
                    window.location.reload();
                } else {
                    alert('打赏失败: ' + (data.message || '未知错误'));
                }
            })
            .catch(error => {
                console.error('打赏请求失败:', error);
                alert('打赏请求失败，请稍后再试');
            });
        });
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>