<?php
/*
 * @Description  : 分类管理
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\CategoryService;

class Category
{
    /**
     * 分类管理列表
     */
    public function categoryList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('category_name/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['category_name', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = CategoryService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 分类管理信息
     */
    public function categoryInfo()
    {
        $param['category_id'] = Request::param('category_id/d', '');

        $data = CategoryService::info($param['category_id']);

        return success($data);
    }

    /**
     * 分类管理添加
     */
    public function categoryAdd()
    {
        if (Request::isGet()) {
            $data = CategoryService::add();
        } else {
            $param['category_name'] = Request::param('category_name/s', '');
            $param['category_pid'] = Request::param('category_pid/d', 0);
            $param['image'] = Request::param('image/s', '');
            $param['remark'] = Request::param('remark/s', '');

            $data = CategoryService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 分类管理修改
     */
    public function categoryEdit()
    {
        $param['category_id'] = Request::param('category_id/d', '');

        if (Request::isGet()) {
            $data = CategoryService::edit($param);
        } else {
            $param['category_name'] = Request::param('category_name/s', '');
            $param['category_pid'] = Request::param('category_pid/d', 0);
            $param['image'] = Request::param('image/s', '');
            $param['remark'] = Request::param('remark/s', '');

            $data = CategoryService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 分类管理删除
     */
    public function categoryDele()
    {
        $param['category_id'] = Request::param('category_id/d', '');

        $data = CategoryService::dele($param['category_id']);

        return success($data);
    }

    /**
     * 分类管理是否禁用
     */
    public function categoryDisable()
    {
        $param['category_id']      = Request::param('category_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = CategoryService::disable($param);

        return success($data);
    }
}
