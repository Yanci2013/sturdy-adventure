<?php
/*
 * @Description  : 打手管理
 */

namespace app\admin\service;

use think\facade\Db;

class PlayerService
{
    /**
     * 打手管理列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'player_id,nickname,avatar,phone,account,level_name,score,balance,is_online,status,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'player_id' => 'desc'];
        }

        $count = Db::name('player')->where($where)->count('player_id');

        $list = Db::name('player')
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
     * 打手管理信息
     */
    public static function info($id)
    {
        $info = Db::name('player')->where('player_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 打手管理添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['player_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('player')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['player_id'] = $id;

        return $param;
    }

    /**
     * 打手管理修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['player_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['player_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('player')->where('player_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['player_id'] = $id;

        return $param;
    }

    /**
     * 打手管理删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('player')->where('player_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['player_id' => $id];
    }

    /**
     * 打手管理是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('player')->where('player_id', $param['player_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
