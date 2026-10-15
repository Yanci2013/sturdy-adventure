<?php
/*
 * @Description  : 余额明细
 */

namespace app\admin\service;

use think\facade\Db;

class BalanceLogService
{
    /**
     * 余额明细列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'log_id,member_name,type,money,before_money,after_money,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'log_id' => 'desc'];
        }

        $count = Db::name('balance_log')->where($where)->count('log_id');

        $list = Db::name('balance_log')
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
     * 余额明细信息
     */
    public static function info($id)
    {
        $info = Db::name('balance_log')->where('log_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 余额明细添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['log_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('balance_log')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['log_id'] = $id;

        return $param;
    }

    /**
     * 余额明细修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['log_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['log_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('balance_log')->where('log_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['log_id'] = $id;

        return $param;
    }

    /**
     * 余额明细删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('balance_log')->where('log_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['log_id' => $id];
    }

    /**
     * 余额明细是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('balance_log')->where('log_id', $param['log_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
