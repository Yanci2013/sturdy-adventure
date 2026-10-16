<?php
/*
 * @Description  : IP黑名单
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\IpBlacklistService;

class IpBlacklist
{
    /**
     * IP黑名单列表
     */
    public function ipBlacklistList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('ip/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['ip', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = IpBlacklistService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * IP黑名单信息
     */
    public function ipBlacklistInfo()
    {
        $param['ip_id'] = Request::param('ip_id/d', '');

        $data = IpBlacklistService::info($param['ip_id']);

        return success($data);
    }

    /**
     * IP黑名单添加
     */
    public function ipBlacklistAdd()
    {
        if (Request::isGet()) {
            $data = IpBlacklistService::add();
        } else {
            $param['ip'] = Request::param('ip/s', '');
            $param['reason'] = Request::param('reason/s', '');
            $param['operator'] = Request::param('operator/s', '');
            $param['expire_time'] = Request::param('expire_time/s', '');
            $param['status'] = Request::param('status/d', 0);

            $data = IpBlacklistService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * IP黑名单修改
     */
    public function ipBlacklistEdit()
    {
        $param['ip_id'] = Request::param('ip_id/d', '');

        if (Request::isGet()) {
            $data = IpBlacklistService::edit($param);
        } else {
            $param['ip'] = Request::param('ip/s', '');
            $param['reason'] = Request::param('reason/s', '');
            $param['operator'] = Request::param('operator/s', '');
            $param['expire_time'] = Request::param('expire_time/s', '');
            $param['status'] = Request::param('status/d', 0);

            $data = IpBlacklistService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * IP黑名单删除
     */
    public function ipBlacklistDele()
    {
        $param['ip_id'] = Request::param('ip_id/d', '');

        $data = IpBlacklistService::dele($param['ip_id']);

        return success($data);
    }

    /**
     * IP黑名单是否禁用
     */
    public function ipBlacklistDisable()
    {
        $param['ip_id']      = Request::param('ip_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = IpBlacklistService::disable($param);

        return success($data);
    }
}
