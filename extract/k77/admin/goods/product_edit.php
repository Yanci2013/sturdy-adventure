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

// 获取商品ID并验证
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($product_id <= 0) {
    header('Location: products.php');
    exit();
}

// 获取商品信息
$sql = "SELECT p.*, c.name as category_name 
        FROM products p
        LEFT JOIN product_categories c ON p.category_id = c.id
        WHERE p.id = :product_id";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        header('Location: products.php');
        exit();
    }
    
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 解析JSON字段
    $product['images'] = json_decode($product['images'], true) ?: [];
    $product['input_fields'] = json_decode($product['input_fields'], true) ?: [];
    $product['game_region_options'] = json_decode($product['game_region_options'], true) ?: [];
    $product['tags'] = json_decode($product['tags'], true) ?: [];
    $product['region_prices'] = json_decode($product['region_prices'], true) ?: [];
    $product['price_increase'] = json_decode($product['price_increase'], true) ?: [];
    $product['wheel_prize'] = json_decode($product['wheel_prize'], true) ?: [];
    
    // 获取所有一级分类
    $parent_categories = $pdo->query("SELECT id, name FROM product_categories WHERE parent_id = 0 AND level = 1 AND status = 1 ORDER BY sort_order DESC, name ASC")->fetchAll();
    
    // 获取所有二级分类（按父分类分组）
    $sub_categories = $pdo->query("SELECT id, parent_id, name FROM product_categories WHERE parent_id > 0 AND level = 2 AND status = 1 ORDER BY sort_order DESC, name ASC")->fetchAll();
    
    $sub_categories_grouped = [];
    foreach ($sub_categories as $category) {
        $sub_categories_grouped[$category['parent_id']][] = $category;
    }
    
    // 获取游戏大区数据
    $game_regions = $pdo->query("SELECT id, name FROM game_regions WHERE status = 1 ORDER BY sort_order DESC, id ASC")->fetchAll();
    
    // 如果没有自定义大区，使用默认值
    if (empty($game_regions)) {
        $game_regions = [
            ['id' => 1, 'name' => 'QQ区'],
            ['id' => 2, 'name' => '微信区'],
            ['id' => 3, 'name' => 'TapTap专区'],
            ['id' => 4, 'name' => '端游专区']
        ];
    }
    
    // 获取当前商品的父分类ID
    $selected_parent_id = 0;
    if ($product['category_id']) {
        $stmt = $pdo->prepare("SELECT parent_id FROM product_categories WHERE id = ?");
        $stmt->execute([$product['category_id']]);
        $category_info = $stmt->fetch();
        $selected_parent_id = $category_info['parent_id'] ?? 0;
    }
    
} catch (PDOException $e) {
    die("数据库错误: " . $e->getMessage());
}

