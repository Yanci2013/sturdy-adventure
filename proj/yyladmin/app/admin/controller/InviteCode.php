<?php
/*
 * @Description  : 邀请码管理
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\InviteCodeService;

class InviteCode
{
    /**
     * 邀请码管理列表
     */
    public function inviteCodeList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('code/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['code', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = InviteCodeService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 邀请码管理信息
     */
    public function inviteCodeInfo()
    {
        $param['code_id'] = Request::param('code_id/d', '');

        $data = InviteCodeService::info($param['code_id']);

        return success($data);
    }

    /**
     * 邀请码管理添加
     */
    public function inviteCodeAdd()
    {
        if (Request::isGet()) {
            $data = InviteCodeService::add();
        } else {
            $param['code'] = Request::param('code/s', '');
            $param['member_name'] = Request::param('member_name/s', '');
            $param['used_num'] = Request::param('used_num/d', 0);
            $param['max_num'] = Request::param('max_num/d', 0);
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = InviteCodeService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 邀请码管理修改
     */
    public function inviteCodeEdit()
    {
        $param['code_id'] = Request::param('code_id/d', '');

        if (Request::isGet()) {
            $data = InviteCodeService::edit($param);
        } else {
            $param['code'] = Request::param('code/s', '');
            $param['member_name'] = Request::param('member_name/s', '');
            $param['used_num'] = Request::param('used_num/d', 0);
            $param['max_num'] = Request::param('max_num/d', 0);
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = InviteCodeService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 邀请码管理删除
     */
    public function inviteCodeDele()
    {
        $param['code_id'] = Request::param('code_id/d', '');

        $data = InviteCodeService::dele($param['code_id']);

        return success($data);
    }

    /**
     * 邀请码管理是否禁用
     */
    public function inviteCodeDisable()
    {
        $param['code_id']      = Request::param('code_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = InviteCodeService::disable($param);

        return success($data);
    }
}
