<?php

namespace app\admin\model\peiwan;

use think\Model;

class WithdrawMerchantConfig extends Model
{
    // 表名
    protected $name = 'withdraw_merchant_config';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text'
    ];
    
    public function getStatusList()
    {
        return ['normal' => '正常', 'hidden' => '隐藏'];
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }

    /**
     * 获取提现商户配置（单例模式，只返回ID为1的记录）
     * @return array
     */
    public static function getWithdrawMerchantConfig()
    {
        $config = self::where('id', 1)->find();
        if (!$config) {
            return null;
        }
        return $config->toArray();
    }
}

