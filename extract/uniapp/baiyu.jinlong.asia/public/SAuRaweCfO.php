<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
// [ 后台入口文件 ]
// 使用此文件可以达到隐藏admin模块的效果
// 为了你的安全，强烈不建议将此文件名修改成admin.php

// 开启错误报告（仅用于开发调试，生产环境应关闭）
// error_reporting(E_ALL);
// ini_set('display_errors', 0); // 不显示错误，只记录到日志

// 定义应用目录
define('APP_PATH', __DIR__ . '/../application/');

// 定义运行时目录（如果还未定义）
defined('RUNTIME_PATH') or define('RUNTIME_PATH', dirname(APP_PATH) . '/runtime/');
defined('LOG_PATH') or define('LOG_PATH', RUNTIME_PATH . 'log/');

/**
 * 写入错误日志到文件
 */
if (!function_exists('writeRequestErrorLog')) {
    function writeRequestErrorLog($message, $file = '', $line = 0, $context = []) {
        $logPath = LOG_PATH;
        if ($logPath) {
            if (!is_dir($logPath)) {
                @mkdir($logPath, 0755, true);
            }
            if (is_writable($logPath) || (!file_exists($logPath) && is_writable(dirname($logPath)))) {
                $logFile = $logPath . date('Y-m-d') . '.log';
                $logMessage = '[' . date('Y-m-d H:i:s') . ']';
                $logMessage .= ' [REQUEST] ' . ($_SERVER['REQUEST_URI'] ?? 'CLI');
                $logMessage .= ' [METHOD] ' . ($_SERVER['REQUEST_METHOD'] ?? 'CLI');
                if ($file) {
                    $logMessage .= ' [' . basename($file);
                    if ($line) {
                        $logMessage .= ':' . $line;
                    }
                    $logMessage .= ']';
                }
                $logMessage .= ' ' . $message;
                if (!empty($context)) {
                    $logMessage .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
                }
                $logMessage .= PHP_EOL;
                @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
            }
        }
    }
}

// 保存原始错误处理器（ThinkPHP会注册自己的）
$originalErrorHandler = set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$originalErrorHandler) {
    // 记录所有错误到日志
    writeRequestErrorLog($errstr, $errfile, $errline, [
        'errno' => $errno,
        'error_type' => 'PHP Error'
    ]);
    // putenv相关错误不显示，直接返回true
    if (strpos($errstr, 'putenv()') !== false) {
        return true; // 已处理，不显示
    }
    // 其他错误继续使用默认处理（ThinkPHP会处理）
    return false;
}, E_ALL | E_STRICT);

// 保存原始异常处理器
$originalExceptionHandler = set_exception_handler(function($exception) use (&$originalExceptionHandler) {
    // 记录异常到日志
    writeRequestErrorLog('Exception: ' . $exception->getMessage(), $exception->getFile(), $exception->getLine(), [
        'exception' => get_class($exception),
        'trace' => $exception->getTraceAsString()
    ]);
    // 继续使用默认处理
    if ($originalExceptionHandler) {
        call_user_func($originalExceptionHandler, $exception);
    }
});

// 设置致命错误处理
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        writeRequestErrorLog($error['message'], $error['file'], $error['line'], [
            'error_type' => 'Fatal Error',
            'type' => $error['type']
        ]);
    }
});

try {
    // 判断是否安装
    if (!is_file(APP_PATH . 'admin/command/Install/install.lock')) {
        header("location:./install.php");
        exit;
    }

    // 加载框架引导文件
    require __DIR__ . '/../thinkphp/base.php';

    // 绑定到admin模块
    \think\Route::bind('admin');

    // 关闭路由
    \think\App::route(false);

    // 设置根url
    \think\Url::root('');

    // 执行应用
    \think\App::run()->send();
    
} catch (\Exception $e) {
    // 捕获所有异常并记录到日志
    writeRequestErrorLog('Exception: ' . $e->getMessage(), $e->getFile(), $e->getLine(), [
        'exception' => get_class($e),
        'trace' => $e->getTraceAsString()
    ]);
    
    // 如果是开发环境，显示错误；生产环境显示友好提示
    if (defined('APP_DEBUG') && APP_DEBUG) {
        throw $e;
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        echo '系统错误，请查看日志文件';
    }
} catch (\Error $e) {
    // 捕获PHP7+的错误
    writeRequestErrorLog('Error: ' . $e->getMessage(), $e->getFile(), $e->getLine(), [
        'error' => get_class($e),
        'trace' => $e->getTraceAsString()
    ]);
    
    if (defined('APP_DEBUG') && APP_DEBUG) {
        throw $e;
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        echo '系统错误，请查看日志文件';
    }
}
