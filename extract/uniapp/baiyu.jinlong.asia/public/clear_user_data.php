<?php

/**
 * 清空项目中用户相关数据的脚本
 *
 * 使用说明：
 * 1. 强烈建议先备份数据库！
 * 2. 将本文件上传到线上环境的 public 目录（与 index.php 同级）
 * 3. 通过浏览器访问一次，例如：http(s)://你的域名/clear_user_data.php
 * 4. 执行完成后请立即删除本文件，避免被他人恶意访问
 *
 * 本脚本的策略：
 * - 保留：管理员账号、权限规则、系统配置等必要数据
 * - 清空：会员/用户相关数据表、验证码表、测试表等
 * - 条件清空：附件表中仅删除 user_id > 0 的记录（管理员上传的附件保留）
 *
 * 如有自定义的业务表需要清空，请根据自己的表名扩展 $tablesToTruncate 和 $conditionalDeletes。
 */

// 仅允许在命令行或明确确认后执行（简单防护，防止误点）
if (php_sapi_name() !== 'cli') {
    // 简单的确认参数，例如 /clear_user_data.php?confirm=1
    if (!isset($_GET['confirm']) || $_GET['confirm'] !== '1') {
        echo '安全起见，请先备份数据库，然后以如下方式访问本脚本：<br>';
        echo '<code>clear_user_data.php?confirm=1</code>';
        exit;
    }
}

// 项目根目录（public 上一级）
$rootPath = dirname(__DIR__);
$envFile  = $rootPath . DIRECTORY_SEPARATOR . '.env';

if (!is_file($envFile)) {
    exit('未找到 .env 文件，无法读取数据库配置。');
}

$env = parse_ini_file($envFile, true);
if ($env === false || !isset($env['database'])) {
    exit('解析 .env 失败，或未找到 [database] 配置段。');
}

$db = $env['database'];

$hostname = isset($db['hostname']) ? $db['hostname'] : '127.0.0.1';
$port     = isset($db['hostport']) && $db['hostport'] !== '' ? $db['hostport'] : '3306';
$dbname   = isset($db['database']) ? $db['database'] : '';
$username = isset($db['username']) ? $db['username'] : 'root';
$password = isset($db['password']) ? $db['password'] : '';

if ($dbname === '') {
    exit('数据库名未配置，终止执行。');
}

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $hostname, $port, $dbname);

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    exit('数据库连接失败：' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// 需要清空（TRUNCATE）的表 —— 这里主要是用户/会员相关表
// 如有自定义业务表（订单、日志等）也需要清空，可在此处追加表名
$tablesToTruncate = [
    'fa_user',
    'fa_user_group',
    'fa_user_money_log',
    'fa_user_score_log',
    'fa_user_token',
    'fa_ems',              // 邮箱验证码
    'fa_sms',              // 短信验证码
    'fa_test',             // 示例测试表
];

// 需要按条件删除数据的表
// key 为表名，value 为 WHERE 条件
$conditionalDeletes = [
    // 删除会员上传的附件（user_id > 0），管理员上传的附件保留
    'fa_attachment' => 'user_id > 0',
    // 如需要清空管理员操作日志，可打开下面这一行
    // 'fa_admin_log' => '1=1',
];

// 检查表是否存在
function tableExists(PDO $pdo, $tableName)
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE :name');
    $stmt->execute([':name' => $tableName]);
    return $stmt->fetchColumn() !== false;
}

// 执行清理
$pdo->beginTransaction();
try {
    // 关闭外键约束，避免因外键关系导致 TRUNCATE 失败
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    $messages = [];

    // 1. 直接 TRUNCATE 的表
    foreach ($tablesToTruncate as $table) {
        if (tableExists($pdo, $table)) {
            $pdo->exec("TRUNCATE TABLE `{$table}`");
            $messages[] = "已清空表数据：{$table}";
        } else {
            $messages[] = "跳过（表不存在）：{$table}";
        }
    }

    // 2. 条件删除的表
    foreach ($conditionalDeletes as $table => $where) {
        if (tableExists($pdo, $table)) {
            $affected = $pdo->exec("DELETE FROM `{$table}` WHERE {$where}");
            $messages[] = "已删除 {$affected} 行数据：{$table}（条件：{$where}）";
        } else {
            $messages[] = "跳过（表不存在）：{$table}";
        }
    }

    // 重新开启外键约束
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    $pdo->commit();

    // 输出结果
    if (php_sapi_name() === 'cli') {
        foreach ($messages as $msg) {
            echo $msg . PHP_EOL;
        }
        echo "清理完成。\n";
    } else {
        echo '<pre>';
        foreach ($messages as $msg) {
            echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "\n";
        }
        echo "清理完成。\n";
        echo '</pre>';
    }
} catch (Exception $e) {
    $pdo->rollBack();
    $msg = '执行失败：' . $e->getMessage();
    if (php_sapi_name() === 'cli') {
        echo $msg . PHP_EOL;
    } else {
        echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
    }
}

