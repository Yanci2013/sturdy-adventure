<?php
/*
 * @Description  : 打手管理
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\PlayerService;

class Player
{
    /**
     * 打手管理列表
     */
    public function playerList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('nickname/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['nickname', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = PlayerService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 打手管理信息
     */
    public function playerInfo()
    {
        $param['player_id'] = Request::param('player_id/d', '');

        $data = PlayerService::info($param['player_id']);

        return success($data);
    }

    /**
     * 打手管理添加
     */
    public function playerAdd()
    {
        if (Request::isGet()) {
            $data = PlayerService::add();
        } else {
            $param['nickname'] = Request::param('nickname/s', '');
            $param['avatar'] = Request::param('avatar/s', '');
            $param['phone'] = Request::param('phone/s', '');
            $param['account'] = Request::param('account/s', '');
            $param['level_name'] = Request::param('level_name/s', '');
            $param['score'] = Request::param('score/d', 0);
            $param['balance'] = Request::param('balance/d', 0);
            $param['is_online'] = Request::param('is_online/d', 0);
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = PlayerService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 打手管理修改
     */
    public function playerEdit()
    {
        $param['player_id'] = Request::param('player_id/d', '');

        if (Request::isGet()) {
            $data = PlayerService::edit($param);
        } else {
            $param['nickname'] = Request::param('nickname/s', '');
            $param['avatar'] = Request::param('avatar/s', '');
            $param['phone'] = Request::param('phone/s', '');
            $param['account'] = Request::param('account/s', '');
            $param['level_name'] = Request::param('level_name/s', '');
            $param['score'] = Request::param('score/d', 0);
            $param['balance'] = Request::param('balance/d', 0);
            $param['is_online'] = Request::param('is_online/d', 0);
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = PlayerService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 打手管理删除
     */
    public function playerDele()
    {
        $param['player_id'] = Request::param('player_id/d', '');

        $data = PlayerService::dele($param['player_id']);

        return success($data);
    }

    /**
     * 打手管理是否禁用
     */
    public function playerDisable()
    {
        $param['player_id']      = Request::param('player_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = PlayerService::disable($param);

        return success($data);
    }
}
