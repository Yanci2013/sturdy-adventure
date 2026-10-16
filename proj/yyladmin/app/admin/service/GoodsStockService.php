<?php
/*
 * @Description  : 商品库存
 */

namespace app\admin\service;

use think\facade\Db;

class GoodsStockService
{
    /**
     * 商品库存列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'stock_id,goods_name,sku,stock,warning_stock,unit,status,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'stock_id' => 'desc'];
        }

        $count = Db::name('goods_stock')->where($where)->count('stock_id');

        $list = Db::name('goods_stock')
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
     * 商品库存信息
     */
    public static function info($id)
    {
        $info = Db::name('goods_stock')->where('stock_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 商品库存添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['stock_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('goods_stock')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['stock_id'] = $id;

        return $param;
    }

    /**
     * 商品库存修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['stock_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['stock_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('goods_stock')->where('stock_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['stock_id'] = $id;

        return $param;
    }

    /**
     * 商品库存删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('goods_stock')->where('stock_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['stock_id' => $id];
    }

    /**
     * 商品库存是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('goods_stock')->where('stock_id', $param['stock_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
