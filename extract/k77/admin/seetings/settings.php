<?php
// 开启输出缓冲
ob_start();

// 引入数据库连接文件
require_once __DIR__.'/../../includes/session_manager.php';
require_once __DIR__.'/../../includes/common.php';
require_once __DIR__.'/../../includes/log_helper.php';

// 检查并修复数据库字段
function checkAndFixDatabaseFields($pdo) {
    // 列出所有需要的字段及其定义
    $requiredFields = [
        // 基本设置
        'site_title' => "varchar(255) DEFAULT '网站标题' COMMENT '网站名称'",
        'site_description' => "text COMMENT '网站描述'",
        'site_domain' => "varchar(255) DEFAULT '' COMMENT '网站域名'",
        'site_logo' => "varchar(255) DEFAULT '' COMMENT '网站Logo'",
        'system_version' => "varchar(20) DEFAULT 'v1.0.0.0' COMMENT '系统版本'",
        
        // 费用设置
        'deposit' => "decimal(10,2) DEFAULT '0.00' COMMENT '保证金金额'",
        'withdrawal_fee_rate' => "decimal(5,2) DEFAULT '0.00' COMMENT '提现手续费率(%)'",
        'hunter_commission_rate' => "decimal(5,2) DEFAULT '0.00' COMMENT '打手佣金比例(%)'",
        'admin_commission_rate' => "decimal(5,2) DEFAULT '0.00' COMMENT '管理员佣金比例(%)'",
        'manager_commission_rate' => "decimal(5,2) DEFAULT '0.00' COMMENT '经理佣金比例(%)'",
        'manager_invite_hunter_commission_rate' => "decimal(5,2) DEFAULT '0.00' COMMENT '管事邀请打手返佣比例(%)'",
        
        // 支付设置
        'payment_api_url' => "varchar(255) DEFAULT '' COMMENT '支付API地址'",
        'payment_app_id' => "varchar(100) DEFAULT '' COMMENT '支付应用ID'",
        'payment_app_key' => "varchar(100) DEFAULT '' COMMENT '支付应用密钥'",
        'payment_notify_url' => "varchar(255) DEFAULT '' COMMENT '支付通知URL'",
        
        // 微信官方支付设置
        'wechat_mch_id' => "varchar(100) DEFAULT '' COMMENT '微信商户号'",
        'wechat_mch_key' => "varchar(100) DEFAULT '' COMMENT '微信商户密钥'",
        'wechat_mch_cert_path' => "varchar(255) DEFAULT '' COMMENT '微信商户证书路径'",
        'wechat_appid' => "varchar(100) DEFAULT '' COMMENT '微信AppID'",
        'wechat_secret' => "varchar(100) DEFAULT '' COMMENT '微信AppSecret'",
        'wechat_pay_enabled' => "tinyint(1) DEFAULT '0' COMMENT '微信支付是否启用：0-否，1-是'",
        'payment_method' => "varchar(20) DEFAULT 'api' COMMENT '支付方式：api-API支付，wechat-微信官方支付'",
        
        // 其他设置
        'order_mode' => "varchar(20) DEFAULT 'guarantee' COMMENT '订单模式：guarantee-保证金模式，direct-直接模式'",
        'announcement' => "text COMMENT '网站公告'",
        'customer_center_qrcode' => "varchar(255) DEFAULT '' COMMENT '客服中心二维码'",
        'hunter_join_url' => "varchar(255) DEFAULT '' COMMENT '打手入驻链接'",
        'customer_notice' => "text COMMENT '客户须知'",
        'appid' => "varchar(100) DEFAULT '' COMMENT '微信AppID'",
        'appkey' => "varchar(100) DEFAULT '' COMMENT '微信AppKey'",
        'wechat_customer_service' => "varchar(100) DEFAULT '' COMMENT '微信客服'",
        'wechat_group' => "varchar(255) DEFAULT '' COMMENT '微信群链接'",
        'enterprise_customer_link' => "varchar(255) DEFAULT '' COMMENT '企业客服链接'",
        'qq_customer_service' => "varchar(50) DEFAULT '' COMMENT 'QQ客服'",
        'h5_announcement' => "text COMMENT 'H5首页公告'"
    ];
    
    // 检查并添加所有缺失的字段
    foreach ($requiredFields as $fieldName => $fieldDef) {
        $colExists = $pdo->query("SHOW COLUMNS FROM settings LIKE '$fieldName'")->rowCount() > 0;
        if (!$colExists) {
            $sql = "ALTER TABLE settings ADD COLUMN $fieldName $fieldDef";
            $pdo->exec($sql);
        }
    }
}

