<?php
/*
 * @Description  : 奖池
 */

namespace app\admin\service;

use think\facade\Db;

class PrizeService
{
    /**
     * 奖池列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'prize_id,prize_name,goods_name,image,rate,stock,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'prize_id' => 'desc'];
        }

        $count = Db::name('prize')->where($where)->count('prize_id');

        $list = Db::name('prize')
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
     * 奖池信息
     */
    public static function info($id)
    {
        $info = Db::name('prize')->where('prize_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 奖池添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['prize_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('prize')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['prize_id'] = $id;

        return $param;
    }

    /**
     * 奖池修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['prize_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['prize_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('prize')->where('prize_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['prize_id'] = $id;

        return $param;
    }

    /**
     * 奖池删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('prize')->where('prize_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['prize_id' => $id];
    }

    /**
     * 奖池是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('prize')->where('prize_id', $param['prize_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
