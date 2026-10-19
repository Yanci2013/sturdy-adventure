<?php
/**
 * 微信支付回调处理
 */
require_once 'includes/common.php';
require_once 'includes/WechatPay.php';

// 获取微信支付配置
$settings = $pdo->query("SELECT * FROM settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);

if (!$settings['wechat_pay_enabled']) {
    echo json_encode(['code' => 'FAIL', 'message' => '微信支付未启用']);
    exit;
}

try {
    $wechatPay = new WechatPay([
        'appid' => $settings['wechat_appid'],
        'mchid' => $settings['wechat_mch_id'],
        'mch_key' => $settings['wechat_mch_key'],
        'notify_url' => $settings['site_domain'] . '/wechat_notify.php',
        'cert_path' => $settings['wechat_mch_cert_path']
    ]);
    
    // 处理回调通知
    $result = $wechatPay->handleNotify();
    
    if ($result['success']) {
        $out_trade_no = $result['out_trade_no'];
        $transaction_id = $result['transaction_id'];
        $total_fee = $result['total_fee'];
        
        // 查找订单
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_no = ?");
        $stmt->execute([$out_trade_no]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            // 更新订单状态为已支付
            $updateStmt = $pdo->prepare("UPDATE orders SET order_status = 1, pay_time = NOW(), transaction_id = ? WHERE id = ?");
            $updateStmt->execute([$transaction_id, $order['id']]);
            
            // 记录订单日志
            require_once 'includes/log_helper.php';
            log_order_action($pdo, $order['id'], $out_trade_no, 'wechat_pay_success', '微信支付成功', null, null, 'system', $transaction_id, $total_fee);
        }
        
        // 返回成功响应给微信
        echo json_encode(['code' => 'SUCCESS', 'message' => '成功']);
    } else {
        echo json_encode(['code' => 'FAIL', 'message' => '解密失败']);
    }
    
} catch (Exception $e) {
    error_log('微信支付回调错误: ' . $e->getMessage());
    echo json_encode(['code' => 'FAIL', 'message' => '处理失败']);
}
