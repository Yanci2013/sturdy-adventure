<?php
/*
 * @Description  : IP黑名单
 */

namespace app\admin\service;

use think\facade\Db;

class IpBlacklistService
{
    /**
     * IP黑名单列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'ip_id,ip,reason,operator,expire_time,status,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'ip_id' => 'desc'];
        }

        $count = Db::name('ip_blacklist')->where($where)->count('ip_id');

        $list = Db::name('ip_blacklist')
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
     * IP黑名单信息
     */
    public static function info($id)
    {
        $info = Db::name('ip_blacklist')->where('ip_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * IP黑名单添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['ip_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('ip_blacklist')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['ip_id'] = $id;

        return $param;
    }

    /**
     * IP黑名单修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['ip_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['ip_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('ip_blacklist')->where('ip_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['ip_id'] = $id;

        return $param;
    }

    /**
     * IP黑名单删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('ip_blacklist')->where('ip_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['ip_id' => $id];
    }

    /**
     * IP黑名单是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('ip_blacklist')->where('ip_id', $param['ip_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
