<?php
/*
 * @Description  : 邀请记录
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\InviteRecordService;

class InviteRecord
{
    /**
     * 邀请记录列表
     */
    public function inviteRecordList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('invite_code/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['invite_code', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = InviteRecordService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 邀请记录信息
     */
    public function inviteRecordInfo()
    {
        $param['record_id'] = Request::param('record_id/d', '');

        $data = InviteRecordService::info($param['record_id']);

        return success($data);
    }

    /**
     * 邀请记录添加
     */
    public function inviteRecordAdd()
    {
        if (Request::isGet()) {
            $data = InviteRecordService::add();
        } else {
            $param['invite_code'] = Request::param('invite_code/s', '');
            $param['inviter'] = Request::param('inviter/s', '');
            $param['invitee'] = Request::param('invitee/s', '');
            $param['ip'] = Request::param('ip/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = InviteRecordService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 邀请记录修改
     */
    public function inviteRecordEdit()
    {
        $param['record_id'] = Request::param('record_id/d', '');

        if (Request::isGet()) {
            $data = InviteRecordService::edit($param);
        } else {
            $param['invite_code'] = Request::param('invite_code/s', '');
            $param['inviter'] = Request::param('inviter/s', '');
            $param['invitee'] = Request::param('invitee/s', '');
            $param['ip'] = Request::param('ip/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = InviteRecordService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 邀请记录删除
     */
    public function inviteRecordDele()
    {
        $param['record_id'] = Request::param('record_id/d', '');

        $data = InviteRecordService::dele($param['record_id']);

        return success($data);
    }

    /**
     * 邀请记录是否禁用
     */
    public function inviteRecordDisable()
    {
        $param['record_id']      = Request::param('record_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = InviteRecordService::disable($param);

        return success($data);
    }
}
