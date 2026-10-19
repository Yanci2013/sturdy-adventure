<?php
require_once '../includes/session_manager.php';
require_once '../config.php';

if (!is_h5_logged_in()) {
    header("Location: login.php");
    exit;
}

$current_user_id = get_h5_user_id();

$orderId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($orderId <= 0) {
    die("无效的订单ID");
}

try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", 
                 DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // 获取客服链接设置
    $stmt = $db->query("SELECT enterprise_customer_link FROM settings LIMIT 1");
    $settings = $stmt->fetch();
    $customer_service_link = $settings['enterprise_customer_link'] ?? '';
    
    $stmt = $db->prepare("
        SELECT 
            o.*, 
            u.username as issuer_username, 
            u.avatar as issuer_avatar,
            hu.username as hunter_username, 
            hu.avatar as hunter_avatar,
            hu.member_level as hunter_level,
            hu.id as hunter_id,
            p.username as fadan_username,
            pr.images as product_images,
            pr.wheel_prize as product_wheel_prize
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id 
        LEFT JOIN users hu ON o.hunter_user_id = hu.id
        LEFT JOIN partners p ON o.fadan_id = p.id
        LEFT JOIN products pr ON o.product_id = pr.id
        WHERE o.id = ? AND o.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$orderId, get_h5_user_id()]);
    $order = $stmt->fetch();
    
    if (!$order) {
        die("订单不存在或无权查看");
    }
    
    $needPrize = false;
    $prizeData = null;
    
    if (!empty($order['product_wheel_prize'])) {
        $needPrize = true;
        $prizeData = json_decode($order['product_wheel_prize'], true);
        
        if ($order['has_lottery'] == 0 && !isset($_GET['skip_prize'])) {
            header("Location: prize.php?order_id=".$orderId);
            exit;
        }
    }

    $lotteryResult = $order['has_lottery'] == 1 && !empty($order['lottery_prize']) 
        ? json_decode($order['lottery_prize'], true) 
        : null;
    
    $inputFields = json_decode($order['input_fields'] ?? '[]', true);
    $cancelImages = json_decode($order['cancel_images'] ?? '[]', true) ?: [];
    $cancelReplyImages = json_decode($order['cancel_reply_images'] ?? '[]', true) ?: [];
    $submitImages = json_decode($order['submit_images'] ?? '[]', true) ?: [];
    
    $statusMap = [
        1 => ['class' => 'status-pending', 'text' => '待接单'],
        2 => ['class' => 'status-paid', 'text' => '待服务'],
        3 => ['class' => 'status-processing', 'text' => '待结算'],
        4 => ['class' => 'status-completed', 'text' => '已完成'],
        5 => ['class' => 'status-canceled', 'text' => '已取消'],
        7 => ['class' => 'status-canceled', 'text' => '已取消'],
        8 => ['class' => 'status-waiting', 'text' => '待客户结单']
    ];
    
    if (!empty($order['cancel_reason'])) {
        $cancelStatus = $order['cancel_review_status'] ?? 'pending';
        $statusText = [
            'pending' => '正在申请撤销订单',
            'approved' => '订单已取消',
            'rejected' => '取消申请被拒绝'
        ][$cancelStatus];
        $statusClass = 'status-canceled';
    } else {
        $statusInfo = $statusMap[$order['order_status']] ?? ['class' => 'status-pending', 'text' => '进行中'];
        $statusClass = $statusInfo['class'];
        $statusText = $statusInfo['text'];
    }
    
    $hunterLevelClass = [
        '普通打手' => 'level-normal',
        '大神打手' => 'level-pro',
        '至尊打手' => 'level-vip'
    ][$order['hunter_level']] ?? '';
    
    $productImages = ['assets/images/default-product.png'];
    if (!empty($order['product_images'])) {
        $images = json_decode($order['product_images'], true);
        if ($images) {
            $productImages = array_map(function($image) {
                return '../../assets/images/products/' . basename($image);
            }, $images);
        }
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {
        if ($order['order_status'] == 8) {
            $stmt = $db->prepare("UPDATE orders SET order_status = 3, update_time = NOW() WHERE id = ?");
            $stmt->execute([$orderId]);
            header("Location: order_detail.php?id=$orderId&confirmed=1");
            exit();
        }
    }
    
} catch (PDOException $e) {
    die("数据库错误: ".$e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>订单详情</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: url('../assets/1.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: #1d1d1f;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            padding-top: 56px;
            padding-bottom: 100px;
            animation: fadeInUp 0.6s ease-out;
            min-height: 100vh;
            position: relative;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .app-container {
            max-width: 100%;
            margin: 0 auto;
            padding: 0 12px;
            position: relative;
            z-index: 1;
        }

        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            padding: 12px 12px;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .back-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #1d1d1f;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.7);
            transform: scale(1.1);
        }

        .header-title {
            font-size: 16px;
            font-weight: 600;
            color: #000000;
        }

        .header-right {
            width: 40px;
        }

        .card {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            background: rgba(255, 255, 255, 0.7);
        }

        .card-title {
            font-size: 14px;
            font-weight: 600;
            color: #000000;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title i {
            color: #666666;
            font-size: 14px;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }

        .order-no {
            font-size: 12px;
            color: #666666;
        }

        .order-status {
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-pending { background: #fff7e6; color: #fa8c16; }
        .status-paid { background: #e6f7ff; color: #1890ff; }
        .status-processing { background: #e6f7ff; color: #1890ff; }
        .status-completed { background: #f6ffed; color: #52c41a; }
        .status-canceled { background: #fff1f0; color: #ff4d4f; }
        .status-waiting { background: #f9f0ff; color: #722ed1; }

        .order-image {
            width: 100%;
            margin-bottom: 12px;
            border-radius: 10px;
            overflow: hidden;
        }

        .swiper {
            width: 100%;
            height: 160px;
            border-radius: 10px;
        }

        .swiper-slide {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f8f8;
        }

        .swiper-slide img {
            max-width: 100%;
            height: 160px;
            object-fit: cover;
            border-radius: 10px;
        }

        .swiper-pagination-bullet {
            background: rgba(0,0,0,0.3);
            opacity: 1;
        }

        .swiper-pagination-bullet-active {
            background: #000;
            width: 20px;
            border-radius: 10px;
        }

        .order-name {
            font-size: 15px;
            font-weight: 600;
            color: #000000;
            margin-bottom: 8px;
        }

        .order-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
        }

        .meta-label {
            font-size: 11px;
            color: #999999;
            margin-bottom: 2px;
        }

        .meta-value {
            font-size: 13px;
            color: #1d1d1f;
            font-weight: 500;
        }

        .hunter-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: rgba(0, 0, 0, 0.03);
            border-radius: 10px;
        }

        .hunter-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .hunter-detail {
            flex: 1;
        }

        .hunter-name {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 2px;
            font-size: 13px;
            color: #000000;
        }

        .hunter-level {
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 500;
        }

        .level-normal { background: rgba(0, 0, 0, 0.05); color: #666666; }
        .level-pro { background: #e6f7ff; color: #1890ff; }
        .level-vip { background: #fffbe6; color: #faad14; }

        .hunter-id {
            font-size: 11px;
            color: #999999;
        }

        .input-fields {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .input-field {
            background: rgba(0, 0, 0, 0.03);
            padding: 8px 10px;
            border-radius: 8px;
        }

        .input-label {
            font-size: 11px;
            color: #999999;
            margin-bottom: 2px;
        }

        .input-value {
            font-size: 13px;
            color: #1d1d1f;
            word-break: break-all;
        }

        .order-total-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: rgba(255, 77, 77, 0.1);
            border-radius: 10px;
            margin-top: 8px;
        }

        .total-title {
            font-size: 13px;
            color: #666666;
        }

        .total-price {
            display: flex;
            align-items: baseline;
        }

        .total-price-currency {
            font-size: 12px;
            color: #ff4444;
        }

        .total-price-amount {
            font-size: 18px;
            font-weight: 700;
            color: #ff4444;
        }

        .tip-display {
            font-size: 12px;
            color: #ff9900;
            margin-top: 4px;
        }

        @media (min-width: 640px) {
            .input-fields {
                grid-template-columns: 1fr 1fr 1fr;
            }
        }

        .input-field {
            padding: 16px;
            background: #fafafa;
            border-radius: 12px;
        }

        .input-label {
            font-size: 12px;
            color: #999999;
            margin-bottom: 6px;
        }

        .input-value {
            font-weight: 500;
            font-size: 14px;
            color: #1d1d1f;
        }

        .order-total-section {
            margin-top: 20px;
            padding: 20px 0;
            text-align: center;
            border-top: 1px solid #f0f0f0;
        }
        
        .total-title {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
            color: #666666;
        }
        
        .total-price {
            font-size: 28px;
            font-weight: 700;
            color: #ff4444;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .total-price-currency {
            font-size: 20px;
        }
        
        .total-price-amount {
            font-size: 28px;
        }

        .prize-result, .prize-notice {
            padding: 16px;
            border-radius: 12px;
            margin-top: 16px;
        }

        .prize-result {
            background: #f0f9ff;
            border-left: 3px solid #1890ff;
        }

        .prize-notice {
            background: #fffbe6;
            border-left: 3px solid #faad14;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .prize-result-title, .prize-notice-title {
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 15px;
            color: #000000;
        }

        .prize-result-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .prize-item {
            display: flex;
            flex-direction: column;
        }

        .prize-label {
            font-size: 12px;
            color: #666666;
            margin-bottom: 4px;
        }

        .prize-value {
            font-size: 14px;
            font-weight: 500;
            color: #1d1d1f;
        }

        .prize-notice-text {
            flex: 1;
        }

        .prize-notice-desc {
            font-size: 13px;
            color: #666666;
        }

        .btn {
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            text-align: center;
            transition: all 0.2s ease;
            border: 1px solid #eaeaea;
            border-radius: 24px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #ffffff;
            color: #1d1d1f;
        }

        .btn:hover {
            background: #f9f9f9;
            transform: translateY(-2px);
        }

        .btn-primary {
            background: #ff4444;
            color: #ffffff;
            border-color: #ff4444;
        }

        .btn-primary:hover {
            background: #e63d3d;
        }

        .btn-success {
            background: #52c41a;
            color: #ffffff;
            border-color: #52c41a;
        }

        .btn-success:hover {
            background: #389e0d;
        }

        .btn-danger {
            background: #ff4d4f;
            color: #ffffff;
            border-color: #ff4d4f;
        }

        .btn-danger:hover {
            background: #d9363e;
        }

        .timeline {
            position: relative;
            padding-left: 28px;
            margin: 8px 0;
        }

        .timeline::before {
            content: "";
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #eaeaea;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }

        .timeline-item::before {
            content: "";
            position: absolute;
            left: -24px;
            top: 4px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #1890ff;
            border: 2px solid #ffffff;
            box-shadow: 0 0 0 2px #1890ff;
        }

        .timeline-time {
            font-size: 12px;
            color: #999999;
            margin-bottom: 4px;
        }

        .timeline-text {
            font-weight: 500;
            margin-bottom: 4px;
            font-size: 14px;
            color: #000000;
        }

        .timeline-desc {
            font-size: 13px;
            color: #666666;
        }

        .submit-images, .cancel-images, .cancel-reply-images {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            gap: 10px;
            margin-top: 12px;
        }

        .submit-image, .cancel-image, .cancel-reply-image {
            width: 100%;
            height: 90px;
            object-fit: cover;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .submit-image:hover, .cancel-image:hover, .cancel-reply-image:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .cancel-section, .hunter-submit {
            padding: 16px;
            background: #fafafa;
            border-radius: 12px;
            margin: 12px 0;
        }

        .cancel-title, .cancel-reply-title {
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 15px;
            color: #000000;
        }

        .cancel-content, .cancel-reply-content, .submit-notes {
            margin-bottom: 12px;
            line-height: 1.6;
            font-size: 14px;
            color: #1d1d1f;
        }

        .confirm-section {
            padding: 16px;
            background: #f6ffed;
            border-radius: 12px;
            margin: 12px 0;
            border-left: 3px solid #52c41a;
        }

        .confirm-title {
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
            color: #52c41a;
            font-size: 15px;
        }

        .action-btns {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .action-btn {
            flex: 1;
            min-width: 120px;
        }

        .cancel-btn {
            position: fixed;
            bottom: 90px;
            right: 16px;
            padding: 10px 18px;
            font-size: 13px;
            font-weight: 500;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 77, 77, 0.5);
            color: #ff4d4f;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            z-index: 50;
        }

        .cancel-btn:hover {
            background: rgba(255, 77, 77, 0.1);
            border-color: #ff4d4f;
            transform: translateY(-2px);
        }

        .cancel-btn i {
            font-size: 12px;
        }

        @media (max-width: 480px) {
            .cancel-btn {
                bottom: 80px;
                right: 12px;
                padding: 8px 14px;
                font-size: 12px;
            }
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: #ffffff;
            border-radius: 16px;
            width: 90%;
            max-width: 400px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            transform: translateY(20px);
            transition: all 0.3s ease;
        }

        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header span {
            font-size: 16px;
            font-weight: 600;
            color: #000000;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999999;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
        }

        .modal-close:hover {
            background: #f5f5f5;
            color: #1d1d1f;
        }

        .form-group {
            padding: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
            color: #000000;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #eaeaea;
            border-radius: 12px;
            font-family: inherit;
            resize: vertical;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #1890ff;
            box-shadow: 0 0 0 3px rgba(24,144,255,0.1);
        }

        .file-upload {
            margin-top: 8px;
        }

        .file-upload-btn {
            padding: 10px 16px;
            background: #fafafa;
            border: 1px dashed #d9d9d9;
            border-radius: 12px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            color: #666666;
            transition: all 0.2s ease;
        }

        .file-upload-btn:hover {
            border-color: #1890ff;
            color: #1890ff;
        }

        .file-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            gap: 10px;
            margin-top: 12px;
        }

        .file-preview-item {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
        }

        .file-preview-item img {
            width: 100%;
            height: 90px;
            object-fit: cover;
        }

        .remove-btn {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 24px;
            height: 24px;
            background: #ff4d4f;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 14px;
            border: none;
            transition: all 0.2s ease;
        }

        .remove-btn:hover {
            transform: scale(1.1);
        }

        .modal-footer {
            padding: 16px 20px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .modal-footer .btn {
            padding: 10px 24px;
        }

        @media (max-width: 480px) {
            body {
                padding-top: 52px;
                padding-bottom: 95px;
            }

            .header {
                padding: 10px 12px;
            }

            .back-btn {
                width: 36px;
                height: 36px;
            }

            .header-title {
                font-size: 15px;
            }

            .card {
                padding: 16px;
            }

            .order-meta {
                grid-template-columns: 1fr;
            }

            .prize-result-content {
                grid-template-columns: 1fr;
            }

            .action-btns {
                flex-direction: column;
            }

            .swiper, .swiper-slide img {
                height: 180px;
            }

            .hunter-avatar {
                width: 48px;
                height: 48px;
            }

            .total-price {
                font-size: 24px;
            }

            .total-price-amount {
                font-size: 24px;
            }

            .input-fields {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/theme-v2.css">
</head>
<body>
<!-- V2 加载动画 -->
<div class="v2-loader-overlay" id="v2Loader">
    <div class="v2-loader-logo-wrap">
        <div class="v2-loader-ring"></div>
        <div class="v2-loader-ring"></div>
        <div class="v2-loader-logo">小</div>
    </div>
    <div class="v2-loader-text">
        <span>加</span><span>载</span><span>中</span><span>...</span>
    </div>
    <div class="v2-loader-progress">
        <div class="v2-loader-progress-bar"></div>
    </div>
</div>

    <header class="header">
        <a href="order_list.php" class="back-btn">
            <i class="fas fa-chevron-left"></i>
        </a>
        <div class="header-title">订单详情</div>
        <div class="header-right"></div>
    </header>

    <main class="app-container">
        <div class="card">
            <div class="order-header">
                <div class="order-no">订单号: <?= htmlspecialchars($order['order_no']) ?></div>
                <div class="order-status <?= $statusClass ?>"><?= $statusText ?></div>
            </div>
            
            <div class="order-image">
                <div class="swiper">
                    <div class="swiper-wrapper">
                        <?php foreach($productImages as $image): ?>
                            <div class="swiper-slide">
                                <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($order['product_name']) ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="swiper-pagination"></div>
                </div>
            </div>
            
            <div class="order-name"><?= htmlspecialchars($order['product_name']) ?></div>
            
            <div class="order-meta">
                <div class="meta-item">
                    <span class="meta-label">商品分类</span>
                    <span class="meta-value"><?= htmlspecialchars($order['category_name'] ?? '未分类') ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">游戏大区</span>
                    <span class="meta-value"><?= htmlspecialchars($order['game_region'] ?? '未指定') ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">下单时间</span>
                    <span class="meta-value"><?= date('Y-m-d H:i', strtotime($order['create_time'])) ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">支付方式</span>
                    <span class="meta-value">
                        <?php 
                        $paymentTypes = [
                            'alipay' => '支付宝',
                            'wxpay' => '微信支付',
                            'qqpay' => 'QQ钱包',
                            'bank' => '银行卡'
                        ];
                        echo $paymentTypes[$order['payment_type']] ?? '未知';
                        ?>
                    </span>
                </div>
            </div>
            
            <?php if ($order['hunter_user_id']): ?>
            <div class="card-title" style="margin-top: 20px;">
                <i class="fas fa-user-check"></i> 接单人信息
            </div>
            <div class="hunter-info">
                <img src="<?= $order['hunter_avatar'] ? '../../assets/images/avatar/'.$order['hunter_avatar'] : '../../assets/images/default-avatar.png' ?>" 
                     alt="<?= htmlspecialchars($order['hunter_username']) ?>" class="hunter-avatar">
                <div class="hunter-detail">
                    <div class="hunter-name">
                        <?= htmlspecialchars($order['hunter_username']) ?>
                        <?php if ($order['hunter_level']): ?>
                        <span class="hunter-level <?= $hunterLevelClass ?>"><?= $order['hunter_level'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="hunter-id">打手ID: <?= $order['hunter_id'] ?></div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card-title" style="margin-top: 20px;">
                <i class="fas fa-list-alt"></i> 订单信息
            </div>
            
            <div class="input-fields">
                <?php foreach ($inputFields as $fieldName => $value): 
                    if ($fieldName === 'image') continue;
                ?>
                <div class="input-field">
                    <div class="input-label"><?= htmlspecialchars($fieldName) ?></div>
                    <div class="input-value"><?= htmlspecialchars($value) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="order-total-section">
                <div class="total-title">订单总价</div>
                <div class="total-price">
                    <span class="total-price-currency">¥</span>
                    <span class="total-price-amount"><?= number_format($order['price'], 2) ?></span>
                    <?php 
                    $tipAmount = isset($order['tip_amount']) ? floatval($order['tip_amount']) : 0;
                    if ($tipAmount > 0): 
                    ?>
                    <span class="tip-display">（含打赏¥<?= number_format($tipAmount, 2) ?>）</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($order['has_lottery'] == 1 && $lotteryResult): ?>
            <div class="prize-result">
                <div class="prize-result-title">
                    <i class="fas fa-gift"></i> 抽奖结果
                </div>
                <div class="prize-result-content">
                    <div class="prize-item">
                        <div class="prize-label">奖品名称</div>
                        <div class="prize-value"><?= htmlspecialchars($lotteryResult['prize_name'] ?? '未知奖品') ?></div>
                    </div>
                    <div class="prize-item">
                        <div class="prize-label">奖品类型</div>
                        <div class="prize-value"><?= htmlspecialchars($lotteryResult['prize_type'] ?? '普通奖品') ?></div>
                    </div>
                    <div class="prize-item">
                        <div class="prize-label">奖品价值</div>
                        <div class="prize-value">¥<?= number_format($lotteryResult['prize_value'] ?? 0, 2) ?></div>
                    </div>
                    <div class="prize-item">
                        <div class="prize-label">抽奖时间</div>
                        <div class="prize-value"><?= date('Y-m-d H:i', strtotime($order['lottery_time'])) ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($needPrize && $order['has_lottery'] == 0): ?>
            <div class="prize-notice">
                <i class="fas fa-gift"></i>
                <div class="prize-notice-text">
                    <div class="prize-notice-title">恭喜！本订单有抽奖机会</div>
                    <div class="prize-notice-desc">完成订单后，您有机会获得额外奖励</div>
                </div>
                <a href="prize.php?order_id=<?= $orderId ?>" class="btn btn-primary">
                    <i class="fas fa-trophy"></i> 立即抽奖
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($order['order_status'] == 8): ?>
        <div class="card">
            <div class="confirm-section">
                <div class="confirm-title">
                    <i class="fas fa-check-circle"></i> 确认结单
                </div>
                
                <div class="hunter-submit">
                    <?php if (!empty($order['submit_notes'])): ?>
                    <div class="submit-notes">
                        <strong>打手提交说明：</strong>
                        <?= nl2br(htmlspecialchars($order['submit_notes'])) ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($submitImages)): ?>
                    <div class="submit-images">
                        <?php foreach ($submitImages as $image): ?>
                        <img src="../../assets/images/orders/<?= htmlspecialchars($image) ?>" 
                             class="submit-image" 
                             onclick="window.open('../../assets/images/orders/<?= htmlspecialchars($image) ?>', '_blank')"
                             alt="结单凭证">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <form method="post">
                    <button type="submit" name="confirm_order" class="btn btn-success" style="width: 100%; margin-top: 16px;">
                        <i class="fas fa-check"></i> 确认结单
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($order['cancel_reason'])): ?>
        <div class="card">
            <div class="card-title">
                <i class="fas fa-exclamation-triangle"></i> 取消申请
            </div>
            
            <div class="cancel-section">
                <div class="cancel-title">
                    <i class="fas fa-info-circle"></i> 取消原因
                </div>
                <div class="cancel-content">
                    <?= nl2br(htmlspecialchars($order['cancel_reason'])) ?>
                </div>
                
                <?php if (!empty($cancelImages)): ?>
                <div class="cancel-images">
                    <?php foreach ($cancelImages as $image): ?>
                    <img src="../<?= htmlspecialchars($image) ?>" 
                         class="cancel-image" 
                         onclick="window.open('../<?= htmlspecialchars($image) ?>', '_blank')">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($order['cancel_reply'])): ?>
                <div class="cancel-reply" style="margin-top: 16px;">
                    <div class="cancel-reply-title">
                        <i class="fas fa-reply"></i> 客服回复
                    </div>
                    <div class="cancel-reply-content">
                        <?= nl2br(htmlspecialchars($order['cancel_reply'])) ?>
                    </div>
                    
                    <?php if (!empty($cancelReplyImages)): ?>
                    <div class="cancel-reply-images">
                        <?php foreach ($cancelReplyImages as $image): ?>
                        <img src="../assets/images/orders/<?= htmlspecialchars($image) ?>" 
                             class="cancel-reply-image" 
                             onclick="window.open('../assets/images/orders/<?= htmlspecialchars($image) ?>', '_blank')">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-title">
                <i class="fas fa-history"></i> 订单时间线
            </div>
            
            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-time"><?= date('Y-m-d H:i', strtotime($order['create_time'])) ?></div>
                    <div class="timeline-text">订单创建</div>
                    <div class="timeline-desc">用户提交订单并完成支付</div>
                </div>
                
                <?php if ($order['accept_time']): ?>
                <div class="timeline-item">
                    <div class="timeline-time"><?= date('Y-m-d H:i', strtotime($order['accept_time'])) ?></div>
                    <div class="timeline-text">打手接单</div>
                    <div class="timeline-desc">打手 <?= htmlspecialchars($order['hunter_username'] ?? '系统分配') ?> 接受了您的订单</div>
                </div>
                <?php endif; ?>
                
                <?php if ($order['service_time']): ?>
                <div class="timeline-item">
                    <div class="timeline-time"><?= date('Y-m-d H:i', strtotime($order['service_time'])) ?></div>
                    <div class="timeline-text">开始服务</div>
                    <div class="timeline-desc">打手开始为您提供服务</div>
                </div>
                <?php endif; ?>
                
                <?php if ($order['complete_time']): ?>
                <div class="timeline-item">
                    <div class="timeline-time"><?= date('Y-m-d H:i', strtotime($order['complete_time'])) ?></div>
                    <div class="timeline-text">订单完成</div>
                    <div class="timeline-desc">服务已完成，感谢您的使用</div>
                </div>
                <?php endif; ?>
                
                <?php if ($order['submit_time']): ?>
                <div class="timeline-item">
                    <div class="timeline-time"><?= date('Y-m-d H:i', strtotime($order['submit_time'])) ?></div>
                    <div class="timeline-text">打手提交结单</div>
                    <div class="timeline-desc">打手已提交服务完成凭证</div>
                </div>
                <div class="hunter-submit">
                    <?php if (!empty($order['submit_notes'])): ?>
                    <div class="submit-notes">
                        <strong>打手提交说明：</strong>
                        <?= nl2br(htmlspecialchars($order['submit_notes'])) ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($submitImages)): ?>
                    <div class="submit-images">
                        <?php foreach ($submitImages as $image): ?>
                        <img src="../../assets/images/orders/<?= htmlspecialchars($image) ?>" 
                             class="submit-image" 
                             onclick="window.open('../../assets/images/orders/<?= htmlspecialchars($image) ?>', '_blank')"
                             alt="结单凭证">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($order['cancel_time']): ?>
                <div class="timeline-item">
                    <div class="timeline-time"><?= date('Y-m-d H:i', strtotime($order['cancel_time'])) ?></div>
                    <div class="timeline-text">订单取消</div>
                    <div class="timeline-desc">
                        订单已取消
                        <?php if ($order['cancel_review_status'] == 'approved'): ?>
                        (已批准)
                        <?php elseif ($order['cancel_review_status'] == 'rejected'): ?>
                        (已拒绝)
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="action-btns">
                <?php if ($needPrize && $order['has_lottery'] == 0): ?>
                <a href="prize.php?order_id=<?= $orderId ?>" class="btn btn-primary action-btn">
                    <i class="fas fa-trophy"></i> 立即抽奖
                </a>
                <?php endif; ?>
            </div>

            <?php if (($order['order_status'] == 1 || $order['order_status'] == 2) && empty($order['cancel_reason'])): ?>
            <button class="btn btn-danger cancel-btn" onclick="showCancelModal(<?= $order['id'] ?>)">
                <i class="fas fa-times"></i> 取消订单
            </button>
            <?php endif; ?>
        </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>

    <div class="modal-overlay" id="cancelModal">
        <div class="modal-content">
            <div class="modal-header">
                <span>申请取消订单</span>
                <button class="modal-close" onclick="hideCancelModal()">&times;</button>
            </div>
            <form id="cancelForm" enctype="multipart/form-data">
                <input type="hidden" name="order_id" id="cancelOrderId">
                <div class="form-group">
                    <label class="form-label">取消原因 *</label>
                    <textarea name="cancel_reason" class="form-control" required placeholder="请详细说明取消订单的原因..." rows="4"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">相关图片</label>
                    <div class="file-upload">
                        <button type="button" class="file-upload-btn" onclick="document.getElementById('cancelImagesInput').click()">
                            <i class="fas fa-image"></i> 上传图片
                        </button>
                        <input type="file" id="cancelImagesInput" name="cancel_images[]" multiple accept="image/*" onchange="previewFiles(this)" style="display: none;">
                    </div>
                    <div class="file-preview" id="filePreview"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="hideCancelModal()">取消</button>
                    <button type="submit" class="btn btn-primary">提交申请</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var swiper = new Swiper('.swiper', {
                loop: <?= count($productImages) > 1 ? 'true' : 'false' ?>,
                autoplay: {
                    delay: 3000,
                    disableOnInteraction: false
                },
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                },
            });
        });

        function showCancelModal(orderId) {
            document.getElementById('cancelOrderId').value = orderId;
            document.getElementById('cancelModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function hideCancelModal() {
            document.getElementById('cancelModal').classList.remove('active');
            document.getElementById('cancelForm').reset();
            document.getElementById('filePreview').innerHTML = '';
            document.body.style.overflow = '';
        }
        
        function previewFiles(input) {
            const preview = document.getElementById('filePreview');
            preview.innerHTML = '';
            
            if (input.files && input.files.length > 0) {
                for (let i = 0; i < input.files.length; i++) {
                    const file = input.files[i];
                    if (!file.type.match('image.*')) continue;
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const previewItem = document.createElement('div');
                        previewItem.className = 'file-preview-item';
                        previewItem.innerHTML = `
                            <img src="${e.target.result}" alt="预览图">
                            <button type="button" class="remove-btn" onclick="removePreview(this)">×</button>
                        `;
                        preview.appendChild(previewItem);
                    }
                    reader.readAsDataURL(file);
                }
            }
        }
        
        function removePreview(btn) {
            btn.parentElement.remove();
        }
        
        document.getElementById('cancelForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const orderId = document.getElementById('cancelOrderId').value;
            
            formData.append('cancel_by', <?= $current_user_id ?>);
            
            fetch('cancel_order.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('取消申请已提交，请等待审核');
                    window.location.reload();
                } else {
                    alert('提交失败: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('提交过程中出错');
            });
        });
        
        <?php if (isset($_GET['confirmed'])): ?>
        alert('订单已确认，等待客服结算..');
        history.replaceState(null, '', window.location.pathname + '?id=<?= $orderId ?>');
        <?php endif; ?>
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
