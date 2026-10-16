<?php
/*
 * @Description  : 易支付
 */

namespace app\admin\service;

use think\facade\Db;

class EpayService
{
    /**
     * 易支付列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'epay_id,name,api_url,pid,key,status,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'epay_id' => 'desc'];
        }

        $count = Db::name('epay')->where($where)->count('epay_id');

        $list = Db::name('epay')
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
     * 易支付信息
     */
    public static function info($id)
    {
        $info = Db::name('epay')->where('epay_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 易支付添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['epay_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('epay')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['epay_id'] = $id;

        return $param;
    }

    /**
     * 易支付修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['epay_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['epay_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('epay')->where('epay_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['epay_id'] = $id;

        return $param;
    }

    /**
     * 易支付删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('epay')->where('epay_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['epay_id' => $id];
    }

    /**
     * 易支付是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('epay')->where('epay_id', $param['epay_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
