<?php
ob_start();
require_once("../includes/session_manager.php");
include("../includes/common.php");

if (!is_admin_logged_in()) {
    ob_end_clean();
    header("Location: login.php");
    exit();
}

function getAdminById($pdo, $admin_id) {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$admin_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$site_title = "俱乐部";
$site_logo = 'assets/images/logo.png';

try {
    $stmt = $pdo->query("SELECT site_title, site_logo, site_domain FROM settings LIMIT 1");
    if ($stmt && $stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $site_title = $row['site_title'] ?? $site_title;
        $site_logo = $row['site_logo'] ?? $site_logo;
        if (!empty($row['site_domain'])) {
            $site_domain = rtrim($row['site_domain'], '/');
            if (strpos($site_logo, 'http') !== 0) {
                $site_logo = $site_domain . '/' . ltrim($site_logo, '/');
            }
        }
    }
} catch (PDOException $e) {}

$admin_id = get_admin_id();
$admin = getAdminById($pdo, $admin_id);

$admin_nickname = $admin['nickname'] ?? $admin['username'] ?? 'Admin';
$admin_avatar = $admin['avatar'] ?? '';
if (empty($admin_avatar)) {
    $admin_avatar = 'https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image?prompt=professional%20admin%20avatar%20icon&image_size=square';
}
$is_super = ($admin['role'] ?? 'admin') === 'super';

if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

// 获取菜单
function getMenus($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM menus WHERE status = 1 ORDER BY sort_order ASC, id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { return []; }
}
$menus = getMenus($pdo);
$has_menu_table = !empty($menus);

function buildMenuTree($menus, $parentId = 0) {
    $tree = [];
    foreach ($menus as $menu) {
        if ($menu['parent_id'] == $parentId) {
            $children = buildMenuTree($menus, $menu['id']);
            if (!empty($children)) $menu['children'] = $children;
            $tree[] = $menu;
        }
    }
    return $tree;
}
$menuTree = buildMenuTree($menus);

function renderMenu($tree) {
    $html = '';
    foreach ($tree as $item) {
        $hasChildren = !empty($item['children']);
        $url = !empty($item['url']) ? ' lay-href="' . htmlspecialchars($item['url']) . '"' : '';
        $tips = !empty($item['tips']) ? ' lay-tips="' . htmlspecialchars($item['tips']) . '"' : '';
        if ($hasChildren) {
            $html .= '<li data-name="' . htmlspecialchars($item['name']) . '" class="layui-nav-item">';
            $html .= '<a href="javascript:;"' . $tips . ' lay-direction="2"><cite>' . htmlspecialchars($item['title']) . '</cite></a>';
            $html .= '<dl class="layui-nav-child">';
            foreach ($item['children'] as $child) {
                $cUrl = !empty($child['url']) ? ' lay-href="' . htmlspecialchars($child['url']) . '"' : '';
                $html .= '<dd data-name="' . htmlspecialchars($child['name']) . '"><a' . $cUrl . '>' . htmlspecialchars($child['title']) . '</a></dd>';
            }
            $html .= '</dl></li>';
        } else {
            $html .= '<li data-name="' . htmlspecialchars($item['name']) . '" class="layui-nav-item">';
            $html .= '<a href="javascript:;"' . $url . $tips . ' lay-direction="2"><cite>' . htmlspecialchars($item['title']) . '</cite></a></li>';
        }
    }
    return $html;
}
$menuHtml = renderMenu($menuTree);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title><?php echo $site_title; ?> - 管理后台</title>
  <meta name="renderer" content="webkit">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="./res/layui/css/layui.css?v=2.9.0" rel="stylesheet">
  <link href="./res/adminui/dist/css/admin.css?v=1.0.0" rel="stylesheet">
  <style>
    * { box-sizing: border-box; }
    .layui-side.layui-side-menu {
      background: #ffffff !important;
      border-right: 1px solid #e8eaed !important;
      box-shadow: 2px 0 12px rgba(0,0,0,0.04);
    }
    .layui-side.layui-side-menu .layui-nav.layui-nav-tree { background: transparent !important; }
    .layui-side.layui-side-menu .layui-nav.layui-nav-tree .layui-nav-item {
      margin: 2px 8px; border-radius: 8px; overflow: hidden;
    }
    .layui-side.layui-side-menu .layui-nav.layui-nav-tree .layui-nav-item a {
      color: #5f6368 !important; padding: 12px 16px !important;
      display: flex !important; align-items: center !important;
      font-size: 14px; font-weight: 500; border-radius: 8px;
      transition: all 0.25s ease; height: auto !important; line-height: 1.4 !important;
      justify-content: center !important;
    }
    .layui-side.layui-side-menu .layui-nav.layui-nav-tree .layui-nav-item a:hover {
      color: #1a73e8 !important; background: rgba(26, 115, 232, 0.06) !important;
    }
    .layui-side.layui-side-menu .layui-nav.layui-nav-tree .layui-nav-item.layui-nav-itemed {
      background: rgba(26, 115, 232, 0.04) !important;
    }
    .layui-side.layui-side-menu .layui-nav.layui-nav-tree .layui-nav-item.layui-nav-itemed > a {
      color: #1a73e8 !important; background: rgba(26, 115, 232, 0.08) !important; font-weight: 600;
    }
    .layui-side.layui-side-menu .layui-nav.layui-nav-tree .layui-nav-child { background: transparent !important; padding: 2px 0; }
    .layui-side.layui-side-menu .layui-nav.layui-nav-tree .layui-nav-child dd {
      margin: 1px 8px; border-radius: 6px; overflow: hidden;
    }
    .layui-side.layui-side-menu .layui-nav.layui-nav-tree .layui-nav-child dd a {
      color: #5f6368 !important; padding: 9px 16px !important;
      font-size: 13px; border-radius: 6px; transition: all 0.2s ease;
      text-align: center !important; justify-content: center !important;
    }
    .layui-side.layui-side-menu .layui-nav.layui-nav-tree .layui-nav-child dd a:hover {
      color: #1a73e8 !important; background: rgba(26, 115, 232, 0.06) !important;
    }
    .layui-side.layui-side-menu .layui-nav.layui-nav-tree .layui-nav-child dd.layui-this a {
      color: #1a73e8 !important; background: rgba(26, 115, 232, 0.1) !important; font-weight: 600;
    }
    .layui-side.layui-side-menu .layui-nav .layui-nav-more {
      border-top-color: #9aa0a6 !important; right: 12px !important;
    }
    .layui-side.layui-side-menu .layui-nav .layui-nav-itemed .layui-nav-more {
      border-top-color: transparent !important; border-bottom-color: #9aa0a6 !important;
    }
    .layui-side.layui-side-menu .layui-logo {
      background: #ffffff !important; color: #202124 !important;
      border-bottom: 1px solid #e8eaed !important;
      display: flex !important; align-items: center !important;
      padding: 0 18px !important; gap: 10px !important;
      height: 60px !important;
    }
    .layui-side.layui-side-menu .layui-logo .logo-image {
      max-height: 32px !important; width: auto !important; border-radius: 4px;
    }
    .layui-side.layui-side-menu .layui-logo .logo-text {
      color: #202124 !important; font-size: 15px !important;
      font-weight: 700 !important; white-space: nowrap !important;
    }
    .layui-layout-admin .layui-header {
      background: #ffffff !important;
      box-shadow: 0 1px 3px rgba(0,0,0,0.06) !important;
      border-bottom: 1px solid #e8eaed !important; height: 60px !important;
    }
    .layui-layout-admin .layui-header .layui-nav { background: transparent !important; padding: 0 6px; }
    .layui-layout-admin .layui-header .layui-nav .layui-nav-item { line-height: 60px !important; }
    .layui-layout-admin .layui-header .layui-nav .layui-nav-item a {
      color: #5f6368 !important; padding: 0 12px !important; font-size: 14px;
      transition: all 0.25s ease; height: 60px; line-height: 60px;
      display: flex; align-items: center; gap: 6px;
    }
    .layui-layout-admin .layui-header .layui-nav .layui-nav-item a:hover {
      color: #1a73e8 !important; background: rgba(26, 115, 232, 0.05) !important;
    }
    .layui-layout-admin .layui-header .layui-nav .layui-nav-child {
      background: #ffffff !important; border: 1px solid #e8eaed !important;
      border-radius: 10px !important; box-shadow: 0 8px 30px rgba(0,0,0,0.1) !important;
      padding: 5px !important; top: 54px !important; min-width: 140px;
    }
    .layui-layout-admin .layui-header .layui-nav .layui-nav-child dd { border-radius: 6px; overflow: hidden; }
    .layui-layout-admin .layui-header .layui-nav .layui-nav-child dd a {
      color: #5f6368 !important; padding: 9px 14px !important; font-size: 13px;
      line-height: 1.4; height: auto; border-radius: 6px; margin: 1px 0;
    }
    .layui-layout-admin .layui-header .layui-nav .layui-nav-child dd a:hover {
      background: #f1f3f4 !important; color: #1a73e8 !important;
    }
    .layui-layout-admin .layui-header .layui-nav .layui-nav-child dd a i {
      margin-right: 8px; font-size: 14px;
    }
    .admin-name {
      font-weight: 600; color: #202124; font-size: 14px;
      max-width: 80px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .admin-role-tag {
      background: linear-gradient(135deg, #c9a96e 0%, #d4af37 100%);
      color: #fff; padding: 2px 8px; border-radius: 10px;
      font-size: 10px; font-weight: 700; margin-left: 6px;
    }
    .layadmin-pagetabs {
      background: #ffffff !important; border-bottom: 1px solid #e8eaed !important;
    }
    .layadmin-pagetabs .layui-tab-title li {
      background: #ffffff !important; color: #5f6368 !important;
      border-right: 1px solid #e8eaed !important; margin: 0 !important;
      transition: all 0.3s ease; padding: 0 16px !important; font-size: 13px;
    }
    .layadmin-pagetabs .layui-tab-title li.layui-this {
      background: #f8f9fa !important; color: #1a73e8 !important;
      font-weight: 600; border-bottom: 2px solid #1a73e8 !important;
    }
    .layadmin-pagetabs .layui-tab-title li:hover {
      background: #f8f9fa !important; color: #202124 !important;
    }
    .layadmin-tabs-control {
      background: #ffffff !important; color: #5f6368 !important;
      border-right: 1px solid #e8eaed !important; transition: all 0.3s;
    }
    .layadmin-tabs-control:hover { background: #f8f9fa !important; color: #1a73e8 !important; }
    .layui-body { background: #f5f6f8 !important; }

    /* 欢迎弹窗 */
    .wm-overlay {
      position: fixed; top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0,0,0,0.45); backdrop-filter: blur(6px);
      z-index: 99999; display: flex; align-items: center; justify-content: center;
      opacity: 0; transition: opacity 0.4s ease;
    }
    .wm-overlay.show { opacity: 1; }
    .wm-box {
      background: #ffffff; border-radius: 20px; width: 400px; max-width: 90vw;
      box-shadow: 0 20px 60px rgba(0,0,0,0.15); overflow: hidden;
      transform: translateY(20px) scale(0.96);
      transition: all 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .wm-overlay.show .wm-box { transform: translateY(0) scale(1); }
    .wm-header {
      background: linear-gradient(135deg, #1a73e8 0%, #4285f4 100%);
      padding: 30px 24px 45px; text-align: center; position: relative;
    }
    .wm-brand {
      font-size: 14px; color: rgba(255,255,255,0.7);
      letter-spacing: 4px; margin-bottom: 8px;
      font-weight: 500;
    }
    .wm-header h2 { color: #fff; font-size: 18px; font-weight: 700; margin-bottom: 4px; }
    .wm-header p { color: rgba(255,255,255,0.85); font-size: 12px; }
    .wm-body { padding: 24px; text-align: center; }
    .wm-body .role-badge {
      display: inline-block; background: #e8f0fe;
      color: #1a73e8; padding: 4px 14px; border-radius: 16px;
      font-size: 12px; font-weight: 700; margin-bottom: 16px;
    }
    .wm-body .wm-name { font-size: 20px; font-weight: 700; color: #202124; margin-bottom: 6px; }
    .wm-body .wm-text { color: #5f6368; font-size: 13px; margin-bottom: 24px; }
    .wm-body .btn-enter {
      display: inline-flex; align-items: center; gap: 6px;
      background: linear-gradient(135deg, #1a73e8 0%, #4285f4 100%);
      color: #fff; border: none; padding: 12px 40px; border-radius: 24px;
      font-size: 14px; font-weight: 600; cursor: pointer;
      transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(26,115,232,0.25);
    }
    .wm-body .btn-enter:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,115,232,0.35); }
    .wm-body .btn-skip {
      display: block; margin-top: 14px; color: #9aa0a6;
      font-size: 12px; cursor: pointer; background: none; border: none;
    }
    .wm-body .btn-skip:hover { color: #5f6368; }
  </style>
  <link href="./res/admin-white.css" rel="stylesheet">
</head>
<body class="layui-layout-body" id="LAY_home_iframe">
  <div id="LAY_app">
    <div class="layui-layout layui-layout-admin">

      <!-- 顶部导航 -->
      <div class="layui-header">
        <ul class="layui-nav layui-layout-left">
          <li class="layui-nav-item layadmin-flexible" lay-unselect>
            <a href="javascript:;" layadmin-event="flexible" title="侧边伸缩">
              <i class="layui-icon layui-icon-shrink-right" id="LAY_app_flexible"></i>
            </a>
          </li>
          <li class="layui-nav-item" lay-unselect>
            <a href="javascript:;" layadmin-event="refresh" title="刷新">
              <i class="layui-icon layui-icon-refresh-3"></i>
            </a>
          </li>
        </ul>
        <ul class="layui-nav layui-layout-right" lay-filter="layadmin-layout-right">
          <li class="layui-nav-item" lay-unselect>
            <a href="javascript:;" layadmin-event="help" title="帮助">
              <i class="layui-icon layui-icon-help" style="font-size: 18px;"></i>
            </a>
            <dl class="layui-nav-child">
              <dd><a href="javascript:;" id="showShortcuts"><i class="layui-icon layui-icon-keyboard"></i>快捷键</a></dd>
              <dd><a href="javascript:;" id="showHelp"><i class="layui-icon layui-icon-read"></i>使用帮助</a></dd>
            </dl>
          </li>
          <li class="layui-nav-item" lay-unselect>
            <a href="javascript:;" class="admin-avatar-wrap">
              <span class="admin-name"><?php echo htmlspecialchars($admin_nickname); ?></span>
              <span class="admin-role-tag"><?php echo $is_super ? '董事长' : '管理员'; ?></span>
              <i class="layui-icon layui-icon-down" style="font-size: 11px; color: #9aa0a6;"></i>
            </a>
            <dl class="layui-nav-child">
              <dd><a lay-href="profile.php"><i class="layui-icon layui-icon-username"></i>修改信息</a></dd>
              <dd layadmin-event="logout"><a><i class="layui-icon layui-icon-logout"></i>退出登录</a></dd>
            </dl>
          </li>
        </ul>
      </div>

      <!-- 侧边菜单 -->
      <div class="layui-side layui-side-menu">
        <div class="layui-side-scroll">
          <div class="layui-logo" lay-href="home/console.php">
            <img src="<?php echo $site_logo; ?>" alt="<?php echo $site_title; ?>" class="logo-image">
            <span class="logo-text"><?php echo $site_title; ?></span>
          </div>
          <ul class="layui-nav layui-nav-tree" lay-accordion id="LAY-system-side-menu" lay-filter="layadmin-system-side-menu">
            <?php if ($has_menu_table && !empty($menuHtml)): ?>
              <?php echo $menuHtml; ?>
            <?php else: ?>
            <li data-name="home" class="layui-nav-item">
              <a href="javascript:;" lay-tips="工作台" lay-direction="2"><cite>工作台</cite></a>
              <dl class="layui-nav-child">
                <dd data-name="console" class="layui-this"><a lay-href="home/console.php">控制台</a></dd>
                <dd data-name="dashboard"><a lay-href="home/dashboard.php">数据看板</a></dd>
              </dl>
            </li>
            <li data-name="carousel" class="layui-nav-item">
              <a href="javascript:;" lay-tips="轮播图设置" lay-direction="2"><cite>轮播图设置</cite></a>
              <dl class="layui-nav-child">
                <dd data-name="home-carousel"><a lay-href="home/carousel.php">首页轮播图</a></dd>
                <dd data-name="partner-carousel"><a lay-href="home/partner_carousel.php">商家中心轮播图</a></dd>
              </dl>
            </li>
            <li data-name="grid" class="layui-nav-item">
              <a href="javascript:;" lay-tips="商品管理" lay-direction="2"><cite>商品管理</cite></a>
              <dl class="layui-nav-child">
                <dd data-name="list"><a lay-href="goods/categories.php">分类列表</a></dd>
                <dd data-name="mobile"><a lay-href="goods/create_category.php">创建分类</a></dd>
                <dd data-name="all"><a lay-href="goods/products.php">商品列表</a></dd>
                <dd data-name="mobile-pc"><a lay-href="goods/create_product.php">创建商品</a></dd>
                <dd data-name="regions"><a lay-href="goods/game_regions.php">游戏大区</a></dd>
              </dl>
            </li>
            <li data-name="button" class="layui-nav-item">
              <a href="javascript:;" lay-tips="会员设置" lay-direction="2"><cite>打手等级</cite></a>
              <dl class="layui-nav-child">
                <dd data-name="member-levels"><a lay-href="seetings/member_levels.php">会员等级管理</a></dd>
                <dd data-name="member-levels-view"><a lay-href="seetings/member_levels_view.php">会员等级查看</a></dd>
              </dl>
            </li>
            <li data-name="quick-entry" class="layui-nav-item">
              <a href="javascript:;" lay-tips="快捷入口" lay-direction="2"><cite>快捷入口</cite></a>
              <dl class="layui-nav-child">
                <dd data-name="quick-list"><a lay-href="quick_entry/list.php">入口列表</a></dd>
                <dd data-name="quick-add"><a lay-href="quick_entry/add.php">新增入口</a></dd>
              </dl>
            </li>
            <li data-name="form" class="layui-nav-item">
              <a href="javascript:;" lay-tips="订单管理" lay-direction="2"><cite>订单管理</cite></a>
              <dl class="layui-nav-child">
                <dd data-name="orders"><a lay-href="orders/orders.php">订单列表</a></dd>
                <dd data-name="orders-cancel"><a lay-href="orders/orders-qxsh.php">取消列表</a></dd>
                <dd data-name="orders-pending"><a lay-href="orders/orders-pending-settlement.php">待结算订单</a></dd>
                <dd data-name="orders-completed"><a lay-href="orders/orders-completed-settled.php">商品已完成已结单</a></dd>
                <dd data-name="orders-deposit"><a lay-href="orders/orders-deposit-recharge.php">保证金充值订单</a></dd>
                <dd data-name="orders-balance"><a lay-href="orders/orders-balance-recharge.php">余额充值订单</a></dd>
              </dl>
            </li>
            <li data-name="penalty" class="layui-nav-item">
              <a href="javascript:;" lay-tips="罚单管理" lay-direction="2"><cite>罚单管理</cite></a>
              <dl class="layui-nav-child">
                <dd data-name="penalty-completed"><a lay-href="home/penalty_completed.php">已成功记录</a></dd>
              </dl>
            </li>
            <li data-name="finance" class="layui-nav-item">
              <a href="javascript:;" lay-tips="财务管理" lay-direction="2"><cite>财务管理</cite></a>
              <dl class="layui-nav-child">
                <dd data-name="transaction_logs"><a lay-href="finance/transaction_logs.php">资金流水</a></dd>
                <dd data-name="withdrawal"><a lay-href="seetings/withdrawal.php">提现管理</a></dd>
              </dl>
            </li>
            <li data-name="user" class="layui-nav-item">
              <a href="javascript:;" lay-tips="用户管理" lay-direction="2"><cite>用户管理</cite></a>
              <dl class="layui-nav-child">
                <dd data-name="approval"><a lay-href="user/approval_list.php">用户审核</a></dd>
                <dd data-name="h5user"><a lay-href="h5user/list.php">H5用户列表</a></dd>
                <dd data-name="playmate"><a lay-href="playmate/list.php">陪玩列表</a></dd>
                <dd data-name="partner"><a lay-href="partner/list.php">商家列表</a></dd>
                <dd data-name="administrators"><a lay-href="user/administrators/list.php">后台管理员</a></dd>
              </dl>
            </li>
            <li data-name="lottery" class="layui-nav-item">
              <a href="javascript:;" lay-tips="抽奖设置" lay-direction="2"><cite>抽奖设置</cite></a>
              <dl class="layui-nav-child">
                <dd data-name="lottery-system"><a lay-href="seetings/lottery_system.php">抽奖管理</a></dd>
              </dl>
            </li>
            <li data-name="invite" class="layui-nav-item">
              <a href="javascript:;" lay-tips="密钥管理" lay-direction="2"><cite>密钥管理</cite></a>
              <dl class="layui-nav-child">
                <dd data-name="invite-generator"><a lay-href="seetings/invite_generator.php">邀请码管理</a></dd>
              </dl>
            </li>
            <li data-name="system-set" class="layui-nav-item">
              <a href="javascript:;" lay-tips="系统设置" lay-direction="2"><cite>系统设置</cite></a>
              <dl class="layui-nav-child">
                <dd data-name="settings"><a lay-href="seetings/settings.php">网站设置</a></dd>
                <dd data-name="login-settings"><a lay-href="seetings/login_settings.php">登录方式设置</a></dd>
                <dd data-name="menu-list"><a lay-href="menu/list.php">菜单管理</a></dd>
              </dl>
            </li>
            <li data-name="menu-management" class="layui-nav-item">
              <a href="javascript:;" lay-href="menu/list.php" lay-tips="菜单管理" lay-direction="2"><cite>菜单管理</cite></a>
            </li>
            <li data-name="announcement" class="layui-nav-item">
              <a href="javascript:;" lay-tips="公告管理" lay-direction="2"><cite>公告管理</cite></a>
              <dl class="layui-nav-child">
                <dd data-name="announcement-list"><a lay-href="announcements/list.php">公告列表</a></dd>
                <dd data-name="announcement-create"><a lay-href="announcements/create.php">新增公告</a></dd>
              </dl>
            </li>
            <li data-name="customer-service" class="layui-nav-item">
              <a href="javascript:;" lay-tips="客服设置" lay-direction="2"><cite>客服设置</cite></a>
              <dl class="layui-nav-child">
                <dd data-name="cs-add"><a lay-href="customer_service/add.php">新增客服</a></dd>
                <dd data-name="cs-list"><a lay-href="customer_service/list.php">客服管理</a></dd>
              </dl>
            </li>
            <li data-name="logs" class="layui-nav-item">
              <a href="javascript:;" lay-tips="日志管理" lay-direction="2"><cite>日志管理</cite></a>
              <dl class="layui-nav-child">
                <dd data-name="admin-logs"><a lay-href="logs/admin_logs.php">管理员登录日志</a></dd>
              </dl>
            </li>
            <li data-name="my-set" class="layui-nav-item">
              <a href="javascript:;" lay-tips="我的设置" lay-direction="2"><cite>个人信息</cite></a>
              <dl class="layui-nav-child">
                <dd data-name="view-profile"><a lay-href="profile.php">修改信息</a></dd>
              </dl>
            </li>
            <li data-name="about" class="layui-nav-item">
              <a href="javascript:;" lay-href="home/about.php" lay-tips="关于系统" lay-direction="2"><cite>关于系统</cite></a>
            </li>
            <?php endif; ?>
          </ul>
        </div>
      </div>

      <div class="layadmin-pagetabs" id="LAY_app_tabs">
        <div class="layui-icon layadmin-tabs-control layui-icon-prev" layadmin-event="leftPage"></div>
        <div class="layui-icon layadmin-tabs-control layui-icon-next" layadmin-event="rightPage"></div>
        <div class="layui-icon layadmin-tabs-control layui-icon-down">
          <ul class="layui-nav layadmin-tabs-select" lay-filter="layadmin-pagetabs-nav">
            <li class="layui-nav-item" lay-unselect>
              <a href="javascript:;"></a>
              <dl class="layui-nav-child layui-anim-fadein">
                <dd layadmin-event="closeThisTabs"><a href="javascript:;">关闭当前标签页</a></dd>
                <dd layadmin-event="closeOtherTabs"><a href="javascript:;">关闭其它标签页</a></dd>
                <dd layadmin-event="closeRightTabs"><a href="javascript:;">关闭右侧标签页</a></dd>
                <dd layadmin-event="closeAllTabs"><a href="javascript:;">关闭全部标签页</a></dd>
              </dl>
            </li>
          </ul>
        </div>
        <div class="layui-tab" lay-unauto lay-allowClose="true" lay-filter="layadmin-layout-tabs">
          <ul class="layui-tab-title" id="LAY_app_tabsheader">
            <li lay-id="home/console.php" lay-attr="home/console.php" class="layui-this"><i class="layui-icon layui-icon-home"></i></li>
          </ul>
        </div>
      </div>

      <div class="layui-body" id="LAY_app_body">
        <div class="layadmin-tabsbody-item layui-show">
          <iframe src="home/console.php" frameborder="0" class="layadmin-iframe"></iframe>
        </div>
      </div>
      <div class="layadmin-body-shade" layadmin-event="shade"></div>
    </div>
  </div>

  <!-- 欢迎弹窗 -->
  <div class="wm-overlay" id="welcomeModal">
    <div class="wm-box">
      <div class="wm-header">
        <div class="wm-brand">小熠网络</div>
        <h2 id="wmTitle"><?php echo $is_super ? '欢迎董事长' : '欢迎管理员'; ?></h2>
        <p>尊贵的 <?php echo $site_title; ?> 管理后台</p>
      </div>
      <div class="wm-body">
        <div class="role-badge"><?php echo $is_super ? '董事长' : '管理员'; ?></div>
        <div class="wm-name" id="wmName"><?php echo htmlspecialchars($admin_nickname); ?></div>
        <div class="wm-text">
          <?php if($is_super): ?>
          欢迎董事长莅临指导，愿您运筹帷幄，决胜千里！
          <?php else: ?>
          欢迎回来，愿您工作顺利，步步高升！
          <?php endif; ?>
        </div>
        <button class="btn-enter" id="btnEnterAdmin">
          <i class="layui-icon layui-icon-ok"></i>
          进入后台
        </button>
        <button class="btn-skip" id="btnSkipWelcome">跳过</button>
      </div>
    </div>
  </div>

  <script src="./res/layui/layui.js"></script>
  <script>
  layui.config({ base: './res/' }).use(['index'], function(){
    var $ = layui.$;
    var layer = layui.layer;
    var adminNickname = '<?php echo addslashes($admin_nickname); ?>';

    // 每次进入都显示欢迎弹窗
    setTimeout(function() { $('#welcomeModal').addClass('show'); }, 300);
    $('#btnEnterAdmin, #btnSkipWelcome').on('click', function() {
      $('#welcomeModal').removeClass('show');
      setTimeout(function() { $('#welcomeModal').hide(); }, 400);
    });

    // 帮助
    $('#showShortcuts').on('click', function() {
      var html = '<div style="padding:24px"><h3 style="margin-bottom:20px;font-weight:700;color:#202124;font-size:16px">快捷键</h3>' +
        '<table style="width:100%;border-collapse:collapse;font-size:13px">' +
        '<tr style="border-bottom:1px solid #e8eaed"><td style="padding:10px 8px;font-family:monospace;color:#1a73e8;font-weight:600">Ctrl + K</td><td style="padding:10px 8px;color:#5f6368">快速搜索</td></tr>' +
        '<tr style="border-bottom:1px solid #e8eaed"><td style="padding:10px 8px;font-family:monospace;color:#1a73e8;font-weight:600">Ctrl + N</td><td style="padding:10px 8px;color:#5f6368">新建标签页</td></tr>' +
        '<tr style="border-bottom:1px solid #e8eaed"><td style="padding:10px 8px;font-family:monospace;color:#1a73e8;font-weight:600">Ctrl + W</td><td style="padding:10px 8px;color:#5f6368">关闭当前标签页</td></tr>' +
        '<tr style="border-bottom:1px solid #e8eaed"><td style="padding:10px 8px;font-family:monospace;color:#1a73e8;font-weight:600">Alt + &larr;</td><td style="padding:10px 8px;color:#5f6368">上一页</td></tr>' +
        '<tr style="border-bottom:1px solid #e8eaed"><td style="padding:10px 8px;font-family:monospace;color:#1a73e8;font-weight:600">Alt + &rarr;</td><td style="padding:10px 8px;color:#5f6368">下一页</td></tr>' +
        '<tr><td style="padding:10px 8px;font-family:monospace;color:#1a73e8;font-weight:600">F5</td><td style="padding:10px 8px;color:#5f6368">刷新页面</td></tr>' +
        '</table></div>';
      layer.open({ type: 1, title: '快捷键', area: ['380px', 'auto'], content: html });
    });

    $('#showHelp').on('click', function() {
      var html = '<div style="padding:24px;max-height:420px;overflow-y:auto">' +
        '<h3 style="margin-bottom:20px;font-weight:700;color:#202124;font-size:16px">使用帮助</h3>' +
        '<div style="margin-bottom:16px"><h4 style="font-weight:600;color:#1a73e8;margin-bottom:8px;font-size:14px">工作台</h4>' +
        '<p style="font-size:13px;color:#5f6368;line-height:1.8">&bull; 控制台：查看系统概览、订单统计、用户统计</p></div>' +
        '<div style="margin-bottom:16px"><h4 style="font-weight:600;color:#1a73e8;margin-bottom:8px;font-size:14px">商品管理</h4>' +
        '<p style="font-size:13px;color:#5f6368;line-height:1.8">&bull; 分类列表 / 商品列表 / 创建商品</p></div>' +
        '<div style="margin-bottom:16px"><h4 style="font-weight:600;color:#1a73e8;margin-bottom:8px;font-size:14px">订单管理</h4>' +
        '<p style="font-size:13px;color:#5f6368;line-height:1.8">&bull; 订单列表 / 待结算 / 取消列表</p></div>' +
        '<div style="margin-bottom:16px"><h4 style="font-weight:600;color:#1a73e8;margin-bottom:8px;font-size:14px">用户管理</h4>' +
        '<p style="font-size:13px;color:#5f6368;line-height:1.8">&bull; H5用户 / 陪玩 / 商家 / 管理员</p></div>' +
        '</div>';
      layer.open({ type: 1, title: '使用帮助', area: ['520px', 'auto'], content: html });
    });

    // 快捷键
    $(document).on('keydown', function(e) {
      if (e.ctrlKey && e.key === 'k') { e.preventDefault(); layer.msg('快速搜索开发中'); }
      if (e.ctrlKey && e.key === 'n') { e.preventDefault(); layer.msg('新建标签页开发中'); }
      if (e.ctrlKey && e.key === 'w') { e.preventDefault(); layer.msg('关闭标签页开发中'); }
    });
  });
  </script>

<script>
// 手机端菜单切换
document.addEventListener('DOMContentLoaded', function() {
    if (window.innerWidth <= 768) {
        // 添加菜单按钮
        var menuBtn = document.createElement('div');
        menuBtn.className = 'mobile-menu-btn';
        menuBtn.innerHTML = '<i class="fa fa-bars"></i>';
        menuBtn.onclick = function() {
            var side = document.querySelector('.layui-side');
            var mask = document.querySelector('.mobile-mask');
            if (side) side.classList.toggle('mobile-show');
            if (mask) mask.classList.toggle('show');
        };
        document.body.appendChild(menuBtn);
        
        // 添加遮罩
        var mask = document.createElement('div');
        mask.className = 'mobile-mask';
        mask.onclick = function() {
            var side = document.querySelector('.layui-side');
            if (side) side.classList.remove('mobile-show');
            this.classList.remove('show');
        };
        document.body.appendChild(mask);
    }
});
</script>
</body>
</html>
