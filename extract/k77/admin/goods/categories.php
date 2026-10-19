<?php
ob_start();
require_once __DIR__.'/../../includes/session_manager.php';
require_once __DIR__.'/../../includes/common.php';
require_once __DIR__.'/../../includes/log_helper.php';

if (!is_admin_logged_in()) {
    ob_end_clean();
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_category'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['error_msg'] = "安全验证失败，请重试";
            header("Location: categories.php");
            exit();
        }
        
        $category_id = intval($_POST['category_id']);
        
        $check_exist_sql = "SELECT id, name FROM product_categories WHERE id = ?";
        $check_exist_stmt = $pdo->prepare($check_exist_sql);
        $check_exist_stmt->execute([$category_id]);
        $category = $check_exist_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$category) {
            $_SESSION['error_msg'] = "分类不存在或已被删除";
            header("Location: categories.php");
            exit();
        }
        
        $category_name = htmlspecialchars($category['name']);
        
        $check_sql = "SELECT COUNT(*) as product_count FROM products WHERE category_id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$category_id]);
        $product_count = $check_stmt->fetchColumn();
        
        if ($product_count > 0) {
            $_SESSION['error_msg'] = "无法删除分类 '{$category_name}'，该分类下还有 {$product_count} 个商品存在";
        } else {
            $check_child_sql = "SELECT COUNT(*) as child_count FROM product_categories WHERE parent_id = ?";
            $check_child_stmt = $pdo->prepare($check_child_sql);
            $check_child_stmt->execute([$category_id]);
            $child_count = $check_child_stmt->fetchColumn();
            
            if ($child_count > 0) {
                $_SESSION['error_msg'] = "无法删除分类 '{$category_name}'，该分类下还有 {$child_count} 个子分类存在";
            } else {
                $pdo->beginTransaction();
                
                try {
                    $delete_sql = "DELETE FROM product_categories WHERE id = ?";
                    $delete_stmt = $pdo->prepare($delete_sql);
                    
                    if ($delete_stmt->execute([$category_id])) {
                        // 记录管理员操作日志
                        $admin_id = $_SESSION['admin_id'] ?? null;
                        $admin_name = $_SESSION['admin_username'] ?? '未知管理员';
                        $operation_details = "删除分类：ID=" . $category_id . ", 名称=" . $category_name;
                        log_admin_operation($pdo, $admin_id, $admin_name, 'delete_category', $operation_details);
                        
                        $pdo->commit();
                        $_SESSION['success_msg'] = "分类 '{$category_name}' 已成功删除";
                    } else {
                        $pdo->rollBack();
                        $_SESSION['error_msg'] = "删除分类时出错";
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $_SESSION['error_msg'] = "删除失败: " . $e->getMessage();
                }
            }
        }
        
        header("Location: categories.php");
        exit();
    }

    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;

    $sql_parents = "SELECT pc.*, 
                    (SELECT COUNT(*) FROM products p WHERE p.category_id = pc.id) as product_count,
                    (SELECT COUNT(*) FROM product_categories c2 WHERE c2.parent_id = pc.id) as child_count
                    FROM product_categories pc
                    WHERE pc.parent_id IS NULL OR pc.parent_id = 0
                    ORDER BY pc.sort_order ASC, pc.created_at DESC";
    
    $stmt_parents = $pdo->prepare($sql_parents);
    $stmt_parents->execute();
    $all_parents = $stmt_parents->fetchAll(PDO::FETCH_ASSOC);
    
    $sql_children = "SELECT pc.*, 
                     (SELECT COUNT(*) FROM products p WHERE p.category_id = pc.id) as product_count
                     FROM product_categories pc
                     WHERE pc.parent_id IS NOT NULL AND pc.parent_id != 0
                     ORDER BY pc.parent_id, pc.sort_order ASC, pc.created_at DESC";
    
    $stmt_children = $pdo->prepare($sql_children);
    $stmt_children->execute();
    $all_children = $stmt_children->fetchAll(PDO::FETCH_ASSOC);
    
    $children_by_parent = [];
    foreach ($all_children as $child) {
        $parent_id = $child['parent_id'];
        if (!isset($children_by_parent[$parent_id])) {
            $children_by_parent[$parent_id] = [];
        }
        $children_by_parent[$parent_id][] = $child;
    }
    
    $total_categories = count($all_parents);
    $total_pages = ceil($total_categories / $per_page);
    
    $current_parents = array_slice($all_parents, $offset, $per_page);
    
    $categories = [];
    foreach ($current_parents as $parent) {
        $parent_id = $parent['id'];
        
        if (isset($children_by_parent[$parent_id])) {
            $parent['children'] = $children_by_parent[$parent_id];
        } else {
            $parent['children'] = [];
        }
        
        $categories[] = $parent;
    }

} catch (PDOException $e) {
    $error = "数据库错误: " . $e->getMessage();
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <title>商品分类管理 - 后台管理系统</title>
  <meta name="renderer" content="webkit">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <link href="https://fonts.googleapis.com/css2?family=Ma+Shan+Zheng&family=Noto+Serif+SC:wght@400;500;600;700&family=ZCOOL+XiaoWei&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 12px;
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

    .parent-row td {
      background: #f8f8f8;
      font-weight: 500;
    }

    .child-row td:first-child {
      padding-left: 40px !important;
    }

    .child-name {
      padding-left: 20px;
    }

    .empty {
      color: #999;
      padding: 20px 0;
      text-align: center;
    }

    .category-logo {
      width: 40px;
      height: 40px;
      object-fit: cover;
      border-radius: 4px;
      cursor: pointer;
      transition: opacity 0.2s ease;
    }

    .category-logo:hover {
      opacity: 0.8;
    }

    .logo-placeholder {
      width: 40px;
      height: 40px;
      background: #f5f5f5;
      border-radius: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .logo-placeholder i {
      color: #cccccc;
      font-size: 18px;
    }

    .category-item {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .category-item strong {
      color: #333;
      font-weight: 500;
    }

    .product-count {
      font-size: 11px;
      color: #999;
      padding: 4px 8px;
      border-radius: 12px;
      background: #f5f5f5;
    }

    .badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 500;
      background: #f5f5f5;
      color: #666;
    }

    .parent-icon {
      color: #666;
      font-size: 16px;
    }

    .child-icon {
      color: #999;
      font-size: 14px;
    }

    .btn-container {
      display: flex;
      gap: 8px;
    }

    .btn {
      display: inline-block;
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

    .btn-xs {
      padding: 6px 12px;
      font-size: 11px;
    }

    .btn.disabled {
      opacity: 0.5;
      cursor: not-allowed;
      pointer-events: none;
    }

    .delete-form {
      display: inline-block;
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
      transition: all 0.3s ease;
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

    .alert {
      padding: 10px 12px;
      border-radius: 4px;
      margin-bottom: 10px;
      font-size: 12px;
    }

    .alert-danger {
      background: #ffebee;
      color: #d32f2f;
    }

    .alert-success {
      background: #e8f5e9;
      color: #388e3c;
    }

    .btn-primary {
      background: #1976d2;
      color: #fff;
    }

    .btn-primary:hover {
      background: #1565c0;
    }
  </style>
  <link href="../res/admin-white.css" rel="stylesheet">
</head>
<body>
  <h1>商品分类管理</h1>

  <div class="btn-group">
    <a href="create_category.php" class="btn btn-primary">
      <i class="fas fa-plus"></i> 添加分类
    </a>
  </div>

    <?php if (isset($_SESSION['error_msg'])): ?>
    <div class="alert alert-danger">
      <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error_msg']); ?>
    </div>
    <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success_msg'])): ?>
    <div class="alert alert-success">
      <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success_msg']); ?>
    </div>
    <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>
    
  <?php if (isset($error)): ?>
  <div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
  </div>
  <?php endif; ?>
  
  <table>
    <thead>
      <tr>
        <th width="40"></th>
        <th width="60">ID</th>
        <th width="80">LOGO</th>
        <th>分类名称</th>
        <th width="100">分类级别</th>
        <th width="100">状态</th>
        <th width="100">秒杀分类</th>
        <th width="80">排序</th>
        <th width="150">创建时间</th>
        <th width="180">操作</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($categories)): ?>
        <tr>
          <td colspan="10">
            <div class="empty">暂无分类数据</div>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($categories as $category): ?>
          <tr class="parent-row">
            <td>
                    <i class="fas fa-folder parent-icon"></i>
                  </td>
                  <td><?php echo $category['id']; ?></td>
                  <td>
                    <?php if (!empty($category['logo'])): ?>
                      <img src="../../<?php echo htmlspecialchars($category['logo']); ?>" 
                           class="category-logo"
                           onclick="window.open('../../<?php echo htmlspecialchars($category['logo']); ?>', '_blank')">
                    <?php else: ?>
                      <div class="logo-placeholder">
                        <i class="fas fa-image"></i>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="category-item">
                      <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                      <?php if ($category['product_count'] > 0): ?>
                        <span class="product-count"><?php echo $category['product_count']; ?>件商品</span>
                      <?php endif; ?>
                      <?php if ($category['child_count'] > 0): ?>
                        <span class="product-count"><?php echo $category['child_count']; ?>个子分类</span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td>一级分类</td>
                  <td>
                    <span class="badge">
                      <?php echo $category['status'] ? '启用' : '禁用'; ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($category['is_flash_sale']): ?>
                      <span class="badge">秒杀分类</span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo $category['sort_order']; ?></td>
                  <td><?php echo date('Y-m-d H:i', strtotime($category['created_at'])); ?></td>
                  <td>
                    <div class="btn-container">
                      <a href="category_edit.php?id=<?php echo $category['id']; ?>" 
                         class="btn btn-xs">
                        <i class="fas fa-edit"></i> 编辑
                      </a>
                      <?php if ($category['child_count'] > 0 || $category['product_count'] > 0): ?>
                        <button class="btn btn-xs disabled" 
                                onclick="showCannotDeleteMessage(<?php echo $category['id']; ?>, <?php echo $category['child_count']; ?>, <?php echo $category['product_count']; ?>)">
                          <i class="fas fa-trash"></i> 删除
                        </button>
                      <?php else: ?>
                        <form method="POST" action="categories.php" class="delete-form" onsubmit="return confirmDelete(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')">
                          <input type="hidden" name="delete_category" value="1">
                          <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                          <button type="submit" class="btn btn-xs">
                            <i class="fas fa-trash"></i> 删除
                          </button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
                
                <?php if (!empty($category['children'])): ?>
                  <?php foreach ($category['children'] as $child): ?>
                    <tr class="child-row">
                      <td><i class="fas fa-folder-open child-icon"></i></td>
                      <td><?php echo $child['id']; ?></td>
                      <td>
                        <?php if (!empty($child['logo'])): ?>
                          <img src="../../<?php echo htmlspecialchars($child['logo']); ?>" 
                               class="category-logo"
                               onclick="window.open('../../<?php echo htmlspecialchars($child['logo']); ?>', '_blank')">
                        <?php else: ?>
                          <div class="logo-placeholder">
                            <i class="fas fa-image"></i>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td class="child-name">
                        <div class="category-item">
                          <?php echo htmlspecialchars($child['name']); ?>
                          <?php if ($child['product_count'] > 0): ?>
                            <span class="product-count"><?php echo $child['product_count']; ?>件商品</span>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td>二级分类</td>
                      <td>
                        <span class="badge">
                          <?php echo $child['status'] ? '启用' : '禁用'; ?>
                        </span>
                      </td>
                      <td>
                        <?php if ($child['is_flash_sale']): ?>
                          <span class="badge">秒杀分类</span>
                        <?php endif; ?>
                      </td>
                      <td><?php echo $child['sort_order']; ?></td>
                      <td><?php echo date('Y-m-d H:i', strtotime($child['created_at'])); ?></td>
                      <td>
                        <div class="btn-container">
                          <a href="category_edit.php?id=<?php echo $child['id']; ?>" 
                             class="btn btn-xs">
                            <i class="fas fa-edit"></i> 编辑
                          </a>
                          <?php if ($child['product_count'] > 0): ?>
                            <button class="btn btn-xs disabled" 
                                    onclick="showCannotDeleteMessage(<?php echo $child['id']; ?>, 0, <?php echo $child['product_count']; ?>)">
                              <i class="fas fa-trash"></i> 删除
                            </button>
                          <?php else: ?>
                            <form method="POST" action="categories.php" class="delete-form" onsubmit="return confirmDelete(<?php echo $child['id']; ?>, '<?php echo htmlspecialchars($child['name']); ?>')">
                              <input type="hidden" name="delete_category" value="1">
                              <input type="hidden" name="category_id" value="<?php echo $child['id']; ?>">
                              <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                              <button type="submit" class="btn btn-xs">
                                <i class="fas fa-trash"></i> 删除
                              </button>
                            </form>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              <?php endforeach; ?>
            <?php endif; ?>
    </tbody>
  </table>
  
  <?php if ($total_pages > 1): ?>
  <div class="pagination-container">
    <ul class="pagination">
      <li>
        <a class="page-link" href="categories.php?page=1">
          <i class="fas fa-angle-double-left"></i>
        </a>
      </li>
      
      <li>
        <a class="page-link" href="categories.php?page=<?php echo max(1, $page - 1); ?>">
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
             href="categories.php?page=<?php echo $i; ?>">
            <?php echo $i; ?>
          </a>
        </li>
      <?php endfor; ?>
      
      <li>
        <a class="page-link" href="categories.php?page=<?php echo min($total_pages, $page + 1); ?>">
          <i class="fas fa-angle-right"></i>
        </a>
      </li>
      
      <li>
        <a class="page-link" href="categories.php?page=<?php echo $total_pages; ?>">
          <i class="fas fa-angle-double-right"></i>
        </a>
      </li>
    </ul>
  </div>
  <?php endif; ?>

  <script src="../res/layui/layui.js"></script>
  <script>
  layui.config({
      base: '../res/'
  }).use(['layer'], function(){
      var $ = layui.$;
      var layer = layui.layer;
      
      $('.delete-btn.disabled').on('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
      });
  });

  function showCannotDeleteMessage(categoryId, childCount, productCount) {
      var message = '';
      if (childCount > 0 && productCount > 0) {
          message = '该分类下有 ' + childCount + ' 个子分类和 ' + productCount + ' 个商品，无法删除。请先删除子分类和商品。';
      } else if (childCount > 0) {
          message = '该分类下有 ' + childCount + ' 个子分类，无法删除。请先删除所有子分类。';
      } else if (productCount > 0) {
          message = '该分类下有 ' + productCount + ' 个商品，无法删除。请先删除或转移这些商品。';
      }
      
      layui.layer.alert(message, {
          icon: 2,
          title: '无法删除分类 #' + categoryId,
          btn: ['确定']
      });
  }

  function confirmDelete(categoryId, categoryName) {
      return confirm('确定要删除分类 "' + categoryName + '" 吗？\n\n此操作不可恢复！');
  }
  </script>
</body>
</html>
