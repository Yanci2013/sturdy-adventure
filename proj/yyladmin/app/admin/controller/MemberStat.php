<?php
/*
 * @Description  : 会员统计
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\MemberStatService;

class MemberStat
{
    /**
     * 会员统计列表
     */
    public function memberStatList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('stat_date/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['stat_date', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = MemberStatService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 会员统计信息
     */
    public function memberStatInfo()
    {
        $param['stat_id'] = Request::param('stat_id/d', '');

        $data = MemberStatService::info($param['stat_id']);

        return success($data);
    }

    /**
     * 会员统计添加
     */
    public function memberStatAdd()
    {
        if (Request::isGet()) {
            $data = MemberStatService::add();
        } else {
            $param['stat_date'] = Request::param('stat_date/s', '');
            $param['total_member'] = Request::param('total_member/d', 0);
            $param['new_member'] = Request::param('new_member/d', 0);
            $param['active_member'] = Request::param('active_member/d', 0);
            $param['growth_rate'] = Request::param('growth_rate/d', 0);

            $data = MemberStatService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 会员统计修改
     */
    public function memberStatEdit()
    {
        $param['stat_id'] = Request::param('stat_id/d', '');

        if (Request::isGet()) {
            $data = MemberStatService::edit($param);
        } else {
            $param['stat_date'] = Request::param('stat_date/s', '');
            $param['total_member'] = Request::param('total_member/d', 0);
            $param['new_member'] = Request::param('new_member/d', 0);
            $param['active_member'] = Request::param('active_member/d', 0);
            $param['growth_rate'] = Request::param('growth_rate/d', 0);

            $data = MemberStatService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 会员统计删除
     */
    public function memberStatDele()
    {
        $param['stat_id'] = Request::param('stat_id/d', '');

        $data = MemberStatService::dele($param['stat_id']);

        return success($data);
    }

    /**
     * 会员统计是否禁用
     */
    public function memberStatDisable()
    {
        $param['stat_id']      = Request::param('stat_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = MemberStatService::disable($param);

        return success($data);
    }
}
