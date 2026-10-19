<?php
// 设置根目录为全局包含路径
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/../');

/*
// 域名授权检测功能已被禁用 - 用于测试目的
// 以下代码已被注释掉以便测试系统在缺少该功能时的运行情况

/**
 * 获取当前服务器域名（不带协议）
 * @return string 当前域名
 */
/*
function getCurrentDomain() {
    $domain = $_SERVER['HTTP_HOST'] ?? '';
    // 移除端口号（如果有）
    $domain = preg_replace('/:\d+$/', '', $domain);
    return strtolower(trim($domain)); // 转为小写并去除空格
}

/**
 * 验证域名授权状态
 * @return array 返回验证结果 [
 *     'is_valid' => bool,
 *     'authorized_domain' => string,
 *     'current_domain' => string
 * ]
 */
/*
function validateDomainAuthorization() {
    $currentDomain = getCurrentDomain();
    $apiUrl = "https://auth.acloudn.com/api/syxtapi.php?content=" . urlencode($currentDomain);
    
    try {
        // 获取API响应（纯文本格式）
        $response = file_get_contents($apiUrl);
        if ($response === false) {
            throw new Exception("无法连接到授权验证服务");
        }
        
        // 处理响应文本
        $authorizedDomain = strtolower(trim($response));
        
        return [
            'is_valid' => ($authorizedDomain === $currentDomain),
            'authorized_domain' => $authorizedDomain ?: '无授权信息',
            'current_domain' => $currentDomain
        ];
        
    } catch (Exception $e) {
        error_log("授权验证失败: " . $e->getMessage());
        return [
            'is_valid' => false,
            'authorized_domain' => '验证服务不可用',
            'current_domain' => $currentDomain,
            'error' => $e->getMessage()
        ];
    }
}

// 执行域名授权验证
$authResult = validateDomainAuthorization();

// 如果验证失败，显示盗版警告
if (!$authResult['is_valid']) {
    $warningMessage = <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <title>系统授权验证失败</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f8f9fa;
                margin: 0;
                padding: 0;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
            }
            .warning-container {
                background-color: #fff;
                border-left: 5px solid #dc3545;
                box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
                padding: 2rem;
                max-width: 600px;
                width: 100%;
                text-align: center;
            }
            h1 {
                color: #dc3545;
                margin-top: 0;
            }
            .domain-info {
                background-color: #f8f9fa;
                padding: 1rem;
                margin: 1rem 0;
                border-radius: 4px;
                text-align: left;
            }
            .footer {
                margin-top: 1.5rem;
                font-size: 0.9em;
                color: #6c757d;
            }
            .auth-button {
                display: inline-block;
                background-color: #28a745;
                color: white;
                padding: 10px 20px;
                text-decoration: none;
                border-radius: 4px;
                margin-top: 15px;
                font-weight: bold;
                transition: background-color 0.3s;
            }
            .auth-button:hover {
                background-color: #218838;
            }
        </style>
    </head>
    <body>
        <div class="warning-container">
            <h1>⚠️ 系统授权验证失败</h1>
            <p>检测到未经授权的系统版本，所有功能已被禁用。</p>
            
            <div class="domain-info">
                <strong>盗版域名:</strong> {$authResult['current_domain']}<br>
                <strong>授权状态:</strong> 未授权
            </div>
            
            <p>请使用正版授权系统以获得完整功能</p>
            
            <a href="https://qm.qq.com/q/XEVHQaqgUw" class="auth-button">
                联系官方获取授权
            </a>
            
            <div class="footer">
                错误代码: DOMAIN_AUTH_FAILED
            </div>
        </div>
    </body>
    </html>
HTML;
    die($warningMessage);
}
*/

// 正常引入其他文件
require_once 'config.php';
require_once 'functions.php';

// 自动修复表结构函数
function autoFixTableStructure($db) {
    // 修复product_categories表
    $requiredFields = [
        'parent_id' => "int(11) DEFAULT 0 COMMENT '父分类ID，0表示一级分类'",
        'level' => "tinyint(1) DEFAULT 1 COMMENT '分类级别：1-一级分类，2-二级分类'",
        'status' => "tinyint(1) DEFAULT 1 COMMENT '状态：1-启用，0-禁用'",
        'sort_order' => "int(11) DEFAULT 0 COMMENT '排序'",
        'logo' => "varchar(255) DEFAULT '' COMMENT '分类图标'"
    ];
    
    foreach ($requiredFields as $fieldName => $fieldDef) {
        // 直接使用字符串拼接，因为SHOW COLUMNS不支持预处理占位符
        // 字段名来自固定数组，不会有SQL注入风险
        $sql = "SHOW COLUMNS FROM product_categories LIKE '$fieldName'";
        $checkStmt = $db->query($sql);
        $exists = $checkStmt->rowCount() > 0;
        
        if (!$exists) {
            $sql = "ALTER TABLE product_categories ADD COLUMN $fieldName $fieldDef";
            $db->exec($sql);
        }
    }
    
    // 确保所有分类都有默认的parent_id值
    $db->exec("UPDATE product_categories SET parent_id = 0 WHERE parent_id IS NULL");
    
    // 更新分类级别
    $db->exec("UPDATE product_categories SET level = 1 WHERE parent_id = 0");
    $db->exec("UPDATE product_categories SET level = 2 WHERE parent_id > 0");
}

