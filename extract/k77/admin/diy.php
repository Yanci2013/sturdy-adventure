<?php
ob_start();
require_once("../includes/session_manager.php");
include("../includes/common.php");
if (!is_admin_logged_in()) {
    ob_end_clean();
    header("Location: login.php");
    exit();
}

// 创建 diy_settings 表（如果不存在）
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS diy_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        config_type VARCHAR(50) NOT NULL DEFAULT 'general',
        config_key VARCHAR(100) NOT NULL,
        config_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_type_key (config_type, config_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    error_log("创建diy_settings表失败: " . $e->getMessage());
}

// 创建上传目录
$upload_dir = dirname(__DIR__) . '/uploads/diy';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// 处理表单提交
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_config') {
        // 保存文本配置
        $configs = $_POST['config'] ?? [];
        foreach ($configs as $type => $items) {
            foreach ($items as $key => $value) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO diy_settings (config_type, config_key, config_value) VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");
                    $stmt->execute([$type, $key, $value]);
                } catch (PDOException $e) {
                    error_log("保存配置失败: " . $e->getMessage());
                }
            }
        }
        $message = '配置保存成功！';
        $message_type = 'success';
    }

    if ($action === 'upload' && !empty($_FILES['upload_file'])) {
        $file = $_FILES['upload_file'];
        $upload_type = $_POST['upload_type'] ?? 'image';
        $config_key = $_POST['config_key'] ?? '';
        $config_type = $_POST['config_type'] ?? 'general';

        if ($file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'], 'audio' => ['mp3', 'wav', 'ogg', 'm4a']];
            $max_size = ['image' => 5 * 1024 * 1024, 'audio' => 10 * 1024 * 1024];

            if (in_array($ext, $allowed[$upload_type]) && $file['size'] <= $max_size[$upload_type]) {
                $filename = uniqid('diy_') . '.' . $ext;
                $filepath = $upload_dir . '/' . $filename;
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $relative_path = 'uploads/diy/' . $filename;
                    try {
                        $stmt = $pdo->prepare("INSERT INTO diy_settings (config_type, config_key, config_value) VALUES (?, ?, ?)
                            ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");
                        $stmt->execute([$config_type, $config_key, $relative_path]);
                    } catch (PDOException $e) {}
                    $message = '上传成功！';
                    $message_type = 'success';
                } else {
                    $message = '文件保存失败';
                    $message_type = 'error';
                }
            } else {
                $message = '不支持的文件格式或文件过大';
                $message_type = 'error';
            }
        }
    }

    if ($action === 'delete') {
        $config_type = $_POST['config_type'] ?? '';
        $config_key = $_POST['config_key'] ?? '';
        if ($config_type && $config_key) {
            try {
                $stmt = $pdo->prepare("DELETE FROM diy_settings WHERE config_type = ? AND config_key = ?");
                $stmt->execute([$config_type, $config_key]);
                $message = '已删除';
                $message_type = 'success';
            } catch (PDOException $e) {}
        }
    }
}

// 读取所有配置
$diy_configs = [];
try {
    $stmt = $pdo->query("SELECT * FROM diy_settings");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $diy_configs[$row['config_type']][$row['config_key']] = $row['config_value'];
    }
} catch (PDOException $e) {}

