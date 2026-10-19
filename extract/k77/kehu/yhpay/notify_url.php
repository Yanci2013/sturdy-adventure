<?php
/**
 * 彩虹易支付异步通知处理（最终版）
 * 版本：2.4
 * 功能：安全处理支付回调，更新订单状态并跳转订单详情页
 */

// 安全设置
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: text/html; charset=utf-8'); // 修改为HTML格式以支持跳转

// 定义日志目录
define('LOG_DIR', __DIR__.'/logs/');
if (!is_dir(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}

// 记录原始请求
file_put_contents(LOG_DIR.'payment_raw_'.date('Ymd').'.log', 
    date('Y-m-d H:i:s')." | IP: ".$_SERVER['REMOTE_ADDR']." | INPUT: ".
    substr(json_encode($_GET), 0, 1000)."\n", FILE_APPEND);

require_once("lib/epay.config.php");
require_once("lib/EpayCore.class.php");

// 初始化验证类
$epay = new EpayCore($epay_config);
$verify_result = $epay->verifyNotify();

// 记录验证结果
file_put_contents(LOG_DIR.'payment_verify_'.date('Ymd').'.log', 
    date('Y-m-d H:i:s')." | 验证: ".($verify_result ? '成功' : '失败')."\n", FILE_APPEND);

if (!$verify_result) {
    $error = method_exists($epay, 'getError') ? $epay->getError() : '未知错误';
    file_put_contents(LOG_DIR.'payment_error_'.date('Ymd').'.log', 
        date('Y-m-d H:i:s')." | 错误: $error\n", FILE_APPEND);
    die('<script>alert("支付验证失败");window.location.href="/";</script>');
}

// 获取并过滤支付参数
$out_trade_no = substr(trim($_GET['out_trade_no']), 0, 32);
$trade_no = substr(trim($_GET['trade_no']), 0, 64);
$trade_status = substr(trim($_GET['trade_status']), 0, 20);
$type = substr(trim($_GET['type']), 0, 20);
$money = floatval($_GET['money']);

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
    $checkOrderStmt = $db->prepare("SELECT id FROM orders WHERE order_no = ? LIMIT 1");
    $checkOrderStmt->execute([$out_trade_no]);
    
    if ($checkOrderStmt->rowCount() > 0) {
        $order_id = $checkOrderStmt->fetchColumn();
        $db->commit();
        
        // 跳转到订单详情页
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
            u.phone AS user_phone
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

    // 获取备注信息
    $notes = $order['notes'] ?? '';

    // 转换input_fields中的字段名为中文
    $inputFields = json_decode($order['input_fields'], true);
    if ($inputFields && is_array($inputFields)) {
        $convertedFields = [];
        $hasRemark = false;
        foreach ($inputFields as $key => $value) {
            // 跳过空值
            if (empty($value)) continue;
            
            // 如果已经是中文键名，直接使用
            if (in_array($key, ['中文ID', '数字ID', '备注'])) {
                $convertedFields[$key] = $value;
                if ($key === '备注') {
                    $hasRemark = true;
                    $notes = $value;
                }
                continue;
            }
            // 将英文键名转换为中文键名
            $newKey = $key;
            if ($key === 'chinese_id') {
                $newKey = '中文ID';
            } elseif ($key === 'numeric_id') {
                $newKey = '数字ID';
            } elseif ($key === 'remark') {
                $newKey = '备注';
                $hasRemark = true;
                $notes = $value;
            }
            $convertedFields[$newKey] = $value;
        }
        // 确保备注字段存在
        if (!$hasRemark && !empty($notes)) {
            $convertedFields['备注'] = $notes;
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
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            NOW(), NOW(), 1, 1, '系统发单',
            ?
        )
    ");
    
    $insertOrderStmt->execute([
        $out_trade_no,
        $order['product_id'],
        $order['category_id'],
        $order['category_name'],
        $order['subcategory_id'] ?? null,
        $order['subcategory_name'] ?? null,
        $order['product_name'],
        $order['price'],
        $order['user_id'],
        $order['user_idu'],
        $order['user_nickname'],
        $order['game_region'],
        $order['hunter_option'],
        $order['hunter_id'],
        $type,
        $money,
        'paid',
        date('Y-m-d H:i:s'),
        $trade_no,
        $order['input_fields'],
        $order['payment_ip'] ?? '' // 添加支付IP字段
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
                total_payments = total_payments + ?,
                last_payment_time = NOW(),
                payment_count = payment_count + 1
            WHERE id = ?
        ");
        $updateUserStmt->execute([$money, $order['user_id']]);
    }

    // ===================== 邀请分佣逻辑 开始 =====================
    $buy_uid = $order['user_id'];
    $pay_amount = $money;
    $commission_rate = 0.1;
    $commission = $pay_amount * $commission_rate;

    // 查询下单用户的上级邀请人
    $inviteStmt = $db->prepare("SELECT invite_uid FROM h5user WHERE id = ? LIMIT 1");
    $inviteStmt->execute([$buy_uid]);
    $inviteRow = $inviteStmt->fetch();
    $pid = intval($inviteRow['invite_uid']);

    // 有上级邀请人则发放佣金
    if ($pid > 0) {
        // 上级余额增加佣金
        $upBalanceStmt = $db->prepare("UPDATE h5user SET balance = balance + ? WHERE id = ?");
        $upBalanceStmt->execute([$commission, $pid]);

        // 写入邀请收益记录，用于邀请页统计展示
        $logStmt = $db->prepare("INSERT INTO user_invite_earn (inviter_uid, earn_amount, create_time) VALUES (?, ?, NOW())");
        $logStmt->execute([$pid, $commission]);
    }
    // ===================== 邀请分佣逻辑 结束 =====================

    // 提交事务
    $db->commit();

    // 记录成功日志
    file_put_contents(LOG_DIR.'payment_success_'.date('Ymd').'.log', 
        date('Y-m-d H:i:s')." | 订单ID: $order_id | 用户: {$order['user_id']}\n", FILE_APPEND);

    // 跳转到订单详情页
    header("Location: ../order_detail.php?id=".$order_id);
    exit;

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    file_put_contents(LOG_DIR.'payment_db_error_'.date('Ymd').'.log', 
        date('Y-m-d H:i:s')." | 错误: ".$e->getMessage()."\n", FILE_APPEND);
    die('<script>alert("支付处理失败，请稍后再试");window.location.href="/";</script>');

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    file_put_contents(LOG_DIR.'payment_error_'.date('Ymd').'.log', 
        date('Y-m-d H:i:s')." | 错误: ".$e->getMessage()."\n", FILE_APPEND);
    die('<script>alert("'.$e->getMessage().'");window.location.href="/";</script>');
}
?>