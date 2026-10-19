<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

require __DIR__ . '/header.php';
include_once __DIR__ . '/../includes/log_helper.php';

$user_id = get_user_user_id() ?? 0;
$user = getUserById($pdo, $user_id);

if (!isset($_GET['id'])) {
    header('Location: my_orders.php');
    exit();
}

$order_id = $_GET['id'];
$success = $error = '';

// 处理打回大厅
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['back_to_hall']) && isset($_POST['order_id'])) {
    $back_order_id = $_POST['order_id'];
    $back_notes = $_POST['back_notes'] ?? '';
    
    try {
        $stmt = $pdo->prepare("UPDATE orders SET 
                              order_status = 1, 
                              hunter_user_id = NULL, 
                              accept_time = NULL
                              WHERE id = ? AND hunter_user_id = ? AND order_status = 2");
        
        $stmt->execute([$back_order_id, $user_id]);

        if ($stmt->rowCount() > 0) {
            // 获取订单信息用于日志
            $order_stmt = $pdo->prepare("SELECT order_no FROM orders WHERE id = ?");
            $order_stmt->execute([$back_order_id]);
            $order_info = $order_stmt->fetch();

            // 记录订单日志
            log_order_action(
                $pdo,
                $back_order_id,
                $order_info['order_no'] ?? '',
                'back_to_hall',
                '订单被发布回抢单大厅',
                $user_id,
                $user['username'] ?? '未知用户',
                'hunter',
                null,
                null,
                $back_notes
            );

            // 删除该订单的所有共享记录，被共享的用户将无法再查看此订单
            $pdo->exec("DELETE FROM order_shares WHERE order_id = " . intval($back_order_id));

            $success = '订单已成功打回大厅';
        } else {
            $error = '订单打回失败，请重试';
        }
    } catch (PDOException $e) {
        $error = '订单打回失败: ' . $e->getMessage();
    }
}

// 处理手动转单
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transfer_order']) && isset($_POST['order_id']) && isset($_POST['target_hunter_id'])) {
    $transfer_order_id = $_POST['order_id'];
    $target_hunter_id = $_POST['target_hunter_id'];
    $transfer_notes = $_POST['transfer_notes'] ?? '';
    
    try {
        // 检查目标打手是否存在
        $check_stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ? AND user_role = 1");
        $check_stmt->execute([$target_hunter_id]);
        $target_hunter = $check_stmt->fetch();
        
        if (!$target_hunter) {
            $error = '目标打手不存在';
        } else {
            // 更新订单
            $update_stmt = $pdo->prepare("UPDATE orders SET 
                                          hunter_user_id = ?, 
                                          accept_time = NOW()
                                          WHERE id = ? AND hunter_user_id = ? AND order_status = 2");
            
            $update_stmt->execute([$target_hunter_id, $transfer_order_id, $user_id]);

            if ($update_stmt->rowCount() > 0) {
                // 获取订单信息用于日志
                $order_stmt = $pdo->prepare("SELECT order_no FROM orders WHERE id = ?");
                $order_stmt->execute([$transfer_order_id]);
                $order_info = $order_stmt->fetch();

                // 记录订单日志
                log_order_action(
                    $pdo,
                    $transfer_order_id,
                    $order_info['order_no'] ?? '',
                    'transfer',
                    '订单被转单给其他打手',
                    $user_id,
                    $user['username'] ?? '未知用户',
                    'hunter',
                    $target_hunter_id,
                    $target_hunter['username'],
                    $transfer_notes
                );

                $success = '订单已成功转递给 ' . htmlspecialchars($target_hunter['username']);
            } else {
                $error = '订单转单失败，请重试';
            }
        }
    } catch (PDOException $e) {
        $error = '订单转单失败: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order']) && isset($_POST['order_id'])) {
    $submit_order_id = $_POST['order_id'];
    $notes = $_POST['notes'] ?? '';
    
    $images = [];
    if (!empty($_FILES['images']['name'][0])) {
        $upload_dir = __DIR__ . '/../assets/images/orders/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['images']['size'][$key] > 50 * 1024 * 1024) {
                $error = "图片大小不能超过50MB";
                continue;
            }
            
            $file_name = $_FILES['images']['name'][$key];
            $file_tmp = $_FILES['images']['tmp_name'][$key];
            $file_ext = strtolower(pathinfo($file_name, 4));
            
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($file_ext, $allowed_extensions)) {
                $new_file_name = uniqid() . '.' . $file_ext;
                $destination = $upload_dir . $new_file_name;
                
                if (move_uploaded_file($file_tmp, $destination)) {
                    $images[] = $new_file_name;
                } else {
                    $error = "文件上传失败: " . $file_name;
                }
            } else {
                $error = "不支持的文件类型: " . $file_ext;
            }
        }
    }
    
    if (empty($images)) {
        $error = "请上传至少一张完成凭证图片";
    } else {
        $images_json = json_encode($images);
        
        $stmt = $pdo->prepare("UPDATE orders SET 
                              order_status = 3, 
                              submit_time = NOW(), 
                              submit_images = ?, 
                              submit_notes = ? 
                              WHERE id = ? AND hunter_user_id = ? AND order_status = 2 AND order_status != 9");
        
        error_log("订单提交调试: 用户ID=$user_id, 订单ID=$submit_order_id");
        
        try {
            $stmt->execute([$images_json, $notes, $submit_order_id, $user_id]);
            $affectedRows = $stmt->rowCount();
            error_log("订单状态更新结果: 受影响行数=$affectedRows");
            
            if ($affectedRows > 0) {
                // 获取订单信息用于日志
                $order_stmt = $pdo->prepare("SELECT order_no FROM orders WHERE id = ?");
                $order_stmt->execute([$submit_order_id]);
                $order_info = $order_stmt->fetch();
                
                // 记录订单日志
                log_order_action(
                    $pdo,
                    $submit_order_id,
                    $order_info['order_no'] ?? '',
                    'submit',
                    '订单已提交完成',
                    $user_id,
                    $user['username'] ?? '未知用户',
                    'hunter',
                    null,
                    null,
                    $notes
                );
                
                $success = '订单提交成功，请等待商家审核';
                header('Location: order_detail.php?id=' . $submit_order_id . '&success=1');
                exit();
            } else {
                $error = '订单提交失败，请重试';
                error_log("订单提交失败: 订单状态未更新");
            }
        } catch (PDOException $e) {
            $error = '订单提交失败: ' . $e->getMessage();
            error_log("PDO错误: " . $e->getMessage());
        }
    }
}

