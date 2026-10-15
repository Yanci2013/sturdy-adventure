<?php
/*
 * @Description  : 商品
 */

namespace app\admin\service;

use think\facade\Db;

class GoodsService
{
    /**
     * 商品列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'goods_id,goods_name,category_name,image,price,stock,unit,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'goods_id' => 'desc'];
        }

        $count = Db::name('goods')->where($where)->count('goods_id');

        $list = Db::name('goods')
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
     * 商品信息
     */
    public static function info($id)
    {
        $info = Db::name('goods')->where('goods_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 商品添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['goods_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('goods')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['goods_id'] = $id;

        return $param;
    }

    /**
     * 商品修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['goods_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['goods_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('goods')->where('goods_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['goods_id'] = $id;

        return $param;
    }

    /**
     * 商品删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('goods')->where('goods_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['goods_id' => $id];
    }

    /**
     * 商品是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('goods')->where('goods_id', $param['goods_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
