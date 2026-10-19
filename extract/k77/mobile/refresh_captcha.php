<?php
session_start();

function generateRandomCaptcha() {
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $captcha = '';
    for ($i = 0; $i < 4; $i++) {
        $captcha .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $captcha;
}

$_SESSION['order_captcha'] = generateRandomCaptcha();

header('Content-Type: application/json');
echo json_encode(['success' => true, 'captcha' => $_SESSION['order_captcha']]);
?>