<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 系统通知接口
 */
class Notification extends Api
{
    protected $noNeedRight = '*';

    /**
     * 获取通知列表
     * @ApiMethod (GET)
     */
    public function list()
    {
        $page = $this->request->get('page/d', 1);
        $limit = $this->request->get('limit/d', 20);
        
        $where = ['user_id' => $this->auth->id];
        
        $list = Db::name('system_notification')
            ->where($where)
            ->order('createtime desc')
            ->page($page, $limit)
            ->select();
        
        // 关联查询触发通知的用户信息
        $useCos = is_cos_enabled();
        
        foreach ($list as &$item) {
            $fromUser = Db::name('user')->where('id', $item['from_user_id'])->find();
            $item['from_user'] = [
                'id' => $fromUser['id'] ?? 0,
                'nickname' => $fromUser['nickname'] ?? '用户',
                'avatar' => $this->processImageUrl($fromUser['avatar'] ?? '', $useCos)
            ];
        }
        unset($item);
        
        $total = Db::name('system_notification')->where($where)->count();
        $unreadCount = Db::name('system_notification')
            ->where($where)
            ->where('is_read', 0)
            ->count();
        
        $this->success('获取成功', [
            'list' => $list,
            'total' => $total,
            'unread_count' => $unreadCount,
            'page' => $page,
            'limit' => $limit
        ]);
    }

    /**
     * 获取未读通知数量
     * @ApiMethod (GET)
     */
    public function unreadCount()
    {
        $count = Db::name('system_notification')
            ->where('user_id', $this->auth->id)
            ->where('is_read', 0)
            ->count();
        
        $this->success('获取成功', ['count' => $count]);
    }

    /**
     * 标记通知为已读
     * @ApiMethod (POST)
     */
    public function markRead()
    {
        $id = $this->request->post('id/d', 0);
        
        if ($id > 0) {
            // 标记单条通知为已读
            Db::name('system_notification')
                ->where('id', $id)
                ->where('user_id', $this->auth->id)
                ->update([
                    'is_read' => 1,
                    'updatetime' => time()
                ]);
        } else {
            // 标记所有通知为已读
            Db::name('system_notification')
                ->where('user_id', $this->auth->id)
                ->where('is_read', 0)
                ->update([
                    'is_read' => 1,
                    'updatetime' => time()
                ]);
        }
        
        $this->success('标记成功');
    }

    /**
     * 处理图片URL
     */
    private function processImageUrl($url, $useCos)
    {
        if (empty($url)) {
            return '';
        }
        
        // 如果已经是完整URL，直接返回
        if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
            return $url;
        }
        
        if ($useCos) {
            return cdnurl($url, true);
        } else {
            // 确保 $url 以 / 开头
            if (strpos($url, '/') !== 0) {
                $url = '/' . $url;
            }
            $domain = request()->domain();
            $domain = rtrim($domain, '/');
            return $domain . $url;
        }
    }
}

