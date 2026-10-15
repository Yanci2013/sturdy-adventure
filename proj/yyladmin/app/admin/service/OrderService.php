<?php
/*
 * @Description  : 订单管理
 */

namespace app\admin\service;

use think\facade\Db;

class OrderService
{
    /**
     * 订单管理列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'order_id,order_no,member_name,player_name,goods_name,amount,commission,status,pay_type,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'order_id' => 'desc'];
        }

        $count = Db::name('order')->where($where)->count('order_id');

        $list = Db::name('order')
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
     * 订单管理信息
     */
    public static function info($id)
    {
        $info = Db::name('order')->where('order_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 订单管理添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['order_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('order')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['order_id'] = $id;

        return $param;
    }

    /**
     * 订单管理修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['order_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['order_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('order')->where('order_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['order_id'] = $id;

        return $param;
    }

    /**
     * 订单管理删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('order')->where('order_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['order_id' => $id];
    }

    /**
     * 订单管理是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('order')->where('order_id', $param['order_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