// 执行字段检查和修复
checkAndFixDatabaseFields($pdo);

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // 准备更新数据
        $settings = [
            // 基础设置
            'site_title' => $_POST['site_title'] ?? '',
            'site_description' => $_POST['site_description'] ?? '',
            'site_domain' => $_POST['site_domain'] ?? getSystemDomain(),
            'deposit' => floatval($_POST['deposit'] ?? 0),
            'withdrawal_fee_rate' => floatval($_POST['withdrawal_fee_rate'] ?? 0),
            'hunter_commission_rate' => floatval($_POST['hunter_commission_rate'] ?? 0),
            'admin_commission_rate' => floatval($_POST['admin_commission_rate'] ?? 0),
            'manager_commission_rate' => floatval($_POST['manager_commission_rate'] ?? 0),
            'manager_invite_hunter_commission_rate' => floatval($_POST['manager_invite_hunter_commission_rate'] ?? 0),
            'payment_api_url' => $_POST['payment_api_url'] ?? '',
            'payment_app_id' => $_POST['payment_app_id'] ?? '',
            'payment_app_key' => $_POST['payment_app_key'] ?? '',
            'payment_notify_url' => $_POST['payment_notify_url'] ?? getSystemDomain(),
            'site_logo' => $_POST['existing_logo'] ?? 'assets/images/logo.png',
            'order_mode' => $_POST['order_mode'] ?? 'deposit',
            'announcement' => $_POST['announcement'] ?? '',
            'wechat_customer_service' => $_POST['wechat_customer_service'] ?? '',
            'wechat_group' => $_POST['wechat_group'] ?? '',
            'enterprise_customer_link' => $_POST['enterprise_customer_link'] ?? '',
            'qq_customer_service' => $_POST['qq_customer_service'] ?? '',
            'system_version' => $_POST['system_version'] ?? 'v1.0.0.0',
            'h5_announcement' => $_POST['h5_announcement'] ?? '',
            // 微信官方支付设置
            'wechat_mch_id' => $_POST['wechat_mch_id'] ?? '',
            'wechat_mch_key' => $_POST['wechat_mch_key'] ?? '',
            'wechat_mch_cert_path' => $_POST['wechat_mch_cert_path'] ?? '',
            'wechat_appid' => $_POST['wechat_appid'] ?? '',
            'wechat_secret' => $_POST['wechat_secret'] ?? '',
            'wechat_pay_enabled' => isset($_POST['wechat_pay_enabled']) ? 1 : 0,
            'payment_method' => $_POST['payment_method'] ?? 'api'
        ];
        
        // 处理logo上传
        if (!empty($_FILES['site_logo']['name'])) {
            $uploadDir = '../../assets/images/logo/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = basename($_FILES['site_logo']['name']);
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
            
            if (in_array($fileExt, $allowedExts)) {
                $newFileName = 'logo_' . time() . '.' . $fileExt;
                $uploadPath = $uploadDir . $newFileName;
                
                if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $uploadPath)) {
                    if (!empty($settings['site_logo']) && $settings['site_logo'] != 'assets/images/logo.png') {
                        @unlink('../../' . $settings['site_logo']);
                    }
                    $settings['site_logo'] = 'assets/images/logo/' . $newFileName;
                } else {
                    throw new Exception('Logo上传失败，请检查目录权限');
                }
            } else {
                throw new Exception('只允许上传JPG, PNG, GIF或SVG格式的图片');
            }
        }
        
        // 处理二维码上传
        if (!empty($_FILES['customer_center_qrcode']['name'])) {
            $uploadDir = '../../assets/images/qrcode/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = basename($_FILES['customer_center_qrcode']['name']);
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($fileExt, $allowedExts)) {
                $newFileName = 'qrcode_' . time() . '.' . $fileExt;
                $uploadPath = $uploadDir . $newFileName;
                
                if (move_uploaded_file($_FILES['customer_center_qrcode']['tmp_name'], $uploadPath)) {
                    if (!empty($settings['customer_center_qrcode'])) {
                        @unlink('../../' . $settings['customer_center_qrcode']);
                    }
                    $settings['customer_center_qrcode'] = 'assets/images/qrcode/' . $newFileName;
                } else {
                    throw new Exception('二维码上传失败，请检查目录权限');
                }
            } else {
                throw new Exception('只允许上传JPG, PNG或GIF格式的图片');
            }
        }
        
        // 确保域名格式正确
        if (!preg_match('/^https?:\/\//i', $settings['site_domain'])) {
            $settings['site_domain'] = 'https://' . $settings['site_domain'];
        }
        $settings['site_domain'] = rtrim($settings['site_domain'], '/');
        
        // 更新数据库
        $sql = "UPDATE settings SET 
            site_title = :site_title, 
            site_description = :site_description, 
            site_domain = :site_domain,
            site_logo = :site_logo,
            deposit = :deposit, 
            withdrawal_fee_rate = :withdrawal_fee_rate,
            hunter_commission_rate = :hunter_commission_rate,
            admin_commission_rate = :admin_commission_rate,
            manager_commission_rate = :manager_commission_rate,
            manager_invite_hunter_commission_rate = :manager_invite_hunter_commission_rate,
            payment_api_url = :payment_api_url, 
            payment_app_id = :payment_app_id, 
            payment_app_key = :payment_app_key, 
            payment_notify_url = :payment_notify_url,
            order_mode = :order_mode,
            announcement = :announcement,
            wechat_customer_service = :wechat_customer_service,
            wechat_group = :wechat_group,
            enterprise_customer_link = :enterprise_customer_link,
            qq_customer_service = :qq_customer_service,
            system_version = :system_version,
            h5_announcement = :h5_announcement,
            wechat_mch_id = :wechat_mch_id,
            wechat_mch_key = :wechat_mch_key,
            wechat_mch_cert_path = :wechat_mch_cert_path,
            wechat_appid = :wechat_appid,
            wechat_secret = :wechat_secret,
            wechat_pay_enabled = :wechat_pay_enabled,
            payment_method = :payment_method
            WHERE id = 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($settings);
        
        if ($stmt->rowCount() > 0) {
            // 记录管理员操作日志
            $admin_id = $_SESSION['admin_id'] ?? null;
            $admin_name = $_SESSION['admin_username'] ?? '未知管理员';
            $changed_fields = array_keys(array_filter($settings, function($key) {
                return true;
            }, ARRAY_FILTER_USE_KEY));
            $operation_details = "更新系统设置：修改字段=" . implode(',', $changed_fields);
            log_admin_operation($pdo, $admin_id, $admin_name, 'update_settings', $operation_details);
            
            $_SESSION['success'] = '网站设置已成功更新';
        } else {
            $_SESSION['info'] = '没有更改任何设置';
        }
        
        $pdo->commit();
        header('Location: settings.php');
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = '更新设置失败: ' . $e->getMessage();
        header('Location: settings.php');
        exit();
    }
}

