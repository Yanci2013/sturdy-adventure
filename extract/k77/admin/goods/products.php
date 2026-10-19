<?php
// 开启输出缓冲
ob_start();

require_once __DIR__.'/../../includes/session_manager.php';
require_once __DIR__.'/../../includes/common.php';
require_once __DIR__.'/../../includes/log_helper.php';

if (!is_admin_logged_in()) {
    ob_end_clean();
    header("Location: ../login.php");
    exit();
}

// 处理删除商品请求
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_product'])) {
    $product_id = intval($_POST['product_id']);
    
    try {
        // 创建PDO连接，使用配置文件中的常量
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", 
            DB_USER, 
            DB_PASS
        );
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 先获取商品信息用于日志
        $stmt = $conn->prepare("SELECT name, price FROM products WHERE id = :id");
        $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
        $stmt->execute();
        $product_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 准备删除语句
        $stmt = $conn->prepare("DELETE FROM products WHERE id = :id");
        $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            // 记录管理员操作日志
            $admin_id = $_SESSION['admin_id'] ?? null;
            $admin_name = $_SESSION['admin_username'] ?? '未知管理员';
            $operation_details = "删除商品：ID=" . $product_id . ", 名称=" . ($product_info['name'] ?? '未知') . ", 价格=" . ($product_info['price'] ?? 0) . "元";
            log_admin_operation($pdo, $admin_id, $admin_name, 'delete_product', $operation_details);
            
            $_SESSION['success_msg'] = "商品 #$product_id 已成功删除";
        } else {
            $_SESSION['error_msg'] = "删除商品时出错: " . implode(" ", $stmt->errorInfo());
        }
        
        $conn = null; // 关闭数据库连接
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "删除商品时数据库错误: " . $e->getMessage();
    }
    
    // 刷新页面避免重新提交
    header("Location: products.php");
    exit();
}

try {
    // 创建PDO连接，使用配置文件中的常量
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", 
        DB_USER, 
        DB_PASS
    );
    // 设置PDO错误模式为异常
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}


// 分页参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 6; // 每页显示6个商品
$offset = ($page - 1) * $per_page;

// 构建基础SQL查询
$base_sql = "SELECT p.*, c.name as category_name 
             FROM products p
             LEFT JOIN product_categories c ON p.category_id = c.id
             WHERE p.status = 1";

// 构建计数SQL
$count_sql = "SELECT COUNT(*) as total FROM products p WHERE p.status = 1";

// 获取总商品数
$total_products = 0;
try {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute();
    $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
    $total_products = $count_result['total'];
    $count_stmt->closeCursor();
} catch (PDOException $e) {
    $_SESSION['error_msg'] = "获取商品总数时出错: " . $e->getMessage();
}

// 计算总页数
$total_pages = ceil($total_products / $per_page);

// 构建数据查询SQL
$sql = $base_sql . " ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset";

// 准备和执行查询
$products = [];
try {
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($result as $row) {
        // 解析JSON字段
        $row['images'] = json_decode($row['images'], true);
        $row['input_fields'] = json_decode($row['input_fields'], true);
        $row['game_region_options'] = json_decode($row['game_region_options'], true);
        $products[] = $row;
    }
} catch (PDOException $e) {
    $_SESSION['error_msg'] = "获取商品列表时出错: " . $e->getMessage();
} finally {
    $conn = null; // 关闭数据库连接
}

