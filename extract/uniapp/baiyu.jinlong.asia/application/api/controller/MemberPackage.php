<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;
use think\Exception;

/**
 * 会员套餐接口
 */
class MemberPackage extends Api
{
    protected $noNeedLogin = ['list', 'notify'];
    protected $noNeedRight = '*';

    /**
     * 获取会员套餐列表
     * @ApiMethod (GET)
     * @ApiParams (name="game_id", type="integer", required=false, description="游戏ID")
     */
    public function list()
    {
        $gameId = $this->request->get('game_id/d', 0);
        
        $where = ['status' => 'normal'];
        if ($gameId > 0) {
            $where['game_id'] = $gameId;
        }
        
        try {
            $list = Db::name('member_package')
                ->where($where)
                ->order('id DESC')
                ->select();
            
            // 关联查询游戏信息
            $useCos = is_cos_enabled();
            
            foreach ($list as &$item) {
                $game = Db::name('game')->where('id', $item['game_id'])->find();
                $item['game_name'] = $game ? $game['name'] : '';
                
                // 处理游戏图标
                if (!empty($game['icon'])) {
                    if (!preg_match('/^https?:\/\//i', $game['icon'])) {
                        $item['game_icon'] = $useCos ? cdnurl($game['icon'], true) : request()->domain() . $game['icon'];
                    } else {
                        $item['game_icon'] = $game['icon'];
                    }
                } else {
                    $item['game_icon'] = '';
                }
                
                // 处理套餐图片
                if (!empty($item['image'])) {
                    if (!preg_match('/^https?:\/\//i', $item['image'])) {
                        $item['image'] = $useCos ? cdnurl($item['image'], true) : request()->domain() . $item['image'];
                    }
                }
            }
            
            $this->success('获取成功', ['list' => $list]);
        } catch (Exception $e) {
            $this->error('获取失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取会员套餐详情
     * @ApiMethod (GET)
     * @ApiParams (name="id", type="integer", required=true, description="套餐ID")
     */
    public function detail()
    {
        $id = $this->request->get('id/d', 0);
        
        if (!$id) {
            $this->error('套餐ID不能为空');
        }
        
        try {
            $package = Db::name('member_package')->where('id', $id)->where('status', 'normal')->find();
            
            if (!$package) {
                $this->error('套餐不存在或已隐藏');
            }
            
            // 关联查询游戏信息
            $useCos = is_cos_enabled();
            
            $game = Db::name('game')->where('id', $package['game_id'])->find();
            $package['game_name'] = $game ? $game['name'] : '';
            
            // 处理游戏图标
            if (!empty($game['icon'])) {
                if (!preg_match('/^https?:\/\//i', $game['icon'])) {
                    $package['game_icon'] = $useCos ? cdnurl($game['icon'], true) : request()->domain() . $game['icon'];
                } else {
                    $package['game_icon'] = $game['icon'];
                }
            } else {
                $package['game_icon'] = '';
            }
            
            // 处理套餐图片
            if (!empty($package['image'])) {
                if (!preg_match('/^https?:\/\//i', $package['image'])) {
                    $package['image'] = $useCos ? cdnurl($package['image'], true) : request()->domain() . $package['image'];
                }
            }
            
            $this->success('获取成功', $package);
        } catch (Exception $e) {
            $this->error('获取失败: ' . $e->getMessage());
        }
    }

    /**
     * 购买会员
     * @ApiMethod (POST)
     * @ApiParams (name="package_id", type="integer", required=true, description="套餐ID")
     * @ApiParams (name="type", type="string", required=true, description="套餐类型:trial=体验会员,formal=正式会员")
     * @ApiParams (name="payment_type", type="string", required=true, description="支付方式:balance=余额,wechat=微信")
     */
    public function purchase()
    {
        $packageId = $this->request->post('package_id/d', 0);
        $type = $this->request->post('type', 'formal'); // trial 或 formal
        $paymentType = $this->request->post('payment_type', 'balance'); // balance 或 wechat
        
        if (!$packageId) {
            $this->error('套餐ID不能为空');
        }
        
        if (!in_array($type, ['trial', 'formal'])) {
            $this->error('套餐类型不正确');
        }
        
        if (!in_array($paymentType, ['balance', 'wechat'])) {
            $this->error('支付方式不正确');
        }
        
        Db::startTrans();
        try {
            // 获取套餐信息
            $package = Db::name('member_package')->where('id', $packageId)->where('status', 'normal')->lock(true)->find();
            if (!$package) {
                throw new Exception('套餐不存在或已下架');
            }
            
            // 确定价格和天数
            $price = $type === 'trial' ? floatval($package['trial_price']) : floatval($package['formal_price']);
            $days = $type === 'trial' ? intval($package['trial_days']) : intval($package['valid_days']);
            
            if ($price <= 0 || $days <= 0) {
                throw new Exception('套餐价格或天数配置错误');
            }
            
            // 获取用户信息（加锁）
            $user = Db::name('user')->where('id', $this->auth->id)->lock(true)->find();
            if (!$user) {
                throw new Exception('用户信息不存在');
            }
            
            $userMoney = floatval($user['money'] ?? 0);
            
            // 余额支付检查
            if ($paymentType === 'balance') {
                if ($userMoney < $price) {
                    throw new Exception('余额不足，当前余额：¥' . number_format($userMoney, 2) . '，需要：¥' . number_format($price, 2));
                }
            }
            
            // 检查用户是否已有该游戏的会员记录（判断首次/续费）
            $existingMember = Db::name('user_member')
                ->where('user_id', $this->auth->id)
                ->where('game_id', $package['game_id'])
                ->where('status', 'normal')
                ->order('end_time DESC')
                ->find();
            
            $isFirstPurchase = !$existingMember || $existingMember['end_time'] <= time();
            
            // 生成订单号
            $orderNo = 'MB' . date('YmdHis') . rand(1000, 9999);
            
            // 计算会员时间
            $startTime = time();
            $endTime = $startTime + ($days * 86400);
            
            // 如果已有未过期的会员，从到期时间开始续费
            if ($existingMember && $existingMember['end_time'] > time()) {
                $startTime = $existingMember['end_time'];
                $endTime = $startTime + ($days * 86400);
            }
            
            // 处理支付
            if ($paymentType === 'balance') {
                // 扣除余额
                Db::name('user')->where('id', $this->auth->id)->setDec('money', $price);
                
                // 记录余额变动
                Db::name('user_money_log')->insert([
                    'user_id' => $this->auth->id,
                    'money' => -$price,
                    'before' => $userMoney,
                    'after' => $userMoney - $price,
                    'memo' => '购买会员套餐：' . ($type === 'trial' ? '体验会员' : '正式会员'),
                    'createtime' => time()
                ]);
                
                // 创建会员记录
                $userMemberId = Db::name('user_member')->insertGetId([
                    'user_id' => $this->auth->id,
                    'package_id' => $packageId,
                    'package_type' => $type,
                    'game_id' => $package['game_id'],
                    'payment_type' => $paymentType,
                    'order_no' => $orderNo,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'status' => 'normal',
                    'createtime' => time(),
                    'updatetime' => time()
                ]);
                
                // 分佣逻辑（传入购买类型：trial=体验会员, formal=正式会员）
                $this->distributeCommission($user, $package, $isFirstPurchase, $price, $orderNo, $userMemberId, $type);
                
                Db::commit();
                
                $result = [
                    'order_no' => $orderNo,
                    'user_member_id' => $userMemberId,
                    'start_time' => $startTime,
                    'end_time' => $endTime
                ];
                
                $this->success('购买成功', $result);
            } else {
                // 微信支付
                $epayInfo = get_addon_info('epay');
                if (!$epayInfo || !$epayInfo['state']) {
                    throw new Exception('请在后台插件管理安装微信支付宝整合插件后重试');
                }
                
                // 获取用户openid（小程序支付需要）
                $openid = $user['openid'] ?? '';
                if (!$openid) {
                    throw new Exception('未找到用户微信信息，请重新登录');
                }
                
                // 创建待支付状态的会员记录
                $userMemberId = Db::name('user_member')->insertGetId([
                    'user_id' => $this->auth->id,
                    'package_id' => $packageId,
                    'package_type' => $type,
                    'game_id' => $package['game_id'],
                    'payment_type' => $paymentType,
                    'order_no' => $orderNo,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'status' => 'pending', // 待支付状态
                    'createtime' => time(),
                    'updatetime' => time()
                ]);
                
                Db::commit();
                
                // 创建微信支付订单
                $request = \think\Request::instance();
                $notifyurl = $request->root(true) . '/api/member_package/notify';
                $returnurl = $request->root(true) . '/pages/my/member';
                
                $params = [
                    'amount'    => $price,
                    'orderid'   => $orderNo,
                    'type'      => 'wechat',
                    'title'     => '购买会员套餐：' . ($type === 'trial' ? '体验会员' : '正式会员'),
                    'notifyurl' => $notifyurl,
                    'returnurl' => $returnurl,
                    'method'    => 'miniapp',
                    'openid'    => $openid
                ];
                
                try {
                    $response = \addons\epay\library\Service::submitOrder($params);
                    
                    // 处理返回数据
                    $responseData = null;
                    if (is_object($response)) {
                        if ($response instanceof \addons\epay\library\Collection) {
                            $responseData = $response->all();
                        } elseif ($response instanceof \Yansongda\Supports\Collection) {
                            $responseData = $response->all();
                        } elseif (method_exists($response, 'toArray')) {
                            $responseData = $response->toArray();
                        } elseif (method_exists($response, 'all')) {
                            $responseData = $response->all();
                        } else {
                            $responseData = (array)$response;
                        }
                    } else {
                        $responseData = $response;
                    }
                    
                    // 清理数据，确保所有数据都是可序列化的
                    $cleanResponseData = $this->cleanDataForJson($responseData);
                    
                    // 构建小程序支付所需的参数（支持多种键名格式）
                    $miniappPayData = [
                        'timeStamp' => isset($cleanResponseData['timeStamp']) ? (string)$cleanResponseData['timeStamp'] : 
                                      (isset($cleanResponseData['timestamp']) ? (string)$cleanResponseData['timestamp'] : (string)time()),
                        'nonceStr' => isset($cleanResponseData['nonceStr']) ? (string)$cleanResponseData['nonceStr'] : 
                                     (isset($cleanResponseData['noncestr']) ? (string)$cleanResponseData['noncestr'] : ''),
                        'package' => isset($cleanResponseData['package']) ? (string)$cleanResponseData['package'] : 
                                    (isset($cleanResponseData['prepay_id']) ? 'prepay_id=' . $cleanResponseData['prepay_id'] : ''),
                        'signType' => isset($cleanResponseData['signType']) ? (string)$cleanResponseData['signType'] : 
                                     (isset($cleanResponseData['signtype']) ? (string)$cleanResponseData['signtype'] : 'MD5'),
                        'paySign' => isset($cleanResponseData['paySign']) ? (string)$cleanResponseData['paySign'] : 
                                    (isset($cleanResponseData['paysign']) ? (string)$cleanResponseData['paysign'] : '')
                    ];
                    
                    // 如果缺少必要参数，尝试从原始数据中获取
                    if (empty($miniappPayData['package']) && isset($cleanResponseData['prepay_id'])) {
                        $miniappPayData['package'] = 'prepay_id=' . $cleanResponseData['prepay_id'];
                    }
                    
                    // 检查是否所有必要参数都存在
                    if (empty($miniappPayData['package']) || empty($miniappPayData['paySign'])) {
                        throw new Exception('支付参数获取失败，请检查支付配置');
                    }
                    
                    // 确保所有数据都是字符串类型，可以序列化
                    $finalPaymentParams = [
                        'timeStamp' => (string)$miniappPayData['timeStamp'],
                        'nonceStr' => (string)$miniappPayData['nonceStr'],
                        'package' => (string)$miniappPayData['package'],
                        'signType' => (string)$miniappPayData['signType'],
                        'paySign' => (string)$miniappPayData['paySign']
                    ];
                    
                    $result = [
                        'order_no' => (string)$orderNo,
                        'user_member_id' => (int)$userMemberId,
                        'payment_params' => $finalPaymentParams
                    ];
                    
                    $this->success('订单创建成功', $result);
                } catch (\think\exception\HttpResponseException $e) {
                    // HttpResponseException 是正常的响应流程，直接重新抛出
                    throw $e;
                } catch (\Exception $e) {
                    // 如果支付订单创建失败，删除待支付的会员记录
                    Db::name('user_member')->where('id', $userMemberId)->delete();
                    throw new Exception('支付订单创建失败：' . $e->getMessage());
                }
            }
        } catch (\think\exception\HttpResponseException $e) {
            Db::rollback();
            throw $e;
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    /**
     * 清理数据，确保所有数据都是可序列化的
     * @param mixed $data
     * @return mixed
     */
    private function cleanDataForJson($data)
    {
        if (is_scalar($data) || is_null($data)) {
            return $data;
        }
        
        if (is_array($data)) {
            $cleaned = [];
            foreach ($data as $key => $value) {
                $cleaned[$key] = $this->cleanDataForJson($value);
            }
            return $cleaned;
        }
        
        if (is_object($data)) {
            // 尝试转换为数组
            if (method_exists($data, 'toArray')) {
                return $this->cleanDataForJson($data->toArray());
            }
            if (method_exists($data, 'all')) {
                return $this->cleanDataForJson($data->all());
            }
            // 使用 JSON 编码/解码来清理对象
            $json = json_encode($data);
            if ($json !== false) {
                return json_decode($json, true);
            }
            // 如果无法序列化，返回字符串表示
            return ['_type' => 'object', '_class' => get_class($data), '_value' => (string)$data];
        }
        
        // 其他类型，转换为字符串
        return (string)$data;
    }
    
    /**
     * 三级分佣逻辑
     * 分佣规则：下级用户充值会员，向上级发起分佣，最多追溯3级上级
     * 根据上级的实际角色（管事/组长/总管）发放对应分佣，缺级自动跳过
     * 
     * @param array $user 购买用户信息
     * @param array $package 套餐信息
     * @param bool $isFirstPurchase 是否首次购买
     * @param float $price 购买价格
     * @param string $orderNo 订单号
     * @param int $userMemberId 用户会员记录ID
     * @param string $packageType 购买类型：trial=体验会员, formal=正式会员
     */
    private function distributeCommission($user, $package, $isFirstPurchase, $price, $orderNo, $userMemberId, $packageType = 'formal')
    {
        // 可分佣的角色
        $commissionRoles = ['guanshi', 'zuzhang', 'zongguan'];
        
        $levelNames = [
            'guanshi' => '管事',
            'zuzhang' => '组长',
            'zongguan' => '总管'
        ];
        
        // 确定分佣类型文案
        $commissionType = '';
        if ($packageType === 'trial') {
            $commissionType = '体验会员';
        } elseif ($isFirstPurchase) {
            $commissionType = '首次正式会员';
        } else {
            $commissionType = '续费正式会员';
        }
        
        // 确定充值者的角色，排除自己的角色（不给自己分佣）
        $userRoleType = $user['role_type'] ?? '';
        $excludeRoles = []; // 要排除的角色（已经分佣过的角色）
        
        // 如果充值者自身是上级角色，排除自己的角色和下级角色
        if ($userRoleType === 'guanshi') {
            $excludeRoles[] = 'guanshi'; // 充值者是管事，不给管事分佣
        } elseif ($userRoleType === 'zuzhang') {
            $excludeRoles[] = 'guanshi'; // 充值者是组长，不给管事分佣
            $excludeRoles[] = 'zuzhang'; // 也不给组长分佣
        } elseif ($userRoleType === 'zongguan') {
            return; // 充值者是总管，无上级需分佣
        }
        
        $currentUserId = $user['id'];
        $totalCommission = 0;
        $maxCommission = $price * 0.9; // 最多分佣90%，预疙10%平台收益
        $distributedRoles = []; // 已经分佣过的角色，避免重复分佣
        
        // 向上追溯，最多3级
        for ($level = 1; $level <= 3; $level++) {
            // 获取当前用户的上级
            $currentUser = Db::name('user')->where('id', $currentUserId)->find();
            if (!$currentUser || intval($currentUser['parent_id']) <= 0) {
                break; // 无上级，停止追溯
            }
            
            $parentId = intval($currentUser['parent_id']);
            $parent = Db::name('user')->where('id', $parentId)->where('status', 'normal')->find();
            if (!$parent) {
                // 上级不存在或状态异常，继续往上找
                $currentUserId = $parentId;
                continue;
            }
            
            $parentRoleType = $parent['role_type'] ?? '';
            
            // 检查上级角色是否是可分佣角色（管事/组长/总管）
            if (in_array($parentRoleType, $commissionRoles)) {
                // 检查是否在排除列表中
                if (in_array($parentRoleType, $excludeRoles)) {
                    $currentUserId = $parentId;
                    continue;
                }
                
                // 检查是否已经给这个角色分佣过
                if (in_array($parentRoleType, $distributedRoles)) {
                    $currentUserId = $parentId;
                    continue;
                }
                
                // 获取该角色的分佣金额
                $commissionAmount = $this->getLevelCommission($package, $packageType, $isFirstPurchase, $parentRoleType);
                
                if ($commissionAmount > 0) {
                    // 检查分佣总额是否超限
                    if ($totalCommission + $commissionAmount > $maxCommission) {
                        $commissionAmount = round($maxCommission - $totalCommission, 2);
                        if ($commissionAmount <= 0) {
                            break;
                        }
                    }
                    
                    // 增加上级的余额
                    Db::name('user')->where('id', $parentId)->setInc('money', $commissionAmount);
                    
                    // 记录佣金日志
                    $levelName = $levelNames[$parentRoleType] ?? $parentRoleType;
                    Db::name('commission_log')->insert([
                        'user_id' => $parentId,
                        'order_id' => 0,
                        'amount' => $commissionAmount,
                        'type' => 'income',
                        'memo' => '会员购买分佣（' . $commissionType . '-' . $levelName . '）- 订单号：' . $orderNo,
                        'createtime' => time()
                    ]);
                    
                    // 记录余额变动
                    $parentMoney = floatval($parent['money'] ?? 0);
                    Db::name('user_money_log')->insert([
                        'user_id' => $parentId,
                        'money' => $commissionAmount,
                        'before' => $parentMoney,
                        'after' => $parentMoney + $commissionAmount,
                        'memo' => '会员购买分佣（' . $commissionType . '-' . $levelName . '）- 订单号：' . $orderNo,
                        'createtime' => time()
                    ]);
                    
                    $totalCommission += $commissionAmount;
                }
                
                // 记录已分佣的角色
                $distributedRoles[] = $parentRoleType;
            }
            
            $currentUserId = $parentId; // 继续往上追溯
        }
    }
    
    /**
     * 获取指定层级的分佣金额
     * @param array $package 套餐信息
     * @param string $packageType 购买类型：trial/formal
     * @param bool $isFirstPurchase 是否首次购买
     * @param string $roleType 角色类型：guanshi/zuzhang/zongguan
     * @return float
     */
    private function getLevelCommission($package, $packageType, $isFirstPurchase, $roleType)
    {
        $commissionAmount = 0;
        
        if ($packageType === 'trial') {
            // 体验会员
            switch ($roleType) {
                case 'guanshi':
                    $commissionAmount = floatval($package['trial_commission_guanshi'] ?? 0);
                    break;
                case 'zuzhang':
                    $commissionAmount = floatval($package['trial_commission_zuzhang'] ?? 0);
                    break;
                case 'zongguan':
                    $commissionAmount = floatval($package['trial_commission_zongguan'] ?? 0);
                    break;
            }
        } elseif ($isFirstPurchase) {
            // 首次正式会员
            switch ($roleType) {
                case 'guanshi':
                    $commissionAmount = floatval($package['first_commission_guanshi'] ?? 0);
                    break;
                case 'zuzhang':
                    $commissionAmount = floatval($package['first_commission_zuzhang'] ?? 0);
                    break;
                case 'zongguan':
                    $commissionAmount = floatval($package['first_commission_zongguan'] ?? 0);
                    break;
            }
        } else {
            // 续费正式会员
            switch ($roleType) {
                case 'guanshi':
                    $commissionAmount = floatval($package['renew_commission_guanshi'] ?? 0);
                    break;
                case 'zuzhang':
                    $commissionAmount = floatval($package['renew_commission_zuzhang'] ?? 0);
                    break;
                case 'zongguan':
                    $commissionAmount = floatval($package['renew_commission_zongguan'] ?? 0);
                    break;
            }
        }
        
        return $commissionAmount;
    }

    /**
     * 微信支付回调通知
     * @ApiMethod (POST)
     */
    public function notify()
    {
        try {
            $epayInfo = get_addon_info('epay');
            if (!$epayInfo || !$epayInfo['state']) {
                return $this->notifyFail();
            }
            
            // 处理支付回调（必须传入支付类型）
            $notify = \addons\epay\library\Service::checkNotify('wechat');
            if (!$notify) {
                return $this->notifyFail();
            }
            
            // 获取回调数据
            $data = \addons\epay\library\Service::isVersionV3() ? $notify->callback() : $notify->verify();
            
            // 微信支付V3返回格式不同
            if (\addons\epay\library\Service::isVersionV3()) {
                $data = $data['resource']['ciphertext'];
                $orderNo = $data['out_trade_no'] ?? '';
                $payAmount = floatval(($data['amount']['total'] ?? 0) / 100);
            } else {
                $orderNo = $data['out_trade_no'] ?? '';
                $payAmount = floatval(($data['total_fee'] ?? 0) / 100);
            }
            
            if (empty($orderNo)) {
                return $this->notifyFail();
            }
            
            // 查找待支付的会员记录
            $userMember = Db::name('user_member')
                ->where('order_no', $orderNo)
                ->where('status', 'pending')
                ->find();
            
            if (!$userMember) {
                // 可能已处理过，返回成功
                return $this->notifySuccess($notify);
            }
            
            // 获取套餐信息
            $package = Db::name('member_package')->where('id', $userMember['package_id'])->find();
            if (!$package) {
                return $this->notifyFail();
            }
            
            // 验证支付金额
            $expectedPrice = $userMember['package_type'] === 'trial' 
                ? floatval($package['trial_price']) 
                : floatval($package['formal_price']);
            
            if (abs($payAmount - $expectedPrice) > 0.01) {
                return $this->notifyFail();
            }
            
            Db::startTrans();
            try {
                // 更新会员记录状态
                Db::name('user_member')->where('id', $userMember['id'])->update([
                    'status' => 'normal',
                    'updatetime' => time()
                ]);
                
                // 获取用户信息
                $user = Db::name('user')->where('id', $userMember['user_id'])->find();
                if (!$user) {
                    throw new Exception('用户不存在');
                }
                
                // 检查是否首次购买（根据同一游戏的会员记录判断）
                $existingMember = Db::name('user_member')
                    ->where('user_id', $userMember['user_id'])
                    ->where('game_id', $package['game_id'])
                    ->where('id', '<>', $userMember['id'])
                    ->where('status', 'normal')
                    ->order('end_time DESC')
                    ->find();
                
                $isFirstPurchase = !$existingMember || $existingMember['end_time'] <= time();
                
                // 执行分佣逻辑（传入购买类型）
                $packageType = $userMember['package_type'] ?? 'formal';
                $this->distributeCommission($user, $package, $isFirstPurchase, $payAmount, $orderNo, $userMember['id'], $packageType);
                
                Db::commit();
                return $this->notifySuccess($notify);
            } catch (\Exception $e) {
                Db::rollback();
                return $this->notifyFail();
            }
        } catch (\Exception $e) {
            return $this->notifyFail();
        }
    }
    
    /**
     * 回调成功响应
     */
    private function notifySuccess($pay)
    {
        if (\addons\epay\library\Service::isVersionV3()) {
            return $pay->success()->getBody()->getContents();
        } else {
            return $pay->success()->send();
        }
    }
    
    /**
     * 回调失败响应
     */
    private function notifyFail()
    {
        if (\addons\epay\library\Service::isVersionV3()) {
            return json(['code' => 'FAIL', 'message' => '失败'], 500, ['Content-Type' => 'application/json']);
        } else {
            return 'fail';
        }
    }
    
    /**
     * 获取用户会员信息
     * @ApiMethod (GET)
     * @ApiParams (name="game_id", type="integer", required=false, description="游戏ID，不传则返回所有游戏的会员")
     */
    public function my_member()
    {
        $gameId = $this->request->get('game_id/d', 0);
        
        $where = [
            'user_id' => $this->auth->id,
            'status' => 'normal',
            'end_time' => ['>', time()]
        ];
        
        if ($gameId > 0) {
            $where['game_id'] = $gameId;
        }
        
        try {
            $list = Db::name('user_member')
                ->where($where)
                ->order('end_time DESC')
                ->select();
            
            // 关联查询套餐和游戏信息
            foreach ($list as &$item) {
                $package = Db::name('member_package')->where('id', $item['package_id'])->find();
                $game = Db::name('game')->where('id', $item['game_id'])->find();
                $item['package_name'] = $package ? ($item['package_type'] === 'trial' ? '体验会员' : '正式会员') : '';
                $item['game_name'] = $game ? $game['name'] : '';
            }
            unset($item);
            
            // 如果指定了game_id，返回单个会员信息或null
            if ($gameId > 0) {
                $this->success('获取成功', $list ? $list[0] : null);
            } else {
                $this->success('获取成功', ['list' => $list]);
            }
        } catch (Exception $e) {
            $this->error('获取失败: ' . $e->getMessage());
        }
    }
}

