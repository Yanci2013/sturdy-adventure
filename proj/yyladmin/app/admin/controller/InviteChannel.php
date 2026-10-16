<?php
/*
 * @Description  : 邀请渠道统计
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\InviteChannelService;

class InviteChannel
{
    /**
     * 邀请渠道统计列表
     */
    public function inviteChannelList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('channel_name/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['channel_name', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = InviteChannelService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 邀请渠道统计信息
     */
    public function inviteChannelInfo()
    {
        $param['channel_id'] = Request::param('channel_id/d', '');

        $data = InviteChannelService::info($param['channel_id']);

        return success($data);
    }

    /**
     * 邀请渠道统计添加
     */
    public function inviteChannelAdd()
    {
        if (Request::isGet()) {
            $data = InviteChannelService::add();
        } else {
            $param['channel_name'] = Request::param('channel_name/s', '');
            $param['invite_count'] = Request::param('invite_count/d', 0);
            $param['register_count'] = Request::param('register_count/d', 0);
            $param['order_count'] = Request::param('order_count/d', 0);
            $param['amount'] = Request::param('amount/d', 0);

            $data = InviteChannelService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 邀请渠道统计修改
     */
    public function inviteChannelEdit()
    {
        $param['channel_id'] = Request::param('channel_id/d', '');

        if (Request::isGet()) {
            $data = InviteChannelService::edit($param);
        } else {
            $param['channel_name'] = Request::param('channel_name/s', '');
            $param['invite_count'] = Request::param('invite_count/d', 0);
            $param['register_count'] = Request::param('register_count/d', 0);
            $param['order_count'] = Request::param('order_count/d', 0);
            $param['amount'] = Request::param('amount/d', 0);

            $data = InviteChannelService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 邀请渠道统计删除
     */
    public function inviteChannelDele()
    {
        $param['channel_id'] = Request::param('channel_id/d', '');

        $data = InviteChannelService::dele($param['channel_id']);

        return success($data);
    }

    /**
     * 邀请渠道统计是否禁用
     */
    public function inviteChannelDisable()
    {
        $param['channel_id']      = Request::param('channel_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = InviteChannelService::disable($param);

        return success($data);
    }
}
