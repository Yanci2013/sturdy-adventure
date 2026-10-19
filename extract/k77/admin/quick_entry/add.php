<?php
// 开启输出缓冲
ob_start();

require_once("../../includes/session_manager.php");
include("../../includes/common.php");
require_once("../../includes/log_helper.php");

// 检查管理员登录
if (!is_admin_logged_in()) {
    ob_end_clean();
    header("Location: ../login.php");
    exit();
}

$admin_id = get_admin_id();
$admin_name = $_SESSION['admin_username'] ?? '管理员';

$is_edit = isset($_GET['id']) && !empty($_GET['id']);
$entry = [
    'id' => '',
    'title' => '',
    'icon_image' => '',
    'content' => '',
    'sort_order' => 0,
    'status' => 1
];

if ($is_edit) {
    $id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM quick_entries WHERE id = ?");
        $stmt->execute([$id]);
        $db_entry = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($db_entry) {
            $entry = $db_entry;
        }
    } catch (PDOException $e) {
        error_log('Error loading entry: ' . $e->getMessage());
    }
}

// 处理POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $sort_order = intval($_POST['sort_order'] ?? 0);
    $status = isset($_POST['status']) ? 1 : 0;
    $icon_image = $entry['icon_image'] ?? '';

    // 处理图片上传
    if (isset($_FILES['icon_image']) && $_FILES['icon_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../assets/images/quick_entry/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_ext = pathinfo($_FILES['icon_image']['name'], PATHINFO_EXTENSION);
        $allowed_exts = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];

        if (in_array(strtolower($file_ext), $allowed_exts)) {
            $new_filename = 'icon_' . time() . '_' . mt_rand(1000, 9999) . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['icon_image']['tmp_name'], $upload_path)) {
                $icon_image = 'assets/images/quick_entry/' . $new_filename;
            }
        }
    }

    if (empty($title)) {
        $_SESSION['error'] = '请填写入口名称';
    } else {
        try {
            if ($is_edit) {
                $id = intval($_POST['id']);
                
                // 记录修改前的值
                $old_entry = $entry;
                
                if ($icon_image) {
                    $stmt = $pdo->prepare("UPDATE quick_entries SET title = ?, icon_image = ?, content = ?, sort_order = ?, status = ? WHERE id = ?");
                    $stmt->execute([$title, $icon_image, $content, $sort_order, $status, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE quick_entries SET title = ?, content = ?, sort_order = ?, status = ? WHERE id = ?");
                    $stmt->execute([$title, $content, $sort_order, $status, $id]);
                }
                
                // 记录编辑日志
                log_admin_operation(
                    $pdo,
                    $admin_id,
                    $admin_name,
                    'edit_quick_entry',
                    '管理员修改了快捷入口' . $title . 'ID为' . $id,
                    'quick_entry',
                    $id,
                    json_encode($old_entry),
                    json_encode(['title' => $title, 'icon_image' => $icon_image, 'content' => $content, 'sort_order' => $sort_order, 'status' => $status])
                );
                
                $_SESSION['success'] = '修改成功';
            } else {
                $stmt = $pdo->prepare("INSERT INTO quick_entries (title, icon_image, content, sort_order, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$title, $icon_image, $content, $sort_order, $status]);
                
                // 获取新插入的ID
                $new_id = $pdo->lastInsertId();
                
                // 记录添加日志
                log_admin_operation(
                    $pdo,
                    $admin_id,
                    $admin_name,
                    'add_quick_entry',
                    '管理员添加了新的快捷入口' . $title . 'ID为' . $new_id,
                    'quick_entry',
                    $new_id,
                    null,
                    json_encode(['title' => $title, 'icon_image' => $icon_image, 'content' => $content, 'sort_order' => $sort_order, 'status' => $status])
                );
                
                $_SESSION['success'] = '添加成功';
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = '操作失败: ' . $e->getMessage();
        }
    }

    ob_end_clean();
    header("Location: list.php");
    exit();
}

$pageTitle = $is_edit ? "编辑快捷入口" : "新增快捷入口";
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title><?php echo $pageTitle; ?></title>
  <meta name="renderer" content="webkit">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="../res/layui/css/layui.css?v=2.9.0" rel="stylesheet">
  <link href="../res/adminui/dist/css/admin.css?v=1.0.0" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .form-container {
      background: white;
      border-radius: 12px;
      padding: 30px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .preview-section {
      background: #f9fafb;
      border-radius: 12px;
      padding: 20px;
      margin-top: 20px;
    }
    .preview-title {
      font-weight: 600;
      margin-bottom: 15px;
      color: #374151;
    }
    .preview-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 12px;
    }
    .preview-item {
      background: white;
      border-radius: 12px;
      padding: 16px 8px;
      text-align: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .preview-item img {
      width: 44px;
      height: 44px;
      object-fit: contain;
      margin-bottom: 8px;
    }
    .preview-item span {
      font-size: 12px;
      color: #333;
    }
    .upload-box {
      border: 2px dashed #d1d5db;
      border-radius: 12px;
      padding: 30px;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s;
      background: #fafafa;
    }
    .upload-box:hover {
      border-color: #3b82f6;
      background: #f0f9ff;
    }
    .upload-box i {
      font-size: 48px;
      color: #9ca3af;
      margin-bottom: 10px;
    }
    .upload-box p {
      color: #6b7280;
      font-size: 14px;
    }
    .current-image {
      margin-top: 15px;
      text-align: center;
    }
    .current-image img {
      max-width: 100px;
      max-height: 100px;
      border-radius: 8px;
      border: 2px solid #e5e7eb;
    }
    .content-editor {
      width: 100%;
      min-height: 200px;
      padding: 15px;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      font-size: 14px;
      line-height: 1.6;
      resize: vertical;
    }
    .content-editor:focus {
      outline: none;
      border-color: #3b82f6;
    }
  </style>
</head>
<body>
  <div class="layui-fluid">
    <div class="layui-row layui-col-space15">
      <div class="layui-col-md12">
        <div class="layui-card">
          <div class="layui-card-header">
            <h2 style="font-size: 20px; font-weight: 600;">
              <i class="fas fa-<?php echo $is_edit ? 'edit' : 'plus'; ?>"></i> <?php echo $pageTitle; ?>
            </h2>
          </div>
          <div class="layui-card-body">
            <?php if (isset($_SESSION['error'])): ?>
            <div class="layui-alert layui-alert-error" style="margin-bottom: 15px;">
              <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
            <?php endif; ?>

            <div class="form-container">
              <form class="layui-form" method="POST" action="add.php" enctype="multipart/form-data">
                <?php if ($is_edit): ?>
                <input type="hidden" name="id" value="<?php echo $entry['id']; ?>">
                <?php endif; ?>

                <div class="layui-form-item">
                  <label class="layui-form-label"><span style="color: red;">*</span> 入口名称</label>
                  <div class="layui-input-block">
                    <input type="text" name="title" id="titleInput" value="<?php echo htmlspecialchars($entry['title']); ?>" required lay-verify="required" placeholder="例如：新手攻略" autocomplete="off" class="layui-input" style="max-width: 400px;">
                  </div>
                </div>

                <div class="layui-form-item">
                  <label class="layui-form-label"><span style="color: red;">*</span> 图标图片</label>
                  <div class="layui-input-block">
                    <div class="upload-box" onclick="document.getElementById('iconUpload').click()">
                      <i class="fas fa-cloud-upload-alt"></i>
                      <p>点击上传图标图片</p>
                      <p style="font-size: 12px; color: #9ca3af;">支持 PNG、JPG、SVG 等格式</p>
                    </div>
                    <input type="file" id="iconUpload" name="icon_image" accept="image/*" style="display: none;" onchange="previewUpload(this)">
                    <?php if (!empty($entry['icon_image'])): ?>
                    <div class="current-image">
                      <p style="color: #6b7280; margin-bottom: 10px;">当前图标：</p>
                      <img src="../../<?php echo htmlspecialchars($entry['icon_image']); ?>" alt="当前图标">
                    </div>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="layui-form-item">
                  <label class="layui-form-label"><span style="color: red;">*</span> 弹窗内容</label>
                  <div class="layui-input-block">
                    <textarea name="content" id="contentEditor" class="content-editor" placeholder="输入要显示的内容，支持HTML代码。例如：&lt;h3&gt;欢迎&lt;/h3&gt;&lt;p&gt;这里是内容...&lt;/p&gt;"><?php echo htmlspecialchars($entry['content'] ?? ''); ?></textarea>
                    <p style="color: #9ca3af; margin-top: 8px; font-size: 12px;">
                      <i class="fas fa-info-circle"></i> 支持HTML代码，可以插入图片、链接、样式等
                    </p>
                  </div>
                </div>

                <div class="layui-form-item">
                  <label class="layui-form-label">排序</label>
                  <div class="layui-input-block">
                    <input type="number" name="sort_order" value="<?php echo $entry['sort_order']; ?>" placeholder="数字越小越靠前" autocomplete="off" class="layui-input" style="max-width: 200px;">
                  </div>
                </div>

                <div class="layui-form-item">
                  <label class="layui-form-label">状态</label>
                  <div class="layui-input-block">
                    <input type="checkbox" name="status" value="1" lay-skin="switch" lay-text="启用|禁用" <?php echo $entry['status'] == 1 ? 'checked' : ''; ?>>
                  </div>
                </div>

                <div class="layui-form-item">
                  <div class="layui-input-block">
                    <button type="submit" class="layui-btn layui-btn-normal">保存</button>
                    <a href="list.php" class="layui-btn layui-btn-primary">返回列表</a>
                  </div>
                </div>
              </form>

              <div class="preview-section">
                <div class="preview-title"><i class="fas fa-eye"></i> 预览效果</div>
                <div class="preview-grid">
                  <div class="preview-item" id="previewItem">
                    <img id="previewImg" src="<?php echo !empty($entry['icon_image']) ? '../../' . htmlspecialchars($entry['icon_image']) : ''; ?>" alt="图标" style="<?php echo empty($entry['icon_image']) ? 'display: none;' : ''; ?>">
                    <i id="previewPlaceholder" class="fas fa-image" style="font-size: 44px; color: #d1d5db; margin-bottom: 8px; <?php echo !empty($entry['icon_image']) ? 'display: none;' : ''; ?>"></i>
                    <span id="previewTitle"><?php echo htmlspecialchars($entry['title']) ?: '入口名称'; ?></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="../res/layui/layui.js"></script>
  <script>
    layui.use(['form'], function(){
      var form = layui.form;
      form.render();
    });

    function previewUpload(input) {
      if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
          document.getElementById('previewImg').src = e.target.result;
          document.getElementById('previewImg').style.display = 'block';
          document.getElementById('previewPlaceholder').style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
      }
    }

    document.getElementById('titleInput').addEventListener('input', function() {
      document.getElementById('previewTitle').textContent = this.value || '入口名称';
    });
  </script>
</body>
</html>
<?php
ob_end_flush();
?>
