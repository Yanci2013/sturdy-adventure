<?php
// 防止重复加载的防护机制
if (defined('FUNCTIONS_LOADED')) {
    return;
}
define('FUNCTIONS_LOADED', true);

/**
 * 获取分类图片URL
 * @param int $categoryId 分类ID
 * @param PDO $pdo PDO连接对象
 * @return string 图片URL
 */
function getCategoryImageUrl($categoryId, $pdo) {
    // 默认图片路径
    $defaultImage = '../assets/images/default_product.png';
    
    if (empty($categoryId)) {
        return $defaultImage;
    }
    
    try {
        // 查询分类图片
        $stmt = $pdo->prepare("SELECT logo FROM product_categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($category && !empty($category['logo'])) {
            $imagePath = '../' . ltrim($category['logo'], '/');
            
            // 检查文件是否存在
            if (file_exists($imagePath) && is_file($imagePath)) {
                return $imagePath;
            }
        }
    } catch (PDOException $e) {
        error_log("获取分类图片失败: " . $e->getMessage());
    }
    
    return $defaultImage;
}

/* 订单相关函数 */

/**
 * 获取订单状态文本
 * @param int $status 订单状态码
 * @return string 状态文本
 */
function getOrderStatusText($status) {
    $statusMap = [
        1 => '待接单',
        2 => '待服务',
        3 => '待结算',
        4 => '已完成',
        5 => '关闭中',
        6 => '客服介入',
        7 => '已撤销'
    ];
    return $statusMap[$status] ?? '未知状态';
}

/**
 * 获取订单状态对应的徽章类
 * @param int $status 订单状态码
 * @return string 徽章类名
 */
function getOrderStatusBadgeClass($status) {
    switch ($status) {
        case 1: return 'badge-primary';
        case 2: return 'badge-warning';
        case 3: return 'badge-info';
        case 4: return 'badge-success';
        case 5: 
        case 6: 
        case 7: return 'badge-danger';
        default: return 'badge-secondary';
    }
}

/**
 * 获取打手接的订单
 * @param PDO $pdo PDO连接对象
 * @param int $hunter_id 打手用户ID
 * @param string $status 订单状态
 * @param string $search 搜索关键词
 * @param int $limit 获取数量限制
 * @return array 订单数组
 */
function getHunterOrders(PDO $pdo, $hunter_id, $status = 'all', $search = '', $limit = 0) {
    $sql = "SELECT o.*, p.name as product_name, p.images as product_images, 
            u.username as fadan_name, u.avatar as fadan_avatar
            FROM orders o
            LEFT JOIN products p ON o.product_id = p.id
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.hunter_user_id = ?";
    
    $params = [$hunter_id];
    
    if ($status != 'all' && is_numeric($status)) {
        $sql .= " AND o.order_status = ?";
        $params[] = $status;
    }
    
    if (!empty($search)) {
        $sql .= " AND (o.order_no LIKE ? OR p.name LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    $sql .= " ORDER BY o.create_time DESC";
    
    if ($limit > 0) {
        $sql .= " LIMIT ?";
        $params[] = $limit;
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("获取打手订单错误: " . $e->getMessage());
        return [];
    }
}

/* 商品相关函数 */

/**
 * 根据商品ID获取商品信息
 * @param PDO $pdo PDO连接对象
 * @param int $product_id 商品ID
 * @return array 商品信息数组
 */
function getProductById(PDO $pdo, $product_id) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: [
        'name' => '未知商品',
        'images' => 'assets/images/default-product.png'
    ];
}

/* 时间相关函数 */

/**
 * 获取时间范围条件
 * @param string $range 时间范围(day/week/month/year)
 * @return string 日期字符串
 */
function getDateCondition($range) {
    $now = new DateTime();
    switch ($range) {
        case 'day': $now->modify('-1 day'); break;
        case 'week': $now->modify('-1 week'); break;
        case 'month': $now->modify('-1 month'); break;
        case 'year': $now->modify('-1 year'); break;
        default: $now->modify('-1 month');
    }
    return $now->format('Y-m-d H:i:s');
}

