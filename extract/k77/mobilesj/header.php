<?php
ob_start();
include("../includes/common.php");
session_start();

if (!isset($_SESSION['partner_id']) || empty($_SESSION['partner_id'])) {
    $_SESSION['error_msg'] = "请先登录商家账号";
    header("Location: login.php");
    ob_end_flush();
    exit;
}

function getPartnerById($pdo, $partner_id) {
    $stmt = $pdo->prepare("SELECT * FROM partners WHERE id = ?");
    $stmt->execute([$partner_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

try {
    $partner_id = $_SESSION['partner_id'];
    $stmt = $pdo->prepare("SELECT id, name, username, contact_person, contact_phone, email, avatar, real_name, id_card, is_verified, balance FROM partners WHERE id = ?");
    $stmt->execute([$partner_id]);
    $partner = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$partner) {
        unset($_SESSION['partner_id']);
        $_SESSION['error_msg'] = "商家信息不存在，请重新登录";
        header("Location: login.php");
        exit;
    }

    $fundStats = getFundStatistics($pdo, $partner_id);
    $_SESSION['partner_info'] = $partner;
} catch (PDOException $e) {
    $_SESSION['error_msg'] = "系统错误，请稍后再试";
    error_log("Database error: " . $e->getMessage());
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $siteTitle ?? '商家端'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
