<?php
// 开启输出缓冲
ob_start();

// 启动会话，确保能访问session变量
// 由于是iframe页面，需要启动自己的会话
if (!session_id()) {
    session_start();
}

// 引入数据库连接文件
require_once __DIR__.'/../../includes/common.php';

// 从session获取管理员信息
// 检查可能使用的不同session键名
$admin_id = $_SESSION['admin_id'] ?? $_SESSION['eadmin_id'] ?? null;
$admin_type = $_SESSION['admin_type'] ?? ($_SESSION['eadmin_id'] ? 'eadmin' : 'admin'); // 根据session键名确定类型

// 确保admin_id有默认值，避免插入null
if (empty($admin_id)) {
    $admin_id = 1; // 默认管理员ID
    $admin_type = 'admin'; // 默认管理员类型
}

// 使用主框架生成的CSRF令牌，不重新生成
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 处理生成邀请码请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_invite'])) {
    handleGenerateInvite();
}

// 处理删除邀请码请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_invite'])) {
    handleDeleteInvite();
}

// 处理修改绑定用户请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    handleUpdateUser();
}

// 获取邀请码列表
$invites = getInvitations();

// 统计邀请码状态
[$active_count, $used_count, $expired_count] = countInvitationStatus($invites);

/**
 * 处理生成邀请码请求
 */
