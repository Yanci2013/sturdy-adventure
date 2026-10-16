<?php
/*
 * @Description  : 短信配置
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\SmsConfigService;

class SmsConfig
{
    /**
     * 短信配置列表
     */
    public function smsConfigList()
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

        $data = SmsConfigService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 短信配置信息
     */
    public function smsConfigInfo()
    {
        $param['sms_id'] = Request::param('sms_id/d', '');

        $data = SmsConfigService::info($param['sms_id']);

        return success($data);
    }

    /**
     * 短信配置添加
     */
    public function smsConfigAdd()
    {
        if (Request::isGet()) {
            $data = SmsConfigService::add();
        } else {
            $param['name'] = Request::param('name/s', '');
            $param['code'] = Request::param('code/s', '');
            $param['sign_name'] = Request::param('sign_name/s', '');
            $param['access_key'] = Request::param('access_key/s', '');
            $param['access_secret'] = Request::param('access_secret/s', '');
            $param['status'] = Request::param('status/d', 0);

            $data = SmsConfigService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 短信配置修改
     */
    public function smsConfigEdit()
    {
        $param['sms_id'] = Request::param('sms_id/d', '');

        if (Request::isGet()) {
            $data = SmsConfigService::edit($param);
        } else {
            $param['name'] = Request::param('name/s', '');
            $param['code'] = Request::param('code/s', '');
            $param['sign_name'] = Request::param('sign_name/s', '');
            $param['access_key'] = Request::param('access_key/s', '');
            $param['access_secret'] = Request::param('access_secret/s', '');
            $param['status'] = Request::param('status/d', 0);

            $data = SmsConfigService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 短信配置删除
     */
    public function smsConfigDele()
    {
        $param['sms_id'] = Request::param('sms_id/d', '');

        $data = SmsConfigService::dele($param['sms_id']);

        return success($data);
    }

    /**
     * 短信配置是否禁用
     */
    public function smsConfigDisable()
    {
        $param['sms_id']      = Request::param('sms_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = SmsConfigService::disable($param);

        return success($data);
    }
}