// 自动修复invitations表结构函数
function autoFixInvitationsTable($db) {
    // 检查并添加缺失的字段
    $requiredFields = [
        'gift_member_level' => "int(11) DEFAULT NULL COMMENT '赠送会员级别'",
        'gift_member_duration' => "varchar(20) DEFAULT NULL COMMENT '赠送会员时长：month/quarter/year'",
        'gift_points' => "int(11) DEFAULT NULL COMMENT '赠送积分'",
        'generator_type' => "varchar(20) DEFAULT 'admin' COMMENT '生成者类型：admin/eadmin'",
        'generator_id' => "int(11) DEFAULT 1 COMMENT '生成者ID'",
        'created_at' => "datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'"
    ];
    
    foreach ($requiredFields as $fieldName => $fieldDef) {
        // 直接使用字符串拼接，因为SHOW COLUMNS不支持预处理占位符
        // 字段名来自固定数组，不会有SQL注入风险
        $sql = "SHOW COLUMNS FROM invitations LIKE '$fieldName'";
        $checkStmt = $db->query($sql);
        $exists = $checkStmt->rowCount() > 0;
        
        if (!$exists) {
            $sql = "ALTER TABLE invitations ADD COLUMN $fieldName $fieldDef";
            $db->exec($sql);
        }
    }
}