/* 用户相关函数 */

/**
 * 获取用户信息
 * @param PDO $pdo PDO连接对象
 * @param int $user_id 用户ID
 * @return array|false 用户信息数组或false
 */
function getUserById(PDO $pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
}

/**
 * 检查用户是否可以接单
 * @param PDO $pdo PDO连接对象
 * @param int $user_id 用户ID
 * @param int $category_id 商品分类ID
 * @return array [bool $canTake, string $message]
 */
function canUserTakeOrder(PDO $pdo, $user_id, $category_id = null) {
    $settings = getSystemSettings($pdo);
    $user = getUserById($pdo, $user_id);
    
    if (!$user) {
        return [false, '用户不存在'];
    }
    
    // 检查是否有正在处理的订单
    $stmt = $pdo->prepare("SELECT COUNT(id) FROM orders WHERE hunter_user_id = ? AND order_status = 2");
    $stmt->execute([$user_id]);
    $activeOrderCount = $stmt->fetchColumn();
    
    if ($activeOrderCount > 0) {
        return [false, '您已有订单在处理中，请完成或提交后再接新订单'];
    }
    
    // 根据接单模式进行验证
    if ($settings['order_mode'] === 'deposit') {
        // 保证金模式验证
        $deposit = $user['deposit'] ?? 0;
        $minDeposit = $settings['deposit'] ?? 0;
        
        if ($deposit < $minDeposit) {
            return [false, "保证金不足！最低需要 ¥" . number_format($minDeposit, 2) . "，当前 ¥" . number_format($deposit, 2)];
        }
    } else {
        // 会员模式验证
        if (empty($user['member_level_id']) || empty($user['member_end_time']) || strtotime($user['member_end_time']) < time()) {
            return [false, "您不是会员或会员已过期"];
        } elseif ($user['member_points'] < ($settings['min_points_for_order'] ?? 0)) {
            return [false, "会员积分不足，当前积分：" . $user['member_points']];
        } elseif ($category_id) {
            // 检查会员是否有权限接该分类的订单
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM member_level_categories WHERE member_level_id = ? AND category_id = ?");
            $stmt->execute([$user['member_level_id'], $category_id]);
            $hasPermission = $stmt->fetchColumn();
            
            if (!$hasPermission) {
                return [false, "您的会员等级无权接此分类的订单"];
            }
        }
    }
    
    return [true, ''];
}

/* 资金相关函数 */

/**
 * 获取用户资金明细
 * @param PDO $pdo PDO连接对象
 * @param int $user_id 用户ID
 * @param int $limit 查询条数限制
 * @param string $time_range 时间范围(day/week/month/year)
 * @return array 资金明细数组
 */
function getFundDetails(PDO $pdo, $user_id, $limit = 10, $time_range = null) {
    try {
        $sql = "SELECT * FROM fund_details WHERE user_id = ?";
        $params = [$user_id];
        
        if ($time_range) {
            $dateCondition = getDateCondition($time_range);
            $sql .= " AND create_time >= ?";
            $params[] = $dateCondition;
        }
        
        $sql .= " ORDER BY create_time DESC";
        
        if ($limit > 0) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("获取资金明细错误: " . $e->getMessage());
        return [];
    }
}

/**
 * 获取用户资金统计信息
 * @param PDO $pdo PDO连接对象
 * @param int $user_id 用户ID
 * @return array 资金统计数据
 */
