<?php
/*
 * @Description  : 邀请奖励配置
 */

namespace app\admin\service;

use think\facade\Db;

class InviteRewardService
{
    /**
     * 邀请奖励配置列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'reward_id,reward_name,reward_type,reward_money,condition,status,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'reward_id' => 'desc'];
        }

        $count = Db::name('invite_reward')->where($where)->count('reward_id');

        $list = Db::name('invite_reward')
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
     * 邀请奖励配置信息
     */
    public static function info($id)
    {
        $info = Db::name('invite_reward')->where('reward_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 邀请奖励配置添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['reward_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('invite_reward')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['reward_id'] = $id;

        return $param;
    }

    /**
     * 邀请奖励配置修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['reward_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['reward_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('invite_reward')->where('reward_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['reward_id'] = $id;

        return $param;
    }

    /**
     * 邀请奖励配置删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('invite_reward')->where('reward_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['reward_id' => $id];
    }

    /**
     * 邀请奖励配置是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('invite_reward')->where('reward_id', $param['reward_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
