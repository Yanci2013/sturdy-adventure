<?php
include 'header.php';

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

try {
    $count_sql = "SELECT COUNT(*) as total FROM penalty_records WHERE penalized_id = ? AND status = 'completed'";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute([$user_id]);
    $total = $count_stmt->fetchColumn();
    $total_pages = ceil($total / $per_page);
    
    $sql = "SELECT pr.*, prs.reason_name, MAX(p.name) as partner_name
            FROM penalty_records pr
            LEFT JOIN penalty_reasons prs ON pr.reason_id = prs.id
            LEFT JOIN partners p ON pr.initiator_id = p.id
            WHERE pr.penalized_id = ? AND pr.status = 'completed'
            GROUP BY pr.id
            ORDER BY pr.created_at DESC
            LIMIT " . $per_page . " OFFSET " . $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $penalties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $statusText = [
        'pending' => '待审核',
        'processing' => '处理中',
        'completed' => '已完成',
        'closed' => '已关闭'
    ];
    
} catch (PDOException $e) {
    $error = "数据库错误: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>罚单记录</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: url('../assets/1.png') no-repeat center center fixed;
            background-size: cover;
            color: #1d1d1f;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            margin: 0;
            padding: 0;
            animation: fadeInUp 0.6s ease-out;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.2);
            z-index: -1;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ===== 实体固定header ===== */
        .fixed-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #ffffff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            z-index: 1000;
            padding: 16px 16px;
            border-bottom: 1px solid #f0f0f0;
            height: 70px; /* 固定高度 */
            display: flex;
            align-items: center;
        }

        .fixed-header .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            max-width: 100%;
        }

        .fixed-header .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .fixed-header .back-btn {
            color: #333;
            font-size: 18px;
            text-decoration: none;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #f5f5f5;
        }

        .fixed-header .back-btn:hover {
            background: #e8e8e8;
        }

        .fixed-header .header-title {
            font-size: 18px;
            font-weight: 600;
            color: #000000;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .fixed-header .header-title i {
            color: #dc2626;
            font-size: 20px;
        }

        .fixed-header .header-right {
            color: #666;
            font-size: 16px;
        }

        /* 隐藏原有的header，避免重复 */
        .header {
            display: none;
        }

        .app-container {
            max-width: 100%;
            margin: 0 auto;
            padding: 85px 12px 20px 12px; /* 上内边距 = header高度(70px) + 额外15px间距 */
        }

        /* 其余原有样式保持不变 */
        .card {
            background: #ffffff;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f0f0f0;
        }

        .card-title {
            font-size: 15px;
            font-weight: 600;
            color: #000000;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-processing {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-completed {
            background: #dcfce7;
            color: #166534;
        }

        .badge-closed {
            background: #f3f4f6;
            color: #4b5563;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .detail-label {
            font-size: 13px;
            color: #666666;
        }

        .detail-value {
            font-size: 14px;
            color: #1d1d1f;
            font-weight: 500;
        }

        .amount {
            color: #dc2626;
            font-size: 16px;
            font-weight: 600;
        }

        .reason-text {
            font-size: 13px;
            color: #666666;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #f0f0f0;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-icon {
            font-size: 48px;
            color: #cccccc;
            margin-bottom: 12px;
        }

        .empty-message {
            color: #999999;
            font-size: 15px;
        }

        .pagination {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 4px;
            flex-wrap: wrap;
        }

        .pagination a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 8px;
            border: 1px solid #eaeaea;
            background: #ffffff;
            color: #666666;
            font-size: 13px;
            text-decoration: none;
            transition: all 0.2s ease;
            border-radius: 8px;
        }

        .pagination a:hover {
            background: #fafafa;
            border-color: #999999;
            color: #222222;
        }

        .pagination a.active {
            background: #000000;
            color: #ffffff;
            border-color: #000000;
            font-weight: 500;
        }

        .evidence-images {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .evidence-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .evidence-image:hover {
            transform: scale(1.05);
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #000000;
            text-decoration: none;
            font-size: 14px;
        }

        .message-notice {
            background: #ffffff;
            border-radius: 12px;
            padding: 16px;
            margin: 12px 0;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            font-size: 14px;
        }

        .message-notice.success {
            border-left: 4px solid #4cd964;
            color: #2e7d32;
        }

        .message-notice.error {
            border-left: 4px solid #ff3b30;
            color: #c62828;
        }

        @media (max-width: 480px) {
            .fixed-header {
                height: 60px;
                padding: 12px 12px;
            }
            
            .fixed-header .header-title {
                font-size: 16px;
            }
            
            .app-container {
                padding: 75px 12px 20px 12px; /* 移动端调整 */
            }

            .card {
                padding: 14px;
            }

            .pagination a {
                min-width: 32px;
                height: 32px;
                font-size: 12px;
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


    <!-- 实体固定header - 用div实现 -->
    <div class="fixed-header">
        <div class="header-content">
            <div class="header-left">
                <a href="javascript:history.back();" class="back-btn">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <span class="header-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    罚单中心
                </span>
            </div>
            <div class="header-right">
                <i class="fas fa-ellipsis-v"></i>
            </div>
        </div>
    </div>

    <main class="app-container">
        <?php if (isset($success) && $success): ?>
            <div class="message-notice success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error) && $error): ?>
            <div class="message-notice error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($penalties)): ?>
            <?php foreach ($penalties as $penalty): ?>
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">
                            <i class="fas fa-exclamation-triangle" style="margin-right: 8px; color: #dc2626;"></i>
                            罚单 #<?php echo $penalty['id']; ?>
                        </span>
                        <span class="badge badge-<?php echo $penalty['status']; ?>">
                            <?php echo $statusText[$penalty['status']]; ?>
                        </span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">罚款金额</span>
                        <span class="detail-value amount">-¥<?php echo number_format($penalty['amount'], 2); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">罚款原因</span>
                        <span class="detail-value"><?php echo htmlspecialchars($penalty['reason_name'] ?? '未知'); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">发单商家</span>
                        <span class="detail-value"><?php echo htmlspecialchars($penalty['partner_name'] ?? '系统'); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">开单时间</span>
                        <span class="detail-value"><?php echo date('Y-m-d H:i', strtotime($penalty['created_at'])); ?></span>
                    </div>
                    
                    <?php if ($penalty['deduction_balance'] > 0): ?>
                        <div class="detail-row">
                            <span class="detail-label">扣除余额</span>
                            <span class="detail-value amount">-¥<?php echo number_format($penalty['deduction_balance'], 2); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($penalty['deduction_deposit'] > 0): ?>
                        <div class="detail-row">
                            <span class="detail-label">扣除保证金</span>
                            <span class="detail-value amount">-¥<?php echo number_format($penalty['deduction_deposit'], 2); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($penalty['review_reply'])): ?>
                        <div class="reason-text">
                            <strong>审核回复：</strong><?php echo htmlspecialchars($penalty['review_reply']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($penalty['evidence_image'])): ?>
                        <?php $images = explode(',', $penalty['evidence_image']); ?>
                        <div class="evidence-images">
                            <?php foreach ($images as $image): ?>
                                <img src="../assets/images/penalty_evidence/<?php echo htmlspecialchars(trim($image)); ?>" 
                                     class="evidence-image"
                                     onclick="window.open('../assets/images/penalty_evidence/<?php echo htmlspecialchars(trim($image)); ?>', '_blank')">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?page=<?php echo $page - 1; ?>">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($end_page - $start_page < 4) {
                        if ($page < 3) {
                            $end_page = min($total_pages, $start_page + 4);
                        } else {
                            $start_page = max(1, $end_page - 4);
                        }
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <a href="?page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?page=<?php echo $total_pages; ?>">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">📋</div>
                <div class="empty-message">暂无罚单记录</div>
            </div>
        <?php endif; ?>
    </main>

    <?php include 'footer.php'; ?>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>