function get_config($type, $key, $default = '') {
    global $diy_configs;
    return $diy_configs[$type][$key] ?? $default;
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>DIY 自定义设置 - 管理后台</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="./res/layui/css/layui.css?v=2.9.0" rel="stylesheet">
  <style>
    body { background: #f5f6f8; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    .diy-container { max-width: 1100px; margin: 0 auto; }
    .diy-header {
      background: linear-gradient(135deg, #1a73e8, #4285f4);
      color: #fff; padding: 24px 28px; border-radius: 16px;
      margin-bottom: 20px; box-shadow: 0 4px 20px rgba(26,115,232,0.25);
    }
    .diy-header h1 { font-size: 22px; font-weight: 700; margin-bottom: 6px; }
    .diy-header p { font-size: 13px; opacity: 0.85; }
    .diy-card {
      background: #fff; border-radius: 14px; padding: 24px;
      margin-bottom: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.05);
      border: 1px solid #e8eaed;
    }
    .diy-card-title {
      font-size: 16px; font-weight: 700; color: #202124;
      margin-bottom: 18px; padding-bottom: 12px;
      border-bottom: 2px solid #e8f0fe;
      display: flex; align-items: center; gap: 8px;
    }
    .diy-card-title i { color: #1a73e8; font-size: 18px; }
    .diy-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
    .diy-item {
      border: 1px solid #e8eaed; border-radius: 10px; padding: 14px;
      transition: all 0.2s ease; background: #fafbfc;
    }
    .diy-item:hover { border-color: #1a73e8; box-shadow: 0 2px 12px rgba(26,115,232,0.1); }
    .diy-item-label { font-size: 13px; font-weight: 600; color: #3c4043; margin-bottom: 8px; }
    .diy-item-desc { font-size: 11px; color: #80868b; margin-bottom: 10px; }
    .diy-preview {
      width: 100%; height: 80px; border-radius: 8px;
      background: #f1f3f4; display: flex; align-items: center; justify-content: center;
      margin-bottom: 10px; overflow: hidden; border: 1px dashed #dadce0;
    }
    .diy-preview img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .diy-preview .placeholder { color: #80868b; font-size: 12px; }
    .diy-actions { display: flex; gap: 8px; }
    .layui-btn { border-radius: 8px !important; font-weight: 500 !important; }
    .layui-btn-primary { border-color: #dadce0 !important; color: #3c4043 !important; }
    .layui-btn-danger { background: #ea4335 !important; }
    .diy-input {
      width: 100%; padding: 8px 12px; border: 1px solid #dadce0;
      border-radius: 8px; font-size: 13px; transition: all 0.2s;
    }
    .diy-input:focus { border-color: #1a73e8; outline: none; box-shadow: 0 0 0 3px rgba(26,115,232,0.1); }
    .diy-save-bar {
      position: sticky; bottom: 0; background: #fff;
      padding: 16px 24px; border-radius: 14px;
      box-shadow: 0 -4px 20px rgba(0,0,0,0.08);
      display: flex; justify-content: space-between; align-items: center;
      margin-top: 20px; border: 1px solid #e8eaed;
    }
    .diy-msg {
      padding: 12px 16px; border-radius: 10px; margin-bottom: 16px;
      font-size: 13px; font-weight: 500;
    }
    .diy-msg.success { background: #e6f4ea; color: #137333; border: 1px solid #ceead6; }
    .diy-msg.error { background: #fce8e6; color: #c5221f; border: 1px solid #fad2cf; }
    .diy-tag {
      display: inline-block; padding: 2px 8px; border-radius: 6px;
      font-size: 10px; font-weight: 600; background: #e8f0fe; color: #1a73e8;
    }
    .upload-area {
      border: 2px dashed #dadce0; border-radius: 10px; padding: 20px;
      text-align: center; cursor: pointer; transition: all 0.2s;
      background: #fafbfc;
    }
    .upload-area:hover { border-color: #1a73e8; background: #f5f9ff; }
    .upload-area i { font-size: 28px; color: #80868b; margin-bottom: 6px; }
    .upload-area p { font-size: 12px; color: #80868b; }
    .audio-preview {
      width: 100%; padding: 8px; background: #f1f3f4; border-radius: 8px;
      margin-bottom: 10px;
    }
    .audio-preview audio { width: 100%; height: 36px; }
  </style>
  <link href="./res/admin-white.css" rel="stylesheet">
</head>
<body>
<div class="diy-container">
  <div class="diy-header">
    <h1><i class="layui-icon layui-icon-theme"></i> DIY 自定义设置</h1>
    <p>自定义前端界面样式、导航图标、背景图、背景音乐等，所有图片均支持本地上传</p>
  </div>

  <?php if ($message): ?>
  <div class="diy-msg <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
  <?php endif; ?>

  <!-- 底部导航自定义 -->
  <div class="diy-card">
    <div class="diy-card-title"><i class="layui-icon layui-icon-layouts"></i> 底部导航栏自定义 <span class="diy-tag">5个导航项</span></div>
    <div class="diy-grid">
      <?php
      $nav_items = [
        ['key' => 'home', 'name' => '首页', 'href' => 'index.php'],
        ['key' => 'category', 'name' => '分类', 'href' => 'categories.php'],
        ['key' => 'hunter', 'name' => '打手', 'href' => 'select.php'],
        ['key' => 'message', 'name' => '消息', 'href' => 'customer_service.php'],
        ['key' => 'mine', 'name' => '我的', 'href' => 'my.php'],
      ];
      foreach ($nav_items as $nav):
        $default_icon = get_config('footer', $nav['key'] . '_icon_default');
        $active_icon = get_config('footer', $nav['key'] . '_icon_active');
        $text = get_config('footer', $nav['key'] . '_text', $nav['name']);
      ?>
      <div class="diy-item">
        <div class="diy-item-label"><?php echo $nav['name']; ?>导航</div>
        <div class="diy-item-desc">自定义图标和文字</div>
        <form method="post" enctype="multipart/form-data" style="margin-bottom:8px;">
          <input type="hidden" name="action" value="upload">
          <input type="hidden" name="upload_type" value="image">
          <input type="hidden" name="config_type" value="footer">
          <input type="hidden" name="config_key" value="<?php echo $nav['key']; ?>_icon_default">
          <div class="diy-preview">
            <?php if ($default_icon): ?>
              <img src="../<?php echo htmlspecialchars($default_icon); ?>" alt="默认图标">
            <?php else: ?>
              <span class="placeholder">默认图标</span>
            <?php endif; ?>
          </div>
          <div class="diy-actions">
            <label class="layui-btn layui-btn-sm layui-btn-primary" style="margin:0;cursor:pointer;">
              <i class="layui-icon layui-icon-upload"></i> 上传默认
              <input type="file" name="upload_file" accept="image/*" style="display:none" onchange="this.form.submit()">
            </label>
            <?php if ($default_icon): ?>
            <button type="button" class="layui-btn layui-btn-sm layui-btn-danger" onclick="deleteConfig('footer','<?php echo $nav['key']; ?>_icon_default')">删除</button>
            <?php endif; ?>
          </div>
        </form>
        <form method="post" enctype="multipart/form-data" style="margin-bottom:8px;">
          <input type="hidden" name="action" value="upload">
          <input type="hidden" name="upload_type" value="image">
          <input type="hidden" name="config_type" value="footer">
          <input type="hidden" name="config_key" value="<?php echo $nav['key']; ?>_icon_active">
          <div class="diy-preview">
            <?php if ($active_icon): ?>
              <img src="../<?php echo htmlspecialchars($active_icon); ?>" alt="选中图标">
            <?php else: ?>
              <span class="placeholder">选中图标</span>
            <?php endif; ?>
          </div>
          <div class="diy-actions">
            <label class="layui-btn layui-btn-sm layui-btn-primary" style="margin:0;cursor:pointer;">
              <i class="layui-icon layui-icon-upload"></i> 上传选中
              <input type="file" name="upload_file" accept="image/*" style="display:none" onchange="this.form.submit()">
            </label>
            <?php if ($active_icon): ?>
            <button type="button" class="layui-btn layui-btn-sm layui-btn-danger" onclick="deleteConfig('footer','<?php echo $nav['key']; ?>_icon_active')">删除</button>
            <?php endif; ?>
          </div>
        </form>
        <form method="post">
          <input type="hidden" name="action" value="save_config">
          <input type="text" name="config[footer][<?php echo $nav['key']; ?>_text]" class="diy-input" placeholder="导航文字" value="<?php echo htmlspecialchars($text); ?>">
          <button type="submit" class="layui-btn layui-btn-sm" style="margin-top:6px;width:100%;">保存文字</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- 底部导航背景 -->
  <div class="diy-card">
    <div class="diy-card-title"><i class="layui-icon layui-icon-picture"></i> 底部导航背景</div>
    <div class="diy-grid">
      <div class="diy-item">
        <div class="diy-item-label">背景图片</div>
        <div class="diy-item-desc">上传图片作为底部导航背景</div>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="upload">
          <input type="hidden" name="upload_type" value="image">
          <input type="hidden" name="config_type" value="footer">
          <input type="hidden" name="config_key" value="bg_image">
          <div class="diy-preview">
            <?php $fbg = get_config('footer', 'bg_image'); ?>
            <?php if ($fbg): ?>
              <img src="../<?php echo htmlspecialchars($fbg); ?>" alt="背景图">
            <?php else: ?>
              <span class="placeholder">未设置背景图</span>
            <?php endif; ?>
          </div>
          <div class="diy-actions">
            <label class="layui-btn layui-btn-sm" style="margin:0;cursor:pointer;">
              <i class="layui-icon layui-icon-upload"></i> 上传背景图
              <input type="file" name="upload_file" accept="image/*" style="display:none" onchange="this.form.submit()">
            </label>
            <?php if ($fbg): ?>
            <button type="button" class="layui-btn layui-btn-sm layui-btn-danger" onclick="deleteConfig('footer','bg_image')">删除</button>
            <?php endif; ?>
          </div>
        </form>
      </div>
      <div class="diy-item">
        <div class="diy-item-label">背景颜色</div>
        <div class="diy-item-desc">设置纯色背景（如 #ffffff）</div>
        <form method="post">
          <input type="hidden" name="action" value="save_config">
          <input type="text" name="config[footer][bg_color]" class="diy-input" placeholder="#ffffff" value="<?php echo htmlspecialchars(get_config('footer', 'bg_color')); ?>">
          <button type="submit" class="layui-btn layui-btn-sm" style="margin-top:10px;width:100%;">保存颜色</button>
        </form>
      </div>
    </div>
  </div>

  <!-- 全局背景图 -->
  <div class="diy-card">
    <div class="diy-card-title"><i class="layui-icon layui-icon-wall"></i> 全局背景自定义</div>
    <div class="diy-grid">
      <div class="diy-item">
        <div class="diy-item-label">页面背景图</div>
        <div class="diy-item-desc">所有页面的背景图片</div>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="upload">
          <input type="hidden" name="upload_type" value="image">
          <input type="hidden" name="config_type" value="general">
          <input type="hidden" name="config_key" value="bg_image">
          <div class="diy-preview">
            <?php $gbg = get_config('general', 'bg_image'); ?>
            <?php if ($gbg): ?>
              <img src="../<?php echo htmlspecialchars($gbg); ?>" alt="背景图">
            <?php else: ?>
              <span class="placeholder">未设置</span>
            <?php endif; ?>
          </div>
          <div class="diy-actions">
            <label class="layui-btn layui-btn-sm" style="margin:0;cursor:pointer;">
              <i class="layui-icon layui-icon-upload"></i> 上传背景图
              <input type="file" name="upload_file" accept="image/*" style="display:none" onchange="this.form.submit()">
            </label>
            <?php if ($gbg): ?>
            <button type="button" class="layui-btn layui-btn-sm layui-btn-danger" onclick="deleteConfig('general','bg_image')">删除</button>
            <?php endif; ?>
          </div>
        </form>
      </div>
      <div class="diy-item">
        <div class="diy-item-label">首页顶部Banner</div>
        <div class="diy-item-desc">首页顶部自定义横幅</div>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="upload">
          <input type="hidden" name="upload_type" value="image">
          <input type="hidden" name="config_type" value="general">
          <input type="hidden" name="config_key" value="top_banner">
          <div class="diy-preview">
            <?php $tbn = get_config('general', 'top_banner'); ?>
            <?php if ($tbn): ?>
              <img src="../<?php echo htmlspecialchars($tbn); ?>" alt="Banner">
            <?php else: ?>
              <span class="placeholder">未设置</span>
            <?php endif; ?>
          </div>
          <div class="diy-actions">
            <label class="layui-btn layui-btn-sm" style="margin:0;cursor:pointer;">
              <i class="layui-icon layui-icon-upload"></i> 上传Banner
              <input type="file" name="upload_file" accept="image/*" style="display:none" onchange="this.form.submit()">
            </label>
            <?php if ($tbn): ?>
            <button type="button" class="layui-btn layui-btn-sm layui-btn-danger" onclick="deleteConfig('general','top_banner')">删除</button>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- 背景音乐 -->
  <div class="diy-card">
    <div class="diy-card-title"><i class="layui-icon layui-icon-headset"></i> 背景音乐设置</div>
    <div class="diy-grid">
      <div class="diy-item">
        <div class="diy-item-label">背景音乐文件</div>
        <div class="diy-item-desc">支持 MP3/WAV/OGG，最大10MB</div>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="upload">
          <input type="hidden" name="upload_type" value="audio">
          <input type="hidden" name="config_type" value="general">
          <input type="hidden" name="config_key" value="bg_music">
          <?php $music = get_config('general', 'bg_music'); ?>
          <?php if ($music): ?>
          <div class="audio-preview">
            <audio controls><source src="../<?php echo htmlspecialchars($music); ?>" type="audio/mpeg"></audio>
          </div>
          <?php else: ?>
          <div class="diy-preview"><span class="placeholder">未设置音乐</span></div>
          <?php endif; ?>
          <div class="diy-actions">
            <label class="layui-btn layui-btn-sm" style="margin:0;cursor:pointer;">
              <i class="layui-icon layui-icon-upload"></i> 上传音乐
              <input type="file" name="upload_file" accept="audio/*" style="display:none" onchange="this.form.submit()">
            </label>
            <?php if ($music): ?>
            <button type="button" class="layui-btn layui-btn-sm layui-btn-danger" onclick="deleteConfig('general','bg_music')">删除</button>
            <?php endif; ?>
          </div>
        </form>
      </div>
      <div class="diy-item">
        <div class="diy-item-label">音乐开关</div>
        <div class="diy-item-desc">开启后前端将自动播放背景音乐</div>
        <form method="post">
          <input type="hidden" name="action" value="save_config">
          <select name="config[general][bg_music_enabled]" class="diy-input">
            <option value="1" <?php echo get_config('general', 'bg_music_enabled') == '1' ? 'selected' : ''; ?>>开启</option>
            <option value="0" <?php echo get_config('general', 'bg_music_enabled') == '0' ? 'selected' : ''; ?>>关闭</option>
          </select>
          <button type="submit" class="layui-btn layui-btn-sm" style="margin-top:10px;width:100%;">保存设置</button>
        </form>
      </div>
    </div>
  </div>

  <!-- 快捷功能自定义 -->
  <div class="diy-card">
    <div class="diy-card-title"><i class="layui-icon layui-icon-app"></i> 快捷功能入口 <span class="diy-tag">首页展示</span></div>
    <p style="font-size:12px;color:#80868b;margin-bottom:14px;">快捷功能入口在后台「快捷入口」菜单中管理，支持上传图标、设置标题和链接。</p>
    <a href="./quick_entry/" class="layui-btn"><i class="layui-icon layui-icon-jump"></i> 前往管理快捷入口</a>
  </div>

  <!-- 实用功能开关 -->
  <div class="diy-card">
    <div class="diy-card-title"><i class="layui-icon layui-icon-set"></i> 实用功能开关</div>
    <div class="diy-grid">
      <div class="diy-item">
        <div class="diy-item-label">回到顶部按钮</div>
        <div class="diy-item-desc">页面右下角显示回到顶部按钮</div>
        <form method="post">
          <input type="hidden" name="action" value="save_config">
          <select name="config[feature][back_to_top]" class="diy-input">
            <option value="1" <?php echo get_config('feature', 'back_to_top', '1') == '1' ? 'selected' : ''; ?>>开启</option>
            <option value="0" <?php echo get_config('feature', 'back_to_top') == '0' ? 'selected' : ''; ?>>关闭</option>
          </select>
          <button type="submit" class="layui-btn layui-btn-sm" style="margin-top:10px;width:100%;">保存</button>
        </form>
      </div>
      <div class="diy-item">
        <div class="diy-item-label">页面加载动画</div>
        <div class="diy-item-desc">进入页面时显示加载动画</div>
        <form method="post">
          <input type="hidden" name="action" value="save_config">
          <select name="config[feature][loader]" class="diy-input">
            <option value="1" <?php echo get_config('feature', 'loader', '1') == '1' ? 'selected' : ''; ?>>开启</option>
            <option value="0" <?php echo get_config('feature', 'loader') == '0' ? 'selected' : ''; ?>>关闭</option>
          </select>
          <button type="submit" class="layui-btn layui-btn-sm" style="margin-top:10px;width:100%;">保存</button>
        </form>
      </div>
      <div class="diy-item">
        <div class="diy-item-label">页面切换动画</div>
        <div class="diy-item-desc">点击链接时显示过渡动画</div>
        <form method="post">
          <input type="hidden" name="action" value="save_config">
          <select name="config[feature][page_transition]" class="diy-input">
            <option value="1" <?php echo get_config('feature', 'page_transition', '1') == '1' ? 'selected' : ''; ?>>开启</option>
            <option value="0" <?php echo get_config('feature', 'page_transition') == '0' ? 'selected' : ''; ?>>关闭</option>
          </select>
          <button type="submit" class="layui-btn layui-btn-sm" style="margin-top:10px;width:100%;">保存</button>
        </form>
      </div>
      <div class="diy-item">
        <div class="diy-item-label">客服悬浮按钮</div>
        <div class="diy-item-desc">页面显示悬浮客服入口</div>
        <form method="post">
          <input type="hidden" name="action" value="save_config">
          <select name="config[feature][float_service]" class="diy-input">
            <option value="1" <?php echo get_config('feature', 'float_service', '1') == '1' ? 'selected' : ''; ?>>开启</option>
            <option value="0" <?php echo get_config('feature', 'float_service') == '0' ? 'selected' : ''; ?>>关闭</option>
          </select>
          <button type="submit" class="layui-btn layui-btn-sm" style="margin-top:10px;width:100%;">保存</button>
        </form>
      </div>
    </div>
  </div>

  <div class="diy-save-bar">
    <span style="font-size:13px;color:#80868b;">修改后请刷新前端页面查看效果</span>
    <a href="../kehu/index.php" target="_blank" class="layui-btn layui-btn-primary"><i class="layui-icon layui-icon-link"></i> 预览前端</a>
  </div>
</div>

<form id="deleteForm" method="post" style="display:none;">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="config_type" id="deleteType">
  <input type="hidden" name="config_key" id="deleteKey">
</form>

<script>
function deleteConfig(type, key) {
  if (confirm('确定要删除这个配置吗？')) {
    document.getElementById('deleteType').value = type;
    document.getElementById('deleteKey').value = key;
    document.getElementById('deleteForm').submit();
  }
}
</script>
</body>
</html>
