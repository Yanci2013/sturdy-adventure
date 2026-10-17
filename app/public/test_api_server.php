<?php
/**
 * 独立 API 服务器 - 实现抢单神器接口 + 静态文件服务
 * 启动: php -S 0.0.0.0:8000 -t . test_api_server.php
 */

// 如果不是 API 请求，处理静态文件
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (strpos($uri, '/api/') !== 0) {
    // 为 HTML 文件设置 no-cache 头
    $ext = pathinfo($uri, PATHINFO_EXTENSION);
    if (empty($ext) || $ext === 'html') {
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
    return false;
}

// 数据库配置
$dbConfig = [
    'host' => '127.0.0.1',
    'port' => 3306,
    'dbname' => 'fastadmin',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'prefix' => 'fa_',
];

// 连接数据库
try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => 0, 'msg' => '数据库连接失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

$prefix = $dbConfig['prefix'];

// CORS 跨域
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: token, Content-Type, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

// 解析请求路径
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');

// 解析路由: /api/qiangdan/{action}
$action = '';
if (preg_match('#/api/qiangdan/(\w+)$#', $uri, $m)) {
    $action = $m[1];
}

// 获取请求参数
$params = array_merge($_GET, $_POST);
$input = json_decode(file_get_contents('php://input'), true);
if (is_array($input)) {
    $params = array_merge($params, $input);
}

// 获取 token
$token = $_SERVER['HTTP_TOKEN'] ?? ($params['token'] ?? '');

// 辅助函数
function success($msg, $data = null) {
    echo json_encode(['code' => 1, 'msg' => $msg, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function error($msg, $code = 0) {
    echo json_encode(['code' => $code, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function getConfig($pdo, $prefix, $name, $default = '') {
    $stmt = $pdo->prepare("SELECT value FROM {$prefix}config WHERE name = ?");
    $stmt->execute([$name]);
    $val = $stmt->fetchColumn();
    return $val !== false ? $val : $default;
}

// 不需要登录的接口
$noNeedLogin = ['notify'];

if (!in_array($action, $noNeedLogin)) {
    if (!$token) {
        error('请先登录', 401);
    }
    // 验证 token
    $stmt = $pdo->prepare("SELECT user_id, expiretime FROM {$prefix}user_token WHERE token = ?");
    $stmt->execute([$token]);
    $tokenInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tokenInfo) {
        error('登录已过期', 401);
    }
    if (!empty($tokenInfo['expiretime']) && $tokenInfo['expiretime'] < time()) {
        error('登录已过期', 401);
    }
    $userId = $tokenInfo['user_id'];

    // 查找打手
    $thugId = intval($params['thug_id'] ?? 0);
    if ($thugId) {
        $stmt = $pdo->prepare("SELECT * FROM {$prefix}thug WHERE id = ? AND user_id = ?");
        $stmt->execute([$thugId, $userId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM {$prefix}thug WHERE user_id = ?");
        $stmt->execute([$userId]);
    }
    $thug = $stmt->fetch(PDO::FETCH_ASSOC);
    $thugId = $thug ? $thug['id'] : 0;
}

// 路由分发
switch ($action) {
    case 'info':
        $enabled = intval(getConfig($pdo, $prefix, 'qiangdan_enabled', '1'));
        $price = floatval(getConfig($pdo, $prefix, 'qiangdan_price', '25'));
        $freeThugs = getConfig($pdo, $prefix, 'qiangdan_free_thugs', '');

        if (!$thug) {
            success('获取成功', [
                'enabled' => $enabled,
                'price' => $price,
                'is_subscribed' => false,
                'is_free' => false,
                'expire_time' => 0,
                'expire_text' => '',
                'qiangdan_status' => 0,
                'config' => (object)[],
            ]);
        }

        $expireTime = intval($thug['qiangdan_expire_time']);
        $now = time();
        $isSubscribed = ($expireTime > $now);

        $freeList = array_filter(explode(',', $freeThugs));
        $isFree = in_array($thugId, $freeList);

        if ($isFree && !$isSubscribed) {
            $isSubscribed = true;
            $expireTime = $now + 365 * 24 * 3600;
        }

        $expireText = $isSubscribed ? date('Y-m-d H:i', $expireTime) : '';
        $config = json_decode($thug['qiangdan_config'], true);
        if (!$config) {
            $config = ['exclude_remark' => false, 'exclude_value_added' => false, 'keywords' => '', 'exclude_keywords' => ''];
        }

        success('获取成功', [
            'enabled' => $enabled,
            'price' => $price,
            'is_subscribed' => $isSubscribed,
            'is_free' => $isFree,
            'expire_time' => $expireTime,
            'expire_text' => $expireText,
            'qiangdan_status' => intval($thug['qiangdan_status']),
            'config' => $config,
        ]);
        break;

    case 'toggle':
        if (!$thug) error('打手信息不存在');

        $now = time();
        $expireTime = intval($thug['qiangdan_expire_time']);
        $freeThugs = getConfig($pdo, $prefix, 'qiangdan_free_thugs', '');
        $freeList = array_filter(explode(',', $freeThugs));
        $isFree = in_array($thugId, $freeList);

        if (!$isFree && $expireTime <= $now) {
            error('请先开通抢单神器');
        }

        $status = intval($params['status'] ?? 0) ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE {$prefix}thug SET qiangdan_status = ? WHERE id = ?");
        $stmt->execute([$status, $thugId]);

        success('操作成功', ['qiangdan_status' => $status]);
        break;

    case 'saveConfig':
        if (!$thug) error('打手信息不存在');

        $config = [
            'exclude_remark' => !empty($params['exclude_remark']),
            'exclude_value_added' => !empty($params['exclude_value_added']),
            'keywords' => $params['keywords'] ?? '',
            'exclude_keywords' => $params['exclude_keywords'] ?? '',
        ];

        $stmt = $pdo->prepare("UPDATE {$prefix}thug SET qiangdan_config = ? WHERE id = ?");
        $stmt->execute([json_encode($config, JSON_UNESCAPED_UNICODE), $thugId]);

        success('保存成功', $config);
        break;

    case 'logs':
        $page = intval($params['page'] ?? 1);
        $limit = intval($params['limit'] ?? 20);
        $type = $params['type'] ?? 'all';

        $where = "thug_id = ?";
        $bindParams = [$thugId];
        if ($type === 'today') {
            $where .= " AND createtime >= ?";
            $bindParams[] = strtotime(date('Y-m-d'));
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$prefix}qiangdan_log WHERE {$where}");
        $stmt->execute($bindParams);
        $total = $stmt->fetchColumn();

        $offset = ($page - 1) * $limit;
        $stmt = $pdo->prepare("SELECT * FROM {$prefix}qiangdan_log WHERE {$where} ORDER BY createtime DESC LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($bindParams);
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$prefix}qiangdan_log WHERE thug_id = ? AND status = 'success'");
        $stmt->execute([$thugId]);
        $totalGrabbed = $stmt->fetchColumn();

        $todayStart = strtotime(date('Y-m-d'));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$prefix}qiangdan_log WHERE thug_id = ? AND status = 'success' AND createtime >= ?");
        $stmt->execute([$thugId, $todayStart]);
        $todayGrabbed = $stmt->fetchColumn();

        success('获取成功', [
            'list' => $list,
            'total' => intval($total),
            'total_grabbed' => intval($totalGrabbed),
            'today_grabbed' => intval($todayGrabbed),
        ]);
        break;

    case 'purchase':
        if (!$thug) error('打手信息不存在');

        // 免费打手直接开通
        $freeThugs = getConfig($pdo, $prefix, 'qiangdan_free_thugs', '');
        $freeList = array_filter(explode(',', $freeThugs));
        if (in_array($thugId, $freeList)) {
            $expireTime = time() + 365 * 24 * 3600;
            $stmt = $pdo->prepare("UPDATE {$prefix}thug SET qiangdan_expire_time = ?, qiangdan_status = 1 WHERE id = ?");
            $stmt->execute([$expireTime, $thugId]);
            success('已为您免费开通', [
                'expire_time' => $expireTime,
                'expire_text' => date('Y-m-d H:i', $expireTime),
            ]);
        }

        // 检查开关
        $enabled = intval(getConfig($pdo, $prefix, 'qiangdan_enabled', '1'));
        if (!$enabled) error('抢单神器暂未开放');

        $days = max(1, intval($params['days'] ?? 1));
        $price = floatval(getConfig($pdo, $prefix, 'qiangdan_price', '25'));
        $amount = $price * $days;

        // 创建订单
        $orderNo = 'QD' . date('YmdHis') . mt_rand(1000, 9999);
        $stmt = $pdo->prepare("INSERT INTO {$prefix}qiangdan_order (order_no, thug_id, amount, days, status, pay_type, createtime, updatetime) VALUES (?, ?, ?, ?, 'pending', 'wechat', ?, ?)");
        $stmt->execute([$orderNo, $thugId, $amount, $days, time(), time()]);

        success('下单成功', [
            'pay_url' => '',
            'out_trade_no' => $orderNo,
            'scene' => 'qiangdan',
            'amount' => $amount,
        ]);
        break;

    case 'sync':
        $outTradeNo = $params['out_trade_no'] ?? '';
        if (!$outTradeNo) error('参数错误');

        $stmt = $pdo->prepare("SELECT status FROM {$prefix}qiangdan_order WHERE order_no = ?");
        $stmt->execute([$outTradeNo]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) error('订单不存在');

        success('查询成功', ['paid' => $order['status'] === 'paid']);
        break;

    default:
        error('接口不存在: ' . $action);
}