// 获取当前系统域名
function getSystemDomain() {
    // 优先从HTTP_HOST获取
    if (isset($_SERVER['HTTP_HOST'])) {
        $host = $_SERVER['HTTP_HOST'];
        // 获取协议
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        // 返回当前访问的完整域名
        return $protocol . '://' . $host;
    }
    // 默认为localhost
    return 'http://localhost';
}

// 获取当前设置
$settings = $pdo->query("SELECT * FROM settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);

// 自动设置网站域名（带泛解析）
$settings['site_domain'] = getSystemDomain();

// 自动设置支付回调地址
$settings['payment_notify_url'] = getSystemDomain();

// 初始化新字段
$settings['wechat_customer_service'] = $settings['wechat_customer_service'] ?? '';
$settings['wechat_group'] = $settings['wechat_group'] ?? '';
$settings['enterprise_customer_link'] = $settings['enterprise_customer_link'] ?? '';
$settings['qq_customer_service'] = $settings['qq_customer_service'] ?? '';
$settings['system_version'] = $settings['system_version'] ?? 'v1.0.0.0';
$settings['h5_announcement'] = $settings['h5_announcement'] ?? '';
$settings['hunter_commission_rate'] = $settings['hunter_commission_rate'] ?? 0.00;
$settings['admin_commission_rate'] = $settings['admin_commission_rate'] ?? 0.00;
$settings['manager_commission_rate'] = $settings['manager_commission_rate'] ?? 0.00;
$settings['manager_invite_hunter_commission_rate'] = $settings['manager_invite_hunter_commission_rate'] ?? 0.00;
$settings['order_mode'] = $settings['order_mode'] ?? 'guarantee';
$settings['announcement'] = $settings['announcement'] ?? '';
// 微信官方支付初始化
$settings['wechat_mch_id'] = $settings['wechat_mch_id'] ?? '';
$settings['wechat_mch_key'] = $settings['wechat_mch_key'] ?? '';
$settings['wechat_mch_cert_path'] = $settings['wechat_mch_cert_path'] ?? '';
$settings['wechat_appid'] = $settings['wechat_appid'] ?? '';
$settings['wechat_secret'] = $settings['wechat_secret'] ?? '';
$settings['wechat_pay_enabled'] = $settings['wechat_pay_enabled'] ?? 0;
$settings['payment_method'] = $settings['payment_method'] ?? 'api';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>网站设置</title>

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #ffffff; color: #333; line-height: 1.5; padding: 20px; font-size: 13px; }
        .settings-container { max-width: 1200px; margin: 0 auto; }
        h2 { font-size: 18px; font-weight: 600; margin-bottom: 16px; color: #000; }
        
        /* 标签页样式 */
        .settings-tabs {
            display: flex;
            border-bottom: 1px solid #e5e5e5;
            margin-bottom: 16px;
        }

        .settings-tab {
            padding: 10px 16px;
            border: 1px solid transparent;
            border-bottom: none;
            background: none;
            color: #666;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s ease;
            border-radius: 8px 8px 0 0;
        }

        .settings-tab:hover {
            color: #333;
            background: #f5f5f5;
        }

        .settings-tab.active {
            color: #333;
            border-color: #e5e5e5;
            border-bottom-color: #ffffff;
            background: #ffffff;
            font-weight: 500;
        }

        /* 内容区域 */
        .settings-content {
            display: none;
            padding: 16px;
            border: 1px solid #e5e5e5;
            margin-bottom: 16px;
            background: #ffffff;
            border-radius: 8px;
        }

        .settings-content.active {
            display: block;
        }

        /* 表单样式 */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: -10px;
        }

        .col-md-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 10px;
        }

        .col-md-12 {
            flex: 0 0 100%;
            max-width: 100%;
            padding: 10px;
        }

        @media screen and (max-width: 768px) {
            .col-md-6 {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e5e5e5;
            font-size: 13px;
            color: #333;
            transition: all 0.2s ease;
            border-radius: 8px;
            background: #ffffff;
        }

        .form-control:focus {
            outline: none;
            border-color: #1976d2;
            box-shadow: 0 0 0 2px rgba(25, 118, 210, 0.1);
        }

        .form-control::placeholder {
            color: #999;
        }

        textarea.form-control {
            min-height: 80px;
            resize: vertical;
        }

        select.form-control {
            background: #ffffff;
        }

        /* 预览区域 */
        .preview-container {
            margin-bottom: 12px;
        }

        .preview-box {
            width: 200px;
            height: 120px;
            border: 1px solid #e5e5e5;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fafafa;
            overflow: hidden;
            border-radius: 8px;
        }

        .preview-box img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .preview-box .text-muted {
            color: #999;
            font-size: 12px;
        }

        /* 消息提示 */
        .alert {
            padding: 12px 14px;
            border: 1px solid #e5e5e5;
            margin-bottom: 16px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-radius: 8px;
            background: #fafafa;
        }

        .alert-danger {
            border-color: #ffebee;
            background: #ffebee;
            color: #d32f2f;
        }

        .alert-success {
            border-color: #e8f5e9;
            background: #e8f5e9;
            color: #388e3c;
        }

        .alert i {
            color: #999;
            font-size: 14px;
        }

        /* 提交按钮 */
        .text-end {
            text-align: right;
        }

        .btn-primary {
            padding: 12px 24px;
            border: 1px solid #e0e0e0;
            background: #f5f5f5;
            color: #333;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border-radius: 8px;
        }

        .btn-primary:hover {
            background: #e0e0e0;
            color: #000;
        }

        /* 响应式设计 */
        @media screen and (max-width: 768px) {
            body {
                padding: 16px;
            }

            h2 {
                font-size: 16px;
                margin-bottom: 12px;
            }

            .settings-tabs {
                overflow-x: auto;
                white-space: nowrap;
            }

            .settings-tab {
                padding: 8px 12px;
                font-size: 12px;
            }

            .settings-content {
                padding: 12px;
            }

            .preview-box {
                width: 150px;
                height: 100px;
            }

            .btn-primary {
                padding: 10px 20px;
                font-size: 12px;
            }
        }
    </style>
    <!-- 引入 Bootstrap Icons 用于图标 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="settings-container">
        <h2>网站设置</h2>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle"></i>
                <?= $_SESSION['error'] ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i>
                <?= $_SESSION['success'] ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <div class="settings-tabs">
            <div class="settings-tab active" data-tab="basic">基本设置</div>
            <div class="settings-tab" data-tab="payment">支付设置</div>
            <div class="settings-tab" data-tab="rate">费率设置</div>
            <div class="settings-tab" data-tab="customer">客服设置</div>
            <div class="settings-tab" data-tab="h5">H5设置</div>
        </div>
        
        <form method="post" enctype="multipart/form-data">
            <!-- 基本设置 -->
            <div class="settings-content active" id="basic-settings">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>网站标题</label>
                            <input type="text" name="site_title" class="form-control" required 
                                   value="<?= htmlspecialchars($settings['site_title']) ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>网站域名</label>
                            <input type="url" name="site_domain" class="form-control" required 
                                   value="<?= htmlspecialchars($settings['site_domain']) ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!--<div class="col-md-6">-->
                    <!--    <div class="form-group">-->
                    <!--        <label>系统版本号</label>-->
                    <!--        <input type="text" name="system_version" class="form-control" -->
                    <!--               value="<?= htmlspecialchars($settings['system_version']) ?>">-->
                    <!--    </div>-->
                    <!--</div>-->
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>接单模式</label>
                            <select name="order_mode" class="form-control">
                                <option value="deposit" <?= $settings['order_mode'] == 'deposit' ? 'selected' : '' ?>>保证金模式</option>
                                <option value="member" <?= $settings['order_mode'] == 'member' ? 'selected' : '' ?>>会员模式</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!--<div class="form-group">-->
                <!--    <label>网站描述</label>-->
                <!--    <textarea name="site_description" class="form-control" rows="3"><?= htmlspecialchars($settings['site_description']) ?></textarea>-->
                <!--</div>-->
                
                <!--<div class="form-group">-->
                <!--    <label>网站公告</label>-->
                <!--    <textarea name="announcement" class="form-control" rows="3"><?= htmlspecialchars($settings['announcement']) ?></textarea>-->
                <!--</div>-->
                
                <div class="form-group">
                    <label>网站Logo</label>
                    <div class="preview-container">
                        <div class="preview-box">
                            <?php if (!empty($settings['site_logo'])): ?>
                                <img src="<?= htmlspecialchars($settings['site_domain'] . '/' . ltrim($settings['site_logo'], '/')) ?>">
                            <?php else: ?>
                                <span class="text-muted">暂无Logo</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <input type="file" name="site_logo" class="form-control">
                    <input type="hidden" name="existing_logo" value="<?= htmlspecialchars($settings['site_logo']) ?>">
                </div>
            </div>
            
            <!-- 支付设置 -->
    </div>
</div><div class="settings-content" id="payment-settings">
    <div class="alert alert-success" style="margin-bottom: 20px;">
        <i class="bi bi-info-circle"></i>
        <strong>支付方式：</strong>请选择使用的支付方式，两种方式可以单独配置
    </div>

    <div class="form-group">
        <label>支付方式选择</label>
        <div class="row">
            <div class="col-md-6">
                <label style="display: flex; align-items: center; gap: 8px; padding: 12px; border: 1px solid #e5e5e5; border-radius: 8px; cursor: pointer; background: <?= $settings['payment_method'] == 'api' ? '#f0f9ff' : '#fff' ?>;">
                    <input type="radio" name="payment_method" value="api" <?= $settings['payment_method'] == 'api' ? 'checked' : '' ?> style="width: 16px; height: 16px;">
                    <span>API支付（第三方支付）</span>
                </label>
            </div>
            <div class="col-md-6">
                <label style="display: flex; align-items: center; gap: 8px; padding: 12px; border: 1px solid #e5e5e5; border-radius: 8px; cursor: pointer; background: <?= $settings['payment_method'] == 'wechat' ? '#f0f9ff' : '#fff' ?>;">
                    <input type="radio" name="payment_method" value="wechat" <?= $settings['payment_method'] == 'wechat' ? 'checked' : '' ?> style="width: 16px; height: 16px;">
                    <span>微信官方支付</span>
                </label>
            </div>
        </div>
    </div>

    <!-- API支付配置 -->
    <div style="border: 1px solid #e5e5e5; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
        <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 16px;">API支付配置</h4>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>支付API地址</label>
                    <input type="url" name="payment_api_url" class="form-control" value="<?= htmlspecialchars($settings['payment_api_url'] ?? '') ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>支付应用ID</label>
                    <input type="text" name="payment_app_id" class="form-control" value="<?= htmlspecialchars($settings['payment_app_id'] ?? '') ?>">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>支付应用密钥</label>
                    <input type="text" name="payment_app_key" class="form-control" value="<?= htmlspecialchars($settings['payment_app_key'] ?? '') ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>支付回调地址</label>
                    <input type="url" name="payment_notify_url" class="form-control" value="<?= htmlspecialchars($settings['payment_notify_url'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- 微信官方支付配置（支持支付+退款完整功能） -->
    <div style="border: 1px solid #e5e5e5; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h4 style="font-size: 14px; font-weight: 600;">微信官方支付配置</h4>
            <label style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="wechat_pay_enabled" value="1" <?= !empty($settings['wechat_pay_enabled']) ? 'checked' : '' ?> style="width: 16px; height: 16px;">
                <span style="font-size: 13px;">启用微信支付</span>
            </label>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group" style="margin-bottom:12px">
                    <label>微信AppID</label>
                    <input type="text" name="wechat_appid" class="form-control" placeholder="请输入微信公众号/小程序AppID" value="<?= htmlspecialchars($settings['wechat_appid'] ?? '') ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group" style="margin-bottom:12px">
                    <label>微信AppSecret</label>
                    <input type="text" name="wechat_secret" class="form-control" placeholder="请输入微信AppSecret" value="<?= htmlspecialchars($settings['wechat_secret'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group" style="margin-bottom:12px">
                    <label>微信商户号 MCH_ID</label>
                    <input type="text" name="wechat_mch_id" class="form-control" placeholder="请输入微信支付商户号" value="<?= htmlspecialchars($settings['wechat_mch_id'] ?? '') ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group" style="margin-bottom:12px">
                    <label>商户API密钥（V2密钥）</label>
                    <input type="text" name="wechat_mch_key" class="form-control" placeholder="请输入微信商户V2 API密钥" value="<?= htmlspecialchars($settings['wechat_mch_key'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group" style="margin-bottom:12px">
                    <label>商户APIv3密钥</label>
                    <input type="text" name="wechat_mch_v3_key" class="form-control" placeholder="退款必备：微信商户APIv3密钥" value="<?= htmlspecialchars($settings['wechat_mch_v3_key'] ?? '') ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group" style="margin-bottom:12px">
                    <label>商户证书序列号</label>
                    <input type="text" name="wechat_mch_serial_no" class="form-control" placeholder="退款必备：商户证书序列号" value="<?= htmlspecialchars($settings['wechat_mch_serial_no'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="form-group" style="margin-bottom:12px">
                    <label>商户私钥文件路径</label>
                    <input type="text" name="wechat_mch_private_key" class="form-control" placeholder="退款必备：商户apiclient_key.pem 绝对路径" value="<?= htmlspecialchars($settings['wechat_mch_private_key'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label>支付回调通知地址</label>
                    <input type="url" name="wechat_notify_url" class="form-control" placeholder="支付成功异步回调地址" value="<?= htmlspecialchars($settings['wechat_notify_url'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>
</div>
                
            </div>
            
            <!-- 费率设置 -->
            <div class="settings-content" id="rate-settings">
                <div class="alert alert-success" style="margin-bottom: 20px;">
                    <i class="bi bi-info-circle"></i>
                    <strong>费率说明：</strong>以下费率需要加起来等于100%：
                    <br>• 打手佣金比例 + 管理员佣金比例 + 管事佣金比例 = 100%
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>保证金金额</label>
                            <input type="number" name="deposit" class="form-control" step="0.01" 
                                   value="<?= htmlspecialchars($settings['deposit']) ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>提现手续费率(%)</label>
                            <input type="number" name="withdrawal_fee_rate" class="form-control" step="0.01" 
                                   value="<?= htmlspecialchars($settings['withdrawal_fee_rate']) ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>打手佣金比例(%)</label>
                            <input type="number" name="hunter_commission_rate" class="form-control" step="0.01" 
                                   value="<?= htmlspecialchars($settings['hunter_commission_rate']) ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>管理员佣金比例(%)</label>
                            <input type="number" name="admin_commission_rate" class="form-control" step="0.01" 
                                   value="<?= htmlspecialchars($settings['admin_commission_rate']) ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>管事佣金比例(%)</label>
                            <input type="number" name="manager_commission_rate" class="form-control" step="0.01" 
                                   value="<?= htmlspecialchars($settings['manager_commission_rate']) ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>管事邀请打手返佣比例(%)</label>
                            <input type="number" name="manager_invite_hunter_commission_rate" class="form-control" step="0.01" 
                                   value="<?= htmlspecialchars($settings['manager_invite_hunter_commission_rate']) ?>">
                        </div>
                    </div>
                </div>
            </div>
            

            
            <!-- 客服设置 -->
            <div class="settings-content" id="customer-settings">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>微信客服号</label>
                            <input type="text" name="wechat_customer_service" class="form-control" 
                                   value="<?= htmlspecialchars($settings['wechat_customer_service']) ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>微信客服群</label>
                            <input type="text" name="wechat_group" class="form-control" 
                                   value="<?= htmlspecialchars($settings['wechat_group']) ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>客服链接</label>
                            <input type="url" name="enterprise_customer_link" class="form-control" 
                                   value="<?= htmlspecialchars($settings['enterprise_customer_link']) ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>QQ客服</label>
                            <input type="text" name="qq_customer_service" class="form-control" 
                                   value="<?= htmlspecialchars($settings['qq_customer_service']) ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- H5设置 -->
            <div class="settings-content" id="h5-settings">
                <div class="form-group">
                    <label>H5首页公告</label>
                    <textarea name="h5_announcement" class="form-control" rows="3"><?= htmlspecialchars($settings['h5_announcement']) ?></textarea>
                </div>
            </div>
            
            <div class="text-end">
                <button type="submit" class="btn-primary">保存设置</button>
            </div>
        </form>
    </div>

    <script>
        // 切换标签页
        document.querySelectorAll('.settings-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // 移除所有active类
                document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.settings-content').forEach(c => c.classList.remove('active'));
                
                // 添加active类
                this.classList.add('active');
                document.getElementById(this.dataset.tab + '-settings').classList.add('active');
            });
        });
        
        // 图片预览功能
        function previewImage(input, previewBox) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewBox.innerHTML = `<img src="${e.target.result}">`;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // 监听文件上传变化
        const logoInput = document.querySelector('input[name="site_logo"]');
        const logoPreview = document.querySelector('.preview-box');
        
        if (logoInput && logoPreview) {
            logoInput.addEventListener('change', function() {
                previewImage(this, logoPreview);
            });
        }
        
        const qrcodeInput = document.querySelector('input[name="customer_center_qrcode"]');
        const qrcodePreview = document.querySelectorAll('.preview-box')[1];
        
        if (qrcodeInput && qrcodePreview) {
            qrcodeInput.addEventListener('change', function() {
                previewImage(this, qrcodePreview);
            });
        }
        
        // 卡片入场动画
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.settings-container');
            if (container) {
                container.style.opacity = '0';
                container.style.transform = 'translateY(10px)';
                
                setTimeout(() => {
                    container.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                    container.style.opacity = '1';
                    container.style.transform = 'translateY(0)';
                }, 50);
            }
        });
    </script>
</body>
</html>