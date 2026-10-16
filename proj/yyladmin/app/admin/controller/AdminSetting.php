<?php
/*
 * @Description  : 系统设置
 * @Author       : https://github.com/skyselang
 * @Date         : 2020-08-05
 * @LastEditTime : 2020-12-10
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\validate\AdminVerifyValidate;
use app\admin\validate\AdminTokenValidate;
use app\admin\service\AdminSettingService;

class AdminSetting
{
    /**
     * 缓存设置
     *
     * @method GET|POST
     *
     * @return json
     */
    public function settingCache()
    {
        if (Request::isGet()) {
            $data = AdminSettingService::settingCache();
        } else {
            $data = AdminSettingService::settingCache([], 'post');
        }

        return success($data);
    }

    /**
     * 验证码设置
     *
     * @method GET|POST
     *
     * @return josn
     */
    public function settingVerify()
    {
        if (Request::isGet()) {
            $data = AdminSettingService::settingVerify();
        } else {
            $param['type']   = Request::param('type/d', 1);
            $param['length'] = Request::param('length/d', 4);
            $param['expire'] = Request::param('expire/d', 180);
            $param['switch'] = Request::param('switch/b', false);
            $param['curve']  = Request::param('curve/b', false);
            $param['noise']  = Request::param('noise/b', false);
            $param['bgimg']  = Request::param('bgimg/b', false);

            validate(AdminVerifyValidate::class)->scene('edit')->check($param);

            $data = AdminSettingService::settingVerify($param, 'post');
        }

        return success($data);
    }

    /**
     * Token设置
     *
     * @method GET|POST
     *
     * @return josn
     */
    public function settingToken()
    {
        if (Request::isGet()) {
            $data = AdminSettingService::settingToken();
        } else {
            $param['iss'] = Request::param('iss/s', 'yylAdmin');
            $param['exp'] = Request::param('exp/d', 12);

            validate(AdminTokenValidate::class)->scene('edit')->check($param);

            $data = AdminSettingService::settingToken($param, 'post');
        }

        return success($data);
    }

    /**
     * 主题颜色设置（作用于后台界面 + 前端 H5）
     *
     * @method GET|POST
     *
     * @return json
     */
    public function settingTheme()
    {
        if (Request::isGet()) {
            $data = AdminSettingService::settingTheme();
        } else {
            $param['primary']   = Request::param('primary/s', '#3b7cff');
            $param['secondary'] = Request::param('secondary/s', '#2f66d6');

            $data = AdminSettingService::settingTheme($param, 'post');
        }

        return success($data);
    }

    /**
     * 客服设置（内置客服开关）
     *
     * @method GET|POST
     *
     * @return json
     */
    public function settingCustomerService()
    {
        if (Request::isGet()) {
            $data = AdminSettingService::settingCustomerService();
        } else {
            $param['builtin_enabled'] = Request::param('builtin_enabled/d', 1);

            $data = AdminSettingService::settingCustomerService($param, 'post');
        }

        return success($data);
    }

    /**
     * 服务器信息
     *
     * @method GET
     *
     * @return json
     */
    public function serverInfo()
    {
        $data = AdminSettingService::serverInfo();

        return success($data);
    }
}
