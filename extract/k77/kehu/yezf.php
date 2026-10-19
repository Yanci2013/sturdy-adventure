<?php
/**
 * 余额支付处理 - 支持订单支付和余额充值
 */

// 开启会话并检查登录
require_once '../includes/session_manager.php';
if (!is_h5_logged_in()) {
    die('<script>alert("请先登录");location.href="login.php";</script>');
}

// 引入数据库配置
require_once __DIR__.'/../config.php';

// 判断交易类型：订单支付或余额充值
$isRecharge = isset($_GET['isRecharge']) && $_GET['isRecharge'] == 1;

// 重要安全检查：余额支付页面绝对不允许处理充值请求
// 充值必须通过外部支付渠道，不能用余额充余额
if ($isRecharge) {
    die('<script>alert("账户充值不能使用余额支付！请选择其他支付方式。");location.href="my.php";</script>');
}

// 获取支付金额
$money = isset($_POST['WIDtotal_fee']) ? floatval($_POST['WIDtotal_fee']) : 0;
if ($money <= 0) {
    die('<script>alert("支付金额无效");history.go(-1);</script>');
}

// 如果是订单支付，获取订单号
$out_trade_no = '';
if (!$isRecharge) {
    $out_trade_no = isset($_POST['WIDout_trade_no']) ? trim($_POST['WIDout_trade_no']) : '';
    if (empty($out_trade_no)) {
        die('<script>alert("订单号不能为空");history.go(-1);</script>');
    }
}

