<?php
/*
 * @Description  : 轮播图
 */

namespace app\admin\service;

use think\facade\Db;

class BannerService
{
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'banner_id,title,image,url,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'banner_id' => 'desc'];
        }

        $count = Db::name('banner')->where($where)->count('banner_id');

        $list = Db::name('banner')
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

    public static function info($banner_id)
    {
        $info = Db::name('banner')->where('banner_id', $banner_id)->find();

        if (empty($info)) {
            exception('轮播图不存在：' . $banner_id);
        }

        return $info;
    }

    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['banner_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('banner')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['banner_id'] = $id;

        return $param;
    }

    public static function edit($param, $method = 'get')
    {
        $banner_id = $param['banner_id'];

        if ($method == 'get') {
            return self::info($banner_id);
        }

        unset($param['banner_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('banner')->where('banner_id', $banner_id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['banner_id'] = $banner_id;

        return $param;
    }

    public static function dele($banner_id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('banner')->where('banner_id', $banner_id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['banner_id' => $banner_id];
    }

    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('banner')->where('banner_id', $param['banner_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
