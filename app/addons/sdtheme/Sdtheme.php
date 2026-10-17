<?php
namespace addons\sdtheme;

use app\common\library\Menu;
use think\Addons;
use think\Request;

/**
 * 插件
 */
class Sdtheme extends Addons
{

    /**
     * HTML替换
     */
    public function viewFilter(&$content)
    {
        $request = Request::instance();
        $module = strtolower($request->module());
        $controller = strtolower($request->controller());
        $action = strtolower($request->action());

        if ($module == 'admin') {

            // 如果没有定义主题就用  sdtheme-white
            if ($controller == 'index' && $action == 'index') {
                if (!config('fastadmin.adminskin') && !cookie('adminskin')) {
                    $content = preg_replace(
                        '/(<body[^>]*class=")([^"]*)(")/',
                        '$1$2  sdtheme-white $3',
                        $content
                    );
                }

                // 右侧菜单栏增加主题
                $newItem = '<li><a href="javascript:;" data-skin="" class="clearfix full-opacity-hover"><div><span style="width: 20%; height: 27px; background: #FFFFFF;"></span><span style="width: 80%; height: 27px; background: #f4f5f7;"></span></div></a><p class="text-center no-margin">sdtheme</p></li>';
                $content = preg_replace(
                    '/(<ul[^>]*class="list-unstyled clearfix skin-list"[^>]*>)/',
                    '$1' . $newItem,
                    $content
                );
            }

            if (!($controller == 'index' && $action == 'login')) {
                $version = config('site.version');
                $cdnurl = config('site.cdnurl');
                $html = "<link href='{$cdnurl}/assets/addons/sdtheme/simple.css?v={$version}' rel='stylesheet'>";

                $content = preg_replace(
                    '/<!--\[if lt IE 9\]>(.*?)<!\[endif\]-->/is',
                    $html,
                    $content
                );

                $content = preg_replace(
                    '/(<body[^>]*class=")([^"]*)(")/',
                    '$1$2 sdtheme-body $3',
                    $content
                );
            }
        }


    }

    /**
     * 插件安装方法
     * @return bool
     */
    public function install()
    {
        $menu=[];
        $config_file= ADDON_PATH ."sdtheme" . DS.'config'.DS. "menu.php";
        if (is_file($config_file)) {
            $menu = include $config_file;
        }
        if($menu){
            Menu::create($menu);
        }
        return true;
    }

    /**
     * 插件卸载方法
     * @return bool
     */
    public function uninstall()
    {
        $info=get_addon_info('sdtheme');
        Menu::delete(isset($info['first_menu'])?$info['first_menu']:'sdtheme');
        return true;
    }

    /**
     * 插件启用方法
     */
    public function enable()
    {
        $info=get_addon_info('sdtheme');
        Menu::enable(isset($info['first_menu'])?$info['first_menu']:'sdtheme');
    }

    /**
     * 插件禁用方法
     */
    public function disable()
    {
        $info=get_addon_info('sdtheme');
        Menu::disable(isset($info['first_menu'])?$info['first_menu']:'sdtheme');
    }
}
