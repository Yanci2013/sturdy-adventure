<?php
require_once 'header.php';

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

try {
    // 先获取总数（使用子查询确保唯一性）
    $count_sql = "SELECT COUNT(DISTINCT pr.id) as total FROM penalty_records pr WHERE pr.status = 'completed'";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute();
    $total = $count_stmt->fetchColumn();
    $total_pages = ceil($total / $per_page);
    
    // 使用 DISTINCT 确保每个罚单只出现一次
    $sql = "SELECT DISTINCT pr.*, prs.reason_name, 
                   p.name as partner_name,
                   penalized.username as penalized_name,
                   penalized.phone as penalized_phone
            FROM penalty_records pr
            LEFT JOIN penalty_reasons prs ON pr.reason_id = prs.id
            LEFT JOIN partners p ON pr.initiator_id = p.id
            LEFT JOIN users penalized ON pr.penalized_id = penalized.id
            WHERE pr.status = 'completed'
            ORDER BY pr.created_at DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $penalties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "数据库错误: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>已成功罚单记录</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Ma+Shan+Zheng&family=Noto+Serif+SC:wght@400;500;600;700&family=ZCOOL+XiaoWei&display=swap" rel="stylesheet">
    <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #ffffff; color: #333; line-height: 1.5; padding: 20px; font-size: 13px; }
    .app-container { max-width: 1200px; margin: 0 auto; }
    
    .header { background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 20px; border: 1px solid #e9ecef; display: flex; align-items: center; justify-content: space-between; }
    .header-title { font-size: 18px; font-weight: 600; color: #333; }
    
    .back-btn { display: inline-flex; align-items: center; gap: 6px; color: #6c757d; text-decoration: none; font-size: 14px; padding: 8px 16px; border: 1px solid #ced4da; border-radius: 4px; transition: all 0.2s; }
    .back-btn:hover { background: #e9ecef; }
    
    .card { background: #f8f9fa; border-radius: 8px; padding: 16px; margin-bottom: 16px; border: 1px solid #e9ecef; transition: all 0.2s; }
    .card:hover { box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    
    .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #e9ecef; }
    .card-title { font-size: 16px; font-weight: 600; color: #333; }
    
    .badge { display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 500; }
    .badge-completed { background: #e8f5e9; color: #2e7d32; }
    
    .detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; }
    .detail-row { display: flex; justify-content: space-between; padding: 8px 0; }
    .detail-label { font-size: 14px; color: #6c757d; }
    .detail-value { font-size: 14px; color: #333; font-weight: 500; }
    
    .amount { color: #c62828; font-size: 18px; font-weight: 600; }
    
    .reason-text { font-size: 14px; color: #6c757d; margin-top: 16px; padding-top: 16px; border-top: 1px solid #e9ecef; }
    
    .empty-state { text-align: center; padding: 40px 20px; }
    .empty-icon { font-size: 32px; color: #ced4da; margin-bottom: 12px; }
    .empty-message { color: #6c757d; font-size: 14px; }
    
    .pagination { margin-top: 20px; display: flex; justify-content: center; gap: 4px; flex-wrap: wrap; }
    .pagination a, .pagination span { display: inline-flex; align-items: center; justify-content: center; min-width: 32px; height: 32px; padding: 0 8px; border: 1px solid #dee2e6; background: #fff; color: #495057; font-size: 12px; text-decoration: none; border-radius: 4px; transition: all 0.2s; }
    .pagination a:hover { background: #e9ecef; }
    .pagination a.active { background: #007bff; color: #fff; border-color: #007bff; }
    
    .evidence-images { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 16px; }
    .evidence-image { width: 100px; height: 100px; object-fit: cover; border-radius: 6px; cursor: pointer; transition: transform 0.2s; border: 1px solid #dee2e6; }
    .evidence-image:hover { transform: scale(1.05); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    
    .message-notice { background: #f8f9fa; padding: 16px; margin-bottom: 16px; border: 1px solid #e9ecef; font-size: 14px; }
    .message-notice.error { border-left: 4px solid #dc3545; color: #721c24; background: #f8d7da; }
    
    .no-data { color: #6c757d; font-style: italic; }
    
    @media (max-width: 768px) {
        .app-container { padding: 0 12px; }
        .header { flex-direction: column; gap: 12px; align-items: flex-start; }
        .header-title { font-size: 16px; }
        .card { padding: 12px; }
        .detail-grid { grid-template-columns: 1fr; }
        .evidence-image { width: 80px; height: 80px; }
    }
    </style>
</head>
<body>
    <div class="app-container">
        <?php if (isset($error) && $error): ?>
            <div class="message-notice error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- 注释掉不存在的返回链接
        <div class="header">
            <a href="penalty_list.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                返回罚单列表
            </a>
            <h1 class="header-title">
                <i class="fas fa-check-circle" style="color: #16BA00; margin-right: 10px;"></i>
                已成功罚单记录
            </h1>
            <div style="width: 120px;"></div>
        </div>
        -->
        
        <?php if (!empty($penalties)): ?>
            <?php 
            // 确保罚单唯一性
            $unique_penalties = [];
            foreach ($penalties as $penalty) {
                $unique_penalties[$penalty['id']] = $penalty;
            }
            ?>
            
            <?php foreach ($unique_penalties as $penalty): ?>
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">
                            <i class="fas fa-exclamation-triangle" style="margin-right: 8px; color: #dc2626;"></i>
                            罚单 #<?php echo $penalty['id']; ?>
                        </span>
                        <span class="badge badge-completed">
                            已成功
                        </span>
                    </div>
                    
                    <div class="detail-grid">
                        <div>
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
                        </div>
                        <div>
                            <div class="detail-row">
                                <span class="detail-label">被罚用户</span>
                                <span class="detail-value">
                                    <?php echo htmlspecialchars($penalty['penalized_name'] ?? '未知'); ?>
                                    <?php if (!empty($penalty['penalized_phone'])): ?>
                                        (<?php echo htmlspecialchars($penalty['penalized_phone']); ?>)
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">开单时间</span>
                                <span class="detail-value"><?php echo date('Y-m-d H:i', strtotime($penalty['created_at'])); ?></span>
                            </div>
                            <?php if (!empty($penalty['status_time'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label">完成时间</span>
                                    <span class="detail-value"><?php echo date('Y-m-d H:i', strtotime($penalty['status_time'])); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($penalty['deduction_balance']) && $penalty['deduction_balance'] > 0): ?>
                        <div class="detail-row">
                            <span class="detail-label">扣除余额</span>
                            <span class="detail-value amount">-¥<?php echo number_format($penalty['deduction_balance'], 2); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($penalty['deduction_deposit']) && $penalty['deduction_deposit'] > 0): ?>
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
                                <?php 
                                $image_src = trim($image);
                                if (!empty($image_src)) {
                                    if (!filter_var($image_src, FILTER_VALIDATE_URL)) {
                                        $image_src = '../assets/images/penalty_evidence/' . $image_src;
                                    }
                                ?>
                                    <img src="<?php echo htmlspecialchars($image_src); ?>" 
                                         class="evidence-image"
                                         alt="证据图片"
                                         onclick="window.open('<?php echo htmlspecialchars($image_src); ?>', '_blank')"
                                         onerror="this.style.display='none'">
                                <?php } ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1" title="第一页">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?page=<?php echo $page - 1; ?>" title="上一页">
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
                        <a href="?page=<?php echo $page + 1; ?>" title="下一页">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?page=<?php echo $total_pages; ?>" title="最后一页">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">📋</div>
                <div class="empty-message">暂无已成功的罚单记录</div>
            </div>
        <?php endif; ?>
    </div>

    <?php require_once 'footer.php'; ?>
</body>
</html>