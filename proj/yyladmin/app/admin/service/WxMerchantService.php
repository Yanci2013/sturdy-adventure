<?php
/*
 * @Description  : 微信商户
 */

namespace app\admin\service;

use think\facade\Db;

class WxMerchantService
{
    /**
     * 微信商户列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'merchant_id,merchant_name,mch_id,app_id,api_key,cert_path,status,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'merchant_id' => 'desc'];
        }

        $count = Db::name('wx_merchant')->where($where)->count('merchant_id');

        $list = Db::name('wx_merchant')
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
     * 微信商户信息
     */
    public static function info($id)
    {
        $info = Db::name('wx_merchant')->where('merchant_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 微信商户添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['merchant_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('wx_merchant')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['merchant_id'] = $id;

        return $param;
    }

    /**
     * 微信商户修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['merchant_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['merchant_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('wx_merchant')->where('merchant_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['merchant_id'] = $id;

        return $param;
    }

    /**
     * 微信商户删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('wx_merchant')->where('merchant_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['merchant_id' => $id];
    }

    /**
     * 微信商户是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('wx_merchant')->where('merchant_id', $param['merchant_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
