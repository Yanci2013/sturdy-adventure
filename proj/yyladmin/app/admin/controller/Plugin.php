<?php
/*
 * @Description  : 插件管理
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\PluginService;

class Plugin
{
    /**
     * 插件管理列表
     */
    public function pluginList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('plugin_name/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['plugin_name', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = PluginService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 插件管理信息
     */
    public function pluginInfo()
    {
        $param['plugin_id'] = Request::param('plugin_id/d', '');

        $data = PluginService::info($param['plugin_id']);

        return success($data);
    }

    /**
     * 插件管理添加
     */
    public function pluginAdd()
    {
        if (Request::isGet()) {
            $data = PluginService::add();
        } else {
            $param['plugin_name'] = Request::param('plugin_name/s', '');
            $param['code'] = Request::param('code/s', '');
            $param['version'] = Request::param('version/s', '');
            $param['author'] = Request::param('author/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = PluginService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 插件管理修改
     */
    public function pluginEdit()
    {
        $param['plugin_id'] = Request::param('plugin_id/d', '');

        if (Request::isGet()) {
            $data = PluginService::edit($param);
        } else {
            $param['plugin_name'] = Request::param('plugin_name/s', '');
            $param['code'] = Request::param('code/s', '');
            $param['version'] = Request::param('version/s', '');
            $param['author'] = Request::param('author/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = PluginService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 插件管理删除
     */
    public function pluginDele()
    {
        $param['plugin_id'] = Request::param('plugin_id/d', '');

        $data = PluginService::dele($param['plugin_id']);

        return success($data);
    }

    /**
     * 插件管理是否禁用
     */
    public function pluginDisable()
    {
        $param['plugin_id']      = Request::param('plugin_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = PluginService::disable($param);

        return success($data);
    }
}
