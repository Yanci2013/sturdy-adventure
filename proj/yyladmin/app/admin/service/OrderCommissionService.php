<?php
/*
 * @Description  : 订单佣金
 */

namespace app\admin\service;

use think\facade\Db;

class OrderCommissionService
{
    /**
     * 订单佣金列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'commission_id,order_no,player_name,order_amount,rate,commission,status,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'commission_id' => 'desc'];
        }

        $count = Db::name('order_commission')->where($where)->count('commission_id');

        $list = Db::name('order_commission')
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
     * 订单佣金信息
     */
    public static function info($id)
    {
        $info = Db::name('order_commission')->where('commission_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 订单佣金添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['commission_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('order_commission')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['commission_id'] = $id;

        return $param;
    }

    /**
     * 订单佣金修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['commission_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['commission_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('order_commission')->where('commission_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['commission_id'] = $id;

        return $param;
    }

    /**
     * 订单佣金删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('order_commission')->where('commission_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['commission_id' => $id];
    }

    /**
     * 订单佣金是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('order_commission')->where('commission_id', $param['commission_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
