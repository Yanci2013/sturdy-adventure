<?php

// 引入session管理
require_once dirname(__DIR__) . '/includes/session_manager.php';

// 引入上级目录的数据库配置
$config_path = dirname(__DIR__) . '/config.php';
if (!file_exists($config_path)) {
    die("系统错误：配置文件缺失");
}
require_once $config_path;

// 数据库连接 (PDO方式)
try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("数据库连接失败: " . $e->getMessage());
    die("系统暂时不可用，请稍后再试");
}

// 登录状态检查 - 使用独立的H5 session
function is_logged_in() {
    return is_h5_logged_in();
}

// 网站配置类
class SiteConfig {
    private static $config;
    
    public static function get($key, $default = '') {
        if (self::$config === null) {
            global $db;
            try {
                $stmt = $db->query("SELECT * FROM site_settings LIMIT 1");
                self::$config = $stmt->fetch() ?? [];
            } catch (PDOException $e) {
                error_log("加载网站配置失败: " . $e->getMessage());
                self::$config = [];
            }
        }
        return self::$config[$key] ?? $default;
    }
}
?>