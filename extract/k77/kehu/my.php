<?php
require '../includes/session_manager.php';
require '../config.php';

// ★★★ 自定义图标路径 ★★★
$rank_icon    = '../assets/images/img/排行榜.png';
$dasher_icon  = '../assets/images/img/打手中心.png';
$login_icon   = '../assets/images/img/立即登录.png';
$notice_icon  = '../assets/images/img/公告通知.png';
$merchant_icon = '../assets/images/img/商家中心.png';

// ★★★ 抽奖图标路径修复 ★★★
$lottery_all_icon = '../assets/images/img/全部.png';
$lottery_no_icon  = '../assets/images/img/未抽奖.png';
$lottery_done_icon= '../assets/images/img/已抽奖.png';

// 如果上面路径不存在，自动降级到 assets/images/
if (!file_exists($lottery_all_icon)) {
    $lottery_all_icon = '../assets/images/全部.png';
}
if (!file_exists($lottery_no_icon)) {
    $lottery_no_icon = '../assets/images/未抽奖.png';
}
if (!file_exists($lottery_done_icon)) {
    $lottery_done_icon = '../assets/images/已抽奖.png';
}

// 最终容错：还不存在就留空，显示占位
$lottery_all_icon = file_exists($lottery_all_icon) ? $lottery_all_icon : '';
$lottery_no_icon  = file_exists($lottery_no_icon)  ? $lottery_no_icon  : '';
$lottery_done_icon= file_exists($lottery_done_icon)? $lottery_done_icon: '';

// ★★★ 商家中心链接 ★★★
$merchant_center_link = '#';

// ★★★ 底部推广信息 ★★★
$promo_tech   = '小熠网络提供技术支持V1.0';
$promo_slogan = '';
$promo_link   = 'https://qm.qq.com/q/76Hec4JuhO';

// ★★★ 订单图标 ★★★
$orderIcons = [
    'pending_pay' => '../assets/images/待付款.png',
    'pending'     => '../assets/images/待接单.png',
    'service'     => '../assets/images/服务中.png',
    'settle'      => '../assets/images/待结单.png',
    'completed'   => '../assets/images/已完成.png',
    'after_sale'  => '../assets/images/售后中.png',
    'refunded'    => '../assets/images/已退款.png',
    'rate'        => '../assets/images/待评价.png'
];

$body_bg_image = '../assets/images/图2.png';
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$isLoggedIn = is_h5_logged_in();
$user_id = get_h5_user_id();
$upload_error = $upload_success = $nickname_edit_success = $nickname_edit_error = '';
$customer_service_link = 'https://qm.qq.com/q/76Hec4JuhO';
$h5_notice = '搭建系统认准小熠网络';
$banner_image = '';
$bottomBannerImg = '';
$headerBgImg = '';
$defaultAvatar = '../assets/images/默认头像.png';

// ★★★ 昵称弹窗状态：提交后不自动弹出 ★★★
$showNickModal = false;

