<?php
/*
 * @Description  : 商品
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\GoodsService;

class Goods
{
    /**
     * 商品列表
     */
    public function goodsList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('goods_name/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['goods_name', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = GoodsService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 商品信息
     */
    public function goodsInfo()
    {
        $param['goods_id'] = Request::param('goods_id/d', '');

        $data = GoodsService::info($param['goods_id']);

        return success($data);
    }

    /**
     * 商品添加
     */
    public function goodsAdd()
    {
        if (Request::isGet()) {
            $data = GoodsService::add();
        } else {
            $param['goods_name'] = Request::param('goods_name/s', '');
            $param['category_name'] = Request::param('category_name/s', '');
            $param['category_pid'] = Request::param('category_pid/d', 0);
            $param['category_id'] = Request::param('category_id/d', 0);
            $param['area'] = Request::param('area/s', '');
            $param['image'] = Request::param('image/s', '');
            $param['price'] = Request::param('price/d', 0);
            $param['stock'] = Request::param('stock/d', 0);
            $param['unit'] = Request::param('unit/s', '');
            $param['remark'] = Request::param('remark/s', '');

            $data = GoodsService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 商品修改
     */
    public function goodsEdit()
    {
        $param['goods_id'] = Request::param('goods_id/d', '');

        if (Request::isGet()) {
            $data = GoodsService::edit($param);
        } else {
            $param['goods_name'] = Request::param('goods_name/s', '');
            $param['category_name'] = Request::param('category_name/s', '');
            $param['category_pid'] = Request::param('category_pid/d', 0);
            $param['category_id'] = Request::param('category_id/d', 0);
            $param['area'] = Request::param('area/s', '');
            $param['image'] = Request::param('image/s', '');
            $param['price'] = Request::param('price/d', 0);
            $param['stock'] = Request::param('stock/d', 0);
            $param['unit'] = Request::param('unit/s', '');
            $param['remark'] = Request::param('remark/s', '');

            $data = GoodsService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 商品删除
     */
    public function goodsDele()
    {
        $param['goods_id'] = Request::param('goods_id/d', '');

        $data = GoodsService::dele($param['goods_id']);

        return success($data);
    }

    /**
     * 商品是否禁用
     */
    public function goodsDisable()
    {
        $param['goods_id']      = Request::param('goods_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = GoodsService::disable($param);

        return success($data);
    }
}
