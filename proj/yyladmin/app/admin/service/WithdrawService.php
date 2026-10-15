<?php
/*
 * @Description  : 提现记录
 */

namespace app\admin\service;

use think\facade\Db;

class WithdrawService
{
    /**
     * 提现记录列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'withdraw_id,member_name,money,fee,real_money,account,status,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'withdraw_id' => 'desc'];
        }

        $count = Db::name('withdraw')->where($where)->count('withdraw_id');

        $list = Db::name('withdraw')
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
     * 提现记录信息
     */
    public static function info($id)
    {
        $info = Db::name('withdraw')->where('withdraw_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 提现记录添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['withdraw_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('withdraw')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['withdraw_id'] = $id;

        return $param;
    }

    /**
     * 提现记录修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['withdraw_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['withdraw_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('withdraw')->where('withdraw_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['withdraw_id'] = $id;

        return $param;
    }

    /**
     * 提现记录删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('withdraw')->where('withdraw_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['withdraw_id' => $id];
    }

    /**
     * 提现记录是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('withdraw')->where('withdraw_id', $param['withdraw_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
