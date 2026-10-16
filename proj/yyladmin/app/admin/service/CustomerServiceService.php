<?php
/*
 * @Description  : 客服管理
 */

namespace app\admin\service;

use think\facade\Db;

class CustomerServiceService
{
    /**
     * 客服列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'cs_id,name,type,avatar,phone,wechat,account,is_online,sort,remark,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'cs_id' => 'desc'];
        }

        $count = Db::name('customer_service')->where($where)->count('cs_id');

        $list = Db::name('customer_service')
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
     * 客服信息
     */
    public static function info($cs_id)
    {
        $cs = Db::name('customer_service')->where('cs_id', $cs_id)->find();

        if (empty($cs)) {
            exception('客服不存在：' . $cs_id);
        }

        return $cs;
    }

    /**
     * 客服添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['cs_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $cs_id = Db::name('customer_service')->insertGetId($param);

        if (empty($cs_id)) {
            exception();
        }

        $param['cs_id'] = $cs_id;

        return $param;
    }

    /**
     * 客服修改
     */
    public static function edit($param, $method = 'get')
    {
        $cs_id = $param['cs_id'];

        if ($method == 'get') {
            return self::info($cs_id);
        }

        unset($param['cs_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('customer_service')->where('cs_id', $cs_id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['cs_id'] = $cs_id;

        return $param;
    }

    /**
     * 客服删除
     */
    public static function dele($cs_id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('customer_service')->where('cs_id', $cs_id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['cs_id' => $cs_id];
    }

    /**
     * 客服是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('customer_service')->where('cs_id', $param['cs_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
