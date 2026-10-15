<?php
/*
 * @Description  : 分类管理
 */

namespace app\admin\service;

use think\facade\Db;

class CategoryService
{
    /**
     * 分类管理列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'category_id,category_name,image,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'category_id' => 'desc'];
        }

        $count = Db::name('category')->where($where)->count('category_id');

        $list = Db::name('category')
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
     * 分类管理信息
     */
    public static function info($id)
    {
        $info = Db::name('category')->where('category_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 分类管理添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['category_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('category')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['category_id'] = $id;

        return $param;
    }

    /**
     * 分类管理修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['category_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['category_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('category')->where('category_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['category_id'] = $id;

        return $param;
    }

    /**
     * 分类管理删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('category')->where('category_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['category_id' => $id];
    }

    /**
     * 分类管理是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('category')->where('category_id', $param['category_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
