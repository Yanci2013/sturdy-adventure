<?php
// 开启输出缓冲
ob_start();

// 引入数据库连接文件
require_once __DIR__.'/../../includes/session_manager.php';
require_once __DIR__.'/../../includes/common.php';
require_once __DIR__.'/../../includes/log_helper.php';

// 检查并创建图片目录 - 使用绝对路径
$uploadBaseDir = dirname(__DIR__, 2) . '/assets/images/products/';
if (!file_exists($uploadBaseDir)) {
    if (!mkdir($uploadBaseDir, 0755, true)) {
        die("无法创建图片目录，请检查权限");
    }
}
if (!is_writable($uploadBaseDir)) {
    die("图片目录不可写，请检查权限");
}

// 数据库连接
$conn = new PDO(
    "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", 
    DB_USER, 
    DB_PASS, 
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

// 自动修复product_categories表结构
// 函数定义已移至includes/common.php，此处直接调用
autoFixTableStructure($conn);

// 查询分类数据
$parent_categories = $conn->query("SELECT id, name FROM product_categories WHERE parent_id = 0 AND level = 1 AND status = 1 ORDER BY sort_order DESC, name ASC")->fetchAll();
$sub_categories = $conn->query("SELECT id, parent_id, name FROM product_categories WHERE parent_id > 0 AND level = 2 AND status = 1 ORDER BY sort_order DESC, name ASC")->fetchAll();

// 将二级分类按父分类ID分组
$sub_categories_grouped = [];
foreach ($sub_categories as $category) {
    $sub_categories_grouped[$category['parent_id']][] = $category;
}

// 获取游戏大区数据
$game_regions = $conn->query("SELECT id, name FROM game_regions WHERE status = 1 ORDER BY sort_order DESC, id ASC")->fetchAll();

// 获取所有抽奖奖品（用于商品关联）
$all_lottery_prizes = [];
try {
    $all_lottery_prizes = $conn->query("SELECT id, name, type FROM lottery_prizes WHERE status = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();
} catch (Exception $e) {
    $all_lottery_prizes = [];
}

// 如果没有自定义大区，使用默认值
if (empty($game_regions)) {
    $game_regions = [
        ['id' => 1, 'name' => 'QQ区'],
        ['id' => 2, 'name' => '微信区'],
        ['id' => 3, 'name' => 'TapTap专区'],
        ['id' => 4, 'name' => '端游专区']
    ];
}

// 获取当前选中的一级分类ID
$selected_parent_id = isset($_POST['parent_category_id']) ? (int)$_POST['parent_category_id'] : 0;

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证CSRF令牌
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF令牌验证失败");
    }

    try {
        $conn->beginTransaction();

        // 获取并清理输入数据
        $parent_category_id = isset($_POST['parent_category_id']) ? (int)$_POST['parent_category_id'] : 0;
        $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $chinese_name = trim($_POST['chinese_name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $original_price = !empty($_POST['original_price']) ? floatval($_POST['original_price']) : null;
        $shipment_quantity = intval($_POST['shipment_quantity'] ?? 1);
        $stock = intval($_POST['stock'] ?? 0);
        $unit = trim($_POST['unit'] ?? '单');
        $max_purchase_per_user = !empty($_POST['max_purchase_per_user']) ? intval($_POST['max_purchase_per_user']) : null;
        $min_purchase_per_user = intval($_POST['min_purchase_per_user'] ?? 1);
        $description = $_POST['description'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $default_input = trim($_POST['default_input'] ?? '');
        $sort_order = !empty($_POST['sort_order']) ? intval($_POST['sort_order']) : null;
        $virtual_sales = intval($_POST['virtual_sales'] ?? 0);
        
        // 处理大转盘设置
        $wheel_prize = null;
        if (isset($_POST['enable_wheel']) && $_POST['enable_wheel'] == '1') {
            $prizes = [];
            for ($i = 1; $i <= 6; $i++) {
                if (!empty($_POST["wheel_prize_name_$i"]) && !empty($_POST["wheel_prize_probability_$i"])) {
                    $prizes[] = [
                        'name' => trim($_POST["wheel_prize_name_$i"]),
                        'probability' => floatval($_POST["wheel_prize_probability_$i"])
                    ];
                }
            }
            if (!empty($prizes)) {
                $wheel_prize = json_encode(['prizes' => $prizes], JSON_UNESCAPED_UNICODE);
            }
        }
        
        // 处理涨价设置
        $price_increase = null;
        if (!empty($_POST['increased_price']) && !empty($_POST['increase_reason'])) {
            $price_increase = json_encode([
                'price' => floatval($_POST['increased_price']),
                'reason' => trim($_POST['increase_reason'])
            ], JSON_UNESCAPED_UNICODE);
        }

        // 验证必填字段
        $required_fields = [
            'parent_category_id' => '请选择有效的一级分类',
            'category_id' => '请选择有效的二级分类',
            'name' => '商品名称不能为空',
            'price' => '商品价格必须大于0',
            'shipment_quantity' => '每份发货数量必须大于0',
            'stock' => '库存不能为负数',
            'min_purchase_per_user' => '最低购买数量必须大于0'
        ];
        
        foreach ($required_fields as $field => $error) {
            if (empty($$field) || (is_numeric($$field) && $$field <= 0 && $field !== 'original_price')) {
                throw new Exception($error);
            }
        }
        
        // 验证描述内容
        if (empty($description) || $description === '<p></p>') {
            throw new Exception('商品说明不能为空');
        }
        
        // 验证二级分类是否属于一级分类
        $stmt = $conn->prepare("SELECT parent_id, level FROM product_categories WHERE id = ?");
        $stmt->execute([$category_id]);
        $category = $stmt->fetch();
        
        if (!$category || $category['parent_id'] != $parent_category_id || $category['level'] != 2) {
            throw new Exception("选择的二级分类不属于该一级分类");
        }
        
        // 处理图片上传
        $images = [];
        
        // 处理文件上传的图片
        if (!empty($_FILES['images']['name'][0])) {
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
            $maxFileSize = 5 * 1024 * 1024; // 5MB
            
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) {
                    throw new Exception("文件上传错误: " . $_FILES['images']['error'][$key]);
                }
                
                // 验证文件大小
                if ($_FILES['images']['size'][$key] > $maxFileSize) {
                    throw new Exception("文件大小不能超过5MB");
                }
                
                $fileName = basename($_FILES['images']['name'][$key]);
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                if (!in_array($fileExt, $allowedExts)) {
                    throw new Exception("只允许上传JPG, PNG, GIF格式的图片");
                }
                
                // 生成唯一文件名
                $newFileName = 'product_' . md5(uniqid() . $fileName) . '.' . $fileExt;
                $uploadPath = $uploadBaseDir . $newFileName;
                
                if (!move_uploaded_file($tmp_name, $uploadPath)) {
                    throw new Exception("无法移动上传的文件");
                }
                
                // 检查文件是否确实存在
                if (!file_exists($uploadPath)) {
                    throw new Exception("上传的文件不存在");
                }
                
                $images[] = '/assets/images/products/' . $newFileName;
            }
        }
        
        if (empty($images)) {
            throw new Exception("请至少上传一张商品图片");
        }

        // 处理主图上传
        $main_image = '';
        if (!empty($_FILES['main_image']['name'])) {
            $fileName = basename($_FILES['main_image']['name']);
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            if (in_array($fileExt, $allowedExts)) {
                $newFileName = 'main_' . md5(uniqid() . $fileName) . '.' . $fileExt;
                $uploadPath = $uploadBaseDir . $newFileName;
                
                if (!move_uploaded_file($_FILES['main_image']['tmp_name'], $uploadPath)) {
                    throw new Exception("无法移动上传的主图文件");
                }
                
                $main_image = '/assets/images/products/' . $newFileName;
            }
        }
        
        // 处理游戏大区选项和价格
        $game_regions = [];
        $region_prices = [];
        
        if (isset($_POST['game_region']) && is_array($_POST['game_region'])) {
            $game_regions = array_map('trim', $_POST['game_region']);
            
            // 处理区域价格
            foreach ($game_regions as $region) {
                $region_key = 'region_price_' . md5($region);
                $region_original_key = 'region_original_price_' . md5($region);
                
                $region_price = isset($_POST[$region_key]) ? floatval($_POST[$region_key]) : $price;
                $region_original_price = isset($_POST[$region_original_key]) ? floatval($_POST[$region_original_key]) : $original_price;
                
                $region_prices[$region] = [
                    'price' => $region_price,
                    'original_price' => $region_original_price
                ];
            }
        }
        
        if (empty($game_regions)) {
            throw new Exception("请至少选择一个游戏大区");
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
        
        // 插入商品数据
        $is_lottery = isset($_POST['is_lottery']) ? 1 : 0;
        $sql = "INSERT INTO products (
            category_id, name, chinese_name, images, original_price, price, 
            shipment_quantity, stock, unit, max_purchase_per_user, min_purchase_per_user, 
            description, notes, input_fields, default_input, game_region_options, 
            sort_order, main_image, virtual_sales, region_prices, 
            wheel_prize, price_increase, is_lottery, status, created_at
        ) VALUES (
            :category_id, :name, :chinese_name, :images, :original_price, :price, 
            :shipment_quantity, :stock, :unit, :max_purchase, :min_purchase, 
            :description, :notes, :input_fields, :default_input, :game_regions, 
            :sort_order, :main_image, :virtual_sales, :region_prices, 
            :wheel_prize, :price_increase, :is_lottery, 1, NOW()
        )";
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            ':category_id' => $category_id,
            ':name' => $name,
            ':chinese_name' => $chinese_name,
            ':images' => json_encode($images, JSON_UNESCAPED_UNICODE),
            ':original_price' => $original_price,
            ':price' => $price,
            ':shipment_quantity' => $shipment_quantity,
            ':stock' => $stock,
            ':unit' => $unit,
            ':max_purchase' => $max_purchase_per_user,
            ':min_purchase' => $min_purchase_per_user,
            ':description' => $description,
            ':notes' => $notes,
            ':input_fields' => json_encode($input_fields, JSON_UNESCAPED_UNICODE),
            ':default_input' => $default_input,
            ':game_regions' => json_encode($game_regions, JSON_UNESCAPED_UNICODE),
            ':sort_order' => $sort_order,
            ':main_image' => $main_image,
            ':virtual_sales' => $virtual_sales,
            ':region_prices' => json_encode($region_prices, JSON_UNESCAPED_UNICODE),
            ':wheel_prize' => $wheel_prize,
            ':price_increase' => $price_increase,
            ':is_lottery' => $is_lottery
        ]);
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception("商品创建失败: " . $errorInfo[2]);
        }
        
        $product_id = $conn->lastInsertId();
        
        // 关联抽奖奖品
        if ($is_lottery && !empty($_POST['lottery_prize_ids'])) {
            $prize_ids = $_POST['lottery_prize_ids'];
            if (!is_array($prize_ids)) {
                $prize_ids = [$prize_ids];
            }
            foreach ($prize_ids as $pid) {
                $pid = intval($pid);
                if ($pid > 0) {
                    $conn->prepare("UPDATE lottery_prizes SET product_id = ? WHERE id = ?")->execute([$product_id, $pid]);
                }
            }
        }
        
        // 记录管理员操作日志
        $admin_id = $_SESSION['admin_id'] ?? null;
        $admin_name = $_SESSION['admin_username'] ?? '未知管理员';
        $operation_details = "创建商品：ID=" . $product_id . ", 名称=" . $name . ", 价格=" . $price . "元";
        log_admin_operation($pdo, $admin_id, $admin_name, 'create_product', $operation_details);
        
        // 提交事务
        $conn->commit();
        
        // 重定向到商品列表页
        $_SESSION['success_message'] = "商品创建成功！";
        header("Location: products.php");
        exit;
        
    } catch (PDOException $e) {
        $conn->rollBack();
        $error = "数据库错误: " . $e->getMessage();
        error_log($error);
    } catch (Exception $e) {
        $conn->rollBack();
        $error = $e->getMessage();
        error_log("商品创建错误: " . $error);
    }
}