function getFundStatistics(PDO $pdo, $user_id) {
    try {
        $incomeSql = "SELECT SUM(amount) as total_income FROM fund_details WHERE user_id = ? AND amount > 0";
        $expenseSql = "SELECT SUM(amount) as total_expense FROM fund_details WHERE user_id = ? AND amount < 0";
        $monthIncomeSql = "SELECT SUM(amount) as month_income FROM fund_details 
                         WHERE user_id = ? AND amount > 0 
                         AND create_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $stmt = $pdo->prepare($incomeSql);
        $stmt->execute([$user_id]);
        $income = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare($expenseSql);
        $stmt->execute([$user_id]);
        $expense = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare($monthIncomeSql);
        $stmt->execute([$user_id]);
        $monthIncome = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_income' => $income['total_income'] ?? 0,
            'total_expense' => abs($expense['total_expense'] ?? 0),
            'month_income' => $monthIncome['month_income'] ?? 0,
            'balance' => ($income['total_income'] ?? 0) - abs($expense['total_expense'] ?? 0)
        ];
    } catch (PDOException $e) {
        error_log("获取资金统计错误: " . $e->getMessage());
        return [
            'total_income' => 0,
            'total_expense' => 0,
            'month_income' => 0,
            'balance' => 0
        ];
    }
}

/* 订单操作相关函数 */

/**
 * 获取所有可接单的订单
 * @param PDO $pdo PDO连接对象
 * @return array 订单数组
 */
function getAvailableOrders(PDO $pdo) {
    $sql = "SELECT o.*, c.name as category_name FROM orders o 
            LEFT JOIN product_categories c ON o.category_id = c.id 
            WHERE o.order_status = 1
            ORDER BY o.create_time DESC";
    
    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("获取可接订单错误: " . $e->getMessage());
        return [];
    }
}

/**
 * 获取订单详情
 * @param PDO $pdo PDO连接对象
 * @param int $order_id 订单ID
 * @return array|false 订单信息数组或false
 */
