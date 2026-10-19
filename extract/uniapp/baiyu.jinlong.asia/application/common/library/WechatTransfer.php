<?php

namespace app\common\library;

use think\Exception;

/**
 * 微信支付转账工具类（商家转账到零钱 V3 API）
 */
class WechatTransfer
{
    private $merchantId;      // 商户号
    private $apiKey;          // API密钥（V3密钥）
    private $certPath;        // 证书路径
    private $certKeyPath;     // 证书密钥路径
    
    public function __construct($merchantId, $apiKey, $certPath, $certKeyPath)
    {
        $this->merchantId = $merchantId;
        $this->apiKey = $apiKey;
        $this->certPath = $certPath;
        $this->certKeyPath = $certKeyPath;
    }
    
    /**
     * 转账到零钱
     * @param string $openid 用户openid
     * @param float $amount 转账金额（元）
     * @param string $desc 转账描述
     * @param string $outTradeNo 商户订单号
     * @return array
     * @throws Exception
     */
    public function transfer($openid, $amount, $desc, $outTradeNo)
    {
        // 参数校验
        if (empty($openid) || strlen($openid) < 10) {
            throw new Exception('用户openid无效');
        }
        if (!is_numeric($amount) || $amount <= 0) {
            throw new Exception('转账金额无效');
        }
        if (empty($outTradeNo) || strlen($outTradeNo) < 10) {
            throw new Exception('订单号无效');
        }
        
        // 商户配置校验
        if (empty($this->merchantId) || empty($this->apiKey)) {
            throw new Exception('提现商户配置不完整');
        }
        if (empty($this->certPath) || !file_exists($this->certPath)) {
            throw new Exception('商户证书文件不存在');
        }
        if (empty($this->certKeyPath) || !file_exists($this->certKeyPath)) {
            throw new Exception('商户证书密钥文件不存在');
        }
        
        // 转换金额为分（防止浮点精度问题）
        $amountFen = intval(bcmul($amount, 100, 0));
        if ($amountFen < 10) { // 最小0.1元
            throw new Exception('提现金额不能低于0.1元');
        }
        
        // 获取小程序appid
        $systemConfig = \think\Db::name('system_config')->where('id', 1)->find();
        if (!$systemConfig || empty($systemConfig['wxappid'])) {
            throw new Exception('系统配置中未设置小程序AppID');
        }
        $appid = $systemConfig['wxappid'];
        
        // 构建请求数据（新版商家转账接口）
        $data = [
            'appid' => $appid,
            'out_bill_no' => $outTradeNo,
            'transfer_scene_id' => '1005',
            'openid' => $openid,
            'transfer_amount' => $amountFen,
            'transfer_remark' => mb_substr($desc, 0, 30),
            'user_recv_perception' => '劳务报酬',
            'transfer_scene_report_infos' => [
                ['info_type' => '岗位类型', 'info_content' => '兼职人员'],
                ['info_type' => '报酬说明', 'info_content' => '余额提现']
            ]
        ];
        
        $url = 'https://api.mch.weixin.qq.com/v3/fund-app/mch-transfer/transfer-bills';
        
        // 生成签名
        $timestamp = time();
        $nonce = $this->generateNonce();
        $body = json_encode($data, JSON_UNESCAPED_UNICODE);
        
        $serialNo = $this->getCertSerialNo();
        if (empty($serialNo)) {
            throw new Exception('无法获取证书序列号');
        }
        
        $signature = $this->generateSignatureV3('POST', $url, $timestamp, $nonce, $body);
        
        // 构建请求头
        $authHeader = 'WECHATPAY2-SHA256-RSA2048 mchid="' . $this->merchantId . '",nonce_str="' . $nonce . '",timestamp="' . $timestamp . '",serial_no="' . $serialNo . '",signature="' . $signature . '"';
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: WechatTransfer/1.0',
            'Authorization: ' . $authHeader
        ];
        
        // 发送请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('网络请求失败: ' . $error);
        }
        
        $result = json_decode($response, true);
        
        // HTTP 200/201/202 都表示成功
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'transfer_bill_no' => $result['transfer_bill_no'] ?? '',
                'out_bill_no' => $result['out_bill_no'] ?? $outTradeNo,
                'state' => $result['state'] ?? 'ACCEPTED',
                'package_info' => $result['package_info'] ?? ''  // 用于前端唤起确认收款弹窗
            ];
        } else {
            // 获取完整的错误信息
            $errorMsg = '';
            if (isset($result['message']) && $result['message']) {
                $errorMsg = $result['message'];
            } elseif (isset($result['code']) && $result['code']) {
                $errorMsg = '错误码:' . $result['code'];
            }
            if (isset($result['detail']) && isset($result['detail']['detail'])) {
                $errorMsg .= ' - ' . $result['detail']['detail'];
            }
            if (empty($errorMsg)) {
                $errorMsg = 'HTTP ' . $httpCode . ', 响应: ' . substr($response, 0, 200);
            }
            throw new Exception($errorMsg);
        }
    }
    
    /**
     * 生成随机字符串
     */
    private function generateNonce($length = 32)
    {
        return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $length);
    }
    
    /**
     * 获取证书序列号（从证书文件中解析）
     */
    private function getCertSerialNo()
    {
        if (!file_exists($this->certPath)) {
            return '';
        }
        
        $certContent = file_get_contents($this->certPath);
        $cert = openssl_x509_parse($certContent);
        
        if ($cert && isset($cert['serialNumberHex'])) {
            return strtoupper($cert['serialNumberHex']);
        }
        
        // 备用方法：从 serialNumber 字段获取
        if ($cert && isset($cert['serialNumber'])) {
            // serialNumber 可能是十进制或十六进制
            $serial = $cert['serialNumber'];
            if (is_numeric($serial)) {
                return strtoupper(dechex($serial));
            }
            return strtoupper($serial);
        }
        
        return '';
    }
    
    /**
     * 生成微信支付V3签名（RSA-SHA256）
     */
    private function generateSignatureV3($method, $url, $timestamp, $nonce, $body)
    {
        // 构建签名字符串
        $urlParts = parse_url($url);
        $canonicalUrl = $urlParts['path'] . (isset($urlParts['query']) ? '?' . $urlParts['query'] : '');
        
        $signStr = $method . "\n" . 
                   $canonicalUrl . "\n" . 
                   $timestamp . "\n" . 
                   $nonce . "\n" . 
                   $body . "\n";
        
        // 读取私钥
        $privateKey = file_get_contents($this->certKeyPath);
        if (!$privateKey) {
            throw new Exception('无法读取证书私钥文件');
        }
        
        // 使用RSA-SHA256签名
        $pkeyId = openssl_pkey_get_private($privateKey);
        if (!$pkeyId) {
            throw new Exception('私钥格式错误');
        }
        
        $signature = '';
        openssl_sign($signStr, $signature, $pkeyId, 'SHA256');
        
        return base64_encode($signature);
    }
}