// 自动创建两张独立数据表
try {
    $db_auto = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);

    $db_auto->exec("CREATE TABLE IF NOT EXISTS user_center_banner (
        id INT PRIMARY KEY AUTO_INCREMENT,
        image_path VARCHAR(255) NOT NULL,
        sort_order INT DEFAULT 0,
        create_time DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='个人中心底部独立轮播';");

    $db_auto->exec("CREATE TABLE IF NOT EXISTS user_center_header_bg (
        id INT PRIMARY KEY AUTO_INCREMENT,
        image_path VARCHAR(255) NOT NULL,
        sort_order INT DEFAULT 0,
        status TINYINT DEFAULT 1,
        create_time DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='个人中心头部透明背景图';");

} catch (PDOException $e) {}

// 读取头部背景图
try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);

    $resHeader = $db->query("SELECT image_path FROM user_center_header_bg WHERE status=1 ORDER BY sort_order DESC LIMIT 1");
    $headerRow = $resHeader ? $resHeader->fetch() : [];

    if (!empty($headerRow['image_path'])) {
        if (substr($headerRow['image_path'], 0, 4) === 'http') {
            $headerBgImg = $headerRow['image_path'];
        } else {
            $headerBgImg = '../' . ltrim($headerRow['image_path'], './');
        }
    }

    $stmt = $db->query("SELECT image_path FROM carousel ORDER BY sort_order DESC, id DESC LIMIT 1");
    $bannerResult = $stmt ? $stmt->fetch() : [];

    if (!empty($bannerResult['image_path'])) {
        $banner_image = $bannerResult['image_path'];
    }

    $resBanner = $db->query("SELECT image_path FROM user_center_banner ORDER BY sort_order DESC LIMIT 1");
    $bottomRow = $resBanner ? $resBanner->fetch() : [];

    if (!empty($bottomRow['image_path'])) {
        if (substr($bottomRow['image_path'], 0, 4) === 'http') {
            $bottomBannerImg = $bottomRow['image_path'];
        } else {
            $bottomBannerImg = '../' . ltrim($bottomRow['image_path'], './');
        }
    }

    $stmtSet = $db->query("SELECT enterprise_customer_link, h5_home_notice FROM settings WHERE id = 1");
    if ($stmtSet) {
        $result = $stmtSet->fetch();
        if (!empty($result['enterprise_customer_link'])) {
            $customer_service_link = $result['enterprise_customer_link'];
        }
        if (!empty($result['h5_home_notice'])) {
            $h5_notice = $result['h5_home_notice'];
        }
    }

} catch (Exception $e) {}

$banner_display = '';
if (!empty($banner_image)) {
    if (strpos($banner_image, 'http://') === 0 || strpos($banner_image, 'https://') === 0) {
        $banner_display = $banner_image;
    } else {
        $banner_display = '../' . ltrim($banner_image, './');
    }
}

// 头像上传
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn && isset($_FILES['avatar'])) {
    $upload_dir = '../uploads/avatars/';

    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file = $_FILES['avatar'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($file_ext, $allowed_exts)) {
        $upload_error = '不支持的图片格式，仅支持 jpg/png/gif/webp';
    } elseif ($file['size'] > 2 * 1024 * 1024) {
        $upload_error = '图片不能超过 2MB';
    } elseif ($file['error'] !== 0) {
        $upload_error = '图片上传失败，请重新选择';
    } else {
        $new_filename = 'avatar_' . $user_id . '.' . $file_ext;
        $target_path = $upload_dir . $new_filename;

        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            try {
                $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $stmt = $db->prepare("UPDATE h5user SET avatar = ? WHERE id = ?");
                $stmt->execute([$target_path, $user_id]);

                $upload_success = '头像上传成功';
                header("Refresh:0");
                exit;

            } catch (PDOException $e) {
                $upload_error = '数据库保存头像失败';
            }
        } else {
            $upload_error = '文件写入失败，请检查 uploads 文件夹权限';
        }
    }
}

// 昵称修改
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn && isset($_POST['new_nickname'])) {
    $new_nickname = trim($_POST['new_nickname']);

    if (mb_strlen($new_nickname) < 2 || mb_strlen($new_nickname) > 12) {
        $nickname_edit_error = '昵称长度需 2-12 个字符';
        $showNickModal = true;
    } else {
        try {
            $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $db->prepare("UPDATE h5user SET nickname = ? WHERE id = ?");
            $stmt->execute([$new_nickname, $user_id]);

            $nickname_edit_success = '昵称修改成功';
            $showNickModal = false;

        } catch (PDOException $e) {
            $nickname_edit_error = '修改失败';
            $showNickModal = true;
        }
    }
}

// 用户信息读取
$userAvatarUrl = $defaultAvatar;

