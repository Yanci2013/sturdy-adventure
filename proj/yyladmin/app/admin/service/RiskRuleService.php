<?php
/*
 * @Description  : 风控规则配置
 */

namespace app\admin\service;

use think\facade\Db;

class RiskRuleService
{
    /**
     * 风控规则配置列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'rule_id,rule_name,rule_type,threshold,action,status,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'rule_id' => 'desc'];
        }

        $count = Db::name('risk_rule')->where($where)->count('rule_id');

        $list = Db::name('risk_rule')
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
     * 风控规则配置信息
     */
    public static function info($id)
    {
        $info = Db::name('risk_rule')->where('rule_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 风控规则配置添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['rule_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('risk_rule')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['rule_id'] = $id;

        return $param;
    }

    /**
     * 风控规则配置修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['rule_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['rule_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('risk_rule')->where('rule_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['rule_id'] = $id;

        return $param;
    }

    /**
     * 风控规则配置删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('risk_rule')->where('rule_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['rule_id' => $id];
    }

    /**
     * 风控规则配置是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('risk_rule')->where('rule_id', $param['rule_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
