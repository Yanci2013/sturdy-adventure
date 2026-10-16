<?php
/*
 * @Description  : 退款管理
 */

namespace app\admin\service;

use think\facade\Db;

class RefundService
{
    /**
     * 退款管理列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'refund_id,order_no,member_name,refund_money,reason,status,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'refund_id' => 'desc'];
        }

        $count = Db::name('refund')->where($where)->count('refund_id');

        $list = Db::name('refund')
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
     * 退款管理信息
     */
    public static function info($id)
    {
        $info = Db::name('refund')->where('refund_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 退款管理添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['refund_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('refund')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['refund_id'] = $id;

        return $param;
    }

    /**
     * 退款管理修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['refund_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['refund_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('refund')->where('refund_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['refund_id'] = $id;

        return $param;
    }

    /**
     * 退款管理删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('refund')->where('refund_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['refund_id' => $id];
    }

    /**
     * 退款管理是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('refund')->where('refund_id', $param['refund_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
