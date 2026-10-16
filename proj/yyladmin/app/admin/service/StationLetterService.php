<?php
/*
 * @Description  : 站内信管理
 */

namespace app\admin\service;

use think\facade\Db;

class StationLetterService
{
    /**
     * 站内信管理列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'letter_id,title,content,receiver,sender,is_read,status,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'letter_id' => 'desc'];
        }

        $count = Db::name('station_letter')->where($where)->count('letter_id');

        $list = Db::name('station_letter')
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
     * 站内信管理信息
     */
    public static function info($id)
    {
        $info = Db::name('station_letter')->where('letter_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 站内信管理添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['letter_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('station_letter')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['letter_id'] = $id;

        return $param;
    }

    /**
     * 站内信管理修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['letter_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['letter_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('station_letter')->where('letter_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['letter_id'] = $id;

        return $param;
    }

    /**
     * 站内信管理删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('station_letter')->where('letter_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['letter_id' => $id];
    }

    /**
     * 站内信管理是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('station_letter')->where('letter_id', $param['letter_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
