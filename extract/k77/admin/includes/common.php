<?php
// admin/includes/common.php - 本地版本，避免open_basedir限制

// 加载根目录的配置文件
require_once '../../config.php';

// 创建PDO数据库连接
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}

// 简单的安全函数
function escape($str) {
    global $pdo;
    return htmlspecialchars($pdo->quote($str), ENT_QUOTES, 'UTF-8');
}

// 获取网站标题函数
function getSiteTitle($pdo) {
    $siteTitle = "俱乐部"; // 默认值
    try {
        $stmt = $pdo->query("SELECT site_title FROM settings WHERE id = 1");
        if ($stmt && $row = $stmt->fetch()) {
            $siteTitle = htmlspecialchars($row['site_title']);
        }
    } catch (PDOException $e) {
        // 静默处理错误，使用默认值
    }
    return $siteTitle;
}

// 管理员验证函数
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) || isset($_SESSION['eadmin_id']);
}

// 获取当前登录管理员ID
function get_admin_id() {
    if (isset($_SESSION['admin_id'])) return $_SESSION['admin_id'];
    if (isset($_SESSION['eadmin_id'])) return $_SESSION['eadmin_id'];
    return 0;
}

// 简单的CSRF令牌生成函数
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// 验证CSRF令牌
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ===================== 新增功能1：管理员头像/昵称拓展信息 =====================
/**
 * 获取管理员拓展信息（头像、后台昵称）
 * @param PDO $pdo
 * @param int $admin_id
 * @return array
 */
function getAdminExtend($pdo, $admin_id){
    $stmt = $pdo->prepare("SELECT * FROM admin_extend WHERE admin_id = ? LIMIT 1");
    $stmt->execute([$admin_id]);
    $res = $stmt->fetch();
    if(empty($res)){
        // 无记录自动初始化默认数据
        $init = $pdo->prepare("INSERT INTO admin_extend(admin_id,nickname,avatar) VALUES (?,?,?)");
        $init->execute([$admin_id,'超级管理员','assets/images/default_avatar.png']);
        return [
            'nickname' => '超级管理员',
            'avatar'   => 'assets/images/default_avatar.png'
        ];
    }
    return $res;
}

/**
 * 更新管理员头像昵称
 * @param PDO $pdo
 * @param int $admin_id
 * @param string $nickname
 * @param string $avatar
 * @return bool
 */
function updateAdminExtend($pdo, $admin_id, $nickname, $avatar){
    $upd = $pdo->prepare("UPDATE admin_extend SET nickname = ?, avatar = ?, update_time = NOW() WHERE admin_id = ?");
    return $upd->execute([$nickname, $avatar, $admin_id]);
}

// ===================== 新增功能2：动态无限层级菜单读取 =====================
/**
 * 递归获取后台动态菜单
 * @param PDO $pdo
 * @param int $parent_id 父菜单ID，0=一级菜单
 * @return array
 */
function getSystemMenus($pdo, $parent_id = 0){
    $stmt = $pdo->prepare("SELECT * FROM system_menus WHERE parent_id = ? AND status = 1 ORDER BY sort ASC");
    $stmt->execute([$parent_id]);
    $list = $stmt->fetchAll();
    foreach($list as &$item){
        $item['children'] = getSystemMenus($pdo, $item['id']);
    }
    unset($item);
    return $list;
}

// ===================== 新增功能3：DeepSeek API密钥AES加密/解密 =====================
// 加密盐密钥（自定义，请勿泄露）
define('AI_AES_SECRET', 'admin_ai_deepseek_2026_secret_key');

/**
 * AES-256-CBC 加密
 */
function aesEncrypt($str) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encode = openssl_encrypt($str, 'aes-256-cbc', AI_AES_SECRET, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $encode);
}

/**
 * AES-256-CBC 解密
 */
function aesDecrypt($str) {
    $raw = base64_decode($str);
    $ivLen = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($raw, 0, $ivLen);
    $data = substr($raw, $ivLen);
    return openssl_decrypt($data, 'aes-256-cbc', AI_AES_SECRET, OPENSSL_RAW_DATA, $iv);
}

/**
 * 初始化写入固定DeepSeek密钥（仅首次执行）
 * 固定密钥：sk-3914eda467a64c108cbd63df4673dabd
 */
function initDeepSeekApiKey($pdo){
    $check = $pdo->query("SELECT id FROM ai_config LIMIT 1")->fetch();
    if(empty($check)){
        $rawKey = 'sk-3914eda467a64c108cbd63df4673dabd';
        $encryptKey = aesEncrypt($rawKey);
        $ins = $pdo->prepare("INSERT INTO ai_config(api_key, model_name, img_model) VALUES (?, ?, ?)");
        $ins->execute([$encryptKey, 'deepseek-chat', 'deepseek-image']);
    }
}

/**
 * 获取脱敏后的API密钥（前端展示为星号，无法查看完整密钥）
 */
function maskDeepSeekKey($pdo){
    // 先初始化密钥（不存在则自动插入）
    initDeepSeekApiKey($pdo);

    $row = $pdo->query("SELECT api_key FROM ai_config LIMIT 1")->fetch();
    $realKey = aesDecrypt($row['api_key']);
    // 脱敏格式 sk-*************************abd
    $prefix = substr($realKey, 0, 3);
    $suffix = substr($realKey, -3);
    return $prefix . str_repeat('*', 24) . $suffix;
}

/**
 * 获取后端解密后的完整API密钥（仅后台AI接口内部调用使用，前端禁止输出）
 */
function getRawDeepSeekKey($pdo){
    initDeepSeekApiKey($pdo);
    $row = $pdo->query("SELECT api_key FROM ai_config LIMIT 1")->fetch();
    return aesDecrypt($row['api_key']);
}
?>