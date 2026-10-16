<?php
/*
 * @Description  : 弹窗配置
 */

namespace app\admin\service;

use think\facade\Db;

class PopupService
{
    /**
     * 弹窗配置列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'popup_id,title,image,url,position,start_time,end_time,status,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'popup_id' => 'desc'];
        }

        $count = Db::name('popup')->where($where)->count('popup_id');

        $list = Db::name('popup')
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
     * 弹窗配置信息
     */
    public static function info($id)
    {
        $info = Db::name('popup')->where('popup_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 弹窗配置添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['popup_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('popup')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['popup_id'] = $id;

        return $param;
    }

    /**
     * 弹窗配置修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['popup_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['popup_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('popup')->where('popup_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['popup_id'] = $id;

        return $param;
    }

    /**
     * 弹窗配置删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('popup')->where('popup_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['popup_id' => $id];
    }

    /**
     * 弹窗配置是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('popup')->where('popup_id', $param['popup_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
