<?php
/*
 * @Description  : 订单统计
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\OrderStatService;

class OrderStat
{
    /**
     * 订单统计列表
     */
    public function orderStatList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('stat_date/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['stat_date', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = OrderStatService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 订单统计信息
     */
    public function orderStatInfo()
    {
        $param['stat_id'] = Request::param('stat_id/d', '');

        $data = OrderStatService::info($param['stat_id']);

        return success($data);
    }

    /**
     * 订单统计添加
     */
    public function orderStatAdd()
    {
        if (Request::isGet()) {
            $data = OrderStatService::add();
        } else {
            $param['stat_date'] = Request::param('stat_date/s', '');
            $param['order_count'] = Request::param('order_count/d', 0);
            $param['order_amount'] = Request::param('order_amount/d', 0);
            $param['finish_count'] = Request::param('finish_count/d', 0);
            $param['cancel_count'] = Request::param('cancel_count/d', 0);

            $data = OrderStatService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 订单统计修改
     */
    public function orderStatEdit()
    {
        $param['stat_id'] = Request::param('stat_id/d', '');

        if (Request::isGet()) {
            $data = OrderStatService::edit($param);
        } else {
            $param['stat_date'] = Request::param('stat_date/s', '');
            $param['order_count'] = Request::param('order_count/d', 0);
            $param['order_amount'] = Request::param('order_amount/d', 0);
            $param['finish_count'] = Request::param('finish_count/d', 0);
            $param['cancel_count'] = Request::param('cancel_count/d', 0);

            $data = OrderStatService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 订单统计删除
     */
    public function orderStatDele()
    {
        $param['stat_id'] = Request::param('stat_id/d', '');

        $data = OrderStatService::dele($param['stat_id']);

        return success($data);
    }

    /**
     * 订单统计是否禁用
     */
    public function orderStatDisable()
    {
        $param['stat_id']      = Request::param('stat_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = OrderStatService::disable($param);

        return success($data);
    }
}
