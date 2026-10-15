<?php
/*
 * @Description  : 佣金明细
 */

namespace app\admin\service;

use think\facade\Db;

class CommissionLogService
{
    /**
     * 佣金明细列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'clog_id,player_name,type,money,before_money,after_money,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'clog_id' => 'desc'];
        }

        $count = Db::name('commission_log')->where($where)->count('clog_id');

        $list = Db::name('commission_log')
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
     * 佣金明细信息
     */
    public static function info($id)
    {
        $info = Db::name('commission_log')->where('clog_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 佣金明细添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['clog_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('commission_log')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['clog_id'] = $id;

        return $param;
    }

    /**
     * 佣金明细修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['clog_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['clog_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('commission_log')->where('clog_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['clog_id'] = $id;

        return $param;
    }

    /**
     * 佣金明细删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('commission_log')->where('clog_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['clog_id' => $id];
    }

    /**
     * 佣金明细是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('commission_log')->where('clog_id', $param['clog_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
