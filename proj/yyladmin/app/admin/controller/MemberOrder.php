<?php
/*
 * @Description  : 会员订单
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\MemberOrderService;

class MemberOrder
{
    /**
     * 会员订单列表
     */
    public function morderList()
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

        $data = MemberOrderService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 会员订单信息
     */
    public function morderInfo()
    {
        $param['morder_id'] = Request::param('morder_id/d', '');

        $data = MemberOrderService::info($param['morder_id']);

        return success($data);
    }

    /**
     * 会员订单添加
     */
    public function morderAdd()
    {
        if (Request::isGet()) {
            $data = MemberOrderService::add();
        } else {
            $param['order_no'] = Request::param('order_no/s', '');
            $param['member_name'] = Request::param('member_name/s', '');
            $param['level_name'] = Request::param('level_name/s', '');
            $param['money'] = Request::param('money/d', 0);
            $param['months'] = Request::param('months/d', 0);
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = MemberOrderService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 会员订单修改
     */
    public function morderEdit()
    {
        $param['morder_id'] = Request::param('morder_id/d', '');

        if (Request::isGet()) {
            $data = MemberOrderService::edit($param);
        } else {
            $param['order_no'] = Request::param('order_no/s', '');
            $param['member_name'] = Request::param('member_name/s', '');
            $param['level_name'] = Request::param('level_name/s', '');
            $param['money'] = Request::param('money/d', 0);
            $param['months'] = Request::param('months/d', 0);
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = MemberOrderService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 会员订单删除
     */
    public function morderDele()
    {
        $param['morder_id'] = Request::param('morder_id/d', '');

        $data = MemberOrderService::dele($param['morder_id']);

        return success($data);
    }

    /**
     * 会员订单是否禁用
     */
    public function morderDisable()
    {
        $param['morder_id']      = Request::param('morder_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = MemberOrderService::disable($param);

        return success($data);
    }
}
