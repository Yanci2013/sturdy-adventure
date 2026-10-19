<?php
include("../../includes/common.php");

// 检查连接是否成功
try {
    // 假设$pdo已经在common.php中初始化
    if (!$pdo) {
        throw new Exception("PDO连接未初始化");
    }
    
    // 从settings表中获取支付配置
    $sql = "SELECT payment_api_url, payment_app_id, payment_app_key FROM settings LIMIT 1";
    $stmt = $pdo->query($sql);
    
    if ($stmt && $stmt->rowCount() > 0) {
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 支付接口地址
        $epay_config['apiurl'] = $settings['payment_api_url'];
        
        // 商户ID
        $epay_config['pid'] = $settings['payment_app_id'];
        
        // 商户密钥
        $epay_config['key'] = $settings['payment_app_key'];
    } else {
        // 如果数据库中没有设置，使用默认值
        $epay_config['apiurl'] = 'http://pay.www.com/';
        $epay_config['pid'] = '1000';
        $epay_config['key'] = 'WWc3Z2jkK7jhNGPALcGKjHLPK47wRK85';
    }
    
} catch (Exception $e) {
    // 如果数据库操作失败，使用默认值
    $epay_config['apiurl'] = 'http://pay.www.com/';
    $epay_config['pid'] = '1000';
    $epay_config['key'] = 'WWc3Z2jkK7jhNGPALcGKjHLPK47wRK85';
    
    // 记录错误日志
    error_log("数据库操作失败: " . $e->getMessage());
}