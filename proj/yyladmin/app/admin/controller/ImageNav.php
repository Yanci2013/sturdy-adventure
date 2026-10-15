<?php
/*
 * @Description  : 图片导航
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\ImageNavService;

class ImageNav
{
    public function navList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $name       = Request::param('name/s', '');

        $where = [];
        if ($name) {
            $where[] = ['name', 'like', '%' . $name . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = ImageNavService::list($where, $page, $limit, $order);

        return success($data);
    }

    public function navInfo()
    {
        $param['nav_id'] = Request::param('nav_id/d', '');

        $data = ImageNavService::info($param['nav_id']);

        return success($data);
    }

    public function navAdd()
    {
        if (Request::isGet()) {
            $data = ImageNavService::add();
        } else {
            $param['name']  = Request::param('name/s', '');
            $param['image'] = Request::param('image/s', '');
            $param['url']   = Request::param('url/s', '');
            $param['sort']  = Request::param('sort/d', 200);

            $data = ImageNavService::add($param, 'post');
        }

        return success($data);
    }

    public function navEdit()
    {
        $param['nav_id'] = Request::param('nav_id/d', '');

        if (Request::isGet()) {
            $data = ImageNavService::edit($param);
        } else {
            $param['name']  = Request::param('name/s', '');
            $param['image'] = Request::param('image/s', '');
            $param['url']   = Request::param('url/s', '');
            $param['sort']  = Request::param('sort/d', 200);

            $data = ImageNavService::edit($param, 'post');
        }

        return success($data);
    }

    public function navDele()
    {
        $param['nav_id'] = Request::param('nav_id/d', '');

        $data = ImageNavService::dele($param['nav_id']);

        return success($data);
    }

    public function navDisable()
    {
        $param['nav_id']     = Request::param('nav_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = ImageNavService::disable($param);

        return success($data);
    }
}
