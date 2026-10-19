<?php
// 底部导航项配置 - 恢复原顺序：首页、分类、打手、消息、我的
$footer_items = [
    ['index' => 0, 'key' => 'home', 'href' => 'index.php', 'text' => '首页',
     'default_icon' => '../assets/images/img/首页.png', 'active_icon' => '../assets/images/img/首页1.png'],
    ['index' => 1, 'key' => 'category', 'href' => 'categories.php', 'text' => '分类',
     'default_icon' => '../assets/images/img/分类.png', 'active_icon' => '../assets/images/img/分类1.png'],
    ['index' => 2, 'key' => 'hunter', 'href' => 'select.php', 'text' => '打手',
     'default_icon' => '../assets/images/img/打手.png', 'active_icon' => '../assets/images/img/打手1.png'],
    ['index' => 3, 'key' => 'message', 'href' => 'message.php', 'text' => '消息',
     'default_icon' => '../assets/images/img/消息.png', 'active_icon' => '../assets/images/img/消息1.png'],
    ['index' => 4, 'key' => 'mine', 'href' => 'my.php', 'text' => '我的',
     'default_icon' => '../assets/images/img/我的.png', 'active_icon' => '../assets/images/img/我的1.png'],
];

// 获取当前页面文件名，用于高亮
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- 底部导航栏 -->
<nav class="flat-white-footer v3-footer">
    <div class="footer-wrap">
        <?php foreach ($footer_items as $item):
            $is_active = ($current_page === $item['href']);
        ?>
        <a href="<?php echo $item['href']; ?>" class="footer-item<?php echo $is_active ? ' active' : ''; ?>" data-index="<?php echo $item['index']; ?>" data-key="<?php echo $item['key']; ?>">
            <div class="f-icon-wrap">
                <img src="<?php echo $item['default_icon']; ?>" alt="<?php echo $item['text']; ?>" class="f-icon f-icon-default">
                <img src="<?php echo $item['active_icon']; ?>" alt="<?php echo $item['text']; ?>" class="f-icon f-icon-active">
            </div>
            <span class="f-text"><?php echo $item['text']; ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</nav>
<style>
.flat-white-footer * {
    margin: 0; padding: 0; box-sizing: border-box;
    -webkit-tap-highlight-color: transparent;
}
.flat-white-footer {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 999;
    background: #ffffff !important;
    border-top: 1px solid #e2e8f0 !important;
    box-shadow: 0 -4px 20px rgba(59, 130, 246, 0.06) !important;
    padding-bottom: env(safe-area-inset-bottom);
}
.footer-wrap {
    display: flex; align-items: center; justify-content: space-around;
    height: 56px; max-width: 100%; margin: 0 auto;
}
.footer-item {
    flex: 1; display: flex; flex-direction: column; align-items: center;
    justify-content: center; text-decoration: none; height: 100%;
    position: relative; transition: all 0.2s ease;
}
.footer-item .f-icon-wrap {
    width: 24px; height: 24px; position: relative; margin-bottom: 2px;
}
.footer-item .f-icon {
    width: 100%; height: 100%; object-fit: contain;
    position: absolute; top: 0; left: 0;
    transition: opacity 0.2s ease;
}
.footer-item .f-icon-default { opacity: 1; }
.footer-item .f-icon-active { opacity: 0; }
.footer-item.active .f-icon-default { opacity: 0; }
.footer-item.active .f-icon-active { opacity: 1; }
.footer-item .f-text {
    font-size: 10px; color: #999; font-weight: 400;
    transition: color 0.2s ease;
}
.footer-item.active .f-text { color: #3b82f6; font-weight: 500; }
.footer-item:active { transform: scale(0.92); }
</style>
