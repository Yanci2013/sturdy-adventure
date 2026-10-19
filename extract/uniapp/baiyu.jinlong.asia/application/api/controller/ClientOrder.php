<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;
use think\Exception;

/**
 * 点单端下单接口
 * 使用新的控制器将订单写入统一的订单表
 */
class ClientOrder extends Api
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

    /**
     * 创建订单（点单）
     * @ApiMethod (POST)
     * @ApiParams (name="goods_id", type="integer", required=true, description="商品ID")
     * @ApiParams (name="payment_type", type="string", required=true, description="支付方式:balance=余额,wechat=微信")
     * @ApiParams (name="boss_contact", type="string", required=false, description="联系方式")
     */
    public function create()
    {
        $user = $this->auth->getUser();
        if (!$user) {
            $this->error('请先登录');
        }

        $goodsId = $this->request->post('goods_id/d', 0);
        $paymentType = $this->request->post('payment_type', 'balance'); // balance 或 wechat
        $bossContact = $this->request->post('boss_contact', ''); // 联系方式
        
        if (!$goodsId) {
            $this->error('商品ID不能为空');
        }
        
        if (!in_array($paymentType, ['balance', 'wechat'])) {
            $this->error('支付方式不正确');
        }

        $goods = Db::name('client_goods')->where('id', $goodsId)->where('status', 'normal')->lock(true)->find();
        if (!$goods) {
            $this->error('商品不存在或已下架');
        }

        $originalPrice = floatval($goods['price']); // 原价（用户支付的）
        if ($originalPrice <= 0) {
            $this->error('商品价格无效');
        }
        
        // 获取抢单价格（打手看到的价格）
        $grabPrice = floatval($goods['grab_price'] ?? 0);
        if ($grabPrice <= 0) {
            $grabPrice = $originalPrice; // 如果没设置抢单价格，默认用原价
        }

        Db::startTrans();
        try {
            // 获取用户信息（加锁）
            $userInfo = Db::name('user')->where('id', $this->auth->id)->lock(true)->find();
            if (!$userInfo) {
                throw new Exception('用户信息不存在');
            }
            
            $userMoney = floatval($userInfo['money'] ?? 0);
            
            // 余额支付检查（用户支付原价）
            if ($paymentType === 'balance') {
                if ($userMoney < $originalPrice) {
                    throw new Exception('余额不足，当前余额：¥' . number_format($userMoney, 2) . '，需要：¥' . number_format($originalPrice, 2));
                }
            }

            // 点单端用户作为商家ID（发布方）
            $shangjiaId = $this->auth->id;

            // 生成订单号
            $orderNo = 'PW' . date('YmdHis') . rand(1000, 9999);

            $orderData = [
                'order_no'   => $orderNo,
                'shangjia_id'=> $shangjiaId,
                'kefu_id'    => 0,
                'dashou_id'  => 0,
                'game_id'    => $goods['game_id'],
                'server_id'  => $goods['server_id'],
                'order_type' => 'custom', // 自定义订单类型，便于区分
                'title'      => $goods['title'],
                'content'    => $goods['desc'] ?: '',
                'price'      => $grabPrice, // 抢单价格（打手看到的价格）
                'original_price' => $originalPrice, // 原价（用户支付的）
                'boss_contact' => $bossContact, // 联系方式
                'status'     => 'daijiedan', // 待接单
                'createtime' => time(),
                'updatetime' => time()
            ];

            $orderId = Db::name('order')->insertGetId($orderData);
            
            // 处理支付
            if ($paymentType === 'balance') {
                // 扣除余额（扣原价）
                Db::name('user')->where('id', $this->auth->id)->setDec('money', $originalPrice);
                
                // 记录余额变动（使用 commission_log）
                Db::name('commission_log')->insert([
                    'user_id' => $this->auth->id,
                    'order_id' => $orderId,
                    'type' => 'expense',
                    'amount' => $originalPrice,
                    'memo' => '点单端下单：' . $goods['title'],
                    'createtime' => time()
                ]);
                
                Db::commit();
                $this->success('下单成功', ['order_id' => $orderId, 'order_no' => $orderNo]);
            } else {
                // 微信支付
                $epayInfo = get_addon_info('epay');
                if (!$epayInfo || !$epayInfo['state']) {
                    throw new Exception('请在后台插件管理安装微信支付宝整合插件后重试');
                }
                
                // 获取用户openid（小程序支付需要）
                $openid = $userInfo['openid'] ?? '';
                if (!$openid) {
                    throw new Exception('未找到用户微信信息，请重新登录');
                }
                
                // 获取点单端小程序配置（appid和openid必须匹配）
                $systemConfig = Db::name('system_config')->where('id', 1)->find();
                $clientAppid = $systemConfig['client_wxappid'] ?? '';
                if (!$clientAppid) {
                    throw new Exception('点单端小程序AppID未配置');
                }
                
                // 调用支付插件生成支付参数
                $params = [
                    'type' => 'wechat',
                    'method' => 'miniapp',
                    'openid' => $openid,
                    'title' => '点单端订单：' . $goods['title'],
                    'amount' => $originalPrice, // 用户支付原价
                    'orderid' => $orderNo,
                    'notifyurl' => request()->domain() . '/api/client_order/notify',
                    'returnurl' => '',
                    // 传入点单端小程序的appid，覆盖epay插件默认配置
                    'custom' => [
                        'miniapp_id' => $clientAppid
                    ]
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
                    } elseif (is_array($response)) {
                        $responseData = $response;
                    }
                    
                    if (!$responseData) {
                        throw new Exception('支付参数获取失败');
                    }
                    
                    // 清理数据，确保可序列化
                    $cleanResponseData = json_decode(json_encode($responseData), true);
                    
                    // 构建小程序支付参数
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
                    
                    // 检查是否所有必要参数都存在
                    if (empty($miniappPayData['package']) || empty($miniappPayData['paySign'])) {
                        throw new Exception('支付参数获取失败，请检查支付配置');
                    }
                    
                    Db::commit();
                    $this->success('请完成支付', [
                        'order_id' => $orderId,
                        'order_no' => $orderNo,
                        'payment_params' => $miniappPayData
                    ]);
                } catch (Exception $e) {
                    throw new Exception('生成支付参数失败: ' . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    /**
     * 点单端订单列表（当前用户作为商家发布的订单）
     * @ApiMethod (GET)
     */
    public function list()
    {
        $user = $this->auth->getUser();
        if (!$user) {
            $this->error('请先登录');
        }

        $status = $this->request->get('status', '');
        $keyword = $this->request->get('keyword', '');
        $page  = $this->request->get('page/d', 1);
        $limit = $this->request->get('limit/d', 10);
        $sortField = $this->request->get('sort_field', 'createtime');
        $sortOrder = $this->request->get('sort_order', 'desc');

        $where = ['shangjia_id' => $this->auth->id];
        
        // 状态筛选
        if ($status && $status !== 'all') {
            $where['status'] = $status;
        }
        
        // 构建查询
        $query = Db::name('order');
        
        // 搜索功能：搜索订单号或订单ID
        if (!empty($keyword)) {
            $keywordNum = is_numeric($keyword) ? intval($keyword) : 0;
            if ($keywordNum > 0) {
                $query->where(function($query) use ($keywordNum, $keyword) {
                    $query->where('id', $keywordNum)
                          ->whereOr('order_no', 'like', '%' . $keyword . '%');
                });
            } else {
                $query->where('order_no', 'like', '%' . $keyword . '%');
            }
        }
        
        $query->where($where);
        
        // 计算统计数据（重新构建查询，因为 ThinkPHP 执行 count/sum 后会重置条件）
        $statsWhere = $where;
        if (!empty($keyword)) {
            $keywordNum = is_numeric($keyword) ? intval($keyword) : 0;
            if ($keywordNum > 0) {
                $statsTotal = Db::name('order')->where($statsWhere)->where(function($q) use ($keywordNum, $keyword) {
                    $q->where('id', $keywordNum)->whereOr('order_no', 'like', '%' . $keyword . '%');
                })->count();
                $statsTotalAmount = Db::name('order')->where($statsWhere)->where(function($q) use ($keywordNum, $keyword) {
                    $q->where('id', $keywordNum)->whereOr('order_no', 'like', '%' . $keyword . '%');
                })->sum('original_price');
            } else {
                $statsTotal = Db::name('order')->where($statsWhere)->where('order_no', 'like', '%' . $keyword . '%')->count();
                $statsTotalAmount = Db::name('order')->where($statsWhere)->where('order_no', 'like', '%' . $keyword . '%')->sum('original_price');
            }
        } else {
            $statsTotal = Db::name('order')->where($statsWhere)->count();
            $statsTotalAmount = Db::name('order')->where($statsWhere)->sum('original_price');
        }
        
        $stats = [
            'total' => $statsTotal,
            'totalAmount' => floatval($statsTotalAmount ?: 0)
        ];
        
        // 排序
        $allowedSortFields = ['createtime', 'price', 'updatetime'];
        $sortField = in_array($sortField, $allowedSortFields) ? $sortField : 'createtime';
        $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? strtolower($sortOrder) : 'desc';
        
        $query->order($sortField . ' ' . $sortOrder);
        $query->page($page, $limit);

        $list = $query->select();

        if ($list) {
            // 关联游戏和区服名称
            $gameIds = array_column($list, 'game_id');
            $serverIds = array_column($list, 'server_id');

            $games = $gameIds ? Db::name('game')->where('id', 'in', $gameIds)->column('name,icon', 'id') : [];
            $servers = $serverIds ? Db::name('game_server')->where('id', 'in', $serverIds)->column('server_name', 'id') : [];
            
            // 检查COS是否启用
            $useCos = is_cos_enabled();

            foreach ($list as &$item) {
                $item['game_name'] = isset($games[$item['game_id']]) ? $games[$item['game_id']]['name'] : '';
                $item['server_name'] = isset($servers[$item['server_id']]) ? $servers[$item['server_id']] : '';
                
                // 处理游戏图标
                if (isset($games[$item['game_id']]) && !empty($games[$item['game_id']]['icon'])) {
                    $icon = $games[$item['game_id']]['icon'];
                    if ($useCos) {
                        $item['game_icon'] = cdnurl($icon, true);
                    } else {
                        if (strpos($icon, '/') !== 0) {
                            $icon = '/' . $icon;
                        }
                        $item['game_icon'] = request()->domain() . $icon;
                    }
                } else {
                    $item['game_icon'] = '';
                }
            }
            unset($item);
        }

        $this->success('获取成功', [
            'list'  => $list,
            'total' => $statsTotal,
            'page'  => $page,
            'limit' => $limit,
            'stats' => $stats
        ]);
    }
}


