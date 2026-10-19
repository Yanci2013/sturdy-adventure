<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;
use think\Exception;

/**
 * 会员接口
 */
class Member extends Api
{
    protected $noNeedLogin = ['typeList'];
    protected $noNeedRight = '*';

    /**
     * 获取会员类型列表
     */
    public function typeList()
    {
        $list = Db::name('member_type')
            ->where('status', 'normal')
            ->order('sort desc,id desc')
            ->select();
        
        $this->success('获取成功', $list);
    }

    /**
     * 购买会员
     */
    public function buy()
    {
        $memberTypeId = $this->request->post('member_type_id/d', 0);
        
        if (!$memberTypeId) {
            $this->error('会员类型ID不能为空');
        }
        
        $memberType = Db::name('member_type')->where('id', $memberTypeId)->find();
        
        if (!$memberType || $memberType['status'] != 'normal') {
            $this->error('会员类型不存在或已下架');
        }
        
        // 检查用户余额
        $user = Db::name('user')->where('id', $this->auth->id)->find();
        if ($user['money'] < $memberType['price']) {
            $this->error('余额不足');
        }
        
        Db::startTrans();
        try {
            // 扣除余额
            Db::name('user')->where('id', $this->auth->id)->setDec('money', $memberType['price']);
            
            // 计算会员到期时间
            $startTime = time();
            $endTime = $startTime + ($memberType['duration'] * 86400);
            
            // 检查是否已有会员
            $userMember = Db::name('user_member')
                ->where('user_id', $this->auth->id)
                ->where('status', 'normal')
                ->order('end_time desc')
                ->find();
            
            if ($userMember && $userMember['end_time'] > time()) {
                // 如果还有会员，从到期时间开始计算
                $startTime = $userMember['end_time'];
                $endTime = $startTime + ($memberType['duration'] * 86400);
            }
            
            // 创建会员记录
            Db::name('user_member')->insert([
                'user_id' => $this->auth->id,
                'member_type_id' => $memberTypeId,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => 'normal',
                'createtime' => time(),
                'updatetime' => time()
            ]);
            
            // 记录余额变动
            Db::name('user_money_log')->insert([
                'user_id' => $this->auth->id,
                'money' => -$memberType['price'],
                'before' => $user['money'],
                'after' => $user['money'] - $memberType['price'],
                'memo' => '购买会员：' . $memberType['name'],
                'createtime' => time()
            ]);
            
            Db::commit();
            $this->success('购买成功');
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    /**
     * 获取当前用户会员信息
     */
    public function info()
    {
        $userMember = Db::name('user_member')
            ->alias('um')
            ->join('fa_member_type mt', 'um.member_type_id = mt.id')
            ->where('um.user_id', $this->auth->id)
            ->where('um.status', 'normal')
            ->where('um.end_time', '>', time())
            ->field('um.*, mt.name as member_type_name, mt.privileges')
            ->order('um.end_time desc')
            ->find();
        
        if ($userMember) {
            $userMember['privileges'] = json_decode($userMember['privileges'], true);
        }
        
        $this->success('获取成功', $userMember ?: null);
    }
}

