<?php
// 定义根目录路径
define('ROOT_PATH', dirname(__DIR__, 3) . '/');

// 加载公共文件
if (file_exists(ROOT_PATH . 'includes/common.php')) {
    require_once(ROOT_PATH . 'includes/common.php');
} else {
    // 如果common.php不存在，使用默认配置
    $epay_config = [
        'apiurl' => 'http://pay.www.com/',
        'pid' => '1000',
        'key' => 'WWc3Z2jkK7jhNGPALcGKjHLPK47wRK85'
    ];
    return;
}

// 默认配置
$default_config = [
    'apiurl' => 'http://pay.www.com/',
    'pid' => '1000',
    'key' => 'WWc3Z2jkK7jhNGPALcGKjHLPK47wRK85'
];

try {
    // 检查数据库连接是否可用
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        // 如果PDO连接无效，使用默认值
        $epay_config = $default_config;
        error_log("数据库连接未初始化");
        return;
    }

    // 设置PDO错误模式
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 从settings表中获取支付配置
    $sql = "SELECT payment_api_url, payment_app_id, payment_app_key FROM settings LIMIT 1";
    $stmt = $pdo->query($sql);
    
    if ($stmt && $stmt->rowCount() > 0) {
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $epay_config = [
            'apiurl' => $settings['payment_api_url'] ?? $default_config['apiurl'],
            'pid' => $settings['payment_app_id'] ?? $default_config['pid'],
            'key' => $settings['payment_app_key'] ?? $default_config['key']
        ];
    } else {
        $epay_config = $default_config;
    }
} catch (PDOException $e) {
    // 捕获PDO异常并使用默认值
    $epay_config = $default_config;
    error_log("数据库错误: " . $e->getMessage());
} catch (Exception $e) {
    // 捕获其他异常并使用默认值
    $epay_config = $default_config;
    error_log("系统错误: " . $e->getMessage());
}