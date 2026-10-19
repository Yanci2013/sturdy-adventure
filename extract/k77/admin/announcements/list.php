<?php
require_once("../../includes/session_manager.php");
include("../../includes/common.php");

// 检查管理员登录
if (!is_admin_logged_in()) {
    header("Location: ../login.php");
    exit();
}

// 分页参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// 获取公告列表
$announcements = [];
$total_pages = 0;
try {
    global $pdo;
    if ($pdo) {
        $stmt = $pdo->query("SHOW TABLES LIKE 'announcements'");
        $table_exists = $stmt->rowCount() > 0;
        if ($table_exists) {
            // 获取总记录数
            $count_stmt = $pdo->query("SELECT COUNT(*) as total FROM announcements");
            $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
            $total_pages = ceil($total_records / $per_page);
            
            // 获取分页数据
            $stmt = $pdo->prepare("SELECT * FROM announcements ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Exception $e) {
    error_log('Error loading announcements: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>公告列表</title>
  <meta name="renderer" content="webkit">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #ffffff; color: #333; line-height: 1.5; padding: 20px; font-size: 13px; }
    .container { max-width: 1200px; margin: 0 auto; }
    h2 { font-size: 18px; font-weight: 600; margin-bottom: 16px; color: #000; }
    
    .action-bar {
      display: flex;
      justify-content: flex-start;
      margin-bottom: 16px;
    }
    
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 16px;
      background: #f5f5f5;
      color: #333;
      text-decoration: none;
      border-radius: 8px;
      font-size: 13px;
      transition: all 0.2s;
    }
    
    .btn:hover {
      background: #e0e0e0;
    }
    
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 12px;
      margin-top: 10px;
    }
    
    th {
      background: #f8f8f8;
      color: #666;
      font-weight: 500;
      padding: 10px 12px;
      text-align: left;
      border-bottom: 1px solid #eee;
    }
    
    td {
      padding: 10px 12px;
      border-bottom: 1px solid #f5f5f5;
      color: #333;
    }
    
    tr:hover td {
      background: #fafafa;
    }
    
    .announcement-content {
      max-width: 300px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    
    .badge {
      display: inline-block;
      padding: 2px 6px;
      border-radius: 3px;
      font-size: 10px;
    }
    
    .badge-published {
      background: #e8f5e9;
      color: #388e3c;
    }
    
    .badge-unpublished {
      background: #f5f5f5;
      color: #666;
    }
    
    .btn-sm {
      padding: 6px 12px;
      font-size: 12px;
      border-radius: 6px;
    }
    
    .btn-primary {
      background: #e3f2fd;
      color: #1976d2;
    }
    
    .btn-primary:hover {
      background: #bbdefb;
    }
    
    .btn-danger {
      background: #ffebee;
      color: #d32f2f;
    }
    
    .btn-danger:hover {
      background: #ffcdd2;
    }
    
    .empty {
      color: #999;
      padding: 20px 0;
      text-align: center;
    }
    
    .pagination {
      display: flex;
      justify-content: center;
      gap: 4px;
      margin-top: 20px;
    }
    
    .pagination a {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      background: #f5f5f5;
      color: #333;
      text-decoration: none;
      border-radius: 6px;
      font-size: 12px;
      transition: all 0.2s;
    }
    
    .pagination a:hover {
      background: #e0e0e0;
    }
    
    .pagination a.active {
      background: #1976d2;
      color: #ffffff;
    }
    
    .pagination a.disabled {
      background: #f5f5f5;
      color: #999;
      cursor: not-allowed;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>公告列表</h2>
    
    <div class="action-bar">
      <a href="create.php" class="btn">
        <i class="fas fa-plus"></i> 新增公告
      </a>
    </div>
    
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>标题</th>
          <th>内容预览</th>
          <th>状态</th>
          <th>发布时间</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($announcements)): ?>
          <?php foreach ($announcements as $item): ?>
            <tr>
              <td><?php echo $item['id']; ?></td>
              <td><?php echo htmlspecialchars($item['title']); ?></td>
              <td class="announcement-content"><?php echo strip_tags($item['content']); ?></td>
              <td>
                <?php if ($item['status'] == 1): ?>
                  <span class="badge badge-published">已发布</span>
                <?php else: ?>
                  <span class="badge badge-unpublished">已下架</span>
                <?php endif; ?>
              </td>
              <td><?php echo $item['created_at']; ?></td>
              <td>
                <a href="edit.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-primary">编辑</a>
                <a href="javascript:;" onclick="deleteAnnouncement(<?php echo $item['id']; ?>)" class="btn btn-sm btn-danger">删除</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="6" class="empty">暂无公告</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
    
    <!-- 分页导航 -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <!-- 第一页 -->
      <a href="?page=1" class="<?php echo $page == 1 ? 'disabled' : ''; ?>" <?php echo $page == 1 ? 'style="pointer-events: none;"' : ''; ?>>
        <i class="fas fa-angle-double-left"></i>
      </a>
      
      <!-- 上一页 -->
      <a href="?page=<?php echo max(1, $page - 1); ?>" class="<?php echo $page == 1 ? 'disabled' : ''; ?>" <?php echo $page == 1 ? 'style="pointer-events: none;"' : ''; ?>>
        <i class="fas fa-angle-left"></i>
      </a>
      
      <!-- 页码 -->
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
      
      <!-- 下一页 -->
      <a href="?page=<?php echo min($total_pages, $page + 1); ?>" class="<?php echo $page == $total_pages ? 'disabled' : ''; ?>" <?php echo $page == $total_pages ? 'style="pointer-events: none;"' : ''; ?>>
        <i class="fas fa-angle-right"></i>
      </a>
      
      <!-- 最后一页 -->
      <a href="?page=<?php echo $total_pages; ?>" class="<?php echo $page == $total_pages ? 'disabled' : ''; ?>" <?php echo $page == $total_pages ? 'style="pointer-events: none;"' : ''; ?>>
        <i class="fas fa-angle-double-right"></i>
      </a>
    </div>
    <?php endif; ?>
  </div>

  <script src="../res/layui/layui.js"></script>
  <script>
    layui.use(['layer'], function(){
      var layer = layui.layer;
      
      window.deleteAnnouncement = function(id) {
        layer.confirm('确定要删除这条公告吗？', function(index){
          window.location.href = 'delete.php?id=' + id;
          layer.close(index);
        });
      };
    });
  </script>
</body>
</html>
