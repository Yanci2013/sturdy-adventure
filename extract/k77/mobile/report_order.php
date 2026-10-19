<?php
// 最基础的执行验证 - 写入系统临时目录
$tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sydj_report_debug.txt';
$testContent = '执行时间: ' . date('Y-m-d H:i:s') . ', IP: ' . $_SERVER['REMOTE_ADDR'] . '\n';
@file_put_contents($tempFile, $testContent, FILE_APPEND);

error_log('REPORT_ORDER_EXECUTED: ' . date('Y-m-d H:i:s'));

// 环境信息调试
error_log('[环境调试] PHP版本: ' . phpversion());
error_log('[环境调试] 操作系统: ' . PHP_OS);
error_log('[环境调试] 当前目录: ' . __DIR__);
error_log('[环境调试] 脚本路径: ' . __FILE__);
error_log('[环境调试] 临时目录: ' . sys_get_temp_dir());
error_log('[环境调试] 安全模式: ' . (ini_get('safe_mode') ? '开启' : '关闭'));
error_log('[环境调试] open_basedir: ' . ini_get('open_basedir'));

// 调试：检查文件系统权限和路径
$debugPath = __DIR__ . '/execution_verify.txt';
error_log('[举报调试] 尝试创建调试文件: ' . $debugPath);
error_log('[举报调试] 目录权限: ' . (is_writable(__DIR__) ? '可写' : '不可写'));

// 尝试创建调试文件
$testContent = '调试文件创建于: ' . date('Y-m-d H:i:s') . '\n';
$writeResult = file_put_contents($debugPath, $testContent);
error_log('[举报调试] 文件创建结果: ' . ($writeResult !== false ? '成功，写入字节数: ' . $writeResult : '失败，错误: ' . error_get_last()['message']));

ob_start(); // 启动输出缓冲
// 紧急调试：创建可见文件以验证执行
file_put_contents('execution_verify.txt', '脚本执行于: ' . date('Y-m-d H:i:s') . '\n用户ID: ' . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '未登录') . '\n请求方法: ' . $_SERVER['REQUEST_METHOD']);

// 关键执行点追踪
error_log("[举报流程] 脚本开始执行 - 时间: " . date('Y-m-d H:i:s'));
error_log("[举报流程] 当前用户ID: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '未登录'));
error_log("[举报流程] 请求方法: " . $_SERVER['REQUEST_METHOD']);
// 开启错误报告
error_reporting(E_ALL);
ini_set('display_errors', 0); // 关闭错误输出到页面
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// 统一JSON响应函数
function sendJsonResponse($data) {
    ob_end_clean();
    echo json_encode($data);
    exit;
}

// 修复日志函数：移除递归调用并增强调试
function logCustom($message) {
    // 使用Windows兼容的绝对路径
    $logFile = 'e:\\trae源码\\北沙电竞\\user\\report_log.txt';
    $absLogFile = $logFile;
    
    // 记录所有调试信息到PHP错误日志
error_log("[LOG_DEBUG] 开始日志记录流程 - 文件: {$absLogFile}");
    
    $logDir = dirname($logFile);
error_log("[LOG_DEBUG] 日志目录: {$logDir}");
    
    // 检查并创建目录
    if (!file_exists($logDir)) {
error_log("[LOG_DEBUG] 目录不存在，尝试创建");
        $oldUmask = umask(0);
        $created = mkdir($logDir, 0777, true);
        umask($oldUmask);
        $error = error_get_last();
        $errorMsg = $error ? $error['message'] : '无错误信息';
error_log("[LOG_DEBUG] 目录创建结果: " . ($created ? '成功' : '失败') . ", 错误: {$errorMsg}");
    }
    
    // 检查目录权限
    if (file_exists($logDir)) {
        $perms = substr(sprintf('%o', fileperms($logDir)), -4);
        $writable = is_writable($logDir) ? '是' : '否';
error_log("[LOG_DEBUG] 目录权限: {$perms}, 可写: {$writable}");
    } else {
error_log("[LOG_DEBUG] 目录不存在，无法检查权限");
        return false;
    }
    
    // 准备日志内容
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    
    // 使用file_put_contents替代error_log，增加错误处理
    $bytesWritten = file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    
    if ($bytesWritten !== false) {
error_log("[LOG_DEBUG] 日志写入成功，字节数: {$bytesWritten}");
        return true;
    } else {
        $error = error_get_last();
        $errorMsg = $error ? $error['message'] : '未知错误';
error_log("[LOG_DEBUG] file_put_contents失败: {$errorMsg}");
        
        // 检查文件是否存在且可写
        if (file_exists($logFile)) {
            $perms = substr(sprintf('%o', fileperms($logFile)), -4);
            $writable = is_writable($logFile) ? '是' : '否';
error_log("[LOG_DEBUG] 文件权限: {$perms}, 可写: {$writable}");
        } else {
error_log("[LOG_DEBUG] 文件不存在");
        }
        
        // 尝试创建文件
        $handle = fopen($logFile, 'w');
        if ($handle) {
            fclose($handle);
error_log("[LOG_DEBUG] 文件已创建");
            chmod($logFile, 0666);
            // 再次尝试写入
            $bytesWritten = file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
error_log("[LOG_DEBUG] 二次尝试写入结果: " . ($bytesWritten !== false ? '成功' : '失败'));
        } else {
            $error = error_get_last();
            $errorMsg = $error ? $error['message'] : '未知错误';
error_log("[LOG_DEBUG] 创建文件失败: {$errorMsg}");
        }
        
        // 写入系统临时目录
        $tempLogFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'report_log.txt';
        $tempResult = file_put_contents($tempLogFile, $logMessage, FILE_APPEND | LOCK_EX);
error_log("[LOG_DEBUG] 临时目录写入结果: " . ($tempResult !== false ? '成功' : '失败') . ", 文件: {$tempLogFile}");
        
        return false;
    }
    
    if (!$success) {
        // 日志写入失败时记录到PHP错误日志
error_log("Failed to write to report log: {$logMessage}");
    }
}

// 调试辅助函数
function logCustomDebug($message) {
    $timestamp = date('Y-m-d H:i:s');
    $debugMessage = "[{$timestamp}] [DEBUG] {$message}\n";
    // 写入PHP错误日志
error_log($debugMessage);
    // 同时输出到响应（仅调试用）
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        file_put_contents('php://stderr', $debugMessage);
    }
}

