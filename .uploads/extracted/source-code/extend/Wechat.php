<?php

use think\Cache;
use fast\Http;

class Wechat
{
    protected $mini_appid; //微信小程序 AppID（在微信公众平台 -> 小程序 -> 开发管理 -> 开发设置 中获取）
    protected $mini_secret; //微信小程序 AppSecret（同上位置获取，注意保密）
    protected $mp_appid; //微信公众号 AppID（在微信公众平台 -> 公众号 -> 开发 -> 基本配置 中获取，用于H5网页授权登录）
    protected $mp_secret; //微信公众号 AppSecret（同上位置获取，注意保密）

    public function __construct()
    {
        // ========== 公众号配置（H5网页端微信授权登录用） ==========
        $this->mp_appid = 'wx135b5dad218c479b';       // 公众号 AppID
        $this->mp_secret = 'e84f7014ae533ef6ad0ba3f14e3c1ae5'; // 公众号 AppSecret

        // ========== 小程序配置（微信小程序端登录用） ==========
        $this->mini_appid = 'wx86f2adec4cb67540';      // 小程序 AppID
        $this->mini_secret = '3a49712328dd782f7a537d545106bd5d';       // 小程序 AppSecret（去微信公众平台 -> 小程序 -> 开发管理 -> 开发设置 复制过来）
    }


    public function error_stop($msg = '', $code = 0, $data = null, $status_code = 200, $header = [])
    {
        $result = [
            'code' => $code ?: 0,
            'msg' => $msg,
            'data' => $data
        ];
        $response = \think\Response::create($result, 'json', $status_code)->header($header);
        throw new \think\exception\HttpResponseException($response);
    }

