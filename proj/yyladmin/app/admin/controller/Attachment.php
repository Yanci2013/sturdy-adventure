<?php
/*
 * @Description  : 附件管理
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\AttachmentService;

class Attachment
{
    /**
     * 附件管理列表
     */
    public function attachList()
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

        $data = AttachmentService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 附件管理信息
     */
    public function attachInfo()
    {
        $param['attachment_id'] = Request::param('attachment_id/d', '');

        $data = AttachmentService::info($param['attachment_id']);

        return success($data);
    }

    /**
     * 附件管理添加
     */
    public function attachAdd()
    {
        if (Request::isGet()) {
            $data = AttachmentService::add();
        } else {
            $param['name'] = Request::param('name/s', '');
            $param['path'] = Request::param('path/s', '');
            $param['size'] = Request::param('size/d', 0);
            $param['mime'] = Request::param('mime/s', '');
            $param['remark'] = Request::param('remark/s', '');

            $data = AttachmentService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 附件管理修改
     */
    public function attachEdit()
    {
        $param['attachment_id'] = Request::param('attachment_id/d', '');

        if (Request::isGet()) {
            $data = AttachmentService::edit($param);
        } else {
            $param['name'] = Request::param('name/s', '');
            $param['path'] = Request::param('path/s', '');
            $param['size'] = Request::param('size/d', 0);
            $param['mime'] = Request::param('mime/s', '');
            $param['remark'] = Request::param('remark/s', '');

            $data = AttachmentService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 附件管理删除
     */
    public function attachDele()
    {
        $param['attachment_id'] = Request::param('attachment_id/d', '');

        $data = AttachmentService::dele($param['attachment_id']);

        return success($data);
    }

    /**
     * 附件管理是否禁用
     */
    public function attachDisable()
    {
        $param['attachment_id']      = Request::param('attachment_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = AttachmentService::disable($param);

        return success($data);
    }
}
