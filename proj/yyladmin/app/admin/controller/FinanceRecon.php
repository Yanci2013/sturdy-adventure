<?php
/*
 * @Description  : 财务对账
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\FinanceReconService;

class FinanceRecon
{
    /**
     * 财务对账列表
     */
    public function financeReconList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('recon_date/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['recon_date', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = FinanceReconService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 财务对账信息
     */
    public function financeReconInfo()
    {
        $param['recon_id'] = Request::param('recon_id/d', '');

        $data = FinanceReconService::info($param['recon_id']);

        return success($data);
    }

    /**
     * 财务对账添加
     */
    public function financeReconAdd()
    {
        if (Request::isGet()) {
            $data = FinanceReconService::add();
        } else {
            $param['recon_date'] = Request::param('recon_date/s', '');
            $param['order_amount'] = Request::param('order_amount/d', 0);
            $param['refund_amount'] = Request::param('refund_amount/d', 0);
            $param['commission'] = Request::param('commission/d', 0);
            $param['settle_amount'] = Request::param('settle_amount/d', 0);
            $param['status'] = Request::param('status/d', 0);

            $data = FinanceReconService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 财务对账修改
     */
    public function financeReconEdit()
    {
        $param['recon_id'] = Request::param('recon_id/d', '');

        if (Request::isGet()) {
            $data = FinanceReconService::edit($param);
        } else {
            $param['recon_date'] = Request::param('recon_date/s', '');
            $param['order_amount'] = Request::param('order_amount/d', 0);
            $param['refund_amount'] = Request::param('refund_amount/d', 0);
            $param['commission'] = Request::param('commission/d', 0);
            $param['settle_amount'] = Request::param('settle_amount/d', 0);
            $param['status'] = Request::param('status/d', 0);

            $data = FinanceReconService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 财务对账删除
     */
    public function financeReconDele()
    {
        $param['recon_id'] = Request::param('recon_id/d', '');

        $data = FinanceReconService::dele($param['recon_id']);

        return success($data);
    }

    /**
     * 财务对账是否禁用
     */
    public function financeReconDisable()
    {
        $param['recon_id']      = Request::param('recon_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = FinanceReconService::disable($param);

        return success($data);
    }
}
