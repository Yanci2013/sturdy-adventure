<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../../includes/session_manager.php';
require_once __DIR__.'/../../includes/common.php';

function checkAndCreateLotteryTables($pdo) {
    try {
        $tables = $pdo->query("SHOW TABLES LIKE 'lottery_prizes'")->rowCount();
        if ($tables == 0) {
            $sql = "CREATE TABLE lottery_prizes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                image VARCHAR(255) DEFAULT '',
                type VARCHAR(50) DEFAULT 'product',
                product_id INT DEFAULT NULL,
                probability DECIMAL(10,4) DEFAULT 0.0000,
                stock INT DEFAULT 0,
                sort_order INT DEFAULT 0,
                status TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $pdo->exec($sql);
        }
        
        $tables = $pdo->query("SHOW TABLES LIKE 'lottery_records'")->rowCount();
        if ($tables == 0) {
            $sql = "CREATE TABLE lottery_records (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                prize_id INT DEFAULT NULL,
                prize_name VARCHAR(255) DEFAULT '',
                prize_type VARCHAR(50) DEFAULT '',
                is_win TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $pdo->exec($sql);
        }
    } catch (Exception $e) {
    }
}

checkAndCreateLotteryTables($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'draw') {
        if (!is_h5_logged_in()) {
            echo json_encode(['success' => false, 'message' => '请先登录']);
            exit;
        }
        
        $user_id = get_h5_user_id();
        
        $prizes = $pdo->query("SELECT * FROM lottery_prizes WHERE status = 1 ORDER BY sort_order DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($prizes)) {
            echo json_encode(['success' => false, 'message' => '暂无可抽奖品']);
            exit;
        }
        
        $totalProbability = 0;
        $availablePrizes = [];
        foreach ($prizes as $prize) {
            if ($prize['stock'] > 0 || $prize['type'] === 'thank_you') {
                $totalProbability += floatval($prize['probability']);
                $availablePrizes[] = $prize;
            }
        }
        
        if (empty($availablePrizes)) {
            echo json_encode(['success' => false, 'message' => '奖品已抽完']);
            exit;
        }
        
        $random = mt_rand() / mt_getrandmax();
        $cumulative = 0;
        $wonPrize = null;
        
        foreach ($availablePrizes as $prize) {
            $adjustedProb = floatval($prize['probability']) / $totalProbability;
            $cumulative += $adjustedProb;
            if ($random <= $cumulative) {
                $wonPrize = $prize;
                break;
            }
        }
        
        if (!$wonPrize) {
            $wonPrize = $availablePrizes[count($availablePrizes) - 1];
        }
        
        $isWin = $wonPrize['type'] !== 'thank_you';
        
        if ($isWin && $wonPrize['stock'] > 0) {
            $stmt = $pdo->prepare("UPDATE lottery_prizes SET stock = stock - 1 WHERE id = ?");
            $stmt->execute([$wonPrize['id']]);
        }
        
        $stmt = $pdo->prepare("INSERT INTO lottery_records (user_id, prize_id, prize_name, prize_type, is_win) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $wonPrize['id'], $wonPrize['name'], $wonPrize['type'], $isWin ? 1 : 0]);
        
        echo json_encode([
            'success' => true,
            'is_win' => $isWin,
            'prize' => [
                'id' => $wonPrize['id'],
                'name' => $wonPrize['name'],
                'description' => $wonPrize['description'],
                'image' => $wonPrize['image'],
                'type' => $wonPrize['type'],
                'product_id' => $wonPrize['product_id']
            ]
        ]);
        exit;
    }
    
    if ($action === 'get_prizes') {
        $prizes = $pdo->query("SELECT * FROM lottery_prizes WHERE status = 1 ORDER BY sort_order DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'prizes' => $prizes]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => '无效请求']);
?>