if (!$isLoggedIn || !$user_id) {
    $isLoggedIn = false;
    $user = [
        'nickname' => '立即登录',
        'id' => 0,
        'avatar' => $defaultAvatar,
        'balance' => 0.00
    ];
} else {
    $isLoggedIn = true;

    try {
        $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        $stmt = $db->prepare("SELECT * FROM h5user WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user) {
            clear_h5_session();
            die("<script>alert('登录信息已过期，请重新登录'); location.href='login.php?redirect=" . urlencode($currentUrl) . "';</script>");
        }

        if (!empty($user['avatar']) && file_exists($user['avatar'])) {
            $userAvatarUrl = '../' . ltrim($user['avatar'], './');
        } else {
            $userAvatarUrl = $defaultAvatar;
        }

        $user['balance'] = $user['balance'] ?? 0.00;

    } catch (PDOException $e) {
        die("数据库错误");
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>个人中心</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "PingFang SC", "Microsoft YaHei", sans-serif;
            padding-bottom: 65px;
            <?php if (!empty($body_bg_image)): ?>
            background-image: url('<?php echo htmlspecialchars($body_bg_image); ?>');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            background-color: #f0f2f5;
            <?php else: ?>
            background-color: #f0f2f5;
            <?php endif; ?>
        }

        .container {
            max-width: 480px;
            margin: 0 auto;
            padding: 0 12px 20px;
        }

        /* 头部 */
        .header-wrap {
            margin-bottom: 12px;
            border-radius: 0 0 20px 20px;
            overflow: hidden;
            position: relative;
        }

        .header-blur-bg {
            position: absolute;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 1;
            <?php if (!empty($headerBgImg)): ?>
            background-image: url('<?php echo htmlspecialchars($headerBgImg); ?>');
            background-size: cover;
            background-position: center;
            opacity: 0.35;
            <?php endif; ?>
        }

        .header-area {
            position: relative;
            z-index: 2;
            background: transparent;
            border-radius: 0 0 20px 20px;
            padding: 20px 16px 16px;
            display: flex;
            flex-direction: row;
            align-items: center;
            text-align: left;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            gap: 14px;
        }

        .avatar-wrap {
            flex-shrink: 0;
            margin-bottom: 0;
        }

        .avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid #e5e7eb;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            cursor: pointer;
            background: #fff;
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-input {
            display: none;
        }

        .user-info {
            color: #1a1a2e;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.6);
            flex: 1;
        }

        .nickname {
            font-size: 20px;
            font-weight: 700;
            cursor: pointer;
            color: #1a1a2e;
            text-align: left;
        }

        .nickname a {
            color: #1a1a2e;
            text-decoration: none;
        }

        .nickname:hover {
            text-decoration: underline;
        }

        .user-id {
            font-size: 12px;
            color: #888;
            margin-top: 2px;
            text-align: left;
        }

        /* 余额 */
        .balance-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 10px;
            border: 1px solid #1a1a2e;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .balance-card:active {
            transform: scale(0.98);
        }

        .balance-left {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .balance-label {
            font-size: 13px;
            color: #888;
            font-weight: 400;
            letter-spacing: 0.3px;
        }

        .balance-amount {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a2e;
            letter-spacing: 0.5px;
        }

        .balance-amount .currency {
            font-size: 18px;
            font-weight: 600;
            color: #999;
            margin-right: 2px;
        }

        .balance-right {
            flex-shrink: 0;
            background: #FFD700;
            padding: 8px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 16px rgba(255, 215, 0, 0.35);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            cursor: pointer;
        }

        .balance-right:active {
            transform: scale(0.92);
            box-shadow: 0 2px 8px rgba(255, 215, 0, 0.2);
        }

        .recharge-text {
            font-size: 13px;
            font-weight: 700;
            color: #1a1a2e;
            letter-spacing: 0.5px;
        }

        /* 订单 */
        .order-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 14px 12px;
            margin-bottom: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .order-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .order-tag {
            background: #FFD700;
            color: #1a1a2e;
            padding: 3px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }

        .order-all {
            font-size: 11px;
            color: #999;
            text-decoration: none;
        }

        .order-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px 4px;
        }

        .order-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #333;
        }

        .order-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 4px;
            background: transparent;
        }

        .order-icon img {
            width: 28px;
            height: 28px;
            object-fit: contain;
            filter: invert(38%) sepia(96%) saturate(800%) hue-rotate(190deg) brightness(95%) contrast(90%);
        }

        .order-text {
            font-size: 10px;
            color: #555;
        }

        /* 抽奖 */
        .lottery-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 14px 12px;
            margin-bottom: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .lottery-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .lottery-tag {
            background: #FFD700;
            color: #1a1a2e;
            padding: 3px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }

        .lottery-all {
            font-size: 11px;
            color: #999;
            text-decoration: none;
        }

        .lottery-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px 4px;
        }

        .lottery-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #333;
        }

        .lottery-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 4px;
        }

        .lottery-icon img {
            width: 28px;
            height: 28px;
            object-fit: contain;
            filter: invert(38%) sepia(96%) saturate(800%) hue-rotate(190deg) brightness(95%) contrast(90%);
        }

        .lottery-text {
            font-size: 10px;
            color: #555;
        }

        /* 底部轮播图：改成原来的 2/3 */
        .banner-container {
            text-align: center;
            padding: 0;
            margin: 0 0 10px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .banner-img {
            width: 100%;
            height: auto;
            max-height: 240px;
            object-fit: cover;
            display: block;
        }

        /* 更多功能 */
        .func-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 16px 12px 14px;
            margin-bottom: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .func-title {
            text-align: center;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 14px;
            color: #333;
        }

        .func-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px 6px;
        }

        .func-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #333;
            padding: 6px 0;
            border-radius: 0;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            transition: transform 0.15s;
        }

        .func-item:active {
            transform: scale(0.92);
        }

        .func-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 4px;
            font-size: 20px;
            color: #555;
            overflow: hidden;
            background: transparent;
        }

        .func-icon img {
            width: 30px;
            height: 30px;
            object-fit: contain;
            filter: invert(38%) sepia(96%) saturate(800%) hue-rotate(190deg) brightness(95%) contrast(90%);
        }

        .func-text {
            font-size: 11px;
            color: #555;
            text-align: center;
        }

        /* 底部推广 */
        .promo-section {
            margin: 12px 0 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .promo-tech {
            background: #FFD700;
            background-image: linear-gradient(120deg, #FFD700, #FFF8DC, #FFD700, #FFF8DC, #FFD700);
            background-size: 300% 100%;
            animation: lightFlow 3s linear infinite;
            padding: 8px 24px;
            border-radius: 8px;
            color: #1a1a2e;
            font-size: 16px;
            font-weight: 600;
            text-align: center;
            display: inline-block;
            box-shadow: 0 4px 12px rgba(255, 215, 0, 0.3);
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .promo-tech:active {
            transform: scale(0.94);
        }

        @keyframes lightFlow {
            0% {
                background-position: 0% 0%;
            }
            100% {
                background-position: 300% 0%;
            }
        }

        /* 弹窗 */
        .nickname-modal-overlay,
        .role-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.55);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 15px;
            backdrop-filter: blur(4px);
        }

        .nickname-modal-overlay.show,
        .role-modal-overlay.show {
            display: flex;
        }

        .nickname-modal {
            width: 85%;
            max-width: 340px;
            background: #fff;
            border-radius: 24px;
            padding: 26px 22px;
            animation: modalIn 0.3s ease;
        }

        @keyframes modalIn {
            from {
                transform: scale(0.9);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .nickname-modal-title {
            font-size: 16px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 14px;
        }

        .nickname-input {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 14px;
            outline: none;
        }

        .nickname-input:focus {
            border-color: #ff9500;
        }

        .nickname-btn-row {
            display: flex;
            gap: 8px;
        }

        .nickname-btn {
            flex: 1;
            padding: 10px;
            border-radius: 10px;
            border: none;
            font-size: 14px;
            cursor: pointer;
        }

        .btn-cancel {
            background: #f3f4f6;
            color: #666;
        }

        .btn-submit {
            background: #ff9500;
            color: #fff;
        }

        .role-modal {
            width: 100%;
            background: #fff;
            border-radius: 24px 24px 0 0;
            padding: 20px 16px 28px;
            max-height: 80vh;
            overflow-y: auto;
            animation: slideUp 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes slideUp {
            from {
                transform: translateY(100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .role-modal-title {
            font-size: 16px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 4px;
            color: #1a1a2e;
        }

        .role-modal-sub {
            font-size: 12px;
            color: #999;
            text-align: center;
            margin-bottom: 18px;
        }

        .role-option-card {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            border-radius: 16px;
            margin-bottom: 10px;
            cursor: pointer;
            border: 2px solid transparent;
            background: #f7f8fa;
            gap: 16px;
        }

        .role-option-card:active {
            transform: scale(0.97);
        }

        .role-option-card.boss {
            background: linear-gradient(135deg, #fff8e1, #ffecb3);
            border-color: #ffc107;
        }

        .role-option-card.boss .role-icon {
            color: #ff8f00;
        }

        .role-option-card.pro {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-color: #42a5f5;
        }

        .role-option-card.pro .role-icon {
            color: #0d47a1;
        }

        .role-option-card .role-icon {
            font-size: 28px;
            flex-shrink: 0;
            width: 40px;
            text-align: center;
        }

        .role-option-card .role-text {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .role-option-card .role-label {
            font-size: 17px;
            font-weight: 700;
            color: #1a1a2e;
        }

        .role-option-card .role-desc {
            font-size: 12px;
            color: #777;
        }

        .role-modal-close {
            text-align: center;
            margin-top: 12px;
            padding: 12px;
            font-size: 15px;
            color: #999;
            cursor: pointer;
            border-radius: 12px;
        }

        .role-modal-close:active {
            background: #f3f4f6;
        }

        .alert {
            padding: 10px;
            border-radius: 8px;
            margin: 8px 0;
            text-align: center;
        }

        .alert.error {
            background: #fee;
            color: #c33;
        }

        .alert.success {
            background: #ef9;
            color: #270;
        }

        @media (max-width: 480px) {
            .header-area {
                padding: 16px;
                gap: 12px;
            }

            .avatar {
                width: 60px;
                height: 60px;
            }

            .nickname {
                font-size: 18px;
            }

            .user-id {
                font-size: 11px;
            }

            .balance-amount {
                font-size: 20px;
            }

            .balance-amount .currency {
                font-size: 16px;
            }

            .balance-right {
                padding: 6px 16px;
            }

            .recharge-text {
                font-size: 12px;
            }

            .order-text {
                font-size: 9px;
            }

            .func-item {
                padding: 4px 0;
            }

            .func-icon {
                width: 38px;
                height: 38px;
                font-size: 17px;
            }

            .func-icon img {
                width: 26px;
                height: 26px;
            }

            .func-text {
                font-size: 10px;
            }

            .promo-tech {
                font-size: 14px;
                padding: 6px 18px;
                border-radius: 6px;
            }

            .order-tag,
            .lottery-tag {
                font-size: 11px;
                padding: 2px 12px;
            }

            .banner-img {
                max-height: 140px;
            }
        }

        @media (max-width: 360px) {
            .header-area {
                padding: 12px;
                gap: 10px;
            }

            .avatar {
                width: 50px;
                height: 50px;
            }

            .nickname {
                font-size: 16px;
            }

            .balance-amount {
                font-size: 18px;
            }

            .balance-amount .currency {
                font-size: 14px;
            }

            .balance-right {
                padding: 4px 14px;
            }

            .recharge-text {
                font-size: 11px;
            }

            .func-item {
                padding: 2px 0;
            }

            .func-icon {
                width: 34px;
                height: 34px;
                font-size: 15px;
            }

            .func-icon img {
                width: 22px;
                height: 22px;
            }

            .func-text {
                font-size: 9px;
            }

            .promo-tech {
                font-size: 12px;
                padding: 5px 14px;
                border-radius: 4px;
            }

            .order-tag,
            .lottery-tag {
                font-size: 10px;
                padding: 2px 10px;
            }

            .banner-img {
                max-height: 120px;
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
        <div class="v2-loader-logo">澜</div>
    </div>
    <div class="v2-loader-text">
        <span>加</span><span>载</span><span>中</span><span>...</span>
    </div>
    <div class="v2-loader-progress">
        <div class="v2-loader-progress-bar"></div>
    </div>
</div>

<div class="container">

    <!-- 头部 -->
    <div class="header-wrap">
        <div class="header-blur-bg"></div>
        <div class="header-area">
            <div class="avatar-wrap">
                <div class="avatar" id="avatarClick">
                    <img src="<?= htmlspecialchars($userAvatarUrl) ?>" alt="用户头像">
                </div>
                <?php if ($isLoggedIn): ?>
                <form id="avatarForm" method="post" enctype="multipart/form-data" style="display:none;">
                    <input type="file" id="avatarInput" name="avatar" class="avatar-input" accept="image/*">
                </form>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <div class="nickname" id="nicknameBtn">
                    <?php if ($isLoggedIn): ?>
                        <?= htmlspecialchars($user['nickname']) ?>
                    <?php else: ?>
                        <a href="login.php">立即登录</a>
                    <?php endif; ?>
                </div>
                <div class="user-id">用户ID：<?= $isLoggedIn ? $user['id'] : '未登录' ?></div>
            </div>
        </div>
    </div>

    <!-- 提示 -->
    <?php if ($upload_error): ?>
        <div class="alert error"><?= htmlspecialchars($upload_error) ?></div>
    <?php endif; ?>
    <?php if ($upload_success): ?>
        <div class="alert success"><?= htmlspecialchars($upload_success) ?></div>
    <?php endif; ?>
    <?php if ($nickname_edit_error): ?>
        <div class="alert error"><?= htmlspecialchars($nickname_edit_error) ?></div>
    <?php endif; ?>
    <?php if ($nickname_edit_success): ?>
        <div class="alert success"><?= htmlspecialchars($nickname_edit_success) ?></div>
    <?php endif; ?>

    <!-- 余额 -->
    <div class="balance-card" onclick="goRecharge()">
        <div class="balance-left">
            <span class="balance-label">我的余额</span>
            <span class="balance-amount">
                <span class="currency">¥</span><?= number_format($user['balance'] ?? 0, 2) ?>
            </span>
        </div>
        <div class="balance-right" onclick="event.stopPropagation(); goRecharge();">
            <span class="recharge-text">充值</span>
        </div>
    </div>

    <!-- 订单 -->
    <div class="order-card">
        <div class="order-title">
            <span class="order-tag">我的订单</span>
            <a href="order_list.php" class="order-all">全部 ></a>
        </div>
        <div class="order-grid">
            <?php
            $orderItems = [
                ['key' => 'pending_pay', 'label' => '待付款', 'link' => 'order_list.php?status=pending_pay'],
                ['key' => 'pending', 'label' => '待接单', 'link' => 'order_list.php?status=pending'],
                ['key' => 'service', 'label' => '服务中', 'link' => 'order_list.php?status=service'],
                ['key' => 'settle', 'label' => '待结单', 'link' => 'order_list.php?status=settle'],
                ['key' => 'completed', 'label' => '已完成', 'link' => 'order_list.php?status=completed'],
                ['key' => 'after_sale', 'label' => '售后中', 'link' => 'order_list.php'],
                ['key' => 'refunded', 'label' => '已退款', 'link' => 'order_list.php'],
                ['key' => 'rate', 'label' => '待评价', 'link' => 'order_list.php']
            ];
            foreach ($orderItems as $item):
                $iconPath = $orderIcons[$item['key']] ?? '';
            ?>
            <a href="<?= $item['link'] ?>" class="order-item">
                <div class="order-icon">
                    <?php if (!empty($iconPath) && file_exists($iconPath)): ?>
                        <img src="<?= htmlspecialchars($iconPath) ?>" alt="<?= $item['label'] ?>">
                    <?php else: ?>
                        <span style="font-size:16px;color:#ccc;">?</span>
                    <?php endif; ?>
                </div>
                <div class="order-text"><?= $item['label'] ?></div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 抽奖 -->
    <div class="lottery-card">
        <div class="lottery-title">
            <span class="lottery-tag">我的抽奖</span>
            <a href="lottery.php" class="lottery-all">全部 ></a>
        </div>
        <div class="lottery-grid">
            <a href="lottery.php?status=all" class="lottery-item">
                <div class="lottery-icon">
                    <?php if (!empty($lottery_all_icon) && file_exists($lottery_all_icon)): ?>
                        <img src="<?= htmlspecialchars($lottery_all_icon) ?>" alt="全部抽奖">
                    <?php else: ?>
                        <span style="font-size:16px;color:#ccc;">?</span>
                    <?php endif; ?>
                </div>
                <div class="lottery-text">全部抽奖</div>
            </a>

            <a href="lottery.php?status=no" class="lottery-item">
                <div class="lottery-icon">
                    <?php if (!empty($lottery_no_icon) && file_exists($lottery_no_icon)): ?>
                        <img src="<?= htmlspecialchars($lottery_no_icon) ?>" alt="未抽奖">
                    <?php else: ?>
                        <span style="font-size:16px;color:#ccc;">?</span>
                    <?php endif; ?>
                </div>
                <div class="lottery-text">未抽奖</div>
            </a>

            <a href="lottery.php?status=done" class="lottery-item">
                <div class="lottery-icon">
                    <?php if (!empty($lottery_done_icon) && file_exists($lottery_done_icon)): ?>
                        <img src="<?= htmlspecialchars($lottery_done_icon) ?>" alt="已抽奖">
                    <?php else: ?>
                        <span style="font-size:16px;color:#ccc;">?</span>
                    <?php endif; ?>
                </div>
                <div class="lottery-text">已抽奖</div>
            </a>
        </div>
    </div>

    <!-- 底部轮播图：已改成 2/3 高度 -->
    <?php if (!empty($bottomBannerImg)): ?>
    <div class="banner-container">
        <img src="<?= htmlspecialchars($bottomBannerImg) ?>" alt="个人中心轮播图" class="banner-img">
    </div>
    <?php endif; ?>

    <!-- 更多功能 -->
    <div class="func-card">
        <div class="func-title">更多功能</div>
        <div class="func-grid">
            <a href="../mobile/index.php" class="func-item">
                <div class="func-icon">
                    <?php if (!empty($dasher_icon) && file_exists($dasher_icon)): ?>
                        <img src="<?= htmlspecialchars($dasher_icon) ?>" alt="打手入住">
                    <?php else: ?>
                        <i class="fas fa-shield-alt"></i>
                    <?php endif; ?>
                </div>
                <div class="func-text">打手入住</div>
            </a>

            <a href="<?= htmlspecialchars($customer_service_link) ?>" class="func-item" target="_blank">
                <div class="func-icon">
                    <img src="../assets/images/carousel/联系客服.png" alt="联系客服" style="width:30px;height:30px;object-fit:contain;">
                </div>
                <div class="func-text">联系客服</div>
            </a>

            <a href="https://qm.qq.com/q/76Hec4JuhO" class="func-item" target="_blank">
                <div class="func-icon">
                    <img src="../assets/images/carousel/搭建同款系统.png" alt="搭建同款系统" style="width:30px;height:30px;object-fit:contain;">
                </div>
                <div class="func-text">搭建同款系统</div>
            </a>

            <?php if ($isLoggedIn): ?>
            <a href="logout.php" class="func-item">
                <div class="func-icon">
                    <?php if (!empty($login_icon) && file_exists($login_icon)): ?>
                        <img src="<?= htmlspecialchars($login_icon) ?>" alt="退出登录">
                    <?php else: ?>
                        <i class="fas fa-right-from-bracket"></i>
                    <?php endif; ?>
                </div>
                <div class="func-text">退出登录</div>
            </a>
            <?php else: ?>
            <a href="login.php" class="func-item">
                <div class="func-icon">
                    <?php if (!empty($login_icon) && file_exists($login_icon)): ?>
                        <img src="<?= htmlspecialchars($login_icon) ?>" alt="立即登录">
                    <?php else: ?>
                        <i class="fas fa-right-to-bracket"></i>
                    <?php endif; ?>
                </div>
                <div class="func-text">立即登录</div>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 底部推广 -->
    <div class="promo-section">
        <div class="promo-tech" onclick="window.open('<?= htmlspecialchars($promo_link) ?>','_blank')">
            <?= htmlspecialchars($promo_tech) ?>
        </div>
    </div>

</div>

<!-- 修改昵称弹窗 -->
<div class="nickname-modal-overlay" id="nickModal">
    <div class="nickname-modal" onclick="event.stopPropagation()">
        <div class="nickname-modal-title">修改昵称</div>
        <form method="post" id="nicknameForm">
            <input type="text" name="new_nickname" class="nickname-input" id="nicknameInput" placeholder="请输入新昵称（2-12字）" value="<?= htmlspecialchars($user['nickname']) ?>">
            <div class="nickname-btn-row">
                <button type="button" class="nickname-btn btn-cancel" id="nickCancel">取消</button>
                <button type="submit" class="nickname-btn btn-submit">确认修改</button>
            </div>
        </form>
    </div>
</div>

<!-- 身份切换弹窗 -->
<div class="role-modal-overlay" id="roleModal">
    <div class="role-modal" onclick="event.stopPropagation()">
        <div class="role-modal-title">选择你的身份</div>
        <div class="role-modal-sub">请选择你的使用身份</div>
        <div class="role-option-card boss" onclick="switchRole('老板')">
            <div class="role-icon"><i class="fas fa-crown"></i></div>
            <div class="role-text">
                <div class="role-label">我是老板</div>
                <div class="role-desc">我要找打手帮我玩</div>
            </div>
        </div>
        <div class="role-option-card pro" onclick="switchRole('大神')">
            <div class="role-icon"><i class="fas fa-star"></i></div>
            <div class="role-text">
                <div class="role-label">我是大神</div>
                <div class="role-desc">我就是打手，我来接单子</div>
            </div>
        </div>
        <div class="role-modal-close" onclick="closeRoleModal()">取消</div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
// 弹窗状态锁
let modalLock = false;

// 头像上传
const avatarClick = document.getElementById('avatarClick');
const avatarInput = document.getElementById('avatarInput');
const avatarForm = document.getElementById('avatarForm');

if (avatarClick && avatarInput && avatarForm) {
    avatarClick.addEventListener('click', function () {
        avatarInput.click();
    });

    avatarInput.addEventListener('change', function () {
        if (this.files && this.files[0]) {
            avatarForm.submit();
        }
    });
}

// 昵称弹窗核心修复
const nicknameBtn = document.getElementById('nicknameBtn');
const nickModal = document.getElementById('nickModal');
const nickCancel = document.getElementById('nickCancel');
const nicknameForm = document.getElementById('nicknameForm');

function openNickModal() {
    if (modalLock) return;
    modalLock = true;
    nickModal.classList.add('show');
}

function closeNickModal() {
    nickModal.classList.remove('show');
    modalLock = false;
}

nicknameBtn?.addEventListener('click', function (e) {
    e.stopPropagation();
    openNickModal();
});

nickCancel?.addEventListener('click', function (e) {
    e.stopPropagation();
    closeNickModal();
});

nickModal.addEventListener('click', function () {
    closeNickModal();
});

nicknameForm?.addEventListener('submit', function () {
    modalLock = true;
});

// 身份弹窗
const roleModal = document.getElementById('roleModal');

function openRoleModal() {
    if (modalLock) return;
    modalLock = true;
    roleModal.classList.add('show');
}

function closeRoleModal() {
    roleModal.classList.remove('show');
    modalLock = false;
}

roleModal.addEventListener('click', function () {
    closeRoleModal();
});

function switchRole(role) {
    closeRoleModal();

    if (role === '老板') {
        alert('已切换为老板身份');
        window.location.href = '../h5/my.php';
    } else if (role === '大神') {
        if (confirm('切换到大神身份需要登录打手账号，是否继续？')) {
            window.location.href = '../user/register.php';
        }
    }
}

// 充值跳转
function goRecharge() {
    <?php if (!$isLoggedIn): ?>
        alert('请先登录账号');
        location.href = 'login.php?redirect=my.php';
    <?php else: ?>
        location.href = 'recharge.php';
    <?php endif; ?>
}
</script>

<?php if ($showNickModal): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(function () {
        document.getElementById('nicknameBtn')?.click();
    }, 300);
});
</script>
<?php endif; ?>

    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
