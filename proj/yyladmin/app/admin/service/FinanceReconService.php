<?php
/*
 * @Description  : 财务对账
 */

namespace app\admin\service;

use think\facade\Db;

class FinanceReconService
{
    /**
     * 财务对账列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'recon_id,recon_date,order_amount,refund_amount,commission,settle_amount,status,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'recon_id' => 'desc'];
        }

        $count = Db::name('finance_recon')->where($where)->count('recon_id');

        $list = Db::name('finance_recon')
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
     * 财务对账信息
     */
    public static function info($id)
    {
        $info = Db::name('finance_recon')->where('recon_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 财务对账添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['recon_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('finance_recon')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['recon_id'] = $id;

        return $param;
    }

    /**
     * 财务对账修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['recon_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['recon_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('finance_recon')->where('recon_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['recon_id'] = $id;

        return $param;
    }

    /**
     * 财务对账删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('finance_recon')->where('recon_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['recon_id' => $id];
    }

    /**
     * 财务对账是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('finance_recon')->where('recon_id', $param['recon_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
