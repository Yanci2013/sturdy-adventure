<?php
/**
 * 支付通知处理文件
 * 处理支付成功后的回调，创建正式订单
 */

// 设置根路径
define('ROOT_PATH', dirname(__DIR__));

// 检查配置文件
if (!file_exists(ROOT_PATH.'/config.php')) {
    die("系统配置文件缺失，请联系管理员");
}

// 引入配置
require_once ROOT_PATH.'/config.php';

// 引入统一会话管理
require_once ROOT_PATH.'/includes/session_manager.php';

// 数据库连接
$db = null;
try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", 
                 DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("数据库错误: ".$e->getMessage());
}

// 处理支付通知
function handlePaymentNotification($db) {
    // 接收支付平台的通知数据
    $postData = $_POST;
    
    // 验证支付状态（这里简化处理，实际需要根据支付平台的验证规则）
    $tradeStatus = isset($postData['trade_status']) ? $postData['trade_status'] : '';
    $orderNo = isset($postData['out_trade_no']) ? $postData['out_trade_no'] : '';
    
    // 对于余额支付，直接从GET参数获取
    if (empty($orderNo) && isset($_GET['order_no'])) {
        $orderNo = $_GET['order_no'];
        $tradeStatus = 'SUCCESS';
    }
    
    if (empty($orderNo)) {
        die('订单号缺失');
    }
    
    // 从临时订单获取订单信息
    try {
        $stmt = $db->prepare("SELECT * FROM order_temp WHERE order_no = ?");
        $stmt->execute([$orderNo]);
        $tempOrder = $stmt->fetch();
        
        if (!$tempOrder) {
            die('临时订单不存在');
        }
        
        // 检查订单是否已处理
        $stmt = $db->prepare("SELECT id FROM orders WHERE order_no = ?");
        $stmt->execute([$orderNo]);
        if ($stmt->fetch()) {
            die('订单已处理');
        }
        
        // 对于抽奖订单，按概率随机选择奖品
        if (strpos($orderNo, 'lottery_') === 0) {
            $selectedProduct = selectPrizeByProbability($db);
            if ($selectedProduct) {
                // 更新临时订单的商品信息
                $tempOrder['product_id'] = $selectedProduct['id'];
                $tempOrder['product_name'] = $selectedProduct['name'];
            }
        }
        
        // 创建正式订单
        $orderId = createFormalOrder($db, $tempOrder);
        
        // 删除临时订单
        $stmt = $db->prepare("DELETE FROM order_temp WHERE order_no = ?");
        $stmt->execute([$orderNo]);
        
        echo 'SUCCESS';
        
    } catch (Exception $e) {
        error_log('处理支付通知失败: '.$e->getMessage());
        die('处理失败: '.$e->getMessage());
    }
}

// 按概率随机选择奖品
function selectPrizeByProbability($db) {
    try {
        $prizes = $db->query("SELECT * FROM lottery_prizes WHERE status = 1")->fetchAll();
        
        if (empty($prizes)) {
            return null;
        }
        
        // 计算总概率
        $totalProbability = 0;
        foreach ($prizes as $prize) {
            $totalProbability += floatval($prize['probability']);
        }
        
        if ($totalProbability <= 0) {
            // 如果总概率为0，随机选择一个
            return $prizes[array_rand($prizes)];
        }
        
        // 生成随机数
        $random = mt_rand(0, $totalProbability * 100) / 100;
        $currentProbability = 0;
        
        // 根据概率选择奖品
        foreach ($prizes as $prize) {
            $currentProbability += floatval($prize['probability']);
            if ($random <= $currentProbability) {
                return $prize;
            }
        }
        
        // 防止概率计算误差，返回最后一个
        return end($prizes);
        
    } catch (Exception $e) {
        error_log('选择奖品失败: '.$e->getMessage());
        return null;
    }
}

// 创建正式订单
function createFormalOrder($db, $tempOrder) {
    try {
        // 生成订单状态为待接单
        $orderStatus = '待接单';
        $orderType = '普通';
        
        // 解析input_fields获取游戏信息
        $inputFields = json_decode($tempOrder['input_fields'], true);
        $gameRegion = $tempOrder['game_region'] ?: '';
        $digitalId = '';
        $chineseId = '';
        $notes = '';
        
        if (is_array($inputFields)) {
            if (isset($inputFields['游戏大区'])) {
                $gameRegion = $inputFields['游戏大区'];
            }
            if (isset($inputFields['游戏ID'])) {
                $digitalId = $inputFields['游戏ID'];
            }
            if (isset($inputFields['游戏昵称'])) {
                $chineseId = $inputFields['游戏昵称'];
            }
            if (isset($inputFields['备注'])) {
                $notes = $inputFields['备注'];
            }
        }
        
        // 插入订单
        $sql = "INSERT INTO orders (
            order_no, product_id, product_name, price, 
            payment_amount, game_region, hunter_option, hunter_id, 
            user_id, user_idu, user_nickname, 
            order_status, order_type, 
            digital_id, chinese_id, notes, 
            create_time, update_time
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $db->prepare($sql);
        
        $result = $stmt->execute([
            $tempOrder['order_no'],
            $tempOrder['product_id'],
            $tempOrder['product_name'],
            $tempOrder['price'],
            $tempOrder['payment_amount'],
            $gameRegion,
            $tempOrder['hunter_option'],
            $tempOrder['hunter_id'],
            $tempOrder['user_id'],
            $tempOrder['user_idu'],
            $tempOrder['user_nickname'],
            $orderStatus,
            $orderType,
            $digitalId,
            $chineseId,
            $notes
        ]);
        
        if (!$result) {
            throw new Exception("创建订单失败");
        }

        // ===================== 邀请分佣逻辑 开始 =====================
        // 获取当前下单用户ID
        $buy_uid = $tempOrder['user_id'];
        // 获取订单实付金额
        $pay_money = $tempOrder['payment_amount'];
        // 佣金比例 10%
        $commission_rate = 0.1;
        $commission = $pay_money * $commission_rate;

        // 查询下单用户的上级邀请人ID
        $stmt_inv = $db->prepare("SELECT invite_uid FROM h5user WHERE id = ? LIMIT 1");
        $stmt_inv->execute([$buy_uid]);
        $inv_data = $stmt_inv->fetch();
        $pid = intval($inv_data['invite_uid']);

        // 存在上级邀请人则发放佣金
        if ($pid > 0) {
            // 给上级增加余额
            $stmt_up = $db->prepare("UPDATE h5user SET balance = balance + ? WHERE id = ?");
            $stmt_up->execute([$commission, $pid]);

            // 写入邀请收益记录表，用于邀请页统计展示
            $stmt_log = $db->prepare("INSERT INTO user_invite_earn (inviter_uid, earn_amount, create_time) VALUES (?, ?, NOW())");
            $stmt_log->execute([$pid, $commission]);
        }
        // ===================== 邀请分佣逻辑 结束 =====================
        
        return $db->lastInsertId();
        
    } catch (Exception $e) {
        throw new Exception("创建正式订单失败: ".$e->getMessage());
    }
}

// 处理支付通知
handlePaymentNotification($db);
?>