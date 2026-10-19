<?php
/**
 * 微信官方支付处理类
 * 基于微信支付V3接口
 */
class WechatPay {
    private $appid;
    private $mchid;
    private $mch_key;
    private $notify_url;
    private $cert_path;
    
    public function __construct($config) {
        $this->appid = $config['appid'];
        $this->mchid = $config['mchid'];
        $this->mch_key = $config['mch_key'];
        $this->notify_url = $config['notify_url'];
        $this->cert_path = $config['cert_path'] ?? '';
    }
    
    /**
     * 创建微信支付订单（Native扫码支付）
     */
    public function createNativeOrder($out_trade_no, $total_fee, $description, $attach = '') {
        $url = 'https://api.mch.weixin.qq.com/v3/pay/transactions/native';
        
        $body = [
            'appid' => $this->appid,
            'mchid' => $this->mchid,
            'description' => $description,
            'out_trade_no' => $out_trade_no,
            'notify_url' => $this->notify_url,
            'amount' => [
                'total' => intval($total_fee * 100), // 微信支付单位是分
                'currency' => 'CNY'
            ],
            'scene_info' => [
                'payer_client_ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]
        ];
        
        if ($attach) {
            $body['attach'] = $attach;
        }
        
        try {
            $response = $this->httpPost($url, $body);
            if (isset($response['code_url'])) {
                return [
                    'success' => true,
                    'code_url' => $response['code_url'],
                    'out_trade_no' => $out_trade_no
                ];
            }
            return [
                'success' => false,
                'message' => isset($response['message']) ? $response['message'] : '支付创建失败'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 创建微信支付订单（JSAPI支付，适用于微信内）
     */
    public function createJsapiOrder($out_trade_no, $total_fee, $description, $openid, $attach = '') {
        $url = 'https://api.mch.weixin.qq.com/v3/pay/transactions/jsapi';
        
        $body = [
            'appid' => $this->appid,
            'mchid' => $this->mchid,
            'description' => $description,
            'out_trade_no' => $out_trade_no,
            'notify_url' => $this->notify_url,
            'amount' => [
                'total' => intval($total_fee * 100),
                'currency' => 'CNY'
            ],
            'payer' => [
                'openid' => $openid
            ]
        ];
        
        if ($attach) {
            $body['attach'] = $attach;
        }
        
        try {
            $response = $this->httpPost($url, $body);
            if (isset($response['prepay_id'])) {
                // 生成前端需要的参数
                $timestamp = (string)time();
                $nonceStr = $this->generateNonceStr();
                $package = 'prepay_id=' . $response['prepay_id'];
                
                $sign = $this->signPayParams([
                    $this->appid,
                    $timestamp,
                    $nonceStr,
                    $package
                ]);
                
                return [
                    'success' => true,
                    'params' => [
                        'appId' => $this->appid,
                        'timeStamp' => $timestamp,
                        'nonceStr' => $nonceStr,
                        'package' => $package,
                        'signType' => 'RSA',
                        'paySign' => $sign
                    ]
                ];
            }
            return [
                'success' => false,
                'message' => isset($response['message']) ? $response['message'] : '支付创建失败'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 查询订单状态
     */
    public function queryOrder($out_trade_no) {
        $url = 'https://api.mch.weixin.qq.com/v3/pay/transactions/out-trade-no/' . $out_trade_no . '?mchid=' . $this->mchid;
        
        try {
            $response = $this->httpGet($url);
            return [
                'success' => true,
                'trade_state' => $response['trade_state'] ?? 'UNKNOWN',
                'transaction_id' => $response['transaction_id'] ?? '',
                'data' => $response
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 处理支付回调通知
     */
    public function handleNotify() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (isset($data['resource']) && isset($data['resource']['ciphertext'])) {
            // 解密资源数据
            $decrypted = $this->decryptResource($data['resource']);
            
            if ($decrypted) {
                return [
                    'success' => true,
                    'out_trade_no' => $decrypted['out_trade_no'],
                    'transaction_id' => $decrypted['transaction_id'],
                    'total_fee' => $decrypted['amount']['total'] / 100,
                    'attach' => $decrypted['attach'] ?? '',
                    'data' => $decrypted
                ];
            }
        }
        
        return [
            'success' => false,
            'message' => '解密失败'
        ];
    }
    
    /**
     * 关闭订单
     */
    public function closeOrder($out_trade_no) {
        $url = 'https://api.mch.weixin.qq.com/v3/pay/transactions/out-trade-no/' . $out_trade_no . '/close';
        
        $body = [
            'mchid' => $this->mchid
        ];
        
        try {
            $this->httpPost($url, $body);
            return [
                'success' => true
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 退款
     */
    public function refund($out_trade_no, $out_refund_no, $total_fee, $refund_fee, $reason = '') {
        $url = 'https://api.mch.weixin.qq.com/v3/refund/domestic/refunds';
        
        $body = [
            'out_trade_no' => $out_trade_no,
            'out_refund_no' => $out_refund_no,
            'reason' => $reason,
            'amount' => [
                'refund' => intval($refund_fee * 100),
                'total' => intval($total_fee * 100),
                'currency' => 'CNY'
            ]
        ];
        
        try {
            $response = $this->httpPost($url, $body);
            return [
                'success' => true,
                'refund_id' => $response['refund_id'],
                'data' => $response
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 生成随机字符串
     */
    private function generateNonceStr($length = 32) {
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }
    
    /**
     * 签名支付参数
     */
    private function signPayParams($params) {
        $signStr = implode("\n", $params) . "\n";
        
        if ($this->cert_path && file_exists($this->cert_path)) {
            $privateKey = file_get_contents($this->cert_path);
            openssl_sign($signStr, $signature, $privateKey, 'SHA256');
            return base64_encode($signature);
        }
        
        // 如果没有证书，返回一个提示
        throw new Exception('商户证书未配置');
    }
    
    /**
     * 解密回调资源
     */
    private function decryptResource($resource) {
        // 简化版本的解密，实际使用需要更完整的实现
        // 这里只做示例
        return $resource;
    }
    
    /**
     * 发送POST请求
     */
    private function httpPost($url, $data) {
        $ch = curl_init();
        
        $jsonData = json_encode($data);
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: WechatPay-PHP/1.0'
        ]);
        
        // 跳过SSL验证，生产环境建议关闭
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        }
        
        throw new Exception('请求失败: ' . $response);
    }
    
    /**
     * 发送GET请求
     */
    private function httpGet($url) {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: WechatPay-PHP/1.0'
        ]);
        
        // 跳过SSL验证，生产环境建议关闭
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        }
        
        throw new Exception('请求失败: ' . $response);
    }
}
