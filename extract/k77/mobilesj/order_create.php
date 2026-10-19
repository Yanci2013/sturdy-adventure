<?php
$siteTitle = '发布订单';
require_once 'header.php';

// 定义订单状态常量
define('ORDER_STATUS_PENDING', 1); // 待接单
define('ORDER_STATUS_ACCEPTED', 2); // 已接单
define('ORDER_STATUS_IN_SERVICE', 3); // 服务中
define('ORDER_STATUS_COMPLETED', 4); // 已完成

// 检查商家是否登录
if (!isset($_SESSION['partner_id']) || empty($_SESSION['partner_id'])) {
    header("Location: partner_login.php");
    exit;
}

// 获取当前商家ID
$partner_id = $_SESSION['partner_id'];

// 获取商家常用说明
try {
    $notesStmt = $pdo->prepare("SELECT common_notes FROM partners WHERE id = ?");
    $notesStmt->execute([$partner_id]);
    $notesData = $notesStmt->fetch(PDO::FETCH_ASSOC);
    $commonNotes = $notesData ? json_decode($notesData['common_notes'], true) : [];
    
    // 确保数据结构正确
    if (!isset($commonNotes['notes']) || !is_array($commonNotes['notes'])) {
        $commonNotes = ['notes' => []];
    }
} catch (PDOException $e) {
    $commonNotes = ['notes' => []];
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 获取并清理输入数据
        $product_name = trim($_POST['product_name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $category_id = intval($_POST['category_id'] ?? 0);
        $subcategory_id = intval($_POST['subcategory_id'] ?? 0);
        $game_region = trim($_POST['game_region'] ?? '');
        $digital_id = trim($_POST['digital_id'] ?? '');
        $chinese_id = trim($_POST['chinese_id'] ?? '');
        $notes = trim($_POST['notes'] ?? ''); 
        
        // 验证必填字段
        if (empty($product_name)) {
            throw new Exception("商品名称不能为空");
        }
        if ($price <= 0) {
            throw new Exception("商品价格必须大于0");
        }
        if ($category_id <= 0) {
            throw new Exception("请选择有效的商品分类");
        }
        if (empty($game_region)) {
            throw new Exception("请选择游戏大区");
        }
        if (empty($digital_id)) {
            throw new Exception("数字ID不能为空");
        }
        if (empty($chinese_id)) {
            throw new Exception("中文ID不能为空");
        }
        
        // 检查商家余额
        $balanceStmt = $pdo->prepare("SELECT balance FROM partners WHERE id = ?");
        $balanceStmt->execute([$partner_id]);
        $partnerBalance = $balanceStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$partnerBalance || $partnerBalance['balance'] < $price) {
            throw new Exception("账户余额不足，当前余额: ¥" . ($partnerBalance['balance'] ?? '0.00'));
        }
        
        // 获取分类名称 - 修复查询条件
        $categoryStmt = $pdo->prepare("SELECT 
            c1.id as parent_id, c1.name as parent_name,
            c2.id as child_id, c2.name as child_name
            FROM product_categories c1
            LEFT JOIN product_categories c2 ON c2.parent_id = c1.id
            WHERE (c1.id = ?) AND c1.status = 1");
        $categoryStmt->execute([$category_id]);
        $categoryData = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!$categoryData) {
            throw new Exception("无效的商品分类");
        }
        
        // 确定分类信息
        $categoryName = '';
        $subcategoryName = '';
        foreach ($categoryData as $row) {
            if ($row['parent_id'] == $category_id) {
                $categoryName = $row['parent_name'];
            }
            // 修复：获取子分类名称
            if ($row['child_id'] == $subcategory_id) {
                $subcategoryName = $row['child_name'];
            }
        }
        
        // 如果没有选择二级分类，则使用一级分类作为主分类
        if ($subcategory_id <= 0) {
            $subcategory_id = null;
            $subcategoryName = null;
            $main_category_id = $category_id;
            $main_category_name = $categoryName;
        } else {
            $main_category_id = $category_id;
            $main_category_name = $categoryName;
        }
        
        // 获取商家名称
        $partnerStmt = $pdo->prepare("SELECT name FROM partners WHERE id = ?");
        $partnerStmt->execute([$partner_id]);
        $partner = $partnerStmt->fetch(PDO::FETCH_ASSOC);
        $partner_name = $partner['name'] ?? '未知商家';
        
        // 自动生成订单号
        $order_no = date('YmdHis') . rand(100, 999);
        
        // 准备输入字段数据
        $input_fields = json_encode([
            '数字ID' => $digital_id,
            '中文ID' => $chinese_id,
            '备注' => $notes
        ], JSON_UNESCAPED_UNICODE);
        
        // 设置订单参数
        $hunter_option = 'system';
        $hunter_id = 0;
        $hunter_user_id = 0;
        $user_id = 0;
        $payment_type = 'alipay';
        $payment_status = 'paid';
        $current_time = date('Y-m-d H:i:s');
        $order_status = ORDER_STATUS_PENDING;
        $trade_no = $order_no;
        $payment_amount = $price;

        // 开始事务处理
        $pdo->beginTransaction();

        try {
            // 扣除商家余额
            $updateBalanceStmt = $pdo->prepare("UPDATE partners SET balance = balance - ? WHERE id = ?");
            $updateBalanceStmt->execute([$price, $partner_id]);
            
            if ($updateBalanceStmt->rowCount() === 0) {
                throw new Exception("扣除余额失败");
            }
            
            // 插入订单
            $sql = "INSERT INTO orders (
                order_no, product_id, category_id, category_name, product_name, price,
                user_id, game_region, hunter_option, hunter_id, hunter_user_id,
                input_fields, digital_id, chinese_id,
                create_time, update_time, payment_time,
                fadan_id, fadan_name, order_status,
                payment_type, payment_status, trade_no, payment_amount,
                subcategory_id, subcategory_name
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $order_no,                 // 1. string
                $main_category_id,         // 2. int (product_id)
                $main_category_id,         // 3. int (category_id)
                $main_category_name,       // 4. string
                $product_name,             // 5. string
                $price,                    // 6. double
                $user_id,                  // 7. int
                $game_region,              // 8. string
                $hunter_option,            // 9. string
                $hunter_id,                // 10. int
                $hunter_user_id,           // 11. int
                $input_fields,             // 12. string
                $digital_id,               // 13. string
                $chinese_id,               // 14. string
                $current_time,             // 15. string (create_time)
                $current_time,             // 16. string (update_time)
                $current_time,             // 17. string (payment_time)
                $partner_id,                // 18. string (fadan_id) - partners.id是varchar类型
                $partner_name,             // 19. string (fadan_name)
                $order_status,             // 20. int
                $payment_type,             // 21. string
                $payment_status,           // 22. string
                $trade_no,                 // 23. string
                $payment_amount,           // 24. double
                $subcategory_id,           // 25. int (subcategory_id)
                $subcategoryName           // 26. string (subcategory_name)
            ]);
            
            $pdo->commit();
            $success = "订单发布成功！订单号: " . htmlspecialchars($order_no) . "，已扣除 ¥" . $price;
            $_POST = array();
        } catch (Exception $e) {
            $pdo->rollback();
            throw $e;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// 查询一级分类列表 - 修复查询条件
$parentCategoriesStmt = $pdo->query("SELECT id, name FROM product_categories WHERE (parent_id IS NULL OR parent_id = 0) AND status = 1 ORDER BY sort_order, name");
$parentCategories = $parentCategoriesStmt->fetchAll(PDO::FETCH_ASSOC);

// 查询所有子分类列表 - 修复查询条件
$childCategoriesStmt = $pdo->query("SELECT id, name, parent_id FROM product_categories WHERE parent_id IS NOT NULL AND parent_id != 0 AND status = 1 ORDER BY sort_order, name");
$childCategories = $childCategoriesStmt->fetchAll(PDO::FETCH_ASSOC);

// 组织子分类数据，按父分类ID分组
$childCategoriesByParent = [];
foreach ($childCategories as $category) {
    $parentId = $category['parent_id'];
    if (!isset($childCategoriesByParent[$parentId])) {
        $childCategoriesByParent[$parentId] = [];
    }
    $childCategoriesByParent[$parentId][] = $category;
}

// 获取当前商家余额用于显示
$balanceStmt = $pdo->prepare("SELECT balance FROM partners WHERE id = ?");
$balanceStmt->execute([$partner_id]);
$balanceRow = $balanceStmt->fetch(PDO::FETCH_ASSOC);
$currentBalance = $balanceRow ? $balanceRow['balance'] : 0.00;

// 获取游戏大区列表
$gameRegionsStmt = $pdo->query("SELECT id, name FROM game_regions WHERE status = 1 ORDER BY sort_order DESC, id ASC");
$gameRegions = $gameRegionsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--primary-teal:#3baaa3;--primary-teal-dark:#2a8a84;--gold:#c9a45c;--gold-dark:#a8843d}
        body{font-family:'Noto Sans SC',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:url('../assets/1.png');background-size:cover;background-position:center;background-repeat:no-repeat;background-attachment:fixed;color:#333;line-height:1.5;-webkit-font-smoothing:antialiased;min-height:100vh;position:relative}
        .container{max-width:900px;margin:0 auto;padding:20px 12px 80px;position:relative;z-index:1}
        .dashboard-main-body{background:transparent}
        
        /* 头部 */
        .header{background:rgba(255,255,255,0.8);padding:16px 20px;border-radius:16px;margin-bottom:20px;box-shadow:0 4px 15px rgba(0,0,0,.08);position:sticky;top:12px;z-index:10}
        .header h4{font-size:18px;font-weight:700;color:#333;margin:0}
        .balance-badge{padding:10px 16px;background:rgba(255,255,255,0.9);color:#333;font-size:14px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.06);font-weight:500;display:inline-block;margin-top:10px}
        .breadcrumb{display:flex;align-items:center;gap:8px;font-size:13px;color:#999;margin-top:10px}
        .breadcrumb a{color:#333;text-decoration:none;display:flex;align-items:center;gap:4px;transition:color 0.2s ease}
        .breadcrumb a:hover{color:#ff9900}
        .breadcrumb i{font-size:14px}
        .list-unstyled{list-style:none;padding:0;margin:0}
        
        /* 消息提示 */
        .alert{padding:16px;background:rgba(255,255,255,0.8);color:#333;font-size:14px;display:flex;align-items:center;gap:10px;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,.08);margin-bottom:20px;border:1px solid #e0e0e0}
        .alert i{color:#ff9900}
        
        /* 卡片样式 */
        .card{background:rgba(255,255,255,0.8);border:1px solid #e0e0e0;border-radius:16px;box-shadow:0 4px 15px rgba(0,0,0,.08);overflow:hidden;transition:all .3s ease}
        .card:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(0,0,0,.15)}
        .card-header{border-bottom:1px solid #e0e0e0;padding:16px 20px;background:rgba(255,255,255,0.9)}
        .card-header h5{font-size:16px;font-weight:700;color:#333;margin:0}
        .card-body{padding:20px}
        
        /* 表单样式 */
        .row{display:flex;flex-wrap:wrap;margin:-8px}
        .col-md-12{flex:0 0 100%;max-width:100%;padding:8px}
        .col-md-6{flex:0 0 50%;max-width:50%;padding:8px}
        @media screen and (max-width:768px){.col-md-6{flex:0 0 100%;max-width:100%}}
        .mb-3{margin-bottom:16px}
        .form-label{display:block;font-size:13px;color:#666;margin-bottom:8px;font-weight:500}
        .text-danger{color:#ff9900}
        .form-control{width:100%;padding:14px 16px;border:1px solid #e0e0e0;font-size:14px;color:#333;transition:all .3s ease;background:rgba(255,255,255,0.9);border-radius:12px}
        .form-control:focus{outline:none;border-color:#ffcc00;box-shadow:0 0 0 3px rgba(255,204,0,0.1)}
        .form-control::placeholder{color:#999}
        .form-select{width:100%;padding:14px 16px;border:1px solid #e0e0e0;font-size:14px;color:#333;background:rgba(255,255,255,0.9);border-radius:12px;transition:all .3s ease}
        .form-select:focus{outline:none;border-color:#ffcc00;box-shadow:0 0 0 3px rgba(255,204,0,0.1)}
        .form-text{font-size:12px;color:#999;margin-top:6px}
        
        /* 常用说明按钮 */
        .common-notes-title{font-size:12px;color:#999;margin-bottom:10px}
        .common-notes-list{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}
        .common-note-item{padding:8px 14px;border:none;background:rgba(255,255,255,0.9);color:#333;font-size:12px;cursor:pointer;transition:all .3s ease;border-radius:8px;font-weight:500;border:1px solid #e0e0e0}
        .common-note-item:hover{background:linear-gradient(135deg,#ffcc00 0%,#ff9900 100%);color:#000;transform:translateY(-1px);box-shadow:0 4px 15px rgba(255,204,0,.2)}
        
        /* 按钮 */
        .btn{padding:14px 24px;border:none;background:linear-gradient(135deg,#ffcc00 0%,#ff9900 100%);color:#000;font-size:14px;cursor:pointer;transition:all .3s ease;display:inline-flex;align-items:center;gap:8px;text-decoration:none;border-radius:12px;font-weight:600;box-shadow:0 3px 10px rgba(255,204,0,.4)}
        .btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(255,204,0,.6)}
        .btn i{color:#000}
        .btn-outline-danger{background:rgba(255,255,255,0.9);color:#333;border:1px solid #e0e0e0;box-shadow:none}
        .btn-outline-danger:hover{background:#f5f5f5;color:#333;transform:translateY(-1px)}
        .px-4{padding-left:24px;padding-right:24px}
        .py-2{padding-top:10px;padding-bottom:10px}
        .mt-4{margin-top:24px}
        .me-2{margin-right:8px}
        .d-flex{display:flex}
        .align-items-center{align-items:center}
        .justify-content-center{justify-content:center}
        .gap-3{gap:16px}
        
        /* 入场动画 */
        @keyframes fadeInUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
        .card{animation:fadeInUp .5s ease-out}
        
        /* 响应式设计 */
        @media screen and (max-width:768px){.container{padding:16px 12px 80px}
        .header{padding:14px 16px}
        .header h4{font-size:16px}
        .balance-badge{font-size:13px;padding:8px 12px}
        .breadcrumb{flex-wrap:wrap}
        .d-flex.align-items-center.justify-content-center{flex-direction:column;width:100%}
        .btn{width:100%;justify-content:center}}
        @media screen and (max-width:480px){.card-header{padding:14px 16px}
        .card-body{padding:16px}
        .form-control,.form-select{padding:12px 14px;font-size:13px}
        .common-notes-list{gap:6px}
        .common-note-item{padding:6px 12px;font-size:11px}}
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

<div class="container">
    <div class="dashboard-main-body">
        <!-- 头部 -->
        <div class="header">
            <h4>发布订单</h4>
            <div class="balance-badge">
                当前余额: ¥<?php echo number_format($currentBalance, 2); ?>
            </div>
            <ul class="breadcrumb list-unstyled">
                <li>
                    <a href="dashboard.php">
                        <i class="fas fa-home"></i>
                        控制面板
                    </a>
                </li>
                <li>-</li>
                <li>
                    <a href="my_orders.php">我的订单</a>
                </li>
                <li>-</li>
                <li>发布订单</li>
            </ul>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h5>订单信息</h5>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <!-- 隐藏字段 -->
                    <input type="hidden" name="hunter_option" value="system">
                    <input type="hidden" name="hunter_id" value="0">
                    <input type="hidden" name="payment_type" value="alipay">
                    
                    <div class="row">
                        <!-- 商品名称 -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">商品名称 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="product_name" 
                                       value="<?php echo isset($_POST['product_name']) ? htmlspecialchars($_POST['product_name']) : ''; ?>" 
                                       required>
                            </div>
                        </div>
                        
                        <!-- 价格 -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">订单价格 (¥) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01" class="form-control" name="price" 
                                       value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>" 
                                       required>
                                <div class="form-text">当前余额: ¥<?php echo number_format($currentBalance, 2); ?></div>
                            </div>
                        </div>
                        
                        <!-- 商品分类 -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">商品分类 <span class="text-danger">*</span></label>
                                <div class="row">
                                    <div class="col-md-6">
                                        <select class="form-select" name="category_id" id="parent_category" required>
                                            <option value="">请选择一级分类</option>
                                            <?php foreach ($parentCategories as $category): ?>
                                                <option value="<?php echo $category['id']; ?>" 
                                                    <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <select class="form-select" name="subcategory_id" id="child_category">
                                            <option value="">请选择二级分类</option>
                                            <?php if (isset($_POST['category_id']) && isset($childCategoriesByParent[$_POST['category_id']])): ?>
                                                <?php foreach ($childCategoriesByParent[$_POST['category_id']] as $subcategory): ?>
                                                    <option value="<?php echo $subcategory['id']; ?>" 
                                                        <?php echo (isset($_POST['subcategory_id']) && $_POST['subcategory_id'] == $subcategory['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($subcategory['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 游戏大区 -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">游戏大区 <span class="text-danger">*</span></label>
                                <select class="form-select" name="game_region" required>
                                    <option value="">请选择游戏大区</option>
                                    <?php foreach ($gameRegions as $region): ?>
                                        <option value="<?php echo htmlspecialchars($region['name']); ?>" <?php echo (isset($_POST['game_region']) && $_POST['game_region'] == $region['name']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($region['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- 数字ID -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">数字ID <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="digital_id" 
                                       value="<?php echo isset($_POST['digital_id']) ? htmlspecialchars($_POST['digital_id']) : ''; ?>"
                                       required>
                            </div>
                        </div>
                        
                        <!-- 中文ID -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">中文ID <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="chinese_id" 
                                       value="<?php echo isset($_POST['chinese_id']) ? htmlspecialchars($_POST['chinese_id']) : ''; ?>"
                                       required>
                            </div>
                        </div>
                        
                        <!-- 备注 -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">其他备注</label>
                                
                                <?php if (!empty($commonNotes['notes'])): ?>
                                <div class="common-notes-title">常用说明 (点击快速输入)</div>
                                <div class="common-notes-list">
                                    <?php foreach ($commonNotes['notes'] as $note): ?>
                                        <button type="button" class="common-note-item" 
                                                onclick="insertNote('<?php echo addslashes($note['content']); ?>')">
                                            <?php echo htmlspecialchars($note['title']); ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                                <div class="form-text">备注信息将保存在订单详情中</div>
                            </div>
                        </div>
                        
                        <!-- 按钮 -->
                        <div class="col-md-12">
                            <div class="d-flex align-items-center justify-content-center gap-3 mt-4">
                                <button type="submit" class="btn px-4 py-2"> 
                                    <i class="fas fa-upload"></i>
                                    发布订单
                                </button>
                                <a href="index.php" class="btn px-4 py-2"> 
                                    <i class="fas fa-times-circle"></i>
                                    取消
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// 分类联动选择
document.addEventListener('DOMContentLoaded', function() {
    const parentCategory = document.getElementById('parent_category');
    const childCategory = document.getElementById('child_category');
    
    // 存储所有子分类数据
    const childCategories = <?php echo json_encode($childCategoriesByParent); ?>;
    
    // 一级分类变化时更新二级分类
    parentCategory.addEventListener('change', function() {
        const parentId = this.value;
        childCategory.innerHTML = '<option value="">请选择二级分类</option>';
        
        if (parentId && childCategories[parentId]) {
            childCategories[parentId].forEach(function(subcategory) {
                const option = document.createElement('option');
                option.value = subcategory.id;
                option.textContent = subcategory.name;
                childCategory.appendChild(option);
            });
        }
    });
    
    // 如果表单提交后有错误，保留已选的分类状态
    <?php if (isset($_POST['category_id'])): ?>
        parentCategory.dispatchEvent(new Event('change'));
    <?php endif; ?>
    
    // 卡片入场动画
    const card = document.querySelector('.card');
    if (card) {
        card.style.opacity = '0';
        card.style.transform = 'translateY(10px)';
        
        setTimeout(() => {
            card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 50);
    }
});

// 插入常用说明到备注文本框
function insertNote(content) {
    const textarea = document.getElementById('notes');
    const currentValue = textarea.value;
    
    // 如果当前有内容，先添加换行
    if (currentValue.length > 0 && !currentValue.endsWith('\n')) {
        textarea.value += '\n';
    }
    
    textarea.value += content;
    textarea.focus();
}
</script>

<?php 
require_once 'footer.php';
?>