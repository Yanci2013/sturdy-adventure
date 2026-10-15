<?php
/*
 * @Description  : 公告管理
 */

namespace app\admin\service;

use think\facade\Db;

class NoticeService
{
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'notice_id,title,content,type,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'notice_id' => 'desc'];
        }

        $count = Db::name('notice')->where($where)->count('notice_id');

        $list = Db::name('notice')
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

    public static function info($notice_id)
    {
        $info = Db::name('notice')->where('notice_id', $notice_id)->find();

        if (empty($info)) {
            exception('公告不存在：' . $notice_id);
        }

        return $info;
    }

    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['notice_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('notice')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['notice_id'] = $id;

        return $param;
    }

    public static function edit($param, $method = 'get')
    {
        $notice_id = $param['notice_id'];

        if ($method == 'get') {
            return self::info($notice_id);
        }

        unset($param['notice_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('notice')->where('notice_id', $notice_id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['notice_id'] = $notice_id;

        return $param;
    }

    public static function dele($notice_id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('notice')->where('notice_id', $notice_id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['notice_id' => $notice_id];
    }

    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('notice')->where('notice_id', $param['notice_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
