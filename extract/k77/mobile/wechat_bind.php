<?php
session_start();
include("../includes/common.php");

// 获取来源页面，默认为register.php（打手注册）
$from = isset($_GET['from']) ? $_GET['from'] : 'register';

// 根据来源页面确定跳转目标
$target_page = $from === 'gsreg' ? 'gsreg.php' : 'register.php';

$suyanw_appid = '3527';
$suyanw_appkey = '3af0521a7f9afbcb6c637c7533530bbe';
$redirect_uri = urlencode('https://' . $_SERVER['HTTP_HOST'] . '/mobile/' . $target_page);

try {
    $url = "https://u.suyanw.cn/connect.php?act=login&appid={$suyanw_appid}&appkey={$suyanw_appkey}&type=wx&redirect_uri={$redirect_uri}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        throw new Exception(curl_error($ch));
    }

    curl_close($ch);

    $data = json_decode($response, true);

    if ($data && $data['code'] === 0 && $data['url']) {
        header("Location: " . $data['url']);
        exit();
    } else {
        throw new Exception('获取登录地址失败: ' . ($data['msg'] ?? '未知错误'));
    }
} catch (Exception $e) {
    $_SESSION['error_msg'] = '微信登录服务暂时不可用，请稍后重试';
    header("Location: " . $target_page);
    exit();
}
?>