// 自动修复聊天相关表结构函数
function autoFixChatTables($db) {
    try {
        // 创建chat_sessions表
        $createSql = "
            CREATE TABLE IF NOT EXISTS chat_sessions (
                session_id VARCHAR(36) PRIMARY KEY,
                session_name VARCHAR(255) DEFAULT NULL,
                session_type ENUM('private', 'group') DEFAULT 'private',
                order_id INT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_order_id (order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $db->exec($createSql);
        
        // 创建chat_participants表
        $createSql = "
            CREATE TABLE IF NOT EXISTS chat_participants (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id VARCHAR(36) NOT NULL,
                user_id INT NOT NULL,
                user_type ENUM('admin', 'partner', 'user', 'h5user') NOT NULL,
                joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_session_id (session_id),
                INDEX idx_user (user_id, user_type),
                FOREIGN KEY (session_id) REFERENCES chat_sessions(session_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $db->exec($createSql);
        
        // 创建chat_messages表
        $createSql = "
            CREATE TABLE IF NOT EXISTS chat_messages (
                message_id VARCHAR(36) PRIMARY KEY,
                session_id VARCHAR(36) NOT NULL,
                sender_id INT NOT NULL,
                sender_type ENUM('admin', 'partner', 'user', 'h5user') NOT NULL,
                content TEXT NOT NULL,
                message_type ENUM('text', 'image', 'file') DEFAULT 'text',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_session_id (session_id),
                INDEX idx_sender (sender_id, sender_type),
                FOREIGN KEY (session_id) REFERENCES chat_sessions(session_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $db->exec($createSql);
        
        // 创建chat_message_status表
        $createSql = "
            CREATE TABLE IF NOT EXISTS chat_message_status (
                id INT AUTO_INCREMENT PRIMARY KEY,
                message_id VARCHAR(36) NOT NULL,
                user_id INT NOT NULL,
                user_type ENUM('admin', 'partner', 'user', 'h5user') NOT NULL,
                is_read TINYINT(1) DEFAULT 0,
                read_at DATETIME DEFAULT NULL,
                INDEX idx_message_id (message_id),
                INDEX idx_user (user_id, user_type),
                INDEX idx_read_status (is_read),
                FOREIGN KEY (message_id) REFERENCES chat_messages(message_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $db->exec($createSql);
    } catch (PDOException $e) {
        // 忽略表创建错误，继续执行
        error_log("自动修复聊天表结构错误: " . $e->getMessage());
    }
}

// 自动修复会员等级表结构函数
function autoFixMemberLevelsTable($db) {
    try {
        // 创建member_levels表
        $createSql = "
            CREATE TABLE IF NOT EXISTS member_levels (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL COMMENT '等级名称',
                description TEXT DEFAULT NULL COMMENT '等级描述',
                trial_supported TINYINT(1) DEFAULT 0 COMMENT '是否支持体验',
                daily_price DECIMAL(10,2) DEFAULT 0.00 COMMENT '日付价格',
                weekly_price DECIMAL(10,2) DEFAULT 0.00 COMMENT '周付价格',
                monthly_price DECIMAL(10,2) DEFAULT 0.00 COMMENT '月付价格',
                quarterly_price DECIMAL(10,2) DEFAULT 0.00 COMMENT '季度价格',
                yearly_price DECIMAL(10,2) DEFAULT 0.00 COMMENT '年付价格',
                upgrade_price DECIMAL(10,2) DEFAULT 0.00 COMMENT '体验升级价格',
                points_given INT DEFAULT 0 COMMENT '开通赠送积分',
                points_rate DECIMAL(10,2) DEFAULT 1.00 COMMENT '积分价值(1元=多少积分)',
                min_points_for_order INT DEFAULT 0 COMMENT '最低接单积分',
                min_points_for_withdraw INT DEFAULT 0 COMMENT '最低提现积分',
                commission_rate DECIMAL(10,2) DEFAULT 0.00 COMMENT '返佣比例(%)',
                min_commission_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT '最低返佣金额',
                max_commission_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT '最高返佣金额',
                commission_type TINYINT(1) DEFAULT 1 COMMENT '返佣类型：1-比例返佣，2-固定金额返佣',
                fixed_commission_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT '固定返佣金额',
                commission_cycle TINYINT(1) DEFAULT 1 COMMENT '返佣周期：1-实时，2-按月，3-按季度',
                commission_enabled TINYINT(1) DEFAULT 0 COMMENT '是否启用返佣',
                status TINYINT(1) DEFAULT 1 COMMENT '启用状态',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $db->exec($createSql);
        
        // 创建member_level_categories表（关联会员等级和商品分类）
        $createSql = "
            CREATE TABLE IF NOT EXISTS member_level_categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                member_level_id INT NOT NULL COMMENT '会员等级ID',
                category_id INT NOT NULL COMMENT '分类ID',
                FOREIGN KEY (member_level_id) REFERENCES member_levels(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE CASCADE,
                UNIQUE KEY idx_member_category (member_level_id, category_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $db->exec($createSql);
        
        // 检查并添加默认会员等级
        $stmt = $db->query("SELECT COUNT(*) FROM member_levels");
        $count = $stmt->fetchColumn();
        
        if ($count === 0) {
            // 插入默认会员等级
            $defaultLevels = [
                ['name' => '普通会员', 'description' => '基础会员等级', 'trial_supported' => 0, 'daily_price' => 0.00, 'weekly_price' => 0.00, 'monthly_price' => 0.00, 'quarterly_price' => 0.00, 'yearly_price' => 0.00, 'upgrade_price' => 0.00, 'points_given' => 0, 'points_rate' => 1.00, 'min_points_for_order' => 0, 'min_points_for_withdraw' => 0, 'commission_rate' => 0.00, 'min_commission_amount' => 0.00, 'max_commission_amount' => 0.00, 'commission_type' => 1, 'fixed_commission_amount' => 0.00, 'commission_cycle' => 1, 'commission_enabled' => 0, 'status' => 1],
                ['name' => '高级会员', 'description' => '高级会员等级', 'trial_supported' => 1, 'daily_price' => 9.90, 'weekly_price' => 29.90, 'monthly_price' => 99.00, 'quarterly_price' => 269.00, 'yearly_price' => 999.00, 'upgrade_price' => 59.00, 'points_given' => 1000, 'points_rate' => 1.00, 'min_points_for_order' => 500, 'min_points_for_withdraw' => 1000, 'commission_rate' => 10.00, 'min_commission_amount' => 5.00, 'max_commission_amount' => 500.00, 'commission_type' => 1, 'fixed_commission_amount' => 0.00, 'commission_cycle' => 1, 'commission_enabled' => 1, 'status' => 1],
                ['name' => 'VIP会员', 'description' => 'VIP会员等级', 'trial_supported' => 1, 'daily_price' => 19.90, 'weekly_price' => 59.90, 'monthly_price' => 199.00, 'quarterly_price' => 529.00, 'yearly_price' => 1999.00, 'upgrade_price' => 119.00, 'points_given' => 3000, 'points_rate' => 1.50, 'min_points_for_order' => 0, 'min_points_for_withdraw' => 500, 'commission_rate' => 15.00, 'min_commission_amount' => 10.00, 'max_commission_amount' => 1000.00, 'commission_type' => 1, 'fixed_commission_amount' => 0.00, 'commission_cycle' => 1, 'commission_enabled' => 1, 'status' => 1]
            ];
            
            $stmt = $db->prepare("INSERT INTO member_levels (name, description, trial_supported, daily_price, weekly_price, monthly_price, quarterly_price, yearly_price, upgrade_price, points_given, points_rate, min_points_for_order, min_points_for_withdraw, commission_rate, min_commission_amount, max_commission_amount, commission_type, fixed_commission_amount, commission_cycle, commission_enabled, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($defaultLevels as $level) {
                $stmt->execute([$level['name'], $level['description'], $level['trial_supported'], $level['daily_price'], $level['weekly_price'], $level['monthly_price'], $level['quarterly_price'], $level['yearly_price'], $level['upgrade_price'], $level['points_given'], $level['points_rate'], $level['min_points_for_order'], $level['min_points_for_withdraw'], $level['commission_rate'], $level['min_commission_amount'], $level['max_commission_amount'], $level['commission_type'], $level['fixed_commission_amount'], $level['commission_cycle'], $level['commission_enabled'], $level['status']]);
            }
        } else {
            // 检查并添加缺失的字段
            $requiredFields = [
                'trial_supported' => "TINYINT(1) DEFAULT 0 COMMENT '是否支持体验'",
                'daily_price' => "DECIMAL(10,2) DEFAULT 0.00 COMMENT '日付价格'",
                'weekly_price' => "DECIMAL(10,2) DEFAULT 0.00 COMMENT '周付价格'",
                'yearly_price' => "DECIMAL(10,2) DEFAULT 0.00 COMMENT '年付价格'",
                'upgrade_price' => "DECIMAL(10,2) DEFAULT 0.00 COMMENT '体验升级价格'",
                'points_given' => "INT DEFAULT 0 COMMENT '开通赠送积分'",
                'points_rate' => "DECIMAL(10,2) DEFAULT 1.00 COMMENT '积分价值(1元=多少积分)'",
                'min_points_for_order' => "INT DEFAULT 0 COMMENT '最低接单积分'",
                'min_points_for_withdraw' => "INT DEFAULT 0 COMMENT '最低提现积分'",
                'commission_rate' => "DECIMAL(10,2) DEFAULT 0.00 COMMENT '返佣比例(%)'",
                'min_commission_amount' => "DECIMAL(10,2) DEFAULT 0.00 COMMENT '最低返佣金额'",
                'max_commission_amount' => "DECIMAL(10,2) DEFAULT 0.00 COMMENT '最高返佣金额'",
                'commission_type' => "TINYINT(1) DEFAULT 1 COMMENT '返佣类型：1-比例返佣，2-固定金额返佣'",
                'fixed_commission_amount' => "DECIMAL(10,2) DEFAULT 0.00 COMMENT '固定返佣金额'",
                'commission_cycle' => "TINYINT(1) DEFAULT 1 COMMENT '返佣周期：1-实时，2-按月，3-按季度'",
                'commission_enabled' => "TINYINT(1) DEFAULT 0 COMMENT '是否启用返佣'",
                'status' => "TINYINT(1) DEFAULT 1 COMMENT '启用状态'"
            ];
            
            foreach ($requiredFields as $fieldName => $fieldDef) {
                $sql = "SHOW COLUMNS FROM member_levels LIKE '$fieldName'";
                $checkStmt = $db->query($sql);
                $exists = $checkStmt->rowCount() > 0;
                
                if (!$exists) {
                    $sql = "ALTER TABLE member_levels ADD COLUMN $fieldName $fieldDef";
                    $db->exec($sql);
                }
            }
        }
    } catch (PDOException $e) {
        // 忽略表创建错误，继续执行
        error_log("自动修复会员等级表结构错误: " . $e->getMessage());
    }
}

// 自动修复商品表结构函数
function autoFixProductsTable($db) {
    try {
        // 创建products表
        $createSql = "
            CREATE TABLE IF NOT EXISTS products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                category_id INT NOT NULL COMMENT '分类ID',
                name VARCHAR(255) NOT NULL COMMENT '商品名称（简介）',
                chinese_name VARCHAR(255) DEFAULT '' COMMENT '商品名称（完整）',
                product_code VARCHAR(50) DEFAULT '' COMMENT '商品编号',
                images TEXT DEFAULT NULL COMMENT '商品图片JSON',
                original_price DECIMAL(10,2) DEFAULT NULL COMMENT '原价',
                price DECIMAL(10,2) NOT NULL COMMENT '现价',
                shipment_quantity INT NOT NULL DEFAULT 1 COMMENT '每份发货数量',
                stock INT DEFAULT 0 COMMENT '库存',
                unit VARCHAR(20) DEFAULT '单' COMMENT '单位',
                max_purchase_per_user INT DEFAULT NULL COMMENT '每人最大购买数量',
                min_purchase_per_user INT NOT NULL DEFAULT 1 COMMENT '每人最小购买数量',
                description TEXT DEFAULT NULL COMMENT '商品描述',
                notes TEXT DEFAULT NULL COMMENT '备注',
                input_fields TEXT DEFAULT NULL COMMENT '输入字段配置JSON',
                default_input VARCHAR(255) DEFAULT '' COMMENT '默认输入',
                game_region_options TEXT DEFAULT NULL COMMENT '游戏大区选项JSON',
                coach_selection_type VARCHAR(20) DEFAULT 'system' COMMENT '教练选择类型',
                sort_order INT DEFAULT NULL COMMENT '排序',
                merchant_price DECIMAL(10,2) DEFAULT NULL COMMENT '商家价格',
                coach_settlement_price DECIMAL(10,2) DEFAULT NULL COMMENT '教练结算价格',
                main_image VARCHAR(255) DEFAULT NULL COMMENT '商品主图',
                game_type VARCHAR(50) DEFAULT '' COMMENT '游戏类型',
                service_type VARCHAR(50) DEFAULT '' COMMENT '服务类型',
                virtual_sales INT DEFAULT 0 COMMENT '虚拟销量',
                tags TEXT DEFAULT NULL COMMENT '商品标签JSON',
                region_prices TEXT DEFAULT NULL COMMENT '区域价格JSON',
                wheel_prize TEXT DEFAULT NULL COMMENT '大转盘设置JSON',
                price_increase TEXT DEFAULT NULL COMMENT '涨价设置JSON',
                status TINYINT(1) DEFAULT 1 COMMENT '状态：1-启用，0-禁用',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_category_id (category_id),
                INDEX idx_status (status),
                INDEX idx_sort_order (sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $db->exec($createSql);
        
        // 检查并添加缺失的字段
        $requiredFields = [
            'original_price' => "DECIMAL(10,2) DEFAULT NULL COMMENT '原价'",
            'chinese_name' => "VARCHAR(255) DEFAULT '' COMMENT '商品名称（完整）'",
            'product_code' => "VARCHAR(50) DEFAULT '' COMMENT '商品编号'",
            'shipment_quantity' => "INT NOT NULL DEFAULT 1 COMMENT '每份发货数量'",
            'max_purchase_per_user' => "INT DEFAULT NULL COMMENT '每人最大购买数量'",
            'min_purchase_per_user' => "INT NOT NULL DEFAULT 1 COMMENT '每人最小购买数量'",
            'notes' => "TEXT DEFAULT NULL COMMENT '备注'",
            'input_fields' => "TEXT DEFAULT NULL COMMENT '输入字段配置JSON'",
            'default_input' => "VARCHAR(255) DEFAULT '' COMMENT '默认输入'",
            'game_region_options' => "TEXT DEFAULT NULL COMMENT '游戏大区选项JSON'",
            'coach_selection_type' => "VARCHAR(20) DEFAULT 'system' COMMENT '教练选择类型'",
            'sort_order' => "INT DEFAULT NULL COMMENT '排序'",
            'merchant_price' => "DECIMAL(10,2) DEFAULT NULL COMMENT '商家价格'",
            'coach_settlement_price' => "DECIMAL(10,2) DEFAULT NULL COMMENT '教练结算价格'",
            'main_image' => "VARCHAR(255) DEFAULT NULL COMMENT '商品主图'",
            'game_type' => "VARCHAR(50) DEFAULT '' COMMENT '游戏类型'",
            'service_type' => "VARCHAR(50) DEFAULT '' COMMENT '服务类型'",
            'virtual_sales' => "INT DEFAULT 0 COMMENT '虚拟销量'",
            'tags' => "TEXT DEFAULT NULL COMMENT '商品标签JSON'",
            'region_prices' => "TEXT DEFAULT NULL COMMENT '区域价格JSON'",
            'wheel_prize' => "TEXT DEFAULT NULL COMMENT '大转盘设置JSON'",
            'price_increase' => "TEXT DEFAULT NULL COMMENT '涨价设置JSON'",
            'status' => "TINYINT(1) DEFAULT 1 COMMENT '状态：1-启用，0-禁用'"
        ];
        
        foreach ($requiredFields as $fieldName => $fieldDef) {
            $sql = "SHOW COLUMNS FROM products LIKE '$fieldName'";
            $checkStmt = $db->query($sql);
            $exists = $checkStmt->rowCount() > 0;
            
            if (!$exists) {
                $sql = "ALTER TABLE products ADD COLUMN $fieldName $fieldDef";
                $db->exec($sql);
            }
        }
    } catch (PDOException $e) {
        // 忽略表创建错误，继续执行
        error_log("自动修复商品表结构错误: " . $e->getMessage());
    }
}

// 自动修复系统通知配置表结构函数
function autoFixSysNotificationConfigTable($db) {
    try {
        // 创建sys_notification_config表
        $createSql = "
            CREATE TABLE IF NOT EXISTS sys_notification_config (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sms_provider VARCHAR(50) DEFAULT NULL COMMENT '短信服务商',
                sms_app_id VARCHAR(100) DEFAULT NULL COMMENT '短信AppID',
                sms_app_key VARCHAR(100) DEFAULT NULL COMMENT '短信AppKey',
                sms_sign VARCHAR(50) DEFAULT NULL COMMENT '短信签名',
                sms_content TEXT DEFAULT NULL COMMENT '短信内容模板',
                order_accept_template VARCHAR(255) DEFAULT '' COMMENT '接单通知模板',
                order_complete_template VARCHAR(255) DEFAULT '' COMMENT '订单完成通知模板',
                smtp_host VARCHAR(100) DEFAULT NULL COMMENT 'SMTP服务器',
                smtp_port INT DEFAULT NULL COMMENT 'SMTP端口',
                from_email VARCHAR(100) DEFAULT NULL COMMENT '发件邮箱',
                email_password VARCHAR(100) DEFAULT NULL COMMENT '邮箱密码',
                from_name VARCHAR(50) DEFAULT '' COMMENT '发件人名称',
                smtp_secure VARCHAR(10) DEFAULT 'ssl' COMMENT 'SMTP安全协议',
                create_time DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                update_time DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $db->exec($createSql);
        
        // 检查并添加默认记录
        $stmt = $db->query("SELECT id FROM sys_notification_config LIMIT 1");
        if ($stmt && $stmt->rowCount() === 0) {
            // 插入默认配置
            $db->exec("INSERT INTO sys_notification_config (sms_provider, smtp_secure) VALUES ('smsbao', 'ssl')");
        }
    } catch (PDOException $e) {
        // 忽略表创建错误，继续执行
        error_log("自动修复系统通知配置表结构错误: " . $e->getMessage());
    }
}

// 自动修复临时订单表结构函数
function autoFixOrderTempTable($db) {
    try {
        // 创建order_temp表
        $createSql = "
            CREATE TABLE IF NOT EXISTS order_temp (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_no VARCHAR(50) NOT NULL COMMENT '订单编号',
                product_id INT DEFAULT 0 COMMENT '商品ID',
                product_name VARCHAR(255) NOT NULL COMMENT '商品名称',
                price DECIMAL(10,2) DEFAULT 0.00 COMMENT '商品价格',
                payment_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT '支付金额',
                game_region VARCHAR(50) DEFAULT '' COMMENT '游戏大区',
                hunter_option VARCHAR(50) DEFAULT '' COMMENT '打手选择方式',
                hunter_id INT DEFAULT 0 COMMENT '指定打手ID',
                input_fields TEXT COMMENT '其他输入字段(JSON格式)',
                category_id INT DEFAULT 0 COMMENT '分类ID',
                category_name VARCHAR(255) DEFAULT '' COMMENT '分类名称',
                user_id INT DEFAULT 0 COMMENT '用户ID',
                user_idu VARCHAR(50) DEFAULT '' COMMENT '用户唯一识别码',
                user_nickname VARCHAR(255) DEFAULT '' COMMENT '用户昵称',
                payment_ip VARCHAR(50) DEFAULT '' COMMENT '支付IP',
                digital_id VARCHAR(100) DEFAULT '' COMMENT '数字ID',
                chinese_id VARCHAR(100) DEFAULT '' COMMENT '中文ID',
                notes TEXT DEFAULT NULL COMMENT '备注',
                trade_no VARCHAR(50) DEFAULT '' COMMENT '交易号',
                create_time DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                expire_time DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '过期时间',
                UNIQUE KEY (order_no)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='临时订单表'
        ";
        $db->exec($createSql);
        
        // 检查并添加缺失的字段
        $requiredFields = [
            'order_no' => "VARCHAR(50) NOT NULL COMMENT '订单编号'",
            'product_id' => "INT DEFAULT 0 COMMENT '商品ID'",
            'product_name' => "VARCHAR(255) NOT NULL COMMENT '商品名称'",
            'price' => "DECIMAL(10,2) DEFAULT 0.00 COMMENT '商品价格'",
            'payment_amount' => "DECIMAL(10,2) DEFAULT 0.00 COMMENT '支付金额'",
            'game_region' => "VARCHAR(50) DEFAULT '' COMMENT '游戏大区'",
            'hunter_option' => "VARCHAR(50) DEFAULT '' COMMENT '打手选择方式'",
            'hunter_id' => "INT DEFAULT 0 COMMENT '指定打手ID'",
            'input_fields' => "TEXT COMMENT '其他输入字段(JSON格式)'",
            'category_id' => "INT DEFAULT 0 COMMENT '分类ID'",
            'category_name' => "VARCHAR(255) DEFAULT '' COMMENT '分类名称'",
            'user_id' => "INT DEFAULT 0 COMMENT '用户ID'",
            'user_idu' => "VARCHAR(50) DEFAULT '' COMMENT '用户唯一识别码'",
            'user_nickname' => "VARCHAR(255) DEFAULT '' COMMENT '用户昵称'",
            'payment_ip' => "VARCHAR(50) DEFAULT '' COMMENT '支付IP'",
            'digital_id' => "VARCHAR(100) DEFAULT '' COMMENT '数字ID'",
            'chinese_id' => "VARCHAR(100) DEFAULT '' COMMENT '中文ID'",
            'notes' => "TEXT DEFAULT NULL COMMENT '备注'",
            'trade_no' => "VARCHAR(50) DEFAULT '' COMMENT '交易号'",
            'create_time' => "DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'",
            'expire_time' => "DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '过期时间'"
        ];
        
        foreach ($requiredFields as $fieldName => $fieldDef) {
            $sql = "SHOW COLUMNS FROM order_temp LIKE '$fieldName'";
            $checkStmt = $db->query($sql);
            $exists = $checkStmt->rowCount() > 0;
            
            if (!$exists) {
                $sql = "ALTER TABLE order_temp ADD COLUMN $fieldName $fieldDef";
                $db->exec($sql);
            }
        }
        
        // 确保有唯一索引
        $db->exec("ALTER IGNORE TABLE order_temp ADD UNIQUE KEY (order_no)");
    } catch (PDOException $e) {
        // 忽略表创建错误，继续执行
        error_log("自动修复临时订单表结构错误: " . $e->getMessage());
    }
}

// 自动修复轮播图表结构函数
function autoFixCarouselTable($db) {
    try {
        // 创建carousel表
        $createSql = "
            CREATE TABLE IF NOT EXISTS carousel (
                id INT AUTO_INCREMENT PRIMARY KEY,
                image_path VARCHAR(255) NOT NULL COMMENT '图片路径',
                link_url VARCHAR(255) DEFAULT '#' COMMENT '跳转地址',
                sort_order INT(11) DEFAULT '0' COMMENT '排序权重',
                created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='轮播图表'
        ";
        $db->exec($createSql);
        
        // 检查并添加缺失的字段
        $requiredFields = [
            'image_path' => "VARCHAR(255) NOT NULL COMMENT '图片路径'",
            'link_url' => "VARCHAR(255) DEFAULT '#' COMMENT '跳转地址'",
            'sort_order' => "INT(11) DEFAULT '0' COMMENT '排序权重'",
            'created_at' => "timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP",
            'updated_at' => "timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
        ];
        
        foreach ($requiredFields as $fieldName => $fieldDef) {
            $sql = "SHOW COLUMNS FROM carousel LIKE '$fieldName'";
            $checkStmt = $db->query($sql);
            $exists = $checkStmt->rowCount() > 0;
            
            if (!$exists) {
                $sql = "ALTER TABLE carousel ADD COLUMN $fieldName $fieldDef";
                $db->exec($sql);
            }
        }
    } catch (PDOException $e) {
        // 忽略表创建错误，继续执行
        error_log("自动修复轮播图表结构错误: " . $e->getMessage());
    }
}

// 自动修复h5user表结构函数
function autoFixH5UserTable($db) {
    try {
        // 检查并添加缺失的字段
        $requiredFields = [
            'balance' => "DECIMAL(10,2) DEFAULT 0.00 COMMENT '用户余额'",
            'total_payments' => "DECIMAL(10,2) DEFAULT 0.00 COMMENT '总支付金额'",
            'payment_count' => "INT DEFAULT 0 COMMENT '支付次数'",
            'last_payment_time' => "DATETIME DEFAULT NULL COMMENT '最后支付时间'",
            'social_uid' => "VARCHAR(50) DEFAULT '' COMMENT '社交平台用户ID'",
            'access_token' => "VARCHAR(255) DEFAULT '' COMMENT '社交平台访问令牌'",
            'social_type' => "VARCHAR(20) DEFAULT '' COMMENT '社交平台类型'",
            'avatar' => "VARCHAR(255) DEFAULT '' COMMENT '用户头像'",
            'gender' => "TINYINT(1) DEFAULT 0 COMMENT '性别：0-未知，1-男，2-女'",
            'username' => "VARCHAR(50) DEFAULT '' COMMENT '用户名'",
            'user_idu' => "VARCHAR(32) DEFAULT '' COMMENT '用户唯一识别码'",
            'reg_ip' => "VARCHAR(50) DEFAULT '' COMMENT '注册IP'",
            'last_ip' => "VARCHAR(50) DEFAULT '' COMMENT '最后登录IP'"
        ];
        
        foreach ($requiredFields as $fieldName => $fieldDef) {
            $sql = "SHOW COLUMNS FROM h5user LIKE '$fieldName'";
            $checkStmt = $db->query($sql);
            $exists = $checkStmt->rowCount() > 0;
            
            if (!$exists) {
                $sql = "ALTER TABLE h5user ADD COLUMN $fieldName $fieldDef";
                $db->exec($sql);
            }
        }
    } catch (PDOException $e) {
        // 忽略表创建错误，继续执行
        error_log("自动修复h5user表结构错误: " . $e->getMessage());
    }
}

// 自动创建user_balance_logs表函数
function autoCreateUserBalanceLogsTable($db) {
    try {
        // 检查表是否存在
        $checkSql = "SHOW TABLES LIKE 'user_balance_logs'";
        $checkStmt = $db->query($checkSql);
        $exists = $checkStmt->rowCount() > 0;
        
        if (!$exists) {
            // 创建表
            $createSql = "CREATE TABLE user_balance_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL COMMENT '用户ID',
                change_type ENUM('income', 'expense') NOT NULL COMMENT '变动类型',
                amount DECIMAL(10,2) NOT NULL COMMENT '变动金额',
                balance DECIMAL(10,2) NOT NULL COMMENT '变动后余额',
                source_type VARCHAR(50) NOT NULL COMMENT '来源类型',
                source_id INT DEFAULT NULL COMMENT '来源ID',
                remark VARCHAR(255) DEFAULT '' COMMENT '备注',
                create_time DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                KEY idx_user_id (user_id),
                KEY idx_create_time (create_time)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户余额变动日志表'";
            $db->exec($createSql);
        }
    } catch (PDOException $e) {
        // 忽略表创建错误，继续执行
        error_log("创建user_balance_logs表错误: " . $e->getMessage());
    }
}



// 自动修复withdrawals表结构函数
function autoFixWithdrawalsTable($db) {
    try {
        // 检查并添加缺失的字段
        $requiredFields = [
            'wechat_openid' => "VARCHAR(100) DEFAULT NULL COMMENT '微信OpenID'",
            'alipay_qrcode' => "VARCHAR(255) DEFAULT NULL COMMENT '支付宝收款码'",
            'wechat_qrcode' => "VARCHAR(255) DEFAULT NULL COMMENT '微信收款码'",
            'payment_method' => "ENUM('wechat','alipay') DEFAULT 'wechat' COMMENT '支付方式'",
            'reject_reason' => "TEXT DEFAULT NULL COMMENT '拒绝原因'"
        ];
        
        foreach ($requiredFields as $fieldName => $fieldDef) {
            $sql = "SHOW COLUMNS FROM withdrawals LIKE '$fieldName'";
            $checkStmt = $db->query($sql);
            $exists = $checkStmt->rowCount() > 0;
            
            if (!$exists) {
                $sql = "ALTER TABLE withdrawals ADD COLUMN $fieldName $fieldDef";
                $db->exec($sql);
            }
        }
    } catch (PDOException $e) {
        // 忽略表创建错误，继续执行
        error_log("自动修复withdrawals表结构错误: " . $e->getMessage());
    }
}

// 自动修复users表结构函数
function autoFixUsersTable($db) {
    try {
        // 检查并添加缺失的字段
        $requiredFields = [
            'alipay_qrcode' => "VARCHAR(255) DEFAULT NULL COMMENT '支付宝收款码'",
            'wechat_qrcode' => "VARCHAR(255) DEFAULT NULL COMMENT '微信收款码'",
            'payment_method' => "ENUM('wechat','alipay') DEFAULT 'wechat' COMMENT '默认支付方式'",
            'approval_status' => "TINYINT(1) DEFAULT 0 COMMENT '审核状态：0-待审核，1-已审核通过，2-审核拒绝'",
            'is_first_login' => "TINYINT(1) DEFAULT 1 COMMENT '是否首次登录：0-否，1-是'",
            'submitted_for_approval_at' => "DATETIME DEFAULT NULL COMMENT '提交审核时间'",
            'approved_at' => "DATETIME DEFAULT NULL COMMENT '审核通过时间'",
            'is_banned' => "TINYINT(1) DEFAULT 0 COMMENT '是否被封禁：0-正常，1-封禁'",
            'banned_at' => "DATETIME DEFAULT NULL COMMENT '封禁时间'",
            'banned_reason' => "VARCHAR(255) DEFAULT NULL COMMENT '封禁原因'"
        ];
        
        foreach ($requiredFields as $fieldName => $fieldDef) {
            $sql = "SHOW COLUMNS FROM users LIKE '$fieldName'";
            $checkStmt = $db->query($sql);
            $exists = $checkStmt->rowCount() > 0;
            
            if (!$exists) {
                $sql = "ALTER TABLE users ADD COLUMN $fieldName $fieldDef";
                $db->exec($sql);
                
                // 如果是approval_status字段，把现有用户默认设为已审核
                if ($fieldName === 'approval_status') {
                    $db->exec("UPDATE users SET approval_status = 1, approved_at = NOW() WHERE approval_status IS NULL");
                }
            }
        }
    } catch (PDOException $e) {
        // 忽略表创建错误，继续执行
        error_log("自动修复users表结构错误: " . $e->getMessage());
    }
}

// 自动创建用户审核记录表函数
function autoCreateUserApprovalRecordsTable($db) {
    try {
        // 检查表是否存在
        $checkSql = "SHOW TABLES LIKE 'user_approval_records'";
        $checkStmt = $db->query($checkSql);
        $exists = $checkStmt->rowCount() > 0;
        
        if (!$exists) {
            // 创建表
            $createSql = "CREATE TABLE user_approval_records (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL COMMENT '用户ID',
                username VARCHAR(255) DEFAULT NULL COMMENT '用户名',
                nickname VARCHAR(255) DEFAULT NULL COMMENT '昵称',
                phone VARCHAR(50) DEFAULT NULL COMMENT '手机号',
                avatar VARCHAR(255) DEFAULT NULL COMMENT '头像',
                status TINYINT(1) DEFAULT 0 COMMENT '状态：0-待审核，1-已通过，2-已拒绝',
                reviewer_id INT DEFAULT NULL COMMENT '审核人ID',
                reviewer_type VARCHAR(20) DEFAULT NULL COMMENT '审核人类型：admin/eadmin',
                review_note TEXT DEFAULT NULL COMMENT '审核备注',
                reviewed_at DATETIME DEFAULT NULL COMMENT '审核时间',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                KEY idx_user_id (user_id),
                KEY idx_status (status),
                KEY idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户审核记录表'";
            $db->exec($createSql);
        }
    } catch (PDOException $e) {
        // 忽略表创建错误，继续执行
        error_log("创建user_approval_records表错误: " . $e->getMessage());
    }
}

// 自动修复orders表结构函数
function autoFixOrdersTable($db) {
    try {
        // 检查并添加缺失的字段
        $requiredFields = [
            'category_id' => "INT DEFAULT 0 COMMENT '分类ID'",
            'category_name' => "VARCHAR(255) DEFAULT '' COMMENT '分类名称'",
            'payment_ip' => "VARCHAR(50) DEFAULT '' COMMENT '支付IP'",
            'payment_amount' => "DECIMAL(10,2) DEFAULT 0.00 COMMENT '支付金额'",
            'payment_status' => "VARCHAR(20) DEFAULT 'unpaid' COMMENT '支付状态'",
            'payment_time' => "DATETIME DEFAULT NULL COMMENT '支付时间'",
            'trade_no' => "VARCHAR(50) DEFAULT '' COMMENT '交易号'",
            'hunter_option' => "VARCHAR(50) DEFAULT 'system' COMMENT '打手选择方式'",
            'hunter_id' => "INT DEFAULT 0 COMMENT '指定打手ID'",
            'game_region' => "VARCHAR(50) DEFAULT '' COMMENT '游戏大区'",
            'input_fields' => "TEXT COMMENT '其他输入字段(JSON格式)'",
            'order_status' => "TINYINT(1) DEFAULT 1 COMMENT '订单状态'",
            'lock_status' => "TINYINT(1) DEFAULT 0 COMMENT '锁单状态(0:未锁定,1:已锁定)'",
            'lock_time' => "DATETIME DEFAULT NULL COMMENT '锁单时间'",
            'lock_user_id' => "INT DEFAULT 0 COMMENT '锁单用户ID'",
            'user_idu' => "VARCHAR(32) DEFAULT '' COMMENT '用户唯一识别码'",
            'user_nickname' => "VARCHAR(255) DEFAULT '' COMMENT '用户昵称'"
        ];
        
        foreach ($requiredFields as $fieldName => $fieldDef) {
            $sql = "SHOW COLUMNS FROM orders LIKE '$fieldName'";
            $checkStmt = $db->query($sql);
            $exists = $checkStmt->rowCount() > 0;
            
            if (!$exists) {
                $sql = "ALTER TABLE orders ADD COLUMN $fieldName $fieldDef";
                $db->exec($sql);
            }
        }
        
        // 特别处理payment_type字段：如果不存在则添加，如果是enum类型则修改为varchar(20)以支持更多支付方式
        $sql = "SHOW COLUMNS FROM orders LIKE 'payment_type'";
        $checkStmt = $db->query($sql);
        $column = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$column) {
            // 字段不存在，添加字段
            $sql = "ALTER TABLE orders ADD COLUMN payment_type VARCHAR(20) DEFAULT 'wxpay' COMMENT '支付方式'";
            $db->exec($sql);
        } elseif (strpos($column['Type'], 'enum') !== false) {
            // 字段存在但类型是enum，修改为varchar(20)
            $sql = "ALTER TABLE orders MODIFY COLUMN payment_type VARCHAR(20) DEFAULT 'wxpay' COMMENT '支付方式'";
            $db->exec($sql);
        }
    } catch (PDOException $e) {
        // 忽略表创建错误，继续执行
        error_log("自动修复orders表结构错误: " . $e->getMessage());
    }
}

// 创建PDO数据库连接
$pdo = null;
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // 自动修复表结构
    autoFixTableStructure($pdo);
    autoFixInvitationsTable($pdo); // 自动修复invitations表结构
    autoFixChatTables($pdo); // 自动修复聊天相关表结构
    autoFixMemberLevelsTable($pdo); // 自动修复会员等级表结构
    autoFixProductsTable($pdo); // 自动修复商品表结构
    autoFixSysNotificationConfigTable($pdo); // 自动修复系统通知配置表结构
    autoFixOrderTempTable($pdo); // 自动修复临时订单表结构
    autoFixCarouselTable($pdo); // 自动修复轮播图表结构
    autoFixH5UserTable($pdo); // 自动修复h5user表结构
    autoFixUsersTable($pdo); // 自动修复users表结构
    autoCreateUserApprovalRecordsTable($pdo); // 自动创建用户审核记录表
    autoFixOrdersTable($pdo); // 自动修复orders表结构
    autoFixWithdrawalsTable($pdo); // 自动修复withdrawals表结构
    autoCreateUserBalanceLogsTable($pdo); // 自动创建用户余额变动日志表
    
    // 清理超时的锁单
    if (function_exists('cleanupExpiredLocks')) {
        cleanupExpiredLocks($pdo);
    }
} catch (PDOException $e) {
    // 记录错误日志而不是输出，避免影响header()函数
    error_log("数据库连接失败: " . $e->getMessage());
    // 不要exit()，让文件继续加载
    // 重定向逻辑会在调用页面处理
}

// 获取网站标题
$siteTitle = "俱乐部"; // 默认值
try {
    if ($pdo) {
        $stmt = $pdo->query("SELECT site_title FROM settings WHERE id = 1");
        if ($stmt && $row = $stmt->fetch()) {
            $siteTitle = htmlspecialchars($row['site_title']);
        }
    }
} catch (PDOException $e) {
    error_log("获取网站标题失败: " . $e->getMessage());
}