function getOrderById(PDO $pdo, $order_id) {
    try {
        $stmt = $pdo->prepare("SELECT o.*, c.name as category_name, p.name as product_name 
                              FROM orders o
                              LEFT JOIN product_categories c ON o.category_id = c.id
                              LEFT JOIN products p ON o.product_id = p.id
                              WHERE o.id = ?");
        $stmt->execute([$order_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("获取订单详情错误: " . $e->getMessage());
        return false;
    }
}

/**
 * 接单操作
 * @param PDO $pdo PDO连接对象
 * @param int $order_id 订单ID
 * @param int $user_id 用户ID
 * @return array [bool $success, string $message]
 */
function takeOrder(PDO $pdo, $order_id, $user_id) {
    // 确保日志辅助函数可用
    if (!function_exists('log_order_action')) {
        require_once __DIR__ . '/log_helper.php';
    }
    
    try {
        $pdo->beginTransaction();
        
        // 检查订单状态和锁单状态
        $stmt = $pdo->prepare("SELECT id, order_no, hunter_option, hunter_id, category_id, lock_status, lock_time, lock_user_id FROM orders WHERE id = ? AND order_status = 1 FOR UPDATE");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        
        if (!$order) {
            $pdo->rollBack();
            return [false, '订单不存在或已被接走'];
        }
        
        // 检查锁单状态
        if ($order['lock_status'] == 1) {
            // 检查锁单是否超时（5分钟）
            $lock_time = strtotime($order['lock_time']);
            $current_time = time();
            if ($current_time - $lock_time < 300) {
                // 锁单未超时
                if ($order['lock_user_id'] == $user_id) {
                    // 是当前用户锁定的订单，可以继续操作
                } else {
                    $pdo->rollBack();
                    return [false, '订单正在被其他用户抢单，请稍后再试'];
                }
            } else {
                // 锁单已超时，自动解锁
                $unlock = $pdo->prepare("UPDATE orders SET lock_status = 0, lock_time = NULL, lock_user_id = 0 WHERE id = ?");
                $unlock->execute([$order_id]);
            }
        }
        
        // 锁定订单
        $lock = $pdo->prepare("UPDATE orders SET lock_status = 1, lock_time = NOW(), lock_user_id = ? WHERE id = ?");
        $lock->execute([$user_id, $order_id]);
        
        if ($order['hunter_option'] === 'assign' && $order['hunter_id'] != $user_id) {
            $pdo->rollBack();
            return [false, '该订单为指定打手订单'];
        }
        
        // 检查用户是否可以接单
        list($canTake, $message) = canUserTakeOrder($pdo, $user_id, $order['category_id']);
        if (!$canTake) {
            $pdo->rollBack();
            return [false, $message];
        }
        
        // 更新订单状态，同时解锁
        $update = $pdo->prepare("UPDATE orders SET 
                                hunter_user_id = ?, 
                                order_status = 2, 
                                accept_time = NOW(),
                                lock_status = 0, 
                                lock_time = NULL, 
                                lock_user_id = 0
                                WHERE id = ?");
        $update->execute([$user_id, $order_id]);
        
        // 获取用户信息用于日志
        $user_stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $user_stmt->execute([$user_id]);
        $user = $user_stmt->fetch();
        
        // 记录订单日志
        log_order_action(
            $pdo,
            $order_id,
            $order['order_no'],
            'accept',
            '订单已被接单',
            $user_id,
            $user['username'] ?? '未知用户',
            'hunter',
            null,
            null,
            null
        );
        
        // 记录日志
        addLog($pdo, $user_id, '接单', "订单ID: {$order_id}");
        addNotification($pdo, $user_id, '接单成功', "订单ID: {$order_id}");
        
        $pdo->commit();
        return [true, '接单成功'];
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("接单操作错误: " . $e->getMessage());
        return [false, '接单失败: ' . $e->getMessage()];
    }
}

/* 锁单相关函数 */

/**
 * 清理超时的锁单
 * @param PDO $pdo 数据库连接
 * @param int $timeout 超时时间（秒），默认300秒（5分钟）
 * @return int 清理的锁单数量
 */
function cleanupExpiredLocks(PDO $pdo, $timeout = 300) {
    try {
        $sql = "UPDATE orders SET lock_status = 0, lock_time = NULL, lock_user_id = 0 
                WHERE lock_status = 1 AND lock_time < DATE_SUB(NOW(), INTERVAL ? SECOND) AND order_status = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$timeout]);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("清理超时锁单错误: " . $e->getMessage());
        return 0;
    }
}

/* 提现相关函数 */

/**
 * 获取用户提现记录
 * @param PDO $pdo PDO连接对象
 * @param int $user_id 用户ID
 * @return array 提现记录数组
 */
function getUserWithdrawals(PDO $pdo, $user_id) {
    $sql = "SELECT * FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("获取提现记录错误: " . $e->getMessage());
        return [];
    }
}

/* 系统设置相关函数 */

/**
 * 获取系统设置
 * @param PDO $pdo PDO连接对象
 * @return array 系统设置数组
 */
function getSystemSettings(PDO $pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log("获取系统设置错误: " . $e->getMessage());
        return [];
    }
}

/* 通知相关函数 */

/**
 * 添加系统通知
 * @param PDO $pdo PDO连接对象
 * @param int $user_id 用户ID
 * @param string $title 通知标题
 * @param string $content 通知内容
 * @return bool 是否添加成功
 */
function addNotification(PDO $pdo, $user_id, $title, $content) {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, content, created_at) VALUES (?, ?, ?, NOW())");
        return $stmt->execute([$user_id, $title, $content]);
    } catch (PDOException $e) {
        error_log("添加通知错误: " . $e->getMessage());
        return false;
    }
}

/* 日志相关函数 */

/**
 * 添加系统日志
 * @param PDO $pdo PDO连接对象
 * @param int $user_id 用户ID
 * @param string $action 操作类型
 * @param string $details 操作详情
 * @return bool 是否添加成功
 */
function addLog(PDO $pdo, $user_id, $action, $details = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        return $stmt->execute([$user_id, $action, $details, $ip]);
    } catch (PDOException $e) {
        error_log("添加日志错误: " . $e->getMessage());
        return false;
    }
}

/* 计算相关函数 */

/**
 * 计算订单收益
 * @param float $price 订单价格
 * @param float $service_fee 服务费
 * @return float 实际收益
 */
function calculateOrderProfit($price, $service_fee) {
    return $price - $service_fee;
}

/**
 * 计算提现金额
 * @param float $amount 提现金额
 * @param float $withdrawal_rate 提现费率
 * @return float 实际到账金额
 */
