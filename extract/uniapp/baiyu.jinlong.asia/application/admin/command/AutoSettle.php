<?php

namespace app\admin\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Exception;

/**
 * 自动结算待结算订单命令
 * 
 * 使用方法：
 * php think AutoSettle
 * 
 * 建议配置定时任务（crontab）每小时执行一次：
 * 0 * * * * cd /path/to/project && php think AutoSettle >> /var/log/auto_settle.log 2>&1
 */
class AutoSettle extends Command
{
    protected function configure()
    {
        $this->setName('AutoSettle')
            ->setDescription('自动结算待结算订单');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('开始执行自动结算任务...');
        $output->writeln('当前时间: ' . date('Y-m-d H:i:s'));
        
        // 获取系统配置
        $systemConfig = Db::name('system_config')->where('id', 1)->find();
        $autoSettleHours = isset($systemConfig['auto_settle_hours']) ? intval($systemConfig['auto_settle_hours']) : 0;
        
        if ($autoSettleHours <= 0) {
            $output->writeln('自动结算时间未设置或为0，跳过自动结算');
            return;
        }
        
        $output->writeln("自动结算时间设置: {$autoSettleHours} 小时");
        
        // 计算超时时间点
        $timeoutTimestamp = time() - ($autoSettleHours * 3600);
        
        // 查找需要自动结算的订单
        // 条件：状态为待结算(daijiesuan)，且提交证明时间/状态更新时间超过指定时长
        $orders = Db::name('order')
            ->where('status', 'daijiesuan')
            ->where('updatetime', '<', $timeoutTimestamp)
            ->select();
        
        $settledCount = 0;
        $errorCount = 0;
        
        foreach ($orders as $order) {
            Db::startTrans();
            try {
                // 再次检查订单状态（加锁）
                $orderCheck = Db::name('order')->where('id', $order['id'])->lock(true)->find();
                if (!$orderCheck || $orderCheck['status'] != 'daijiesuan') {
                    Db::rollback();
                    continue;
                }
                
                // 更新订单状态为已完成
                Db::name('order')->where('id', $order['id'])->update([
                    'status' => 'yiwancheng',
                    'jiesuan_time' => time(),
                    'updatetime' => time()
                ]);
                
                // 创建结算记录
                Db::name('settlement_log')->insert([
                    'order_id' => $order['id'],
                    'shangjia_id' => $order['shangjia_id'],
                    'dashou_id' => $order['dashou_id'],
                    'amount' => $order['price'],
                    'createtime' => time()
                ]);
                
                // 增加打手佣金
                Db::name('commission_log')->insert([
                    'user_id' => $order['dashou_id'],
                    'order_id' => $order['id'],
                    'amount' => $order['price'],
                    'type' => 'income',
                    'memo' => '订单自动结算（超时' . $autoSettleHours . '小时）',
                    'createtime' => time()
                ]);
                
                // 更新打手余额
                Db::name('user')->where('id', $order['dashou_id'])->setInc('money', $order['price']);
                
                // 推广分佣逻辑
                $dashouInfo = Db::name('user')->where('id', $order['dashou_id'])->find();
                if ($dashouInfo && $dashouInfo['promote_parent_id'] > 0) {
                    // 获取系统配置的分佣比例
                    $commissionRate = floatval($systemConfig['order_commission_rate'] ?? 0);
                    
                    if ($commissionRate > 0) {
                        // 计算分佣金额（订单金额 * 分佣比例 / 100）
                        $commissionAmount = round($order['price'] * $commissionRate / 100, 2);
                        
                        if ($commissionAmount > 0) {
                            // 检查推广上级是否存在
                            $promoteParent = Db::name('user')->where('id', $dashouInfo['promote_parent_id'])->where('status', 'normal')->find();
                            
                            if ($promoteParent) {
                                // 增加推广上级余额
                                Db::name('user')->where('id', $promoteParent['id'])->setInc('money', $commissionAmount);
                                
                                // 记录推广上级佣金日志
                                Db::name('commission_log')->insert([
                                    'user_id' => $promoteParent['id'],
                                    'order_id' => $order['id'],
                                    'amount' => $commissionAmount,
                                    'type' => 'income',
                                    'memo' => '推广分佣（' . $commissionRate . '%）- 订单号：' . $order['order_no'] . '（自动结算）',
                                    'createtime' => time()
                                ]);
                                
                                // 记录推广上级余额变动
                                Db::name('user_money_log')->insert([
                                    'user_id' => $promoteParent['id'],
                                    'money' => $commissionAmount,
                                    'before' => $promoteParent['money'],
                                    'after' => $promoteParent['money'] + $commissionAmount,
                                    'memo' => '推广分佣：来自' . ($dashouInfo['nickname'] ?: '用户' . $dashouInfo['id']) . '的订单（自动结算）',
                                    'createtime' => time()
                                ]);
                                
                                $output->writeln("  - 推广分佣 ¥{$commissionAmount} 给用户ID: {$promoteParent['id']}");
                            }
                        }
                    }
                }
                
                // 更新排行榜统计
                $this->updateRanking($order['dashou_id'], 'dashou', $order['price']);
                
                Db::commit();
                $settledCount++;
                $output->writeln("订单 {$order['order_no']} (ID:{$order['id']}) 自动结算成功");
                
            } catch (Exception $e) {
                Db::rollback();
                $errorCount++;
                $output->writeln("订单 {$order['order_no']} (ID:{$order['id']}) 自动结算失败: " . $e->getMessage());
            }
        }
        
        $output->writeln('');
        $output->writeln("自动结算任务完成！");
        $output->writeln("成功结算: {$settledCount} 个订单");
        $output->writeln("失败: {$errorCount} 个订单");
        $output->writeln('完成时间: ' . date('Y-m-d H:i:s'));
    }
    
    /**
     * 更新排行榜统计
     */
    protected function updateRanking($userId, $roleType, $amount)
    {
        // 检查是否已有排行榜记录
        $ranking = Db::name('ranking')
            ->where('user_id', $userId)
            ->where('role_type', $roleType)
            ->find();
        
        if ($ranking) {
            // 更新已有记录
            Db::name('ranking')
                ->where('id', $ranking['id'])
                ->update([
                    'total_commission' => Db::raw('total_commission + ' . $amount),
                    'completed_orders' => Db::raw('completed_orders + 1'),
                    'updatetime' => time()
                ]);
        } else {
            // 插入新记录
            Db::name('ranking')->insert([
                'user_id' => $userId,
                'role_type' => $roleType,
                'total_commission' => $amount,
                'total_orders' => 0,
                'completed_orders' => 1,
                'createtime' => time(),
                'updatetime' => time()
            ]);
        }
    }
}
