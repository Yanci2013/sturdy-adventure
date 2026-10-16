<?php
/*
 * @Description  : 风控拦截日志
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\RiskLogService;

class RiskLog
{
    /**
     * 风控拦截日志列表
     */
    public function riskLogList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('ip/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['ip', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = RiskLogService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 风控拦截日志信息
     */
    public function riskLogInfo()
    {
        $param['log_id'] = Request::param('log_id/d', '');

        $data = RiskLogService::info($param['log_id']);

        return success($data);
    }

    /**
     * 风控拦截日志添加
     */
    public function riskLogAdd()
    {
        if (Request::isGet()) {
            $data = RiskLogService::add();
        } else {
            $param['ip'] = Request::param('ip/s', '');
            $param['member_name'] = Request::param('member_name/s', '');
            $param['rule_name'] = Request::param('rule_name/s', '');
            $param['content'] = Request::param('content/s', '');
            $param['level'] = Request::param('level/d', 0);
            $param['status'] = Request::param('status/d', 0);

            $data = RiskLogService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 风控拦截日志修改
     */
    public function riskLogEdit()
    {
        $param['log_id'] = Request::param('log_id/d', '');

        if (Request::isGet()) {
            $data = RiskLogService::edit($param);
        } else {
            $param['ip'] = Request::param('ip/s', '');
            $param['member_name'] = Request::param('member_name/s', '');
            $param['rule_name'] = Request::param('rule_name/s', '');
            $param['content'] = Request::param('content/s', '');
            $param['level'] = Request::param('level/d', 0);
            $param['status'] = Request::param('status/d', 0);

            $data = RiskLogService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 风控拦截日志删除
     */
    public function riskLogDele()
    {
        $param['log_id'] = Request::param('log_id/d', '');

        $data = RiskLogService::dele($param['log_id']);

        return success($data);
    }

    /**
     * 风控拦截日志是否禁用
     */
    public function riskLogDisable()
    {
        $param['log_id']      = Request::param('log_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = RiskLogService::disable($param);

        return success($data);
    }
}
