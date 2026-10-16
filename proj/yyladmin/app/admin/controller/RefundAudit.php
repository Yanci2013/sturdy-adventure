<?php
/*
 * @Description  : 退款审核
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\RefundAuditService;

class RefundAudit
{
    /**
     * 退款审核列表
     */
    public function refundAuditList()
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

        $data = RefundAuditService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 退款审核信息
     */
    public function refundAuditInfo()
    {
        $param['audit_id'] = Request::param('audit_id/d', '');

        $data = RefundAuditService::info($param['audit_id']);

        return success($data);
    }

    /**
     * 退款审核添加
     */
    public function refundAuditAdd()
    {
        if (Request::isGet()) {
            $data = RefundAuditService::add();
        } else {
            $param['order_no'] = Request::param('order_no/s', '');
            $param['member_name'] = Request::param('member_name/s', '');
            $param['refund_money'] = Request::param('refund_money/d', 0);
            $param['reason'] = Request::param('reason/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = RefundAuditService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 退款审核修改
     */
    public function refundAuditEdit()
    {
        $param['audit_id'] = Request::param('audit_id/d', '');

        if (Request::isGet()) {
            $data = RefundAuditService::edit($param);
        } else {
            $param['order_no'] = Request::param('order_no/s', '');
            $param['member_name'] = Request::param('member_name/s', '');
            $param['refund_money'] = Request::param('refund_money/d', 0);
            $param['reason'] = Request::param('reason/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = RefundAuditService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 退款审核删除
     */
    public function refundAuditDele()
    {
        $param['audit_id'] = Request::param('audit_id/d', '');

        $data = RefundAuditService::dele($param['audit_id']);

        return success($data);
    }

    /**
     * 退款审核是否禁用
     */
    public function refundAuditDisable()
    {
        $param['audit_id']      = Request::param('audit_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = RefundAuditService::disable($param);

        return success($data);
    }
}
