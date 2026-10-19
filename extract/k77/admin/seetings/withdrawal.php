<?php
ob_start();
require_once __DIR__.'/../../includes/common.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $withdrawal_id = isset($_POST['withdrawal_id']) ? (int)$_POST['withdrawal_id'] : 0;
    $action = $_POST['action'];
    
    if ($withdrawal_id <= 0) {
        $_SESSION['error'] = '无效的提现ID';
        header('Location: withdrawal.php');
        exit();
    }

    try {
        $sql = "SELECT w.*, u.username, u.balance 
                FROM withdrawals w 
                LEFT JOIN users u ON w.user_id = u.id 
                WHERE w.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$withdrawal_id]);
        $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$withdrawal) {
            $_SESSION['error'] = '无效的提现ID';
            header('Location: withdrawal.php');
            exit();
        }

        if ($withdrawal['status'] !== 'pending') {
            $_SESSION['error'] = '提现申请状态不正确';
            header('Location: withdrawal.php');
            exit();
        }

        if (empty($withdrawal['username'])) {
            $_SESSION['error'] = '用户不存在';
            header('Location: withdrawal.php');
            exit();
        }

        $pdo->beginTransaction();

        if ($action === 'approve') {
            $update_sql = "UPDATE withdrawals SET status = 'completed', processed_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($update_sql);
            $stmt->execute([$withdrawal_id]);
            
            if ($stmt->rowCount() <= 0) {
                throw new Exception("更新提现状态失败");
            }
            
            $amount = $withdrawal['amount'];
            $user_id = $withdrawal['user_id'];
            
            $timestamp = date('YmdHi');
            $random = str_pad(mt_rand(1, 99), 2, '0', STR_PAD_LEFT);
            $withdrawal_id_suffix = str_pad(substr($withdrawal_id, -2), 2, '0', STR_PAD_LEFT);
            $order_no = 'W' . $timestamp . $random . $withdrawal_id_suffix;
            
            $insert_sql = "INSERT INTO fund_details 
                          (user_id, source, destination, amount, related_id, note, order_no)
                          VALUES 
                          (?, 'withdrawal', 'user_account', -?, ?, '提现成功', ?)";
            
            $stmt = $pdo->prepare($insert_sql);
            $stmt->execute([$user_id, $amount, $withdrawal_id, $order_no]);
            
            if ($stmt->rowCount() <= 0) {
                throw new Exception("记录资金明细失败");
            }
            
            $_SESSION['success'] = '提现申请已批准';
        } elseif ($action === 'reject') {
            $reject_reason = isset($_POST['reject_reason']) ? trim($_POST['reject_reason']) : '管理员拒绝';
            
            $update_sql = "UPDATE withdrawals SET status = 'rejected', processed_at = NOW(), reject_reason = ? WHERE id = ?";
            $stmt = $pdo->prepare($update_sql);
            $stmt->execute([$reject_reason, $withdrawal_id]);
            
            if ($stmt->rowCount() <= 0) {
                throw new Exception("更新提现状态失败");
            }
            
            $amount = $withdrawal['amount'];
            $user_id = $withdrawal['user_id'];
            
            $balance_update_sql = "UPDATE users SET balance = balance + ? WHERE id = ?";
            $stmt = $pdo->prepare($balance_update_sql);
            $stmt->execute([$amount, $user_id]);
            
            if ($stmt->rowCount() <= 0) {
                throw new Exception("恢复用户余额失败");
            }
            
            $timestamp = date('YmdHi');
            $random = str_pad(mt_rand(1, 99), 2, '0', STR_PAD_LEFT);
            $withdrawal_id_suffix = str_pad(substr($withdrawal_id, -2), 2, '0', STR_PAD_LEFT);
            $order_no = 'W' . $timestamp . $random . $withdrawal_id_suffix;
            
            $insert_sql = "INSERT INTO fund_details 
                          (user_id, source, destination, amount, related_id, note, order_no)
                          VALUES 
                          (?, 'withdrawal_refund', 'user_account', ?, ?, '提现被拒绝，金额已退回', ?)";
            
            $stmt = $pdo->prepare($insert_sql);
            $stmt->execute([$user_id, $amount, $withdrawal_id, $order_no]);
            
            if ($stmt->rowCount() <= 0) {
                throw new Exception("记录资金明细失败");
            }
            
            $_SESSION['success'] = '提现申请已拒绝，金额已退回用户账户';
        } else {
            throw new Exception("无效的操作类型");
        }
        
        $pdo->commit();
        header('Location: withdrawal.php');
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = '数据库错误: ' . $e->getMessage();
        header('Location: withdrawal.php');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
        header('Location: withdrawal.php');
        exit();
    }
}

