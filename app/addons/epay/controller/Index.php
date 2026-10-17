<?php

namespace addons\epay\controller;

use addons\epay\library\Service;
use fast\Random;
use think\addons\Controller;
use Exception;
use think\Db;
use Yansongda\Pay\Log;

/**
 * 微信支付宝整合插件首页
 *
 * 此控制器仅用于开发展示说明和测试，请自行添加一个新的控制器进行处理返回和回调事件，同时删除此控制器文件
 *
 * Class Index
 * @package addons\epay\controller
 */
class Index extends Controller
{
    protected $layout = 'default';
    protected $noNeedLogin = ['*'];

    protected $config = [];

    public function _initialize()
    {
        parent::_initialize();
//        if (!config("app_debug")) {
//            $this->error("仅在开发环境下查看");
//        }
    }

    public function index()
    {
        $this->view->assign("title", "微信支付宝整合");
        return $this->view->fetch();
    }

    /**
     * 体验，仅供开发测试
     */
    public function experience()
    {
        $amount = $this->request->post('amount');
        $type = $this->request->post('type');
        $method = $this->request->post('method');
        $openid = $this->request->post('openid', "");

        if (!$amount || $amount < 0) {
            $this->error("支付金额必须大于0");
        }

        if (!$type || !in_array($type, ['alipay', 'wechat'])) {
            $this->error("支付类型不能为空");
        }

        if (in_array($method, ['miniapp', 'mp']) && !$openid) {
            $this->error("openid不能为空");
        }

        //订单号
        $out_trade_no = date("YmdHis") . mt_rand(100000, 999999);

        //订单标题
        $title = '测试订单';

        //回调链接
        $notifyurl = $this->request->root(true) . '/addons/epay/index/notifyx/paytype/' . $type;
        $returnurl = $this->request->root(true) . '/addons/epay/index/returnx/paytype/' . $type . '/out_trade_no/' . $out_trade_no;

        $response = Service::submitOrder($amount, $out_trade_no, $type, $title, $notifyurl, $returnurl, $method, $openid);

        return $response;
    }

