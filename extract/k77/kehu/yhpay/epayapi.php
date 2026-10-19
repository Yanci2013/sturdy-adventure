<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>正在为您跳转到支付页面，请稍候...</title>
    <style type="text/css">
        body{margin:0;padding:0}
        p{position:absolute;left:50%;top:50%;height:35px;margin:-35px 0 0 -160px;padding:20px;font:bold 16px/30px "宋体",Arial;text-indent:40px;border:1px solid #c5d0dc}
        #waiting{font-family:Arial}
    </style>
</head>
<body>
<?php
// 先加载配置文件获取数据库配置
require_once(dirname(__DIR__, 2) . '/config.php');

try {
    // 使用PDO连接数据库
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 获取支付回调配置和支付API配置
    $stmt = $pdo->query("SELECT payment_notify_url, payment_api_url, payment_app_id, payment_app_key, site_domain FROM settings LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config) {
        die("未获取到支付配置信息");
    }

    $base_url = rtrim($config['payment_notify_url'], '/');
    
    // 设置支付配置
    $epay_config = [
        'apiurl' => $config['payment_api_url'] ?? 'http://pay.www.com/',
        'pid' => $config['payment_app_id'] ?? '1000',
        'key' => $config['payment_app_key'] ?? 'WWc3Z2jkK7jhNGPALcGKjHLPK47wRK85'
    ];

    /**************************请求参数**************************/
    $notify_url = $base_url . "/kehu/yhpay/notify_url.php"; 
    $return_url = $base_url . "/kehu/yhpay/return_url.php"; 

    // 获取订单信息并验证
    if (!isset($_POST['WIDout_trade_no'], $_POST['type'], $_POST['WIDsubject'], $_POST['WIDtotal_fee'])) {
        die("缺少必要的支付参数");
    }

    $out_trade_no = $_POST['WIDout_trade_no'];
    $type = $_POST['type'];
    $name = $_POST['WIDsubject'];
    $money = $_POST['WIDtotal_fee'];

    // 加载支付核心类
    require_once("lib/EpayCore.class.php");

    // 构造要请求的参数数组
    $parameter = array(
        "pid" => $epay_config['pid'],
        "type" => $type,
        "notify_url" => $notify_url,
        "return_url" => $return_url,
        "out_trade_no" => $out_trade_no,
        "name" => $name,
        "money" => $money,
    );

    // 建立请求
    $epay = new EpayCore($epay_config);
    $html_text = $epay->pagePay($parameter);
    echo $html_text;
    
} catch(PDOException $e) {
    die("数据库错误: " . $e->getMessage());
} catch(Exception $e) {
    die("系统错误: " . $e->getMessage());
}
?>
<p>正在为您跳转到支付页面，请稍候...</p>
</body>
</html>