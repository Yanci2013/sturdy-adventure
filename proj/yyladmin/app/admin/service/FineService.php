<?php
/*
 * @Description  : 罚款管理
 */

namespace app\admin\service;

use think\facade\Db;

class FineService
{
    /**
     * 罚款管理列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'fine_id,player_name,money,reason,status,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'fine_id' => 'desc'];
        }

        $count = Db::name('fine')->where($where)->count('fine_id');

        $list = Db::name('fine')
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
     * 罚款管理信息
     */
    public static function info($id)
    {
        $info = Db::name('fine')->where('fine_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 罚款管理添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['fine_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('fine')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['fine_id'] = $id;

        return $param;
    }

    /**
     * 罚款管理修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['fine_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['fine_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('fine')->where('fine_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['fine_id'] = $id;

        return $param;
    }

    /**
     * 罚款管理删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('fine')->where('fine_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['fine_id' => $id];
    }

    /**
     * 罚款管理是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('fine')->where('fine_id', $param['fine_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
