<?php
/*
 * @Description  : 风控规则配置
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\RiskRuleService;

class RiskRule
{
    /**
     * 风控规则配置列表
     */
    public function riskRuleList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('rule_name/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['rule_name', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = RiskRuleService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 风控规则配置信息
     */
    public function riskRuleInfo()
    {
        $param['rule_id'] = Request::param('rule_id/d', '');

        $data = RiskRuleService::info($param['rule_id']);

        return success($data);
    }

    /**
     * 风控规则配置添加
     */
    public function riskRuleAdd()
    {
        if (Request::isGet()) {
            $data = RiskRuleService::add();
        } else {
            $param['rule_name'] = Request::param('rule_name/s', '');
            $param['rule_type'] = Request::param('rule_type/s', '');
            $param['threshold'] = Request::param('threshold/d', 0);
            $param['action'] = Request::param('action/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = RiskRuleService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 风控规则配置修改
     */
    public function riskRuleEdit()
    {
        $param['rule_id'] = Request::param('rule_id/d', '');

        if (Request::isGet()) {
            $data = RiskRuleService::edit($param);
        } else {
            $param['rule_name'] = Request::param('rule_name/s', '');
            $param['rule_type'] = Request::param('rule_type/s', '');
            $param['threshold'] = Request::param('threshold/d', 0);
            $param['action'] = Request::param('action/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = RiskRuleService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 风控规则配置删除
     */
    public function riskRuleDele()
    {
        $param['rule_id'] = Request::param('rule_id/d', '');

        $data = RiskRuleService::dele($param['rule_id']);

        return success($data);
    }

    /**
     * 风控规则配置是否禁用
     */
    public function riskRuleDisable()
    {
        $param['rule_id']      = Request::param('rule_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = RiskRuleService::disable($param);

        return success($data);
    }
}
