<?php
/*
 * @Description  : 微信商户
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\WxMerchantService;

class WxMerchant
{
    /**
     * 微信商户列表
     */
    public function wxMerchantList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('merchant_name/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['merchant_name', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = WxMerchantService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 微信商户信息
     */
    public function wxMerchantInfo()
    {
        $param['merchant_id'] = Request::param('merchant_id/d', '');

        $data = WxMerchantService::info($param['merchant_id']);

        return success($data);
    }

    /**
     * 微信商户添加
     */
    public function wxMerchantAdd()
    {
        if (Request::isGet()) {
            $data = WxMerchantService::add();
        } else {
            $param['merchant_name'] = Request::param('merchant_name/s', '');
            $param['mch_id'] = Request::param('mch_id/s', '');
            $param['app_id'] = Request::param('app_id/s', '');
            $param['api_key'] = Request::param('api_key/s', '');
            $param['cert_path'] = Request::param('cert_path/s', '');
            $param['status'] = Request::param('status/d', 0);

            $data = WxMerchantService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 微信商户修改
     */
    public function wxMerchantEdit()
    {
        $param['merchant_id'] = Request::param('merchant_id/d', '');

        if (Request::isGet()) {
            $data = WxMerchantService::edit($param);
        } else {
            $param['merchant_name'] = Request::param('merchant_name/s', '');
            $param['mch_id'] = Request::param('mch_id/s', '');
            $param['app_id'] = Request::param('app_id/s', '');
            $param['api_key'] = Request::param('api_key/s', '');
            $param['cert_path'] = Request::param('cert_path/s', '');
            $param['status'] = Request::param('status/d', 0);

            $data = WxMerchantService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 微信商户删除
     */
    public function wxMerchantDele()
    {
        $param['merchant_id'] = Request::param('merchant_id/d', '');

        $data = WxMerchantService::dele($param['merchant_id']);

        return success($data);
    }

    /**
     * 微信商户是否禁用
     */
    public function wxMerchantDisable()
    {
        $param['merchant_id']      = Request::param('merchant_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = WxMerchantService::disable($param);

        return success($data);
    }
}
