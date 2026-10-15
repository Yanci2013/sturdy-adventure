<?php
/*
 * @Description  : 保证金记录
 */

namespace app\admin\service;

use think\facade\Db;

class DepositService
{
    /**
     * 保证金记录列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'deposit_id,player_name,type,money,before_money,after_money,status,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'deposit_id' => 'desc'];
        }

        $count = Db::name('deposit')->where($where)->count('deposit_id');

        $list = Db::name('deposit')
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
     * 保证金记录信息
     */
    public static function info($id)
    {
        $info = Db::name('deposit')->where('deposit_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 保证金记录添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['deposit_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('deposit')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['deposit_id'] = $id;

        return $param;
    }

    /**
     * 保证金记录修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['deposit_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['deposit_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('deposit')->where('deposit_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['deposit_id'] = $id;

        return $param;
    }

    /**
     * 保证金记录删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('deposit')->where('deposit_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['deposit_id' => $id];
    }

    /**
     * 保证金记录是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('deposit')->where('deposit_id', $param['deposit_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