$order = getOrderById($pdo, $order_id);

// 检查用户是否有权限访问此订单（所有者或被共享者）
$isOwner = ($order && $order['hunter_user_id'] == $user_id);
$isSharedViewer = false;
$sharedUsers = [];

if ($order) {
    // 检查是否是共享查看者
    $shareStmt = $pdo->prepare("SELECT * FROM order_shares WHERE order_id = ? AND shared_user_id = ?");
    $shareStmt->execute([$order_id, $user_id]);
    $isSharedViewer = ($shareStmt->rowCount() > 0);

    // 获取已共享的用户列表
    $shareListStmt = $pdo->prepare("SELECT os.*, u.username FROM order_shares os LEFT JOIN users u ON os.shared_user_id = u.id WHERE os.order_id = ?");
    $shareListStmt->execute([$order_id]);
    $sharedUsers = $shareListStmt->fetchAll(PDO::FETCH_ASSOC);
}

// 获取分成信息
$shareProfitInfo = null;
$shareCount = count($sharedUsers) + 1; // 所有者 + 共享的人数
$perPersonProfit = 0;

if ($order) {
    if ($order['order_status'] == 4) {
        // 已结算，从分成记录表获取实际分成
        $profitStmt = $pdo->prepare("SELECT * FROM order_share_profits WHERE order_id = ? LIMIT 1");
        $profitStmt->execute([$order_id]);
        $profitRecord = $profitStmt->fetch(PDO::FETCH_ASSOC);
        if ($profitRecord) {
            $shareProfitInfo = [
                'settled' => true,
                'per_person' => $profitRecord['per_person_amount'],
                'total' => $profitRecord['profit_amount'] * $profitRecord['share_count'],
                'count' => $profitRecord['share_count']
            ];
        }
    } else {
        // 未结算，计算预期分成
        $settingsStmt = $pdo->query("SELECT hunter_commission_rate FROM settings LIMIT 1");
        $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);
        $hunterRate = ($settings['hunter_commission_rate'] ?? 70) / 100;
        $hunterAmount = $order['price'] * $hunterRate;
        $perPersonProfit = $hunterAmount / $shareCount;
        $shareProfitInfo = [
            'settled' => false,
            'per_person' => $perPersonProfit,
            'total' => $hunterAmount,
            'count' => $shareCount
        ];
    }
}

