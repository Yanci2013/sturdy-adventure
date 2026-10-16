<?php
/*
 * @Description  : 财务报表
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\FinanceReportService;

class FinanceReport
{
    /**
     * 财务报表列表
     */
    public function financeReportList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('report_date/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['report_date', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = FinanceReportService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 财务报表信息
     */
    public function financeReportInfo()
    {
        $param['report_id'] = Request::param('report_id/d', '');

        $data = FinanceReportService::info($param['report_id']);

        return success($data);
    }

    /**
     * 财务报表添加
     */
    public function financeReportAdd()
    {
        if (Request::isGet()) {
            $data = FinanceReportService::add();
        } else {
            $param['report_date'] = Request::param('report_date/s', '');
            $param['income'] = Request::param('income/d', 0);
            $param['expense'] = Request::param('expense/d', 0);
            $param['refund'] = Request::param('refund/d', 0);
            $param['profit'] = Request::param('profit/d', 0);

            $data = FinanceReportService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 财务报表修改
     */
    public function financeReportEdit()
    {
        $param['report_id'] = Request::param('report_id/d', '');

        if (Request::isGet()) {
            $data = FinanceReportService::edit($param);
        } else {
            $param['report_date'] = Request::param('report_date/s', '');
            $param['income'] = Request::param('income/d', 0);
            $param['expense'] = Request::param('expense/d', 0);
            $param['refund'] = Request::param('refund/d', 0);
            $param['profit'] = Request::param('profit/d', 0);

            $data = FinanceReportService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 财务报表删除
     */
    public function financeReportDele()
    {
        $param['report_id'] = Request::param('report_id/d', '');

        $data = FinanceReportService::dele($param['report_id']);

        return success($data);
    }

    /**
     * 财务报表是否禁用
     */
    public function financeReportDisable()
    {
        $param['report_id']      = Request::param('report_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = FinanceReportService::disable($param);

        return success($data);
    }
}
