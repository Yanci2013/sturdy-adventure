<?php
/*
 * @Description  : 充值订单
 */

namespace app\admin\service;

use think\facade\Db;

class RechargeService
{
    /**
     * 充值订单列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'recharge_id,order_no,member_name,money,pay_type,status,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'recharge_id' => 'desc'];
        }

        $count = Db::name('recharge')->where($where)->count('recharge_id');

        $list = Db::name('recharge')
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
     * 充值订单信息
     */
    public static function info($id)
    {
        $info = Db::name('recharge')->where('recharge_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 充值订单添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['recharge_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('recharge')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['recharge_id'] = $id;

        return $param;
    }

    /**
     * 充值订单修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['recharge_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['recharge_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('recharge')->where('recharge_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['recharge_id'] = $id;

        return $param;
    }

    /**
     * 充值订单删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('recharge')->where('recharge_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['recharge_id' => $id];
    }

    /**
     * 充值订单是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('recharge')->where('recharge_id', $param['recharge_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
