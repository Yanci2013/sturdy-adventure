<?php

namespace app\api\controller;

use think\Controller;
use think\Db;
use think\Request;
use addons\epay\library\Service as EpayService;

/**
 * 智能抢单神器 API
 *
 * 接口列表：
 * GET  qiangdan/info        获取抢单配置和当前打手订阅状态
 * POST qiangdan/purchase    购买抢单神器（返回支付链接）
 * POST qiangdan/sync        同步支付结果（前端轮询调用）
 * POST qiangdan/toggle      开启/关闭抢单
 * POST qiangdan/saveConfig  保存抢单配置
 * GET  qiangdan/logs        获取抢单日志
 */
class Qiangdan extends Controller
{
    /**
     * 无需登录的方法
     */
    protected $noNeedLogin = ['notify'];

    /**
     * 当前登录用户ID
     */
    protected $userId = 0;

    /**
     * 当前打手ID
     */
    protected $thugId = 0;

    /**
     * 初始化
     */
    public function _initialize()
    {
        parent::_initialize();
        // 跨域
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: token, Content-Type, X-Requested-With');

        if (Request::instance()->isOptions()) {
            exit();
        }

        // 检查是否需要登录
        $action = Request::instance()->action();
        if (!in_array($action, $this->noNeedLogin)) {
            $this->checkLogin();
        }
    }

    /**
     * Token 登录验证
     */
    protected function checkLogin()
    {
        $token = Request::instance()->header('token');
        if (!$token) {
            $token = Request::instance()->request('token');
        }
        if (!$token) {
            $this->error('请先登录', null, 401);
        }

        // 从 token 表查询用户
        $tokenInfo = Db::name('user_token')->where('token', $token)->find();
        if (!$tokenInfo) {
            $this->error('登录已过期', null, 401);
        }
        // 检查是否过期
        if (!empty($tokenInfo['expiretime']) && $tokenInfo['expiretime'] < time()) {
            $this->error('登录已过期', null, 401);
        }

        $this->userId = $tokenInfo['user_id'];

        // 查询打手信息（优先用 user_id 关联，其次用请求传入的 thug_id）
        $thugIdParam = intval(Request::instance()->request('thug_id', 0));
        if ($thugIdParam) {
            $thug = Db::name('thug')->where('id', $thugIdParam)->where('user_id', $this->userId)->find();
        } else {
            $thug = Db::name('thug')->where('user_id', $this->userId)->find();
        }
        if ($thug) {
            $this->thugId = $thug['id'];
        }
    }

    /**
     * 获取配置值
     */
    protected function getConfig($name, $default = '')
    {
        $config = Db::name('config')->where('name', $name)->value('value');
        return $config !== null ? $config : $default;
    }

    /**
     * 获取抢单配置和当前打手订阅状态
     */
    public function info()
    {
        // 全局配置
        $enabled = intval($this->getConfig('qiangdan_enabled', '1'));
        $price = floatval($this->getConfig('qiangdan_price', '25'));
        $freeThugs = $this->getConfig('qiangdan_free_thugs', '');

        // 打手信息
        $thug = Db::name('thug')->where('id', $this->thugId)->find();
        if (!$thug) {
            return $this->success('获取成功', [
                'enabled' => $enabled,
                'price' => $price,
                'is_subscribed' => false,
                'is_free' => false,
                'expire_time' => 0,
                'expire_text' => '',
                'qiangdan_status' => 0,
                'config' => (object)[],
            ]);
        }

        $expireTime = intval($thug['qiangdan_expire_time']);
        $now = time();
        $isSubscribed = ($expireTime > $now);

        // 检查是否在免费列表中
        $freeList = array_filter(explode(',', $freeThugs));
        $isFree = in_array($this->thugId, $freeList);

        // 免费打手强制视为已订阅
        if ($isFree && !$isSubscribed) {
            $isSubscribed = true;
            $expireTime = $now + 365 * 24 * 3600; // 免费给一年
        }

        $expireText = $isSubscribed ? date('Y-m-d H:i', $expireTime) : '';

        $config = json_decode($thug['qiangdan_config'], true);
        if (!$config) {
            $config = [
                'exclude_remark' => false,
                'exclude_value_added' => false,
                'keywords' => '',
                'exclude_keywords' => '',
            ];
        }

        return $this->success('获取成功', [
            'enabled' => $enabled,
            'price' => $price,
            'is_subscribed' => $isSubscribed,
            'is_free' => $isFree,
            'expire_time' => $expireTime,
            'expire_text' => $expireText,
            'qiangdan_status' => intval($thug['qiangdan_status']),
            'config' => $config,
        ]);
    }

