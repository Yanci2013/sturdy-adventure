<?php
/*
 * @Description  : 会员黑名单
 */

namespace app\admin\service;

use think\facade\Db;

class MemberBlacklistService
{
    /**
     * 会员黑名单列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'black_id,member_name,phone,reason,operator,status,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'black_id' => 'desc'];
        }

        $count = Db::name('member_blacklist')->where($where)->count('black_id');

        $list = Db::name('member_blacklist')
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
     * 会员黑名单信息
     */
    public static function info($id)
    {
        $info = Db::name('member_blacklist')->where('black_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 会员黑名单添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['black_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('member_blacklist')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['black_id'] = $id;

        return $param;
    }

    /**
     * 会员黑名单修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['black_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['black_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('member_blacklist')->where('black_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['black_id'] = $id;

        return $param;
    }

    /**
     * 会员黑名单删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('member_blacklist')->where('black_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['black_id' => $id];
    }

    /**
     * 会员黑名单是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('member_blacklist')->where('black_id', $param['black_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
