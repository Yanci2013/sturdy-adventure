<?php
/**
 * DIY 配置 API - 输出 JSON 格式的自定义配置
 * 供前端 JS 读取应用
 */
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: public, max-age=300');

require_once dirname(dirname(__DIR__)) . '/config.php';

$configs = [];
try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $stmt = $db->query("SELECT config_type, config_key, config_value FROM diy_settings");
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        $configs[$row['config_type']][$row['config_key']] = $row['config_value'];
    }
} catch (Exception $e) {
    $configs = [];
}

// 构建完整路径
$base_url = dirname(dirname($_SERVER['SCRIPT_NAME']));
if (!empty($configs['general']['bg_image'])) {
    $configs['general']['bg_image'] = $base_url . '/' . $configs['general']['bg_image'];
}
if (!empty($configs['general']['top_banner'])) {
    $configs['general']['top_banner'] = $base_url . '/' . $configs['general']['top_banner'];
}
if (!empty($configs['general']['bg_music'])) {
    $configs['general']['bg_music'] = $base_url . '/' . $configs['general']['bg_music'];
}
if (!empty($configs['footer']['bg_image'])) {
    $configs['footer']['bg_image'] = $base_url . '/' . $configs['footer']['bg_image'];
}
foreach (['home', 'category', 'hunter', 'message', 'mine'] as $key) {
    if (!empty($configs['footer'][$key . '_icon_default'])) {
        $configs['footer'][$key . '_icon_default'] = $base_url . '/' . $configs['footer'][$key . '_icon_default'];
    }
    if (!empty($configs['footer'][$key . '_icon_active'])) {
        $configs['footer'][$key . '_icon_active'] = $base_url . '/' . $configs['footer'][$key . '_icon_active'];
    }
}

echo 'window.V3_SERVER_CONFIG = ' . json_encode($configs, JSON_UNESCAPED_UNICODE) . ';';
echo "\n";
echo 'window.V3_BASE_URL = ' . json_encode($base_url) . ';';
?>
