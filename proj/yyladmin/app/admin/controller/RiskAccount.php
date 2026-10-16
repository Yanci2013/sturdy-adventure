<?php
/*
 * @Description  : 风险账号
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\RiskAccountService;

class RiskAccount
{
    /**
     * 风险账号列表
     */
    public function riskAccountList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('member_name/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['member_name', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = RiskAccountService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 风险账号信息
     */
    public function riskAccountInfo()
    {
        $param['risk_id'] = Request::param('risk_id/d', '');

        $data = RiskAccountService::info($param['risk_id']);

        return success($data);
    }

    /**
     * 风险账号添加
     */
    public function riskAccountAdd()
    {
        if (Request::isGet()) {
            $data = RiskAccountService::add();
        } else {
            $param['member_name'] = Request::param('member_name/s', '');
            $param['risk_level'] = Request::param('risk_level/d', 0);
            $param['risk_reason'] = Request::param('risk_reason/s', '');
            $param['handle_status'] = Request::param('handle_status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = RiskAccountService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 风险账号修改
     */
    public function riskAccountEdit()
    {
        $param['risk_id'] = Request::param('risk_id/d', '');

        if (Request::isGet()) {
            $data = RiskAccountService::edit($param);
        } else {
            $param['member_name'] = Request::param('member_name/s', '');
            $param['risk_level'] = Request::param('risk_level/d', 0);
            $param['risk_reason'] = Request::param('risk_reason/s', '');
            $param['handle_status'] = Request::param('handle_status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = RiskAccountService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 风险账号删除
     */
    public function riskAccountDele()
    {
        $param['risk_id'] = Request::param('risk_id/d', '');

        $data = RiskAccountService::dele($param['risk_id']);

        return success($data);
    }

    /**
     * 风险账号是否禁用
     */
    public function riskAccountDisable()
    {
        $param['risk_id']      = Request::param('risk_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = RiskAccountService::disable($param);

        return success($data);
    }
}
