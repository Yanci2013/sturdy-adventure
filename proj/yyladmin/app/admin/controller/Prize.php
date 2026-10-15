<?php
/*
 * @Description  : 奖池
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\PrizeService;

class Prize
{
    /**
     * 奖池列表
     */
    public function prizeList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('prize_name/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['prize_name', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = PrizeService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 奖池信息
     */
    public function prizeInfo()
    {
        $param['prize_id'] = Request::param('prize_id/d', '');

        $data = PrizeService::info($param['prize_id']);

        return success($data);
    }

    /**
     * 奖池添加
     */
    public function prizeAdd()
    {
        if (Request::isGet()) {
            $data = PrizeService::add();
        } else {
            $param['prize_name'] = Request::param('prize_name/s', '');
            $param['goods_name'] = Request::param('goods_name/s', '');
            $param['image'] = Request::param('image/s', '');
            $param['rate'] = Request::param('rate/d', 0);
            $param['stock'] = Request::param('stock/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = PrizeService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 奖池修改
     */
    public function prizeEdit()
    {
        $param['prize_id'] = Request::param('prize_id/d', '');

        if (Request::isGet()) {
            $data = PrizeService::edit($param);
        } else {
            $param['prize_name'] = Request::param('prize_name/s', '');
            $param['goods_name'] = Request::param('goods_name/s', '');
            $param['image'] = Request::param('image/s', '');
            $param['rate'] = Request::param('rate/d', 0);
            $param['stock'] = Request::param('stock/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = PrizeService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 奖池删除
     */
    public function prizeDele()
    {
        $param['prize_id'] = Request::param('prize_id/d', '');

        $data = PrizeService::dele($param['prize_id']);

        return success($data);
    }

    /**
     * 奖池是否禁用
     */
    public function prizeDisable()
    {
        $param['prize_id']      = Request::param('prize_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = PrizeService::disable($param);

        return success($data);
    }
}