// 检查并创建图片目录
$uploadBaseDir = dirname(__DIR__, 2) . '/assets/images/products/';
if (!file_exists($uploadBaseDir)) {
    if (!mkdir($uploadBaseDir, 0755, true)) {
        die("无法创建图片目录，请检查权限");
    }
}
if (!is_writable($uploadBaseDir)) {
    die("图片目录不可写，请检查权限");
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取并清理表单数据
    $name = trim($_POST['name']);
    $chinese_name = trim($_POST['chinese_name']);
    $product_code = trim($_POST['product_code']);
    $price = floatval($_POST['price']);
    $original_price = !empty($_POST['original_price']) ? floatval($_POST['original_price']) : null;
    $parent_category_id = intval($_POST['parent_category_id']);
    $category_id = intval($_POST['category_id']);
    $shipment_quantity = intval($_POST['shipment_quantity']);
    $stock = intval($_POST['stock']);
    $unit = trim($_POST['unit']);
    $max_purchase_per_user = !empty($_POST['max_purchase_per_user']) ? intval($_POST['max_purchase_per_user']) : null;
    $min_purchase_per_user = intval($_POST['min_purchase_per_user']);
    $description = trim($_POST['description']);
    $notes = trim($_POST['notes']);
    $default_input = trim($_POST['default_input']);
    $coach_selection_type = isset($_POST['coach_selection_type']) ? trim($_POST['coach_selection_type']) : 'system';
    $status = isset($_POST['status']) ? 1 : 0;
    $sort_order = !empty($_POST['sort_order']) ? intval($_POST['sort_order']) : null;
    $merchant_price = !empty($_POST['merchant_price']) ? floatval($_POST['merchant_price']) : null;
    $coach_settlement_price = !empty($_POST['coach_settlement_price']) ? floatval($_POST['coach_settlement_price']) : null;
    $game_type = trim($_POST['game_type']);
    $service_type = trim($_POST['service_type']);
    $virtual_sales = intval($_POST['virtual_sales']);
    
    // 处理涨价信息
    $price_increase = [];
    if (!empty($_POST['price_increase_amount'])) {
        $price_increase = [
            'price' => floatval($_POST['price_increase_amount']),
            'reason' => trim($_POST['price_increase_reason'])
        ];
    }
    
    // 处理大转盘奖品
    $wheel_prize = [];
    if (!empty($_POST['wheel_prize_name'])) {
        $wheel_prize = [
            'name' => trim($_POST['wheel_prize_name']),
            'description' => trim($_POST['wheel_prize_description']),
            'probability' => floatval($_POST['wheel_prize_probability'])
        ];
    }
    
    // 处理图片上传
    $images = $product['images'] ?? [];
    if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        
        $new_images = [];
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                $fileName = basename($_FILES['images']['name'][$key]);
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                if (in_array($fileExt, $allowedExts) && $_FILES['images']['size'][$key] <= $maxFileSize) {
                    $newFileName = 'product_' . md5(uniqid() . $fileName) . '.' . $fileExt;
                    $uploadPath = $uploadBaseDir . $newFileName;
                    
                    if (move_uploaded_file($tmp_name, $uploadPath)) {
                        $new_images[] = '/assets/images/products/' . $newFileName;
                    }
                }
            }
        }
        
        if (!empty($new_images)) {
            // 删除旧图片
            if (!empty($images)) {
                foreach ($images as $old_image) {
                    $old_image_path = dirname(__DIR__, 2) . $old_image;
                    if (file_exists($old_image_path)) {
                        unlink($old_image_path);
                    }
                }
            }
            $images = $new_images;
        }
    }
    
    // 处理主图上传
    $main_image = $product['main_image'] ?? '';
    if (!empty($_FILES['main_image']['name'])) {
        $fileName = basename($_FILES['main_image']['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($fileExt, $allowedExts)) {
            $newFileName = 'main_' . md5(uniqid() . $fileName) . '.' . $fileExt;
            $uploadPath = $uploadBaseDir . $newFileName;
            
            if (move_uploaded_file($_FILES['main_image']['tmp_name'], $uploadPath)) {
                // 删除旧主图
                if (!empty($main_image)) {
                    $old_main_image_path = dirname(__DIR__, 2) . $main_image;
                    if (file_exists($old_main_image_path)) {
                        unlink($old_main_image_path);
                    }
                }
                $main_image = '/assets/images/products/' . $newFileName;
            }
        }
    }
    
    // 处理游戏大区选项
    $game_regions = [];
    if (isset($_POST['game_region']) && is_array($_POST['game_region'])) {
        $game_regions = array_map('trim', $_POST['game_region']);
    }
    
    // 处理区域价格
    $region_prices = [];
    foreach ($game_regions as $region) {
        $region_key = md5($region);
        $region_price = isset($_POST["region_price_{$region_key}"]) ? floatval($_POST["region_price_{$region_key}"]) : $price;
        $region_original_price = isset($_POST["region_original_price_{$region_key}"]) ? floatval($_POST["region_original_price_{$region_key}"]) : $original_price;
        
        $region_prices[$region] = [
            'price' => $region_price,
            'original_price' => $region_original_price
        ];
    }
    
    // 准备输入字段配置
    $input_fields = [
        [
            'label' => '中文ID',
            'name' => 'chinese_id',
            'type' => 'text',
            'required' => 1,
            'placeholder' => '请输入中文ID'
        ],
        [
            'label' => '数字ID',
            'name' => 'numeric_id',
            'type' => 'number',
            'required' => 1,
            'placeholder' => '请输入数字ID'
        ],
        [
            'label' => '备注',
            'name' => 'remark',
            'type' => 'text',
            'required' => 0,
            'placeholder' => '请输入备注信息'
        ]
    ];
    
    // 验证数据
    $error = '';
    if (empty($name)) $error = "商品名称不能为空";
    elseif ($price <= 0) $error = "价格必须大于0";
    elseif ($parent_category_id <= 0) $error = "请选择有效的一级分类";
    elseif ($category_id <= 0) $error = "请选择有效的二级分类";
    elseif ($shipment_quantity <= 0) $error = "每份发货数量必须大于0";
    elseif ($stock < 0) $error = "库存不能为负数";
    elseif ($min_purchase_per_user <= 0) $error = "最低购买数量必须大于0";
    elseif (empty($game_regions)) $error = "请至少选择一个游戏大区";
    
    if (empty($error)) {
        // 准备更新SQL
        $update_sql = "UPDATE products SET 
                      name = :name,
                      chinese_name = :chinese_name,
                      product_code = :product_code,
                      price = :price,
                      original_price = :original_price,
                      category_id = :category_id,
                      shipment_quantity = :shipment_quantity,
                      stock = :stock,
                      unit = :unit,
                      max_purchase_per_user = :max_purchase_per_user,
                      min_purchase_per_user = :min_purchase_per_user,
                      description = :description,
                      notes = :notes,
                      images = :images,
                      input_fields = :input_fields,
                      default_input = :default_input,
                      game_region_options = :game_regions,
                      region_prices = :region_prices,
                      coach_selection_type = :coach_selection_type,
                      status = :status,
                      sort_order = :sort_order,
                      merchant_price = :merchant_price,
                      coach_settlement_price = :coach_settlement_price,
                      main_image = :main_image,
                      game_type = :game_type,
                      service_type = :service_type,
                      virtual_sales = :virtual_sales,
                      price_increase = :price_increase,
                      wheel_prize = :wheel_prize,
                      updated_at = NOW()
                      WHERE id = :product_id";
        
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare($update_sql);
            
            $max_purchase = $max_purchase_per_user > 0 ? $max_purchase_per_user : null;
            
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':chinese_name', $chinese_name);
            $stmt->bindParam(':product_code', $product_code);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':original_price', $original_price);
            $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
            $stmt->bindParam(':shipment_quantity', $shipment_quantity, PDO::PARAM_INT);
            $stmt->bindParam(':stock', $stock, PDO::PARAM_INT);
            $stmt->bindParam(':unit', $unit);
            $stmt->bindParam(':max_purchase_per_user', $max_purchase, PDO::PARAM_INT);
            $stmt->bindParam(':min_purchase_per_user', $min_purchase_per_user, PDO::PARAM_INT);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':notes', $notes);
            $stmt->bindValue(':images', json_encode($images, JSON_UNESCAPED_UNICODE));
            $stmt->bindValue(':input_fields', json_encode($input_fields, JSON_UNESCAPED_UNICODE));
            $stmt->bindParam(':default_input', $default_input);
            $stmt->bindValue(':game_regions', json_encode($game_regions, JSON_UNESCAPED_UNICODE));
            $stmt->bindValue(':region_prices', json_encode($region_prices, JSON_UNESCAPED_UNICODE));
            $stmt->bindParam(':coach_selection_type', $coach_selection_type);
            $stmt->bindParam(':status', $status, PDO::PARAM_INT);
            $stmt->bindParam(':sort_order', $sort_order, PDO::PARAM_INT);
            $stmt->bindParam(':merchant_price', $merchant_price);
            $stmt->bindParam(':coach_settlement_price', $coach_settlement_price);
            $stmt->bindParam(':main_image', $main_image);
            $stmt->bindParam(':game_type', $game_type);
            $stmt->bindParam(':service_type', $service_type);
            $stmt->bindParam(':virtual_sales', $virtual_sales, PDO::PARAM_INT);
            $stmt->bindValue(':price_increase', json_encode($price_increase, JSON_UNESCAPED_UNICODE));
            $stmt->bindValue(':wheel_prize', json_encode($wheel_prize, JSON_UNESCAPED_UNICODE));
            $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                // 记录管理员操作日志
                $admin_id = $_SESSION['admin_id'] ?? null;
                $admin_name = $_SESSION['admin_username'] ?? '未知管理员';
                $operation_details = "编辑商品：ID=" . $product_id . ", 名称=" . $name . ", 价格=" . $price . "元";
                log_admin_operation($pdo, $admin_id, $admin_name, 'edit_product', $operation_details);
                
                $pdo->commit();
                $_SESSION['success_msg'] = "商品 #$product_id 更新成功！";
                header("Location: product_edit.php?id=$product_id");
                exit();
            } else {
                $pdo->rollBack();
                $error = "商品更新失败";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "商品更新失败: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑商品 - 后台管理系统</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 图标 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- 自定义样式 -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #ffffff;
            color: #333;
            line-height: 1.5;
            padding: 20px;
            font-size: 13px;
        }

        h1 {
            font-size: 18px;
            color: #000;
            margin-bottom: 20px;
            font-weight: 600;
        }

        h4 {
            font-size: 16px;
            color: #000;
            margin-bottom: 16px;
            font-weight: 600;
        }

        .btn {
            display: inline-block;
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff;
            color: #333;
            text-decoration: none;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn:hover {
            background: #f0f0f0;
        }

        .btn-primary {
            background: #1E9FFF;
            color: white;
            border-color: #1E9FFF;
        }

        .btn-primary:hover {
            background: #1C86EE;
            border-color: #1C86EE;
        }

        .alert {
            padding: 10px 16px;
            margin: 12px 0;
            border-radius: 4px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-success {
            background: #f0f9ff;
            border: 1px solid #e0f2fe;
            color: #0369a1;
        }

        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fee2e2;
            color: #b91c1c;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            color: #333;
            background: #ffffff;
        }

        .form-control:focus {
            outline: none;
            border-color: #1E9FFF;
            box-shadow: 0 0 0 2px rgba(30, 159, 255, 0.1);
        }

        .form-select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            color: #333;
            background: #ffffff;
        }

        .form-label {
            font-size: 13px;
            color: #666;
            font-weight: 500;
            margin-bottom: 6px;
            display: block;
        }

        .row {
            display: flex;
            margin-bottom: 16px;
            align-items: flex-start;
        }

        .col-md-2 {
            flex: 0 0 16.666%;
            padding-right: 12px;
            font-weight: 500;
            color: #666;
            font-size: 13px;
        }

        .col-md-10 {
            flex: 1;
        }

        .col-md-6 {
            flex: 0 0 50%;
            padding: 0 8px;
        }

        .required-field::after {
            content: ' *';
            color: #ff4d4f;
        }

        /* 标签页样式 */
        .nav-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }

        .nav-link {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff;
            color: #333;
            text-decoration: none;
            font-size: 12px;
            cursor: pointer;
        }

        .nav-link.active {
            background: #1E9FFF;
            color: white;
            border-color: #1E9FFF;
        }

        .tab-content {
            margin-bottom: 20px;
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
        }

        /* 图片预览项样式 */
        .image-preview-item {
            width: 100px;
            height: 100px;
            background-size: cover;
            background-position: center;
            border-radius: 4px;
            border: 1px solid #ddd;
            margin-right: 10px;
            margin-bottom: 10px;
            display: inline-block;
            position: relative;
        }

        /* 删除图片按钮样式 */
        .image-preview-item .remove-image {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 20px;
            height: 20px;
            background: #ff4d4f;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            cursor: pointer;
        }

        /* 价格区块样式 */
        .price-section {
            background: #f8f8f8;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 12px;
        }

        /* 价格区块标题样式 */
        .price-section-title {
            font-weight: 500;
            margin-bottom: 8px;
            color: #333;
            font-size: 13px;
        }

        /* 区域价格项样式 */
        .region-price-item {
            margin-bottom: 12px;
            padding: 12px;
            background: #f8f8f8;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }

        /* 区域价格标签样式 */
        .region-price-label {
            font-weight: 500;
            margin-bottom: 6px;
            color: #333;
            font-size: 13px;
        }

        /* 标签项样式 */
        .tag-item {
            margin-bottom: 8px;
        }

        /* 操作按钮样式 */
        .btn-action {
            min-width: 100px;
        }

        /* 面包屑导航 */
        .breadcrumb {
            font-size: 12px;
            color: #999;
            margin-bottom: 16px;
        }

        .breadcrumb a {
            color: #1E9FFF;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }
    </style>
  <link href="../res/admin-white.css" rel="stylesheet">
</head>
<body>
    <h4>编辑商品 #<?php echo $product_id; ?></h4>
    
    <!-- 面包屑导航 -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">控制台</a></li>
            <li class="breadcrumb-item"><a href="products.php">商品列表</a></li>
            <li class="breadcrumb-item active" aria-current="page">编辑商品</li>
        </ol>
    </nav>

    <!-- 错误提示 -->
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <!-- 成功提示 -->
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($_SESSION['success_msg']); ?>
            <?php unset($_SESSION['success_msg']); ?>
        </div>
    <?php endif; ?>

    <!-- 主表单 -->
    <form method="post" action="product_edit.php?id=<?php echo $product_id; ?>" enctype="multipart/form-data">
        <!-- 标签导航 -->
        <ul class="nav nav-tabs" id="productTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab">基本信息</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="price-tab" data-bs-toggle="tab" data-bs-target="#price" type="button" role="tab">价格设置</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="inventory-tab" data-bs-toggle="tab" data-bs-target="#inventory" type="button" role="tab">库存管理</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="content-tab" data-bs-toggle="tab" data-bs-target="#content" type="button" role="tab">内容设置</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab">高级设置</button>
            </li>
        </ul>
        
        <!-- 标签内容区 -->
        <div class="tab-content" id="productTabsContent">
            <!-- 基本信息标签页 -->
            <div class="tab-pane active" id="basic" role="tabpanel">
                <h5>基本信息</h5>
                        
                        <!-- 分类选择 -->
                        <div class="row mb-3">
                            <label class="col-md-2 col-form-label required-field">一级分类</label>
                            <div class="col-md-10">
                                <select class="form-select" id="parent_category" name="parent_category_id" required>
                                    <option value="">请选择一级分类</option>
                                    <?php foreach ($parent_categories as $category): ?>
                                        <option value="<?= $category['id'] ?>" 
                                            <?= ($selected_parent_id == $category['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <label class="col-md-2 col-form-label required-field">二级分类</label>
                            <div class="col-md-10">
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">请先选择一级分类</option>
                                    <?php if ($selected_parent_id > 0 && isset($sub_categories_grouped[$selected_parent_id])): ?>
                                        <?php foreach ($sub_categories_grouped[$selected_parent_id] as $sub_cat): ?>
                                            <option value="<?= $sub_cat['id'] ?>" 
                                                <?= ($sub_cat['id'] == $product['category_id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($sub_cat['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- 商品名称 -->
                        <div class="row mb-3">
                            <label class="col-md-2 col-form-label required-field">商品名称</label>
                            <div class="col-md-10">
                                <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                            </div>
                        </div>
                        
                        <!-- 中文名称 -->
                        <div class="row mb-3">
                            <label class="col-md-2 col-form-label">中文名称</label>
                            <div class="col-md-10">
                                <input type="text" class="form-control" name="chinese_name" value="<?php echo htmlspecialchars($product['chinese_name'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <!-- 商品编号 -->
                        <div class="row mb-3">
                            <label class="col-md-2 col-form-label">商品编号</label>
                            <div class="col-md-10">
                                <input type="text" class="form-control" name="product_code" value="<?php echo htmlspecialchars($product['product_code'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <!-- 商品图片 -->
                        <div class="row mb-3">
                            <label class="col-md-2 col-form-label">商品图片</label>
                            <div class="col-md-10">
                                <input type="file" class="form-control" name="images[]" multiple accept="image/*">
                                <?php if (!empty($product['images'])): ?>
                                    <div class="mt-2" id="imagePreview">
                                        <?php foreach ($product['images'] as $index => $image): ?>
                                            <div class="image-preview-item" style="background-image: url('<?php echo htmlspecialchars($image); ?>')">
                                                <div class="remove-image" onclick="removeImage(<?= $index ?>)">
                                                    <i class="fas fa-times"></i>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <small class="text-muted">当前图片</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- 商品主图 -->
                        <div class="row mb-3">
                            <label class="col-md-2 col-form-label">商品主图</label>
                            <div class="col-md-10">
                                <input type="file" class="form-control" name="main_image" accept="image/*">
                                <?php if (!empty($product['main_image'])): ?>
                                    <div class="mt-2">
                                        <div class="image-preview-item" style="background-image: url('<?php echo htmlspecialchars($product['main_image']); ?>')"></div>
                                        <small class="text-muted">当前主图</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 价格设置标签页 -->
                <div class="tab-pane fade" id="price" role="tabpanel">
                    <div class="card-section">
                        <h5 class="card-title">价格设置</h5>
                        
                        <!-- 商品原价 -->
                        <div class="row mb-3">
                            <label class="col-md-2 col-form-label">商品原价</label>
                            <div class="col-md-10">
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" class="form-control" name="original_price" 
                                           value="<?php echo htmlspecialchars($product['original_price'] ?? ''); ?>">
                                    <span class="input-group-text">元</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 商品现价 -->
                        <div class="row mb-3">
                            <label class="col-md-2 col-form-label required-field">商品现价</label>
                            <div class="col-md-10">
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0.01" class="form-control" name="price" 
                                           value="<?php echo htmlspecialchars($product['price']); ?>" required>
                                    <span class="input-group-text">元</span>
                                </div>
                                <small class="text-muted">默认价格，如果未设置区域价格则使用此价格</small>
                            </div>
                        </div>
                        
                        <!-- 涨价信息 -->
                        <div class="price-section">
                            <h6 class="price-section-title">涨价信息</h6>
                            
                            <div class="row mb-3">
                                <label class="col-md-2 col-form-label">涨价金额</label>
                                <div class="col-md-10">
                                    <div class="input-group">
                                        <input type="number" step="0.01" min="0" class="form-control" name="price_increase_amount" 
                                               value="<?php echo htmlspecialchars($product['price_increase']['price'] ?? ''); ?>">
                                        <span class="input-group-text">元</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <label class="col-md-2 col-form-label">涨价原因</label>
                                <div class="col-md-10">
                                    <input type="text" class="form-control" name="price_increase_reason" 
                                           value="<?php echo htmlspecialchars($product['price_increase']['reason'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- 区域价格设置 -->
                        <div class="price-section">
                            <h6 class="price-section-title">区域价格设置</h6>
                            
                            <div class="row mb-3">
                                <label class="col-md-2 col-form-label">游戏大区</label>
                                <div class="col-md-10">
                                    <?php foreach ($game_regions as $region): ?>
                                    <div class="form-check">
                                        <input class="form-check-input game-region-checkbox" type="checkbox" name="game_region[]" id="region_<?php echo $region['id']; ?>" value="<?php echo htmlspecialchars($region['name']); ?>"
                                            <?= in_array($region['name'], $product['game_region_options'] ?? []) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="region_<?php echo $region['id']; ?>"><?php echo htmlspecialchars($region['name']); ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- 区域价格容器 -->
                            <div id="regionPricesContainer">
                                <?php 
                                $regions = $product['game_region_options'] ?? [];
                                foreach ($regions as $region): 
                                    $region_key = md5($region);
                                    $region_price = $product['region_prices'][$region]['price'] ?? $product['price'];
                                    $region_original_price = $product['region_prices'][$region]['original_price'] ?? $product['original_price'];
                                ?>
                                <div class="region-price-item">
                                    <div class="region-price-label"><?= htmlspecialchars($region) ?></div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="input-group mb-3">
                                                <span class="input-group-text">价格</span>
                                                <input type="number" step="0.01" min="0" class="form-control" 
                                                       name="region_price_<?= $region_key ?>" 
                                                       value="<?= number_format($region_price, 2) ?>">
                                                <span class="input-group-text">¥</span>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="input-group mb-3">
                                                <span class="input-group-text">原价</span>
                                                <input type="number" step="0.01" min="0" class="form-control" 
                                                       name="region_original_price_<?= $region_key ?>" 
                                                       value="<?= $region_original_price ? number_format($region_original_price, 2) : '' ?>">
                                                <span class="input-group-text">¥</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- 商家价格 -->
                        <div class="row mb-3">
                            <label class="col-md-2 col-form-label">商家价格</label>
                            <div class="col-md-10">
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" class="form-control" name="merchant_price" 
                                           value="<?php echo htmlspecialchars($product['merchant_price'] ?? ''); ?>">
                                    <span class="input-group-text">¥</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 打手结算价 -->
                        <div class="row mb-3">
                            <label class="col-md-2 col-form-label">打手结算价</label>
                            <div class="col-md-10">
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" class="form-control" name="coach_settlement_price" 
                                           value="<?php echo htmlspecialchars($product['coach_settlement_price'] ?? ''); ?>">
                                    <span class="input-group-text">¥</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 库存管理标签页 -->
                <div class="tab-pane fade" id="inventory" role="tabpanel">
                    <div class="card-section">
                        <h5 class="card-title">库存管理</h5>
                        
                        <!-- 每份发货数量 -->
                        <div class="row mb-3">
                            <label class="col-md-2 col-form-label required-field">每份发货数量</label>
                            <div class="col-md-10">
                                <div class="input-group">
                                    <input type="number" min="1" class="form-control" name="shipment_quantity" 
                                           value="<?php echo htmlspecialchars($product['shipment_quantity']); ?>" required>
                                    <span class="input-group-text">件/单</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 商品库存 -->
                        <div class="row mb-3">
                            <label class="col-md-2 col-form-label required-field">商品库存</label>
                            <div class="col-md-10">
                                <div class="input-group">
                                    <input type="number" min="0" class="form-control" name="stock" 
                                           value="<?php echo htmlspecialchars($product['stock']); ?>" required>
                                    <span class="input-group-text">件</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 虚拟销量 -->
                        <div class="row mb-3">
                            <label class="col-md-2 col-form-label">虚拟销量</label>
                            <div class="col-md-10">
                                <div class="input-group">
                                    <input type="number" min="0" class="form-control" name="virtual_sales" 
                                           value="<?php echo htmlspecialchars($product['virtual_sales'] ?? '0'); ?>">
                                    <span class="input-group-text">件</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 数量单位 -->
                        <div class="row mb-3">
                            <label class="col-md-2 col-form-label required-field">数量单位</label>
                            <div class="col-md-10">
                                <input type="text" class="form-control" name="unit" 
                                       value="<?php echo htmlspecialchars($product['unit']); ?>" required>
                                <small class="text-muted">如：单、小时、把等</small>
                            </div>
                        </div>
                        
                        <!-- 排序权重 -->
                        <div class="row mb-3">
                            <label class="col-md-2 col-form-label">排序权重</label>
                            <div class="col-md-10">
                                <input type="number" class="form-control" name="sort_order" 
                                       value="<?php echo htmlspecialchars($product['sort_order'] ?? ''); ?>">
                                <small class="text-muted">数字越大排序越靠前</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 内容设置标签页 -->
                <div class="tab-pane fade" id="content" role="tabpanel">
                    <div class="card-section">
                        <h5 class="card-title">内容设置</h5>
                        
                        <!-- 商品描述 -->
                        <div class="row mb-3">
                            <label class="col-md-2 col-form-label required-field">商品描述</label>
                            <div class="col-md-10">
                                <textarea class="form-control" name="description" rows="5" required><?php 
                                    echo htmlspecialchars($product['description']); 
                                ?></textarea>
                            </div>
                        </div>
                        
                        <!-- 购买注意事项 -->
                        <div class="row mb-3">
                            <label class="col-md-2 col-form-label">购买注意事项</label>
                            <div class="col-md-10">
                                <textarea class="form-control" name="notes" rows="3"><?php 
                                    echo htmlspecialchars($product['notes'] ?? ''); 
                                ?></textarea>
                            </div>
                        </div>
                        
                        <!-- 默认输入 -->
                        <div class="row mb-3">
                            <label class="col-md-2 col-form-label">默认输入</label>
                            <div class="col-md-10">
                                <input type="text" class="form-control" name="default_input" 
                                       value="<?php echo htmlspecialchars($product['default_input'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <!-- 游戏类型 -->
                        <div class="row mb-3">
                            <label class="col-md-2 col-form-label">游戏类型</label>
                            <div class="col-md-10">
                                <input type="text" class="form-control" name="game_type" 
                                       value="<?php echo htmlspecialchars($product['game_type'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <!-- 服务类型 -->
                        <div class="row mb-3">
                            <label class="col-md-2 col-form-label">服务类型</label>
                            <div class="col-md-10">
                                <input type="text" class="form-control" name="service_type" 
                                       value="<?php echo htmlspecialchars($product['service_type'] ?? ''); ?>">
                            </div>
                        </div>
                        
                    </div>
                </div>
                
                <!-- 高级设置标签页 -->
                <div class="tab-pane fade" id="settings" role="tabpanel">
                    <div class="card-section">
                        <h5 class="card-title">高级设置</h5>
                        
                        <!-- 最低购买数量 -->
                        <div class="row mb-3">
                            <label class="col-md-2 col-form-label required-field">最低购买数量</label>
                            <div class="col-md-10">
                                <div class="input-group">
                                    <input type="number" min="1" class="form-control" name="min_purchase_per_user" 
                                           value="<?php echo htmlspecialchars($product['min_purchase_per_user']); ?>" required>
                                    <span class="input-group-text">每用户</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 最高购买数量 -->
                        <div class="row mb-3">
                            <label class="col-md-2 col-form-label">最高购买数量</label>
                            <div class="col-md-10">
                                <div class="input-group">
                                    <input type="number" min="1" class="form-control" name="max_purchase_per_user" 
                                           value="<?php echo htmlspecialchars($product['max_purchase_per_user'] ?? ''); ?>">
                                    <span class="input-group-text">留空表示不限制</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 商品状态 -->
                        <div class="row mb-3">
                            <label class="col-md-2 col-form-label">商品状态</label>
                            <div class="col-md-10">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="status" name="status" value="1" 
                                        <?= $product['status'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="status">上架</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 表单操作按钮 -->
            <div class="d-flex justify-content-end gap-3 mt-4">
                <a href="products.php" class="btn btn-secondary btn-action">取消</a>
                <button type="reset" class="btn btn-warning btn-action">重置</button>
                <button type="submit" class="btn btn-primary btn-action">保存修改</button>
            </div>
        </form>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- 自定义JavaScript -->
    <script>
    // 动态加载二级分类
    document.getElementById('parent_category').addEventListener('change', function() {
        const parentId = this.value;
        const subCategorySelect = document.getElementById('category_id');
        
        if (parentId > 0) {
            subCategorySelect.innerHTML = '<option value="">加载中...</option>';
            
            fetch('get_subcategories.php?parent_id=' + parentId)
                .then(response => response.json())
                .then(data => {
                    subCategorySelect.innerHTML = '<option value="">请选择二级分类</option>';
                    
                    if (data.error) {
                        console.error(data.error);
                        subCategorySelect.innerHTML = '<option value="">加载分类失败</option>';
                    } else if (data.length === 0) {
                        subCategorySelect.innerHTML = '<option value="">该分类下无二级分类</option>';
                    } else {
                        data.forEach(category => {
                            const option = document.createElement('option');
                            option.value = category.id;
                            option.textContent = category.name;
                            subCategorySelect.appendChild(option);
                        });
                        
                        // 恢复之前选中的值
                        const selectedCategoryId = <?= $product['category_id'] ?? 0 ?>;
                        if (selectedCategoryId > 0) {
                            subCategorySelect.value = selectedCategoryId;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    subCategorySelect.innerHTML = '<option value="">加载分类失败</option>';
                });
        } else {
            subCategorySelect.innerHTML = '<option value="">请先选择一级分类</option>';
        }
    });

    // 添加商品标签
    document.getElementById('addTagBtn').addEventListener('click', function() {
        const container = document.getElementById('tagsContainer');
        const newField = document.createElement('div');
        newField.className = 'input-group mb-2 tag-item';
        newField.innerHTML = `
            <input type="text" class="form-control" name="tags[]">
            <button type="button" class="btn btn-outline-danger remove-tag">删除</button>
        `;
        container.appendChild(newField);
    });

    // 删除商品标签
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-tag')) {
            const tagGroup = e.target.closest('.tag-item');
            if (document.querySelectorAll('#tagsContainer .tag-item').length > 1) {
                tagGroup.remove();
            } else {
                tagGroup.querySelector('input').value = '';
            }
        }
    });

    // 图片预览功能
    document.querySelector('input[name="images[]"]').addEventListener('change', function(e) {
        const previewContainer = document.getElementById('imagePreview');
        if (!previewContainer) return;
        
        previewContainer.innerHTML = '';
        
        if (this.files) {
            const files = Array.from(this.files).slice(0, 5); // 最多显示5张预览
            
            files.forEach(file => {
                if (file.type.match('image.*')) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const preview = document.createElement('div');
                        preview.className = 'image-preview-item';
                        preview.style.backgroundImage = `url(${e.target.result})`;
                        previewContainer.appendChild(preview);
                    }
                    
                    reader.readAsDataURL(file);
                }
            });
        }
    });

    // 删除图片
    function removeImage(index) {
        const previewContainer = document.getElementById('imagePreview');
        if (!previewContainer) return;
        
        const previewItems = previewContainer.querySelectorAll('.image-preview-item');
        if (previewItems[index]) {
            previewItems[index].remove();
            
            // 发送AJAX请求删除服务器上的图片
            const imagePath = <?= json_encode($product['images'] ?? []) ?>[index];
            if (imagePath) {
                fetch('delete_image.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `image_path=${encodeURIComponent(imagePath)}`
                }).then(response => {
                    if (!response.ok) {
                        console.error('删除图片失败');
                    }
                });
            }
        }
    }

    // 当游戏大区选择变化时更新区域价格字段
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('game-region-checkbox')) {
            updateRegionPriceFields();
        }
    });

    // 初始化区域价格字段
    function updateRegionPriceFields() {
        const container = document.getElementById('regionPricesContainer');
        const checkboxes = document.querySelectorAll('.game-region-checkbox:checked');
        
        container.innerHTML = '';
        
        checkboxes.forEach(checkbox => {
            const region = checkbox.value;
            const regionKey = md5(region);
            
            const priceRow = document.createElement('div');
            priceRow.className = 'region-price-item';
            priceRow.innerHTML = `
                <div class="region-price-label">${region}</div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="input-group mb-3">
                            <span class="input-group-text">价格</span>
                            <input type="number" step="0.01" min="0" class="form-control" 
                                   name="region_price_${regionKey}" 
                                   placeholder="默认价格">
                            <span class="input-group-text">¥</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="input-group mb-3">
                            <span class="input-group-text">原价</span>
                            <input type="number" step="0.01" min="0" class="form-control" 
                                   name="region_original_price_${regionKey}" 
                                   placeholder="默认原价">
                            <span class="input-group-text">¥</span>
                        </div>
                    </div>
                </div>
            `;
            
            container.appendChild(priceRow);
        });
    }

    // 简单的MD5函数用于生成唯一键
    function md5(str) {
        return str.split('').reduce((prevHash, currVal) =>
            (((prevHash << 5) - prevHash) + currVal.charCodeAt(0))|0, 0);
    }

    // 表单验证
    document.querySelector('form').addEventListener('submit', function(e) {
        let isValid = true;
        const errorMessages = [];
        
        // 验证价格
        const price = parseFloat(document.querySelector('input[name="price"]').value);
        if (price <= 0) {
            errorMessages.push('商品价格必须大于0');
            isValid = false;
        }
        
        // 验证库存
        const stock = parseInt(document.querySelector('input[name="stock"]').value);
        if (stock < 0) {
            errorMessages.push('库存不能为负数');
            isValid = false;
        }
        
        // 验证最低购买数量
        const minPurchase = parseInt(document.querySelector('input[name="min_purchase_per_user"]').value);
        if (minPurchase <= 0) {
            errorMessages.push('最低购买数量必须大于0');
            isValid = false;
        }
        
        // 验证游戏大区选项
        const gameRegions = document.querySelectorAll('input[name="game_region[]"]:checked');
        if (gameRegions.length === 0) {
            errorMessages.push('请至少选择一个游戏大区');
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            
            // 显示所有错误信息
            const errorHtml = errorMessages.map(msg => `<li>${msg}</li>`).join('');
            const errorAlert = `
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>表单验证失败!</strong>
                    <ul class="mb-0">${errorHtml}</ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            
            // 移除旧的错误提示
            const oldAlert = document.querySelector('.alert.alert-danger');
            if (oldAlert) {
                oldAlert.remove();
            }
            
            // 添加新的错误提示
            document.querySelector('.admin-container').insertAdjacentHTML('afterbegin', errorAlert);
            
            // 滚动到顶部
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });
    
    // 页面加载完成后初始化
    document.addEventListener('DOMContentLoaded', function() {
        updateRegionPriceFields();
    });
    </script>
</body>
</html>