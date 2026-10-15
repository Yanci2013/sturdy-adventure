<?php
/*
 * @Description  : 订单佣金
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\OrderCommissionService;

class OrderCommission
{
    /**
     * 订单佣金列表
     */
    public function commissionList()
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

        $data = OrderCommissionService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 订单佣金信息
     */
    public function commissionInfo()
    {
        $param['commission_id'] = Request::param('commission_id/d', '');

        $data = OrderCommissionService::info($param['commission_id']);

        return success($data);
    }

    /**
     * 订单佣金添加
     */
    public function commissionAdd()
    {
        if (Request::isGet()) {
            $data = OrderCommissionService::add();
        } else {
            $param['order_no'] = Request::param('order_no/s', '');
            $param['player_name'] = Request::param('player_name/s', '');
            $param['order_amount'] = Request::param('order_amount/d', 0);
            $param['rate'] = Request::param('rate/d', 0);
            $param['commission'] = Request::param('commission/d', 0);
            $param['status'] = Request::param('status/d', 0);

            $data = OrderCommissionService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 订单佣金修改
     */
    public function commissionEdit()
    {
        $param['commission_id'] = Request::param('commission_id/d', '');

        if (Request::isGet()) {
            $data = OrderCommissionService::edit($param);
        } else {
            $param['order_no'] = Request::param('order_no/s', '');
            $param['player_name'] = Request::param('player_name/s', '');
            $param['order_amount'] = Request::param('order_amount/d', 0);
            $param['rate'] = Request::param('rate/d', 0);
            $param['commission'] = Request::param('commission/d', 0);
            $param['status'] = Request::param('status/d', 0);

            $data = OrderCommissionService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 订单佣金删除
     */
    public function commissionDele()
    {
        $param['commission_id'] = Request::param('commission_id/d', '');

        $data = OrderCommissionService::dele($param['commission_id']);

        return success($data);
    }

    /**
     * 订单佣金是否禁用
     */
    public function commissionDisable()
    {
        $param['commission_id']      = Request::param('commission_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = OrderCommissionService::disable($param);

        return success($data);
    }
}
