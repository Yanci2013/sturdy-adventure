<?php
/**
 * 日志记录辅助函数
 */

/**
 * 记录管理员操作日志
 */
function log_admin_operation($pdo, $admin_id, $admin_name, $action, $description = '', $target_type = null, $target_id = null, $old_value = null, $new_value = null) {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $stmt = $pdo->prepare("INSERT INTO admin_operation_logs
            (admin_id, admin_name, action, description, target_type, target_id, old_value, new_value, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

        $stmt->execute([
            $admin_id,
            $admin_name,
            $action,
            $description,
            $target_type,
            $target_id,
            $old_value,
            $new_value,
            $ip_address,
            mb_substr($user_agent, 0, 500, 'utf-8')
        ]);

        return true;
    } catch (PDOException $e) {
        error_log('Failed to log admin operation: ' . $e->getMessage());
        return false;
    }
}

/**
 * 记录订单操作日志
 */
function log_order_action($pdo, $order_id, $order_no, $action, $action_text, $operator_id = null, $operator_name = null, $operator_type = 'system', $target_hunter_id = null, $target_hunter_name = null, $notes = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO order_logs
            (order_id, order_no, action, action_text, operator_id, operator_name, operator_type, target_hunter_id, target_hunter_name, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

        $stmt->execute([
            $order_id,
            $order_no,
            $action,
            $action_text,
            $operator_id,
            $operator_name,
            $operator_type,
            $target_hunter_id,
            $target_hunter_name,
            $notes
        ]);

        return true;
    } catch (PDOException $e) {
        error_log('Failed to log order action: ' . $e->getMessage());
        return false;
    }
}

/**
 * 获取操作日志列表
 */
function get_admin_logs($pdo, $page = 1, $page_size = 20, $admin_id = null, $action = null, $start_date = null, $end_date = null) {
    $where = "1=1";
    $params = [];

    if ($admin_id) {
        $where .= " AND admin_id = ?";
        $params[] = $admin_id;
    }

    if ($action) {
        // 支持多个操作类型（用逗号分隔）
        $actions = explode(',', $action);
        if (count($actions) > 1) {
            $placeholders = implode(',', array_fill(0, count($actions), '?'));
            $where .= " AND action IN ($placeholders)";
            $params = array_merge($params, $actions);
        } else {
            $where .= " AND action = ?";
            $params[] = $action;
        }
    }

    if ($start_date) {
        $where .= " AND created_at >= ?";
        $params[] = $start_date . ' 00:00:00';
    }

    if ($end_date) {
        $where .= " AND created_at <= ?";
        $params[] = $end_date . ' 23:59:59';
    }

    $offset = ($page - 1) * $page_size;

    // 获取总数
    $count_sql = "SELECT COUNT(*) FROM admin_operation_logs WHERE $where";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

    // 获取数据
    $sql = "SELECT * FROM admin_operation_logs WHERE $where ORDER BY created_at DESC LIMIT $offset, $page_size";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'total' => $total,
        'page' => $page,
        'page_size' => $page_size,
        'data' => $data
    ];
}

/**
 * 获取订单日志列表
 */
function get_order_logs($pdo, $order_id = null, $page = 1, $page_size = 20, $start_date = null, $end_date = null) {
    $where = "1=1";
    $params = [];

    if ($order_id) {
        $where .= " AND order_id = ?";
        $params[] = $order_id;
    }

    if ($start_date) {
        $where .= " AND created_at >= ?";
        $params[] = $start_date . ' 00:00:00';
    }

    if ($end_date) {
        $where .= " AND created_at <= ?";
        $params[] = $end_date . ' 23:59:59';
    }

    $offset = ($page - 1) * $page_size;

    // 获取总数
    $count_sql = "SELECT COUNT(*) FROM order_logs WHERE $where";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

    // 获取数据
    $sql = "SELECT * FROM order_logs WHERE $where ORDER BY created_at DESC LIMIT $offset, $page_size";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'total' => $total,
        'page' => $page,
        'page_size' => $page_size,
        'data' => $data
    ];
}
?>
