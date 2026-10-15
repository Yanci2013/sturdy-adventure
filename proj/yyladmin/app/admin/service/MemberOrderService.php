<?php
/*
 * @Description  : 会员订单
 */

namespace app\admin\service;

use think\facade\Db;

class MemberOrderService
{
    /**
     * 会员订单列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'morder_id,order_no,member_name,level_name,money,months,status,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'morder_id' => 'desc'];
        }

        $count = Db::name('member_order')->where($where)->count('morder_id');

        $list = Db::name('member_order')
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
     * 会员订单信息
     */
    public static function info($id)
    {
        $info = Db::name('member_order')->where('morder_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 会员订单添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['morder_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('member_order')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['morder_id'] = $id;

        return $param;
    }

    /**
     * 会员订单修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['morder_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['morder_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('member_order')->where('morder_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['morder_id'] = $id;

        return $param;
    }

    /**
     * 会员订单删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('member_order')->where('morder_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['morder_id' => $id];
    }

    /**
     * 会员订单是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('member_order')->where('morder_id', $param['morder_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
