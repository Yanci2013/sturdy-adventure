<?php
/*
 * @Description  : 模板消息配置
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\MsgTemplateService;

class MsgTemplate
{
    public function tplList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $name       = Request::param('name/s', '');

        $where = [];
        if ($name) {
            $where[] = ['name', 'like', '%' . $name . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = MsgTemplateService::list($where, $page, $limit, $order);

        return success($data);
    }

    public function tplInfo()
    {
        $param['tpl_id'] = Request::param('tpl_id/d', '');

        $data = MsgTemplateService::info($param['tpl_id']);

        return success($data);
    }

    public function tplAdd()
    {
        if (Request::isGet()) {
            $data = MsgTemplateService::add();
        } else {
            $param['name']    = Request::param('name/s', '');
            $param['code']    = Request::param('code/s', '');
            $param['title']   = Request::param('title/s', '');
            $param['content'] = Request::param('content/s', '');
            $param['sort']    = Request::param('sort/d', 200);

            $data = MsgTemplateService::add($param, 'post');
        }

        return success($data);
    }

    public function tplEdit()
    {
        $param['tpl_id'] = Request::param('tpl_id/d', '');

        if (Request::isGet()) {
            $data = MsgTemplateService::edit($param);
        } else {
            $param['name']    = Request::param('name/s', '');
            $param['code']    = Request::param('code/s', '');
            $param['title']   = Request::param('title/s', '');
            $param['content'] = Request::param('content/s', '');
            $param['sort']    = Request::param('sort/d', 200);

            $data = MsgTemplateService::edit($param, 'post');
        }

        return success($data);
    }

    public function tplDele()
    {
        $param['tpl_id'] = Request::param('tpl_id/d', '');

        $data = MsgTemplateService::dele($param['tpl_id']);

        return success($data);
    }

    public function tplDisable()
    {
        $param['tpl_id']     = Request::param('tpl_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = MsgTemplateService::disable($param);

        return success($data);
    }
}
