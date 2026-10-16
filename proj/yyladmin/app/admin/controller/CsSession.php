<?php
/*
 * @Description  : 客服会话记录
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\CsSessionService;

class CsSession
{
    /**
     * 客服会话记录列表
     */
    public function csSessionList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('cs_name/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['cs_name', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = CsSessionService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 客服会话记录信息
     */
    public function csSessionInfo()
    {
        $param['session_id'] = Request::param('session_id/d', '');

        $data = CsSessionService::info($param['session_id']);

        return success($data);
    }

    /**
     * 客服会话记录添加
     */
    public function csSessionAdd()
    {
        if (Request::isGet()) {
            $data = CsSessionService::add();
        } else {
            $param['cs_name'] = Request::param('cs_name/s', '');
            $param['member_name'] = Request::param('member_name/s', '');
            $param['content'] = Request::param('content/s', '');
            $param['duration'] = Request::param('duration/d', 0);
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = CsSessionService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 客服会话记录修改
     */
    public function csSessionEdit()
    {
        $param['session_id'] = Request::param('session_id/d', '');

        if (Request::isGet()) {
            $data = CsSessionService::edit($param);
        } else {
            $param['cs_name'] = Request::param('cs_name/s', '');
            $param['member_name'] = Request::param('member_name/s', '');
            $param['content'] = Request::param('content/s', '');
            $param['duration'] = Request::param('duration/d', 0);
            $param['status'] = Request::param('status/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = CsSessionService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 客服会话记录删除
     */
    public function csSessionDele()
    {
        $param['session_id'] = Request::param('session_id/d', '');

        $data = CsSessionService::dele($param['session_id']);

        return success($data);
    }

    /**
     * 客服会话记录是否禁用
     */
    public function csSessionDisable()
    {
        $param['session_id']      = Request::param('session_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = CsSessionService::disable($param);

        return success($data);
    }
}