if (!$order || (!$isOwner && !$isSharedViewer)) {
    header('Location: my_orders.php');
    exit();
}

// 处理共享订单
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['share_order']) && isset($_POST['selected_users'])) {
    $selectedUsers = $_POST['selected_users'];
    $selectedUsers = is_array($selectedUsers) ? $selectedUsers : [$selectedUsers];

    try {
        foreach ($selectedUsers as $sharedUserId) {
            $sharedUserId = intval($sharedUserId);
            if ($sharedUserId > 0 && $sharedUserId != $user_id) {
                // 检查是否已经共享
                $checkStmt = $pdo->prepare("SELECT id FROM order_shares WHERE order_id = ? AND shared_user_id = ?");
                $checkStmt->execute([$order_id, $sharedUserId]);

                if ($checkStmt->rowCount() === 0) {
                    $insertStmt = $pdo->prepare("INSERT INTO order_shares (order_id, owner_user_id, original_user_id, shared_user_id) VALUES (?, ?, ?, ?)");
                    $insertStmt->execute([$order_id, $user_id, $user_id, $sharedUserId]);
                }
            }
        }

        $success = '订单共享成功';
        // 刷新共享用户列表
        $shareListStmt = $pdo->prepare("SELECT os.*, u.username FROM order_shares os LEFT JOIN users u ON os.shared_user_id = u.id WHERE os.order_id = ?");
        $shareListStmt->execute([$order_id]);
        $sharedUsers = $shareListStmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error = '共享失败: ' . $e->getMessage();
    }
}

// 处理取消共享
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unshare_order']) && isset($_POST['unshare_user_id'])) {
    $unshareUserId = intval($_POST['unshare_user_id']);

    try {
        $deleteStmt = $pdo->prepare("DELETE FROM order_shares WHERE order_id = ? AND shared_user_id = ? AND owner_user_id = ?");
        $deleteStmt->execute([$order_id, $unshareUserId, $user_id]);
        $success = '已取消共享';

        // 刷新共享用户列表
        $shareListStmt = $pdo->prepare("SELECT os.*, u.username FROM order_shares os LEFT JOIN users u ON os.shared_user_id = u.id WHERE os.order_id = ?");
        $shareListStmt->execute([$order_id]);
        $sharedUsers = $shareListStmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error = '取消共享失败: ' . $e->getMessage();
    }
}

// 获取其他打手列表（用于转单和共享）
$hunters = [];
try {
    $hunter_stmt = $pdo->prepare("SELECT id, username FROM users WHERE user_role = 1 AND id != ? ORDER BY id ASC");
    $hunter_stmt->execute([$user_id]);
    $hunters = $hunter_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("获取打手列表失败: " . $e->getMessage());
}

$product = getProductById($pdo, $order['product_id']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>订单详情</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: url('https://s3.bmp.ovh/2026/06/06/IfZ7rYuc.jpg') no-repeat center center fixed;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: #333333;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            padding-top: 56px;
            padding-bottom: 120px;
            animation: fadeInUp 0.6s ease-out;
        }

