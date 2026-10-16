<?php
/*
 * @Description  : 财务报表
 */

namespace app\admin\service;

use think\facade\Db;

class FinanceReportService
{
    /**
     * 财务报表列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'report_id,report_date,income,expense,refund,profit,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'report_id' => 'desc'];
        }

        $count = Db::name('finance_report')->where($where)->count('report_id');

        $list = Db::name('finance_report')
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
     * 财务报表信息
     */
    public static function info($id)
    {
        $info = Db::name('finance_report')->where('report_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 财务报表添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['report_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('finance_report')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['report_id'] = $id;

        return $param;
    }

    /**
     * 财务报表修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['report_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['report_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('finance_report')->where('report_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['report_id'] = $id;

        return $param;
    }

    /**
     * 财务报表删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('finance_report')->where('report_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['report_id' => $id];
    }

    /**
     * 财务报表是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('finance_report')->where('report_id', $param['report_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
