<?php
// 开启错误报告
error_reporting(E_ALL);
ini_set("display_errors", 1);

// 引入数据库连接文件
include_once __DIR__ . '/../../includes/common.php';

// 设置响应头为JSON
header('Content-Type: application/json');

// 获取GET参数
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

// 验证参数
if ($order_id <= 0) {
    echo json_encode(["code" => 1, "msg" => "无效的订单ID", "data" => ["remark" => ""]]);
    exit;
}

try {
    // 获取订单备注
    $sql = "SELECT remark FROM orders WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $order_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo json_encode(["code" => 0, "msg" => "获取成功", "data" => ["remark" => $result['remark'] ?? '']]);
    } else {
        echo json_encode(["code" => 1, "msg" => "订单不存在", "data" => ["remark" => ""]]);
    }
    
} catch (PDOException $e) {
    echo json_encode(["code" => 1, "msg" => "数据库错误: " . $e->getMessage(), "data" => ["remark" => ""]]);
}
?>