<?php
require_once 'header.php';

if (empty($_SESSION['partner_id'])) {
    header("Location: partner_login.php");
    exit;
}

$partner_id = $_SESSION['partner_id'];

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$per_page = 15;

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    $sql = "SELECT pr.*, u.nickname AS hunter_name, prs.reason_name
            FROM penalty_records pr
            LEFT JOIN h5user u ON pr.penalized_id = u.id
            LEFT JOIN penalty_reasons prs ON pr.reason_id = prs.id
            WHERE pr.initiator_id = :partner_id";

    $params = [':partner_id' => $partner_id];

    if (!empty($status) && in_array($status, ['pending', 'processing', 'completed', 'closed'])) {
        $sql .= " AND pr.status = :status";
        $params[':status'] = $status;
    }

    if (!empty($search)) {
        $sql .= " AND (pr.order_no LIKE :search OR u.nickname LIKE :search OR prs.reason_name LIKE :search)";
        $params[':search'] = "%$search%";
    }

    $count_sql = "SELECT COUNT(*) FROM ($sql) AS total";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();

    $total_pages = ceil($total_records / $per_page);
    $offset = ($page - 1) * $per_page;

    $sql .= " ORDER BY pr.created_at DESC LIMIT :offset, :per_page";
    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);

    $stmt->execute();
    $penalties = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['error_msg'] = "获取罚单列表失败，请稍后再试";
    $penalties = [];
    $total_pages = 1;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>罚单管理 - 商家后台</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--primary-teal:#3baaa3;--primary-teal-dark:#2a8a84;--gold:#c9a45c;--gold-dark:#a8843d}
        body{font-family:'Noto Sans SC',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:url('../assets/1.png');background-size:cover;background-position:center;background-repeat:no-repeat;background-attachment:fixed;color:#333;line-height:1.5;-webkit-font-smoothing:antialiased;min-height:100vh;position:relative}
        .app-container{max-width:100%;margin:0 auto;padding:0 12px 80px;position:relative;z-index:1}
        .header{padding:12px;background:rgba(255,255,255,0.8);position:sticky;top:0;z-index:100;box-shadow:0 2px 10px rgba(0,0,0,.08)}
        .header-content{display:flex;align-items:center;justify-content:space-between;gap:12px}
        .header-title{font-size:18px;font-weight:700;color:#333;flex-shrink:0}
        .header-btn{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#ffcc00 0%,#ff9900 100%);border:none;color:#000;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px;text-decoration:none;transition:all .2s ease;box-shadow:0 3px 10px rgba(255,204,0,.4)}
        .header-btn:hover{transform:scale(1.1)}
        .page-title{margin:20px 0 16px;font-size:18px;font-weight:700;color:#333;display:flex;align-items:center;gap:8px}
        .page-title i{color:#ffcc00}
        .alert{padding:14px 16px;border-radius:12px;margin-bottom:16px;font-size:14px;display:flex;align-items:center;gap:8px;background:rgba(255,255,255,0.9);border:1px solid #e0e0e0}
        .alert-success{background:rgba(232,245,233,0.9)!important;border-color:#c8e6c9!important;color:#2e7d32}
        .alert-error{background:rgba(255,235,238,0.9)!important;border-color:#ffcdd2!important;color:#c62828}
        .card{background:rgba(255,255,255,0.8);border-radius:16px;padding:20px;margin-bottom:16px;box-shadow:0 4px 15px rgba(0,0,0,.08)}
        .card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #f0f0f0}
        .card-title{font-size:16px;font-weight:700;color:#333}
        .search-form{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px}
        .search-input{flex:1;min-width:200px;padding:12px 16px;border:1px solid #e0e0e0;background:rgba(255,255,255,0.9);border-radius:12px;font-size:14px;transition:all .2s ease}
        .search-input:focus{outline:none;border-color:#ffcc00;box-shadow:0 0 0 3px rgba(255,204,0,.2)}
        .search-btn{padding:12px 20px;background:linear-gradient(135deg,#ffcc00 0%,#ff9900 100%);border:none;border-radius:12px;font-size:14px;font-weight:600;color:#000;cursor:pointer;transition:all .2s ease;box-shadow:0 4px 12px rgba(255,204,0,.3)}
        .search-btn:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(255,204,0,.4)}
        .filter-tabs{display:flex;gap:8px;margin-bottom:16px;overflow-x:auto;padding-bottom:4px}
        .filter-tab{padding:10px 18px;background:rgba(255,255,255,0.8);border:1px solid #e0e0e0;border-radius:25px;font-size:13px;font-weight:600;color:#666;text-decoration:none;white-space:nowrap;transition:all .3s ease;box-shadow:0 2px 8px rgba(0,0,0,.05)}
        .filter-tab:hover{border-color:#ffcc00;transform:translateY(-2px);box-shadow:0 4px 12px rgba(255,204,0,.2)}
        .filter-tab.active{background:linear-gradient(135deg,#ffcc00 0%,#ff9900 100%);color:#000;border-color:#ffcc00;box-shadow:0 4px 15px rgba(255,204,0,.3)}
        .penalty-list{background:rgba(255,255,255,0.8);border-radius:16px;padding:16px;margin-bottom:16px;box-shadow:0 4px 15px rgba(0,0,0,.08)}
        .penalty-item{background:rgba(255,255,255,0.95);border:1px solid #e0e0e0;border-radius:12px;padding:16px;margin-bottom:12px;transition:all .3s ease}
        .penalty-item:last-child{margin-bottom:0}
        .penalty-item:hover{border-color:#ffcc00;transform:translateY(-2px);box-shadow:0 4px 15px rgba(255,204,0,.2)}
        .penalty-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #f0f0f0}
        .penalty-id{font-size:15px;font-weight:700;color:#333}
        .status-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600}
        .status-pending{background:#fff3e0;color:#f57c00}
        .status-processing{background:#e3f2fd;color:#1976d2}
        .status-completed{background:#e8f5e9;color:#388e3c}
        .status-closed{background:#f5f5f5;color:#666}
        .info-row{display:flex;margin-bottom:8px;font-size:13px}
        .info-label{width:70px;color:#999;flex-shrink:0}
        .info-value{flex:1;color:#333}
        .price-value{color:#ff4444;font-weight:700}
        .penalty-actions{display:flex;gap:8px;margin-top:12px;padding-top:12px;border-top:1px solid #f0f0f0}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 16px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;transition:all .2s ease}
        .btn-danger{background:#ffebee;color:#d32f2f}
        .btn-danger:hover{background:#ffcdd2}
        .empty-state{text-align:center;padding:60px 20px;background:rgba(255,255,255,0.8);border-radius:16px;border:1px solid #e0e0e0}
        .empty-icon{font-size:48px;color:#ccc;margin-bottom:16px}
        .empty-message{font-size:16px;font-weight:600;color:#666}
        .pagination{display:flex;justify-content:center;gap:8px;margin-top:20px}
        .page-btn{display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,0.8);border:1px solid #e0e0e0;color:#333;font-size:14px;text-decoration:none;transition:all .2s ease}
        .page-btn:hover{background:#ffcc00;color:#000;border-color:#ffcc00}
        .page-btn.active{background:linear-gradient(135deg,#ffcc00 0%,#ff9900 100%);color:#000;border-color:#ffcc00}
        footer p{font-size:22px !important;font-weight:600;background:linear-gradient(135deg,var(--gold) 0%,var(--gold-dark) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;text-align:center;margin:30px 0 20px;letter-spacing:6px}
        @media (max-width:768px){.filter-tabs{gap:6px}.filter-tab{padding:8px 14px;font-size:12px}.info-label{width:60px;font-size:12px}}
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

    <header class="header">
        <div class="header-content">
            <div class="header-title">罚单管理</div>
            <a href="index.php" class="header-btn">
                <i class="fas fa-home"></i>
            </a>
        </div>
    </header>

    <main class="app-container">
        <h1 class="page-title"><i class="fas fa-exclamation-triangle"></i> 罚单管理</h1>

        <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
        </div>
        <?php endif; ?>

        <form method="get" class="search-form">
            <input type="text" name="search" class="search-input" placeholder="搜索订单号、打手或原因..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="search-btn">
                <i class="fas fa-search"></i> 搜索
            </button>
        </form>

        <div class="filter-tabs">
            <a href="?status=" class="filter-tab <?php echo $status === '' ? 'active' : ''; ?>">全部</a>
            <a href="?status=pending" class="filter-tab <?php echo $status === 'pending' ? 'active' : ''; ?>">待处理</a>
            <a href="?status=processing" class="filter-tab <?php echo $status === 'processing' ? 'active' : ''; ?>">处理中</a>
            <a href="?status=completed" class="filter-tab <?php echo $status === 'completed' ? 'active' : ''; ?>">已完成</a>
            <a href="?status=closed" class="filter-tab <?php echo $status === 'closed' ? 'active' : ''; ?>">已关闭</a>
        </div>

        <?php if (empty($penalties)): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-inbox"></i></div>
            <div class="empty-message">暂无罚单记录</div>
        </div>
        <?php else: ?>
        <div class="penalty-list">
            <?php foreach ($penalties as $penalty): ?>
            <div class="penalty-item">
                <div class="penalty-header">
                    <div class="penalty-id">#<?php echo $penalty['id']; ?></div>
                    <?php
                        $status_class = 'status-' . $penalty['status'];
                        $status_text = '';
                        switch ($penalty['status']) {
                            case 'pending': $status_text = '待处理'; break;
                            case 'processing': $status_text = '处理中'; break;
                            case 'completed': $status_text = '已完成'; break;
                            case 'closed': $status_text = '已关闭'; break;
                            default: $status_text = $penalty['status'];
                        }
                    ?>
                    <span class="status-badge <?php echo $status_class; ?>">
                        <?php echo $status_text; ?>
                    </span>
                </div>

                <div class="info-row">
                    <div class="info-label">订单号</div>
                    <div class="info-value"><?php echo htmlspecialchars($penalty['order_no']); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">打手</div>
                    <div class="info-value"><?php echo htmlspecialchars($penalty['hunter_name'] ?? '未知'); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">罚款原因</div>
                    <div class="info-value"><?php echo htmlspecialchars($penalty['reason_name']); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">金额</div>
                    <div class="info-value price-value">¥<?php echo number_format($penalty['amount'], 2); ?></div>
                </div>

                <div class="info-row">
                    <div class="info-label">创建时间</div>
                    <div class="info-value"><?php echo date('Y-m-d H:i', strtotime($penalty['created_at'])); ?></div>
                </div>

                <div class="penalty-actions">
                    <?php if ($penalty['status'] == 'pending' || $penalty['status'] == 'processing'): ?>
                    <button class="btn btn-danger" onclick="cancelPenalty(<?php echo $penalty['id']; ?>)">
                        <i class="fas fa-times"></i> 撤销
                    </button>
                    <?php else: ?>
                    <span style="color:#999;font-size:13px;">无操作</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-btn">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </main>

    <footer>
        <p>到底啦！</p>
    </footer>

    <?php require_once 'footer.php'; ?>

<script>
function cancelPenalty(penaltyId) {
    if (!confirm('确定要撤销此罚单吗？撤销后将无法恢复。')) {
        return;
    }

    const formData = new FormData();
    formData.append('id', penaltyId);

    fetch('cancel_penalty.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        if (text.startsWith("SUCCESS:")) {
            alert(text.substring(8));
            location.reload();
        } else if (text.startsWith("ERROR:")) {
            alert(text.substring(6));
        } else {
            alert('未知响应');
        }
    })
    .catch(error => {
        alert('撤销失败: ' + error.message);
    });
}
</script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>