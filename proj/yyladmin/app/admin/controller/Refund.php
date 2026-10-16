<?php
/*
 * @Description  : 退款管理
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\RefundService;

class Refund
{
    /**
     * 退款管理列表
     */
    public function refundList()
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

        $data = RefundService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 退款管理信息
     */
    public function refundInfo()
    {
        $param['refund_id'] = Request::param('refund_id/d', '');

        $data = RefundService::info($param['refund_id']);

        return success($data);
    }

    /**
     * 退款管理添加
     */
    public function refundAdd()
    {
        if (Request::isGet()) {
            $data = RefundService::add();
        } else {
            $param['order_no'] = Request::param('order_no/s', '');
            $param['member_name'] = Request::param('member_name/s', '');
            $param['refund_money'] = Request::param('refund_money/d', 0);
            $param['reason'] = Request::param('reason/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = RefundService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 退款管理修改
     */
    public function refundEdit()
    {
        $param['refund_id'] = Request::param('refund_id/d', '');

        if (Request::isGet()) {
            $data = RefundService::edit($param);
        } else {
            $param['order_no'] = Request::param('order_no/s', '');
            $param['member_name'] = Request::param('member_name/s', '');
            $param['refund_money'] = Request::param('refund_money/d', 0);
            $param['reason'] = Request::param('reason/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = RefundService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 退款管理删除
     */
    public function refundDele()
    {
        $param['refund_id'] = Request::param('refund_id/d', '');

        $data = RefundService::dele($param['refund_id']);

        return success($data);
    }

    /**
     * 退款管理是否禁用
     */
    public function refundDisable()
    {
        $param['refund_id']      = Request::param('refund_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = RefundService::disable($param);

        return success($data);
    }
}
