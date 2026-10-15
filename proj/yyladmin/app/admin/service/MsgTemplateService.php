<?php
/*
 * @Description  : 模板消息配置
 */

namespace app\admin\service;

use think\facade\Db;

class MsgTemplateService
{
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'tpl_id,name,code,title,content,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'tpl_id' => 'desc'];
        }

        $count = Db::name('msg_template')->where($where)->count('tpl_id');

        $list = Db::name('msg_template')
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

    public static function info($tpl_id)
    {
        $info = Db::name('msg_template')->where('tpl_id', $tpl_id)->find();

        if (empty($info)) {
            exception('模板不存在：' . $tpl_id);
        }

        return $info;
    }

    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['tpl_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('msg_template')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['tpl_id'] = $id;

        return $param;
    }

    public static function edit($param, $method = 'get')
    {
        $tpl_id = $param['tpl_id'];

        if ($method == 'get') {
            return self::info($tpl_id);
        }

        unset($param['tpl_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('msg_template')->where('tpl_id', $tpl_id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['tpl_id'] = $tpl_id;

        return $param;
    }

    public static function dele($tpl_id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('msg_template')->where('tpl_id', $tpl_id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['tpl_id' => $tpl_id];
    }

    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('msg_template')->where('tpl_id', $param['tpl_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
