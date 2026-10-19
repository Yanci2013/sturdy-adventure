<?php
ob_start();
require_once __DIR__ . '/../../includes/session_manager.php';
require_once __DIR__ . '/../../includes/common.php';
session_start();

if (!is_admin_logged_in()) {
    ob_end_clean();
    header("Location: ../login.php");
    exit();
}

$settings = getSystemSettings($pdo);
$order_mode = $settings['order_mode'] ?? 'deposit';
$min_deposit = $settings['deposit'] ?? 0;

function safeQuery($pdo, $sql, $default = []) {
    try {
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Query error: " . $e->getMessage());
        return $default;
    }
}

try {
    $member_levels = safeQuery($pdo, "SELECT * FROM member_levels WHERE status = 1 ORDER BY id ASC");

    $members = [];
    if ($order_mode === 'deposit') {
        $sql = "
            SELECT u.*,
                   ml.name as member_level_name,
                   ml.monthly_price,
                   ml.commission_rate,
                   ml.commission_enabled,
                   COALESCE(order_stats.completed_orders, 0) as completed_orders,
                   COALESCE(order_stats.total_spent, 0) as total_spent
            FROM users u
            LEFT JOIN member_levels ml ON u.member_level_id = ml.id
            LEFT JOIN (
                SELECT hunter_user_id,
                       COUNT(*) as completed_orders,
                       SUM(price) as total_spent
                FROM orders
                WHERE hunter_user_id IS NOT NULL
                  AND hunter_user_id > 0
                  AND order_status = 4
                GROUP BY hunter_user_id
            ) order_stats ON u.id = order_stats.hunter_user_id
            WHERE u.deposit >= $min_deposit AND u.status = '正常'
            ORDER BY u.created_at DESC
        ";
        $members = safeQuery($pdo, $sql);
    } else {
        $sql = "
            SELECT u.*,
                   ml.name as member_level_name,
                   ml.monthly_price,
                   ml.commission_rate,
                   ml.commission_enabled,
                   COALESCE(order_stats.completed_orders, 0) as completed_orders,
                   COALESCE(order_stats.total_spent, 0) as total_spent
            FROM users u
            LEFT JOIN member_levels ml ON u.member_level_id = ml.id
            LEFT JOIN (
                SELECT hunter_user_id,
                       COUNT(*) as completed_orders,
                       SUM(price) as total_spent
                FROM orders
                WHERE hunter_user_id IS NOT NULL
                  AND hunter_user_id > 0
                  AND order_status = 4
                GROUP BY hunter_user_id
            ) order_stats ON u.id = order_stats.hunter_user_id
            WHERE u.member_level_id IS NOT NULL
              AND u.member_end_time IS NOT NULL
              AND u.member_end_time > NOW()
              AND u.status = '正常'
            ORDER BY u.created_at DESC
        ";
        $members = safeQuery($pdo, $sql);
    }

    $stats = [
        'total_members' => count($members),
        'vip_members' => 0,
        'regular_members' => 0,
        'total_balance' => 0,
        'total_deposit' => 0,
    ];

    foreach ($members as $member) {
        if ($member['member_level_id']) {
            $stats['vip_members']++;
        } else {
            $stats['regular_members']++;
        }
        $stats['total_balance'] += floatval($member['balance'] ?? 0);
        $stats['total_deposit'] += floatval($member['deposit'] ?? 0);
    }

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "数据库查询错误";
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <title>会员等级查看 - 后台管理系统</title>
  <meta name="renderer" content="webkit">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <link href="../res/layui/css/layui.css" rel="stylesheet">
  <link href="../res/adminui/dist/css/admin.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      background: #ffffff;
      color: #333;
      line-height: 1.5;
      padding: 20px;
      font-size: 13px;
    }
    .container { max-width: 1400px; margin: 0 auto; }
    .page-header {
      background: #ffffff;
      padding: 16px;
      margin-bottom: 20px;
      border-bottom: 1px solid #eee;
    }
    .page-title { font-size: 18px; font-weight: 600; color: #000; margin-bottom: 8px; }
    .page-subtitle { color: #666; font-size: 13px; }
    .section {
      background: #ffffff;
      border-radius: 4px;
      padding: 16px;
      margin-bottom: 16px;
      border: 1px solid #eee;
    }
    .section-title {
      font-size: 16px;
      font-weight: 600;
      color: #000;
      margin-bottom: 16px;
      padding-bottom: 12px;
      border-bottom: 1px solid #eee;
    }
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 12px;
    }
    .stat-card {
      background: #f8f8f8;
      border-radius: 4px;
      padding: 16px;
      text-align: center;
      border: 1px solid #eee;
    }
    .stat-icon {
      width: 40px; height: 40px;
      border-radius: 4px;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 8px; font-size: 18px;
    }
    .stat-icon.blue { background: #e6f7ff; color: #1E9FFF; }
    .stat-icon.green { background: #f0f9ff; color: #52c41a; }
    .stat-icon.orange { background: #fff7e6; color: #fa8c16; }
    .stat-icon.purple { background: #f9f0ff; color: #722ed1; }
    .stat-icon.gold { background: #fffbe6; color: #faad14; }
    .stat-value { font-size: 24px; font-weight: 600; color: #000; margin-bottom: 4px; }
    .stat-label { font-size: 12px; color: #666; }
    .filter-bar { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
    .filter-item { display: flex; align-items: center; gap: 6px; }
    .filter-label { font-size: 12px; color: #666; }
    .filter-select, .search-input {
      padding: 6px 10px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 12px;
      background: #fff;
    }
    .filter-select:focus, .search-input:focus { 
      outline: none; 
      border-color: #1E9FFF;
      box-shadow: 0 0 0 2px rgba(30, 159, 255, 0.1);
    }
    .search-input { width: 180px; }
    .table-wrapper { overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .data-table th {
      background: #f8f8f8;
      color: #666;
      font-weight: 500;
      padding: 8px 12px;
      text-align: left;
      border-bottom: 1px solid #eee;
      white-space: nowrap;
    }
    .data-table td { padding: 8px 12px; border-bottom: 1px solid #f0f0f0; color: #666; }
    .data-table tbody tr:hover { background: #f9f9f9; }
    .badge {
      padding: 2px 8px;
      border-radius: 10px;
      font-size: 10px;
      font-weight: 500;
    }
    .badge-vip { background: #fffbe6; color: #faad14; border: 1px solid #ffe58f; }
    .badge-member { background: #e6f7ff; color: #1E9FFF; }
    .badge-inactive { background: #f5f5f5; color: #999; }
    .badge-active { background: #f0f9ff; color: #52c41a; }
    .text-money { color: #ff4d4f; font-weight: 600; }
    .text-center { text-align: center; }
    .empty-state { text-align: center; padding: 60px 20px; color: #999; }
    .level-badge {
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 500;
      background: #f8f8f8; color: #666;
    }
    .level-badge.gold { background: #fffbe6; color: #faad14; }
    .level-badge.silver { background: #f8f8f8; color: #999; }
    .level-badge.bronze { background: #fff7e6; color: #fa8c16; }
    .info-text { font-size: 11px; color: #999; }
    @media (max-width: 768px) {
      body {
        padding: 16px;
      }
      .stats-grid { grid-template-columns: repeat(2, 1fr); }
      .filter-bar { flex-direction: column; }
      .search-input { width: 100%; }
      .page-title {
        font-size: 16px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="page-header">
      <h1 class="page-title">会员等级查看</h1>
      <p class="page-subtitle">查看系统内所有可抢单会员的信息 - 接单模式: <?php echo $order_mode === 'deposit' ? '保证金模式' : '会员模式'; ?></p>
    </div>

    <?php if (isset($error)): ?>
    <div class="section">
      <div class="empty-state"><?php echo $error; ?></div>
    </div>
    <?php else: ?>

    <div class="section">
      <h2 class="section-title">会员统计</h2>
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon blue"><i class="fas fa-users"></i></div>
          <div class="stat-value"><?php echo $stats['total_members']; ?></div>
          <div class="stat-label">可接单会员</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon gold"><i class="fas fa-crown"></i></div>
          <div class="stat-value"><?php echo $stats['vip_members']; ?></div>
          <div class="stat-label">VIP会员</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon purple"><i class="fas fa-user"></i></div>
          <div class="stat-value"><?php echo $stats['regular_members']; ?></div>
          <div class="stat-label">普通会员</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green"><i class="fas fa-coins"></i></div>
          <div class="stat-value">¥<?php echo number_format($stats['total_balance'], 2); ?></div>
          <div class="stat-label">总余额</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon orange"><i class="fas fa-wallet"></i></div>
          <div class="stat-value">¥<?php echo number_format($stats['total_deposit'], 2); ?></div>
          <div class="stat-label">总保证金</div>
        </div>
      </div>
    </div>

    <div class="section">
      <h2 class="section-title">会员等级配置</h2>
      <div class="table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th>等级名称</th>
              <th>月付价格</th>
              <th>年付价格</th>
              <th>日付价格</th>
              <th>状态</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($member_levels as $level): ?>
            <tr>
              <td><strong><?php echo htmlspecialchars($level['name']); ?></strong></td>
              <td>¥<?php echo number_format($level['monthly_price'], 2); ?></td>
              <td>¥<?php echo number_format($level['yearly_price'], 2); ?></td>
              <td>¥<?php echo number_format($level['daily_price'], 2); ?></td>
              <td><span class="badge <?php echo $level['status'] ? 'badge-active' : 'badge-inactive'; ?>"><?php echo $level['status'] ? '启用' : '禁用'; ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($member_levels)): ?>
            <tr><td colspan="5" class="text-center"><div class="empty-state">暂无会员等级配置</div></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="section">
      <h2 class="section-title">可抢单会员列表</h2>
      <div class="filter-bar">
        <div class="filter-item">
          <span class="filter-label">会员等级:</span>
          <select class="filter-select" id="levelFilter">
            <option value="">全部</option>
            <?php foreach ($member_levels as $level): ?>
            <option value="<?php echo $level['id']; ?>"><?php echo htmlspecialchars($level['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-item">
          <input type="text" class="search-input" id="searchInput" placeholder="搜索用户名...">
        </div>
      </div>
      <div class="table-wrapper">
        <table class="data-table" id="membersTable">
          <thead>
            <tr>
              <th>用户名</th>
              <th>会员等级</th>
              <th>余额</th>
              <th>保证金</th>
              <th>完成订单</th>
              <th>累计已结算收益</th>
              <th>到期时间</th>
              <th>状态</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($members as $member): ?>
            <tr data-level="<?php echo $member['member_level_id'] ?? ''; ?>">
              <td><strong><?php echo htmlspecialchars($member['username']); ?></strong></td>
              <td>
                <?php if ($member['member_level_name']): ?>
                <span class="level-badge gold"><?php echo htmlspecialchars($member['member_level_name']); ?></span>
                <?php else: ?>
                <span class="level-badge">普通用户</span>
                <?php endif; ?>
              </td>
              <td><span class="text-money">¥<?php echo number_format(floatval($member['balance'] ?? 0), 2); ?></span></td>
              <td><span class="text-money">¥<?php echo number_format(floatval($member['deposit'] ?? 0), 2); ?></span></td>
              <td><?php echo intval($member['completed_orders']); ?> 单</td>
              <td><span class="text-money">¥<?php echo number_format(floatval($member['total_spent'] ?? 0), 2); ?></span></td>
              <td>
                <?php if (!empty($member['member_end_time'])): ?>
                <?php echo date('Y-m-d H:i', strtotime($member['member_end_time'])); ?>
                <?php else: ?>
                <span class="info-text">-</span>
                <?php endif; ?>
              </td>
              <td>
                <?php
                  $isAvailable = isset($member['available_for_orders']) && $member['available_for_orders'] == 1;
                ?>
                <span class="badge <?php echo $isAvailable ? 'badge-active' : 'badge-inactive'; ?>">
                  <?php echo $isAvailable ? '可接单' : '已下线'; ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($members)): ?>
            <tr><td colspan="8" class="text-center"><div class="empty-state">暂无可抢单会员</div></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php endif; ?>
  </div>

  <script src="../res/layui/layui.js"></script>
  <script>
  layui.config({ base: '../res/' }).use(['index'], function(){
      var $ = layui.$;

      $('#levelFilter').on('change', function() {
          var levelId = $(this).val();
          var searchText = $('#searchInput').val().toLowerCase();
          filterTable(levelId, searchText);
      });

      $('#searchInput').on('input', function() {
          var levelId = $('#levelFilter').val();
          var searchText = $(this).val().toLowerCase();
          filterTable(levelId, searchText);
      });

      function filterTable(levelId, searchText) {
          $('#membersTable tbody tr').each(function() {
              var row = $(this);
              var level = row.data('level');
              var username = row.find('td:first').text().toLowerCase();
              var matchLevel = levelId === '' || level == levelId;
              var matchSearch = searchText === '' || username.includes(searchText);
              row.toggle(matchLevel && matchSearch);
          });
      }
  });
  </script>
</body>
</html>