<?php
require_once 'header.php';

// 检查登录状态和打手身份
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'hunter') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$tags = [];
$selected_tags = [];

// 获取系统标签列表
try {
    $stmt = $pdo->query("SELECT id, name FROM hunter_tags WHERE status = 'active'");
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 获取当前打手信息
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 获取当前打手标签
    $stmt = $pdo->prepare("SELECT tag_id FROM hunter_user_tags WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $selected_tags = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'tag_id');

} catch (PDOException $e) {
    error_log('获取打手信息失败: ' . $e->getMessage());
    $error = '系统错误，请稍后再试';
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = trim($_POST['description'] ?? '');
    $new_tags = $_POST['tags'] ?? [];
    $avatar = $_FILES['avatar'] ?? null;

    // 验证数据
    if (mb_strlen($description) > 15) {
        $error = '个人简介不能超过15个字';
    } elseif (count($new_tags) > 3) {
        $error = '最多只能选择3个标签';
    } else {
        try {
            $pdo->beginTransaction();

            // 处理头像上传
            $avatar_path = $user['avatar'];
            if ($avatar && $avatar['error'] == UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/avatars/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $file_ext = pathinfo($avatar['name'], PATHINFO_EXTENSION);
                $new_filename = 'hunter_' . $user_id . '_' . time() . '.' . $file_ext;
                $dest_path = $upload_dir . $new_filename;

                if (move_uploaded_file($avatar['tmp_name'], $dest_path)) {
                    $avatar_path = $dest_path;
                    // 删除旧头像
                    if ($user['avatar'] && file_exists($user['avatar'])) {
                        unlink($user['avatar']);
                    }
                }
            }

            // 更新用户信息
            $stmt = $pdo->prepare("UPDATE users SET description = ?, avatar = ? WHERE id = ?");
            $stmt->execute([$description, $avatar_path, $user_id]);

            // 更新标签
            // 删除旧标签
            $stmt = $pdo->prepare("DELETE FROM hunter_user_tags WHERE user_id = ?");
            $stmt->execute([$user_id]);

            // 添加新标签
            if (!empty($new_tags)) {
                $tag_values = [];
                foreach ($new_tags as $tag_id) {
                    $tag_values[] = '(' . $user_id . ', ' . intval($tag_id) . ')';
                }
                $stmt = $pdo->prepare("INSERT INTO hunter_user_tags (user_id, tag_id) VALUES " . implode(',', $tag_values));
                $stmt->execute();
            }

            $pdo->commit();
            $success = '个人信息更新成功';
            // 刷新页面以显示最新信息
            header("Refresh: 2; url=hunter_profile.php");
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('更新个人信息失败: ' . $e->getMessage());
            $error = '更新失败，请稍后再试';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>个人资料设置 - 打手中心</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .tag-selector .form-check {
            display: inline-block;
            margin-right: 15px;
            margin-bottom: 10px;
        }
        .avatar-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #eee;
            margin-bottom: 15px;
        }
        .upload-btn-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        .upload-btn-wrapper input[type=file] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
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

    <div class="container py-4">
        <div class="row">
            <div class="col-md-3">
                <?php include 'sidebar.php'; ?>
            </div>
            <div class="col-md-9">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>个人资料设置</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo $success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="post" enctype="multipart/form-data">
                            <div class="row mb-4">
                                <div class="col-md-12 text-center">
                                    <img src="<?php echo $user['avatar'] ?: '../assets/images/user/default.png'; ?>" alt="头像预览" class="avatar-preview" id="avatarPreview">
                                    <div class="upload-btn-wrapper">
                                        <button class="btn btn-outline-secondary"><i class="fas fa-upload me-2"></i>更换头像</button>
                                        <input type="file" name="avatar" id="avatarInput" accept="image/*" />
                                    </div>
                                    <div class="form-text mt-2">支持JPG、PNG格式，建议尺寸200x200像素</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="username" class="form-label">用户名</label>
                                <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                <div class="form-text">用户名不可修改</div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">个人简介</label>
                                <textarea class="form-control" id="description" name="description" rows="3" maxlength="15" placeholder="请输入个人简介（最多15字）"><?php echo htmlspecialchars($user['description'] ?? ''); ?></textarea>
                                <div class="form-text"><span id="descCount"><?php echo strlen($user['description'] ?? ''); ?></span>/15 字</div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">核心标签</label>
                                <div class="tag-selector border p-3 rounded">
                                    <?php if (empty($tags)): ?>
                                        <div class="text-muted">暂无可用标签，请联系管理员</div>
                                    <?php else: ?>
                                        <?php foreach ($tags as $tag): ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="tags[]" value="<?php echo $tag['id']; ?>"
                                                    <?php echo in_array($tag['id'], $selected_tags) ? 'checked' : ''; ?>>
                                                <label class="form-check-label">
                                                    <?php echo htmlspecialchars($tag['name']); ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="form-text mt-2">最多选择3个标签，标签将展示在您的个人主页</div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="button" class="btn btn-secondary me-md-2" onclick="history.back()">取消</button>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>保存修改</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 头像预览
        document.getElementById('avatarInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatarPreview').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });

        // 简介字数统计
        document.getElementById('description').addEventListener('input', function() {
            document.getElementById('descCount').textContent = this.value.length;
        });

        // 限制标签选择数量
        const tagCheckboxes = document.querySelectorAll('input[name="tags[]"]');
        tagCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const checkedCount = document.querySelectorAll('input[name="tags[]"]:checked').length;
                if (checkedCount > 3) {
                    this.checked = false;
                    alert('最多只能选择3个标签');
                }
            });
        });
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>