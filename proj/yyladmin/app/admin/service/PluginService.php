<?php
/*
 * @Description  : 插件管理
 */

namespace app\admin\service;

use think\facade\Db;

class PluginService
{
    /**
     * 插件管理列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'plugin_id,plugin_name,code,version,author,status,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'plugin_id' => 'desc'];
        }

        $count = Db::name('plugin')->where($where)->count('plugin_id');

        $list = Db::name('plugin')
            ->field($field)
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->order($order)
            ->select()
            ->toArray();

        $data['count'] = $count;
        $data['pages'] = ceil($count / $limit);
        $data['page']  = $page;
        $data['limit'] = $limit;
        $data['list']  = $list;

        return $data;
    }

    /**
     * 插件管理信息
     */
    public static function info($id)
    {
        $info = Db::name('plugin')->where('plugin_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 插件管理添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['plugin_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('plugin')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['plugin_id'] = $id;

        return $param;
    }

    /**
     * 插件管理修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['plugin_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['plugin_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('plugin')->where('plugin_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['plugin_id'] = $id;

        return $param;
    }

    /**
     * 插件管理删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('plugin')->where('plugin_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['plugin_id' => $id];
    }

    /**
     * 插件管理是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('plugin')->where('plugin_id', $param['plugin_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
