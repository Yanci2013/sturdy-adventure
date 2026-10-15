<?php
/*
 * @Description  : 订单投诉
 */

namespace app\admin\service;

use think\facade\Db;

class OrderComplaintService
{
    /**
     * 订单投诉列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'complaint_id,order_no,member_name,player_name,content,reply,status,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'complaint_id' => 'desc'];
        }

        $count = Db::name('order_complaint')->where($where)->count('complaint_id');

        $list = Db::name('order_complaint')
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
     * 订单投诉信息
     */
    public static function info($id)
    {
        $info = Db::name('order_complaint')->where('complaint_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 订单投诉添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['complaint_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('order_complaint')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['complaint_id'] = $id;

        return $param;
    }

    /**
     * 订单投诉修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['complaint_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['complaint_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('order_complaint')->where('complaint_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['complaint_id'] = $id;

        return $param;
    }

    /**
     * 订单投诉删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('order_complaint')->where('complaint_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['complaint_id' => $id];
    }

    /**
     * 订单投诉是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('order_complaint')->where('complaint_id', $param['complaint_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
