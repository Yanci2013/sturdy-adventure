<?php
/*
 * @Description  : 聚合登录
 */

namespace app\admin\service;

use think\facade\Db;

class OauthLoginService
{
    /**
     * 聚合登录列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'oauth_id,name,code,app_id,app_key,status,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'oauth_id' => 'desc'];
        }

        $count = Db::name('oauth_login')->where($where)->count('oauth_id');

        $list = Db::name('oauth_login')
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
     * 聚合登录信息
     */
    public static function info($id)
    {
        $info = Db::name('oauth_login')->where('oauth_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 聚合登录添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['oauth_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('oauth_login')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['oauth_id'] = $id;

        return $param;
    }

    /**
     * 聚合登录修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['oauth_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['oauth_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('oauth_login')->where('oauth_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['oauth_id'] = $id;

        return $param;
    }

    /**
     * 聚合登录删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('oauth_login')->where('oauth_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['oauth_id' => $id];
    }

    /**
     * 聚合登录是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('oauth_login')->where('oauth_id', $param['oauth_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
