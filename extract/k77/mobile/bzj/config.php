<?php
// 保证金充值系统配置文件

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// 日志目录
define('LOG_DIR', __DIR__ . '/logs/');
if (!is_dir(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}

// 引入主配置文件获取数据库连接
require_once(APP_ROOT . '/../config.php');

// 初始化数据库连接
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log('数据库连接失败: ' . $e->getMessage());
    die('数据库连接失败');
}
?>