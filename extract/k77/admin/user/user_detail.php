<?php
require_once 'header.php';

// 检查是否有用户ID参数
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: user_list.php");
    exit();
}

$user_id = (int)$_GET['id'];

// 获取用户当前信息
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: user_list.php");
    exit();
}

$user = $result->fetch_assoc();

// 处理密码修改
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    if (empty($new_password) || empty($confirm_password)) {
        $password_error = "新密码和确认密码不能为空";
    } elseif ($new_password !== $confirm_password) {
        $password_error = "两次输入的密码不一致";
    } else {
        // 更新密码
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            $password_success = "密码修改成功";
        } else {
            $password_error = "密码修改失败: " . $conn->error;
        }
    }
}

// 处理通知设置更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notifications'])) {
    // 这里假设您的users表有这些通知设置字段
    $notifications = [
        'company_news' => isset($_POST['company_news']) ? 1 : 0,
        'push_notifications' => isset($_POST['push_notifications']) ? 1 : 0,
        'weekly_newsletters' => isset($_POST['weekly_newsletters']) ? 1 : 0,
        'meetups_near_you' => isset($_POST['meetups_near_you']) ? 1 : 0,
        'order_notifications' => isset($_POST['order_notifications']) ? 1 : 0
    ];
    
    $stmt = $conn->prepare("UPDATE users SET 
        company_news = ?, 
        push_notifications = ?, 
        weekly_newsletters = ?, 
        meetups_near_you = ?, 
        order_notifications = ? 
        WHERE id = ?");
    
    $stmt->bind_param("iiiiii", 
        $notifications['company_news'],
        $notifications['push_notifications'],
        $notifications['weekly_newsletters'],
        $notifications['meetups_near_you'],
        $notifications['order_notifications'],
        $user_id);
    
    if ($stmt->execute()) {
        $notification_success = "通知设置已更新";
        // 重新获取用户信息以显示更新后的通知设置
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
    } else {
        $notification_error = "更新通知设置时出错: " . $conn->error;
    }
}
?>

