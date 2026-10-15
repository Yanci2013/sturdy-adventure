<?php
/*
 * @Description  : 公告管理
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\NoticeService;

class Notice
{
    public function noticeList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $title      = Request::param('title/s', '');

        $where = [];
        if ($title) {
            $where[] = ['title', 'like', '%' . $title . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = NoticeService::list($where, $page, $limit, $order);

        return success($data);
    }

    public function noticeInfo()
    {
        $param['notice_id'] = Request::param('notice_id/d', '');

        $data = NoticeService::info($param['notice_id']);

        return success($data);
    }

    public function noticeAdd()
    {
        if (Request::isGet()) {
            $data = NoticeService::add();
        } else {
            $param['title']   = Request::param('title/s', '');
            $param['content'] = Request::param('content/s', '');
            $param['type']    = Request::param('type/d', 1);
            $param['sort']    = Request::param('sort/d', 200);

            $data = NoticeService::add($param, 'post');
        }

        return success($data);
    }

    public function noticeEdit()
    {
        $param['notice_id'] = Request::param('notice_id/d', '');

        if (Request::isGet()) {
            $data = NoticeService::edit($param);
        } else {
            $param['title']   = Request::param('title/s', '');
            $param['content'] = Request::param('content/s', '');
            $param['type']    = Request::param('type/d', 1);
            $param['sort']    = Request::param('sort/d', 200);

            $data = NoticeService::edit($param, 'post');
        }

        return success($data);
    }

    public function noticeDele()
    {
        $param['notice_id'] = Request::param('notice_id/d', '');

        $data = NoticeService::dele($param['notice_id']);

        return success($data);
    }

    public function noticeDisable()
    {
        $param['notice_id']  = Request::param('notice_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = NoticeService::disable($param);

        return success($data);
    }
}
