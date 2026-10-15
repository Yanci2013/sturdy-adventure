<?php
/*
 * @Description  : 充值订单
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\RechargeService;

class Recharge
{
    /**
     * 充值订单列表
     */
    public function rechargeList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('order_no/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['order_no', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = RechargeService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 充值订单信息
     */
    public function rechargeInfo()
    {
        $param['recharge_id'] = Request::param('recharge_id/d', '');

        $data = RechargeService::info($param['recharge_id']);

        return success($data);
    }

    /**
     * 充值订单添加
     */
    public function rechargeAdd()
    {
        if (Request::isGet()) {
            $data = RechargeService::add();
        } else {
            $param['order_no'] = Request::param('order_no/s', '');
            $param['member_name'] = Request::param('member_name/s', '');
            $param['money'] = Request::param('money/d', 0);
            $param['pay_type'] = Request::param('pay_type/d', 0);
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = RechargeService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 充值订单修改
     */
    public function rechargeEdit()
    {
        $param['recharge_id'] = Request::param('recharge_id/d', '');

        if (Request::isGet()) {
            $data = RechargeService::edit($param);
        } else {
            $param['order_no'] = Request::param('order_no/s', '');
            $param['member_name'] = Request::param('member_name/s', '');
            $param['money'] = Request::param('money/d', 0);
            $param['pay_type'] = Request::param('pay_type/d', 0);
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = RechargeService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 充值订单删除
     */
    public function rechargeDele()
    {
        $param['recharge_id'] = Request::param('recharge_id/d', '');

        $data = RechargeService::dele($param['recharge_id']);

        return success($data);
    }

    /**
     * 充值订单是否禁用
     */
    public function rechargeDisable()
    {
        $param['recharge_id']      = Request::param('recharge_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = RechargeService::disable($param);

        return success($data);
    }
}
