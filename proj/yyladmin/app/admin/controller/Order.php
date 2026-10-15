<?php
/*
 * @Description  : 订单管理
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\OrderService;

class Order
{
    /**
     * 订单管理列表
     */
    public function orderList()
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

        $data = OrderService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 订单管理信息
     */
    public function orderInfo()
    {
        $param['order_id'] = Request::param('order_id/d', '');

        $data = OrderService::info($param['order_id']);

        return success($data);
    }

    /**
     * 订单管理添加
     */
    public function orderAdd()
    {
        if (Request::isGet()) {
            $data = OrderService::add();
        } else {
            $param['order_no'] = Request::param('order_no/s', '');
            $param['member_name'] = Request::param('member_name/s', '');
            $param['player_name'] = Request::param('player_name/s', '');
            $param['goods_name'] = Request::param('goods_name/s', '');
            $param['amount'] = Request::param('amount/d', 0);
            $param['commission'] = Request::param('commission/d', 0);
            $param['status'] = Request::param('status/d', 0);
            $param['pay_type'] = Request::param('pay_type/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = OrderService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 订单管理修改
     */
    public function orderEdit()
    {
        $param['order_id'] = Request::param('order_id/d', '');

        if (Request::isGet()) {
            $data = OrderService::edit($param);
        } else {
            $param['order_no'] = Request::param('order_no/s', '');
            $param['member_name'] = Request::param('member_name/s', '');
            $param['player_name'] = Request::param('player_name/s', '');
            $param['goods_name'] = Request::param('goods_name/s', '');
            $param['amount'] = Request::param('amount/d', 0);
            $param['commission'] = Request::param('commission/d', 0);
            $param['status'] = Request::param('status/d', 0);
            $param['pay_type'] = Request::param('pay_type/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = OrderService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 订单管理删除
     */
    public function orderDele()
    {
        $param['order_id'] = Request::param('order_id/d', '');

        $data = OrderService::dele($param['order_id']);

        return success($data);
    }

    /**
     * 订单管理是否禁用
     */
    public function orderDisable()
    {
        $param['order_id']      = Request::param('order_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = OrderService::disable($param);

        return success($data);
    }
}