// 页面标题
$pageTitle = '商品管理';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品列表</title>
    <link href="https://fonts.googleapis.com/css2?family=Ma+Shan+Zheng&family=Noto+Serif+SC:wght@400;500;600;700&family=ZCOOL+XiaoWei&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: #ffffff;
      color: #333;
      line-height: 1.5;
      padding: 20px;
      font-size: 13px;
    }

    h1 {
      font-size: 18px;
      font-weight: 600;
      margin-bottom: 20px;
      color: #000;
    }

    h2 {
      font-size: 14px;
      font-weight: 600;
      margin: 20px 0 10px 0;
      color: #333;
    }

    .btn-group {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      background: #f5f5f5;
      color: #333;
      border: none;
      border-radius: 4px;
      font-size: 12px;
      cursor: pointer;
      text-decoration: none;
    }

    .btn:hover {
      background: #e0e0e0;
    }

    .btn-primary {
      background: #1976d2;
      color: #fff;
    }

    .btn-primary:hover {
      background: #1565c0;
    }

    .alert {
      padding: 10px 12px;
      border-radius: 4px;
      margin-bottom: 10px;
      font-size: 12px;
    }

    .alert-success {
      background: #e8f5e9;
      color: #388e3c;
    }

    .alert-error {
      background: #ffebee;
      color: #d32f2f;
    }

    .card-list {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 12px;
      margin-bottom: 24px;
    }

    .product-card {
      background: #ffffff;
      border: 1px solid #f5f5f5;
      border-radius: 4px;
      overflow: hidden;
    }

    .product-image {
      width: 100%;
      height: 180px;
      object-fit: cover;
    }

    .card-content {
      padding: 16px;
    }

    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
      padding-bottom: 12px;
      border-bottom: 1px solid #f5f5f5;
    }

    .product-id {
      font-size: 14px;
      font-weight: 500;
      color: #666;
    }

    .status-badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 500;
      background: #f5f5f5;
      color: #666;
    }

    .info-row {
      display: flex;
      margin-bottom: 8px;
    }

    .info-label {
      width: 80px;
      font-size: 12px;
      color: #999;
    }

    .info-value {
      flex: 1;
      font-size: 13px;
      color: #666;
    }

    .price-value {
      color: #333;
      font-weight: 500;
    }

    .card-actions {
      display: flex;
      gap: 8px;
      margin-top: 16px;
      padding-top: 16px;
      border-top: 1px solid #f5f5f5;
    }

    .btn-sm {
      flex: 1;
      padding: 6px 12px;
      font-size: 11px;
      justify-content: center;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 12px;
      margin-bottom: 24px;
    }

    th {
      background: #f8f8f8;
      color: #666;
      font-weight: 500;
      padding: 8px 12px;
      text-align: left;
      border-bottom: 1px solid #eee;
    }

    td {
      padding: 8px 12px;
      border-bottom: 1px solid #f5f5f5;
      color: #333;
    }

    tr:hover td {
      background: #fafafa;
    }

    .product-image-thumb {
      width: 40px;
      height: 40px;
      border-radius: 4px;
      object-fit: cover;
    }

    .table-actions {
      display: flex;
      gap: 8px;
    }

    .table-actions .btn {
      padding: 6px 12px;
      font-size: 11px;
    }

    .empty {
      color: #999;
      padding: 20px 0;
      text-align: center;
    }

    .pagination-container {
      margin-top: 24px;
      display: flex;
      justify-content: center;
    }

    .pagination {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 8px;
      list-style: none;
    }

    .page-link {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      border-radius: 4px;
      border: 1px solid #eee;
      background: #ffffff;
      color: #666;
      font-size: 13px;
      text-decoration: none;
    }

    .page-link:hover {
      background: #f9f9f9;
      border-color: #ddd;
      color: #333;
    }

    .page-link.active {
      background: #f8f8f8;
      border-color: #ddd;
      color: #333;
      font-weight: 500;
    }

    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.3);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }

    .modal.show {
      display: flex;
    }

    .modal-content {
      background: #ffffff;
      border-radius: 4px;
      width: 90%;
      max-width: 400px;
      padding: 20px;
    }

    .modal-title {
      font-size: 16px;
      font-weight: 600;
      margin-bottom: 16px;
      color: #333;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .modal-text {
      font-size: 13px;
      color: #666;
      margin-bottom: 20px;
      line-height: 1.6;
    }

    .highlight {
      color: #333;
      font-weight: 500;
    }

    .modal-actions {
      display: flex;
      justify-content: flex-end;
      gap: 8px;
    }

    @media (min-width: 992px) {
      .card-list {
        display: none;
      }
    }

    @media (max-width: 991px) {
      table {
        display: none;
      }
    }

    @media screen and (max-width: 768px) {
      body {
        padding: 16px;
      }

      .btn-group {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
      }

      .btn {
        width: 100%;
        justify-content: center;
      }

      .card-actions {
        flex-direction: column;
      }

      .page-link {
        width: 36px;
        height: 36px;
        font-size: 12px;
      }
    }
    </style>
  <link href="../res/admin-white.css" rel="stylesheet">