// 引入头部文件（确保已包含 PDO 连接）
require __DIR__ . '/header.php';

// 检查PDO连接是否成功并设置错误模式
if (!isset($pdo) || !$pdo instanceof PDO) {
    logCustom("PDO连接未初始化或不可用。header.php可能未正确包含或数据库配置错误。包含路径: " . __DIR__ . '/header.php');
    sendJsonResponse(['success' => false, 'message' => '举报失败: 数据库连接错误']);
}

// 记录PDO连接状态和属性
logCustom("PDO连接已初始化，开始检查属性");
try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $errorMode = $pdo->getAttribute(PDO::ATTR_ERRMODE);
    logCustom("PDO驱动: {$driver}, 错误模式: {$errorMode}");
    if ($errorMode !== PDO::ERRMODE_EXCEPTION) {
            logCustom("警告: PDO错误模式不是异常模式，当前模式: {$errorMode}");
            try {
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                logCustom("已重置PDO错误模式为异常模式");
            } catch (PDOException $e) {
                logCustom("设置PDO错误模式失败: " . $e->getMessage());
                sendJsonResponse(['success' => false, 'message' => '数据库配置错误，无法启用异常模式']);
            }
        }
} catch (PDOException $e) {
    logCustom("获取PDO属性失败: " . $e->getMessage());
}

// PDO错误模式已在连接检查阶段设置
  logCustom("使用连接检查阶段设置的PDO错误模式");

/**
 * 记录系统日志
 */
function addLog($pdo, $user_id, $action, $detail) {
    try {
        $stmt = $pdo->prepare("INSERT INTO logs (user_id, action, detail, time) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, $action, $detail]);
    } catch (PDOException $e) {
error_log("记录日志失败: " . $e->getMessage());
    }
}

/**
 * 发送通知
 */
function addNotification($pdo, $user_id, $title, $content) {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, content, time, is_read) VALUES (?, ?, ?, NOW(), 0)");
        $stmt->execute([$user_id, $title, $content]);
    } catch (PDOException $e) {
error_log("发送通知失败: " . $e->getMessage());
    }
}

// 验证用户是否登录
if (!isset($_SESSION['user_id'])) {
    $result = ['success' => false, 'message' => '请先登录'];
    logCustom("用户未登录,无法举报订单");
    sendJsonResponse($result);
}
$user_id = $_SESSION['user_id'];

// 验证用户是否存在
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
$stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
    $result = ['success' => false, 'message' => '用户不存在'];
    logCustom("用户不存在: {$user_id}");
    sendJsonResponse($result);
}

