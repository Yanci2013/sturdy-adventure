<?php
require_once '../../config.php';

// 数据库连接
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 检查penalty_records表的结构
    $stmt = $pdo->query("DESCRIBE penalty_records");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "penalty_records表的列结构：\n";
    foreach ($columns as $column) {
        echo $column['Field'] . " (" . $column['Type'] . ")" . " - " . $column['Comment'] . "\n";
    }
    
} catch (PDOException $e) {
    echo '数据库连接失败：' . $e->getMessage();
}
?>