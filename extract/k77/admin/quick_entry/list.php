<?php
// 开启输出缓冲
ob_start();

require_once("../../includes/session_manager.php");
include("../../includes/common.php");
require_once("../../includes/log_helper.php");

// 检查管理员登录
if (!is_admin_logged_in()) {
    ob_end_clean();
    header("Location: ../login.php");
    exit();
}

// 处理删除请求
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // 先获取被删除的快捷入口信息
    $entry_info = null;
    try {
        $stmt = $pdo->prepare("SELECT * FROM quick_entries WHERE id = ?");
        $stmt->execute([$id]);
        $entry_info = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // 获取信息失败时继续删除操作
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM quick_entries WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = '删除成功';
        
        // 记录管理员操作日志
        $admin_id = $_SESSION['admin_id'] ?? null;
        $admin_name = $_SESSION['admin_username'] ?? '未知管理员';
        $operation_details = "删除快捷入口: ID=" . $id . ", 标题=" . ($entry_info['title'] ?? '未知');
        log_admin_operation($pdo, $admin_id, $admin_name, 'delete_quick_entry', $operation_details);
        
    } catch (PDOException $e) {
        $_SESSION['error'] = '删除失败: ' . $e->getMessage();
    }
    ob_end_clean();
    header("Location: list.php");
    exit();
}

// 获取所有快捷入口
$entries = [];
try {
    $stmt = $pdo->query("SELECT * FROM quick_entries ORDER BY sort_order ASC, id DESC");
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error loading quick entries: ' . $e->getMessage());
}

$pageTitle = "快捷入口列表";
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title><?php echo $pageTitle; ?></title>
  <meta name="renderer" content="webkit">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="../res/layui/css/layui.css?v=2.9.0" rel="stylesheet">
  <link href="../res/adminui/dist/css/admin.css?v=1.0.0" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .quick-entry-card {
      background: white;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 15px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      display: flex;
      align-items: center;
      transition: all 0.3s;
    }
    .quick-entry-card:hover {
      box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    }
    .quick-entry-icon {
      width: 60px;
      height: 60px;
      border-radius: 12px;
      overflow: hidden;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f3f4f6;
    }
    .quick-entry-icon img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .quick-entry-info {
      flex: 1;
      margin-left: 20px;
    }
    .quick-entry-title {
      font-size: 16px;
      font-weight: 600;
      color: #111827;
      margin-bottom: 5px;
    }
    .quick-entry-content {
      font-size: 13px;
      color: #6b7280;
      max-width: 400px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .quick-entry-actions {
      display: flex;
      gap: 10px;
    }
    .entry-status {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 500;
    }
    .status-enabled {
      background: #d1fae5;
      color: #065f46;
    }
    .status-disabled {
      background: #f3f4f6;
      color: #6b7280;
    }
    .entry-sort {
      color: #9ca3af;
      font-size: 12px;
      margin-right: 15px;
    }
  </style>
</head>
<body>
  <div class="layui-fluid">
    <div class="layui-row layui-col-space15">
      <div class="layui-col-md12">
        <div class="layui-card">
          <div class="layui-card-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <h2 style="font-size: 20px; font-weight: 600;">
                <i class="fas fa-th-large"></i> <?php echo $pageTitle; ?>
              </h2>
              <a href="add.php" class="layui-btn layui-btn-normal">
                <i class="fas fa-plus"></i> 新增入口
              </a>
            </div>
          </div>
          <div class="layui-card-body">
            <?php if (isset($_SESSION['success'])): ?>
            <div class="layui-alert layui-alert-success" style="margin-bottom: 15px;">
              <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
            <div class="layui-alert layui-alert-error" style="margin-bottom: 15px;">
              <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
            <?php endif; ?>

            <?php if (empty($entries)): ?>
            <div style="text-align: center; padding: 50px 0; color: #9ca3af;">
              <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px;"></i>
              <p>暂无快捷入口</p>
              <a href="add.php" class="layui-btn layui-btn-normal" style="margin-top: 15px;">添加第一个入口</a>
            </div>
            <?php else: ?>
            <?php foreach ($entries as $entry): ?>
            <div class="quick-entry-card">
              <div class="quick-entry-icon">
                <?php if (!empty($entry['icon_image'])): ?>
                <img src="../../<?php echo htmlspecialchars($entry['icon_image']); ?>" alt="<?php echo htmlspecialchars($entry['title']); ?>">
                <?php else: ?>
                <i class="fas fa-image" style="font-size: 24px; color: #9ca3af;"></i>
                <?php endif; ?>
              </div>
              <div class="quick-entry-info">
                <div class="quick-entry-title"><?php echo htmlspecialchars($entry['title']); ?></div>
                <div class="quick-entry-content">
                  <?php
                  $content_preview = strip_tags($entry['content'] ?? '');
                  echo htmlspecialchars(mb_substr($content_preview, 0, 50, 'utf-8')) . (mb_strlen($content_preview, 'utf-8') > 50 ? '...' : '');
                  ?>
                </div>
              </div>
              <span class="entry-sort">排序: <?php echo $entry['sort_order']; ?></span>
              <span class="entry-status <?php echo $entry['status'] == 1 ? 'status-enabled' : 'status-disabled'; ?>">
                <?php echo $entry['status'] == 1 ? '已启用' : '已禁用'; ?>
              </span>
              <div class="quick-entry-actions">
                <a href="add.php?id=<?php echo $entry['id']; ?>" class="layui-btn layui-btn-sm layui-btn-normal">
                  <i class="fas fa-edit"></i>
                </a>
                <a href="list.php?action=delete&id=<?php echo $entry['id']; ?>" class="layui-btn layui-btn-sm layui-btn-danger" onclick="return confirm('确定要删除这个快捷入口吗？')">
                  <i class="fas fa-trash"></i>
                </a>
              </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="../res/layui/layui.js"></script>
</body>
</html>
<?php
ob_end_flush();
?>