background: rgba(248,246,240,0.75);

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
        }

        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            padding: 12px 12px;
            background: rgba(248,246,240,0.75);
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .back-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.6);
            color: #333333;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.6);
            transform: scale(1.1);
        }

        .header-title {
            font-size: 16px;
            font-weight: 600;
            color: #ffffff;
        }

        .header-right {
            width: 40px;
        }

        .card {
            background: rgba(248,246,240,0.75);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: #333333;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title i {
            color: #666666;
            font-size: 16px;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
        }

        .order-no {
            font-size: 14px;
            color: #666666;
        }

        .order-status {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        .status-pending { background: #fff7e6; color: #fa8c16; }
        .status-paid { background: #e6f7ff; color: #1890ff; }
        .status-processing { background: #e6f7ff; color: #1890ff; }
        .status-completed { background: #f6ffed; color: #52c41a; }
        .status-canceled { background: #fff1f0; color: #ff4d4f; }
        .status-waiting { background: #f9f0ff; color: #722ed1; }

        .order-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
        }

        .meta-label {
            font-size: 12px;
            color: #999999;
            margin-bottom: 4px;
        }

        .meta-value {
            font-size: 14px;
            color: #333333;
            font-weight: 500;
        }

        .input-fields {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }

        @media (min-width: 640px) {
            .input-fields {
                grid-template-columns: 1fr 1fr;
            }
        }

        .input-field {
            padding: 16px;
            background: rgba(255, 255, 255, 0.3);
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
            color: #333333;
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

        .submit-images {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            gap: 10px;
            margin-top: 12px;
        }

        .submit-image {
            width: 100%;
            height: 90px;
            object-fit: cover;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .submit-image:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .hunter-submit {
            padding: 16px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            margin: 12px 0;
        }

        .submit-notes {
            margin-bottom: 12px;
            line-height: 1.6;
            font-size: 14px;
            color: #333333;
        }

        .btn {
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            text-align: center;
            transition: all 0.2s ease;
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 24px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: rgba(248,246,240,0.75);
            color: #333333;
        }

        .btn:hover {
            background: rgba(255, 255, 255, 0.5);
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

        .alert {
            padding: 12px 16px;
            margin-bottom: 12px;
            border-radius: 12px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-success {
            background: #f6ffed;
            border: 1px solid #b7eb8f;
            color: #52c41a;
        }

        .alert-error {
            background: #fff1f0;
            border: 1px solid #ffa39e;
            color: #ff4d4f;
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
            background: rgba(255, 255, 255, 0.9);
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
            color: #666666;
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
            color: #000000;
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
            background: #ffffff;
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
                padding-bottom: 110px;
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

            .action-btns {
                flex-direction: column;
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

<header class="header"></header>

    <main class="app-container">
        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> 订单提交成功，请等待商家审核。
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="order-header">
                <div class="order-no">订单号: <?php echo htmlspecialchars($order['order_no']); ?></div>
                <div class="order-status">
                    <?php 
                    $statusText = '';
                    switch ($order['order_status']) {
                        case 1: $statusText = '待接单'; break;
                        case 2: $statusText = '待服务'; break;
                        case 3: $statusText = '待结算'; break;
                        case 4: $statusText = '已完成'; break;
                        case 5: $statusText = '关闭中'; break;
                        case 6: $statusText = '客服介入'; break;
                        case 7: $statusText = '已撤销'; break;
                        case 9: $statusText = '已关闭'; break;
                    }
                    echo $statusText;
                    ?>
                </div>
            </div>
            
            <div class="order-meta">
                <div class="meta-item">
                    <span class="meta-label">订单ID</span>
                    <span class="meta-value"><?php echo $order['id']; ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">商品名称</span>
                    <span class="meta-value"><?php echo htmlspecialchars($order['product_name']); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">游戏大区</span>
                    <span class="meta-value"><?php echo htmlspecialchars($order['game_region']); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">订单价格</span>
                    <span class="meta-value" style="color: #ff4444; font-weight: 600;">¥<?php echo number_format($order['price'], 2); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">发单人</span>
                    <span class="meta-value"><?php echo htmlspecialchars($order['fadan_name'] ?: '系统发单'); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">接单时间</span>
                    <span class="meta-value"><?php echo $order['accept_time'] ? date('Y-m-d H:i', strtotime($order['accept_time'])) : '--'; ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">开始服务时间</span>
                    <span class="meta-value"><?php echo $order['service_time'] ? date('Y-m-d H:i', strtotime($order['service_time'])) : '--'; ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">提交时间</span>
                    <span class="meta-value"><?php echo $order['submit_time'] ? date('Y-m-d H:i', strtotime($order['submit_time'])) : '--'; ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">完成时间</span>
                    <span class="meta-value"><?php echo $order['complete_time'] ? date('Y-m-d H:i', strtotime($order['complete_time'])) : '--'; ?></span>
                </div>
            </div>
            
            <?php if (!empty($order['service_time'])): ?>
            <?php if ($order['input_fields']): ?>
            <div class="card-title" style="margin-top: 20px;">
                <i class="fas fa-list-alt"></i> 订单信息
            </div>
            <div class="input-fields">
                <?php 
                $input_fields = json_decode($order['input_fields'], true);
                $display_fields = [];
                if (isset($input_fields['中文ID'])) $display_fields['中文ID'] = $input_fields['中文ID'];
                if (isset($input_fields['数字ID'])) $display_fields['数字ID'] = $input_fields['数字ID'];
                if (isset($input_fields['备注'])) $display_fields['备注'] = $input_fields['备注'];
                if (!isset($display_fields['中文ID']) && isset($input_fields['chinese_id'])) $display_fields['中文ID'] = $input_fields['chinese_id'];
                if (!isset($display_fields['数字ID']) && isset($input_fields['numeric_id'])) $display_fields['数字ID'] = $input_fields['numeric_id'];
                if (!isset($display_fields['备注']) && isset($input_fields['remark'])) $display_fields['备注'] = $input_fields['remark'];
                foreach ($display_fields as $key => $value): 
                ?>
                    <div class="input-field">
                        <div class="input-label"><?php echo htmlspecialchars($key); ?></div>
                        <div class="input-value"><?php echo htmlspecialchars($value); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="card-title" style="margin-top: 20px;">
                <i class="fas fa-lock"></i> 订单信息
            </div>
            <div class="input-fields" style="background:#f5f5f5;padding:20px;text-align:center;color:#999;">
                <i class="fas fa-shield-alt" style="font-size:48px;margin-bottom:10px;color:#ddd;"></i>
                <div style="font-size:14px;">请先点击开始服务，订单详情将在开始服务后显示</div>
            </div>
            <?php endif; ?>
            
            <?php if ($order['order_status'] >= 3 && ($order['submit_notes'] || $order['submit_images'])): ?>
            <div class="card-title" style="margin-top: 20px;">
                <i class="fas fa-file-alt"></i> 提交信息
            </div>
            <div class="hunter-submit">
                <?php if ($order['submit_notes']): ?>
                <div class="submit-notes">
                    <strong>备注信息：</strong>
                    <?php echo htmlspecialchars($order['submit_notes']); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($order['submit_images']): ?>
                <div class="submit-images">
                    <?php 
                    $images = json_decode($order['submit_images'], true);
                    foreach ($images as $image): 
                    ?>
                        <img src="../assets/images/orders/<?php echo htmlspecialchars($image); ?>" 
                             class="submit-image" 
                             onclick="window.open('../assets/images/orders/<?php echo htmlspecialchars($image); ?>', '_blank')"
                             alt="上传图片">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="order-total-section">
                <div class="total-title">订单总价</div>
                <div class="total-price">
                    <span class="total-price-currency">¥</span>
                    <span class="total-price-amount"><?php echo number_format($order['price'], 2); ?></span>
                </div>
            </div>
            
            <div class="action-btns">
                <a href="my_orders.php" class="btn action-btn">
                    <i class="fas fa-arrow-left"></i> 返回列表
                </a>

                <?php
// 检查订单是否已被共享
$orderIsShared = !empty($sharedUsers);
?>

<?php if ($isOwner): // 只有订单所有者才能操作 ?>
                    <?php if ($order['order_status'] == 1 || ($order['order_status'] == 2 && empty($order['service_time'])) || $order['order_status'] == 5): ?>
                        <button class="btn btn-success action-btn" onclick="openBackModal()">
                            <i class="fas fa-undo"></i> 打回大厅
                        </button>
                        <?php if (!$orderIsShared): // 未共享的订单才能转单 ?>
                        <button class="btn action-btn" onclick="openTransferModal()">
                            <i class="fas fa-exchange-alt"></i> 转单
                        </button>
                        <?php else: ?>
                        <span class="btn action-btn" style="background:#f0f0f0;color:#999;cursor:not-allowed;" title="已共享的订单无法转单">
                            <i class="fas fa-exchange-alt"></i> 已共享
                        </span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if ($order['order_status'] == 2): ?>
                        <button class="btn btn-primary action-btn" onclick="openSubmitModal()">
                            <i class="fas fa-upload"></i> 提交订单
                        </button>
                    <?php elseif ($order['order_status'] == 9): ?>
                        <span class="btn action-btn" style="background-color: #999; cursor: not-allowed;">
                            <i class="fas fa-lock"></i> 已关闭
                        </span>
                    <?php endif; ?>

                    <?php if ($order['order_status'] == 2 || $order['order_status'] == 5): ?>
                        <button class="btn action-btn" onclick="openShareModal()" style="background:#722ed1;color:#fff;">
                            <i class="fas fa-users"></i> 共享订单
                        </button>
                    <?php endif; ?>
                <?php elseif ($isSharedViewer): ?>
                    <span class="btn action-btn" style="background:#d9d9d9;cursor:not-allowed;">
                        <i class="fas fa-eye"></i> 仅可查看
                    </span>
                <?php endif; ?>
            </div>

            <?php if ((!empty($sharedUsers) || $isOwner) && $shareProfitInfo): ?>
            <div class="shared-users-section" style="margin-top:20px;padding:15px;background:rgba(114,46,209,0.1);border-radius:12px;">
                <div style="font-size:14px;font-weight:600;color:#722ed1;margin-bottom:10px;">
                    <i class="fas fa-users"></i> 共享分成信息
                    <?php if ($shareProfitInfo['settled']): ?>
                        <span style="background:#52c41a;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:8px;">已结算</span>
                    <?php else: ?>
                        <span style="background:#fa8c16;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:8px;">预计</span>
                    <?php endif; ?>
                </div>
                <div style="background:#fff;padding:12px;border-radius:8px;margin-bottom:10px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                        <span style="color:#666;">订单金额</span>
                        <span style="color:#333;font-weight:600;">¥<?php echo number_format($order['price'], 2); ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                        <span style="color:#666;">打手佣金比例</span>
                        <span style="color:#333;font-weight:600;">
                            <?php
                            $settingsStmt = $pdo->query("SELECT hunter_commission_rate FROM settings LIMIT 1");
                            $st = $settingsStmt->fetch(PDO::FETCH_ASSOC);
                            echo ($st['hunter_commission_rate'] ?? 70) . '%';
                            ?>
                        </span>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                        <span style="color:#666;">打手佣金总额</span>
                        <span style="color:#ff4d4f;font-weight:600;">¥<?php echo number_format($shareProfitInfo['total'], 2); ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;border-top:1px dashed #eee;padding-top:8px;margin-top:8px;">
                        <span style="color:#666;">分成人数</span>
                        <span style="color:#722ed1;font-weight:600;"><?php echo $shareProfitInfo['count']; ?> 人</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;background:#722ed1;color:#fff;padding:10px;border-radius:6px;margin-top:8px;">
                        <span>每人分得</span>
                        <span style="font-size:18px;font-weight:700;">¥<?php echo number_format($shareProfitInfo['per_person'], 2); ?></span>
                    </div>
                </div>
                <?php if (!empty($sharedUsers) && $isOwner): ?>
                <div style="font-size:13px;color:#666;margin-bottom:8px;">已共享用户：</div>
                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    <?php foreach ($sharedUsers as $su): ?>
                        <span style="padding:6px 12px;background:#fff;border-radius:20px;font-size:12px;display:inline-flex;align-items:center;gap:6px;">
                            <?php echo htmlspecialchars($su['username']); ?>
                            <span style="color:#722ed1;font-weight:600;">¥<?php echo number_format($shareProfitInfo['per_person'], 2); ?></span>
                            <?php if ($isOwner): ?>
                            <form method="post" style="margin:0;">
                                <input type="hidden" name="unshare_user_id" value="<?php echo $su['shared_user_id']; ?>">
                                <button type="submit" name="unshare_order" style="background:none;border:none;color:#ff4d4f;cursor:pointer;font-size:14px;padding:0;line-height:1;">×</button>
                            </form>
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- 提交订单模态框 -->
    <div class="modal-overlay" id="submitModal">
        <div class="modal-content">
            <div class="modal-header">
                <span>提交订单完成</span>
                <button class="modal-close" onclick="hideSubmitModal()">&times;</button>
            </div>
            <form id="submitForm" method="post" action="order_detail.php?id=<?php echo $order_id; ?>" enctype="multipart/form-data">
                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                <div class="form-group">
                    <label class="form-label">备注信息</label>
                    <textarea name="notes" class="form-control" placeholder="请填写订单完成情况说明" rows="4"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">完成凭证</label>
                    <div class="file-upload">
                        <button type="button" class="file-upload-btn" onclick="document.getElementById('imageUpload').click()">
                            <i class="fas fa-image"></i> 上传图片
                        </button>
                        <input type="file" name="images[]" id="imageUpload" multiple accept="image/*" onchange="previewFiles(this)" style="display: none;">
                    </div>
                    <span style="color:red;">必须上传图片和保留随机备注，否则订单无法提交成功</span>
                    <div class="file-preview" id="filePreview"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="hideSubmitModal()">取消</button>
                    <button type="submit" name="submit_order" class="btn btn-primary">
                        <i class="fas fa-check"></i> 确认提交
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 打回大厅模态框 -->
    <div class="modal-overlay" id="backModal">
        <div class="modal-content">
            <div class="modal-header">
                <span>打回大厅</span>
                <button class="modal-close" onclick="hideBackModal()">&times;</button>
            </div>
            <form method="post" action="order_detail.php?id=<?php echo $order_id; ?>">
                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                <div class="form-group">
                    <label class="form-label">备注信息</label>
                    <textarea name="back_notes" class="form-control" placeholder="请填写打回大厅的原因" rows="4"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="hideBackModal()">取消</button>
                    <button type="submit" name="back_to_hall" class="btn btn-primary">
                        <i class="fas fa-check"></i> 确认打回
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 转单模态框 -->
    <div class="modal-overlay" id="transferModal">
        <div class="modal-content">
            <div class="modal-header">
                <span>手动转单</span>
                <button class="modal-close" onclick="hideTransferModal()">&times;</button>
            </div>
            <form id="transferForm" method="post" action="order_detail.php?id=<?php echo $order_id; ?>">
                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                <div class="form-group">
                    <label class="form-label">搜索打手</label>
                    <input type="text" id="transferSearch" class="form-control" placeholder="输入用户名搜索..." oninput="filterTransferOptions()">
                </div>
                <div class="form-group">
                    <label class="form-label">选择打手</label>
                    <select name="target_hunter_id" class="form-control" id="transferSelect" required>
                        <option value="">请选择打手</option>
                        <?php foreach ($hunters as $hunter): ?>
                            <option value="<?php echo $hunter['id']; ?>" data-username="<?php echo htmlspecialchars($hunter['username']); ?>">
                                <?php echo htmlspecialchars($hunter['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">备注信息</label>
                    <textarea name="transfer_notes" class="form-control" placeholder="请填写转单原因" rows="4"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="hideTransferModal()">取消</button>
                    <button type="submit" name="transfer_order" class="btn btn-primary">
                        <i class="fas fa-check"></i> 确认转单
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 共享订单模态框 -->
    <div class="modal-overlay" id="shareModal">
        <div class="modal-content">
            <div class="modal-header">
                <span>共享订单</span>
                <button class="modal-close" onclick="hideShareModal()">&times;</button>
            </div>
            <form id="shareForm" method="post" action="order_detail.php?id=<?php echo $order_id; ?>">
                <input type="hidden" name="share_order" value="1">
                <div class="form-group">
                    <label class="form-label">搜索用户</label>
                    <input type="text" id="shareSearch" class="form-control" placeholder="输入用户名搜索..." oninput="filterShareOptions()">
                </div>
                <div class="form-group">
                    <label class="form-label">选择要共享的用户（可多选）</label>
                    <div style="max-height:250px;overflow-y:auto;border:1px solid #eaeaea;border-radius:12px;padding:10px;" id="shareOptionsContainer">
                        <?php
                        $availableHunters = [];
                        foreach ($hunters as $h) {
                            $isAlreadyShared = false;
                            foreach ($sharedUsers as $su) {
                                if ($su['shared_user_id'] == $h['id']) {
                                    $isAlreadyShared = true;
                                    break;
                                }
                            }
                            if (!$isAlreadyShared) {
                                $availableHunters[] = $h;
                            }
                        }
                        ?>
                        <?php if (empty($availableHunters)): ?>
                            <div style="text-align:center;color:#999;padding:20px;">暂无可共享的用户</div>
                        <?php else: ?>
                            <?php foreach ($availableHunters as $hunter): ?>
                                <label style="display:flex;align-items:center;padding:10px;cursor:pointer;border-bottom:1px solid #f0f0f0;" class="share-option-item" data-username="<?php echo htmlspecialchars($hunter['username']); ?>">
                                    <input type="checkbox" name="selected_users[]" value="<?php echo $hunter['id']; ?>" class="share-checkbox">
                                    <span style="font-size:14px;margin-left:10px;"><?php echo htmlspecialchars($hunter['username']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group" style="background:#fff3e0;padding:12px;border-radius:8px;font-size:13px;color:#e65100;">
                    <i class="fas fa-info-circle"></i> 共享后，被共享的用户可以查看此订单详情，但无法操作。订单完成后利润将平均分配给所有共享者。
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="hideShareModal()">取消</button>
                    <button type="submit" class="btn" style="background:#722ed1;color:#fff;border-color:#722ed1;">
                        <i class="fas fa-check"></i> 确认共享
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php require __DIR__ . '/footer.php'; ?>

    <script>
        function openSubmitModal() {
            document.getElementById('submitModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function hideSubmitModal() {
            document.getElementById('submitModal').classList.remove('active');
            document.getElementById('submitForm').reset();
            document.getElementById('filePreview').innerHTML = '';
            document.body.style.overflow = '';
        }
        
        function openBackModal() {
            document.getElementById('backModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function hideBackModal() {
            document.getElementById('backModal').classList.remove('active');
            document.body.style.overflow = '';
        }
        
        function openTransferModal() {
            document.getElementById('transferModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function hideTransferModal() {
            document.getElementById('transferModal').classList.remove('active');
            document.getElementById('transferForm').reset();
            document.body.style.overflow = '';
        }

        function openShareModal() {
            document.getElementById('shareModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function hideShareModal() {
            document.getElementById('shareModal').classList.remove('active');
            document.getElementById('shareSearch').value = '';
            filterShareOptions();
            document.body.style.overflow = '';
        }

        // 过滤转单打手选项
        function filterTransferOptions() {
            var searchText = document.getElementById('transferSearch').value.toLowerCase();
            var select = document.getElementById('transferSelect');
            var options = select.options;

            for (var i = 0; i < options.length; i++) {
                var option = options[i];
                if (i === 0) {
                    option.style.display = '';
                    continue;
                }
                var username = option.getAttribute('data-username') || '';
                if (username.toLowerCase().indexOf(searchText) !== -1 || searchText === '') {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            }
        }

        // 过滤共享用户选项
        function filterShareOptions() {
            var searchText = document.getElementById('shareSearch').value.toLowerCase();
            var container = document.getElementById('shareOptionsContainer');
            var items = container.querySelectorAll('.share-option-item');

            for (var i = 0; i < items.length; i++) {
                var item = items[i];
                var username = item.getAttribute('data-username') || '';
                if (username.toLowerCase().indexOf(searchText) !== -1 || searchText === '') {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            }

            // 检查是否还有可见项
            var visibleItems = container.querySelectorAll('.share-option-item[style=""], .share-option-item:not([style])');
            var hasVisible = false;
            for (var j = 0; j < items.length; j++) {
                if (items[j].style.display !== 'none') {
                    hasVisible = true;
                    break;
                }
            }
            var noResult = container.querySelector('.no-result');
            if (!hasVisible && searchText !== '') {
                if (!noResult) {
                    var msg = document.createElement('div');
                    msg.className = 'no-result';
                    msg.style.cssText = 'text-align:center;color:#999;padding:20px;';
                    msg.textContent = '未找到匹配的用户';
                    container.appendChild(msg);
                }
            } else {
                if (noResult) noResult.remove();
            }
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
            const fileInput = document.getElementById('imageUpload');
            fileInput.value = '';
        }

        // 表单验证
        document.getElementById('submitForm').addEventListener('submit', function(e) {
            const notes = document.querySelector('textarea[name="notes"]').value.trim();
            const fileInput = document.getElementById('imageUpload');
            const filePreview = document.getElementById('filePreview');
            
            if (!notes) {
                alert('请填写备注信息');
                e.preventDefault();
                return false;
            }
            
            if (!fileInput.files || fileInput.files.length === 0) {
                alert('请上传完成凭证图片');
                e.preventDefault();
                return false;
            }
        });
        
        // 关闭按钮通用处理
        document.querySelectorAll('.modal-close').forEach(function(btn, index) {
            btn.addEventListener('click', function() {
                if (index === 0) hideSubmitModal();
                if (index === 1) hideBackModal();
                if (index === 2) hideTransferModal();
                if (index === 3) hideShareModal();
            });
        });
        
        // 点击遮罩层关闭
        document.querySelectorAll('.modal-overlay').forEach(function(modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        <?php if (isset($_SESSION['form_data'])): ?>
            openSubmitModal();
            <?php unset($_SESSION['form_data']); ?>
        <?php endif; ?>
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