    /**
     * 用户下单支付成功
     */
    public function notifyx()
    {
        $paytype = $this->request->param('paytype');
        $pay = Service::checkNotify($paytype);
        if (!$pay) {
            return json(['code' => 'FAIL', 'message' => '失败'], 500, ['Content-Type' => 'application/json']);
        }

        // 获取回调数据，V3和V2的回调接收不同
        $data = Service::isVersionV3() ? $pay->callback() : $pay->verify();
        $this->printLogs('用户下单'.json_encode($data));
        try {
            //微信支付V3返回和V2不同
            if (Service::isVersionV3() && $paytype === 'wechat') {
                $data = $data['resource']['ciphertext'];
                $data['total_fee'] = $data['amount']['total'];
            }

            \think\Log::record($data);
            //获取支付金额、订单号
            $payamount = $paytype == 'alipay' ? $data['total_amount'] : $data['total_fee'] / 100;
            $out_trade_no = $data['out_trade_no'];

            \think\Log::record("回调成功，订单号：{$out_trade_no}，金额：{$payamount}");

            Db::startTrans();
            try {
                //获取订单信息
                $order = Db::name('order')->where(['order_no' => $out_trade_no])->field('id,thug_id,type,pay_price,status')->find();
                if($order && $order['status'] == 0)
                {
                    $update  = [
                        'paytime' => time(),
                        'order_json' => json_encode($data),
                    ];
                    if($order['type'] == '2')//2为指定打手 直接改为待开始
                    {
                        $update['status'] = 2;
                        $update['connecttime'] = time();//接单时间
                    }else{
                        $update['status'] = 1;
                    }

                    //修改订单状态
                    Db::name('order')->where(['id' => $order['id']])->update($update);

                    // 推送下单通知给在线打手
                    try {
                        $orderInfo = Db::name('order')->where('id', $order['id'])->find();
                        $wechat = new \Wechat();
                        $wechat->sendOrderNotifyToThugs($orderInfo);
                    } catch (\Exception $e) {
                        \think\Log::record("推送下单通知失败:" . $e->getMessage(), 'error');
                    }
                }

                Db::commit();
            } catch (\think\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            //你可以在此编写订单逻辑
        } catch (Exception $e) {
            \think\Log::record("回调逻辑处理错误:" . $e->getMessage(), "error");
        }

        //下面这句必须要执行,且在此之前不能有任何输出
        if (Service::isVersionV3()) {
            return $pay->success()->getBody()->getContents();
        } else {
            return $pay->success()->send();
        }
    }

    /**
     * 支付返回
     */
    public function returnx()
    {
        $paytype = $this->request->param('paytype');
        $out_trade_no = $this->request->param('out_trade_no');
        $pay = Service::checkReturn($paytype);
        if (!$pay) {
            $this->error('签名错误', '');
        }

        //你可以在这里定义你的提示信息,但切记不可在此编写逻辑
        $this->success("请返回网站查看支付结果", addon_url("epay/index/index"));
    }

    /**
     * 打赏/礼物支付成功
     */
    public function notifyxs()
    {
        $paytype = $this->request->param('paytype');
        $pay = Service::checkNotify($paytype);
        if (!$pay) {
            return json(['code' => 'FAIL', 'message' => '失败'], 500, ['Content-Type' => 'application/json']);
        }

        // 获取回调数据，V3和V2的回调接收不同
        $data = Service::isVersionV3() ? $pay->callback() : $pay->verify();
        $this->printLogs('打赏/礼物'.json_encode($data));
        try {
            //微信支付V3返回和V2不同
            if (Service::isVersionV3() && $paytype === 'wechat') {
                $data = $data['resource']['ciphertext'];
                $data['total_fee'] = $data['amount']['total'];
            }

            \think\Log::record($data);
            //获取支付金额、订单号
            $payamount = $paytype == 'alipay' ? $data['total_amount'] : $data['total_fee'] / 100;
            $out_trade_no = $data['out_trade_no'];

            \think\Log::record("回调成功，订单号：{$out_trade_no}，金额：{$payamount}");

            Db::startTrans();
            try {
                // 先检查是否是礼物订单
                $giftOrder = Db::name('gift_order')->where(['order_no' => $out_trade_no])->field('id,total_price,user_id,thug_id,gift_id,gift_name,gift_icon,gift_effect,price,num,status')->find();
                if ($giftOrder && $giftOrder['status'] == 0) {
                    // 修改礼物订单状态
                    Db::name('gift_order')->where(['id' => $giftOrder['id']])->update([
                        'status' => 1,
                        'paytime' => time(),
                        'order_json' => json_encode($data),
                    ]);
                    
                    // 添加礼物赠送记录
                    Db::name('gift_record')->insert([
                        'user_id' => $giftOrder['user_id'],
                        'thug_id' => $giftOrder['thug_id'],
                        'gift_id' => $giftOrder['gift_id'],
                        'gift_name' => $giftOrder['gift_name'],
                        'gift_icon' => $giftOrder['gift_icon'],
                        'gift_effect' => $giftOrder['gift_effect'],
                        'price' => $giftOrder['price'],
                        'num' => $giftOrder['num'],
                        'total_price' => $giftOrder['total_price'],
                        'createtime' => time()
                    ]);
                    
                    // 更新打手可用佣金
                    $thug = Db::name('thug')->where('id', $giftOrder['thug_id'])->field('available_money')->find();
                    Db::name('thug')->where('id', $giftOrder['thug_id'])->setInc('available_money', $giftOrder['total_price']);
                    
                    // 记录打手佣金变动日志
                    try {
                        $prefix = config('database.prefix');
                        $tableName = $prefix . 'thug_money_log';
                        $isTableExist = Db::query("SHOW TABLES LIKE '{$tableName}'");
                        if (!empty($isTableExist)) {
                            Db::name('thug_money_log')->insert([
                                'thug_id' => $giftOrder['thug_id'],
                                'money' => $giftOrder['total_price'],
                                'before' => $thug['available_money'] ?? 0,
                                'after' => ($thug['available_money'] ?? 0) + $giftOrder['total_price'],
                                'type' => 1,
                                'memo' => '用户赠送礼物-' . $out_trade_no,
                                'createtime' => time()
                            ]);
                        }
                    } catch (\Exception $e) {
                    }
                } else {
                    // 获取打赏订单信息
                    $order = Db::name('user_reward')->where(['order_no' => $out_trade_no])->field('id,money,user_id,thug_id,status')->find();
                    if ($order && $order['status'] == 0) {
                        //修改订单状态
                        Db::name('user_reward')->where(['id' => $order['id']])->update([
                            'status' => 1,
                            'paytime' => time(),
                            'order_json' => json_encode($data),
                        ]);
                        //添加余额
                        Db::name('thug')->where(['id' => $order['thug_id']])->update([
                            'money' => Db::raw('money + ' . $order['money']),
                            'lj_money' => Db::raw('lj_money + ' . $order['money']),
                        ]);
                    }
                }

                Db::commit();
            } catch (\think\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            //你可以在此编写订单逻辑
        } catch (Exception $e) {
            \think\Log::record("回调逻辑处理错误:" . $e->getMessage(), "error");
        }

        //下面这句必须要执行,且在此之前不能有任何输出
        if (Service::isVersionV3()) {
            return $pay->success()->getBody()->getContents();
        } else {
            return $pay->success()->send();
        }
    }

    /**
     * 支付返回
     */
    public function returnxs()
    {
        $paytype = $this->request->param('paytype');
        $out_trade_no = $this->request->param('out_trade_no');
        $pay = Service::checkReturn($paytype);
        if (!$pay) {
            $this->error('签名错误', '');
        }

        //你可以在这里定义你的提示信息,但切记不可在此编写逻辑
        $this->success("请返回网站查看支付结果", addon_url("epay/index/index"));
    }

    public function printLogs($msg) {
        if (!is_dir('log')){
            mkdir('log',0777,true);
        }
        $path="log/".date('Y-m-d').".log";
        file_put_contents($path, "【" . date('Y-m-d H:i:s') . "】" . $msg . "\r\n\r\n", FILE_APPEND);
    }


    /**
     * 保证金支付成功
     */
    public function notifyxb()
    {
        $paytype = $this->request->param('paytype');
        $pay = Service::checkNotify($paytype);
        if (!$pay) {
            return json(['code' => 'FAIL', 'message' => '失败'], 500, ['Content-Type' => 'application/json']);
        }

        // 获取回调数据，V3和V2的回调接收不同
        $data = Service::isVersionV3() ? $pay->callback() : $pay->verify();
        $this->printLogs('保证金'.json_encode($data));
        try {
            //微信支付V3返回和V2不同
            if (Service::isVersionV3() && $paytype === 'wechat') {
                $data = $data['resource']['ciphertext'];
                $data['total_fee'] = $data['amount']['total'];
            }

            \think\Log::record($data);
            //获取支付金额、订单号
            $payamount = $paytype == 'alipay' ? $data['total_amount'] : $data['total_fee'] / 100;
            $out_trade_no = $data['out_trade_no'];

            \think\Log::record("回调成功，订单号：{$out_trade_no}，金额：{$payamount}");

            Db::startTrans();
            try {
                //获取订单信息
                $order = Db::name('bond')->where(['order_no' => $out_trade_no])->field('id,user_id,thug_id,money,status')->find();
                if($order && $order['status'] == 0)
                {
                    //修改订单状态
                    Db::name('bond')->where(['id' => $order['id']])->update([
                        'status' => 1,
                        'paytime' => time(),
                        'order_json' => json_encode($data),
                    ]);
                    //添加保证金
                    Db::name('thug')->where(['id'=>$order['thug_id'],'user_id'=>$order['user_id']])->setInc('bond', $payamount);
                }

                Db::commit();
            } catch (\think\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            //你可以在此编写订单逻辑
        } catch (Exception $e) {
            \think\Log::record("回调逻辑处理错误:" . $e->getMessage(), "error");
        }

        //下面这句必须要执行,且在此之前不能有任何输出
        if (Service::isVersionV3()) {
            return $pay->success()->getBody()->getContents();
        } else {
            return $pay->success()->send();
        }
    }

    /**
     * 保证金支付返回
     */
    public function returnxb()
    {

        $paytype = $this->request->param('paytype');
        $out_trade_no = $this->request->param('out_trade_no');
        $pay = Service::checkReturn($paytype);
        if (!$pay) {
            $this->error('签名错误', '');
        }

        //你可以在这里定义你的提示信息,但切记不可在此编写逻辑
        $this->success("请返回网站查看支付结果", addon_url("epay/index/index"));
    }

    /**
     * 会员等级支付回调
     */
    public function notifylevel()
    {
        $paytype = $this->request->param('paytype');
        $pay = Service::checkNotify($paytype);
        if (!$pay) {
            return json(['code' => 'FAIL', 'message' => '失败'], 500, ['Content-Type' => 'application/json']);
        }

        $data = Service::isVersionV3() ? $pay->callback() : $pay->verify();
        $this->printLogs('会员等级支付' . json_encode($data));
        try {
            if (Service::isVersionV3() && $paytype === 'wechat') {
                $data = $data['resource']['ciphertext'];
                $data['total_fee'] = $data['amount']['total'];
            }

            \think\Log::record($data);
            $payamount = $paytype == 'alipay' ? $data['total_amount'] : $data['total_fee'] / 100;
            $out_trade_no = $data['out_trade_no'];

            \think\Log::record("会员等级回调成功，订单号：{$out_trade_no}，金额：{$payamount}");

            Db::startTrans();
            try {
                $order = Db::name('level_order')->where(['order_no' => $out_trade_no])->field('id,user_id,level_id,days,status')->find();
                if ($order && $order['status'] == 0) {
                    $now = time();
                    // 查询用户当前会员到期时间
                    $user = Db::name('user')->where('id', $order['user_id'])->field('level_id,level_expire')->find();
                    $currentExpire = ($user && $user['level_expire'] > $now) ? $user['level_expire'] : $now;
                    $newExpire = $currentExpire + ($order['days'] * 86400);

                    // 更新订单状态
                    Db::name('level_order')->where(['id' => $order['id']])->update([
                        'status'     => 1,
                        'paytime'    => $now,
                        'expiretime' => $newExpire,
                        'order_json' => json_encode($data),
                    ]);

                    // 更新用户会员等级和到期时间
                    Db::name('user')->where('id', $order['user_id'])->update([
                        'level_id'     => $order['level_id'],
                        'level_expire' => $newExpire,
                    ]);
                }

                Db::commit();
            } catch (\think\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
        } catch (Exception $e) {
            \think\Log::record("会员等级回调逻辑处理错误:" . $e->getMessage(), "error");
        }

        if (Service::isVersionV3()) {
            return $pay->success()->getBody()->getContents();
        } else {
            return $pay->success()->send();
        }
    }

    /**
     * 会员等级支付返回
     */
    public function returnlevel()
    {
        $paytype = $this->request->param('paytype');
        $out_trade_no = $this->request->param('out_trade_no');
        $pay = Service::checkReturn($paytype);
        if (!$pay) {
            $this->error('签名错误', '');
        }
        $this->success("请返回网站查看支付结果", addon_url("epay/index/index"));
    }

    /**
     * 余额充值支付回调
     */
    public function notifybalance()
    {
        $paytype = $this->request->param('paytype');
        $pay = Service::checkNotify($paytype);
        if (!$pay) {
            return json(['code' => 'FAIL', 'message' => '失败'], 500, ['Content-Type' => 'application/json']);
        }

        $data = Service::isVersionV3() ? $pay->callback() : $pay->verify();
        $this->printLogs('余额充值支付' . json_encode($data));
        try {
            if (Service::isVersionV3() && $paytype === 'wechat') {
                $data = $data['resource']['ciphertext'];
                $data['total_fee'] = $data['amount']['total'];
            }

            \think\Log::record($data);
            $payamount = $paytype == 'alipay' ? $data['total_amount'] : $data['total_fee'] / 100;
            $out_trade_no = $data['out_trade_no'];

            \think\Log::record("余额充值回调成功，订单号：{$out_trade_no}，金额：{$payamount}");

            Db::startTrans();
            try {
                $order = Db::name('balance_recharge_order')->where(['order_no' => $out_trade_no])->field('id,user_id,amount,status')->find();
                if ($order && $order['status'] == 0) {
                    $now = time();
                    // 更新充值订单状态
                    Db::name('balance_recharge_order')->where(['id' => $order['id']])->update([
                        'status'  => 1,
                        'paytime' => $now,
                    ]);

                    // 获取用户当前余额
                    $before = Db::name('user')->where('id', $order['user_id'])->value('balance') ?: 0;
                    $amount = $order['amount'];
                    $after = function_exists('bcadd') ? bcadd($before, $amount, 2) : $before + $amount;

                    // 增加用户余额
                    Db::name('user')->where('id', $order['user_id'])->setInc('balance', $amount);

                    // 记录余额变动日志
                    Db::name('user_balance_log')->insert([
                        'user_id'    => $order['user_id'],
                        'balance'    => $amount,
                        'before'     => $before,
                        'after'      => $after,
                        'type'       => 1,
                        'memo'       => '在线充值' . $amount . '元',
                        'admin_id'   => 0,
                        'createtime' => $now,
                    ]);
                }

                Db::commit();
            } catch (\think\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
        } catch (Exception $e) {
            \think\Log::record("余额充值回调逻辑处理错误:" . $e->getMessage(), "error");
        }

        if (Service::isVersionV3()) {
            return $pay->success()->getBody()->getContents();
        } else {
            return $pay->success()->send();
        }
    }

    /**
     * 余额充值支付返回
     */
    public function returnbalance()
    {
        $paytype = $this->request->param('paytype');
        $out_trade_no = $this->request->param('out_trade_no');
        $pay = Service::checkReturn($paytype);
        if (!$pay) {
            $this->error('签名错误', '');
        }
        $this->success("请返回网站查看支付结果", addon_url("epay/index/index"));
    }

    /**
     * 罚款支付回调
     */
    public function notifyxf()
    {
        $paytype = $this->request->param('paytype');
        $pay = Service::checkNotify($paytype);
        if (!$pay) {
            return json(['code' => 'FAIL', 'message' => '失败'], 500, ['Content-Type' => 'application/json']);
        }

        $data = Service::isVersionV3() ? $pay->callback() : $pay->verify();
        $this->printLogs('罚款支付' . json_encode($data));
        try {
            if (Service::isVersionV3() && $paytype === 'wechat') {
                $data = $data['resource']['ciphertext'];
                $data['total_fee'] = $data['amount']['total'];
            }

            \think\Log::record($data);
            $payamount = $paytype == 'alipay' ? $data['total_amount'] : $data['total_fee'] / 100;
            $out_trade_no = $data['out_trade_no'];

            \think\Log::record("罚款回调成功，订单号：{$out_trade_no}，金额：{$payamount}");

            Db::startTrans();
            try {
                $fine = Db::name('thug_fine')->where(['pay_order_no' => $out_trade_no, 'status' => 0])->find();
                if ($fine) {
                    // 更新罚单状态
                    Db::name('thug_fine')->where('id', $fine['id'])->update([
                        'status'   => 1,
                        'pay_time' => time(),
                    ]);

                    // 同步 has_unpaid_fine
                    $remainUnpaid = Db::name('thug_fine')->where(['thug_id' => $fine['thug_id'], 'status' => 0])->count();
                    Db::name('thug')->where('id', $fine['thug_id'])->update(['has_unpaid_fine' => $remainUnpaid > 0 ? 1 : 0]);
                }
                Db::commit();
            } catch (\think\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
        } catch (Exception $e) {
            \think\Log::record("罚款回调逻辑处理错误:" . $e->getMessage(), "error");
        }

        if (Service::isVersionV3()) {
            return $pay->success()->getBody()->getContents();
        } else {
            return $pay->success()->send();
        }
    }

    /**
     * 罚款支付返回
     */
    public function returnxf()
    {
        $paytype = $this->request->param('paytype');
        $out_trade_no = $this->request->param('out_trade_no');
        $pay = Service::checkReturn($paytype);
        if (!$pay) {
            $this->error('签名错误', '');
        }
        $this->success("请返回网站查看支付结果", addon_url("epay/index/index"));
    }
}