// 生成CSRF令牌
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>创建商品 - 俱乐部</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- ckeditor5 CSS -->
    <script src="https://cdn.ckeditor.com/ckeditor5/40.1.0/classic/ckeditor.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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

        .btn-lg {
            padding: 12px 24px;
            font-size: 14px;
        }

        .me-3 {
            margin-right: 12px;
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

        .alert-error {
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

        .form-check {
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 8px 0;
        }

        .form-check-input {
            width: 16px;
            height: 16px;
        }

        .form-check-label {
            font-size: 13px;
            color: #333;
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
            border-bottom: 1px solid #f0f0f0;
        }

        tr:hover {
            background: #f9f9f9;
        }

        .table-actions {
            display: flex;
            gap: 8px;
        }

        .status-badge {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 500;
        }

        .status-active {
            background: #f0fdf4;
            color: #16a34a;
        }

        .status-inactive {
            background: #fef2f2;
            color: #b91c1c;
        }

        .empty {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .empty div:first-child {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .empty div:nth-child(2) {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .pagination-container {
            margin-top: 24px;
            text-align: center;
        }

        .pagination {
            display: inline-flex;
            list-style: none;
            gap: 4px;
        }

        .pagination li {
            display: inline;
        }

        .page-link {
            display: block;
            padding: 6px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff;
            color: #333;
            text-decoration: none;
            font-size: 12px;
        }

        .page-link:hover {
            background: #f0f0f0;
        }

        .page-link.active {
            background: #1E9FFF;
            color: white;
            border-color: #1E9FFF;
        }

        .btn-group {
            margin-bottom: 16px;
            display: flex;
            gap: 8px;
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

        /* 表单行样式 */
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

        .col-form-label {
            margin-bottom: 6px;
            display: block;
        }

        .required-field::after {
            content: ' *';
            color: #ff4d4f;
        }

        .text-muted {
            font-size: 12px;
            color: #999;
            margin-top: 4px;
        }

        /* 输入组样式 */
        .input-group {
            display: flex;
            align-items: center;
        }

        .input-group .form-control {
            flex: 1;
            border-right: none;
            border-radius: 4px 0 0 4px;
        }

        .input-group-text {
            padding: 8px 12px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-left: none;
            border-radius: 0 4px 4px 0;
            font-size: 12px;
            color: #666;
        }

        /* 开关样式 (用于大转盘等) */
        .form-switch {
            display: flex;
            align-items: center;
        }

        .form-switch .form-check-input {
            width: 52px;
            height: 26px;
            border: 2px solid var(--primary-yellow);
            background: var(--primary-bg);
            appearance: none;
            cursor: pointer;
            position: relative;
            border-radius: 30px;
            margin-right: 14px;
        }

        .form-switch .form-check-input:checked {
            background: var(--primary-yellow);
            border-color: var(--primary-yellow-dark);
        }

        .form-switch .form-check-input::before {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            background: white;
            top: 1px;
            left: 1px;
            border-radius: 50%;
            transition: left 0.25s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .form-switch .form-check-input:checked::before {
            left: 27px;
        }

        /* 图片预览 */
        .image-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 18px;
        }

        .image-preview-item {
            width: 90px;
            height: 90px;
            border: 2px solid var(--border-color);
            background-size: cover;
            background-position: center;
            position: relative;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }

        .image-preview-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .remove-image {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 26px;
            height: 26px;
            background: var(--card-bg);
            border: 2px solid var(--primary-yellow);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--primary-yellow-dark);
            font-size: 14px;
            transition: all 0.15s;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }

        .remove-image:hover {
            background: var(--primary-yellow);
            color: #fff;
        }



        /* 图片URL预览区域 */
        .image-url-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 16px;
        }

        .image-url-preview img {
            width: 80px;
            height: 80px;
            border: 2px solid var(--border-color);
            object-fit: cover;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .image-url-input-container {
            margin-top: 24px;
            padding: 22px;
            background: var(--primary-bg);
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        /* 标签容器 */
        #tagsContainer .input-group {
            margin-bottom: 12px;
        }

        /* 区域价格行 */
        .region-price-row {
            margin-bottom: 20px;
            padding-bottom: 18px;
            border-bottom: 2px dashed var(--border-color);
        }
        .region-price-row:last-child {
            border-bottom: none;
        }

        /* 大转盘奖品行 */
        .wheel-prize-row {
            margin-bottom: 18px;
            padding: 20px;
            background: var(--primary-bg);
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        /* 消息提示 */
        .alert {
            padding: 18px 22px;
            border: none;
            margin-bottom: 25px;
            font-size: 15px;
            position: relative;
            border-radius: 16px;
            background: var(--card-bg);
            color: var(--text-primary);
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .alert-danger {
            background: linear-gradient(135deg, #fff5f5 0%, #ffe0e0 100%);
            border-left: 4px solid #ff6b6b;
        }

        .alert i {
            color: var(--primary-yellow-dark);
            margin-right: 12px;
            font-size: 18px;
        }

        .alert ul {
            margin: 10px 0 0 24px;
            color: var(--text-primary);
        }

        .btn-close {
            position: absolute;
            top: 18px;
            right: 20px;
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: var(--text-tertiary);
            transition: color 0.2s;
        }

        .btn-close:hover {
            color: var(--primary-yellow-dark);
        }

        /* 编辑器容器 */
        #ckeditor-container {
            border: 1px solid var(--border-color);
            min-height: 320px;
            border-radius: 16px;
            overflow: hidden;
            background: var(--card-bg);
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .editor-error {
            border: 2px solid #ff6b6b;
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.2);
        }

        /* 响应式设计 */
        @media screen and (max-width: 992px) {
            .col-md-2,
            .col-md-4,
            .col-md-5,
            .col-md-6,
            .col-md-10 {
                flex: 0 0 100%;
                max-width: 100%;
                padding: 0;
            }

            .col-md-2 {
                margin-bottom: 10px;
            }
        }

        @media screen and (max-width: 768px) {
            body { padding: 16px; }
            h2 { font-size: 24px; }
            .nav-link { padding: 10px 18px; }
            .tab-content { padding: 20px; }
        }

        @media screen and (max-width: 480px) {
            .btn-group { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
  <link href="../res/admin-white.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h2>✨ 创建新商品</h2>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="" enctype="multipart/form-data" id="productForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <ul class="nav nav-tabs" id="productTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab">基本信息</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="price-tab" data-bs-toggle="tab" data-bs-target="#price" type="button" role="tab">价格库存</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="restriction-tab" data-bs-toggle="tab" data-bs-target="#restriction" type="button" role="tab">购买限制</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="description-tab" data-bs-toggle="tab" data-bs-target="#description" type="button" role="tab">商品描述</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="region-tab" data-bs-toggle="tab" data-bs-target="#region" type="button" role="tab">游戏大区</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="lottery-tab" data-bs-toggle="tab" data-bs-target="#lottery" type="button" role="tab">抽奖设置</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="price-increase-tab" data-bs-toggle="tab" data-bs-target="#price-increase" type="button" role="tab">涨价设置</button>
                </li>
            </ul>
            
            <div class="tab-content" id="productTabsContent">
                <!-- 基本信息部分 -->
                <div class="tab-pane fade show active" id="basic" role="tabpanel">
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
                                            <?= (isset($_POST['category_id']) && $_POST['category_id'] == $sub_cat['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sub_cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- 商品名称 -->
                    <div class="row mb-3">
                        <label class="col-md-2 col-form-label required-field">商品名称【简介】</label>
                        <div class="col-md-10">
                            <input type="text" class="form-control" name="name" value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>" required>
                        </div>
                    </div>
                    
                    <!-- 中文名称 -->
                    <div class="row mb-3">
                        <label class="col-md-2 col-form-label">商品名称【完整】</label>
                        <div class="col-md-10">
                            <input type="text" class="form-control" name="chinese_name" value="<?= isset($_POST['chinese_name']) ? htmlspecialchars($_POST['chinese_name']) : '' ?>">
                        </div>
                    </div>
                    
                    <!-- 商品图片 -->
                    <div class="row mb-3">
                        <label class="col-md-2 col-form-label required-field">商品图片</label>
                        <div class="col-md-10">
                            <input type="file" class="form-control" id="productImages" name="images[]" multiple accept="image/*" required>
                            <span class="text-muted">可上传1-5张图片，建议尺寸：800x800像素，支持JPG, PNG格式</span>
                            <div class="image-preview mt-3" id="imagePreview"></div>
                        </div>
                    </div>
                    
                    <!-- 商品主图 -->
                    <div class="row mb-3">
                        <label class="col-md-2 col-form-label">商品主图</label>
                        <div class="col-md-10">
                            <input type="file" class="form-control" name="main_image" accept="image/*">
                            <span class="text-muted">用于商品列表展示的主图</span>
                        </div>
                    </div>
                </div>
                
                <!-- 价格库存部分 -->
                <div class="tab-pane fade" id="price" role="tabpanel">
                    <h5>价格库存</h5>
                    
                    <div class="row mb-3">
                        <label class="col-md-2 col-form-label">商品原价</label>
                        <div class="col-md-10">
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" class="form-control" name="original_price" value="<?= isset($_POST['original_price']) ? htmlspecialchars($_POST['original_price']) : '' ?>">
                                <span class="input-group-text">元</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label class="col-md-2 col-form-label required-field">商品现价</label>
                        <div class="col-md-10">
                            <div class="input-group">
                                <input type="number" step="0.01" min="0.01" class="form-control" name="price" value="<?= isset($_POST['price']) ? htmlspecialchars($_POST['price']) : '' ?>" required>
                                <span class="input-group-text">元</span>
                            </div>
                            <span class="text-muted">默认价格，如果未设置区域价格则使用此价格</span>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label class="col-md-2 col-form-label required-field">每份发货数量</label>
                        <div class="col-md-10">
                            <div class="input-group">
                                <input type="number" min="1" class="form-control" name="shipment_quantity" value="<?= isset($_POST['shipment_quantity']) ? htmlspecialchars($_POST['shipment_quantity']) : '1' ?>" required>
                                <span class="input-group-text">件/单</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label class="col-md-2 col-form-label required-field">商品库存</label>
                        <div class="col-md-10">
                            <div class="input-group">
                                <input type="number" min="0" class="form-control" name="stock" value="<?= isset($_POST['stock']) ? htmlspecialchars($_POST['stock']) : '0' ?>" required>
                                <span class="input-group-text">件</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label class="col-md-2 col-form-label">虚拟销量</label>
                        <div class="col-md-10">
                            <div class="input-group">
                                <input type="number" min="0" class="form-control" name="virtual_sales" value="<?= isset($_POST['virtual_sales']) ? htmlspecialchars($_POST['virtual_sales']) : '0' ?>">
                                <span class="input-group-text">件</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <label class="col-md-2 required-field">数量单位</label>
                        <div class="col-md-10">
                            <input type="text" class="form-control" name="unit" value="<?= isset($_POST['unit']) ? htmlspecialchars($_POST['unit']) : '单' ?>" required>
                            <span class="text-muted">如：单、小时、把等</span>
                        </div>
                    </div>
                    
                    <div class="row">
                        <label class="col-md-2">排序权重</label>
                        <div class="col-md-10">
                            <input type="number" class="form-control" name="sort_order" value="<?= isset($_POST['sort_order']) ? htmlspecialchars($_POST['sort_order']) : '' ?>">
                            <span class="text-muted">数字越大排序越靠前</span>
                        </div>
                    </div>
                </div>
                
                <!-- 购买限制部分 -->
                <div class="tab-pane fade" id="restriction" role="tabpanel">
                    <h5>购买限制</h5>
                    
                    <div class="row mb-3">
                        <label class="col-md-2 col-form-label required-field">单用户最低购买</label>
                        <div class="col-md-10">
                            <div class="input-group">
                                <input type="number" min="1" class="form-control" name="min_purchase_per_user" value="<?= isset($_POST['min_purchase_per_user']) ? htmlspecialchars($_POST['min_purchase_per_user']) : '1' ?>" required>
                                <span class="input-group-text">件/单</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label class="col-md-2 col-form-label">单用户最多购买</label>
                        <div class="col-md-10">
                            <div class="input-group">
                                <input type="number" min="1" class="form-control" name="max_purchase_per_user" value="<?= isset($_POST['max_purchase_per_user']) ? htmlspecialchars($_POST['max_purchase_per_user']) : '' ?>">
                                <span class="input-group-text">留空表示不限制</span>
                            </div>
                        </div>
                    </div>
                    
                </div>
                
                <!-- 商品描述部分 -->
                <div class="tab-pane fade" id="description" role="tabpanel">
                    <h5>商品描述</h5>
                    
                    <div class="row mb-3">
                        <label class="col-md-2 col-form-label required-field">商品说明</label>
                        <div class="col-md-10">
                            <textarea id="ckeditor" name="description" style="display:none;">
                                <?= isset($_POST['description']) ? htmlspecialchars_decode($_POST['description']) : '' ?>
                            </textarea>
                            <div id="ckeditor-container" style="border: 1px solid #faebcc; min-height: 300px;"></div>
                            <input type="hidden" id="descriptionValidation" name="description_validation" value="1">
                            
                            <div class="image-url-input-container">
                                <label>插入图片URL（多个URL用逗号分隔）</label>
                                <input type="text" id="imageUrlsInput" class="form-control" placeholder="例如: https://example.com/image1.jpg,https://example.com/image2.jpg">
                                <div class="row">
                                    <div class="col-md-4">
                                        <label>宽度（可选）</label>
                                        <input type="number" id="imageWidthInput" class="form-control" placeholder="宽度（像素）" min="0">
                                    </div>
                                    <div class="col-md-4">
                                        <label>高度（可选）</label>
                                        <input type="number" id="imageHeightInput" class="form-control" placeholder="高度（像素）" min="0">
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" id="insertImageUrlsBtn" class="btn" style="width: 100%;">插入图片</button>
                                    </div>
                                </div>
                                <div class="image-url-preview" id="imageUrlPreview"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <label class="col-md-2">购买注意事项</label>
                        <div class="col-md-10">
                            <textarea class="form-control" name="notes" rows="5"><?= isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : '' ?></textarea>
                        </div>
                    </div>
                    
                    <div class="row">
                        <label class="col-md-2">默认输入内容</label>
                        <div class="col-md-10">
                            <input type="text" class="form-control" name="default_input" value="<?= isset($_POST['default_input']) ? htmlspecialchars($_POST['default_input']) : '' ?>">
                        </div>
                    </div>
                    
                </div>

                <!-- 游戏大区选项部分 -->
                <div class="tab-pane fade" id="region" role="tabpanel">
                    <h5>游戏大区</h5>
                    
                    <div class="row mb-3">
                        <label class="col-md-2 col-form-label">大区选项</label>
                        <div class="col-md-10">
                            <?php foreach ($game_regions as $region): ?>
                                <div class="form-check">
                                    <input class="form-check-input game-region-checkbox" type="checkbox" name="game_region[]" id="region_<?php echo $region['id']; ?>" value="<?php echo htmlspecialchars($region['name']); ?>">
                                    <label class="form-check-label" for="region_<?php echo $region['id']; ?>"><?php echo htmlspecialchars($region['name']); ?></label>
                                </div>
                            <?php endforeach; ?>
                            <span class="text-muted">设置用户可选择的游戏大区</span>
                        </div>
                    </div>
                    
                    <div id="regionPricesContainer"></div>
                </div>
                
                <!-- 大转盘设置部分 (注释掉) -->
                <!--<div class="tab-pane" id="wheel">-->
                <!--    <div class="row">-->
                <!--        <div class="col-md-12">-->
                <!--            <div class="form-switch">-->
                <!--                <input class="form-check-input" type="checkbox" id="enableWheel" name="enable_wheel" value="1" <?= isset($_POST['enable_wheel']) && $_POST['enable_wheel'] == '1' ? 'checked' : '' ?>>-->
                <!--                <label class="form-check-label" for="enableWheel">启用大转盘</label>-->
                <!--            </div>-->
                <!--        </div>-->
                <!--    </div>-->
                    
                <!--    <div id="wheelPrizesContainer">-->
                        <!-- 动态生成最多6个奖品输入框 -->
                <!--        <?php for ($i = 1; $i <= 6; $i++): ?>-->
                <!--            <div class="wheel-prize-row" id="wheelPrizeRow<?= $i ?>" style="display: <?= (isset($_POST["wheel_prize_name_$i"]) && !empty($_POST["wheel_prize_name_$i"])) ? 'block' : 'none' ?>;">-->
                <!--                <div class="row">-->
                <!--                    <div class="col-md-5">-->
                <!--                        <label>奖品名称 <?= $i ?></label>-->
                <!--                        <input type="text" class="form-control" name="wheel_prize_name_<?= $i ?>" value="<?= isset($_POST["wheel_prize_name_$i"]) ? htmlspecialchars($_POST["wheel_prize_name_$i"]) : '' ?>">-->
                <!--                    </div>-->
                <!--                    <div class="col-md-5">-->
                <!--                        <label>中奖概率 <?= $i ?> (0-1之间)</label>-->
                <!--                        <input type="number" step="0.01" min="0" max="1" class="form-control" name="wheel_prize_probability_<?= $i ?>" value="<?= isset($_POST["wheel_prize_probability_$i"]) ? htmlspecialchars($_POST["wheel_prize_probability_$i"]) : '' ?>">-->
                <!--                    </div>-->
                <!--                    <div class="col-md-2">-->
                <!--                        <button type="button" class="btn remove-wheel-prize" data-index="<?= $i ?>" style="margin-top: 24px;">-->
                <!--                            <i class="fas fa-times"></i>删除-->
                <!--                        </button>-->
                <!--                    </div>-->
                <!--                </div>-->
                <!--            </div>-->
                <!--        <?php endfor; ?>-->
                <!--    </div>-->
                    
                <!--    <div class="row">-->
                <!--        <div class="col-md-12">-->
                <!--            <button type="button" class="btn" id="addWheelPrizeBtn" style="display: <?= (isset($_POST['enable_wheel']) && $_POST['enable_wheel'] == '1') ? 'block' : 'none' ?>;">-->
                <!--                <i class="fas fa-plus"></i>添加奖品-->
                <!--            </button>-->
                <!--            <span class="text-muted d-block mt-2">最多可设置6个奖品，所有奖品的概率总和应为1</span>-->
                <!--        </div>-->
                <!--    </div>-->
                <!--</div>-->
                
                <!-- 抽奖设置部分 -->
                <div class="tab-pane fade" id="lottery" role="tabpanel">
                    <h5>抽奖设置</h5>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="isLottery" name="is_lottery" value="1" <?= isset($_POST['is_lottery']) && $_POST['is_lottery'] == '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isLottery">设为抽奖商品（用户下单后可参与大转盘抽奖）</label>
                            </div>
                        </div>
                    </div>
                    <div id="lotteryPrizesSection" style="display: <?= isset($_POST['is_lottery']) && $_POST['is_lottery'] == '1' ? 'block' : 'none' ?>;">
                        <h6>选择可抽取的奖品</h6>
                        <p class="text-muted">勾选该商品下单后可抽到的奖品，可多选。奖品在"后台-抽奖奖品管理"中添加。</p>
                        <?php if (empty($all_lottery_prizes)): ?>
                        <div class="alert alert-warning">暂无奖品，请先到 <a href="../seetings/lottery_prizes.php">抽奖奖品管理</a> 添加奖品。</div>
                        <?php else: ?>
                        <div class="row">
                            <?php 
                            $selected_prizes = isset($_POST['lottery_prize_ids']) ? $_POST['lottery_prize_ids'] : [];
                            if (!is_array($selected_prizes)) { $selected_prizes = [$selected_prizes]; }
                            foreach ($all_lottery_prizes as $prize): 
                            ?>
                            <div class="col-md-4 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="lottery_prize_ids[]" value="<?= $prize['id'] ?>" id="prize_<?= $prize['id'] ?>" <?= in_array($prize['id'], $selected_prizes) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="prize_<?= $prize['id'] ?>">
                                        <?= htmlspecialchars($prize['name']) ?>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($prize['type'] ?? '奖品') ?></span>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- 涨价设置部分 -->
                <div class="tab-pane fade" id="price-increase" role="tabpanel">
                    <h5>涨价设置</h5>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="col-form-label">涨价后价格</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" class="form-control" name="increased_price" value="<?= isset($_POST['increased_price']) ? htmlspecialchars($_POST['increased_price']) : '' ?>">
                                <span class="input-group-text">元</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="col-form-label">涨价原因</label>
                            <input type="text" class="form-control" name="increase_reason" placeholder="例如：原材料价格上涨" value="<?= isset($_POST['increase_reason']) ? htmlspecialchars($_POST['increase_reason']) : '' ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 提交按钮 -->
            <div class="row">
                <div class="col-12" style="text-align: center;">
                    <button type="submit" class="btn btn-primary btn-lg me-3">
                        <i class="fas fa-save"></i>立即提交
                    </button>
                    <button type="reset" class="btn btn-secondary btn-lg me-3">
                        <i class="fas fa-undo"></i>重置
                    </button>
                    <a href="products.php" class="btn btn-danger btn-lg">
                        <i class="fas fa-times"></i>取消
                    </a>
                </div>
            </div>
        </form>
    </div>

    <script>

    // 抽奖设置：开关控制奖品选择区域显示
    document.getElementById('isLottery').addEventListener('change', function() {
        document.getElementById('lotteryPrizesSection').style.display = this.checked ? 'block' : 'none';
    });

    // 初始化CKEditor
    let editor;
    
    ClassicEditor
        .create(document.querySelector('#ckeditor-container'), {
            initialData: document.getElementById('ckeditor').value || '<p>请输入商品详细描述...</p>',
            placeholder: '请输入商品详细描述...',
            
            simpleUpload: {
                uploadUrl: 'upload-image.php',
                headers: {
                    'X-CSRF-TOKEN': '<?= $_SESSION['csrf_token'] ?>'
                }
            },
            
            toolbar: {
                items: [
                    'heading', '|',
                    'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|',
                    'outdent', 'indent', '|',
                    'imageUpload', 'blockQuote', 'insertTable', 'mediaEmbed', '|',
                    'undo', 'redo', '|',
                    'alignment', 'fontBackgroundColor', 'fontColor', 'fontSize', 'fontFamily', '|',
                    'code', 'codeBlock', 'htmlEmbed', '|',
                    'horizontalLine', 'pageBreak', 'removeFormat', 'specialCharacters', '|',
                    'sourceEditing', 'restrictedEditing', 'findAndReplace', '|',
                    'subscript', 'superscript', 'strikethrough', 'underline', '|',
                    'todoList', 'highlight'
                ],
                shouldNotGroupWhenFull: true
            },
            
            language: 'zh-cn',
            image: {
                toolbar: [
                    'imageTextAlternative',
                    'toggleImageCaption',
                    'imageStyle:inline',
                    'imageStyle:block',
                    'imageStyle:side',
                    'linkImage'
                ],
                upload: {
                    types: ['jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff']
                }
            },
            table: {
                contentToolbar: [
                    'tableColumn',
                    'tableRow',
                    'mergeTableCells',
                    'tableCellProperties',
                    'tableProperties'
                ]
            }
        })
        .then(editorInstance => {
            editor = editorInstance;
            
            editor.model.document.on('change:data', () => {
                document.getElementById('ckeditor').value = editor.getData();
            });
            
            editor.plugins.get('FileRepository').on('uploadComplete', (evt, data) => {
                if (data.loaded > 0) {
                    console.log('图片上传成功:', data);
                }
            });
            
            editor.plugins.get('FileRepository').on('uploadError', (evt, data) => {
                console.error('图片上传失败:', data.error);
                alert('图片上传失败: ' + data.error);
            });
        })
        .catch(error => {
            console.error('CKEditor初始化失败:', error);
            
            const container = document.querySelector('#ckeditor-container');
            container.innerHTML = `
                <div class="alert alert-danger">
                    <strong>编辑器加载失败!</strong> 
                    请刷新页面或联系管理员。错误详情: ${error.message}
                </div>
            `;
            container.style.padding = '20px';
        });

    // 图片URL插入功能
    document.getElementById('insertImageUrlsBtn').addEventListener('click', function() {
        const urlsInput = document.getElementById('imageUrlsInput').value.trim();
        const widthInput = document.getElementById('imageWidthInput').value;
        const heightInput = document.getElementById('imageHeightInput').value;
        
        if (!urlsInput) {
            alert('请输入至少一个图片URL');
            return;
        }
        
        const urls = urlsInput.split(',').map(url => url.trim()).filter(url => url);
        
        if (urls.length === 0) {
            alert('请输入有效的图片URL');
            return;
        }
        
        let imagesHtml = '';
        urls.forEach(url => {
            let imgTag = '<img src="' + url + '"';
            
            if (widthInput) {
                imgTag += ' width="' + widthInput + '"';
            }
            
            if (heightInput) {
                imgTag += ' height="' + heightInput + '"';
            }
            
            imgTag += ' style="max-width:100%;height:auto;"';
            imgTag += '>';
            
            imagesHtml += imgTag + '<br>';
        });
        
        if (editor) {
            editor.model.change(writer => {
                const viewFragment = editor.data.processor.toView(imagesHtml);
                const modelFragment = editor.data.toModel(viewFragment);
                editor.model.insertContent(modelFragment, editor.model.document.selection);
            });
        } else {
            alert('编辑器未初始化，请稍后再试');
        }
        
        updateImageUrlPreview(urls);
    });

    function updateImageUrlPreview(urls) {
        const previewContainer = document.getElementById('imageUrlPreview');
        previewContainer.innerHTML = '';
        
        urls.forEach(url => {
            const img = document.createElement('img');
            img.src = url;
            img.onerror = function() {
                this.style.display = 'none';
            };
            previewContainer.appendChild(img);
        });
    }

    // 图片预览功能
    const imageInput = document.querySelector('input[name="images[]"]');
    const previewContainer = document.getElementById('imagePreview');
    let uploadedImages = [];
    
    imageInput.addEventListener('change', function(e) {
        previewContainer.innerHTML = '';
        uploadedImages = [];
        
        if (this.files) {
            const files = Array.from(this.files).slice(0, 5);
            
            files.forEach((file, index) => {
                if (file.type.match('image.*')) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const preview = document.createElement('div');
                        preview.className = 'image-preview-item';
                        preview.style.backgroundImage = `url(${e.target.result})`;
                        
                        const removeBtn = document.createElement('div');
                        removeBtn.className = 'remove-image';
                        removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                        removeBtn.addEventListener('click', () => removeImage(index));
                        
                        preview.appendChild(removeBtn);
                        previewContainer.appendChild(preview);
                        
                        uploadedImages.push({
                            file: file,
                            url: e.target.result
                        });
                    }
                    
                    reader.readAsDataURL(file);
                }
            });
        }
    });
    
    function removeImage(index) {
        const previewItems = previewContainer.querySelectorAll('.image-preview-item');
        if (previewItems[index]) {
            previewItems[index].remove();
        }
        
        uploadedImages.splice(index, 1);
        
        const dataTransfer = new DataTransfer();
        uploadedImages.forEach(img => {
            dataTransfer.items.add(img.file);
        });
        
        imageInput.files = dataTransfer.files;
        
        previewContainer.innerHTML = '';
        uploadedImages.forEach((img, i) => {
            const preview = document.createElement('div');
            preview.className = 'image-preview-item';
            preview.style.backgroundImage = `url(${img.url})`;
            
            const removeBtn = document.createElement('div');
            removeBtn.className = 'remove-image';
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
            removeBtn.addEventListener('click', () => removeImage(i));
            
            preview.appendChild(removeBtn);
            previewContainer.appendChild(preview);
        });
    }

    // 动态加载二级分类
    document.getElementById('parent_category').addEventListener('change', function() {
        const parentId = this.value;
        const subCategorySelect = document.getElementById('category_id');
        
        if (parentId > 0) {
            subCategorySelect.innerHTML = '<option value="">加载中...</option>';
            
            setTimeout(() => {
                const subCategories = <?php echo json_encode($sub_categories_grouped); ?>;
                const categoriesForParent = subCategories[parentId] || [];
                
                subCategorySelect.innerHTML = categoriesForParent.length > 0 
                    ? '<option value="">请选择二级分类</option>'
                    : '<option value="">该分类下无二级分类</option>';
                
                categoriesForParent.forEach(category => {
                    const option = document.createElement('option');
                    option.value = category.id;
                    option.textContent = category.name;
                    subCategorySelect.appendChild(option);
                });
                
                if (<?= isset($_POST['category_id']) ? 'true' : 'false' ?>) {
                    const selectedCategoryId = <?= $_POST['category_id'] ?? 0 ?>;
                    if (selectedCategoryId > 0) {
                        subCategorySelect.value = selectedCategoryId;
                    }
                }
            }, 300);
        } else {
            subCategorySelect.innerHTML = '<option value="">请先选择一级分类</option>';
        }
    });

    // 动态生成区域价格输入框
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('game-region-checkbox')) {
            updateRegionPriceFields();
        }
    });

    function updateRegionPriceFields() {
        const container = document.getElementById('regionPricesContainer');
        const checkboxes = document.querySelectorAll('.game-region-checkbox:checked');
        
        container.innerHTML = '';
        
        if (checkboxes.length > 0) {
            const titleRow = document.createElement('div');
            titleRow.className = 'row';
            titleRow.innerHTML = `
                <div class="col-md-12">
                    <h6>区域价格设置</h6>
                    <span class="text-muted">为每个区域设置不同的价格，留空则使用默认价格</span>
                </div>
            `;
            container.appendChild(titleRow);
        }
        
        checkboxes.forEach(checkbox => {
            const region = checkbox.value;
            const regionKey = md5(region);
            
            const priceRow = document.createElement('div');
            priceRow.className = 'region-price-row';
            priceRow.innerHTML = `
                <div class="row">
                    <div class="col-md-4">
                        <label>${region}价格</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" class="form-control" 
                                   name="region_price_${regionKey}" 
                                   placeholder="默认价格">
                            <span class="input-group-text">元</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label>${region}原价</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" class="form-control" 
                                   name="region_original_price_${regionKey}" 
                                   placeholder="默认原价">
                            <span class="input-group-text">元</span>
                        </div>
                    </div>
                </div>
            `;
            
            container.appendChild(priceRow);
        });
    }

    // 添加商品标签
    document.getElementById('addTagBtn').addEventListener('click', function() {
        const container = document.getElementById('tagsContainer');
        const newField = document.createElement('div');
        newField.className = 'input-group mb-2';
        newField.innerHTML = `
            <input type="text" class="form-control" name="tags[]">
            <button type="button" class="btn remove-tag"><i class="fas fa-times"></i></button>
        `;
        container.appendChild(newField);
    });

    // 删除商品标签
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-tag') || e.target.closest('.remove-tag')) {
            const btn = e.target.classList.contains('remove-tag') ? e.target : e.target.closest('.remove-tag');
            const tagGroup = btn.closest('.input-group');
            if (document.querySelectorAll('#tagsContainer .input-group').length > 1) {
                tagGroup.remove();
            } else {
                tagGroup.querySelector('input').value = '';
            }
        }
    });

    // 简单的MD5函数用于生成唯一键
    function md5(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }
        return Math.abs(hash).toString(36);
    }

    // 表单验证
    document.getElementById('productForm').addEventListener('submit', function(e) {
        let isValid = true;
        const errorMessages = [];
        
        const price = parseFloat(document.querySelector('input[name="price"]').value);
        if (price <= 0) {
            errorMessages.push('商品价格必须大于0');
            isValid = false;
        }
        
        const stock = parseInt(document.querySelector('input[name="stock"]').value);
        if (stock < 0) {
            errorMessages.push('库存不能为负数');
            isValid = false;
        }
        
        const minPurchase = parseInt(document.querySelector('input[name="min_purchase_per_user"]').value);
        if (minPurchase <= 0) {
            errorMessages.push('最低购买数量必须大于0');
            isValid = false;
        }
        
        const maxPurchaseInput = document.querySelector('input[name="max_purchase_per_user"]');
        if (maxPurchaseInput.value) {
            const maxPurchase = parseInt(maxPurchaseInput.value);
            if (maxPurchase < minPurchase) {
                errorMessages.push('最大购买数量不能小于最低购买数量');
                isValid = false;
            }
        }
        
        const imageInput = document.querySelector('input[name="images[]"]');
        if (!imageInput.files || imageInput.files.length === 0) {
            errorMessages.push('请至少上传一张商品图片');
            isValid = false;
        }
        
        const gameRegions = document.querySelectorAll('input[name="game_region[]"]:checked');
        if (gameRegions.length === 0) {
            errorMessages.push('请至少选择一个游戏大区');
            isValid = false;
        }
        
        const parentCategory = document.getElementById('parent_category').value;
        const subCategory = document.getElementById('category_id').value;
        if (!parentCategory || !subCategory) {
            errorMessages.push('请选择完整分类');
            isValid = false;
        }
        
        const descriptionValidation = document.getElementById('descriptionValidation').value;
        if (descriptionValidation === '0') {
            errorMessages.push('商品说明不能为空');
            isValid = false;
            
            const editorContainer = document.querySelector('#ckeditor-container');
            if (editorContainer) {
                editorContainer.classList.add('editor-error');
            }
        }
        
        if (!isValid) {
            e.preventDefault();
            
            const errorAlert = document.createElement('div');
            errorAlert.className = 'alert alert-danger';
            errorAlert.innerHTML = `
                <strong>表单验证失败!</strong>
                <ul>
                    ${errorMessages.map(msg => `<li>${msg}</li>`).join('')}
                </ul>
            `;
            
            const oldAlert = document.querySelector('.alert.alert-danger');
            if (oldAlert) {
                oldAlert.remove();
            }
            
            const container = document.querySelector('.container');
            container.insertBefore(errorAlert, container.firstChild);
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });

    // 初始化区域价格输入框
    updateRegionPriceFields();
    </script>
</body>
</html>
<?php
ob_end_flush();
?>