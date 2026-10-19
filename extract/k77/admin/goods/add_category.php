<?php
// 开启输出缓冲
ob_start();

// 引入数据库连接文件
require_once __DIR__.'/../../includes/common.php';

// 开启会话
session_start();

// 初始化变量
$error = '';
$success = '';
$category_name = '';
$category_description = '';

try {
    // 处理表单提交
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $category_name = trim($_POST['category_name']);
        $category_description = trim($_POST['category_description']);
        
        // 验证数据
        if (empty($category_name)) {
            $error = "请填写分类名称";
        } else {
            // 检查分类名称是否已存在
            $check_query = "SELECT id FROM order_categories WHERE name = ?";
            $stmt = $pdo->prepare($check_query);
            $stmt->execute([$category_name]);
            
            if ($stmt->rowCount() > 0) {
                $error = "该分类名称已存在，请使用其他名称";
            } else {
                // 添加新分类
                $sql = "INSERT INTO order_categories (name, description) VALUES (?, ?)";
                $stmt = $pdo->prepare($sql);
                
                if ($stmt->execute([$category_name, $category_description])) {
                    $success = "分类添加成功！";
                    // 清空表单数据
                    $category_name = "";
                    $category_description = "";
                } else {
                    $error = "分类添加失败";
                }
            }
        }
    }
} catch (PDOException $e) {
    $error = "数据库错误: " . $e->getMessage();
}
?>

<div class="dashboard-main-body">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <h6 class="fw-semibold mb-0">订单分类管理</h6>
        <ul class="d-flex align-items-center gap-2">
            <li class="fw-medium">
                <a href="index.php" class="d-flex align-items-center gap-1 hover-text-primary">
                    <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                    首页
                </a>
            </li>
            <li>-</li>
            <li class="fw-medium">订单分类管理</li>
        </ul>
    </div>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-24">
            <iconify-icon icon="solar:danger-circle-bold" class="text-danger me-2"></iconify-icon>
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show mb-24">
            <iconify-icon icon="solar:check-circle-bold" class="text-success me-2"></iconify-icon>
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <iconify-icon icon="solar:tag-outline" class="text-primary-light me-2"></iconify-icon>
                        添加新分类
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" action="add_category.php">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="category_name" class="form-label">
                                    <iconify-icon icon="solar:tag-horizontal-outline" class="me-2"></iconify-icon>
                                    分类名称
                                </label>
                                <input type="text" class="form-control" id="category_name" 
                                       name="category_name" value="<?php echo htmlspecialchars($category_name); ?>" required
                                       placeholder="例如：和平精英Q区">
                                <div class="form-text mt-1">
                                    用于标识订单分类的名称，将在订单列表中显示
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="category_description" class="form-label">
                                    <iconify-icon icon="solar:document-text-outline" class="me-2"></iconify-icon>
                                    分类描述
                                </label>
                                <textarea class="form-control" id="category_description" 
                                          name="category_description" rows="3"><?php echo htmlspecialchars($category_description); ?></textarea>
                                <div class="form-text mt-1">
                                    对分类的详细描述，可用于说明分类的具体范围和内容
                                </div>
                            </div>
                            
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <iconify-icon icon="solar:check-circle-outline" class="me-2"></iconify-icon>
                                    保存分类
                                </button>
                                <a href="categories.php" class="btn btn-outline-secondary ms-3">
                                    <iconify-icon icon="solar:list-check-outline" class="me-2"></iconify-icon>
                                    查看分类列表
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <iconify-icon icon="solar:list-check-outline" class="text-primary-light me-2"></iconify-icon>
                        现有分类列表
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                    <?php
                        try {
                            // 分页参数
                            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                            $per_page = 12;
                            $offset = ($page - 1) * $per_page;
                            
                            // 获取总记录数
                            $count_query = "SELECT COUNT(*) as total FROM order_categories";
                            $count_stmt = $pdo->query($count_query);
                            $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
                            $total_pages = ceil($total_records / $per_page);
                            
                            // 查询现有分类
                            $categories_query = "SELECT * FROM order_categories ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
                            $stmt = $pdo->query($categories_query);
                            
                            if ($stmt->rowCount() > 0) {
                                while ($category = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo '<div class="col-md-6 mb-4">';
                                    echo '<div class="card h-100 hover-shadow">';
                                    echo '<div class="card-body">';
                                    echo '<div class="d-flex align-items-center gap-3 mb-3">';
                                    echo '<div class="w-40-px h-40-px bg-primary-50 text-primary-light rounded-circle d-flex justify-content-center align-items-center">';
                                    echo '<iconify-icon icon="solar:tag-outline" class="text-xl"></iconify-icon>';
                                    echo '</div>';
                                    echo '<h6 class="mb-0">' . htmlspecialchars($category['name']) . '</h6>';
                                    echo '</div>';
                                    
                                    echo '<p class="text-secondary-light mb-3">' . 
                                         ($category['description'] ? htmlspecialchars($category['description']) : '<span class="text-muted">未设置描述</span>') . 
                                         '</p>';
                                    
                                    echo '<div class="d-flex justify-content-between align-items-center">';
                                    echo '<span class="badge bg-neutral-100 text-secondary-light">';
                                    echo '<iconify-icon icon="solar:clock-circle-outline" class="me-1"></iconify-icon>';
                                    echo date('Y-m-d H:i', strtotime($category['created_at']));
                                    echo '</span>';
                                    
                                    echo '<span class="badge bg-neutral-100 text-secondary-light">';
                                    echo '<iconify-icon icon="solar:id-number-outline" class="me-1"></iconify-icon>';
                                    echo 'ID: ' . $category['id'];
                                    echo '</span>';
                                    echo '</div>';
                                    echo '</div>';
                                    echo '</div>';
                                    echo '</div>';
                                }
                            } else {
                                echo '<div class="col-12 text-center py-5">';
                                echo '<iconify-icon icon="solar:box-outline" class="text-secondary-light" style="font-size: 64px;"></iconify-icon>';
                                echo '<h5 class="mb-3 mt-3">暂无分类数据</h5>';
                                echo '<p class="text-secondary-light">请点击上方按钮添加新分类</p>';
                                echo '</div>';
                            }
                        } catch (PDOException $e) {
                            echo '<div class="col-12 text-center py-5">';
                            echo '<iconify-icon icon="solar:warning-circle-outline" class="text-danger" style="font-size: 64px;"></iconify-icon>';
                            echo '<h5 class="mb-3 mt-3">加载分类数据失败</h5>';
                            echo '<p class="text-secondary-light">' . htmlspecialchars($e->getMessage()) . '</p>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                    
                    <!-- 分页导航 -->
                    <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <!-- 第一页 -->
                        <a href="?page=1" class="btn btn-sm btn-outline-primary <?php echo $page == 1 ? 'disabled' : ''; ?>">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        
                        <!-- 上一页 -->
                        <a href="?page=<?php echo max(1, $page - 1); ?>" class="btn btn-sm btn-outline-primary <?php echo $page == 1 ? 'disabled' : ''; ?>">
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
                            <a href="?page=<?php echo $i; ?>" class="btn btn-sm <?php echo $i == $page ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <!-- 下一页 -->
                        <a href="?page=<?php echo min($total_pages, $page + 1); ?>" class="btn btn-sm btn-outline-primary <?php echo $page == $total_pages ? 'disabled' : ''; ?>">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        
                        <!-- 最后一页 -->
                        <a href="?page=<?php echo $total_pages; ?>" class="btn btn-sm btn-outline-primary <?php echo $page == $total_pages ? 'disabled' : ''; ?>">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>