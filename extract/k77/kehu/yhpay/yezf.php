<?php
/**
 * 支付方式选择页面 - 完整功能版
 * 包含所有订单信息的临时存储
 */

// 设置根路径
define('ROOT_PATH', dirname(__DIR__));

// 检查配置文件
if (!file_exists(ROOT_PATH.'../includes/common.php')) {
    die("系统配置文件缺失，请联系管理员");
}

// 引入配置
require_once ROOT_PATH.'../includes/common.php';

// 开启会话并检查登录
session_start();
if (empty($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// 获取订单号
$out_trade_no = isset($_POST['WIDout_trade_no']) ? trim($_POST['WIDout_trade_no']) : '';
if (empty($out_trade_no)) {
    die('<script>alert("订单号不能为空");history.go(-2);</script>');
}

// 获取支付金额
$money = isset($_POST['WIDtotal_fee']) ? floatval($_POST['WIDtotal_fee']) : 0;
if ($money <= 0) {
    die('<script>alert("支付金额无效");history.go(-2);</script>');
}

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
            u.balance AS user_balance,
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

    // 检查用户余额是否足够
    if ($order['user_balance'] < $money) {
        throw new Exception("账户余额不足，当前余额: ¥".number_format($order['user_balance'], 2));
    }

    // 扣除用户余额
    $deductBalanceStmt = $db->prepare("
        UPDATE h5user SET 
            balance = balance - :amount,
            updated_at = NOW()
        WHERE id = :user_id AND balance >= :amount
    ");
    
    $deductBalanceStmt->execute([
        ':amount' => $money,
        ':user_id' => $order['user_id']
    ]);

    if ($deductBalanceStmt->rowCount() === 0) {
        throw new Exception("扣除余额失败，可能余额不足");
    }

    // 记录余额变动
    $insertBalanceLogStmt = $db->prepare("
        INSERT INTO user_balance_logs (
            user_id, amount, balance, type, 
            related_id, remark, create_time
        ) VALUES (
            :user_id, :amount, (SELECT balance FROM h5user WHERE id = :user_id), 'payment',
            :order_no, '支付订单: ' || :order_no, NOW()
        )
    ");
    
    $insertBalanceLogStmt->execute([
        ':user_id' => $order['user_id'],
        ':amount' => -$money,
        ':order_no' => $out_trade_no
    ]);

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
        ':payment_type' => 'balance',
        ':payment_amount' => $money,
        ':payment_status' => 'paid',
        ':payment_time' => date('Y-m-d H:i:s'),
        ':trade_no' => 'BALANCE_'.date('YmdHis').mt_rand(1000,9999),
        ':input_fields' => $order['input_fields'],
        ':payment_ip' => $order['payment_ip'] ?? '' // 添加支付IP字段
    ]);

    // 获取新订单ID
    $order_id = $db->lastInsertId();

    // 删除临时订单
    $deleteTempOrderStmt = $db->prepare("DELETE FROM order_temp WHERE order_no = ?");
    $deleteTempOrderStmt->execute([$out_trade_no]);

    // 更新用户支付信息
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

    // 提交事务
    $db->commit();

    // 记录成功日志
    file_put_contents(LOG_DIR.'balance_payment_success_'.date('Ymd').'.log', 
        date('Y-m-d H:i:s')." | 订单ID: $order_id | 用户: {$order['user_id']} | 金额: $money\n", FILE_APPEND);

    // 确保没有输出内容
    ob_end_clean();
    
    // 跳转到订单详情页
    header("Location: ../order_detail.php?id=".$order_id);
    exit;

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    file_put_contents(LOG_DIR.'balance_payment_db_error_'.date('Ymd').'.log', 
        date('Y-m-d H:i:s')." | 错误: ".$e->getMessage()."\n", FILE_APPEND);
    
    ob_end_clean();
    die('<script>alert("支付处理失败，请稍后再试");history.go(-2);</script>');

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    file_put_contents(LOG_DIR.'balance_payment_error_'.date('Ymd').'.log', 
        date('Y-m-d H:i:s')." | 错误: ".$e->getMessage()."\n", FILE_APPEND);
    
    ob_end_clean();
    die('<script>alert("'.$e->getMessage().'");history.go(-2);</script>');
}