<?php
/**
 * 抽奖 API - 下单后触发抽奖
 */
require_once dirname(dirname(__DIR__)) . '/includes/session_manager.php';
require_once dirname(dirname(__DIR__)) . '/config.php';

header('Content-Type: application/json');

if (!is_h5_logged_in()) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

$order_id = intval($_POST['order_id'] ?? $_GET['order_id'] ?? 0);
$user_id = get_h5_user_id();

if ($order_id < 1) {
    echo json_encode(['success' => false, 'message' => '订单ID无效']);
    exit;
}

try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

    // 获取订单信息
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();

    if (!$order) {
        echo json_encode(['success' => false, 'message' => '订单不存在']);
        exit;
    }

    // 检查是否已抽奖
    if ($order['has_lottery'] == 1) {
        echo json_encode([
            'success' => true,
            'already_drawn' => true,
            'prize_name' => $order['lottery_prize'] ?? '已抽奖',
            'prize_id' => $order['lottery_prize_id'] ?? 0
        ]);
        exit;
    }

    // 获取商品信息
    $stmt = $db->prepare("SELECT is_lottery, name FROM products WHERE id = ?");
    $stmt->execute([$order['product_id']]);
    $product = $stmt->fetch();

    if (!$product || $product['is_lottery'] != 1) {
        echo json_encode(['success' => false, 'message' => '该商品不是抽奖商品']);
        exit;
    }

    // 获取该商品的奖品列表
    $stmt = $db->prepare("SELECT * FROM lottery_prizes WHERE product_id = ? AND status = 1 ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$order['product_id']]);
    $prizes = $stmt->fetchAll();

    if (empty($prizes)) {
        // 如果没有独立奖品，使用通用奖品
        $stmt = $db->query("SELECT * FROM lottery_prizes WHERE (product_id IS NULL OR product_id = 0) AND status = 1 ORDER BY sort_order ASC, id ASC");
        $prizes = $stmt->fetchAll();
    }

    if (empty($prizes)) {
        echo json_encode(['success' => false, 'message' => '暂无奖品']);
        exit;
    }

    // 按概率抽奖
    $total_prob = 0;
    foreach ($prizes as $p) {
        $total_prob += floatval($p['probability']);
    }

    if ($total_prob <= 0) {
        // 没有设置概率，平均分配
        $rand = mt_rand(0, count($prizes) - 1);
        $prize = $prizes[$rand];
    } else {
        $rand = mt_rand(0, intval($total_prob * 10000)) / 10000;
        $cumulative = 0;
        $prize = $prizes[count($prizes) - 1]; // 默认最后一个
        foreach ($prizes as $p) {
            $cumulative += floatval($p['probability']);
            if ($rand <= $cumulative) {
                $prize = $p;
                break;
            }
        }
    }

    // 开始事务
    $db->beginTransaction();

    // 更新订单抽奖信息
    $stmt = $db->prepare("UPDATE orders SET has_lottery = 1, lottery_time = NOW(), lottery_prize = ?, lottery_prize_id = ?, lottery_ip = ? WHERE id = ?");
    $stmt->execute([$prize['name'], $prize['id'], $_SERVER['REMOTE_ADDR'] ?? '', $order_id]);

    // 记录抽奖记录
    $stmt = $db->prepare("INSERT INTO lottery_records (user_id, prize_id, prize_name, prize_type, is_win, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
    $stmt->execute([$user_id, $prize['id'], $prize['name'], $prize['type'] ?? 'product']);

    // 记录中奖信息
    $stmt = $db->prepare("INSERT INTO lottery_winners (user_id, prize_id, prize_name, order_id, record_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $record_id = $db->lastInsertId();
    $stmt->execute([$user_id, $prize['id'], $prize['name'], $order_id, $record_id]);

    // 如果奖品有库存，扣减
    if (!empty($prize['stock']) && $prize['stock'] > 0) {
        $db->prepare("UPDATE lottery_prizes SET stock = stock - 1 WHERE id = ?")->execute([$prize['id']]);
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'prize' => [
            'id' => $prize['id'],
            'name' => $prize['name'],
            'image' => $prize['image'] ?? '',
            'description' => $prize['description'] ?? ''
        ],
        'all_prizes' => array_map(function($p) {
            return ['id' => $p['id'], 'name' => $p['name'], 'image' => $p['image'] ?? ''];
        }, $prizes)
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => '抽奖失败: ' . $e->getMessage()]);
}