try {
    // 数据库连接
    $db = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", 
        DB_USER, 
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]
    );
    
    // 如果是充值操作，确保数据库表结构完整
    if ($isRecharge) {
        // 检查并创建充值日志表
        $checkTable = $db->query("SHOW TABLES LIKE 'recharge_logs'");
        if ($checkTable->rowCount() === 0) {
            // 创建充值日志表
            $db->exec("CREATE TABLE recharge_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                create_time DATETIME NOT NULL,
                status VARCHAR(20) NOT NULL,
                remark VARCHAR(255),
                INDEX idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        
        // 检查并添加h5user表中的充值相关字段
        $result = $db->query("SHOW COLUMNS FROM h5user LIKE 'total_recharges'");
        if ($result->rowCount() === 0) {
            $db->exec("ALTER TABLE h5user ADD COLUMN total_recharges DECIMAL(10,2) DEFAULT 0.00");
            $db->exec("ALTER TABLE h5user ADD COLUMN recharge_count INT DEFAULT 0");
            $db->exec("ALTER TABLE h5user ADD COLUMN last_recharge_time DATETIME DEFAULT NULL");
        }
    }

    // 开始事务
    $db->beginTransaction();

    if ($isRecharge) {
        // 余额充值逻辑
        // 1. 增加用户余额
        // 兼容处理：先检查字段是否存在，如果不存在则只更新balance字段
        $hasRechargeFields = true;
        try {
            $result = $db->query("SHOW COLUMNS FROM h5user LIKE 'total_recharges'");
            $hasRechargeFields = $result->rowCount() > 0;
        } catch (Exception $e) {
            $hasRechargeFields = false;
        }
        
        if ($hasRechargeFields) {
            $rechargeStmt = $db->prepare("        
                UPDATE h5user SET 
                    balance = ROUND(balance + ?, 2),
                    total_recharges = ROUND(total_recharges + ?, 2),
                    recharge_count = recharge_count + 1,
                    last_recharge_time = NOW()
                WHERE id = ?
            ");
        } else {
            // 如果没有充值相关字段，只更新余额
            $rechargeStmt = $db->prepare("        
                UPDATE h5user SET 
                    balance = ROUND(balance + ?, 2)
                WHERE id = ?
            ");
        }
        
        if ($hasRechargeFields) {
            $rechargeStmt->execute([$money, $money, get_h5_user_id()]);
        } else {
            $rechargeStmt->execute([$money, get_h5_user_id()]);
        }
        
        // 2. 记录充值日志
        $rechargeLogStmt = $db->prepare("        
            INSERT INTO recharge_logs (user_id, amount, create_time, status, remark)
            VALUES (?, ?, NOW(), 'success', '余额充值')
        ");
        $rechargeLogStmt->execute([get_h5_user_id(), $money]);
        
        // 提交事务
        $db->commit();
        
        // 充值成功，跳转回用户中心
        die('<script>alert("充值成功！余额已增加¥' . number_format($money, 2) . '");location.href="my.php";</script>');
    } else {
        // 订单支付逻辑
        // 1. 检查用户余额是否足够
        $checkBalanceStmt = $db->prepare("        
            SELECT id, balance 
            FROM h5user 
            WHERE id = ? 
            AND ROUND(balance, 2) >= ROUND(?, 2)
            FOR UPDATE
        ");
        $checkBalanceStmt->execute([get_h5_user_id(), $money]);
        
        if ($checkBalanceStmt->rowCount() === 0) {
            throw new Exception("账户余额不足");
        }

        // 2. 扣除用户余额
        $deductStmt = $db->prepare("        
            UPDATE h5user SET 
                balance = ROUND(balance - ?, 2),
                total_payments = ROUND(total_payments + ?, 2),
                payment_count = payment_count + 1,
                last_payment_time = NOW()
            WHERE id = ?
        ");
        $deductStmt->execute([$money, $money, get_h5_user_id()]);

        // 3. 检查临时订单
        $checkTempOrderStmt = $db->prepare("
            SELECT * FROM order_temp 
            WHERE order_no = ? 
            AND user_id = ?
            LIMIT 1 FOR UPDATE
        ");
        $checkTempOrderStmt->execute([$out_trade_no, get_h5_user_id()]);
        $tempOrder = $checkTempOrderStmt->fetch(PDO::FETCH_ASSOC);

        if (!$tempOrder) {
            throw new Exception("未找到待支付订单");
        }
        
        // 4. 对于抽奖订单，按概率随机选择奖品
        if (strpos($out_trade_no, 'lottery_') === 0) {
            try {
                $prizes = $db->query("SELECT * FROM lottery_prizes WHERE status = 1")->fetchAll();
                
                if (!empty($prizes)) {
                    // 计算总概率
                    $totalProbability = 0;
                    foreach ($prizes as $prize) {
                        $totalProbability += floatval($prize['probability']);
                    }
                    
                    if ($totalProbability > 0) {
                        // 生成随机数
                        $random = mt_rand(0, $totalProbability * 100) / 100;
                        $currentProbability = 0;
                        
                        // 根据概率选择奖品
                        foreach ($prizes as $prize) {
                            $currentProbability += floatval($prize['probability']);
                            if ($random <= $currentProbability) {
                                $tempOrder['product_id'] = $prize['id'];
                        $tempOrder['product_name'] = $prize['name'];
                                break;
                            }
                        }
                    } else {
                        // 如果总概率为0，随机选择一个
                    $selectedPrize = $prizes[array_rand($prizes)];
                    $tempOrder['product_id'] = $selectedPrize['id'];
                    $tempOrder['product_name'] = $selectedPrize['name'];
                    }
                }
            } catch (Exception $e) {
                error_log('选择奖品失败: '.$e->getMessage());
            }
        }

        // 4. 处理输入字段
        $inputFields = null;
        if (!empty($tempOrder['input_fields'])) {
            $inputFields = json_decode($tempOrder['input_fields'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $safeFields = [];
                foreach ($inputFields as $key => $value) {
                    // 保留中文字符和常用字符
                    $safeKey = preg_replace('/[<>"&]/', '', $key);
                    $safeValue = is_string($value) ? htmlspecialchars(trim($value), ENT_QUOTES) : $value;
                    $safeFields[$safeKey] = $safeValue;
                }
                $inputFields = json_encode($safeFields, JSON_UNESCAPED_UNICODE);
            } else {
                $inputFields = $tempOrder['input_fields'];
            }
        }

        // 5. 创建正式订单
        $trade_no = 'BAL_'.date('YmdHis').'_'.substr(md5(uniqid()), 0, 8);
        $createOrderStmt = $db->prepare("
            INSERT INTO orders (
                order_no, product_id, product_name, price,
                game_region, hunter_option, hunter_id, input_fields,
                category_id, category_name, subcategory_id, subcategory_name,
                user_id, user_idu, user_nickname,
                payment_type, payment_amount, payment_status, payment_time, trade_no,
                create_time, update_time, order_status, payment_ip
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                'wxpay', ?, 'paid', NOW(), ?,
                NOW(), NOW(), 1, ?
            )
        ");
        
        $createOrderStmt->execute([
            $tempOrder['order_no'],
            $tempOrder['product_id'],
            $tempOrder['product_name'],
            $money,
            $tempOrder['game_region'],
            $tempOrder['hunter_option'],
            $tempOrder['hunter_id'],
            $inputFields,
            $tempOrder['category_id'],
            $tempOrder['category_name'],
            $tempOrder['subcategory_id'],
            $tempOrder['subcategory_name'],
            $tempOrder['user_id'],
            $tempOrder['user_idu'],
            $tempOrder['user_nickname'],
            $money,
            $trade_no,
            $tempOrder['payment_ip']
        ]);
        
        $order_id = $db->lastInsertId();

        // 6. 删除临时订单
        $deleteTempStmt = $db->prepare("DELETE FROM order_temp WHERE order_no = ?");
        $deleteTempStmt->execute([$out_trade_no]);

        // 提交事务
        $db->commit();

        // 跳转到订单列表页
        header("Location: order_list.php");
        exit;
    }

} catch (PDOException $e) {
    // 回滚事务
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("[".date('Y-m-d H:i:s')."] 数据库错误: ".$e->getMessage()."\n", 3, __DIR__.'/../logs/payment_error.log');
    
    die('<script>alert("支付失败: 数据库错误，请稍后再试");history.go(-1);</script>');

} catch (Exception $e) {
    // 回滚事务
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("[".date('Y-m-d H:i:s')."] 业务错误: ".$e->getMessage()."\n", 3, __DIR__.'/../logs/payment_error.log');
    
    die('<script>alert("'.$e->getMessage().'");history.go(-1);</script>');
}
?>