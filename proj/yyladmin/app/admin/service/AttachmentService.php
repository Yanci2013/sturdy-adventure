<?php
/*
 * @Description  : 附件管理
 */

namespace app\admin\service;

use think\facade\Db;

class AttachmentService
{
    /**
     * 附件管理列表
     */
    public static function list($where = [], $page = 1, $limit = 10, $order = [], $field = '')
    {
        if (empty($field)) {
            $field = 'attachment_id,name,path,size,mime,remark,sort,is_disable,create_time';
        }

        $where[] = ['is_delete', '=', 0];

        if (empty($order)) {
            $order = ['sort' => 'desc', 'attachment_id' => 'desc'];
        }

        $count = Db::name('attachment')->where($where)->count('attachment_id');

        $list = Db::name('attachment')
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
     * 附件管理信息
     */
    public static function info($id)
    {
        $info = Db::name('attachment')->where('attachment_id', $id)->find();

        if (empty($info)) {
            exception('数据不存在：' . $id);
        }

        return $info;
    }

    /**
     * 附件管理添加
     */
    public static function add($param = [], $method = 'get')
    {
        if ($method == 'get') {
            return [];
        }

        unset($param['attachment_id']);
        $param['create_time'] = date('Y-m-d H:i:s');

        $id = Db::name('attachment')->insertGetId($param);

        if (empty($id)) {
            exception();
        }

        $param['attachment_id'] = $id;

        return $param;
    }

    /**
     * 附件管理修改
     */
    public static function edit($param, $method = 'get')
    {
        $id = $param['attachment_id'];

        if ($method == 'get') {
            return self::info($id);
        }

        unset($param['attachment_id']);
        $param['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('attachment')->where('attachment_id', $id)->update($param);

        if ($res === false) {
            exception();
        }

        $param['attachment_id'] = $id;

        return $param;
    }

    /**
     * 附件管理删除
     */
    public static function dele($id)
    {
        $update['is_delete']   = 1;
        $update['delete_time'] = date('Y-m-d H:i:s');

        $res = Db::name('attachment')->where('attachment_id', $id)->update($update);

        if ($res === false) {
            exception();
        }

        return ['attachment_id' => $id];
    }

    /**
     * 附件管理是否禁用
     */
    public static function disable($param)
    {
        $update['is_disable']  = $param['is_disable'];
        $update['update_time'] = date('Y-m-d H:i:s');

        $res = Db::name('attachment')->where('attachment_id', $param['attachment_id'])->update($update);

        if ($res === false) {
            exception();
        }

        return $param;
    }
}
