<?php
/*
 * @Description  : 易支付
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\EpayService;

class Epay
{
    /**
     * 易支付列表
     */
    public function epayList()
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

        $data = EpayService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 易支付信息
     */
    public function epayInfo()
    {
        $param['epay_id'] = Request::param('epay_id/d', '');

        $data = EpayService::info($param['epay_id']);

        return success($data);
    }

    /**
     * 易支付添加
     */
    public function epayAdd()
    {
        if (Request::isGet()) {
            $data = EpayService::add();
        } else {
            $param['name'] = Request::param('name/s', '');
            $param['api_url'] = Request::param('api_url/s', '');
            $param['pid'] = Request::param('pid/s', '');
            $param['key'] = Request::param('key/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = EpayService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 易支付修改
     */
    public function epayEdit()
    {
        $param['epay_id'] = Request::param('epay_id/d', '');

        if (Request::isGet()) {
            $data = EpayService::edit($param);
        } else {
            $param['name'] = Request::param('name/s', '');
            $param['api_url'] = Request::param('api_url/s', '');
            $param['pid'] = Request::param('pid/s', '');
            $param['key'] = Request::param('key/s', '');
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = EpayService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 易支付删除
     */
    public function epayDele()
    {
        $param['epay_id'] = Request::param('epay_id/d', '');

        $data = EpayService::dele($param['epay_id']);

        return success($data);
    }

    /**
     * 易支付是否禁用
     */
    public function epayDisable()
    {
        $param['epay_id']      = Request::param('epay_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = EpayService::disable($param);

        return success($data);
    }
}
