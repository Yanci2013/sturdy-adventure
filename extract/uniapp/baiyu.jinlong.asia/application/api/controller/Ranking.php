<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 排行榜接口
 */
class Ranking extends Api
{
    protected $noNeedLogin = ['list', 'types'];
    protected $noNeedRight = '*';
    
    /**
     * 支持的角色类型
     */
    protected $allowedTypes = [
        'dashou' => '打手',
        'jindashou' => '金牌打手',
        'guanshi' => '管事',
        'zuzhang' => '组长',
        'zongguan' => '总管',
        'shangjia' => '商家',
        'jinshangjia' => '金牌商家',
        'kefu' => '客服',
        'shouhou' => '售后'
    ];
    
    /**
     * 不同角色的排行维度和单位
     */
    protected $rankingConfig = [
        // 管事、组长、总管 → 招人数
        'guanshi' => ['field' => 'recruit_count', 'label' => '招人数', 'unit' => '人'],
        'zuzhang' => ['field' => 'recruit_count', 'label' => '招人数', 'unit' => '人'],
        'zongguan' => ['field' => 'recruit_count', 'label' => '招人数', 'unit' => '人'],
        // 售后 → 处理订单数
        'shouhou' => ['field' => 'handle_count', 'label' => '处理订单', 'unit' => '单'],
        // 打手、金牌打手 → 接单金额
        'dashou' => ['field' => 'receive_amount', 'label' => '接单金额', 'unit' => '元'],
        'jindashou' => ['field' => 'receive_amount', 'label' => '接单金额', 'unit' => '元'],
        // 商家、金牌商家、客服 → 下单金额
        'shangjia' => ['field' => 'order_amount', 'label' => '下单金额', 'unit' => '元'],
        'jinshangjia' => ['field' => 'order_amount', 'label' => '下单金额', 'unit' => '元'],
        'kefu' => ['field' => 'order_amount', 'label' => '下单金额', 'unit' => '元'],
    ];

    /**
     * 获取排行榜类型列表
     */
    public function types()
    {
        $types = [];
        foreach ($this->allowedTypes as $key => $name) {
            $config = $this->rankingConfig[$key] ?? ['label' => '佣金', 'unit' => '元'];
            $types[] = [
                'value' => $key,
                'label' => $name,
                'rank_label' => $config['label'],
                'rank_unit' => $config['unit']
            ];
        }
        $this->success('获取成功', ['list' => $types]);
    }

    /**
     * 获取排行榜列表
     * @param string type 角色类型
     * @param string period 时间范围: day/week/month/all
     * @param int limit 限制数量
     */
    public function list()
    {
        $type = $this->request->get('type', 'dashou');
        $period = $this->request->get('period', 'all'); // day/week/month/all
        $limit = $this->request->get('limit/d', 50);
        
        // 验证角色类型
        if (!array_key_exists($type, $this->allowedTypes)) {
            $type = 'dashou';
        }
        
        // 验证时间范围
        if (!in_array($period, ['day', 'week', 'month', 'all'])) {
            $period = 'all';
        }
        
        // 获取时间范围
        $timeRange = $this->getTimeRange($period);
        
        // 根据角色类型获取排行数据
        $config = $this->rankingConfig[$type] ?? ['field' => 'receive_amount', 'label' => '接单金额', 'unit' => '元'];
        
        $list = $this->getRankingData($type, $config['field'], $timeRange, $limit);
        
        // 检查COS是否启用
        $useCos = is_cos_enabled();
        
        // 处理头像URL
        foreach ($list as &$item) {
            if (!empty($item['avatar'])) {
                if ($useCos) {
                    $item['avatar'] = cdnurl($item['avatar'], true);
                } else {
                    $item['avatar'] = request()->domain() . $item['avatar'];
                }
            }
            // 添加排行维度信息
            $item['rank_label'] = $config['label'];
            $item['rank_unit'] = $config['unit'];
        }
        unset($item);
        
        $this->success('获取成功', [
            'list' => $list,
            'type' => $type,
            'type_name' => $this->allowedTypes[$type] ?? $type,
            'period' => $period,
            'rank_label' => $config['label'],
            'rank_unit' => $config['unit']
        ]);
    }
    
    /**
     * 获取时间范围
     */
    private function getTimeRange($period)
    {
        $now = time();
        switch ($period) {
            case 'day':
                return [strtotime(date('Y-m-d')), $now];
            case 'week':
                // 本周一开始
                $weekStart = strtotime('monday this week');
                return [$weekStart, $now];
            case 'month':
                return [strtotime(date('Y-m-01')), $now];
            case 'all':
            default:
                return [0, $now];
        }
    }
    
