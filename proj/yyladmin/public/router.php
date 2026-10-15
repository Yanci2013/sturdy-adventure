<?php
// [ 应用入口文件 ] 开发服务器路由脚本（php think run / php -S 使用）

// 静态文件直接返回
if (is_file($_SERVER["DOCUMENT_ROOT"] . $_SERVER["SCRIPT_NAME"])) {
    return false;
}

// 其余请求交给应用入口
$_SERVER["SCRIPT_FILENAME"] = __DIR__ . '/index.php';

require __DIR__ . "/index.php";
