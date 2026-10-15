<?php
/*
 * @Description  : 会员等级管理
 */

namespace app\admin\service;

use think\facade\Db;

class MemberLevelService
{
    /**
     * 会员等级管理列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'level_id,level_name,level_img,level_num,discount,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'level_id' => 'desc'];
        }

        $count = Db::name('member_level')->where($where)->count('level_id');

        $list = Db::name('member_level')
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
     * 会员等级管理信息
     */
    public static function info($id)
    {
        $info = Db::name('member_level')->where('level_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 会员等级管理添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['level_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('member_level')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['level_id'] = $id;

        return $param;
    }

    /**
     * 会员等级管理修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['level_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['level_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('member_level')->where('level_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['level_id'] = $id;

        return $param;
    }

    /**
     * 会员等级管理删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('member_level')->where('level_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['level_id' => $id];
    }

    /**
     * 会员等级管理是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('member_level')->where('level_id', $param['level_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
