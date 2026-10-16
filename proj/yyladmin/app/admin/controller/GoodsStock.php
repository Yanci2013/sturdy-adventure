<?php
/*
 * @Description  : 商品库存
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\GoodsStockService;

class GoodsStock
{
    /**
     * 商品库存列表
     */
    public function goodsStockList()
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

        $data = GoodsStockService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 商品库存信息
     */
    public function goodsStockInfo()
    {
        $param['stock_id'] = Request::param('stock_id/d', '');

        $data = GoodsStockService::info($param['stock_id']);

        return success($data);
    }

    /**
     * 商品库存添加
     */
    public function goodsStockAdd()
    {
        if (Request::isGet()) {
            $data = GoodsStockService::add();
        } else {
            $param['goods_name'] = Request::param('goods_name/s', '');
            $param['sku'] = Request::param('sku/s', '');
            $param['stock'] = Request::param('stock/d', 0);
            $param['warning_stock'] = Request::param('warning_stock/d', 0);
            $param['unit'] = Request::param('unit/s', '');
            $param['status'] = Request::param('status/d', 0);

            $data = GoodsStockService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 商品库存修改
     */
    public function goodsStockEdit()
    {
        $param['stock_id'] = Request::param('stock_id/d', '');

        if (Request::isGet()) {
            $data = GoodsStockService::edit($param);
        } else {
            $param['goods_name'] = Request::param('goods_name/s', '');
            $param['sku'] = Request::param('sku/s', '');
            $param['stock'] = Request::param('stock/d', 0);
            $param['warning_stock'] = Request::param('warning_stock/d', 0);
            $param['unit'] = Request::param('unit/s', '');
            $param['status'] = Request::param('status/d', 0);

            $data = GoodsStockService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 商品库存删除
     */
    public function goodsStockDele()
    {
        $param['stock_id'] = Request::param('stock_id/d', '');

        $data = GoodsStockService::dele($param['stock_id']);

        return success($data);
    }

    /**
     * 商品库存是否禁用
     */
    public function goodsStockDisable()
    {
        $param['stock_id']      = Request::param('stock_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = GoodsStockService::disable($param);

        return success($data);
    }
}
