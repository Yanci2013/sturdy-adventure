<?php
/*
 * @Description  : 客服会话记录
 */

namespace app\admin\service;

use think\facade\Db;

class CsSessionService
{
    /**
     * 客服会话记录列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'session_id,cs_name,member_name,content,duration,status,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'session_id' => 'desc'];
        }

        $count = Db::name('cs_session')->where($where)->count('session_id');

        $list = Db::name('cs_session')
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
     * 客服会话记录信息
     */
    public static function info($id)
    {
        $info = Db::name('cs_session')->where('session_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 客服会话记录添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['session_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('cs_session')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['session_id'] = $id;

        return $param;
    }

    /**
     * 客服会话记录修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['session_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['session_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('cs_session')->where('session_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['session_id'] = $id;

        return $param;
    }

    /**
     * 客服会话记录删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('cs_session')->where('session_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['session_id' => $id];
    }

    /**
     * 客服会话记录是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('cs_session')->where('session_id', $param['session_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