<div class="dashboard-main-body">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <h6 class="fw-semibold mb-0">用户详情</h6>
        <ul class="d-flex align-items-center gap-2">
            <li class="fw-medium">
                <a href="index.php" class="d-flex align-items-center gap-1 hover-text-primary">
                    <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                    控制面板
                </a>
            </li>
            <li>-</li>
            <li class="fw-medium">
                <a href="user_list.php" class="hover-text-primary">用户列表</a>
            </li>
            <li>-</li>
            <li class="fw-medium">用户详情</li>
        </ul>
    </div>

    <div class="row gy-4">
        <!-- 左侧用户信息卡片 -->
        <div class="col-lg-4">
            <div class="user-grid-card position-relative border radius-16 overflow-hidden bg-base h-100">
                <img src="/assets/images/user-grid/user-grid-bg1.png" alt="背景图" class="w-100 object-fit-cover">
                <div class="pb-24 ms-16 mb-24 me-16 mt--100">
                    <div class="text-center border border-top-0 border-start-0 border-end-0">
                        <?php
                        // 处理头像路径
                        $avatar_path = '/assets/images/default_avatar.png'; // 默认路径
                        
                        if (!empty($user['avatar'])) {
                            if (filter_var($user['avatar'], FILTER_VALIDATE_URL)) {
                                $avatar_path = $user['avatar'];
                            } elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . $user['avatar'])) {
                                $avatar_path = $user['avatar'];
                            }
                        }
                        ?>
                        <img src="<?php echo $avatar_path; ?>" 
                             alt="<?php echo htmlspecialchars($user['username']); ?>" 
                             class="border br-white border-width-2-px w-200-px h-200-px rounded-circle object-fit-cover">
                        <h6 class="mb-0 mt-16"><?php echo htmlspecialchars($user['username']); ?></h6>
                        <span class="text-secondary-light mb-16"><?php echo htmlspecialchars($user['phone']); ?></span>
                    </div>
                    <div class="mt-24">
                        <h6 class="text-xl mb-16">个人信息</h6>
                        <ul>
                            <li class="d-flex align-items-center gap-1 mb-12">
                                <span class="w-30 text-md fw-semibold text-primary-light">用户名</span>
                                <span class="w-70 text-secondary-light fw-medium">: <?php echo htmlspecialchars($user['username']); ?></span>
                            </li>
                            <li class="d-flex align-items-center gap-1 mb-12">
                                <span class="w-30 text-md fw-semibold text-primary-light">手机号</span>
                                <span class="w-70 text-secondary-light fw-medium">: <?php echo htmlspecialchars($user['phone']); ?></span>
                            </li>
                            <li class="d-flex align-items-center gap-1 mb-12">
                                <span class="w-30 text-md fw-semibold text-primary-light">真实姓名</span>
                                <span class="w-70 text-secondary-light fw-medium">: <?php echo !empty($user['real_name']) ? htmlspecialchars($user['real_name']) : '未设置'; ?></span>
                            </li>
                            <li class="d-flex align-items-center gap-1 mb-12">
                                <span class="w-30 text-md fw-semibold text-primary-light">身份证号</span>
                                <span class="w-70 text-secondary-light fw-medium">: <?php echo !empty($user['id_card']) ? htmlspecialchars($user['id_card']) : '未设置'; ?></span>
                            </li>
                            <li class="d-flex align-items-center gap-1 mb-12">
                                <span class="w-30 text-md fw-semibold text-primary-light">支付宝账号</span>
                                <span class="w-70 text-secondary-light fw-medium">: <?php echo !empty($user['alipay_account']) ? htmlspecialchars($user['alipay_account']) : '未设置'; ?></span>
                            </li>
                            <li class="d-flex align-items-center gap-1 mb-12">
                                <span class="w-30 text-md fw-semibold text-primary-light">账户余额</span>
                                <span class="w-70 text-secondary-light fw-medium">: ¥<?php echo number_format($user['balance'], 2); ?></span>
                            </li>
                            <li class="d-flex align-items-center gap-1 mb-12">
                                <span class="w-30 text-md fw-semibold text-primary-light">保证金</span>
                                <span class="w-70 text-secondary-light fw-medium">: ¥<?php echo number_format($user['deposit'], 2); ?></span>
                            </li>
                            <li class="d-flex align-items-center gap-1 mb-12">
                                <span class="w-30 text-md fw-semibold text-primary-light">会员等级</span>
                                <span class="w-70 text-secondary-light fw-medium">: 
                                    <?php 
                                    switch($user['member_level']) {
                                        case '普通会员': echo '<span class="badge bg-secondary">普通会员</span>'; break;
                                        case '普通打手': echo '<span class="badge bg-primary">普通打手</span>'; break;
                                        case '大神打手': echo '<span class="badge bg-info">大神打手</span>'; break;
                                        case '至尊打手': echo '<span class="badge bg-warning">至尊打手</span>'; break;
                                        default: echo '<span class="badge bg-secondary">'.$user['member_level'].'</span>';
                                    }
                                    ?>
                                </span>
                            </li>
                            <li class="d-flex align-items-center gap-1 mb-12">
                                <span class="w-30 text-md fw-semibold text-primary-light">账号状态</span>
                                <span class="w-70 text-secondary-light fw-medium">: 
                                    <?php 
                                    $status_class = '';
                                    switch($user['status']) {
                                        case '正常': $status_class = 'text-success-600'; break;
                                        case '封禁': $status_class = 'text-danger-600'; break;
                                        case '异常': $status_class = 'text-warning-600'; break;
                                        default: $status_class = 'text-secondary-light';
                                    }
                                    ?>
                                    <span class="<?php echo $status_class; ?>"><?php echo $user['status']; ?></span>
                                </span>
                            </li>
                            <li class="d-flex align-items-center gap-1">
                                <span class="w-30 text-md fw-semibold text-primary-light">注册时间</span>
                                <span class="w-70 text-secondary-light fw-medium">: <?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- 右侧选项卡内容 -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-body p-24">
                    <ul class="nav border-gradient-tab nav-pills mb-20 d-inline-flex" id="pills-tab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link d-flex align-items-center px-24 active" id="pills-edit-profile-tab" data-bs-toggle="pill" data-bs-target="#pills-edit-profile" type="button" role="tab" aria-controls="pills-edit-profile" aria-selected="true">
                                编辑资料
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link d-flex align-items-center px-24" id="pills-change-password-tab" data-bs-toggle="pill" data-bs-target="#pills-change-password" type="button" role="tab" aria-controls="pills-change-password" aria-selected="false" tabindex="-1">
                                修改密码
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link d-flex align-items-center px-24" id="pills-notification-tab" data-bs-toggle="pill" data-bs-target="#pills-notification" type="button" role="tab" aria-controls="pills-notification" aria-selected="false" tabindex="-1">
                                通知设置
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="pills-tabContent">   
                        <!-- 编辑资料选项卡 -->
                        <div class="tab-pane fade show active" id="pills-edit-profile" role="tabpanel" aria-labelledby="pills-edit-profile-tab" tabindex="0">
                            <form action="edit_user.php?id=<?php echo $user_id; ?>" method="post" enctype="multipart/form-data">
                                <h6 class="text-md text-primary-light mb-16">用户头像</h6>
                                <!-- 头像上传区域 -->
                                <div class="mb-24 mt-16">
                                    <div class="avatar-upload">
                                        <div class="avatar-edit position-absolute bottom-0 end-0 me-24 mt-16 z-1 cursor-pointer">
                                            <input type='file' id="imageUpload" name="avatar" accept=".png, .jpg, .jpeg" hidden>
                                            <label for="imageUpload" class="w-32-px h-32-px d-flex justify-content-center align-items-center bg-primary-50 text-primary-600 border border-primary-600 bg-hover-primary-100 text-lg rounded-circle">
                                                <iconify-icon icon="solar:camera-outline" class="icon"></iconify-icon>
                                            </label>
                                        </div>
                                        <div class="avatar-preview">
                                            <div id="imagePreview" style="background-image: url('<?php echo $avatar_path; ?>');"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="mb-20">
                                            <label for="username" class="form-label fw-semibold text-primary-light text-sm mb-8">用户名 <span class="text-danger-600">*</span></label>
                                            <input type="text" class="form-control radius-8" id="username" name="username" 
                                                   value="<?php echo htmlspecialchars($user['username']); ?>" placeholder="输入用户名" required>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="mb-20">
                                            <label for="phone" class="form-label fw-semibold text-primary-light text-sm mb-8">手机号 <span class="text-danger-600">*</span></label>
                                            <input type="tel" class="form-control radius-8" id="phone" name="phone" 
                                                   value="<?php echo htmlspecialchars($user['phone']); ?>" placeholder="输入手机号" required>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="mb-20">
                                            <label for="real_name" class="form-label fw-semibold text-primary-light text-sm mb-8">真实姓名</label>
                                            <input type="text" class="form-control radius-8" id="real_name" name="real_name" 
                                                   value="<?php echo htmlspecialchars($user['real_name'] ?? ''); ?>" placeholder="输入真实姓名">
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="mb-20">
                                            <label for="id_card" class="form-label fw-semibold text-primary-light text-sm mb-8">身份证号</label>
                                            <input type="text" class="form-control radius-8" id="id_card" name="id_card" 
                                                   value="<?php echo htmlspecialchars($user['id_card'] ?? ''); ?>" placeholder="输入身份证号">
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="mb-20">
                                            <label for="alipay_account" class="form-label fw-semibold text-primary-light text-sm mb-8">支付宝账号</label>
                                            <input type="text" class="form-control radius-8" id="alipay_account" name="alipay_account" 
                                                   value="<?php echo htmlspecialchars($user['alipay_account'] ?? ''); ?>" placeholder="输入支付宝账号">
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="mb-20">
                                            <label for="balance" class="form-label fw-semibold text-primary-light text-sm mb-8">账户余额</label>
                                            <input type="number" step="0.01" class="form-control radius-8" id="balance" name="balance" 
                                                   value="<?php echo htmlspecialchars($user['balance']); ?>" placeholder="输入账户余额">
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="mb-20">
                                            <label for="deposit" class="form-label fw-semibold text-primary-light text-sm mb-8">保证金</label>
                                            <input type="number" step="0.01" class="form-control radius-8" id="deposit" name="deposit" 
                                                   value="<?php echo htmlspecialchars($user['deposit']); ?>" placeholder="输入保证金">
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="mb-20">
                                            <label for="status" class="form-label fw-semibold text-primary-light text-sm mb-8">状态 <span class="text-danger-600">*</span></label>
                                            <select class="form-control radius-8 form-select" id="status" name="status" required>
                                                <option value="正常" <?php echo $user['status'] === '正常' ? 'selected' : ''; ?>>正常</option>
                                                <option value="封禁" <?php echo $user['status'] === '封禁' ? 'selected' : ''; ?>>封禁</option>
                                                <option value="异常" <?php echo $user['status'] === '异常' ? 'selected' : ''; ?>>异常</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="mb-20">
                                            <label for="member_level" class="form-label fw-semibold text-primary-light text-sm mb-8">会员等级 <span class="text-danger-600">*</span></label>
                                            <select class="form-control radius-8 form-select" id="member_level" name="member_level" required>
                                                <option value="普通会员" <?php echo $user['member_level'] === '普通会员' ? 'selected' : ''; ?>>普通会员</option>
                                                <option value="普通打手" <?php echo $user['member_level'] === '普通打手' ? 'selected' : ''; ?>>普通打手</option>
                                                <option value="大神打手" <?php echo $user['member_level'] === '大神打手' ? 'selected' : ''; ?>>大神打手</option>
                                                <option value="至尊打手" <?php echo $user['member_level'] === '至尊打手' ? 'selected' : ''; ?>>至尊打手</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center justify-content-center gap-3">
                                    <a href="user_list.php" class="border border-danger-600 bg-hover-danger-200 text-danger-600 text-md px-56 py-11 radius-8 text-decoration-none d-flex align-items-center justify-content-center"> 
                                        取消
                                    </a>
                                    <button type="submit" class="btn btn-primary border border-primary-600 text-md px-56 py-12 radius-8"> 
                                        保存更改
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- 修改密码选项卡 -->
                        <div class="tab-pane fade" id="pills-change-password" role="tabpanel" aria-labelledby="pills-change-password-tab" tabindex="0">
                            <?php if (isset($password_error)): ?>
                                <div class="alert alert-danger mb-20"><?php echo $password_error; ?></div>
                            <?php endif; ?>
                            
                            <?php if (isset($password_success)): ?>
                                <div class="alert alert-success mb-20"><?php echo $password_success; ?></div>
                            <?php endif; ?>
                            
                            <form action="user_detail.php?id=<?php echo $user_id; ?>" method="post">
                                <input type="hidden" name="change_password" value="1">
                                <div class="mb-20">
                                    <label for="new_password" class="form-label fw-semibold text-primary-light text-sm mb-8">新密码 <span class="text-danger-600">*</span></label>
                                    <div class="position-relative">
                                        <input type="password" class="form-control radius-8" id="new_password" name="new_password" placeholder="输入新密码" required>
                                        <span class="toggle-password ri-eye-line cursor-pointer position-absolute end-0 top-50 translate-middle-y me-16 text-secondary-light" data-toggle="#new_password"></span>
                                    </div>
                                </div>
                                <div class="mb-20">
                                    <label for="confirm_password" class="form-label fw-semibold text-primary-light text-sm mb-8">确认密码 <span class="text-danger-600">*</span></label>
                                    <div class="position-relative">
                                        <input type="password" class="form-control radius-8" id="confirm_password" name="confirm_password" placeholder="再次输入新密码" required>
                                        <span class="toggle-password ri-eye-line cursor-pointer position-absolute end-0 top-50 translate-middle-y me-16 text-secondary-light" data-toggle="#confirm_password"></span>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center justify-content-center gap-3">
                                    <button type="reset" class="border border-danger-600 bg-hover-danger-200 text-danger-600 text-md px-56 py-11 radius-8"> 
                                        重置
                                    </button>
                                    <button type="submit" class="btn btn-primary border border-primary-600 text-md px-56 py-12 radius-8"> 
                                        修改密码
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- 通知设置选项卡 -->
                        <div class="tab-pane fade" id="pills-notification" role="tabpanel" aria-labelledby="pills-notification-tab" tabindex="0">
                            <?php if (isset($notification_error)): ?>
                                <div class="alert alert-danger mb-20"><?php echo $notification_error; ?></div>
                            <?php endif; ?>
                            
                            <?php if (isset($notification_success)): ?>
                                <div class="alert alert-success mb-20"><?php echo $notification_success; ?></div>
                            <?php endif; ?>
                            
                            <form action="user_detail.php?id=<?php echo $user_id; ?>" method="post">
                                <input type="hidden" name="update_notifications" value="1">
                                <div class="form-switch switch-primary py-12 px-16 border radius-8 position-relative mb-16">
                                    <label for="company_news" class="position-absolute w-100 h-100 start-0 top-0"></label>
                                    <div class="d-flex align-items-center gap-3 justify-content-between">
                                        <span class="form-check-label line-height-1 fw-medium text-secondary-light">公司新闻</span>
                                        <input class="form-check-input" type="checkbox" role="switch" id="company_news" name="company_news" 
                                            <?php echo (!empty($user['company_news']) && $user['company_news']) ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                                <div class="form-switch switch-primary py-12 px-16 border radius-8 position-relative mb-16">
                                    <label for="push_notifications" class="position-absolute w-100 h-100 start-0 top-0"></label>
                                    <div class="d-flex align-items-center gap-3 justify-content-between">
                                        <span class="form-check-label line-height-1 fw-medium text-secondary-light">推送通知</span>
                                        <input class="form-check-input" type="checkbox" role="switch" id="push_notifications" name="push_notifications" 
                                            <?php echo (!empty($user['push_notifications']) && $user['push_notifications']) ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                                <div class="form-switch switch-primary py-12 px-16 border radius-8 position-relative mb-16">
                                    <label for="weekly_newsletters" class="position-absolute w-100 h-100 start-0 top-0"></label>
                                    <div class="d-flex align-items-center gap-3 justify-content-between">
                                        <span class="form-check-label line-height-1 fw-medium text-secondary-light">每周新闻简报</span>
                                        <input class="form-check-input" type="checkbox" role="switch" id="weekly_newsletters" name="weekly_newsletters" 
                                            <?php echo (!empty($user['weekly_newsletters']) && $user['weekly_newsletters']) ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                                <div class="form-switch switch-primary py-12 px-16 border radius-8 position-relative mb-16">
                                    <label for="meetups_near_you" class="position-absolute w-100 h-100 start-0 top-0"></label>
                                    <div class="d-flex align-items-center gap-3 justify-content-between">
                                        <span class="form-check-label line-height-1 fw-medium text-secondary-light">附近活动</span>
                                        <input class="form-check-input" type="checkbox" role="switch" id="meetups_near_you" name="meetups_near_you" 
                                            <?php echo (!empty($user['meetups_near_you']) && $user['meetups_near_you']) ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                                <div class="form-switch switch-primary py-12 px-16 border radius-8 position-relative mb-16">
                                    <label for="order_notifications" class="position-absolute w-100 h-100 start-0 top-0"></label>
                                    <div class="d-flex align-items-center gap-3 justify-content-between">
                                        <span class="form-check-label line-height-1 fw-medium text-secondary-light">订单通知</span>
                                        <input class="form-check-input" type="checkbox" role="switch" id="order_notifications" name="order_notifications" 
                                            <?php echo (!empty($user['order_notifications']) && $user['order_notifications']) ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center justify-content-center gap-3">
                                    <button type="reset" class="border border-danger-600 bg-hover-danger-200 text-danger-600 text-md px-56 py-11 radius-8"> 
                                        重置
                                    </button>
                                    <button type="submit" class="btn btn-primary border border-primary-600 text-md px-56 py-12 radius-8"> 
                                        保存设置
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 头像上传预览功能
function readURL(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        
        reader.onload = function(e) {
            document.getElementById('imagePreview').style.backgroundImage = 'url(' + e.target.result + ')';
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

document.getElementById("imageUpload").addEventListener("change", function() {
    readURL(this);
});

// 显示/隐藏密码功能
document.querySelectorAll('.toggle-password').forEach(function(element) {
    element.addEventListener('click', function() {
        const target = this.getAttribute('data-toggle');
        const input = document.querySelector(target);
        
        if (input.type === 'password') {
            input.type = 'text';
            this.classList.remove('ri-eye-line');
            this.classList.add('ri-eye-off-line');
        } else {
            input.type = 'password';
            this.classList.remove('ri-eye-off-line');
            this.classList.add('ri-eye-line');
        }
    });
});

// 表单验证
document.querySelectorAll('form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        // 可以根据不同的表单添加特定的验证逻辑
        if (this.querySelector('#new_password') && this.querySelector('#confirm_password')) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('两次输入的密码不一致');
            }
        }
    });
});
</script>

<?php
require_once 'footer.php';
?>