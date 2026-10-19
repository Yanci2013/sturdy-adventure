<?php
ob_start();
require_once("../../includes/session_manager.php");
include("../../includes/common.php");

if (!is_admin_logged_in()) {
    ob_end_clean();
    header("Location: ../login.php");
    exit();
}

$site_title = "俱乐部";
try {
    $stmt = $pdo->query("SELECT site_title FROM settings LIMIT 1");
    if ($stmt && $stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $site_title = $row['site_title'] ?? $site_title;
    }
} catch (PDOException $e) {}

// AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $parent_id = intval($_POST['parent_id'] ?? 0);
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $tips = trim($_POST['tips'] ?? '');
        $status = intval($_POST['status'] ?? 1);
        if (empty($name) || empty($title)) {
            echo json_encode(['code' => 1, 'msg' => '菜单标识和标题不能为空']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO menus (name, title, icon, url, parent_id, sort_order, tips, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$name, $title, $icon, $url, $parent_id, $sort_order, $tips, $status]);
            echo json_encode(['code' => 0, 'msg' => '添加成功']);
        } catch (PDOException $e) {
            echo json_encode(['code' => 1, 'msg' => '添加失败：' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $parent_id = intval($_POST['parent_id'] ?? 0);
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $tips = trim($_POST['tips'] ?? '');
        $status = intval($_POST['status'] ?? 1);
        if ($id <= 0 || empty($name) || empty($title)) {
            echo json_encode(['code' => 1, 'msg' => '参数错误']);
            exit;
        }
        if ($parent_id == $id) {
            echo json_encode(['code' => 1, 'msg' => '不能将自己设为父菜单']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("UPDATE menus SET name=?, title=?, icon=?, url=?, parent_id=?, sort_order=?, tips=?, status=? WHERE id=?");
            $stmt->execute([$name, $title, $icon, $url, $parent_id, $sort_order, $tips, $status, $id]);
            echo json_encode(['code' => 0, 'msg' => '修改成功']);
        } catch (PDOException $e) {
            echo json_encode(['code' => 1, 'msg' => '修改失败：' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['code' => 1, 'msg' => '参数错误']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM menus WHERE parent_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['code' => 1, 'msg' => '该菜单下还有子菜单，请先删除子菜单']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM menus WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['code' => 0, 'msg' => '删除成功']);
        } catch (PDOException $e) {
            echo json_encode(['code' => 1, 'msg' => '删除失败：' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'get') {
        $id = intval($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare("SELECT * FROM menus WHERE id = ?");
            $stmt->execute([$id]);
            $menu = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($menu) echo json_encode(['code' => 0, 'data' => $menu]);
            else echo json_encode(['code' => 1, 'msg' => '菜单不存在']);
        } catch (PDOException $e) {
            echo json_encode(['code' => 1, 'msg' => '查询失败']);
        }
        exit;
    }
}

// 获取菜单列表
try {
    $stmt = $pdo->query("SELECT * FROM menus ORDER BY sort_order ASC, id ASC");
    $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $menus = [];
}

function buildTree($menus, $parentId = 0) {
    $tree = [];
    foreach ($menus as $menu) {
        if ($menu['parent_id'] == $parentId) {
            $children = buildTree($menus, $menu['id']);
            if (!empty($children)) $menu['children'] = $children;
            $tree[] = $menu;
        }
    }
    return $tree;
}
$menuTree = buildTree($menus);

function renderRows($tree, $level = 0) {
    $html = '';
    foreach ($tree as $item) {
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
        $type = $level == 0 ? '<span class="layui-badge layui-bg-blue">一级</span>' : '<span class="layui-badge layui-bg-green">二级</span>';
        $status = $item['status'] == 1 ? '<span class="layui-badge layui-bg-green">显示</span>' : '<span class="layui-badge layui-bg-gray">隐藏</span>';
        $html .= "{ id: {$item['id']}, title: '{$indent}" . addslashes($item['title']) . "', name: '" . addslashes($item['name']) . "', type: '{$type}', url: '" . addslashes($item['url']) . "', sort_order: {$item['sort_order']}, status: '{$status}', operate: '' },
";
        if (!empty($item['children'])) $html .= renderRows($item['children'], $level + 1);
    }
    return $html;
}
$menuData = renderRows($menuTree);

// 获取一级菜单用于下拉
$topMenus = [];
foreach ($menus as $m) {
    if ($m['parent_id'] == 0) $topMenus[] = $m;
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>菜单管理 - <?php echo $site_title; ?></title>
  <meta name="renderer" content="webkit">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="../res/layui/css/layui.css?v=2.9.0" rel="stylesheet">
  <link href="../res/adminui/dist/css/admin.css?v=1.0.0" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .page-container { padding: 24px; }
    .toolbar { margin-bottom: 20px; display: flex; gap: 10px; }
    .layui-form-label { width: 100px; }
    .layui-input-block { margin-left: 130px; }
    .layui-btn { border-radius: 8px; font-weight: 600; }
    .layui-btn-normal { background: linear-gradient(135deg, #1a73e8 0%, #4285f4 100%); }
    .layui-form input, .layui-form select { border-radius: 8px; }
  </style>
</head>
<body>
  <div class="page-container">
    <div class="toolbar">
      <button class="layui-btn layui-btn-normal" id="btnAddTop"><i class="layui-icon layui-icon-add-1"></i> 添加一级菜单</button>
      <button class="layui-btn layui-btn-primary" id="btnAddSub"><i class="layui-icon layui-icon-add-1"></i> 添加二级菜单</button>
    </div>
    <table class="layui-hide" id="menuTable" lay-filter="menuTable"></table>
  </div>

  <div id="menuForm" style="display: none; padding: 20px;">
    <form class="layui-form" lay-filter="menuForm">
      <input type="hidden" name="id" id="menuId">
      <input type="hidden" name="action" id="formAction" value="add">
      <div class="layui-form-item">
        <label class="layui-form-label">菜单标识</label>
        <div class="layui-input-block">
          <input type="text" name="name" required lay-verify="required" placeholder="如：home, user" class="layui-input">
        </div>
      </div>
      <div class="layui-form-item">
        <label class="layui-form-label">菜单标题</label>
        <div class="layui-input-block">
          <input type="text" name="title" required lay-verify="required" placeholder="如：工作台" class="layui-input">
        </div>
      </div>
      <div class="layui-form-item">
        <label class="layui-form-label">父菜单</label>
        <div class="layui-input-block">
          <select name="parent_id" id="parentSelect">
            <option value="0">无（作为一级菜单）</option>
            <?php foreach ($topMenus as $m): ?>
            <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['title']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="layui-form-item">
        <label class="layui-form-label">链接地址</label>
        <div class="layui-input-block">
          <input type="text" name="url" placeholder="如：home/console.php，一级菜单可留空" class="layui-input">
        </div>
      </div>
      <div class="layui-form-item">
        <label class="layui-form-label">图标类名</label>
        <div class="layui-input-block">
          <input type="text" name="icon" placeholder="如：layui-icon-home" class="layui-input">
        </div>
      </div>
      <div class="layui-form-item">
        <label class="layui-form-label">提示文字</label>
        <div class="layui-input-block">
          <input type="text" name="tips" placeholder="鼠标悬停提示" class="layui-input">
        </div>
      </div>
      <div class="layui-form-item">
        <label class="layui-form-label">排序</label>
        <div class="layui-input-block">
          <input type="number" name="sort_order" value="0" class="layui-input">
        </div>
      </div>
      <div class="layui-form-item">
        <label class="layui-form-label">状态</label>
        <div class="layui-input-block">
          <input type="radio" name="status" value="1" title="显示" checked>
          <input type="radio" name="status" value="0" title="隐藏">
        </div>
      </div>
      <div class="layui-form-item">
        <div class="layui-input-block">
          <button class="layui-btn layui-btn-normal" lay-submit lay-filter="submitMenu">保存</button>
          <button type="button" class="layui-btn layui-btn-primary" id="btnCancel">取消</button>
        </div>
      </div>
    </form>
  </div>

  <script type="text/html" id="toolbarDemo">
    <a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="edit">编辑</a>
    <a class="layui-btn layui-btn-xs layui-btn-danger" lay-event="del">删除</a>
  </script>

  <script src="../res/layui/layui.js"></script>
  <script>
  layui.use(['table', 'form', 'layer'], function(){
    var table = layui.table, form = layui.form, layer = layui.layer, $ = layui.$;

    table.render({
      elem: '#menuTable',
      data: [<?php echo rtrim($menuData, ",
"); ?>],
      cols: [[
        {field: 'id', title: 'ID', width: 60},
        {field: 'title', title: '菜单名称', minWidth: 200},
        {field: 'name', title: '标识', width: 120},
        {field: 'type', title: '类型', width: 80},
        {field: 'url', title: '链接', minWidth: 180},
        {field: 'sort_order', title: '排序', width: 70},
        {field: 'status', title: '状态', width: 80},
        {title: '操作', toolbar: '#toolbarDemo', width: 130, fixed: 'right'}
      ]],
      page: false, limit: 100
    });

    var formIndex = null;
    function openForm(action, title, data) {
      $('#formAction').val(action);
      form.val('menuForm', {
        id: '', name: '', title: '', parent_id: data.parent_id !== undefined ? data.parent_id : 0,
        url: '', icon: '', tips: '', sort_order: 0, status: 1
      });
      if (action === 'edit' && data.id) {
        $('#menuId').val(data.id);
        form.val('menuForm', data);
      }
      form.render('select');
      formIndex = layer.open({ type: 1, title: title, area: ['500px', 'auto'], content: $('#menuForm') });
    }

    $('#btnAddTop').on('click', function(){ openForm('add', '添加一级菜单', {parent_id: 0}); });
    $('#btnAddSub').on('click', function(){ openForm('add', '添加二级菜单', {parent_id: ''}); });
    $('#btnCancel').on('click', function(){ layer.close(formIndex); });

    form.on('submit(submitMenu)', function(data){
      $.post('', data.field, function(res){
        if (res.code === 0) { layer.msg(res.msg, {icon: 1}); setTimeout(function(){ location.reload(); }, 500); }
        else { layer.msg(res.msg, {icon: 2}); }
      }, 'json');
      return false;
    });

    table.on('tool(menuTable)', function(obj){
      var data = obj.data;
      if (obj.event === 'edit') {
        $.post('', {action: 'get', id: data.id}, function(res){
          if (res.code === 0) openForm('edit', '编辑菜单', res.data);
          else layer.msg(res.msg, {icon: 2});
        }, 'json');
      }
      if (obj.event === 'del') {
        layer.confirm('确定要删除该菜单吗？', function(index){
          $.post('', {action: 'delete', id: data.id}, function(res){
            if (res.code === 0) { layer.msg(res.msg, {icon: 1}); setTimeout(function(){ location.reload(); }, 500); }
            else { layer.msg(res.msg, {icon: 2}); }
          }, 'json');
          layer.close(index);
        });
      }
    });
  });
  </script>
</body>
</html>