<?php
/*
 * @Description  : 会员标签
 */

namespace app\admin\service;

use think\facade\Db;

class MemberTagService
{
    /**
     * 会员标签列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'tag_id,tag_name,color,member_num,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'tag_id' => 'desc'];
        }

        $count = Db::name('member_tag')->where($where)->count('tag_id');

        $list = Db::name('member_tag')
            ->field($field)
            ->where($where)
            ->page($page)
            ->limit($limit)
            ->order($order)
            ->select()
            ->toArray();

        $data['count'] = $count;
        $data['pages'] = ceil($count / $limit);
        $data['page']  = $page;
        $data['limit'] = $limit;
        $data['list']  = $list;

        return $data;
    }

    /**
     * 会员标签信息
     */
    public static function info($id)
    {
        $info = Db::name('member_tag')->where('tag_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 会员标签添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['tag_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('member_tag')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['tag_id'] = $id;

        return $param;
    }

    /**
     * 会员标签修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['tag_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['tag_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('member_tag')->where('tag_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['tag_id'] = $id;

        return $param;
    }

    /**
     * 会员标签删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('member_tag')->where('tag_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['tag_id' => $id];
    }

    /**
     * 会员标签是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('member_tag')->where('tag_id', $param['tag_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
