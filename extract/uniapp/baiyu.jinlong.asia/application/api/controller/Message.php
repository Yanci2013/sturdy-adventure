<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 消息接口
 */
class Message extends Api
{
    protected $noNeedRight = '*';

    /**
     * 获取消息列表
     */
    public function list()
    {
        $orderId = $this->request->get('order_id/d', 0);
        $page = $this->request->get('page/d', 1);
        $limit = $this->request->get('limit/d', 20);
        
        // 如果指定了订单ID，只查询该订单的消息
        if ($orderId) {
            $where = ['order_id' => $orderId];
            $list = Db::name('message')
                ->where($where)
                ->order('createtime desc')
                ->page($page, $limit)
                ->select();
        } else {
            // 否则查询与当前用户相关的所有消息（使用闭包构建OR条件）
            $list = Db::name('message')
                ->where(function($query) {
                    $query->where('from_user_id', $this->auth->id)
                          ->whereOr('to_user_id', $this->auth->id);
                })
                ->order('createtime desc')
                ->page($page, $limit)
                ->select();
        }
        
        // 检查COS是否启用
        $useCos = is_cos_enabled();
        
        // 关联查询用户信息
        foreach ($list as &$item) {
            $fromUser = Db::name('user')->where('id', $item['from_user_id'])->find();
            $toUser = Db::name('user')->where('id', $item['to_user_id'])->find();
            
            // 处理头像URL
            $fromAvatar = '';
            if (!empty($fromUser['avatar'])) {
                $fromAvatar = $useCos ? cdnurl($fromUser['avatar'], true) : request()->domain() . $fromUser['avatar'];
            }
            
            $toAvatar = '';
            if (!empty($toUser['avatar'])) {
                $toAvatar = $useCos ? cdnurl($toUser['avatar'], true) : request()->domain() . $toUser['avatar'];
            }
            
            $item['from_user'] = [
                'id' => $fromUser['id'],
                'nickname' => $fromUser['nickname'],
                'avatar' => $fromAvatar
            ];
            $item['to_user'] = [
                'id' => $toUser['id'],
                'nickname' => $toUser['nickname'],
                'avatar' => $toAvatar
            ];
        }
        
        // 计算总数
        if ($orderId) {
            $total = Db::name('message')->where($where)->count();
        } else {
            $total = Db::name('message')
                ->where(function($query) {
                    $query->where('from_user_id', $this->auth->id)
                          ->whereOr('to_user_id', $this->auth->id);
                })
                ->count();
        }
        
        $this->success('获取成功', [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);
    }

    /**
     * 发送消息
     */
    public function send()
    {
        $toUserId = $this->request->post('to_user_id/d', 0);
        $orderId = $this->request->post('order_id/d', 0);
        $content = $this->request->post('content', '');
        $msgType = $this->request->post('msg_type', 'text');
        
        if (!$toUserId || !$content) {
            $this->error('参数错误');
        }
        
        // 检查聊天解锁（只针对陪玩端用户，点单端 role_type=user 不检测）
        $userInfo = Db::name('user')->where('id', $this->auth->id)->find();
        $isClientUser = ($userInfo['role_type'] ?? '') === 'user'; // 点单端用户
        $unlockConfig = Db::name('unlock_merchant_config')->where('id', 1)->where('status', 'normal')->find();
        if (!$isClientUser && $unlockConfig && $unlockConfig['require_chat_unlock'] == 1) {
            $remainingTimes = intval($userInfo['chat_unlock_times_remaining'] ?? 0);
            if ($remainingTimes <= 0) {
                $this->error('消息服务次数已用完，请续费', [
                    'need_chat_unlock' => true,
                    'chat_unlock_price' => floatval($unlockConfig['chat_unlock_price']),
                    'chat_unlock_times' => intval($unlockConfig['chat_unlock_times'])
                ]);
            }
        }
        
        $data = [
            'from_user_id' => $this->auth->id,
            'to_user_id' => $toUserId,
            'order_id' => $orderId,
            'content' => $content,
            'msg_type' => $msgType,
            'is_read' => 0,
            'createtime' => time()
        ];
        
        $messageId = Db::name('message')->insertGetId($data);
        
        // 发送成功后扣除聊天次数（只针对陪玩端用户）
        if (!$isClientUser && $unlockConfig && $unlockConfig['require_chat_unlock'] == 1) {
            Db::name('user')->where('id', $this->auth->id)->where('chat_unlock_times_remaining', '>', 0)->setDec('chat_unlock_times_remaining', 1);
        }
        
        $this->success('发送成功', ['message_id' => $messageId]);
    }

    /**
     * 标记消息已读
     */
    public function read()
    {
        $messageId = $this->request->post('id/d', 0);
        
        if (!$messageId) {
            $this->error('消息ID不能为空');
        }
        
        $message = Db::name('message')->where('id', $messageId)->find();
        
        if (!$message) {
            $this->error('消息不存在');
        }
        
        // 只能标记发给自己的消息为已读
        if ($message['to_user_id'] != $this->auth->id) {
            $this->error('无权操作');
        }
        
        Db::name('message')->where('id', $messageId)->update([
            'is_read' => 1
        ]);
        
        $this->success('标记成功');
    }

    /**
     * 批量标记消息已读（按订单和对方用户）
     * @ApiMethod (POST)
     * @ApiParams (name="order_id", type="integer", required=true, description="订单ID")
     * @ApiParams (name="from_user_id", type="integer", required=true, description="发送消息的用户ID")
     */
    public function markRead()
    {
        $orderId = $this->request->post('order_id/d', 0);
        $fromUserId = $this->request->post('from_user_id/d', 0);
        
        if (!$orderId || !$fromUserId) {
            $this->error('参数错误');
        }
        
        // 标记该订单中，对方发给自己的所有未读消息为已读
        Db::name('message')
            ->where('order_id', $orderId)
            ->where('from_user_id', $fromUserId)
            ->where('to_user_id', $this->auth->id)
            ->where('is_read', 0)
            ->update([
                'is_read' => 1
            ]);
        
        $this->success('标记成功');
    }

    /**
     * 获取未读消息数量
     */
    public function unreadCount()
    {
        $count = Db::name('message')
            ->where('to_user_id', $this->auth->id)
            ->where('is_read', 0)
            ->count();
        
        $this->success('获取成功', ['count' => $count]);
    }
}