// 检查是否是POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
error_log("[举报流程] 进入POST处理逻辑");
    logCustom("收到POST请求，用户ID: " . $_SESSION['user_id']);
    
    // 验证CSRF令牌
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if ($csrfToken !== $_SESSION['csrf_token']) {
error_log("[举报流程] CSRF令牌验证失败，提交: {$csrfToken}, 会话: {$_SESSION['csrf_token']}");
        sendJsonResponse(['success' => false, 'message' => '安全验证失败，请刷新页面重试']);
    }
    
    // 获取订单ID、举报原因和详细内容
    $order_id = isset($_POST['order_id']) ? (int)trim($_POST['order_id']) : 0;
    $reason = isset($_POST['report_reason']) ? trim($_POST['report_reason']) : '';
    $content = isset($_POST['report_content']) ? trim($_POST['report_content']) : '';
    
error_log("[举报流程] 原始请求参数 - order_id: {$_POST['order_id']}, report_reason: {$_POST['report_reason']}");
error_log("[举报流程] 处理后参数 - 订单ID: {$order_id}, 举报原因: {$reason}, 内容长度: " . strlen($content));
    
    // 参数验证
    if ($order_id <= 0) {
error_log("[举报流程] 订单ID无效: {$order_id}");
        sendJsonResponse(['success' => false, 'message' => '订单ID无效']);
    }
    if (empty($reason)) {
error_log("[举报流程] 举报原因为空");
        sendJsonResponse(['success' => false, 'message' => '请选择举报原因']);
    }
    if (empty($content)) {
error_log("[举报流程] 举报内容为空");
        sendJsonResponse(['success' => false, 'message' => '请填写举报详情']);
    }
    
    logCustom("请求参数 - 订单ID: {$order_id}, 举报原因: {$reason}");
      if (!empty($content)) {
          logCustom("举报详细内容: {$content}");
      }
    
    // 验证参数
    if (empty($order_id)) {
        $result = ['success' => false, 'message' => '订单ID不能为空'];
        logCustom("订单ID为空");
        sendJsonResponse($result);
    }
    
    if (empty($reason)) {
        $result = ['success' => false, 'message' => '请填写举报原因'];
        logCustom("举报原因为空");
        sendJsonResponse($result);
    }
    
    try {
        // 记录举报信息到文件作为备份
        $report_time = date('Y-m-d H:i:s');
        $report_data = [
            'report_id' => time(),
            'user_id' => $_SESSION['user_id'],
            'order_id' => $order_id,
            'report_reason' => $reason,
            'report_content' => $content,
            'report_time' => $report_time,
            'status' => 1
        ];
        file_put_contents(__DIR__ . '/report_backup.txt', json_encode($report_data) . "\n", FILE_APPEND);
        logCustom("已备份举报数据到文件");
        
        // 检查订单是否存在
try {
            $stmt = $pdo->prepare("SELECT id, order_number AS order_no FROM orders WHERE id = ?");
            logCustom("开始检查订单是否存在，订单ID: {$order_id}");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            $order_exists = !empty($order);
            $order_no = $order_exists ? $order['order_no'] : '';
            logCustom("订单对象内容: " . json_encode($order));
            logCustom("订单检查 - ID: {$order_id}, 是否存在: " . ($order_exists ? '是' : '否') . ", order_no: {$order_no}");
        } catch (PDOException $e) {
            logCustom("订单存在性检查失败: " . $e->getMessage() . ", SQL状态: " . implode(', ', $stmt->errorInfo()));
            sendJsonResponse(['success' => false, 'message' => '系统错误: 无法验证订单信息']);
        }

        if (!$order_exists) {
    $result = ['success' => false, 'message' => '订单不存在'];
    logCustom("订单不存在: {$order_id}");
    sendJsonResponse($result);
}logCustom("订单存在验证通过，订单ID: {$order_id}");
        
        // 检查用户是否已经举报过该订单
        logCustom("开始检查用户是否已举报该订单 - 用户ID: {$user_id}, 订单ID: {$order_id}");
        try {
            $stmt = $pdo->prepare("SELECT id FROM order_reports WHERE user_id = ? AND order_id = ?");
            logCustom("准备执行重复举报检查SQL: SELECT id FROM order_reports WHERE user_id = ? AND order_id = ?");
            $stmt->execute([$user_id, $order_id]);
            $already_reported = $stmt->fetchColumn() > 0;
            logCustom("重复举报检查结果: " . ($already_reported ? '已举报' : '未举报'));
        } catch (PDOException $e) {
            logCustom("查询举报记录失败: " . $e->getMessage() . ", SQL状态: " . implode(', ', $stmt->errorInfo()));
            sendJsonResponse(['success' => false, 'message' => '查询举报记录失败，请稍后重试']);
        }
        
        logCustom("重复举报检查 - 已举报: " . ($already_reported ? '是' : '否'));
        
        if ($already_reported) {
            $result = ['success' => false, 'message' => '您已经举报过该订单'];
            logCustom("用户已举报过该订单: 用户ID={$_SESSION['user_id']}, 订单ID={$order_id}");
            sendJsonResponse($result);
        }
        
        // 记录举报信息
        logCustom("准备插入举报记录 - 订单ID: {$order_id}, 用户ID: {$user_id}, 举报原因: {$reason}, 举报内容: {$content}");
        $report_time = date('Y-m-d H:i:s');
        // 尝试不同的列名组合以匹配表结构
        // 重新添加created_at字段以匹配表结构
        // 添加status字段并设置默认值1
        // 将created_at改回report_time以匹配表结构
        // 移除status字段并恢复created_at字段
        // 将created_at改回report_time以匹配表结构
        // 根据report_table.sql修正表名和字段名
        // 修正表名和字段以匹配report_table.sql结构
        $sql = "INSERT INTO order_reports (order_id, user_id, report_reason, report_content, report_time) VALUES (?, ?, ?, ?, NOW())";
          logCustom("举报SQL语句: {$sql}");
          logCustom("举报参数值 - order_id: {$order_id}, user_id: {$user_id}, reason: {$reason}, content: {$content}");
          try {
              $stmt = $pdo->prepare($sql);
              $result_exec = $stmt->execute([$order_id, $user_id, $reason, $content]);
          } catch (PDOException $e) {
            $errorInfo = $stmt->errorInfo();
            $errorDetails = "SQL错误: {$e->getMessage()}, SQLSTATE: {$errorInfo[0]}, 驱动错误: {$errorInfo[1]}, 错误消息: {$errorInfo[2]}";
            $errorDetails .= "\nSQL语句: {$sql}";
            $errorDetails .= "\n参数: " . json_encode([$order_id, $order_no, $user_id, $reason, $content]);
            $errorDetails .= "\n字段数: " . substr_count($sql, '?') . ", 参数数: " . count([$order_id, $order_no, $user_id, $reason, $content]);
            logCustom("举报记录插入失败: {$errorDetails}");
            $result = ['success' => false, 'message' => '举报提交失败: ' . $errorInfo[2] . ' (错误代码: ' . $errorInfo[1] . ')'];
            sendJsonResponse($result);
        }

        // 检查执行结果
        if (!$result_exec) {
            $errorInfo = $stmt->errorInfo();
              $errorDetails = implode(' ', $errorInfo);
              logCustom("数据库插入失败: SQL语句=" . addslashes($stmt->queryString) . ", 参数=" . json_encode([$_SESSION['user_id'], $order_id, $reason]) . ", 错误信息=" . $errorDetails);
                $result = ['success' => false, 'message' => '举报失败: ' . $errorDetails];
        } else {
            $report_id = $pdo->lastInsertId();
            logCustom("举报记录插入成功，举报ID: {$report_id}");
            logCustom("举报成功 - 举报ID: {$report_id}, 用户ID: {$_SESSION['user_id']}, 订单ID: {$order_id}");
            
            // 如果数据库写入成功，尝试发送通知给管理员
            try {
                if (isset($pdo) && $pdo !== null) {
                    // 插入管理员通知
                    $adminNotification = $pdo->prepare(
                        "INSERT INTO notifications (user_id, title, content, time, is_read) 
                         VALUES (0, '收到新举报', '用户{$_SESSION['user_id']}举报了订单{$order_id}', NOW(), 0)"
                    );
                    $adminNotification->execute();
                }
            } catch (Exception $notifyEx) {
                logCustom("发送管理员通知失败: " . $notifyEx->getMessage());
            }
            $result = ['success' => true, 'message' => '举报成功，我们会尽快处理'];
        }
    } catch (Exception $e) {logCustom("发生错误: " . $e->getMessage());
        $errorMessage = '举报失败: ' . $e->getMessage();
        $result = ['success' => false, 'message' => $errorMessage, 'error_details' => $e->getMessage()];
        logCustom('响应数据: ' . json_encode($result));
        logCustom('举报处理失败: ' . $e->getMessage());
    }
    
    sendJsonResponse($result);
} else {
    logCustom("非POST请求，拒绝处理");
    sendJsonResponse(['success' => false, 'message' => '非法请求方式']);
}
?>