<?php
/*
 * @Description  : 邀请记录
 */

namespace app\admin\service;

use think\facade\Db;

class InviteRecordService
{
    /**
     * 邀请记录列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'record_id,invite_code,inviter,invitee,ip,status,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'record_id' => 'desc'];
        }

        $count = Db::name('invite_record')->where($where)->count('record_id');

        $list = Db::name('invite_record')
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
     * 邀请记录信息
     */
    public static function info($id)
    {
        $info = Db::name('invite_record')->where('record_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 邀请记录添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['record_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('invite_record')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['record_id'] = $id;

        return $param;
    }

    /**
     * 邀请记录修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['record_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['record_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('invite_record')->where('record_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['record_id'] = $id;

        return $param;
    }

    /**
     * 邀请记录删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('invite_record')->where('record_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['record_id' => $id];
    }

    /**
     * 邀请记录是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('invite_record')->where('record_id', $param['record_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
