<?php
// 开启输出缓冲
ob_start();

require_once("../../includes/session_manager.php");
include("../../includes/common.php");
include("../../includes/log_helper.php");

// 检查管理员登录
if (!is_admin_logged_in()) {
    ob_end_clean();
    header("Location: ../login.php");
    exit();
}

$admin_id = get_admin_id();
$admin_name = $_SESSION['admin_username'] ?? '管理员';

// 获取筛选条件（只显示登录相关）
$filter_admin_id = isset($_GET['admin_id']) ? intval($_GET['admin_id']) : null;
$filter_action = isset($_GET['action']) ? trim($_GET['action']) : null;
$filter_start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : null;
$filter_end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : null;

// 如果没有指定操作类型，默认只显示登录相关
if (empty($filter_action)) {
    $filter_action = 'login,login_failed,logout';
}

// 分页
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$page_size = 20;

// 获取日志列表
$result = get_admin_logs($pdo, $page, $page_size, $filter_admin_id, $filter_action, $filter_start_date, $filter_end_date);
$logs = $result['data'];
$total = $result['total'];
$total_pages = ceil($total / $page_size);

// 获取所有管理员列表（用于筛选）
$admins = [];
try {
    $stmt = $pdo->query("SELECT id, username FROM admins ORDER BY id");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// 获取所有操作类型（只显示登录相关）
$actions = [
    ['action' => 'login'],
    ['action' => 'login_failed'],
    ['action' => 'logout']
];

$pageTitle = "管理员登录日志";
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
    .log-card {
      background: white;
      padding: 10px 15px;
      margin-bottom: 5px;
      border-bottom: 1px solid #f3f4f6;
    }
    .log-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 5px;
    }
    .log-admin {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .log-admin-avatar {
      width: 24px;
      height: 24px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 600;
      font-size: 12px;
    }
    .log-admin-name {
      font-weight: 600;
      color: #111827;
      font-size: 13px;
    }
    .log-action-badge {
      padding: 2px 8px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 500;
      background: #dbeafe;
      color: #1d4ed8;
    }
    .log-time {
      font-size: 12px;
      color: #6b7280;
    }
    .log-description {
      font-size: 13px;
      color: #374151;
      margin-bottom: 5px;
      line-height: 1.4;
    }
    .log-details {
      background: #f9fafb;
      border-radius: 4px;
      padding: 8px;
      font-size: 12px;
    }
    .log-detail-row {
      display: flex;
      margin-bottom: 3px;
    }
    .log-detail-row:last-child {
      margin-bottom: 0;
    }
    .log-detail-label {
      color: #6b7280;
      min-width: 70px;
      font-size: 12px;
    }
    .log-detail-value {
      color: #111827;
      word-break: break-all;
      font-size: 12px;
    }
    .log-ip {
      color: #9ca3af;
      font-size: 11px;
      margin-top: 5px;
    }
    .filter-section {
      background: white;
      border-radius: 8px;
      padding: 15px;
      margin-bottom: 15px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
  </style>
</head>
<body>
  <div class="layui-fluid">
    <div class="layui-row layui-col-space15">
      <div class="layui-col-md12">
        <div class="layui-card">
          <div class="layui-card-header">
            <h2 style="font-size: 20px; font-weight: 600;">
              <i class="fas fa-history"></i> <?php echo $pageTitle; ?>
            </h2>
          </div>
          <div class="layui-card-body">
            <!-- 筛选区域 -->
            <div class="filter-section">
              <form class="layui-form" method="GET" action="">
                <div class="layui-form-item">
                  <div class="layui-inline">
                    <label class="layui-form-label">管理员</label>
                    <div class="layui-input-inline" style="width: 150px;">
                      <select name="admin_id">
                        <option value="">全部</option>
                        <?php foreach ($admins as $admin): ?>
                        <option value="<?php echo $admin['id']; ?>" <?php echo $filter_admin_id == $admin['id'] ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars($admin['username']); ?>
                        </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                  <div class="layui-inline">
                    <label class="layui-form-label">操作类型</label>
                    <div class="layui-input-inline" style="width: 150px;">
                      <select name="action">
                        <option value="login,login_failed,logout" <?php echo $filter_action == 'login,login_failed,logout' ? 'selected' : ''; ?>>全部登录相关</option>
                        <option value="login" <?php echo $filter_action == 'login' ? 'selected' : ''; ?>>登录成功</option>
                        <option value="login_failed" <?php echo $filter_action == 'login_failed' ? 'selected' : ''; ?>>登录失败</option>
                        <option value="logout" <?php echo $filter_action == 'logout' ? 'selected' : ''; ?>>退出登录</option>
                      </select>
                    </div>
                  </div>
                  <div class="layui-inline">
                    <label class="layui-form-label">日期范围</label>
                    <div class="layui-input-inline" style="width: 150px;">
                      <input type="date" name="start_date" value="<?php echo $filter_start_date; ?>" class="layui-input">
                    </div>
                    <div class="layui-form-mid">-</div>
                    <div class="layui-input-inline" style="width: 150px;">
                      <input type="date" name="end_date" value="<?php echo $filter_end_date; ?>" class="layui-input">
                    </div>
                  </div>
                  <div class="layui-inline">
                    <button type="submit" class="layui-btn layui-btn-normal"><i class="fas fa-search"></i> 筛选</button>
                    <a href="admin_logs.php" class="layui-btn layui-btn-primary">重置</a>
                  </div>
                </div>
              </form>
            </div>

            <!-- 日志列表 -->
            <?php if (empty($logs)): ?>
            <div style="text-align: center; padding: 50px 0; color: #9ca3af;">
              <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px;"></i>
              <p>暂无日志记录</p>
            </div>
            <?php else: ?>
            <?php foreach ($logs as $log): ?>
            <div class="log-card">
              <div class="log-header">
                <div class="log-admin">
                  <div class="log-admin-avatar"><?php echo mb_substr($log['admin_name'], 0, 1, 'utf-8'); ?></div>
                  <span class="log-admin-name"><?php echo htmlspecialchars($log['admin_name']); ?></span>
                  <span class="log-action-badge"><?php echo htmlspecialchars($log['action']); ?></span>
                </div>
                <span class="log-time"><i class="fas fa-clock"></i> <?php echo $log['created_at']; ?></span>
              </div>
              <div class="log-description"><?php echo htmlspecialchars($log['description']); ?></div>
              <?php if ($log['target_type'] || $log['old_value'] || $log['new_value']): ?>
              <div class="log-details">
                <?php if ($log['target_type']): ?>
                <div class="log-detail-row">
                  <span class="log-detail-label">操作对象</span>
                  <span class="log-detail-value"><?php echo htmlspecialchars($log['target_type']); ?> #<?php echo htmlspecialchars($log['target_id']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($log['old_value']): ?>
                <div class="log-detail-row">
                  <span class="log-detail-label">修改前</span>
                  <span class="log-detail-value"><?php echo htmlspecialchars(mb_substr($log['old_value'], 0, 200, 'utf-8')); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($log['new_value']): ?>
                <div class="log-detail-row">
                  <span class="log-detail-label">修改后</span>
                  <span class="log-detail-value"><?php echo htmlspecialchars(mb_substr($log['new_value'], 0, 200, 'utf-8')); ?></span>
                </div>
                <?php endif; ?>
              </div>
              <?php endif; ?>
              <div class="log-ip"><i class="fas fa-map-marker-alt"></i> IP: <?php echo htmlspecialchars($log['ip_address']); ?></div>
            </div>
            <?php endforeach; ?>

            <!-- 分页 -->
            <?php if ($total_pages > 1): ?>
            <div style="text-align: center; margin-top: 30px;">
              <div class="layui-box layui-laypage layui-laypage-default">
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&admin_id=<?php echo $filter_admin_id; ?>&action=<?php echo urlencode($filter_action); ?>&start_date=<?php echo $filter_start_date; ?>&end_date=<?php echo $filter_end_date; ?>" class="layui-laypage-prev">上一页</a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?page=<?php echo $i; ?>&admin_id=<?php echo $filter_admin_id; ?>&action=<?php echo urlencode($filter_action); ?>&start_date=<?php echo $filter_start_date; ?>&end_date=<?php echo $filter_end_date; ?>" class="<?php echo $i == $page ? 'layui-this' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&admin_id=<?php echo $filter_admin_id; ?>&action=<?php echo urlencode($filter_action); ?>&start_date=<?php echo $filter_start_date; ?>&end_date=<?php echo $filter_end_date; ?>" class="layui-laypage-next">下一页</a>
                <?php endif; ?>
              </div>
              <div style="margin-top: 10px; color: #6b7280;">共 <?php echo $total; ?> 条记录</div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="../res/layui/layui.js"></script>
  <script>
    layui.use(['form'], function(){
      var form = layui.form;
      form.render();
    });
  </script>
</body>
</html>
<?php
ob_end_flush();
?>
