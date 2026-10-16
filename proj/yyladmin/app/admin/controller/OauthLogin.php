<?php
/*
 * @Description  : 聚合登录
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\OauthLoginService;

class OauthLogin
{
    /**
     * 聚合登录列表
     */
    public function oauthLoginList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('name/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['name', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = OauthLoginService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 聚合登录信息
     */
    public function oauthLoginInfo()
    {
        $param['oauth_id'] = Request::param('oauth_id/d', '');

        $data = OauthLoginService::info($param['oauth_id']);

        return success($data);
    }

    /**
     * 聚合登录添加
     */
    public function oauthLoginAdd()
    {
        if (Request::isGet()) {
            $data = OauthLoginService::add();
        } else {
            $param['name'] = Request::param('name/s', '');
            $param['code'] = Request::param('code/s', '');
            $param['app_id'] = Request::param('app_id/s', '');
            $param['app_key'] = Request::param('app_key/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = OauthLoginService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 聚合登录修改
     */
    public function oauthLoginEdit()
    {
        $param['oauth_id'] = Request::param('oauth_id/d', '');

        if (Request::isGet()) {
            $data = OauthLoginService::edit($param);
        } else {
            $param['name'] = Request::param('name/s', '');
            $param['code'] = Request::param('code/s', '');
            $param['app_id'] = Request::param('app_id/s', '');
            $param['app_key'] = Request::param('app_key/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = OauthLoginService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 聚合登录删除
     */
    public function oauthLoginDele()
    {
        $param['oauth_id'] = Request::param('oauth_id/d', '');

        $data = OauthLoginService::dele($param['oauth_id']);

        return success($data);
    }

    /**
     * 聚合登录是否禁用
     */
    public function oauthLoginDisable()
    {
        $param['oauth_id']      = Request::param('oauth_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = OauthLoginService::disable($param);

        return success($data);
    }
}
