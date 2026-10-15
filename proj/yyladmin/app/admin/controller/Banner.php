<?php
/*
 * @Description  : 轮播图
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\BannerService;

class Banner
{
    public function bannerList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $title      = Request::param('title/s', '');

        $where = [];
        if ($title) {
            $where[] = ['title', 'like', '%' . $title . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = BannerService::list($where, $page, $limit, $order);

        return success($data);
    }

    public function bannerInfo()
    {
        $param['banner_id'] = Request::param('banner_id/d', '');

        $data = BannerService::info($param['banner_id']);

        return success($data);
    }

    public function bannerAdd()
    {
        if (Request::isGet()) {
            $data = BannerService::add();
        } else {
            $param['title'] = Request::param('title/s', '');
            $param['image'] = Request::param('image/s', '');
            $param['url']   = Request::param('url/s', '');
            $param['sort']  = Request::param('sort/d', 200);

            $data = BannerService::add($param, 'post');
        }

        return success($data);
    }

    public function bannerEdit()
    {
        $param['banner_id'] = Request::param('banner_id/d', '');

        if (Request::isGet()) {
            $data = BannerService::edit($param);
        } else {
            $param['title'] = Request::param('title/s', '');
            $param['image'] = Request::param('image/s', '');
            $param['url']   = Request::param('url/s', '');
            $param['sort']  = Request::param('sort/d', 200);

            $data = BannerService::edit($param, 'post');
        }

        return success($data);
    }

    public function bannerDele()
    {
        $param['banner_id'] = Request::param('banner_id/d', '');

        $data = BannerService::dele($param['banner_id']);

        return success($data);
    }

    public function bannerDisable()
    {
        $param['banner_id']  = Request::param('banner_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = BannerService::disable($param);

        return success($data);
    }
}
