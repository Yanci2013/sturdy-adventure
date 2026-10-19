<?php
/**
 * 支付结果页面
 * 处理支付完成后的跳转和结果显示
 */

// 安全设置
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start(); // 开启输出缓冲
header('Content-Type: text/html; charset=utf-8');

// 定义日志目录
define('LOG_DIR', __DIR__.'/logs/');
if (!is_dir(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}

// 记录原始请求
file_put_contents(LOG_DIR.'payment_raw_'.date('Ymd').'.log', 
    date('Y-m-d H:i:s')." | IP: ".$_SERVER['REMOTE_ADDR'].(isset($_GET) ? " | GET: ".substr(json_encode($_GET), 0, 1000) : '')."\n", FILE_APPEND);

// 检查是否有订单号参数
if (!isset($_GET['out_trade_no'])) {
    ob_end_clean();
    die('<script>alert("订单信息缺失");window.location.href="../index.php";</script>');
}

require_once("lib/epay.config.php");
require_once("lib/EpayCore.class.php");

// 初始化验证类
$epay = new EpayCore($epay_config);
$verify_result = $epay->verifyNotify();

// 记录验证结果
file_put_contents(LOG_DIR.'payment_verify_'.date('Ymd').'.log', 
    date('Y-m-d H:i:s')." | 验证: ".($verify_result ? '成功' : '失败')."\n", FILE_APPEND);

if (!$verify_result) {
    ob_end_clean(); // 清空缓冲区
    $error = method_exists($epay, 'getError') ? $epay->getError() : '未知错误';
    file_put_contents(LOG_DIR.'payment_error_'.date('Ymd').'.log', 
        date('Y-m-d H:i:s')." | 错误: $error\n", FILE_APPEND);
    die('<script>alert("支付验证失败");window.location.href="../index.php";</script>');
}

// 获取并过滤支付参数
$out_trade_no = substr(trim($_GET['out_trade_no']), 0, 32);
$trade_no = isset($_GET['trade_no']) ? substr(trim($_GET['trade_no']), 0, 64) : '';
$trade_status = isset($_GET['trade_status']) ? substr(trim($_GET['trade_status']), 0, 20) : '';
$type = isset($_GET['type']) ? substr(trim($_GET['type']), 0, 20) : '';
$money = isset($_GET['money']) ? floatval($_GET['money']) : 0;

// 记录有效参数
file_put_contents(LOG_DIR.'payment_info_'.date('Ymd').'.log', 
    date('Y-m-d H:i:s')." | 订单: $out_trade_no | 金额: $money | 状态: $trade_status\n", FILE_APPEND);

try {
    // 数据库连接
    $db = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", 
        DB_USER, 
        DB_PASS, 
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false
        ]
    );

    // 开始事务
    $db->beginTransaction();

    // 检查订单是否已存在
    $checkOrderStmt = $db->prepare("SELECT id FROM orders WHERE order_no = ? AND payment_status = 'paid' LIMIT 1");
    $checkOrderStmt->execute([$out_trade_no]);
    
    if ($checkOrderStmt->rowCount() > 0) {
        $order_id = $checkOrderStmt->fetchColumn();
        $db->commit();
        ob_end_clean(); // 清空缓冲区
        header("Location: ../order_detail.php?id=".$order_id);
        exit;
    }

    // 获取临时订单及用户信息
    $getTempOrderStmt = $db->prepare("
        SELECT 
            ot.*, 
            u.id AS user_id, 
            u.user_idu, 
            u.nickname AS user_nickname,
            u.phone AS user_phone,
            u.total_payments,
            u.payment_count
        FROM order_temp ot
        LEFT JOIN h5user u ON ot.user_id = u.id
        WHERE ot.order_no = ?
        LIMIT 1
    ");
    $getTempOrderStmt->execute([$out_trade_no]);
    $order = $getTempOrderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("未找到临时订单: $out_trade_no");
    }

    // 验证金额一致性
    if (abs(floatval($order['price']) - $money) > 0.01) {
        throw new Exception("金额不匹配: 订单{$order['price']} ≠ 支付{$money}");
    }

    // 转换input_fields中的字段名为中文
    $inputFields = json_decode($order['input_fields'], true);
    if ($inputFields && is_array($inputFields)) {
        $convertedFields = [];
        foreach ($inputFields as $key => $value) {
            // 将英文键名转换为中文键名
            $newKey = $key;
            if ($key === 'chinese_id') {
                $newKey = '中文ID';
            } elseif ($key === 'numeric_id') {
                $newKey = '数字ID';
            } elseif ($key === 'remark') {
                $newKey = '备注';
            }
            $convertedFields[$newKey] = $value;
        }
        $order['input_fields'] = json_encode($convertedFields, JSON_UNESCAPED_UNICODE);
    }

    // 插入正式订单
    $insertOrderStmt = $db->prepare("
        INSERT INTO orders (
            order_no, product_id, category_id, category_name,
            subcategory_id, subcategory_name, product_name, price, 
            user_id, user_idu, user_nickname, game_region, 
            hunter_option, hunter_id, payment_type, payment_amount, 
            payment_status, payment_time, trade_no, input_fields, 
            create_time, update_time, order_status, fadan_id, fadan_name,
            payment_ip
        ) VALUES (
            :order_no, :product_id, :category_id, :category_name,
            :subcategory_id, :subcategory_name, :product_name, :price, 
            :user_id, :user_idu, :user_nickname, :game_region, 
            :hunter_option, :hunter_id, :payment_type, :payment_amount, 
            :payment_status, :payment_time, :trade_no, :input_fields, 
            NOW(), NOW(), 1, 1, '系统发单',
            :payment_ip
        )
    ");
    
    // 绑定参数
    $insertOrderStmt->execute([
        ':order_no' => $out_trade_no,
        ':product_id' => $order['product_id'],
        ':category_id' => $order['category_id'],
        ':category_name' => $order['category_name'],
        ':subcategory_id' => $order['subcategory_id'] ?? null,
        ':subcategory_name' => $order['subcategory_name'] ?? null,
        ':product_name' => $order['product_name'],
        ':price' => $order['price'],
        ':user_id' => $order['user_id'],
        ':user_idu' => $order['user_idu'],
        ':user_nickname' => $order['user_nickname'],
        ':game_region' => $order['game_region'],
        ':hunter_option' => $order['hunter_option'],
        ':hunter_id' => $order['hunter_id'],
        ':payment_type' => $type,
        ':payment_amount' => $money,
        ':payment_status' => 'paid',
        ':payment_time' => date('Y-m-d H:i:s'),
        ':trade_no' => $trade_no,
        ':input_fields' => $order['input_fields'],
        ':payment_ip' => $order['payment_ip'] ?? ''
    ]);

    // 获取新订单ID
    $order_id = $db->lastInsertId();

    // 删除临时订单
    $deleteTempOrderStmt = $db->prepare("DELETE FROM order_temp WHERE order_no = ?");
    $deleteTempOrderStmt->execute([$out_trade_no]);

    // 更新用户支付信息
    if ($order['user_id']) {
        $updateUserStmt = $db->prepare("
            UPDATE h5user SET 
                total_payments = total_payments + :amount,
                last_payment_time = NOW(),
                payment_count = payment_count + 1,
                updated_at = NOW()
            WHERE id = :user_id
        ");
        $updateUserStmt->execute([
            ':amount' => $money,
            ':user_id' => $order['user_id']
        ]);
    }

    // 提交事务
    $db->commit();

    // 记录成功日志
    file_put_contents(LOG_DIR.'payment_success_'.date('Ymd').'.log', 
        date('Y-m-d H:i:s')." | 订单ID: $order_id | 用户: {$order['user_id']}\n", FILE_APPEND);

    // 确保没有输出内容
    ob_end_clean();
    
    // 跳转到订单详情页
    header("Location: ../order_detail.php?id=".$order_id);
    exit;

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    file_put_contents(LOG_DIR.'payment_db_error_'.date('Ymd').'.log', 
        date('Y-m-d H:i:s')." | 错误: ".$e->getMessage()."\n", FILE_APPEND);
    
    ob_end_clean();
    die('<script>alert("支付处理失败，请稍后再试");window.location.href="../index.php";</script>');

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    file_put_contents(LOG_DIR.'payment_error_'.date('Ymd').'.log', 
        date('Y-m-d H:i:s')." | 错误: ".$e->getMessage()."\n", FILE_APPEND);
    
    ob_end_clean();
    die('<script>alert("'.$e->getMessage().'");window.location.href="../index.php";</script>');
}