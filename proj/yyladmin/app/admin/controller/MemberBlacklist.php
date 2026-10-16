<?php
/*
 * @Description  : 会员黑名单
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\MemberBlacklistService;

class MemberBlacklist
{
    /**
     * 会员黑名单列表
     */
    public function memberBlacklistList()
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

        $data = MemberBlacklistService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 会员黑名单信息
     */
    public function memberBlacklistInfo()
    {
        $param['black_id'] = Request::param('black_id/d', '');

        $data = MemberBlacklistService::info($param['black_id']);

        return success($data);
    }

    /**
     * 会员黑名单添加
     */
    public function memberBlacklistAdd()
    {
        if (Request::isGet()) {
            $data = MemberBlacklistService::add();
        } else {
            $param['member_name'] = Request::param('member_name/s', '');
            $param['phone'] = Request::param('phone/s', '');
            $param['reason'] = Request::param('reason/s', '');
            $param['operator'] = Request::param('operator/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = MemberBlacklistService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 会员黑名单修改
     */
    public function memberBlacklistEdit()
    {
        $param['black_id'] = Request::param('black_id/d', '');

        if (Request::isGet()) {
            $data = MemberBlacklistService::edit($param);
        } else {
            $param['member_name'] = Request::param('member_name/s', '');
            $param['phone'] = Request::param('phone/s', '');
            $param['reason'] = Request::param('reason/s', '');
            $param['operator'] = Request::param('operator/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = MemberBlacklistService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 会员黑名单删除
     */
    public function memberBlacklistDele()
    {
        $param['black_id'] = Request::param('black_id/d', '');

        $data = MemberBlacklistService::dele($param['black_id']);

        return success($data);
    }

    /**
     * 会员黑名单是否禁用
     */
    public function memberBlacklistDisable()
    {
        $param['black_id']      = Request::param('black_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = MemberBlacklistService::disable($param);

        return success($data);
    }
}
