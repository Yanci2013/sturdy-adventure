<?php
/*
 * @Description  : 站内信管理
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\StationLetterService;

class StationLetter
{
    /**
     * 站内信管理列表
     */
    public function stationLetterList()
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

        $data = StationLetterService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 站内信管理信息
     */
    public function stationLetterInfo()
    {
        $param['letter_id'] = Request::param('letter_id/d', '');

        $data = StationLetterService::info($param['letter_id']);

        return success($data);
    }

    /**
     * 站内信管理添加
     */
    public function stationLetterAdd()
    {
        if (Request::isGet()) {
            $data = StationLetterService::add();
        } else {
            $param['title'] = Request::param('title/s', '');
            $param['content'] = Request::param('content/s', '');
            $param['receiver'] = Request::param('receiver/s', '');
            $param['sender'] = Request::param('sender/s', '');
            $param['is_read'] = Request::param('is_read/d', 0);
            $param['status'] = Request::param('status/d', 0);

            $data = StationLetterService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 站内信管理修改
     */
    public function stationLetterEdit()
    {
        $param['letter_id'] = Request::param('letter_id/d', '');

        if (Request::isGet()) {
            $data = StationLetterService::edit($param);
        } else {
            $param['title'] = Request::param('title/s', '');
            $param['content'] = Request::param('content/s', '');
            $param['receiver'] = Request::param('receiver/s', '');
            $param['sender'] = Request::param('sender/s', '');
            $param['is_read'] = Request::param('is_read/d', 0);
            $param['status'] = Request::param('status/d', 0);

            $data = StationLetterService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 站内信管理删除
     */
    public function stationLetterDele()
    {
        $param['letter_id'] = Request::param('letter_id/d', '');

        $data = StationLetterService::dele($param['letter_id']);

        return success($data);
    }

    /**
     * 站内信管理是否禁用
     */
    public function stationLetterDisable()
    {
        $param['letter_id']      = Request::param('letter_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = StationLetterService::disable($param);

        return success($data);
    }
}
