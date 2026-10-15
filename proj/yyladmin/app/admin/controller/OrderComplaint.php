<?php
/*
 * @Description  : 订单投诉
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\OrderComplaintService;

class OrderComplaint
{
    /**
     * 订单投诉列表
     */
    public function complaintList()
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

        $data = OrderComplaintService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 订单投诉信息
     */
    public function complaintInfo()
    {
        $param['complaint_id'] = Request::param('complaint_id/d', '');

        $data = OrderComplaintService::info($param['complaint_id']);

        return success($data);
    }

    /**
     * 订单投诉添加
     */
    public function complaintAdd()
    {
        if (Request::isGet()) {
            $data = OrderComplaintService::add();
        } else {
            $param['order_no'] = Request::param('order_no/s', '');
            $param['member_name'] = Request::param('member_name/s', '');
            $param['player_name'] = Request::param('player_name/s', '');
            $param['content'] = Request::param('content/s', '');
            $param['reply'] = Request::param('reply/s', '');
            $param['status'] = Request::param('status/d', 0);

            $data = OrderComplaintService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 订单投诉修改
     */
    public function complaintEdit()
    {
        $param['complaint_id'] = Request::param('complaint_id/d', '');

        if (Request::isGet()) {
            $data = OrderComplaintService::edit($param);
        } else {
            $param['order_no'] = Request::param('order_no/s', '');
            $param['member_name'] = Request::param('member_name/s', '');
            $param['player_name'] = Request::param('player_name/s', '');
            $param['content'] = Request::param('content/s', '');
            $param['reply'] = Request::param('reply/s', '');
            $param['status'] = Request::param('status/d', 0);

            $data = OrderComplaintService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 订单投诉删除
     */
    public function complaintDele()
    {
        $param['complaint_id'] = Request::param('complaint_id/d', '');

        $data = OrderComplaintService::dele($param['complaint_id']);

        return success($data);
    }

    /**
     * 订单投诉是否禁用
     */
    public function complaintDisable()
    {
        $param['complaint_id']      = Request::param('complaint_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = OrderComplaintService::disable($param);

        return success($data);
    }
}
