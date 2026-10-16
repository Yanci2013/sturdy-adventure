<?php
/*
 * @Description  : 商品审核
 */

namespace app\admin\service;

use think\facade\Db;

class GoodsAuditService
{
    /**
     * 商品审核列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'audit_id,goods_name,player_name,price,images,status,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'audit_id' => 'desc'];
        }

        $count = Db::name('goods_audit')->where($where)->count('audit_id');

        $list = Db::name('goods_audit')
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
     * 商品审核信息
     */
    public static function info($id)
    {
        $info = Db::name('goods_audit')->where('audit_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 商品审核添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['audit_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('goods_audit')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['audit_id'] = $id;

        return $param;
    }

    /**
     * 商品审核修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['audit_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['audit_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('goods_audit')->where('audit_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['audit_id'] = $id;

        return $param;
    }

    /**
     * 商品审核删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('goods_audit')->where('audit_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['audit_id' => $id];
    }

    /**
     * 商品审核是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('goods_audit')->where('audit_id', $param['audit_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
