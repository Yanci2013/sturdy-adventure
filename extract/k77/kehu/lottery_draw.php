<?php
/**
 * 大转盘抽奖处理接口
 * 奖品完全自定义，不关联商品表
 * 中奖后直接创建代练订单发布到打手后台
 */
require_once '../includes/session_manager.php';
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_h5_logged_in()) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => '用户未登录']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请求方式错误']);
    exit;
}

$game_area = $_POST['game_area'] ?? '';
$game_nickname = $_POST['game_nickname'] ?? '';
$game_id = $_POST['game_id'] ?? '';
$remark = $_POST['remark'] ?? '';
$payment_order_id = $_POST['payment_order_id'] ?? '';

if (empty($game_area) || empty($game_nickname) || empty($game_id)) {
    echo json_encode(['success' => false, 'message' => '请填写完整的游戏信息']);
    exit;
}

try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $db->beginTransaction();

    // 1. 检查设置
    $settings = $db->query("SELECT * FROM lottery_settings WHERE id = 1")->fetch();
    if (!$settings || $settings['enabled'] != 1) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => '抽奖活动已关闭']);
        exit;
    }

    // 2. 检查今日次数
    $today = date('Y-m-d');
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM lottery_records WHERE user_id = ? AND DATE(created_at) = ?");
    $stmt->execute([$user_id, $today]);
    $today_count = intval($stmt->fetch()['cnt'] ?? 0);
    if ($today_count >= intval($settings['daily_limit'])) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => $settings['limit_message'] ?? '今日抽奖次数已达上限']);
        exit;
    }

    // 3. 获取有效奖品
    $prizes = $db->query("SELECT * FROM lottery_prizes WHERE status = 1 ORDER BY sort_order DESC, id ASC")->fetchAll();
    if (empty($prizes)) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => '暂无奖品']);
        exit;
    }

    // 4. 概率抽奖
    $totalProb = 0;
    foreach ($prizes as $p) $totalProb += floatval($p['probability']);
    $rand = mt_rand(1, 10000) / 100;
    $cum = 0; $winPrize = null;
    foreach ($prizes as $p) {
        $cum += floatval($p['probability']);
        if ($rand <= $cum) { $winPrize = $p; break; }
    }

    // 5. 记录抽奖
    $stmt = $db->prepare("INSERT INTO lottery_records (user_id, prize_id, prize_name, is_win, game_area, game_nickname, game_id, remark, payment_order_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([
        $user_id,
        $winPrize ? $winPrize['id'] : 0,
        $winPrize ? $winPrize['name'] : '谢谢参与',
        $winPrize ? 1 : 0,
        $game_area, $game_nickname, $game_id, $remark, $payment_order_id
    ]);
    $record_id = $db->lastInsertId();

    // 6. 中奖则创建订单（发布到打手后台）
    $order_id = null;
    $order_no = null;
    if ($winPrize) {
        $order_no = 'DD' . date('YmdHis') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

        $user = $db->prepare("SELECT username, phone FROM users WHERE id = ?");
        $user->execute([$user_id]);
        $userInfo = $user->fetch();

        // 创建订单 - 不关联product_id，奖品名称作为商品名
        $stmt = $db->prepare("INSERT INTO orders (
            order_no, user_id, username, phone,
            product_id, product_name, product_price,
            game_area, game_nickname, game_id, remark,
            status, source_type, source_id, lottery_record_id,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");

        $stmt->execute([
            $order_no,
            $user_id,
            $userInfo['username'] ?? '',
            $userInfo['phone'] ?? '',
            0, // product_id = 0（自定义奖品）
            $winPrize['name'], // 奖品名称作为商品名
            0, // 价格为0（抽奖获得）
            $game_area,
            $game_nickname,
            $game_id,
            $remark . ' [大转盘抽奖获得奖品: ' . $winPrize['name'] . ']',
            0, // status=0 待接单（发布到打手后台）
            'lottery_wheel',
            $winPrize['id'],
            $record_id
        ]);

        $order_id = $db->lastInsertId();

        // 记录中奖
        $stmt = $db->prepare("INSERT INTO lottery_winners (user_id, prize_id, prize_name, order_id, record_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $winPrize['id'], $winPrize['name'], $order_id, $record_id]);
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'is_win' => $winPrize ? true : false,
        'prize' => $winPrize ? ['id' => $winPrize['id'], 'name' => $winPrize['name']] : null,
        'message' => $winPrize ? '恭喜您抽中：' . $winPrize['name'] : '很遗憾，这次没有中奖',
        'order_id' => $order_id,
        'order_no' => $order_no,
        'record_id' => $record_id,
        'today_count' => $today_count + 1,
        'daily_limit' => intval($settings['daily_limit'])
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('Lottery draw error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '系统错误，请稍后重试']);
}
