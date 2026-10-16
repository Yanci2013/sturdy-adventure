<?php
/*
 * @Description  : 登录安全管理
 */

namespace app\admin\service;

use think\facade\Db;

class LoginSecurityService
{
    /**
     * 登录安全管理列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'security_id,username,ip,region,device,login_time,status,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'security_id' => 'desc'];
        }

        $count = Db::name('login_security')->where($where)->count('security_id');

        $list = Db::name('login_security')
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
     * 登录安全管理信息
     */
    public static function info($id)
    {
        $info = Db::name('login_security')->where('security_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 登录安全管理添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['security_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('login_security')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['security_id'] = $id;

        return $param;
    }

    /**
     * 登录安全管理修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['security_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['security_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('login_security')->where('security_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['security_id'] = $id;

        return $param;
    }

    /**
     * 登录安全管理删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('login_security')->where('security_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['security_id' => $id];
    }

    /**
     * 登录安全管理是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('login_security')->where('security_id', $param['security_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
