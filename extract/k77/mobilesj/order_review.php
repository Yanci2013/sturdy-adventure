<?php
require_once 'header.php';

// 检查商家是否登录
if (!isset($_SESSION['partner_info'])) {
    $_SESSION['error_msg'] = "请先登录商家账号";
    header("Location: login.php");
    exit;
}

$partner = $_SESSION['partner_info'];
$partner_id = $partner['id']; // 商家ID，格式为'88'+数字

try {
    // 获取待审核订单 - 仅获取当前商家发布的且状态为待审核(3)的订单
    $sql = "SELECT o.*, 
                   u.username as hunter_username, 
                   u.avatar as hunter_avatar,
                   u.id as hunter_user_id
            FROM orders o 
            LEFT JOIN users u ON o.hunter_user_id = u.id 
            WHERE o.order_status = 3 AND o.fadan_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$partner['id']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error_msg'] = "数据库查询失败: " . $e->getMessage();
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>审核订单</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .card-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-size: 1.25rem;
            color: #2d3748;
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .card-title i {
            margin-right: 10px;
            color: #4a5568;
        }
        
        .badge {
            background: #f6ad55;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .badge i {
            margin-right: 5px;
        }
        
        .table-container {
            overflow-x: auto;
            padding: 0 20px 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        th {
            background: #f8fafc;
            padding: 12px 15px;
            text-align: left;
            color: #4a5568;
            font-weight: 600;
            border-bottom: 1px solid #e2e8f0;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #edf2f7;
            color: #4a5568;
        }
        
        tr:hover {
            background: #f8fafc;
        }
        
        .btn-group {
            display: flex;
            gap: 8px;
        }
        
        .btn {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 5;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s ease;
        }
        
        .btn i {
            margin-right: 5px;
        }
        
        .btn-view {
            background: #4299e1;
            color: white;
        }
        
        .btn-approve {
            background: #48bb78;
            color: white;
        }
        
        .btn-reject {
            background: #f56565;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .empty-icon {
            font-size: 3rem;
            color: #cbd5e0;
            margin-bottom: 15px;
        }
        
        .empty-title {
            font-size: 1.25rem;
            color: #4a5568;
            margin-bottom: 10px;
        }
        
        .empty-description {
            color: #718096;
            font-size: 0.875rem;
        }
        
        .alert-success {
            background: rgba(72, 187, 120, 0.1);
            border-left: 4px solid #48bb78;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            color: #065f46;
            font-weight: 500;
        }
        
        .alert-success i {
            margin-right: 10px;
            font-size: 1.25rem;
        }
        
        .user-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 8px;
        }
        
        .price-value {
            font-weight: 700;
            color: #065f46;
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

<div class="container">
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert-success">
            <i class="fas fa-check-circle"></i>
            <?php 
            echo $_SESSION['success_msg'];
            unset($_SESSION['success_msg']);
            ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert-error" style="background: rgba(245, 101, 101, 0.1); border-left: 4px solid #f56565; padding: 15px; margin-bottom: 20px; display: flex; align-items: center; color: #742a2a; font-weight: 500;">
            <i class="fas fa-exclamation-circle" style="margin-right: 10px; font-size: 1.25rem;"></i>
            <?php 
            echo $_SESSION['error_msg'];
            unset($_SESSION['error_msg']);
            ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h1 class="card-title">
                <i class="fas fa-clipboard-check"></i>
                待审核订单
            </h1>
            <div class="badge">
                <i class="fas fa-clock"></i> 等待审核
            </div>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>订单ID</th>
                        <th>订单名称</th>
                        <th>分类</th>
                        <th>价格</th>
                        <th>接单用户</th>
                        <th>完成时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($orders)): ?>
                        <?php foreach ($orders as $row): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category_name'] ?? '无'); ?></td>
                                <td class="price-value">¥<?php echo number_format($row['price'], 2); ?></td>
                                <td>
                                    <?php if ($row['hunter_user_id']): ?>
                                        <img src="<?php echo $row['hunter_avatar'] ? 'uploads/avatars/'.$row['hunter_avatar'] : 'https://via.placeholder.com/40'; ?>" 
                                             class="user-avatar">
                                        <?php echo htmlspecialchars($row['hunter_username']); ?>
                                    <?php else: ?>
                                        未接单
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $row['complete_time'] ? date('Y-m-d H:i', strtotime($row['complete_time'])) : '未完成'; ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="order_view.php?id=<?php echo $row['id']; ?>" 
                                           class="btn btn-view">
                                            <i class="fas fa-eye"></i> 查看
                                        </a>
                                        <a href="order_review_approve.php?id=<?php echo $row['id']; ?>" 
                                           class="btn btn-approve">
                                            <i class="fas fa-check"></i> 通过
                                        </a>
                                        <a href="order_review_reject.php?id=<?php echo $row['id']; ?>" 
                                           class="btn btn-reject"
                                           onclick="return confirm('确定要拒绝这个订单吗？请确保已与接单用户沟通')">
                                            <i class="fas fa-times"></i> 拒绝
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="fas fa-inbox"></i>
                                    </div>
                                    <div class="empty-title">没有待审核的订单</div>
                                    <div class="empty-description">目前没有需要审核的订单，请稍后再查看</div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>