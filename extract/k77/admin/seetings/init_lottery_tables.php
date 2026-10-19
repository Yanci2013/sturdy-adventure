<?php
require_once __DIR__.'/../../includes/common.php';

function createLotteryTables($pdo) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS lottery_prizes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL COMMENT '奖品名称',
            description TEXT COMMENT '奖品描述',
            image VARCHAR(255) DEFAULT '' COMMENT '奖品图片',
            type ENUM('product', 'coupon', 'points', 'thank_you') DEFAULT 'product' COMMENT '奖品类型',
            product_id INT DEFAULT NULL COMMENT '关联商品ID',
            probability DECIMAL(10,4) DEFAULT 0.0000 COMMENT '中奖概率(0-1)',
            stock INT DEFAULT 0 COMMENT '奖品库存',
            sort_order INT DEFAULT 0 COMMENT '排序',
            status TINYINT(1) DEFAULT 1 COMMENT '状态：1-启用，0-禁用',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_sort (sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='抽奖奖品表'";
        
        $pdo->exec($sql);
        
        $sql = "CREATE TABLE IF NOT EXISTS lottery_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL COMMENT '用户ID',
            prize_id INT DEFAULT NULL COMMENT '奖品ID',
            prize_name VARCHAR(255) DEFAULT '' COMMENT '奖品名称',
            prize_type VARCHAR(50) DEFAULT '' COMMENT '奖品类型',
            is_win TINYINT(1) DEFAULT 0 COMMENT '是否中奖',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='抽奖记录表'";
        
        $pdo->exec($sql);
        
        echo "抽奖相关表创建成功！";
    } catch (Exception $e) {
        echo "创建表失败: " . $e->getMessage();
    }
}

createLotteryTables($pdo);
?>