    /**
     * 购买抢单神器
     */
    public function purchase()
    {
        // 检查是否已开通
        $thug = Db::name('thug')->where('id', $this->thugId)->find();
        if (!$thug) {
            return $this->error('打手信息不存在');
        }

        // 免费打手直接开通
        $freeThugs = $this->getConfig('qiangdan_free_thugs', '');
        $freeList = array_filter(explode(',', $freeThugs));
        if (in_array($this->thugId, $freeList)) {
            $expireTime = time() + 365 * 24 * 3600;
            Db::name('thug')->where('id', $this->thugId)->update([
                'qiangdan_expire_time' => $expireTime,
                'qiangdan_status' => 1,
            ]);
            return $this->success('已为您免费开通', [
                'expire_time' => $expireTime,
                'expire_text' => date('Y-m-d H:i', $expireTime),
            ]);
        }

        // 检查全局开关
        $enabled = intval($this->getConfig('qiangdan_enabled', '1'));
        if (!$enabled) {
            return $this->error('抢单神器暂未开放');
        }

        $days = intval(Request::instance()->post('days', 1));
        if ($days < 1) $days = 1;
        $price = floatval($this->getConfig('qiangdan_price', '25'));
        $amount = $price * $days;

        // 创建订单
        $orderNo = 'QD' . date('YmdHis') . mt_rand(1000, 9999);
        Db::name('qiangdan_order')->insert([
            'order_no' => $orderNo,
            'thug_id' => $this->thugId,
            'amount' => $amount,
            'days' => $days,
            'status' => 'pending',
            'pay_type' => 'wechat',
            'createtime' => time(),
            'updatetime' => time(),
        ]);

        // 调用易支付
        $notifyUrl = Request::instance()->root(true) . '/api/qiangdan/notify';
        $returnUrl = Request::instance()->root(true) . '/api/qiangdan/notify';

        try {
            $result = EpayService::submitOrder(
                $amount,
                $orderNo,
                'wechat',
                '抢单神器-' . $days . '天',
                $notifyUrl,
                $returnUrl,
                'wap'
            );

            $payUrl = '';
            if (is_object($result) && method_exists($result, 'getTargetUrl')) {
                $payUrl = $result->getTargetUrl();
            } elseif (is_string($result)) {
                $payUrl = $result;
            } elseif (is_object($result) && method_exists($result, 'getContent')) {
                $payUrl = $result->getContent();
            }

            if (!$payUrl) {
                return $this->error('支付链接生成失败');
            }

            return $this->success('下单成功', [
                'pay_url' => $payUrl,
                'out_trade_no' => $orderNo,
                'scene' => 'qiangdan',
                'amount' => $amount,
            ]);
        } catch (\Exception $e) {
            return $this->error('支付初始化失败：' . $e->getMessage());
        }
    }

    /**
     * 支付回调通知
     */
    public function notify()
    {
        // 验证支付回调
        $pay = EpayService::checkNotify('wechat');
        if (!$pay) {
            return 'fail';
        }

        $data = Request::instance()->param();
        $outTradeNo = isset($data['out_trade_no']) ? $data['out_trade_no'] : '';
        $transactionId = isset($data['transaction_id']) ? $data['transaction_id'] : '';

        if (!$outTradeNo) {
            return 'fail';
        }

        // 查找订单
        $order = Db::name('qiangdan_order')->where('order_no', $outTradeNo)->find();
        if (!$order) {
            return 'fail';
        }

        // 已处理则直接返回成功
        if ($order['status'] === 'paid') {
            return 'success';
        }

        // 更新订单状态
        Db::name('qiangdan_order')->where('id', $order['id'])->update([
            'status' => 'paid',
            'transaction_id' => $transactionId,
            'updatetime' => time(),
        ]);

        // 更新打手到期时间
        $thug = Db::name('thug')->where('id', $order['thug_id'])->find();
        $now = time();
        $currentExpire = $thug ? intval($thug['qiangdan_expire_time']) : 0;
        $baseTime = $currentExpire > $now ? $currentExpire : $now;
        $newExpire = $baseTime + $order['days'] * 24 * 3600;

        Db::name('thug')->where('id', $order['thug_id'])->update([
            'qiangdan_expire_time' => $newExpire,
        ]);

        // 更新订单到期时间
        Db::name('qiangdan_order')->where('id', $order['id'])->update([
            'expire_time' => $newExpire,
        ]);

        return 'success';
    }

