<?php
/*
 * @Description  : 弹窗配置
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\PopupService;

class Popup
{
    /**
     * 弹窗配置列表
     */
    public function popupList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('title/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['title', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = PopupService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 弹窗配置信息
     */
    public function popupInfo()
    {
        $param['popup_id'] = Request::param('popup_id/d', '');

        $data = PopupService::info($param['popup_id']);

        return success($data);
    }

    /**
     * 弹窗配置添加
     */
    public function popupAdd()
    {
        if (Request::isGet()) {
            $data = PopupService::add();
        } else {
            $param['title'] = Request::param('title/s', '');
            $param['image'] = Request::param('image/s', '');
            $param['url'] = Request::param('url/s', '');
            $param['position'] = Request::param('position/s', '');
            $param['start_time'] = Request::param('start_time/s', '');
            $param['end_time'] = Request::param('end_time/s', '');
            $param['status'] = Request::param('status/d', 0);

            $data = PopupService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 弹窗配置修改
     */
    public function popupEdit()
    {
        $param['popup_id'] = Request::param('popup_id/d', '');

        if (Request::isGet()) {
            $data = PopupService::edit($param);
        } else {
            $param['title'] = Request::param('title/s', '');
            $param['image'] = Request::param('image/s', '');
            $param['url'] = Request::param('url/s', '');
            $param['position'] = Request::param('position/s', '');
            $param['start_time'] = Request::param('start_time/s', '');
            $param['end_time'] = Request::param('end_time/s', '');
            $param['status'] = Request::param('status/d', 0);

            $data = PopupService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 弹窗配置删除
     */
    public function popupDele()
    {
        $param['popup_id'] = Request::param('popup_id/d', '');

        $data = PopupService::dele($param['popup_id']);

        return success($data);
    }

    /**
     * 弹窗配置是否禁用
     */
    public function popupDisable()
    {
        $param['popup_id']      = Request::param('popup_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = PopupService::disable($param);

        return success($data);
    }
}
