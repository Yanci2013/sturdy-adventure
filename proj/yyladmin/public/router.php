<?php
// [ 应用入口文件 ] 开发服务器路由脚本（php think run / php -S 使用）

// /api/* 代理转发到 Python api_server (8000)
$reqUri = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($reqUri, '/api/') === 0) {
    $target = 'http://127.0.0.1:8000' . $reqUri;
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $ch = curl_init($target);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HEADER, true);
    $fwdHeaders = [];
    $hdrs = function_exists('getallheaders') ? getallheaders() : [];
    foreach ($hdrs as $k => $v) {
        if (strcasecmp($k, 'Host') === 0) continue;
        $fwdHeaders[] = $k . ': ' . $v;
    }
    if ($fwdHeaders) curl_setopt($ch, CURLOPT_HTTPHEADER, $fwdHeaders);
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
        $body = file_get_contents('php://input');
        if ($body !== false && $body !== '') curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $resp = curl_exec($ch);
    $hsize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $respHeaders = substr($resp, 0, $hsize);
    $respBody = substr($resp, $hsize);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    foreach (explode("\r\n", $respHeaders) as $line) {
        if ($line !== '' && preg_match('/^(Content-Type:|Content-Length:|Set-Cookie:)/i', $line)) {
            header($line);
        }
    }
    http_response_code($code ?: 200);
    echo $respBody;
    return true;
}

// 静态文件直接返回
if (is_file($_SERVER["DOCUMENT_ROOT"] . $_SERVER["SCRIPT_NAME"])) {
    return false;
}

// 其余请求交给应用入口
$_SERVER["SCRIPT_FILENAME"] = __DIR__ . '/index.php';

require __DIR__ . "/index.php";
