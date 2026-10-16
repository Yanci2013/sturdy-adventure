<?php
/*
 * @Description  : 登录安全管理
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\LoginSecurityService;

class LoginSecurity
{
    /**
     * 登录安全管理列表
     */
    public function loginSecurityList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('username/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['username', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = LoginSecurityService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 登录安全管理信息
     */
    public function loginSecurityInfo()
    {
        $param['security_id'] = Request::param('security_id/d', '');

        $data = LoginSecurityService::info($param['security_id']);

        return success($data);
    }

    /**
     * 登录安全管理添加
     */
    public function loginSecurityAdd()
    {
        if (Request::isGet()) {
            $data = LoginSecurityService::add();
        } else {
            $param['username'] = Request::param('username/s', '');
            $param['ip'] = Request::param('ip/s', '');
            $param['region'] = Request::param('region/s', '');
            $param['device'] = Request::param('device/s', '');
            $param['login_time'] = Request::param('login_time/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = LoginSecurityService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 登录安全管理修改
     */
    public function loginSecurityEdit()
    {
        $param['security_id'] = Request::param('security_id/d', '');

        if (Request::isGet()) {
            $data = LoginSecurityService::edit($param);
        } else {
            $param['username'] = Request::param('username/s', '');
            $param['ip'] = Request::param('ip/s', '');
            $param['region'] = Request::param('region/s', '');
            $param['device'] = Request::param('device/s', '');
            $param['login_time'] = Request::param('login_time/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = LoginSecurityService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 登录安全管理删除
     */
    public function loginSecurityDele()
    {
        $param['security_id'] = Request::param('security_id/d', '');

        $data = LoginSecurityService::dele($param['security_id']);

        return success($data);
    }

    /**
     * 登录安全管理是否禁用
     */
    public function loginSecurityDisable()
    {
        $param['security_id']      = Request::param('security_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = LoginSecurityService::disable($param);

        return success($data);
    }
}
