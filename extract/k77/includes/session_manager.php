<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

define('SESSION_H5_PREFIX', 'h5_');
define('SESSION_USER_PREFIX', 'user_');
define('SESSION_ADMIN_PREFIX', 'admin_');

function set_h5_session($user_id, $user_info = null) {
    $_SESSION[SESSION_H5_PREFIX . 'user_id'] = $user_id;
    if ($user_info) {
        $_SESSION[SESSION_H5_PREFIX . 'user_info'] = $user_info;
    }
    $_SESSION[SESSION_H5_PREFIX . 'logged_in'] = true;
    $_SESSION[SESSION_H5_PREFIX . 'login_time'] = time();
}

function is_h5_logged_in() {
    return isset($_SESSION[SESSION_H5_PREFIX . 'logged_in']) && 
           $_SESSION[SESSION_H5_PREFIX . 'logged_in'] === true &&
           isset($_SESSION[SESSION_H5_PREFIX . 'user_id']);
}

function get_h5_user_id() {
    return isset($_SESSION[SESSION_H5_PREFIX . 'user_id']) ? $_SESSION[SESSION_H5_PREFIX . 'user_id'] : null;
}

function get_h5_user_info() {
    return isset($_SESSION[SESSION_H5_PREFIX . 'user_info']) ? $_SESSION[SESSION_H5_PREFIX . 'user_info'] : null;
}

function clear_h5_session() {
    foreach ($_SESSION as $key => $value) {
        if (strpos($key, SESSION_H5_PREFIX) === 0) {
            unset($_SESSION[$key]);
        }
    }
}

function set_user_session($user_id, $user_info = null) {
    $_SESSION[SESSION_USER_PREFIX . 'user_id'] = $user_id;
    if ($user_info) {
        $_SESSION[SESSION_USER_PREFIX . 'user_info'] = $user_info;
    }
    $_SESSION[SESSION_USER_PREFIX . 'logged_in'] = true;
    $_SESSION[SESSION_USER_PREFIX . 'login_time'] = time();
}

function is_user_logged_in() {
    return isset($_SESSION[SESSION_USER_PREFIX . 'logged_in']) && 
           $_SESSION[SESSION_USER_PREFIX . 'logged_in'] === true &&
           isset($_SESSION[SESSION_USER_PREFIX . 'user_id']);
}

function get_user_user_id() {
    return isset($_SESSION[SESSION_USER_PREFIX . 'user_id']) ? $_SESSION[SESSION_USER_PREFIX . 'user_id'] : null;
}

function get_user_user_info() {
    return isset($_SESSION[SESSION_USER_PREFIX . 'user_info']) ? $_SESSION[SESSION_USER_PREFIX . 'user_info'] : null;
}

function clear_user_session() {
    foreach ($_SESSION as $key => $value) {
        if (strpos($key, SESSION_USER_PREFIX) === 0) {
            unset($_SESSION[$key]);
        }
    }
}

function set_admin_session($admin_id, $username = null) {
    $_SESSION[SESSION_ADMIN_PREFIX . 'admin_id'] = $admin_id;
    if ($username) {
        $_SESSION[SESSION_ADMIN_PREFIX . 'admin_username'] = $username;
    }
    $_SESSION[SESSION_ADMIN_PREFIX . 'logged_in'] = true;
    $_SESSION[SESSION_ADMIN_PREFIX . 'login_time'] = time();
}

function is_admin_logged_in() {
    return isset($_SESSION[SESSION_ADMIN_PREFIX . 'logged_in']) && 
           $_SESSION[SESSION_ADMIN_PREFIX . 'logged_in'] === true &&
           isset($_SESSION[SESSION_ADMIN_PREFIX . 'admin_id']);
}

function get_admin_id() {
    return isset($_SESSION[SESSION_ADMIN_PREFIX . 'admin_id']) ? $_SESSION[SESSION_ADMIN_PREFIX . 'admin_id'] : null;
}

function get_admin_username() {
    return isset($_SESSION[SESSION_ADMIN_PREFIX . 'admin_username']) ? $_SESSION[SESSION_ADMIN_PREFIX . 'admin_username'] : null;
}

function clear_admin_session() {
    foreach ($_SESSION as $key => $value) {
        if (strpos($key, SESSION_ADMIN_PREFIX) === 0) {
            unset($_SESSION[$key]);
        }
    }
}

function clear_all_sessions() {
    clear_h5_session();
    clear_user_session();
    clear_admin_session();
}

/**
 * 检查用户是否已审核通过
 * @param PDO $pdo 数据库连接对象
 * @param int|null $user_id 用户ID（可选，默认为当前登录用户）
 * @return bool 是否已审核通过
 */
function is_user_approved($pdo, $user_id = null) {
    if ($user_id === null) {
        $user_id = get_user_user_id();
    }
    
    if ($user_id === null) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT approval_status FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        return $user && $user['approval_status'] == 1;
    } catch (PDOException $e) {
        error_log("检查用户审核状态错误: " . $e->getMessage());
        return false;
    }
}

/**
 * 获取用户审核状态
 * @param PDO $pdo 数据库连接对象
 * @param int|null $user_id 用户ID（可选，默认为当前登录用户）
 * @return int|null 审核状态：0-待审核，1-已通过，2-已拒绝，null-未知
 */
function get_user_approval_status($pdo, $user_id = null) {
    if ($user_id === null) {
        $user_id = get_user_user_id();
    }
    
    if ($user_id === null) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT approval_status FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        return $user ? $user['approval_status'] : null;
    } catch (PDOException $e) {
        error_log("获取用户审核状态错误: " . $e->getMessage());
        return null;
    }
}

/**
 * 检查用户是否为首次登录
 * @param PDO $pdo 数据库连接对象
 * @param int|null $user_id 用户ID（可选，默认为当前登录用户）
 * @return bool 是否首次登录
 */
function is_user_first_login($pdo, $user_id = null) {
    if ($user_id === null) {
        $user_id = get_user_user_id();
    }
    
    if ($user_id === null) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT is_first_login FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        return $user && $user['is_first_login'] == 1;
    } catch (PDOException $e) {
        error_log("检查用户首次登录错误: " . $e->getMessage());
        return false;
    }
}

/**
 * 要求用户已审核通过，否则重定向到待审核页面
 * @param PDO $pdo 数据库连接对象
 */
function require_user_approved($pdo) {
    if (!is_user_logged_in()) {
        header("Location: login.php");
        exit();
    }
    
    $status = get_user_approval_status($pdo);
    if ($status === 0) {
        header("Location: pending_approval.php");
        exit();
    } elseif ($status === 2) {
        clear_user_session();
        header("Location: login.php?error=" . urlencode('您的账户审核未通过，请联系管理员'));
        exit();
    }
}
