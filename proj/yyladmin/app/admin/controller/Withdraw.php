<?php
/*
 * @Description  : 提现记录
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\WithdrawService;

class Withdraw
{
    /**
     * 提现记录列表
     */
    public function withdrawList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('member_name/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['member_name', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = WithdrawService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 提现记录信息
     */
    public function withdrawInfo()
    {
        $param['withdraw_id'] = Request::param('withdraw_id/d', '');

        $data = WithdrawService::info($param['withdraw_id']);

        return success($data);
    }

    /**
     * 提现记录添加
     */
    public function withdrawAdd()
    {
        if (Request::isGet()) {
            $data = WithdrawService::add();
        } else {
            $param['member_name'] = Request::param('member_name/s', '');
            $param['money'] = Request::param('money/d', 0);
            $param['fee'] = Request::param('fee/d', 0);
            $param['real_money'] = Request::param('real_money/d', 0);
            $param['account'] = Request::param('account/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = WithdrawService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 提现记录修改
     */
    public function withdrawEdit()
    {
        $param['withdraw_id'] = Request::param('withdraw_id/d', '');

        if (Request::isGet()) {
            $data = WithdrawService::edit($param);
        } else {
            $param['member_name'] = Request::param('member_name/s', '');
            $param['money'] = Request::param('money/d', 0);
            $param['fee'] = Request::param('fee/d', 0);
            $param['real_money'] = Request::param('real_money/d', 0);
            $param['account'] = Request::param('account/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = WithdrawService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 提现记录删除
     */
    public function withdrawDele()
    {
        $param['withdraw_id'] = Request::param('withdraw_id/d', '');

        $data = WithdrawService::dele($param['withdraw_id']);

        return success($data);
    }

    /**
     * 提现记录是否禁用
     */
    public function withdrawDisable()
    {
        $param['withdraw_id']      = Request::param('withdraw_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = WithdrawService::disable($param);

        return success($data);
    }
}
