<?php
require_once '../../config.php';

// 数据库连接
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 检查penalty_reasons表是否存在
    $stmt = $pdo->query("SHOW TABLES LIKE 'penalty_reasons'");
    if ($stmt->rowCount() > 0) {
        echo 'penalty_reasons表存在';
    } else {
        echo 'penalty_reasons表不存在';
    }
    
} catch (PDOException $e) {
    echo '数据库连接失败：' . $e->getMessage();
}
?>