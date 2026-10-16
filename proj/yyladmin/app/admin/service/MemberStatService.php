<?php
/*
 * @Description  : 会员统计
 */

namespace app\admin\service;

use think\facade\Db;

class MemberStatService
{
    /**
     * 会员统计列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'stat_id,stat_date,total_member,new_member,active_member,growth_rate,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'stat_id' => 'desc'];
        }

        $count = Db::name('member_stat')->where($where)->count('stat_id');

        $list = Db::name('member_stat')
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
     * 会员统计信息
     */
    public static function info($id)
    {
        $info = Db::name('member_stat')->where('stat_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 会员统计添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['stat_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('member_stat')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['stat_id'] = $id;

        return $param;
    }

    /**
     * 会员统计修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['stat_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['stat_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('member_stat')->where('stat_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['stat_id'] = $id;

        return $param;
    }

    /**
     * 会员统计删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('member_stat')->where('stat_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['stat_id' => $id];
    }

    /**
     * 会员统计是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('member_stat')->where('stat_id', $param['stat_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