    /**
     * 获取排行数据
     */
    private function getRankingData($type, $field, $timeRange, $limit)
    {
        switch ($field) {
            case 'recruit_count':
                // 招人数排行（管事、组长、总管）
                return $this->getRecruitRanking($type, $timeRange, $limit);
            case 'handle_count':
                // 处理订单数排行（售后）
                return $this->getHandleRanking($type, $timeRange, $limit);
            case 'receive_amount':
                // 接单金额排行（打手、金牌打手）
                return $this->getReceiveRanking($type, $timeRange, $limit);
            case 'order_amount':
                // 下单金额排行（商家、金牌商家、客服）
                return $this->getOrderRanking($type, $timeRange, $limit);
            default:
                return [];
        }
    }
    
    /**
     * 招人数排行（管事、组长、总管）
     * 统计下级用户数量
     */
    private function getRecruitRanking($type, $timeRange, $limit)
    {
        $query = Db::name('user')
            ->alias('leader')
            ->join('fa_user sub', 'sub.parent_id = leader.id')
            ->where('leader.role_type', $type)
            ->where('leader.status', 'normal');
        
        if ($timeRange[0] > 0) {
            $query->where('sub.createtime', '>=', $timeRange[0]);
        }
        if ($timeRange[1] > 0) {
            $query->where('sub.createtime', '<=', $timeRange[1]);
        }
        
        $list = $query
            ->field('leader.id as user_id, leader.nickname, leader.avatar, COUNT(sub.id) as rank_value')
            ->group('leader.id')
            ->order('rank_value desc')
            ->limit($limit)
            ->select();
        
        return $list ?: [];
    }
    
    /**
     * 处理订单数排行（售后）
     * 统计售后处理的订单数（从 order_intervene_log 表统计，按订单去重）
     */
    private function getHandleRanking($type, $timeRange, $limit)
    {
        // 统计售后处理的订单数（通过介入处理记录，按订单去重）
        $query = Db::name('user')
            ->alias('u')
            ->join('fa_order_intervene_log il', 'il.kefu_id = u.id', 'LEFT')
            ->where('u.role_type', $type)
            ->where('u.status', 'normal');
        
        if ($timeRange[0] > 0) {
            $query->where('il.createtime', '>=', $timeRange[0]);
        }
        if ($timeRange[1] > 0) {
            $query->where('il.createtime', '<=', $timeRange[1]);
        }
        
        $list = $query
            ->field('u.id as user_id, u.nickname, u.avatar, COUNT(DISTINCT il.order_id) as rank_value')
            ->group('u.id')
            ->order('rank_value desc')
            ->limit($limit)
            ->select();
        
        return $list ?: [];
    }
    
    /**
     * 接单金额排行（打手、金牌打手）
     * 统计完成订单的总金额
     */
    private function getReceiveRanking($type, $timeRange, $limit)
    {
        $query = Db::name('user')
            ->alias('u')
            ->join('fa_order o', 'o.dashou_id = u.id AND o.status = "yiwancheng"', 'LEFT')
            ->where('u.role_type', $type)
            ->where('u.status', 'normal');
        
        if ($timeRange[0] > 0) {
            $query->where('o.wancheng_time', '>=', $timeRange[0]);
        }
        if ($timeRange[1] > 0) {
            $query->where('o.wancheng_time', '<=', $timeRange[1]);
        }
        
        $list = $query
            ->field('u.id as user_id, u.nickname, u.avatar, IFNULL(SUM(o.price), 0) as rank_value')
            ->group('u.id')
            ->having('rank_value > 0')
            ->order('rank_value desc')
            ->limit($limit)
            ->select();
        
        return $list ?: [];
    }
    
    /**
     * 下单金额排行（商家、金牌商家、客服）
     * 统计发布订单的总金额
     */
    private function getOrderRanking($type, $timeRange, $limit)
    {
        $query = Db::name('user')
            ->alias('u')
            ->join('fa_order o', 'o.shangjia_id = u.id', 'LEFT')
            ->where('u.role_type', $type)
            ->where('u.status', 'normal');
        
        if ($timeRange[0] > 0) {
            $query->where('o.createtime', '>=', $timeRange[0]);
        }
        if ($timeRange[1] > 0) {
            $query->where('o.createtime', '<=', $timeRange[1]);
        }
        
        $list = $query
            ->field('u.id as user_id, u.nickname, u.avatar, IFNULL(SUM(o.price), 0) as rank_value')
            ->group('u.id')
            ->having('rank_value > 0')
            ->order('rank_value desc')
            ->limit($limit)
            ->select();
        
        return $list ?: [];
    }
}