</head>
<body>
  <h1>商品列表</h1>

  <div class="btn-group">
    <a href="create_product.php" class="btn btn-primary">
      <i class="fas fa-plus"></i> 新增商品
    </a>
  </div>
  
  <?php if (isset($_SESSION['success_msg'])): ?>
  <div class="alert alert-success">
    <i class="fas fa-check-circle"></i>
    <?php echo $_SESSION['success_msg']; ?>
  </div>
  <?php unset($_SESSION['success_msg']); ?>
  <?php endif; ?>
  
  <?php if (isset($_SESSION['error_msg'])): ?>
  <div class="alert alert-error">
    <i class="fas fa-exclamation-circle"></i>
    <?php echo $_SESSION['error_msg']; ?>
  </div>
  <?php unset($_SESSION['error_msg']); ?>
  <?php endif; ?>
  
  <!-- 移动端卡片视图 -->
  <div class="card-list">
    <?php if (empty($products)): ?>
      <div class="empty">
        <div>🎮</div>
        <div>没有找到商品记录</div>
        <div style="font-size: 13px;">当前没有上架的商品，请添加新商品</div>
      </div>
        <?php else: ?>
            <?php foreach ($products as $product): ?>
            <div class="product-card">
                <?php if (!empty($product['images'][0])): ?>
                <img src="../../<?php echo htmlspecialchars($product['images'][0]); ?>" class="product-image">
                <?php endif; ?>
                
                <div class="card-content">
                    <div class="card-header">
                        <div class="product-id">#<?php echo $product['id']; ?></div>
                        <div class="status-badge status-active">已上架</div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">商品名称</div>
                        <div class="info-value"><?php echo htmlspecialchars($product['name']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">中文名称</div>
                        <div class="info-value"><?php echo htmlspecialchars($product['chinese_name'] ?? '未设置'); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">分类</div>
                        <div class="info-value"><?php echo htmlspecialchars($product['category_name'] ?? '未分类'); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">价格</div>
                        <div class="info-value price-value">¥<?php echo number_format($product['price'], 2); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">库存</div>
                        <div class="info-value"><?php echo $product['stock']; ?> <?php echo htmlspecialchars($product['unit']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">创建时间</div>
                        <div class="info-value"><?php echo date('Y-m-d H:i', strtotime($product['created_at'])); ?></div>
                    </div>
                    
                    <div class="card-actions">
                        <a href="product_edit.php?id=<?php echo $product['id']; ?>" class="btn btn-sm">
                            <i class="fas fa-eye"></i> 查看
                        </a>
                        
                        <a href="product_edit.php?id=<?php echo $product['id']; ?>" class="btn btn-sm">
                            <i class="fas fa-edit"></i> 编辑
                        </a>
                        
                        <button type="button" class="btn btn-sm" 
                                onclick="confirmDelete(<?php echo $product['id']; ?>)">
                            <i class="fas fa-trash-alt"></i> 删除
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
  <!-- 桌面端表格视图 -->
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>商品图片</th>
        <th>商品名称</th>
        <th>分类</th>
        <th>价格</th>
        <th>库存</th>
        <th>状态</th>
        <th>创建时间</th>
        <th>操作</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($products)): ?>
        <tr>
          <td colspan="9">
            <div class="empty">
              <div>🎮</div>
              <div>没有找到商品记录</div>
              <div style="font-size: 13px;">当前没有上架的商品，请添加新商品</div>
            </div>
          </td>
        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?php echo $product['id']; ?></td>
                            <td>
                                <?php if (!empty($product['images'][0])): ?>
                                <img src="../../<?php echo htmlspecialchars($product['images'][0]); ?>" class="product-image-thumb">
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($product['name']); ?></div>
                                <div style="font-size: 12px; color: #999;">
                                    <?php echo htmlspecialchars($product['chinese_name'] ?? ''); ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($product['category_name'] ?? '未分类'); ?></td>
                            <td class="price-value">¥<?php echo number_format($product['price'], 2); ?></td>
                            <td><?php echo $product['stock']; ?> <?php echo htmlspecialchars($product['unit']); ?></td>
                            <td>
                                <span class="status-badge status-active">已上架</span>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($product['created_at'])); ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="product_edit.php?id=<?php echo $product['id']; ?>" class="btn" title="编辑">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <button type="button" class="btn" title="删除"
                                            onclick="confirmDelete(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
    </tbody>
  </table>
  
  <!-- 分页导航 -->
  <?php if ($total_pages > 1): ?>
  <div class="pagination-container">
    <ul class="pagination">
      <li>
        <a class="page-link" href="products.php?page=1" title="第一页">
          <i class="fas fa-angle-double-left"></i>
        </a>
      </li>
      
      <li>
        <a class="page-link" href="products.php?page=<?php echo max(1, $page - 1); ?>" title="上一页">
          <i class="fas fa-angle-left"></i>
        </a>
      </li>
      
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
        <li>
          <a class="page-link <?php echo $i == $page ? 'active' : ''; ?>" 
             href="products.php?page=<?php echo $i; ?>">
            <?php echo $i; ?>
          </a>
        </li>
      <?php endfor; ?>
      
      <li>
        <a class="page-link" href="products.php?page=<?php echo min($total_pages, $page + 1); ?>" title="下一页">
          <i class="fas fa-angle-right"></i>
        </a>
      </li>
      
      <li>
        <a class="page-link" href="products.php?page=<?php echo $total_pages; ?>" title="最后一页">
          <i class="fas fa-angle-double-right"></i>
        </a>
      </li>
    </ul>
  </div>
  <?php endif; ?>

