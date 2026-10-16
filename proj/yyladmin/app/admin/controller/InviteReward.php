<?php
/*
 * @Description  : 邀请奖励配置
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\InviteRewardService;

class InviteReward
{
    /**
     * 邀请奖励配置列表
     */
    public function inviteRewardList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('reward_name/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['reward_name', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = InviteRewardService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 邀请奖励配置信息
     */
    public function inviteRewardInfo()
    {
        $param['reward_id'] = Request::param('reward_id/d', '');

        $data = InviteRewardService::info($param['reward_id']);

        return success($data);
    }

    /**
     * 邀请奖励配置添加
     */
    public function inviteRewardAdd()
    {
        if (Request::isGet()) {
            $data = InviteRewardService::add();
        } else {
            $param['reward_name'] = Request::param('reward_name/s', '');
            $param['reward_type'] = Request::param('reward_type/s', '');
            $param['reward_money'] = Request::param('reward_money/d', 0);
            $param['condition'] = Request::param('condition/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = InviteRewardService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 邀请奖励配置修改
     */
    public function inviteRewardEdit()
    {
        $param['reward_id'] = Request::param('reward_id/d', '');

        if (Request::isGet()) {
            $data = InviteRewardService::edit($param);
        } else {
            $param['reward_name'] = Request::param('reward_name/s', '');
            $param['reward_type'] = Request::param('reward_type/s', '');
            $param['reward_money'] = Request::param('reward_money/d', 0);
            $param['condition'] = Request::param('condition/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = InviteRewardService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 邀请奖励配置删除
     */
    public function inviteRewardDele()
    {
        $param['reward_id'] = Request::param('reward_id/d', '');

        $data = InviteRewardService::dele($param['reward_id']);

        return success($data);
    }

    /**
     * 邀请奖励配置是否禁用
     */
    public function inviteRewardDisable()
    {
        $param['reward_id']      = Request::param('reward_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = InviteRewardService::disable($param);

        return success($data);
    }
}
