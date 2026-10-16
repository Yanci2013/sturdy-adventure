<?php
/*
 * @Description  : 风险账号
 */

namespace app\admin\service;

use think\facade\Db;

class RiskAccountService
{
    /**
     * 风险账号列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'risk_id,member_name,risk_level,risk_reason,handle_status,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'risk_id' => 'desc'];
        }

        $count = Db::name('risk_account')->where($where)->count('risk_id');

        $list = Db::name('risk_account')
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
     * 风险账号信息
     */
    public static function info($id)
    {
        $info = Db::name('risk_account')->where('risk_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 风险账号添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['risk_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('risk_account')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['risk_id'] = $id;

        return $param;
    }

    /**
     * 风险账号修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['risk_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['risk_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('risk_account')->where('risk_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['risk_id'] = $id;

        return $param;
    }

    /**
     * 风险账号删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('risk_account')->where('risk_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['risk_id' => $id];
    }

    /**
     * 风险账号是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('risk_account')->where('risk_id', $param['risk_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