function handleGenerateInvite() {
    global $pdo, $admin_id, $admin_type;
    
    // CSRF验证
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "CSRF令牌验证失败";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    $quantity = intval($_POST['quantity']);
    $register_type = intval($_POST['register_type']);
    $gift_member_level = isset($_POST['gift_member_level']) ? intval($_POST['gift_member_level']) : null;
    $gift_member_duration = isset($_POST['gift_member_duration']) && in_array($_POST['gift_member_duration'], ['month', 'quarter', 'year']) 
                          ? $_POST['gift_member_duration'] 
                          : null;
    $gift_points = isset($_POST['gift_points']) ? intval($_POST['gift_points']) : null;
    
    // 验证输入
    if ($quantity < 1 || $quantity > 100) {
        $_SESSION['error'] = "生成数量必须在1-100之间";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if ($register_type < 1 || $register_type > 3) {
        $_SESSION['error'] = "无效的注册类型";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    $success_count = 0;
    $generated_codes = [];
    
    // 添加调试日志
    error_log("开始生成邀请码: 数量={$quantity}, 注册类型={$register_type}, 管理员ID={$admin_id}, 管理员类型={$admin_type}");
    
    // 开始事务
    $pdo->beginTransaction();
    
    try {
        for ($i = 0; $i < $quantity; $i++) {
            $code = generateRandomString(16);
            
            $stmt = $pdo->prepare("INSERT INTO invitations 
                                  (code, generator_type, generator_id, register_type, status, 
                                  gift_member_level, gift_member_duration, gift_points, created_at)
                                  VALUES (:code, :admin_type, :admin_id, :register_type, 'active', 
                                  :gift_member_level, :gift_member_duration, :gift_points, NOW())");
            
            // 执行插入并获取受影响的行数
            $stmt->execute([
                ':code' => $code,
                ':admin_type' => $admin_type,
                ':admin_id' => $admin_id,
                ':register_type' => $register_type,
                ':gift_member_level' => $gift_member_level,
                ':gift_member_duration' => $gift_member_duration,
                ':gift_points' => $gift_points
            ]);
            
            $affectedRows = $stmt->rowCount();
            error_log("插入邀请码 {$code}: 受影响行数={$affectedRows}, SQLSTATE={$stmt->errorCode()}");
            
            if ($affectedRows > 0) {
                $success_count++;
                $generated_codes[] = $code;
            } else {
                error_log("插入邀请码 {$code} 失败，无受影响行数");
            }
        }
        
        $pdo->commit();
        $_SESSION['success'] = "成功生成 {$success_count} 个邀请码";
        $_SESSION['generated_codes'] = $generated_codes;
        error_log("邀请码生成成功，共生成 {$success_count} 个");
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "生成邀请码时出错: " . $e->getMessage();
        error_log("生成邀请码异常: " . $e->getMessage() . " 行号: " . $e->getLine() . " 文件: " . $e->getFile());
        error_log("SQL语句: INSERT INTO invitations (code, generator_type, generator_id, register_type, status, gift_member_level, gift_member_duration, gift_points, created_at) VALUES (?, ?, ?, ?, 'active', ?, ?, ?, NOW())");
        error_log("参数: 管理员ID={$admin_id}, 管理员类型={$admin_type}, 注册类型={$register_type}, 赠送会员类型={$gift_member_level}, 赠送时长={$gift_member_duration}, 赠送积分={$gift_points}");
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

/**
 * 处理删除邀请码请求
 */
function handleDeleteInvite() {
    global $pdo, $admin_id, $admin_type;
    
    // CSRF验证
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "CSRF令牌验证失败";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    $invite_id = intval($_POST['invite_id']);
    
    try {
        // 检查邀请码是否属于当前管理员
        $stmt = $pdo->prepare("SELECT id FROM invitations 
                             WHERE id = :id AND generator_type = :admin_type AND generator_id = :admin_id");
        $stmt->execute([
            ':id' => $invite_id,
            ':admin_type' => $admin_type,
            ':admin_id' => $admin_id
        ]);
        
        if ($stmt->rowCount() === 0) {
            $_SESSION['error'] = "无权删除此邀请码或邀请码不存在";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
        
        // 删除邀请码
        $stmt = $pdo->prepare("DELETE FROM invitations WHERE id = :id");
        $stmt->execute([':id' => $invite_id]);
        
        $_SESSION['success'] = "邀请码删除成功";
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "删除邀请码时出错: " . $e->getMessage();
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

/**
 * 处理修改绑定用户请求
 */
function handleUpdateUser() {
    global $pdo, $admin_id, $admin_type;
    
    // CSRF验证
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "CSRF令牌验证失败";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    $invite_id = intval($_POST['invite_id']);
    $user_id = !empty($_POST['user_id']) ? intval($_POST['user_id']) : null;
    
    try {
        // 检查邀请码是否属于当前管理员
        $stmt = $pdo->prepare("SELECT id FROM invitations 
                             WHERE id = :id AND generator_type = :admin_type AND generator_id = :admin_id");
        $stmt->execute([
            ':id' => $invite_id,
            ':admin_type' => $admin_type,
            ':admin_id' => $admin_id
        ]);
        
        if ($stmt->rowCount() === 0) {
            $_SESSION['error'] = "无权修改此邀请码或邀请码不存在";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
        
        // 检查用户是否存在
        if ($user_id) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = :id");
            $stmt->execute([':id' => $user_id]);
            
            if ($stmt->rowCount() === 0) {
                $_SESSION['error'] = "用户ID不存在";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
        }
        
        // 更新邀请码绑定用户
        $stmt = $pdo->prepare("UPDATE invitations 
                              SET user_id = :user_id, 
                                  status = IF(:user_id IS NULL, 'active', 'used'), 
                                  used_at = IF(:user_id IS NULL, NULL, NOW()) 
                              WHERE id = :id");
        $stmt->execute([
            ':user_id' => $user_id,
            ':id' => $invite_id
        ]);
        
        $_SESSION['success'] = "邀请码绑定用户更新成功";
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "更新邀请码绑定时出错: " . $e->getMessage();
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

/**
 * 获取邀请码列表
 */
function getInvitations() {
    global $pdo, $admin_id, $admin_type;
    
    // 添加调试日志
    error_log("获取邀请码列表: 管理员ID={$admin_id}, 管理员类型={$admin_type}");
    
    try {
        // 尝试获取所有邀请码，暂时不限制管理员类型和ID，确保能看到生成的邀请码
        $stmt = $pdo->prepare("SELECT i.*, u.username as used_by 
                              FROM invitations i 
                              LEFT JOIN users u ON i.user_id = u.id 
                              ORDER BY created_at DESC LIMIT 100");
        $stmt->execute();
        
        $invites = $stmt->fetchAll();
        error_log("获取到邀请码数量: " . count($invites));
        
        return $invites;
    } catch (PDOException $e) {
        error_log("获取邀请码列表错误: " . $e->getMessage() . " SQLSTATE: " . $e->getCode());
        return [];
    }
}

/**
 * 统计邀请码状态
 */
function countInvitationStatus($invites) {
    $active_count = 0;
    $used_count = 0;
    $expired_count = 0;
    
    foreach ($invites as $invite) {
        if ($invite['status'] == 'active') $active_count++;
        if ($invite['status'] == 'used') $used_count++;
        if ($invite['status'] == 'expired') $expired_count++;
    }
    
    return [$active_count, $used_count, $expired_count];
}

/**
 * 生成随机字符串
 */
function generateRandomString($length = 16) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    
    return $randomString;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>密钥管理 - 管理面板</title>
  <link href="../res/layui/css/layui.css" rel="stylesheet">
  <link href="../res/adminui/dist/css/admin.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #ffffff; color: #333; line-height: 1.5; padding: 20px; font-size: 13px; }
    .container { max-width: 1200px; margin: 0 auto; }
    h1 { font-size: 18px; font-weight: 600; margin-bottom: 16px; color: #000; }
    h2 { font-size: 14px; font-weight: 600; margin: 20px 0 12px 0; color: #333; }
    
    .overview-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
    @media (max-width: 992px) { .overview-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 768px) { .overview-grid { grid-template-columns: repeat(2, 1fr); } }
    
    .overview-card { background: #fafafa; border-radius: 8px; padding: 16px; display: flex; align-items: flex-start; gap: 14px; }
    .overview-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
    .overview-icon.blue { background: #e3f2fd; color: #1976d2; }
    .overview-icon.green { background: #e8f5e9; color: #388e3c; }
    .overview-icon.orange { background: #fff3e0; color: #f57c00; }
    .overview-icon.red { background: #ffebee; color: #d32f2f; }
    .overview-icon.purple { background: #f3e5f5; color: #7b1fa2; }
    .overview-icon.cyan { background: #e0f7fa; color: #00838f; }
    .overview-icon.gray { background: #f5f5f5; color: #757575; }
    .overview-content { flex: 1; min-width: 0; }
    .overview-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
    .overview-title { font-size: 12px; color: #666; }
    .overview-tag { font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 500; }
    .overview-tag.realtime { background: #e8f5e9; color: #388e3c; }
    .overview-tag.today { background: #e3f2fd; color: #1976d2; }
    .overview-tag.yesterday { background: #fff3e0; color: #f57c00; }
    .overview-tag.pending { background: #ffebee; color: #f44336; }
    .overview-tag.total { background: #f5f5f5; color: #666; }
    .overview-tag.active { background: #e8f5e9; color: #388e3c; }
    .overview-tag.used { background: #e3f2fd; color: #1976d2; }
    .overview-tag.expired { background: #f5f5f5; color: #666; }
    .overview-value { font-size: 20px; font-weight: 700; color: #000; margin-bottom: 2px; }
    .overview-sub { font-size: 11px; color: #999; }
    .overview-sub span { color: #666; font-weight: 500; }
    
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 16px; }
    .form-group { margin-bottom: 0; }
    .form-label { display: block; font-size: 12px; color: #666; margin-bottom: 6px; }
    .form-input, .form-select { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; }
    .form-input:focus, .form-select:focus { outline: none; border-color: #1976d2; box-shadow: 0 0 0 2px rgba(25, 118, 210, 0.2); }
    
    .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border: 1px solid #ddd; background: #fff; color: #333; font-size: 13px; cursor: pointer; text-decoration: none; transition: all 0.2s; border-radius: 4px; }
    .btn:hover { background: #f5f5f5; }
    .btn-primary { background: #1976d2; color: #fff; border-color: #1976d2; }
    .btn-primary:hover { background: #1565c0; }
    .btn-danger { background: #d32f2f; color: #fff; border-color: #d32f2f; }
    .btn-danger:hover { background: #c62828; }
    .btn-group { display: flex; gap: 8px; flex-wrap: wrap; }
    .w-100 { width: 100%; }
    
    table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 10px; }
    th { background: #f8f8f8; color: #666; font-weight: 500; padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; }
    td { padding: 10px 12px; border-bottom: 1px solid #f5f5f5; color: #333; }
    tr:hover td { background: #fafafa; }
    .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 10px; }
    .badge-pending { background: #fff3e0; color: #f57c00; }
    .badge-processing { background: #e3f2fd; color: #1976d2; }
    .badge-completed { background: #e8f5e9; color: #388e3c; }
    .badge-closed { background: #f5f5f5; color: #666; }
    .badge-success { background: #e8f5e9; color: #388e3c; }
    .badge-warning { background: #fff3e0; color: #f57c00; }
    .badge-secondary { background: #f5f5f5; color: #666; }
    .badge-primary { background: #e3f2fd; color: #1976d2; }
    .money { color: #d32f2f; }
    
    .alert { padding: 14px 16px; margin-bottom: 16px; border-radius: 8px; font-size: 13px; display: flex; align-items: center; gap: 8px; }
    .alert-success { background: #e8f5e9; color: #388e3c; border: 1px solid #c8e6c9; }
    .alert-danger { background: #ffebee; color: #d32f2f; border: 1px solid #ffcdd2; }
    .alert-info { background: #e3f2fd; color: #1976d2; border: 1px solid #bbdefb; }
    
    .code-item { background: #fff; border: 1px solid #eee; padding: 12px; margin-bottom: 8px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; }
    .code-item:hover { background: #fafafa; }
    .code-value { font-family: monospace; font-size: 14px; color: #1976d2; font-weight: 600; }
    .code-time { font-size: 11px; color: #999; margin-top: 4px; }
    
    .empty { color: #999; padding: 20px 0; text-align: center; }
    
    .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
    .modal.show { display: flex; }
    .modal-content { background: #fff; border-radius: 8px; padding: 20px; width: 90%; max-width: 400px; }
    .modal-header { font-size: 16px; font-weight: 600; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; }
    .modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: #999; }
    .modal-footer { margin-top: 16px; display: flex; justify-content: flex-end; gap: 8px; }
    
    .copy-success { position: fixed; bottom: 20px; right: 20px; background: #388e3c; color: #fff; padding: 12px 20px; border-radius: 6px; display: none; z-index: 1001; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    
    .generated-codes { background: #fff; border: 1px solid #eee; border-radius: 8px; padding: 16px; margin-top: 16px; }
    .generated-codes h4 { font-size: 14px; font-weight: 600; margin-bottom: 12px; color: #333; }
    
    .tables-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 16px; }
    @media (max-width: 768px) { .tables-grid { grid-template-columns: 1fr; } }
    
    @media (max-width: 768px) {
      .form-grid { grid-template-columns: 1fr; }
    }
  </style>
  <script src="../res/layui/layui.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <div class="container">
        <h1>密钥管理</h1>

        <!-- 消息提示 -->
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($_SESSION['error']); ?>
            <button type="button" class="btn-close" onclick="this.parentElement.remove()">×</button>
        </div>
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($_SESSION['success']); ?>
            <button type="button" class="btn-close" onclick="this.parentElement.remove()">×</button>
        </div>
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <!-- 统计信息 -->
        <div class="overview-grid">
            <div class="overview-card">
                <div class="overview-icon blue"><i class="fas fa-key"></i></div>
                <div class="overview-content">
                    <div class="overview-header">
                        <span class="overview-title">可用邀请码</span>
                        <span class="overview-tag active">实时</span>
                    </div>
                    <div class="overview-value"><?php echo $active_count; ?></div>
                    <div class="overview-sub"><span><?php echo count($invites); ?></span> 个已生成</div>
                </div>
            </div>

            <div class="overview-card">
                <div class="overview-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="overview-content">
                    <div class="overview-header">
                        <span class="overview-title">已使用</span>
                        <span class="overview-tag used">统计</span>
                    </div>
                    <div class="overview-value"><?php echo $used_count; ?></div>
                    <div class="overview-sub">使用率 <span><?php echo count($invites) > 0 ? round(($used_count / count($invites)) * 100, 1) : 0; ?>%</span></div>
                </div>
            </div>

            <div class="overview-card">
                <div class="overview-icon gray"><i class="fas fa-clock"></i></div>
                <div class="overview-content">
                    <div class="overview-header">
                        <span class="overview-title">已过期</span>
                        <span class="overview-tag expired">统计</span>
                    </div>
                    <div class="overview-value"><?php echo $expired_count; ?></div>
                    <div class="overview-sub">过期率 <span><?php echo count($invites) > 0 ? round(($expired_count / count($invites)) * 100, 1) : 0; ?>%</span></div>
                </div>
            </div>

            <div class="overview-card">
                <div class="overview-icon orange"><i class="fas fa-bar-chart"></i></div>
                <div class="overview-content">
                    <div class="overview-header">
                        <span class="overview-title">总邀请码</span>
                        <span class="overview-tag total">累计</span>
                    </div>
                    <div class="overview-value"><?php echo count($invites); ?></div>
                    <div class="overview-sub">今日生成 <span>0</span> 个</div>
                </div>
            </div>
        </div>

        <!-- 生成邀请码表单 -->
        <h2>生成邀请码</h2>
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">生成数量</label>
                    <input type="number" name="quantity" class="form-input" min="1" max="100" value="1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">注册类型</label>
                    <select name="register_type" class="form-select" required>
                        <option value="2">商家注册</option>
                        <option value="3">管事注册</option>
                    </select>
                </div>
            </div>
            <div class="btn-group" style="margin-top: 16px;">
                <button type="submit" name="generate_invite" class="btn btn-primary"><i class="fas fa-plus-circle"></i> 生成邀请码</button>
            </div>
        </form>

        <!-- 生成的邀请码 -->
        <?php if (isset($_SESSION['generated_codes']) && !empty($_SESSION['generated_codes'])): ?>
        <div class="generated-codes">
            <h4>生成的邀请码</h4>
            <?php foreach ($_SESSION['generated_codes'] as $code): ?>
            <div class="code-item">
                <div>
                    <div class="code-value" onclick="copyToClipboard('<?php echo $code; ?>')" title="点击复制"><?php echo $code; ?></div>
                    <div class="code-time"><?php echo date('Y-m-d H:i:s'); ?></div>
                </div>
                <button class="btn btn-sm" onclick="copyToClipboard('<?php echo $code; ?>')" title="复制"><i class="fas fa-copy"></i></button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php unset($_SESSION['generated_codes']); ?>
        <?php endif; ?>

        <!-- 邀请码列表 -->
        <h2>邀请码列表</h2>
        <table>
            <thead>
                <tr>
                    <th>邀请码</th>
                    <th>状态</th>
                    <th>生成者</th>
                    <th>生成时间</th>
                    <th>注册类型</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($invites) > 0): ?>
                    <?php foreach ($invites as $invite): ?>
                    <tr>
                        <td>
                            <span class="code-value" onclick="copyToClipboard('<?php echo $invite['code']; ?>')" title="点击复制"><?php echo $invite['code']; ?></span>
                        </td>
                        <td>
                            <span class="badge <?php echo $invite['status'] == 'active' ? 'badge-success' : ($invite['status'] == 'used' ? 'badge-primary' : 'badge-secondary'); ?>">
                                <?php echo $invite['status'] == 'active' ? '可用' : ($invite['status'] == 'used' ? '已使用' : '已过期'); ?>
                            </span>
                        </td>
                        <td><?php echo $invite['generator_type'] == 'admin' ? '系统管理员' : '管事'; ?></td>
                        <td><?php echo $invite['created_at']; ?></td>
                        <td>
                            <?php echo $invite['register_type'] == 1 ? '打手注册' : ($invite['register_type'] == 2 ? '商家注册' : '管事注册'); ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <button class="btn btn-sm" onclick="copyToClipboard('<?php echo $invite['code']; ?>')" title="复制"><i class="fas fa-copy"></i></button>
                                <?php if ($invite['status'] == 'active' || $invite['status'] == 'used'): ?>
                                <button class="btn btn-sm btn-danger" onclick="deleteInvite(<?php echo $invite['id']; ?>)" title="删除"><i class="fas fa-trash"></i></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="empty">暂无邀请码</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 删除确认模态框 -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <span>确认删除</span>
                <button class="modal-close" onclick="document.getElementById('deleteModal').classList.remove('show')">×</button>
            </div>
            <div class="modal-body">
                <p>确定要删除这个邀请码吗？</p>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="document.getElementById('deleteModal').classList.remove('show')">取消</button>
                <button class="btn btn-danger" id="confirmDelete">删除</button>
            </div>
        </div>
    </div>

    <div class="copy-success" id="copySuccess">
        复制成功！
    </div>

  <script>
  layui.config({ base: '../res/' }).use(['index'], function(){
    var $ = layui.$;

    let currentInviteId = null;

    function copyToClipboard(text) {
      navigator.clipboard.writeText(text).then(function() {
        const success = document.getElementById('copySuccess');
        success.style.display = 'block';
        setTimeout(function() {
          success.style.display = 'none';
        }, 2000);
      }, function(err) {
        console.error('复制失败:', err);
      });
    }

    function deleteInvite(id) {
      currentInviteId = id;
      document.getElementById('deleteModal').classList.add('show');
    }

    document.getElementById('confirmDelete').addEventListener('click', function() {
      if (currentInviteId) {
        const form = document.createElement('form');
        form.method = 'post';
        form.action = '';
        form.innerHTML = `
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="delete_invite" value="1">
          <input type="hidden" name="invite_id" value="${currentInviteId}">
        `;
        document.body.appendChild(form);
        form.submit();
      }
    });
  });
  </script>
</body>
</html>