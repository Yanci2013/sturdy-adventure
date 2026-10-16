<?php
/*
 * @Description  : 客服管理
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\CustomerServiceService;

class CustomerService
{
    /**
     * 客服列表
     */
    public function csList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $name       = Request::param('name/s', '');
        $phone      = Request::param('phone/s', '');

        $where = [];
        if ($name) {
            $where[] = ['name', 'like', '%' . $name . '%'];
        }
        if ($phone) {
            $where[] = ['phone', 'like', '%' . $phone . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = CustomerServiceService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 客服信息
     */
    public function csInfo()
    {
        $param['cs_id'] = Request::param('cs_id/d', '');

        $data = CustomerServiceService::info($param['cs_id']);

        return success($data);
    }

    /**
     * 客服添加
     */
    public function csAdd()
    {
        if (Request::isGet()) {
            $data = CustomerServiceService::add();
        } else {
            $param['name']     = Request::param('name/s', '');
            $param['type']     = Request::param('type/s', 'im');
            $param['avatar']   = Request::param('avatar/s', '');
            $param['phone']    = Request::param('phone/s', '');
            $param['wechat']   = Request::param('wechat/s', '');
            $param['account']  = Request::param('account/s', '');
            $param['is_online'] = Request::param('is_online/d', 0);
            $param['sort']     = Request::param('sort/d', 200);
            $param['remark']   = Request::param('remark/s', '');

            $data = CustomerServiceService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 客服修改
     */
    public function csEdit()
    {
        $param['cs_id'] = Request::param('cs_id/d', '');

        if (Request::isGet()) {
            $data = CustomerServiceService::edit($param);
        } else {
            $param['name']     = Request::param('name/s', '');
            $param['type']     = Request::param('type/s', 'im');
            $param['avatar']   = Request::param('avatar/s', '');
            $param['phone']    = Request::param('phone/s', '');
            $param['wechat']   = Request::param('wechat/s', '');
            $param['account']  = Request::param('account/s', '');
            $param['is_online'] = Request::param('is_online/d', 0);
            $param['sort']     = Request::param('sort/d', 200);
            $param['remark']   = Request::param('remark/s', '');

            $data = CustomerServiceService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 客服删除
     */
    public function csDele()
    {
        $param['cs_id'] = Request::param('cs_id/d', '');

        $data = CustomerServiceService::dele($param['cs_id']);

        return success($data);
    }

    /**
     * 客服是否禁用
     */
    public function csDisable()
    {
        $param['cs_id']       = Request::param('cs_id/d', '');
        $param['is_disable']  = Request::param('is_disable/d', 0);

        $data = CustomerServiceService::disable($param);

        return success($data);
    }
}
