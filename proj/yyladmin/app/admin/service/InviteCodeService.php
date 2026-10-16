<?php
/*
 * @Description  : 邀请码管理
 */

namespace app\admin\service;

use think\facade\Db;

class InviteCodeService
{
    /**
     * 邀请码管理列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'code_id,code,member_name,used_num,max_num,status,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'code_id' => 'desc'];
        }

        $count = Db::name('invite_code')->where($where)->count('code_id');

        $list = Db::name('invite_code')
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
     * 邀请码管理信息
     */
    public static function info($id)
    {
        $info = Db::name('invite_code')->where('code_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 邀请码管理添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['code_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('invite_code')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['code_id'] = $id;

        return $param;
    }

    /**
     * 邀请码管理修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['code_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['code_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('invite_code')->where('code_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['code_id'] = $id;

        return $param;
    }

    /**
     * 邀请码管理删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('invite_code')->where('code_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['code_id' => $id];
    }

    /**
     * 邀请码管理是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('invite_code')->where('code_id', $param['code_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