<!-- 删除确认模态框 -->
<div class="modal" id="deleteModal">
    <div class="modal-content">
        <div class="modal-title">
            <i class="fas fa-exclamation-triangle"></i>
            确认删除商品
        </div>
        
        <div class="modal-text">
            您确定要永久删除商品 <span class="highlight" id="deleteProductId"></span> 吗？
            <br><br>
            此操作<span class="highlight">无法撤销</span>，所有相关数据将被永久移除。
        </div>
        
        <div class="modal-actions">
            <form method="POST" id="deleteForm">
                <input type="hidden" name="product_id" id="deleteProductIdInput">
                <input type="hidden" name="delete_product" value="1">
                <button type="button" class="btn" onclick="closeDeleteModal()">取消</button>
                <button type="submit" class="btn btn-primary">确认删除</button>
            </form>
        </div>
    </div>
</div>

<script>
    // 显示删除确认模态框
    function confirmDelete(productId) {
        document.getElementById('deleteProductId').textContent = '#' + productId;
        document.getElementById('deleteProductIdInput').value = productId;
        document.getElementById('deleteModal').classList.add('show');
    }
    
    // 关闭删除模态框
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('show');
    }
    
    // 点击模态框外部关闭
    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteModal();
        }
    });
    
    // 监听删除表单提交
    document.getElementById('deleteForm').addEventListener('submit', function() {
        closeDeleteModal();
    });
</script>
</body>
</html>