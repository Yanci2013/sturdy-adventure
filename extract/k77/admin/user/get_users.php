<?php
// 调试模式
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 引入数据库连接
require_once __DIR__.'/../../../config.php';

header('Content-Type: application/json');

// 调试数据库连接
try {
    $test = $pdo->query("SELECT 1");
    if(!$test) {
        throw new Exception("数据库连接测试失败");
    }
    error_log("数据库连接测试成功");
} catch (Exception $e) {
    error_log("数据库连接测试失败: " . $e->getMessage());
    echo json_encode([
        'code' => 1,
        'msg' => '数据库连接失败',
        'count' => 0,
        'data' => []
    ]);
    exit;
}

try {
    // 获取请求参数
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, intval($_GET['limit'] ?? 10));
    $offset = ($page - 1) * $limit;

    // 构建查询条件
    $where = [];
    $params = [];

    // 筛选条件
    if(!empty($_GET['id'])) {
        $where[] = "id = ?";
        $params[] = $_GET['id'];
    }

    if(!empty($_GET['username'])) {
        $where[] = "username LIKE ?";
        $params[] = '%'.$_GET['username'].'%';
    }

    if(!empty($_GET['phone'])) {
        $where[] = "phone LIKE ?";
        $params[] = '%'.$_GET['phone'].'%';
    }

    if(isset($_GET['gender']) && $_GET['gender'] != '0') {
        $where[] = "gender = ?";
        $params[] = $_GET['gender'];
    }

    if(isset($_GET['status']) && $_GET['status'] !== '') {
        $where[] = "status = ?";
        $params[] = $_GET['status'];
    }

    $whereClause = $where ? 'WHERE '.implode(' AND ', $where) : '';

    // 查询总数
    $totalSql = "SELECT COUNT(*) FROM h5user $whereClause";
    $stmt = $pdo->prepare($totalSql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

    // 查询数据
    $dataSql = "SELECT 
                id, username, nickname, phone, gender, 
                balance, deposit, user_level, vip_level, 
                status, created_at, last_ip, avatar 
                FROM h5user $whereClause 
                ORDER BY id DESC 
                LIMIT ?, ?";
    
    $params[] = $offset;
    $params[] = $limit;
    
    $stmt = $pdo->prepare($dataSql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 处理头像路径
    foreach($data as &$item) {
        if(empty($item['avatar']) || $item['avatar'] == 'default_avatar.png') {
            $item['avatar'] = '../../assets/images/default_avatar.png';
        } else {
            $item['avatar'] = '../../uploads/avatars/'.$item['avatar'];
        }
    }

    // 返回结果
    echo json_encode([
        'code' => 0,
        'msg' => '',
        'count' => $total,
        'data' => $data
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'code' => 1,
        'msg' => '数据库错误: '.$e->getMessage(),
        'count' => 0,
        'data' => []
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'code' => 1,
        'msg' => '系统错误: '.$e->getMessage(),
        'count' => 0,
        'data' => []
    ]);
}
?>