    /**
     * 前端轮询同步支付结果
     */
    public function sync()
    {
        $outTradeNo = Request::instance()->post('out_trade_no');
        if (!$outTradeNo) {
            return $this->error('参数错误');
        }

        $order = Db::name('qiangdan_order')->where('order_no', $outTradeNo)->find();
        if (!$order) {
            return $this->error('订单不存在');
        }

        if ($order['status'] === 'paid') {
            return $this->success('支付成功', ['paid' => true]);
        }

        return $this->success('等待支付', ['paid' => false]);
    }

    /**
     * 开启/关闭抢单
     */
    public function toggle()
    {
        $thug = Db::name('thug')->where('id', $this->thugId)->find();
        if (!$thug) {
            return $this->error('打手信息不存在');
        }

        $now = time();
        $expireTime = intval($thug['qiangdan_expire_time']);

        // 检查是否在免费列表
        $freeThugs = $this->getConfig('qiangdan_free_thugs', '');
        $freeList = array_filter(explode(',', $freeThugs));
        $isFree = in_array($this->thugId, $freeList);

        if (!$isFree && $expireTime <= $now) {
            return $this->error('请先开通抢单神器');
        }

        $status = intval(Request::instance()->post('status', 0));
        $status = $status ? 1 : 0;

        Db::name('thug')->where('id', $this->thugId)->update([
            'qiangdan_status' => $status,
        ]);

        return $this->success('操作成功', ['qiangdan_status' => $status]);
    }

    /**
     * 保存抢单配置
     */
    public function saveConfig()
    {
        $thug = Db::name('thug')->where('id', $this->thugId)->find();
        if (!$thug) {
            return $this->error('打手信息不存在');
        }

        $config = [
            'exclude_remark' => Request::instance()->post('exclude_remark', 0) ? true : false,
            'exclude_value_added' => Request::instance()->post('exclude_value_added', 0) ? true : false,
            'keywords' => Request::instance()->post('keywords', ''),
            'exclude_keywords' => Request::instance()->post('exclude_keywords', ''),
        ];

        Db::name('thug')->where('id', $this->thugId)->update([
            'qiangdan_config' => json_encode($config, JSON_UNESCAPED_UNICODE),
        ]);

        return $this->success('保存成功', $config);
    }

    /**
     * 获取抢单日志
     */
    public function logs()
    {
        $page = intval(Request::instance()->get('page', 1));
        $limit = intval(Request::instance()->get('limit', 20));
        $type = Request::instance()->get('type', 'all'); // all / today

        $where = ['thug_id' => $this->thugId];
        if ($type === 'today') {
            $where['createtime'] = ['>=', strtotime(date('Y-m-d'))];
        }

        $total = Db::name('qiangdan_log')->where($where)->count();
        $list = Db::name('qiangdan_log')
            ->where($where)
            ->order('createtime desc')
            ->page($page, $limit)
            ->select();

        // 统计
        $totalGrabbed = Db::name('qiangdan_log')->where('thug_id', $this->thugId)->where('status', 'success')->count();
        $todayStart = strtotime(date('Y-m-d'));
        $todayGrabbed = Db::name('qiangdan_log')->where('thug_id', $this->thugId)->where('status', 'success')->where('createtime', '>=', $todayStart)->count();

        return $this->success('获取成功', [
            'list' => $list,
            'total' => $total,
            'total_grabbed' => $totalGrabbed,
            'today_grabbed' => $todayGrabbed,
        ]);
    }
}
