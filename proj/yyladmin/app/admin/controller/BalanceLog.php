<?php
/*
 * @Description  : 余额明细
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\BalanceLogService;

class BalanceLog
{
    /**
     * 余额明细列表
     */
    public function balanceLogList()
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

        $data = BalanceLogService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 余额明细信息
     */
    public function balanceLogInfo()
    {
        $param['log_id'] = Request::param('log_id/d', '');

        $data = BalanceLogService::info($param['log_id']);

        return success($data);
    }

    /**
     * 余额明细添加
     */
    public function balanceLogAdd()
    {
        if (Request::isGet()) {
            $data = BalanceLogService::add();
        } else {
            $param['member_name'] = Request::param('member_name/s', '');
            $param['type'] = Request::param('type/d', 0);
            $param['money'] = Request::param('money/d', 0);
            $param['before_money'] = Request::param('before_money/d', 0);
            $param['after_money'] = Request::param('after_money/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = BalanceLogService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 余额明细修改
     */
    public function balanceLogEdit()
    {
        $param['log_id'] = Request::param('log_id/d', '');

        if (Request::isGet()) {
            $data = BalanceLogService::edit($param);
        } else {
            $param['member_name'] = Request::param('member_name/s', '');
            $param['type'] = Request::param('type/d', 0);
            $param['money'] = Request::param('money/d', 0);
            $param['before_money'] = Request::param('before_money/d', 0);
            $param['after_money'] = Request::param('after_money/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = BalanceLogService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 余额明细删除
     */
    public function balanceLogDele()
    {
        $param['log_id'] = Request::param('log_id/d', '');

        $data = BalanceLogService::dele($param['log_id']);

        return success($data);
    }

    /**
     * 余额明细是否禁用
     */
    public function balanceLogDisable()
    {
        $param['log_id']      = Request::param('log_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = BalanceLogService::disable($param);

        return success($data);
    }
}
