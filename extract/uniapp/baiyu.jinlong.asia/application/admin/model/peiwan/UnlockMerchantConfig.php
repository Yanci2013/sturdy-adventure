<?php

namespace app\admin\model\peiwan;

use think\Model;

class UnlockMerchantConfig extends Model
{
    // 表名
    protected $name = 'unlock_merchant_config';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'require_withdraw_unlock_text',
        'require_recharge_unlock_text',
        'require_channel_unlock_text',
        'require_chat_unlock_text'
    ];

    public function getStatusList()
    {
        return ['normal' => __('Status normal'), 'hidden' => __('Status hidden')];
    }

    public function getRequireWithdrawUnlockList()
    {
        return ['0' => '否', '1' => '是'];
    }

    public function getRequireRechargeUnlockList()
    {
        return ['0' => '否', '1' => '是'];
    }

    public function getRequireChannelUnlockList()
    {
        return ['0' => '否', '1' => '是'];
    }

    public function getRequireChatUnlockList()
    {
        return ['0' => '否', '1' => '是'];
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }

    public function getRequireWithdrawUnlockTextAttr($value, $data)
    {
        $value = $value ?: ($data['require_withdraw_unlock'] ?? '');
        $list = $this->getRequireWithdrawUnlockList();
        return $list[$value] ?? '';
    }

    public function getRequireRechargeUnlockTextAttr($value, $data)
    {
        $value = $value ?: ($data['require_recharge_unlock'] ?? '');
        $list = $this->getRequireRechargeUnlockList();
        return $list[$value] ?? '';
    }

    public function getRequireChannelUnlockTextAttr($value, $data)
    {
        $value = $value ?: ($data['require_channel_unlock'] ?? '');
        $list = $this->getRequireChannelUnlockList();
        return $list[$value] ?? '';
    }

    public function getRequireChatUnlockTextAttr($value, $data)
    {
        $value = $value ?: ($data['require_chat_unlock'] ?? '');
        $list = $this->getRequireChatUnlockList();
        return $list[$value] ?? '';
    }

    /**
     * 获取解锁商户配置（单例模式，只返回ID为1的记录）
     * @return array
     */
    public static function getUnlockConfig()
    {
        $config = self::where('id', 1)->find();
        if (!$config) {
            // 如果不存在，返回默认值
            return [
                'require_withdraw_unlock' => 1,
                'require_recharge_unlock' => 0,
                'require_channel_unlock' => 0,
                'require_chat_unlock' => 0,
                'unlock_price' => 0,
                'unlock_times' => 10,
                'chat_unlock_price' => 0,
                'chat_unlock_times' => 10,
                'status' => 'normal'
            ];
        }
        return $config->toArray();
    }
}
