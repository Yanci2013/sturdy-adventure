<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 充值接口
 */
class Recharge extends Api
{
    protected $noNeedLogin = ['config'];
    protected $noNeedRight = '*';

    /**
     * 获取充值配置
     */
    public function config()
    {
        $rechargeInfo = get_addon_info('recharge');
        if (!$rechargeInfo || !$rechargeInfo['state']) {
            $this->error('充值功能未启用');
        }
        
        $config = get_addon_config('recharge');
        
        // 处理金额列表
        $moneyList = [];
        foreach ($config['moneylist'] as $index => $item) {
            $moneyList[] = [
                'value' => $item,
                'text' => $index,
                'default' => $item === $config['defaultmoney']
            ];
        }
        
        // 处理支付方式列表
        $paytypeList = [];
        $paytypes = explode(',', $config['paytypelist']);
        foreach ($paytypes as $item) {
            $paytypeList[] = [
                'value' => trim($item),
                'default' => trim($item) === $config['defaultpaytype']
            ];
        }
        
        $result = [
            'moneylist' => $moneyList,
            'paytypelist' => $paytypeList,
            'minmoney' => isset($config['minmoney']) ? $config['minmoney'] : 0,
            'iscustommoney' => isset($config['iscustommoney']) ? $config['iscustommoney'] : false,
            'rechargetips' => isset($config['rechargetips']) ? $config['rechargetips'] : '',
            'defaultmoney' => $config['defaultmoney'],
            'defaultpaytype' => $config['defaultpaytype']
        ];
        
        $this->success('获取成功', $result);
    }

    /**
     * 提交充值订单
     */
    public function submit()
    {
        $rechargeInfo = get_addon_info('recharge');
        if (!$rechargeInfo || !$rechargeInfo['state']) {
            $this->error('充值功能未启用');
        }
        
        $epayInfo = get_addon_info('epay');
        if (!$epayInfo || !$epayInfo['state']) {
            $this->error('请在后台插件管理安装微信支付宝整合插件后重试');
        }
        
        // 检查解锁商户配置，是否需要解锁才能充值
        $unlockConfig = \think\Db::name('unlock_merchant_config')->where('id', 1)->where('status', 'normal')->find();
        if ($unlockConfig && $unlockConfig['require_recharge_unlock'] == 1) {
            // 检查用户的剩余解锁次数
            $userInfo = \think\Db::name('user')->where('id', $this->auth->id)->find();
            $remainingTimes = intval($userInfo['unlock_times_remaining'] ?? 0);
            
            if ($remainingTimes <= 0) {
                // 解锁次数不足，返回错误并附带解锁配置信息
                $this->error('您尚未解锁充值功能或次数已用完，请先购买解锁权限', [
                    'need_unlock' => true,
                    'unlock_price' => floatval($unlockConfig['unlock_price']),
                    'unlock_times' => intval($unlockConfig['unlock_times'])
                ]);
            }
        }
        
        $money = $this->request->post('money/f', 0);
        $paytype = $this->request->post('paytype', 'wechat');
        
        if ($money <= 0) {
            $this->error('充值金额不正确');
        }
        
        $config = get_addon_config('recharge');
        if (isset($config['minmoney']) && $money < $config['minmoney']) {
            $this->error('充值金额不能低于' . $config['minmoney'] . '元');
        }
        
        // 获取用户openid（小程序支付需要）
        $user = $this->auth->getUser();
        $openid = $user ? $user->openid : '';
        
        if (!$openid && $paytype === 'wechat') {
            $this->error('未找到用户微信信息，请重新登录');
        }
        
        try {
            // 确保 Auth 实例已初始化（Order::submit 内部会使用 Auth::instance()）
            $token = $this->request->header('TOKEN') ?: $this->request->header('token') ?: $this->request->param('token');
            if ($token) {
                $auth = \app\common\library\Auth::instance();
                $auth->init($token);
            }
            
            // 调用充值插件的订单提交方法
            $response = \addons\recharge\library\Order::submit($money, $paytype, 'miniapp', $openid);
            
            // 处理返回数据，如果是对象则转换为数组
            $responseData = null;
            if (is_object($response)) {
                // 如果是 Collection 对象（addons\epay\library\Collection），转换为数组
                if ($response instanceof \addons\epay\library\Collection) {
                    $responseData = $response->all();
                } elseif ($response instanceof \Yansongda\Supports\Collection) {
                    $responseData = $response->all();
                } elseif (method_exists($response, 'toArray')) {
                    $responseData = $response->toArray();
                } elseif (method_exists($response, 'all')) {
                    $responseData = $response->all();
                } elseif (method_exists($response, '__toString')) {
                    // 如果是字符串响应
                    $responseData = ['raw' => (string)$response];
                } else {
                    // 尝试转换为数组
                    $responseData = (array)$response;
                }
            } else {
                $responseData = $response;
            }
            
            // 清理数据，确保所有数据都是可序列化的
            $cleanResponseData = $this->cleanDataForJson($responseData);
            
            // 小程序支付需要返回特定的格式
            if ($paytype === 'wechat') {
                // 微信小程序支付参数
                // V3版本返回格式：['appId' => '', 'timeStamp' => '', 'nonceStr' => '', 'package' => '', 'signType' => '', 'paySign' => '']
                // V2版本返回格式：['appId' => '', 'timeStamp' => '', 'nonceStr' => '', 'package' => '', 'signType' => '', 'paySign' => '']
                if (is_array($cleanResponseData)) {
                    // 构建小程序支付所需的参数
                    $miniappPayData = [
                        'timeStamp' => isset($cleanResponseData['timeStamp']) ? (string)$cleanResponseData['timeStamp'] : (string)time(),
                        'nonceStr' => isset($cleanResponseData['nonceStr']) ? (string)$cleanResponseData['nonceStr'] : '',
                        'package' => isset($cleanResponseData['package']) ? (string)$cleanResponseData['package'] : (isset($cleanResponseData['prepay_id']) ? 'prepay_id=' . $cleanResponseData['prepay_id'] : ''),
                        'signType' => isset($cleanResponseData['signType']) ? (string)$cleanResponseData['signType'] : 'MD5',
                        'paySign' => isset($cleanResponseData['paySign']) ? (string)$cleanResponseData['paySign'] : ''
                    ];
                    
                    // 如果缺少必要参数，尝试从原始数据中获取
                    if (empty($miniappPayData['package']) && isset($cleanResponseData['prepay_id'])) {
                        $miniappPayData['package'] = 'prepay_id=' . $cleanResponseData['prepay_id'];
                    }
                    
                    // 检查是否所有必要参数都存在
                    if (empty($miniappPayData['package']) || empty($miniappPayData['paySign'])) {
                        $this->error('支付参数获取失败，请检查支付配置');
                    }
                    
                    // 再次清理数据，确保可序列化
                    $finalPayData = $this->cleanDataForJson($miniappPayData);
                    $this->success('订单创建成功', $finalPayData);
                } else {
                    // 如果无法解析，返回原始数据
                    $this->error('支付数据格式错误，请稍后重试');
                }
            } else {
                // 确保返回的数据都是可序列化的
                $finalData = $this->cleanDataForJson($cleanResponseData);
                $this->success('订单创建成功', $finalData);
            }
        } catch (\think\exception\HttpResponseException $e) {
            // HttpResponseException 是正常的响应流程，直接重新抛出
            throw $e;
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            $errorMessage = (string)($errorMsg ?: '订单创建失败，请稍后重试');
            $this->error($errorMessage);
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
}

