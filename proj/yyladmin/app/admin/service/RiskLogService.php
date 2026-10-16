<?php
/*
 * @Description  : 风控拦截日志
 */

namespace app\admin\service;

use think\facade\Db;

class RiskLogService
{
    /**
     * 风控拦截日志列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'log_id,ip,member_name,rule_name,content,level,status,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'log_id' => 'desc'];
        }

        $count = Db::name('risk_log')->where($where)->count('log_id');

        $list = Db::name('risk_log')
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
     * 风控拦截日志信息
     */
    public static function info($id)
    {
        $info = Db::name('risk_log')->where('log_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 风控拦截日志添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['log_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('risk_log')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['log_id'] = $id;

        return $param;
    }

    /**
     * 风控拦截日志修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['log_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['log_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('risk_log')->where('log_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['log_id'] = $id;

        return $param;
    }

    /**
     * 风控拦截日志删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('risk_log')->where('log_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['log_id' => $id];
    }

    /**
     * 风控拦截日志是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('risk_log')->where('log_id', $param['log_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
