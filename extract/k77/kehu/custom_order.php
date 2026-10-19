<?php
require '../includes/session_manager.php';
require '../config.php';

$isLoggedIn = is_h5_logged_in();
$user_id = get_h5_user_id();

if(!$isLoggedIn || !$user_id) {
    die("<script>alert('请先登录'); location.href='login.php?redirect=custom_order.php';</script>");
}

// 处理表单提交
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", 
                     DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        // 获取并清理输入数据
        $product_name = trim($_POST['product_name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $category_id = intval($_POST['category_id'] ?? 0);
        $subcategory_id = intval($_POST['subcategory_id'] ?? 0);
        $game_region = trim($_POST['game_region'] ?? '');
        $digital_id = trim($_POST['digital_id'] ?? '');
        $chinese_id = trim($_POST['chinese_id'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $hunter_option = trim($_POST['hunter_option'] ?? 'system');
        $hunter_id = intval($_POST['hunter_id'] ?? 0);
        
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
        
        // 验证通过，生成订单号
        $order_no = date('YmdHis') . rand(100, 999);
        
        // 获取用户信息
        $userStmt = $db->prepare("SELECT user_idu, nickname FROM h5user WHERE id = ?");
        $userStmt->execute([$user_id]);
        $userInfo = $userStmt->fetch();
        $user_idu = $userInfo['user_idu'] ?? '';
        $user_nickname = $userInfo['nickname'] ?? '';
        
        // 获取客户端IP
        function getClientIP() {
            $ip = '';
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $ip = trim($ipList[0]);
            } else {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
            $ip = filter_var($ip, FILTER_VALIDATE_IP);
            return $ip ? $ip : '0.0.0.0';
        }
        $payment_ip = getClientIP();
        
        // 获取分类名称
        $category_name = '';
        $subcategory_name = '';
        if ($category_id > 0) {
            $categoryStmt = $db->prepare("SELECT name FROM product_categories WHERE id = ?");
            $categoryStmt->execute([$category_id]);
            $categoryInfo = $categoryStmt->fetch();
            $category_name = $categoryInfo['name'] ?? '';
            
            if ($subcategory_id > 0) {
                $subcategoryStmt = $db->prepare("SELECT name FROM product_categories WHERE id = ?");
                $subcategoryStmt->execute([$subcategory_id]);
                $subcategoryInfo = $subcategoryStmt->fetch();
                $subcategory_name = $subcategoryInfo['name'] ?? '';
            }
        }
        
        // 准备输入字段
        $input_fields = json_encode([
            '数字ID' => $digital_id,
            '中文ID' => $chinese_id,
            '备注' => $notes
        ], JSON_UNESCAPED_UNICODE);
        
        // 将订单数据保存到临时表，供支付回调使用
        // 先检查并创建order_temp表
        $createTableSql = "CREATE TABLE IF NOT EXISTS `order_temp` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `order_no` VARCHAR(50) NOT NULL COMMENT '订单号',
            `user_id` INT(11) NOT NULL COMMENT '用户ID',
            `product_id` INT(11) NOT NULL DEFAULT 0 COMMENT '商品ID',
            `product_name` VARCHAR(255) NOT NULL COMMENT '商品名称',
            `price` DECIMAL(10,2) NOT NULL COMMENT '价格',
            `payment_amount` DECIMAL(10,2) NOT NULL COMMENT '支付金额',
            `category_id` INT(11) NOT NULL COMMENT '分类ID',
            `subcategory_id` INT(11) DEFAULT NULL COMMENT '子分类ID',
            `game_region` VARCHAR(50) NOT NULL COMMENT '游戏大区',
            `digital_id` VARCHAR(100) NOT NULL COMMENT '数字ID',
            `chinese_id` VARCHAR(100) NOT NULL COMMENT '中文ID',
            `notes` TEXT DEFAULT NULL COMMENT '备注',
            `hunter_option` enum('system','assign') NOT NULL DEFAULT 'system' COMMENT '接单方式',
            `hunter_id` INT(11) DEFAULT 0 COMMENT '指定打手ID',
            `user_idu` VARCHAR(50) DEFAULT NULL COMMENT '用户唯一标识',
            `user_nickname` VARCHAR(100) DEFAULT NULL COMMENT '用户昵称',
            `payment_ip` VARCHAR(50) DEFAULT NULL COMMENT '支付IP',
            `create_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
            `expire_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '过期时间',
            `input_fields` TEXT DEFAULT NULL COMMENT '输入字段',
            `category_name` VARCHAR(100) DEFAULT NULL COMMENT '分类名称',
            `subcategory_name` VARCHAR(100) DEFAULT NULL COMMENT '子分类名称',
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_order_no` (`order_no`),
            KEY `idx_user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='临时订单表'";
        
        $db->exec($createTableSql);
        
        // 插入临时订单数据
        $stmt = $db->prepare("INSERT INTO order_temp (order_no, user_id, product_id, product_name, price, payment_amount, category_id, subcategory_id, game_region, digital_id, chinese_id, notes, hunter_option, hunter_id, user_idu, user_nickname, payment_ip, create_time, expire_time, input_fields, category_name, subcategory_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 15 MINUTE), ?, ?, ?)");
        $stmt->execute([$order_no, $user_id, 0, $product_name, $price, $price, $category_id, $subcategory_id, $game_region, $digital_id, $chinese_id, $notes, $hunter_option, $hunter_id, $user_idu, $user_nickname, $payment_ip, $input_fields, $category_name, $subcategory_name]);
        
        // 跳转到支付选择页面
        ob_clean(); // 清除可能的输出
        header("Location: payment_select.php?order_no=$order_no&amount=$price&type=custom");
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// 查询一级分类列表
try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", 
                 DB_USER, DB_PASS);
    
    $parentCategoriesStmt = $db->query("SELECT id, name FROM product_categories WHERE (parent_id IS NULL OR parent_id = 0) AND status = 1 ORDER BY sort_order, name");
    $parentCategories = $parentCategoriesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 查询所有子分类列表
    $childCategoriesStmt = $db->query("SELECT id, name, parent_id FROM product_categories WHERE parent_id IS NOT NULL AND parent_id != 0 AND status = 1 ORDER BY sort_order, name");
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
    
    // 获取当前用户余额
    $balanceStmt = $db->prepare("SELECT balance FROM h5user WHERE id = ?");
    $balanceStmt->execute([$user_id]);
    $balanceRow = $balanceStmt->fetch();
    $currentBalance = $balanceRow ? $balanceRow['balance'] : 0.00;
    
} catch (PDOException $e) {
    $parentCategories = [];
    $childCategoriesByParent = [];
    $currentBalance = 0.00;
}

// 获取打手列表
try {
    $hunterStmt = $db->query("SELECT id, username FROM users WHERE status = 1 ORDER BY id DESC LIMIT 20");
    $hunters = $hunterStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $hunters = [];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>自定义发布订单</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;500;600;700&family=ZCOOL+XiaoWei&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-teal: #3baaa3;
            --primary-teal-dark: #2a8a84;
            --primary-teal-light: #5bc4bd;
            --scroll-paper: #fdf8e8;
            --scroll-paper-dark: #f5ecd8;
            --gold: #c9a45c;
            --gold-dark: #a8843d;
            --gold-light: #e0c078;
            --dragon-green: #4db8a8;
            --cloud-blue: #7dd3d8;
            --border-gold: #d4af37;
        }

        body {
            font-family: 'Noto Serif SC', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            padding: 20px 16px 80px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
        }

        .header {
            background: #ffffff;
            padding: 16px;
            border-radius: 16px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .header h1 {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-teal-dark);
            text-align: center;
            margin: 0;
        }

        .balance-info {
            text-align: center;
            margin: 12px 0;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 12px;
        }

        .balance-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 4px;
        }

        .balance-amount {
            font-size: 18px;
            font-weight: 700;
            color: var(--gold-dark);
        }

        .card {
            background: #ffffff;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .card h2 {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary-teal-dark);
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f0f0f0;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            font-size: 14px;
            color: #333;
            background: #ffffff;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(201, 164, 92, 0.2);
        }

        .form-select {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            font-size: 14px;
            color: #333;
            background: #ffffff;
            transition: all 0.3s ease;
        }

        .form-select:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(201, 164, 92, 0.2);
        }

        .form-textarea {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            font-size: 14px;
            color: #333;
            background: #ffffff;
            resize: vertical;
            min-height: 100px;
            transition: all 0.3s ease;
        }

        .form-textarea:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(201, 164, 92, 0.2);
        }

        .radio-group {
            display: flex;
            gap: 16px;
            margin-top: 8px;
        }

        .radio-item {
            flex: 1;
            padding: 12px;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .radio-item:hover {
            border-color: var(--gold);
            background: #fff9e6;
        }

        .radio-item input[type="radio"] {
            display: none;
        }

        .radio-item input[type="radio"]:checked + label {
            color: var(--gold-dark);
            font-weight: 600;
        }

        .radio-item input[type="radio"]:checked {
            border-color: var(--gold);
            background: #fff9e6;
        }

        .radio-item label {
            display: block;
            cursor: pointer;
            font-size: 14px;
        }

        .btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
            color: #ffffff;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(201, 164, 92, 0.4);
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #333;
            margin-top: 12px;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        .alert-success {
            background: #e8f5e8;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .form-hint {
            font-size: 12px;
            color: #999;
            margin-top: 4px;
        }

        .row {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
        }

        .col {
            flex: 1;
        }

        @media (max-width: 480px) {
            body {
                padding: 16px 12px 80px;
            }
            
            .card {
                padding: 16px;
            }
            
            .form-input,
            .form-select,
            .form-textarea {
                padding: 12px 14px;
                font-size: 13px;
            }
            
            .btn {
                padding: 14px;
                font-size: 15px;
            }
        }
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
        <div class="header">
            <h1>自定义发布订单</h1>
            <div class="balance-info">
                <div class="balance-label">当前余额</div>
                <div class="balance-amount">¥<?php echo number_format($currentBalance, 2); ?></div>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>订单信息</h2>
            <form method="post" action="">
                <div class="form-group">
                    <label class="form-label">商品名称 <span style="color: #ff6b6b;">*</span></label>
                    <input type="text" class="form-input" name="product_name" 
                           value="<?php echo isset($_POST['product_name']) ? htmlspecialchars($_POST['product_name']) : ''; ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">订单价格 (¥) <span style="color: #ff6b6b;">*</span></label>
                    <input type="number" step="0.01" min="0.01" class="form-input" name="price" 
                           value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>" 
                           required>
                    <div class="form-hint">余额不足时将跳转到支付页面</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">商品分类 <span style="color: #ff6b6b;">*</span></label>
                    <div class="row">
                        <div class="col">
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
                        <div class="col">
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
                
                <div class="form-group">
                    <label class="form-label">游戏大区 <span style="color: #ff6b6b;">*</span></label>
                    <select class="form-select" name="game_region" required>
                        <option value="">请选择游戏大区</option>
                        <option value="手机端" <?php echo (isset($_POST['game_region']) && $_POST['game_region'] == '手机端') ? 'selected' : ''; ?>>手机端</option>
                        <option value="电脑端" <?php echo (isset($_POST['game_region']) && $_POST['game_region'] == '电脑端') ? 'selected' : ''; ?>>电脑端</option>
                        <option value="QQ区" <?php echo (isset($_POST['game_region']) && $_POST['game_region'] == 'QQ区') ? 'selected' : ''; ?>>QQ区</option>
                        <option value="WX区" <?php echo (isset($_POST['game_region']) && $_POST['game_region'] == 'WX区') ? 'selected' : ''; ?>>WX区</option>
                    </select>
                </div>
                
                <div class="row">
                    <div class="col">
                        <div class="form-group">
                            <label class="form-label">数字ID <span style="color: #ff6b6b;">*</span></label>
                            <input type="text" class="form-input" name="digital_id" 
                                   value="<?php echo isset($_POST['digital_id']) ? htmlspecialchars($_POST['digital_id']) : ''; ?>" 
                                   required>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label class="form-label">中文ID <span style="color: #ff6b6b;">*</span></label>
                            <input type="text" class="form-input" name="chinese_id" 
                                   value="<?php echo isset($_POST['chinese_id']) ? htmlspecialchars($_POST['chinese_id']) : ''; ?>" 
                                   required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">接单方式</label>
                    <div class="radio-group">
                        <div class="radio-item">
                            <input type="radio" id="system" name="hunter_option" value="system" checked>
                            <label for="system">系统安排</label>
                        </div>
                        <div class="radio-item">
                            <input type="radio" id="assign" name="hunter_option" value="assign">
                            <label for="assign">指定打手</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" id="hunter-select" style="display: none;">
                    <label class="form-label">选择打手</label>
                    <select class="form-select" name="hunter_id">
                        <option value="0">请选择打手</option>
                        <?php foreach ($hunters as $hunter): ?>
                            <option value="<?php echo $hunter['id']; ?>" 
                                <?php echo (isset($_POST['hunter_id']) && $_POST['hunter_id'] == $hunter['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($hunter['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">其他备注</label>
                    <textarea class="form-textarea" name="notes"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                    <div class="form-hint">备注信息将保存在订单详情中</div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i>
                    发布订单
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i>
                    取消
                </a>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 分类联动
            const parentCategory = document.getElementById('parent_category');
            const childCategory = document.getElementById('child_category');
            
            const childCategories = <?php echo json_encode($childCategoriesByParent); ?>;
            
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
            
            // 接单方式切换
            const hunterOption = document.querySelectorAll('input[name="hunter_option"]');
            const hunterSelect = document.getElementById('hunter-select');
            
            hunterOption.forEach(function(radio) {
                radio.addEventListener('change', function() {
                    if (this.value === 'assign') {
                        hunterSelect.style.display = 'block';
                    } else {
                        hunterSelect.style.display = 'none';
                    }
                });
            });
            
            // 初始化接单方式
            const selectedOption = document.querySelector('input[name="hunter_option"]:checked');
            if (selectedOption && selectedOption.value === 'assign') {
                hunterSelect.style.display = 'block';
            }
        });
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>