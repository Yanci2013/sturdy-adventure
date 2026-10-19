<?php
require_once __DIR__.'/../../includes/common.php';

// 开启会话
session_start();

// 验证管理员登录
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['code' => 403, 'message' => '未授权访问']);
    exit();
}

// 验证CSRF令牌
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Content-Type: application/json');
    echo json_encode(['code' => 403, 'message' => 'CSRF令牌验证失败']);
    exit();
}

// 获取父分类ID
$parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;

if ($parent_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['code' => 400, 'message' => '无效的父分类ID']);
    exit();
}

// 数据库连接
try {
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

    // 查询二级分类
    $stmt = $conn->prepare("SELECT id, name FROM product_categories WHERE parent_id = ? AND level = 2 AND status = 1 ORDER BY sort_order DESC, name ASC");
    $stmt->execute([$parent_id]);
    $sub_categories = $stmt->fetchAll();

    header('Content-Type: application/json');
    echo json_encode([
        'code' => 0,
        'message' => '成功',
        'data' => $sub_categories
    ]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'code' => 500,
        'message' => '数据库错误: ' . $e->getMessage()
    ]);
}
?>