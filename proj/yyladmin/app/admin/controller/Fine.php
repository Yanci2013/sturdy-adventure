<?php
/*
 * @Description  : 罚款管理
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\FineService;

class Fine
{
    /**
     * 罚款管理列表
     */
    public function fineList()
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

        $data = FineService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 罚款管理信息
     */
    public function fineInfo()
    {
        $param['fine_id'] = Request::param('fine_id/d', '');

        $data = FineService::info($param['fine_id']);

        return success($data);
    }

    /**
     * 罚款管理添加
     */
    public function fineAdd()
    {
        if (Request::isGet()) {
            $data = FineService::add();
        } else {
            $param['player_name'] = Request::param('player_name/s', '');
            $param['money'] = Request::param('money/d', 0);
            $param['reason'] = Request::param('reason/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = FineService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 罚款管理修改
     */
    public function fineEdit()
    {
        $param['fine_id'] = Request::param('fine_id/d', '');

        if (Request::isGet()) {
            $data = FineService::edit($param);
        } else {
            $param['player_name'] = Request::param('player_name/s', '');
            $param['money'] = Request::param('money/d', 0);
            $param['reason'] = Request::param('reason/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = FineService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 罚款管理删除
     */
    public function fineDele()
    {
        $param['fine_id'] = Request::param('fine_id/d', '');

        $data = FineService::dele($param['fine_id']);

        return success($data);
    }

    /**
     * 罚款管理是否禁用
     */
    public function fineDisable()
    {
        $param['fine_id']      = Request::param('fine_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = FineService::disable($param);

        return success($data);
    }
}
