<?php
/*
 * @Description  : 商品审核
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\GoodsAuditService;

class GoodsAudit
{
    /**
     * 商品审核列表
     */
    public function goodsAuditList()
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

        $data = GoodsAuditService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 商品审核信息
     */
    public function goodsAuditInfo()
    {
        $param['audit_id'] = Request::param('audit_id/d', '');

        $data = GoodsAuditService::info($param['audit_id']);

        return success($data);
    }

    /**
     * 商品审核添加
     */
    public function goodsAuditAdd()
    {
        if (Request::isGet()) {
            $data = GoodsAuditService::add();
        } else {
            $param['goods_name'] = Request::param('goods_name/s', '');
            $param['player_name'] = Request::param('player_name/s', '');
            $param['price'] = Request::param('price/d', 0);
            $param['images'] = Request::param('images/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = GoodsAuditService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 商品审核修改
     */
    public function goodsAuditEdit()
    {
        $param['audit_id'] = Request::param('audit_id/d', '');

        if (Request::isGet()) {
            $data = GoodsAuditService::edit($param);
        } else {
            $param['goods_name'] = Request::param('goods_name/s', '');
            $param['player_name'] = Request::param('player_name/s', '');
            $param['price'] = Request::param('price/d', 0);
            $param['images'] = Request::param('images/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = GoodsAuditService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 商品审核删除
     */
    public function goodsAuditDele()
    {
        $param['audit_id'] = Request::param('audit_id/d', '');

        $data = GoodsAuditService::dele($param['audit_id']);

        return success($data);
    }

    /**
     * 商品审核是否禁用
     */
    public function goodsAuditDisable()
    {
        $param['audit_id']      = Request::param('audit_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = GoodsAuditService::disable($param);

        return success($data);
    }
}
