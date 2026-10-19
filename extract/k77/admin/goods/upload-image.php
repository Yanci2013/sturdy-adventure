<?php
session_start();

header('Content-Type: application/json');

// 验证CSRF令牌
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    die(json_encode(['error' => ['message' => 'CSRF token validation failed']]));
}

// 检查文件上传
if (!isset($_FILES['upload'])) {
    http_response_code(400);
    die(json_encode(['error' => ['message' => 'No file uploaded']]));
}

// 设置上传目录
$uploadDir = __DIR__ . '/uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// 验证文件类型
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$fileType = $_FILES['upload']['type'];
if (!in_array($fileType, $allowedTypes)) {
    http_response_code(415);
    die(json_encode(['error' => ['message' => '只允许上传JPG, PNG, GIF, WEBP格式的图片']]));
}

// 验证文件大小 (最大5MB)
$maxFileSize = 5 * 1024 * 1024;
if ($_FILES['upload']['size'] > $maxFileSize) {
    http_response_code(413);
    die(json_encode(['error' => ['message' => '文件大小不能超过5MB']]));
}

// 生成唯一文件名
$fileExt = pathinfo($_FILES['upload']['name'], PATHINFO_EXTENSION);
$fileName = 'img_' . md5(uniqid() . $_FILES['upload']['name']) . '.' . $fileExt;
$filePath = $uploadDir . $fileName;

// 移动上传的文件
if (!move_uploaded_file($_FILES['upload']['tmp_name'], $filePath)) {
    http_response_code(500);
    die(json_encode(['error' => ['message' => '文件上传失败']]));
}

// 返回CKEditor期望的响应格式
echo json_encode([
    'url' => '/uploads/' . $fileName,
    'uploaded' => true
]);
?>