$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$where = "1=1";
$params = [];
if (!empty($status)) {
    $where .= " AND w.status = ?";
    $params[] = $status;
}
if (!empty($search)) {
    $where .= " AND (u.username LIKE ? OR w.wechat_openid LIKE ? OR w.real_name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql = "SELECT w.*, u.username 
        FROM withdrawals w 
        LEFT JOIN users u ON w.user_id = u.id 
        WHERE $where 
        ORDER BY w.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status='pending'");
$pendingCount = $stmt->fetchColumn();
$pageTitle = '提现管理';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Ma+Shan+Zheng&family=Noto+Serif+SC:wght@400;500;600;700&family=ZCOOL+XiaoWei&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #ffffff; color: #333; line-height: 1.5; padding: 20px; font-size: 13px; }
    .app-container { max-width: 1200px; margin: 0 auto; }
    
    .header { background: #f8f9fa; border-radius: 8px; padding: 16px; margin-bottom: 20px; border: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center; }
    .header-title { font-size: 18px; font-weight: 600; color: #333; }
    
    .card { background: #f8f9fa; border-radius: 8px; padding: 16px; margin-bottom: 16px; border: 1px solid #e9ecef; transition: all 0.2s; }
    .card:hover { box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    
    .card-header { font-size: 16px; font-weight: 600; color: #333; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center; }
    
    .form-group { margin-bottom: 16px; }
    .form-label { display: block; font-size: 14px; font-weight: 500; color: #6c757d; margin-bottom: 8px; }
    .form-input { width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; transition: all 0.2s; }
    .form-input:focus { outline: none; border-color: #007bff; box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25); }
    .form-select { width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; cursor: pointer; transition: all 0.2s; }
    .form-select:focus { outline: none; border-color: #007bff; box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25); }
    
    .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer; text-decoration: none; transition: all 0.2s; }
    .btn-primary { background: #007bff; color: #fff; border-color: #007bff; }
    .btn-primary:hover { background: #0069d9; }
    .btn-secondary { background: #6c757d; color: #fff; border-color: #6c757d; }
    .btn-secondary:hover { background: #5a6268; }
    .btn-success { background: #28a745; color: #fff; border-color: #28a745; }
    .btn-success:hover { background: #218838; }
    .btn-danger { background: #dc3545; color: #fff; border-color: #dc3545; }
    .btn-danger:hover { background: #c82333; }
    .btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 3px; }
    .btn-group { display: flex; gap: 8px; flex-wrap: wrap; }
    
    .filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end; }
    .filter-item { display: flex; flex-direction: column; }
    
    .table-wrapper { overflow-x: auto; border-radius: 8px; }
    .table { width: 100%; border-collapse: collapse; font-size: 12px; min-width: 1000px; }
    .table th { background: #e9ecef; color: #495057; font-weight: 500; padding: 10px 12px; text-align: left; border-bottom: 2px solid #dee2e6; white-space: nowrap; }
    .table td { padding: 10px 12px; border-bottom: 1px solid #dee2e6; color: #333; white-space: nowrap; }
    .table tbody tr:hover { background: #f8f9fa; }
    
    .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 500; }
    .badge-success { background: #e8f5e9; color: #2e7d32; }
    .badge-warning { background: #fff3e0; color: #ef6c00; }
    .badge-danger { background: #ffebee; color: #c62828; }
    .badge-info { background: #e3f2fd; color: #1565c0; }
    
    .price-value { color: #007bff; font-weight: 600; }
    
    .empty-state { text-align: center; padding: 40px 20px; }
    .empty-icon { font-size: 32px; color: #ced4da; margin-bottom: 12px; }
    .empty-message { color: #6c757d; font-size: 14px; }
    
    .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; padding: 20px; }
    .modal.active { display: flex; }
    .modal-content { background: #fff; border: 1px solid #e9ecef; border-radius: 8px; max-width: 500px; width: 100%; max-height: 90vh; overflow-y: auto; }
    .modal-header { padding: 16px; border-bottom: 1px solid #e9ecef; font-size: 16px; font-weight: 600; color: #333; display: flex; justify-content: space-between; align-items: center; }
    .modal-close { background: none; border: none; font-size: 24px; color: #6c757d; cursor: pointer; transition: color 0.2s; }
    .modal-close:hover { color: #333; }
    .modal-body { padding: 16px; }
    .modal-footer { padding: 16px; border-top: 1px solid #e9ecef; display: flex; justify-content: flex-end; gap: 8px; }
    
    .qrcode-img { max-width: 100%; max-height: 400px; display: block; margin: 0 auto; }
    
    .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
    .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    
    @media (max-width: 480px) {
        body { padding: 12px; }
        .card { padding: 12px; }
        .header { flex-direction: column; gap: 12px; align-items: flex-start; }
        .form-input, .form-select { padding: 8px 12px; font-size: 13px; }
        .filter-item { min-width: 100%; }
        .table { font-size: 11px; }
        .table th, .table td { padding: 8px 6px; }
        .btn { padding: 8px 14px; font-size: 13px; }
    }
    </style>
</head>
<body>
    <header class="header">
        <h1 class="header-title">💰 提现管理</h1>
        <span class="badge badge-warning">
            <i class="fa fa-clock"></i> 待处理: <?php echo $pendingCount; ?>
        </span>
    </header>

    <main class="app-container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fa fa-check-circle"></i> <?php echo $_SESSION['success']; ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fa fa-exclamation-circle"></i> <?php echo $_SESSION['error']; ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">🔍 筛选条件</div>
            
            <form method="GET">
                <div class="filter-row">
                    <div class="filter-item">
                        <div class="form-group">
                            <label class="form-label">状态</label>
                            <select name="status" class="form-select">
                                <option value="">全部状态</option>
                                <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>待处理</option>
                                <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>已完成</option>
                                <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?>>已拒绝</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-item" style="flex: 2;">
                        <div class="form-group">
                            <label class="form-label">搜索</label>
                            <input type="text" name="search" placeholder="用户名、微信OpenID或姓名" 
                                   value="<?php echo htmlspecialchars($search); ?>" class="form-input">
                        </div>
                    </div>
                    
                    <div class="filter-item">
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-search"></i> 搜索
                            </button>
                            <a href="withdrawal.php" class="btn btn-secondary">重置</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">📋 提现列表</div>
            
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>申请ID</th>
                            <th>用户</th>
                            <th>提现金额</th>
                            <th>手续费</th>
                            <th>实到金额</th>
                            <th>支付方式</th>
                            <th>收款码</th>
                            <th>真实姓名</th>
                            <th>交易ID</th>
                            <th>申请时间</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($result)): ?>
                            <?php foreach ($result as $row): 
                                $statusText = [
                                    'pending' => '待处理',
                                    'completed' => '已完成',
                                    'rejected' => '已拒绝'
                                ];
                                
                                $paymentMethodText = [
                                    'wechat' => '微信',
                                    'alipay' => '支付宝'
                                ];
                                
                                $userExists = !empty($row['username']);
                                
                                $statusClass = [
                                    'pending' => 'badge-warning',
                                    'completed' => 'badge-success',
                                    'rejected' => 'badge-danger'
                                ];
                            ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td>
                                        <?php if ($userExists): ?>
                                            <?php echo htmlspecialchars($row['username']); ?>
                                        <?php else: ?>
                                            <span class="badge badge-info">用户已删除(<?php echo $row['user_id']; ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="price-value">¥<?php echo number_format($row['amount'], 2); ?></span></td>
                                    <td><span class="price-value">-¥<?php echo number_format($row['fee'], 2); ?></span></td>
                                    <td><span class="price-value">¥<?php echo number_format($row['actual_amount'], 2); ?></span></td>
                                    <td><?php echo $paymentMethodText[$row['payment_method']] ?? '未知'; ?></td>
                                    <td>
                                        <?php if ($row['payment_method'] === 'wechat' && !empty($row['wechat_qrcode'])): ?>
                                            <button class="btn btn-secondary btn-sm view-qrcode" 
                                                    data-img="/assets/images/user/<?php echo htmlspecialchars($row['wechat_qrcode']); ?>"
                                                    data-title="微信收款码">
                                                <i class="fa fa-qrcode"></i>
                                            </button>
                                        <?php elseif ($row['payment_method'] === 'alipay' && !empty($row['alipay_qrcode'])): ?>
                                            <button class="btn btn-secondary btn-sm view-qrcode" 
                                                    data-img="/assets/images/user/<?php echo htmlspecialchars($row['alipay_qrcode']); ?>"
                                                    data-title="支付宝收款码">
                                                <i class="fa fa-qrcode"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="badge badge-info">无收款码</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['real_name']); ?></td>
                                    <td><?php echo $row['wechat_transaction_id'] ? htmlspecialchars($row['wechat_transaction_id']) : '--'; ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <span class="badge <?php echo $statusClass[$row['status']]; ?>">
                                            <?php echo $statusText[$row['status']]; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if ($row['status'] == 'pending'): ?>
                                                <?php if ($userExists): ?>
                                                    <form method="post" action="withdrawal.php" style="display: inline;">
                                                        <input type="hidden" name="withdrawal_id" value="<?php echo $row['id']; ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="btn btn-success btn-sm">
                                                            <i class="fa fa-check"></i> 通过
                                                        </button>
                                                    </form>
                                                    <button class="btn btn-danger btn-sm reject-btn" data-id="<?php echo $row['id']; ?>">
                                                        <i class="fa fa-times"></i> 拒绝
                                                    </button>
                                                <?php else: ?>
                                                    <span class="badge badge-danger" title="无法操作：关联用户已被删除">
                                                        用户已删除
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="12">
                                    <div class="empty-state">
                                        <div class="empty-icon">💰</div>
                                        <div class="empty-message">没有找到提现申请</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div class="modal" id="qrcode-modal">
        <div class="modal-content">
            <div class="modal-header">
                <span id="qrcode-modal-title">收款码</span>
                <button class="modal-close" onclick="closeModal('qrcode-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <img id="qrcode-modal-image" src="" class="qrcode-img" onerror="this.src='https://sydj.bcorn.cn/assets/images/user/qr_68710f6e9764f.jpg'">
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('qrcode-modal')">关闭</button>
            </div>
        </div>
    </div>

    <div class="modal" id="reject-modal">
        <div class="modal-content">
            <div class="modal-header">
                <span>请输入拒绝原因</span>
                <button class="modal-close" onclick="closeModal('reject-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="reject-form" method="post" action="withdrawal.php">
                    <input type="hidden" name="withdrawal_id" id="reject-withdrawal-id">
                    <input type="hidden" name="action" value="reject">
                    <div class="form-group">
                        <label class="form-label">拒绝原因</label>
                        <textarea name="reject_reason" placeholder="请输入拒绝提现的原因..." class="form-input" style="min-height: 100px; resize: vertical;" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('reject-modal')">取消</button>
                <button type="submit" form="reject-form" class="btn btn-danger">确认拒绝</button>
            </div>
        </div>
    </div>

    <script>
    function openModal(modalId) {
        document.getElementById(modalId).classList.add('active');
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
    }

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

        document.querySelectorAll('.view-qrcode').forEach(btn => {
            btn.addEventListener('click', function() {
                const imgUrl = this.dataset.img;
                const title = this.dataset.title;
                document.getElementById('qrcode-modal-image').src = imgUrl;
                document.getElementById('qrcode-modal-title').textContent = title;
                openModal('qrcode-modal');
            });
        });

        document.querySelectorAll('.reject-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                document.getElementById('reject-withdrawal-id').value = id;
                openModal('reject-modal');
            });
        });
    });
    </script>
</body>
</html>