    /**
     * 获取小程序access_token
     *
     * @param boolean $is_upload
     * @return void
     */
    public function getMiniAccessToken($is_upload = false)
    {

        $accessToken = Cache::get('mini_access_token');
        if (!$accessToken || $is_upload) {
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->mini_appid}&secret={$this->mini_secret}";
            $result = json_decode(Http::get($url), true);
            Cache::set('mini_access_token', $result['access_token'], 7100);
            return $result['access_token'];
        }
        return $accessToken;
    }

    /**
     * 小程序登陆
     *
     * @param string $code
     * @return void
     */
    public function miniLogin($code)
    {
        $params = [
            'appid' => $this->mini_appid,
            'secret' => $this->mini_secret,
            'js_code' => $code,
            'grant_type' => 'authorization_code'
        ];
        $result = Http::sendRequest("https://api.weixin.qq.com/sns/jscode2session", $params, 'GET');
        $json = (array) json_decode($result['msg'], true);
        if (array_key_exists('openid', $json) || array_key_exists('unionid', $json)) {
            return $json;
        } else {
            return $this->error_stop();
        }
    }




    /**
     * 微信解密
     *
     * @param string $sessionKey
     * @param string $encryptedData
     * @param string $iv
     * @param string $data
     * @return void
     */
    public function decryptData($sessionKey, $encryptedData, $iv, &$data)
    {
        if (strlen($sessionKey) != 24) {
            return -41001;
        }
        $aesKey = base64_decode($sessionKey);


        if (strlen($iv) != 24) {
            return -41002;
        }
        $aesIV = base64_decode($iv);

        $aesCipher = base64_decode($encryptedData);

        $result = openssl_decrypt($aesCipher, "AES-128-CBC", $aesKey, 1, $aesIV);

        $dataObj = json_decode($result);
        if ($dataObj == NULL) {
            return -41003;
        }
        if ($dataObj->watermark->appid != $this->mini_appid) {
            return -41004;
        }
        $data = $result;
        return 0;
    }


    //避免把iv里面的空格转换为 +
    public function define_str_replace($data)
    {
        return str_replace('+', ' ', $data);
    }

    /**
     * 生成小程序码
     *
     * @param string $page 默认是主页，页面 page，例如 pages/index/index，根路径前不要填加 /，不能携带参数（参数请放在scene字段里），如果不填写这个字段，默认跳主页面。scancode_time为系统保留参数，不允许配置
     * @param string $scene 最大32个可见字符，只支持数字，大小写英文以及部分特殊字符：!#$&'()*+,/:;=?@-._~，其它字符请自行编码为合法字符（因不支持%，中文无法使用 urlencode 处理，请使用其他编码方式）
     * @return void
     */
    public function getWxaCodeun($page, $scene)
    {
        $access_token = $this->getMiniAccessToken();
        $url = 'https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=' . $access_token;

        $param = json_encode([
            'scene' => $scene,
            'page' => $page,

            'check_path' => false,
            'env_version' => 'trial', //要打开的小程序版本。正式版为 "release"，体验版为 "trial"，开发版为 "develop"。默认是正式版。
        ]);
        $result = Http::sendRequest($url, $param, 'post', [CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Content-Length: ' . strlen($param),]]);
        // var_dump($result);die;
        $store_path = '/uploads/' . date('Ymd') . '/' . md5(time() . mt_rand(1000, 9999)) . '.png'; // 存数据库路径
        $save_path = ROOT_PATH . 'public' . $store_path; // 服务器绝对路径
        $dir = dirname($save_path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        file_put_contents($save_path, $result['msg']);

        return $store_path;
    }

    /**
     * 获取公众号access_token
     *
     * @param boolean $is_upload
     * @return void
     */
    public function getMpAccessToken($is_upload = false)
    {
        $accessToken = Cache::get('mp_access_token');
        if (!$accessToken || $is_upload) {
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->mp_appid}&secret={$this->mp_secret}";
            $result = json_decode(Http::get($url), true);
            Cache::set('mp_access_token', $result['access_token'], 7100);
            return $result['access_token'];
        }
        return $accessToken;
    }


    public function getJsapiTicket()
    {
        $accessToken = $this->getMpAccessToken();

        $jsapi_ticket = Cache::get('jsapi_ticket');
        if (!$jsapi_ticket) {
            $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token={$accessToken}&type=jsapi";
            $result = json_decode(Http::get($url), true);
            Cache::set('jsapi_ticket', $result['ticket'], 7100);
            return $result['ticket'];
        }
        return $jsapi_ticket;
    }


    public function getMpConfig($url)
    {
        $jsapi_ticket = $this->getJsapiTicket();
        $timestamp = time();
        $nonceStr = \fast\Random::alnum(12);
        $data = [
            'appid' => $this->mp_appid,
            'timestamp' => $timestamp,
            'nonceStr' => $nonceStr,
        ];
        $str = "jsapi_ticket={$jsapi_ticket}&noncestr={$nonceStr}&timestamp={$timestamp}&url={$url}";
        $signature = sha1($str);
        $data['signature'] = $signature;
        $data['jsapi_ticket'] = $jsapi_ticket;

        return $data;
    }

    /**
     * 公众号登录
     */
    public function mpLogin($code)
    {
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=" . $this->mp_appid . "&secret=" . $this->mp_secret . "&code=" . $code . "&grant_type=authorization_code";
        $result = json_decode(file_get_contents($url), true);
        if (isset($result['errcode'])) {
            return false;
        }
        //根据access_token拉取用户信息
        $url = "https://api.weixin.qq.com/sns/userinfo?access_token=" . $result['access_token'] . "&openid=" . $result['openid'] . "&lang=zh_CN";
        $re = file_get_contents($url);
        $data = json_decode($re, true);
        if (isset($data['errcode'])) {
            $result['msg'] = $data['errmsg'];
            return $result;
        }
        $data['openid'] = $result['openid'];
        return $data;
    }


    /**
     * 发送公众号模板消息
     *
     * @param string $openid 接收者公众号openid
     * @param string $templateId 模板ID
     * @param array  $data 模板数据
     * @param string $miniAppPagePath 点击跳转小程序页面路径
     * @return array
     */
    public function sendMpTemplateMessage($openid, $templateId, $data, $url = '')
    {
        $access_token = $this->getMpAccessToken();
        $apiUrl = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token={$access_token}";
        $param = [
            'touser'      => $openid,
            'template_id' => $templateId,
            'data'        => $data,
        ];
        // 点击模板消息跳转的链接
        if (!empty($url)) {
            $param['url'] = $url;
        }
        $param = json_encode($param, JSON_UNESCAPED_UNICODE);
        $result = Http::sendRequest($apiUrl, $param, 'post', [CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Content-Length: ' . strlen($param)]]);
        return json_decode($result['msg'], true);
    }

    /**
     * 发送下单通知给有接单权限的打手（通过公众号模板消息）
     *
     * @param array $order 订单信息
     * @return void
     */
    public function sendOrderNotifyToThugs($order)
    {
        try {
            $templateId = config('site.order_notify_template_id');
            $page = config('site.order_notify_page') ?: 'https://yuniop.xitifk.cn/h5/';

            if (empty($templateId)) {
                return;
            }

            // 查询商品分类名称
            $goodsType = \think\Db::name('goods_category')
                ->where('id', $order['goods_type'] ?? 0)
                ->value('name') ?: '普通订单';

            // 获取订单的游戏区服
            $gameQf = $order['game_qf'] ?? '';

            \think\Log::record("下单通知调试 - goods_type: {$order['goods_type']}, goodsType: {$goodsType}, gameQf: {$gameQf}, order_id: {$order['id']}", 'info');

            // 查询所有有接单权限且有关注公众号的打手
            $thugs = \think\Db::name('thug')
                ->alias('t')
                ->join('user u', 'u.id = t.user_id')
                ->where('u.openid', '<>', '')
                ->field('u.openid, t.receiving_orders')
                ->select();

            if (empty($thugs)) {
                \think\Log::record("下单通知调试 - 没有打手", 'info');
                return;
            }

            $data = [
                'phrase9' => ['value' => '待接单'],
                'thing3'  => ['value' => mb_substr($gameQf ?: '未知区服', 0, 20)],
                'thing27' => ['value' => mb_substr($order['title'] ?? '新订单', 0, 20)],
                'time4'   => ['value' => date('Y-m-d H:i:s', $order['createtime'] ?? time())],
            ];

            foreach ($thugs as $thug) {
                $openid = $thug['openid'];
                $receivingOrders = $thug['receiving_orders'] ?? '';

                \think\Log::record("下单通知调试 - receiving_orders: {$receivingOrders}", 'info');

                // 检查打手是否有该分类和区服的接单权限
                $hasPermission = $this->checkThugPermission($receivingOrders, $goodsType, $gameQf);

                \think\Log::record("下单通知调试 - hasPermission: " . ($hasPermission ? 'true' : 'false'), 'info');

                if ($hasPermission) {
                    try {
                        $result = $this->sendMpTemplateMessage($openid, $templateId, $data, $page);
                        \think\Log::record("下单通知调试 - 发送结果: openid={$openid}, result=" . json_encode($result), 'info');
                    } catch (\Exception $e) {
                        \think\Log::record("推送打手通知失败[{$openid}]:" . $e->getMessage(), 'error');
                    }
                }
            }
        } catch (\Exception $e) {
            \think\Log::record("发送下单通知异常:" . $e->getMessage(), 'error');
        }
    }

    /**
     * 检查打手是否有接单权限
     *
     * @param string $receivingOrders 打手的接单权限字符串
     * @param string $goodsType 商品分类名称
     * @param string $gameQf 游戏区服
     * @return bool
     */
    private function checkThugPermission($receivingOrders, $goodsType, $gameQf)
    {
        if (empty($receivingOrders)) {
            \think\Log::record("权限检查 - receivingOrders为空", 'info');
            return false;
        }

        $categories = array_filter(array_map('trim', explode(',', $receivingOrders)));

        \think\Log::record("权限检查 - 解析后categories: " . json_encode($categories) . ", 期望分类: {$goodsType}, 期望区服: {$gameQf}", 'info');

        foreach ($categories as $category) {
            $parts = explode('-', $category);
            $catName = trim($parts[0]);

            // 如果只有分类名没有区服，说明该分类下所有区服都可以接单
            if (count($parts) === 1) {
                \think\Log::record("权限检查 - 只有分类名: {$catName}, 比较: " . ($catName === $goodsType ? '匹配' : '不匹配'), 'info');
                if ($catName === $goodsType) {
                    return true;
                }
            } else {
                // 有分类名和区服名，需要都匹配
                $qfName = trim($parts[1]);
                $catMatch = $catName === $goodsType ? '匹配' : '不匹配';
                $qfMatch = $qfName === $gameQf ? '匹配' : '不匹配';
                \think\Log::record("权限检查 - 分类名: {$catName}[{$catMatch}], 区服: {$qfName}[{$qfMatch}]", 'info');
                if ($catName === $goodsType && $qfName === $gameQf) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 获取小程序用户手机号码（通过小程序 getPhoneNumber 授权的 code 换取）
     */
    public function getMiniPhone($code)
    {
        $access_token = $this->getMiniAccessToken();
        $url = "https://api.weixin.qq.com/wxa/business/getuserphonenumber?access_token={$access_token}";
        $param = json_encode([
            'code' => $code
        ]);
        $result = Http::sendRequest($url, $param, 'post', [CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Content-Length: ' . strlen($param),]]);
        $data = json_decode($result['msg'], true);

        if (isset($data['errcode']) && $data['errcode'] === 0) {
            return $data['phone_info']['phoneNumber'];
        }

        return '';
    }
}
