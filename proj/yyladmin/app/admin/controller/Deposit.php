<?php
/*
 * @Description  : 保证金记录
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\DepositService;

class Deposit
{
    /**
     * 保证金记录列表
     */
    public function depositList()
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

        $data = DepositService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 保证金记录信息
     */
    public function depositInfo()
    {
        $param['deposit_id'] = Request::param('deposit_id/d', '');

        $data = DepositService::info($param['deposit_id']);

        return success($data);
    }

    /**
     * 保证金记录添加
     */
    public function depositAdd()
    {
        if (Request::isGet()) {
            $data = DepositService::add();
        } else {
            $param['player_name'] = Request::param('player_name/s', '');
            $param['type'] = Request::param('type/d', 0);
            $param['money'] = Request::param('money/d', 0);
            $param['before_money'] = Request::param('before_money/d', 0);
            $param['after_money'] = Request::param('after_money/d', 0);
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = DepositService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 保证金记录修改
     */
    public function depositEdit()
    {
        $param['deposit_id'] = Request::param('deposit_id/d', '');

        if (Request::isGet()) {
            $data = DepositService::edit($param);
        } else {
            $param['player_name'] = Request::param('player_name/s', '');
            $param['type'] = Request::param('type/d', 0);
            $param['money'] = Request::param('money/d', 0);
            $param['before_money'] = Request::param('before_money/d', 0);
            $param['after_money'] = Request::param('after_money/d', 0);
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = DepositService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 保证金记录删除
     */
    public function depositDele()
    {
        $param['deposit_id'] = Request::param('deposit_id/d', '');

        $data = DepositService::dele($param['deposit_id']);

        return success($data);
    }

    /**
     * 保证金记录是否禁用
     */
    public function depositDisable()
    {
        $param['deposit_id']      = Request::param('deposit_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = DepositService::disable($param);

        return success($data);
    }
}
