<?php
/**
 * 彩虹聚合登录SDK
 * 1.0
**/

// API地址
$Oauth_config['apiurl'] = 'https://login.acloudn.com/';

// APPID
$Oauth_config['appid'] = '1006';

// APPKEY
$Oauth_config['appkey'] = 'c511de22216084dd19e261c72bbb8899';

// 引入数据库配置文件
require_once __DIR__ . '/../config.php';

// 创建数据库连接
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("数据库连接失败: " . mysqli_connect_error());
}

// 查询站点域名
$sql = "SELECT site_domain FROM settings WHERE id = 1 LIMIT 1";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $site_domain = rtrim($row['site_domain'], '/');
    
    // 构建回调地址
    $callback_path = '/h5/connect.php';
    $Oauth_config['callback'] = $site_domain . $callback_path;
} else {
    // 如果查询失败，使用默认值
    $Oauth_config['callback'] = 'https://lmpw.bcorn.cn/h5/connect.php';
}

// 关闭数据库连接
mysqli_close($conn);
?>