function calculateWithdrawalAmount($amount, $withdrawal_rate) {
    return $amount * (1 - $withdrawal_rate);
}

/* 网站信息相关函数 */

/**
 * 获取网站标题
 * @param PDO $pdo PDO连接对象
 * @return string 网站标题
 */
function getWebsiteTitle(PDO $pdo) {
    try {
        $stmt = $pdo->query("SELECT site_title FROM settings WHERE id = 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? htmlspecialchars($row['site_title']) : "俱乐部 | 高端游戏商城";
    } catch (PDOException $e) {
        error_log("获取网站标题错误: " . $e->getMessage());
        return "俱乐部 | 高端游戏商城";
    }
}

/**
 * 获取会员等级信息
 * @param PDO $pdo PDO连接对象
 * @param int $level_id 会员等级ID
 * @return array|false 会员等级信息或false
 */
function getMemberLevelById(PDO $pdo, $level_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM member_levels WHERE id = ?");
        $stmt->execute([$level_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("获取会员等级信息错误: " . $e->getMessage());
        return false;
    }
}
// 格式化文件大小
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// 检查用户是否有权限访问会话
function checkChatPermission($pdo, $user_id, $session_id) {
    $stmt = $pdo->prepare("
        SELECT 1 FROM chat_participants 
        WHERE session_id = ? AND user_id = ? AND user_type = 'user'
    ");
    $stmt->execute([$session_id, $user_id]);
    return $stmt->fetch() !== false;
}

// 检查用户是否可以创建会话
function canCreateChat($pdo, $user_id, $user_role, $target_id, $target_type, $order_id = 0) {
    // 管理员可以创建任何会话
    if ($user_role == 2) { // 管事
        return true;
    }
    
    // 订单相关会话
    if ($order_id > 0) {
        $stmt = $pdo->prepare("
            SELECT 1 FROM orders 
            WHERE id = ? 
            AND (
                (hunter_user_id = ? AND user_id = ? AND ? = 'h5user') OR
                (user_id = ? AND hunter_user_id = ? AND ? = 'user')
            )
            AND accept_time IS NOT NULL
            AND TIMESTAMPDIFF(HOUR, accept_time, NOW()) <= 48
        ");
        
        if ($target_type == 'h5user') {
            $stmt->execute([$order_id, $user_id, $target_id, $target_type, $user_id, $target_id, $target_type]);
        } else {
            $stmt->execute([$order_id, $target_id, $user_id, $target_type, $target_id, $user_id, $target_type]);
        }
        
        return $stmt->fetch() !== false;
    }
    
    // 管事可以创建与旗下打手的会话
    if ($user_role == 2 && $target_type == 'user') {
        $stmt = $pdo->prepare("
            SELECT 1 FROM partner_service_relations
            WHERE inviter_id = ? AND inviter_type = 'eadmin'
            AND invitee_id = ? AND invitee_type = 'fighter'
        ");
        $stmt->execute([$user_id, $target_id]);
        return $stmt->fetch() !== false;
    }
    
    // 打手可以创建与管事的会话
    if ($user_role == 1 && $target_type == 'user') {
        $stmt = $pdo->prepare("
            SELECT 1 FROM partner_service_relations
            WHERE inviter_id = ? AND inviter_type = 'eadmin'
            AND invitee_id = ? AND invitee_type = 'fighter'
        ");
        $stmt->execute([$target_id, $user_id]);
        return $stmt->fetch() !== false;
    }
    
    return false;
}
/**
 * 获取会员可接的分类
 * @param PDO $pdo PDO连接对象
 * @param int $level_id 会员等级ID
 * @return array 分类ID数组
 */
function getMemberLevelCategories(PDO $pdo, $level_id) {
    try {
        $stmt = $pdo->prepare("SELECT category_id FROM member_level_categories WHERE member_level_id = ?");
        $stmt->execute([$level_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("获取会员可接分类错误: " . $e->getMessage());
        return [];
    }
}