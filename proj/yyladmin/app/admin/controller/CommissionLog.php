<?php
/*
 * @Description  : 佣金明细
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\CommissionLogService;

class CommissionLog
{
    /**
     * 佣金明细列表
     */
    public function clogList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('player_name/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['player_name', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = CommissionLogService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 佣金明细信息
     */
    public function clogInfo()
    {
        $param['clog_id'] = Request::param('clog_id/d', '');

        $data = CommissionLogService::info($param['clog_id']);

        return success($data);
    }

    /**
     * 佣金明细添加
     */
    public function clogAdd()
    {
        if (Request::isGet()) {
            $data = CommissionLogService::add();
        } else {
            $param['player_name'] = Request::param('player_name/s', '');
            $param['type'] = Request::param('type/d', 0);
            $param['money'] = Request::param('money/d', 0);
            $param['before_money'] = Request::param('before_money/d', 0);
            $param['after_money'] = Request::param('after_money/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = CommissionLogService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 佣金明细修改
     */
    public function clogEdit()
    {
        $param['clog_id'] = Request::param('clog_id/d', '');

        if (Request::isGet()) {
            $data = CommissionLogService::edit($param);
        } else {
            $param['player_name'] = Request::param('player_name/s', '');
            $param['type'] = Request::param('type/d', 0);
            $param['money'] = Request::param('money/d', 0);
            $param['before_money'] = Request::param('before_money/d', 0);
            $param['after_money'] = Request::param('after_money/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = CommissionLogService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 佣金明细删除
     */
    public function clogDele()
    {
        $param['clog_id'] = Request::param('clog_id/d', '');

        $data = CommissionLogService::dele($param['clog_id']);

        return success($data);
    }

    /**
     * 佣金明细是否禁用
     */
    public function clogDisable()
    {
        $param['clog_id']      = Request::param('clog_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = CommissionLogService::disable($param);

        return success($data);
    }
}
