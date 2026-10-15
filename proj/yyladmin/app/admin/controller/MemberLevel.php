<?php
/*
 * @Description  : 会员等级管理
 */

namespace app\admin\controller;

use think\facade\Request;
use app\admin\service\MemberLevelService;

class MemberLevel
{
    /**
     * 会员等级管理列表
     */
    public function levelList()
    {
        $page       = Request::param('page/d', 1);
        $limit      = Request::param('limit/d', 10);
        $sort_field = Request::param('sort_field/s', '');
        $sort_type  = Request::param('sort_type/s', '');
        $keyword    = Request::param('level_name/s', '');

        $where = [];
        if ($keyword) {
            $where[] = ['level_name', 'like', '%' . $keyword . '%'];
        }

        $order = [];
        if ($sort_field && $sort_type) {
            $order = [$sort_field => $sort_type];
        }

        $data = MemberLevelService::list($where, $page, $limit, $order);

        return success($data);
    }

    /**
     * 会员等级管理信息
     */
    public function levelInfo()
    {
        $param['level_id'] = Request::param('level_id/d', '');

        $data = MemberLevelService::info($param['level_id']);

        return success($data);
    }

    /**
     * 会员等级管理添加
     */
    public function levelAdd()
    {
        if (Request::isGet()) {
            $data = MemberLevelService::add();
        } else {
            $param['level_name'] = Request::param('level_name/s', '');
            $param['level_img'] = Request::param('level_img/s', '');
            $param['level_num'] = Request::param('level_num/d', 0);
            $param['discount'] = Request::param('discount/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = MemberLevelService::add($param, 'post');
        }

        return success($data);
    }

    /**
     * 会员等级管理修改
     */
    public function levelEdit()
    {
        $param['level_id'] = Request::param('level_id/d', '');

        if (Request::isGet()) {
            $data = MemberLevelService::edit($param);
        } else {
            $param['level_name'] = Request::param('level_name/s', '');
            $param['level_img'] = Request::param('level_img/s', '');
            $param['level_num'] = Request::param('level_num/d', 0);
            $param['discount'] = Request::param('discount/d', 0);
            $param['remark'] = Request::param('remark/s', '');

            $data = MemberLevelService::edit($param, 'post');
        }

        return success($data);
    }

    /**
     * 会员等级管理删除
     */
    public function levelDele()
    {
        $param['level_id'] = Request::param('level_id/d', '');

        $data = MemberLevelService::dele($param['level_id']);

        return success($data);
    }

    /**
     * 会员等级管理是否禁用
     */
    public function levelDisable()
    {
        $param['level_id']      = Request::param('level_id/d', '');
        $param['is_disable'] = Request::param('is_disable/d', 0);

        $data = MemberLevelService::disable($param);

        return success($data);
    }
}
