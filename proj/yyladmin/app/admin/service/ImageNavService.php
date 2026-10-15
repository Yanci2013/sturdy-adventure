<?php
/*
 * @Description  : 图片导航
 */

namespace app\admin\service;

use think\facade\Db;

class ImageNavService
{
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'nav_id,name,image,url,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'nav_id' => 'desc'];
        }

        $count = Db::name('image_nav')->where($where)->count('nav_id');

        $list = Db::name('image_nav')
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

    public static function info($nav_id)
    {
        $info = Db::name('image_nav')->where('nav_id', $nav_id)->find();

        if (empty($info)) {
            exception('图片导航不存在：' . $nav_id);
        }

        return $info;
    }

    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['nav_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('image_nav')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['nav_id'] = $id;

        return $param;
    }

    public static function edit($param, $method = 'get')
    {
        $nav_id = $param['nav_id'];

        if ($method == 'get') {
            return self::info($nav_id);
        }

        unset($param['nav_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('image_nav')->where('nav_id', $nav_id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['nav_id'] = $nav_id;

        return $param;
    }

    public static function dele($nav_id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('image_nav')->where('nav_id', $nav_id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['nav_id' => $nav_id];
    }

    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('image_nav')->where('nav_id', $param['nav_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
