<?php
/*
 * @Description  : 会员标签
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\MemberTagService;

class MemberTag
{
    /**
     * 会员标签列表
     */
    public function memberTagList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('tag_name/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['tag_name', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = MemberTagService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 会员标签信息
     */
    public function memberTagInfo()
    {
        $param['tag_id'] = Request::param('tag_id/d', '');

        $data = MemberTagService::info($param['tag_id']);

        return success($data);
    }

    /**
     * 会员标签添加
     */
    public function memberTagAdd()
    {
        if (Request::isGet()) {
            $data = MemberTagService::add();
        } else {
            $param['tag_name'] = Request::param('tag_name/s', '');
            $param['color'] = Request::param('color/s', '');
            $param['member_num'] = Request::param('member_num/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = MemberTagService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 会员标签修改
     */
    public function memberTagEdit()
    {
        $param['tag_id'] = Request::param('tag_id/d', '');

        if (Request::isGet()) {
            $data = MemberTagService::edit($param);
        } else {
            $param['tag_name'] = Request::param('tag_name/s', '');
            $param['color'] = Request::param('color/s', '');
            $param['member_num'] = Request::param('member_num/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = MemberTagService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 会员标签删除
     */
    public function memberTagDele()
    {
        $param['tag_id'] = Request::param('tag_id/d', '');

        $data = MemberTagService::dele($param['tag_id']);

        return success($data);
    }

    /**
     * 会员标签是否禁用
     */
    public function memberTagDisable()
    {
        $param['tag_id']      = Request::param('tag_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = MemberTagService::disable($param);

        return success($data);
    }
}
