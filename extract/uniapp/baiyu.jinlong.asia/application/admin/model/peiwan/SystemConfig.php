<?php

namespace app\admin\model\peiwan;

use think\Model;


class SystemConfig extends Model
{

    

    

    // 表名
    protected $name = 'system_config';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'is_audit_text',
        'is_custom_order_text',
        'is_auth_enabled_text',
        'is_audio_enabled_text',
        'is_withdraw_audit_text'
    ];
    

    
    public function getIsAuditList()
    {
        return ['0' => '否', '1' => '是'];
    }

    public function getIsCustomOrderList()
    {
        return ['0' => '否', '1' => '是'];
    }

    public function getIsAuthEnabledList()
    {
        return ['0' => '否', '1' => '是'];
    }

    public function getIsAudioEnabledList()
    {
        return ['0' => '否', '1' => '是'];
    }

    public function getIsWithdrawAuditList()
    {
        return ['0' => '否', '1' => '是'];
    }


    public function getIsAuditTextAttr($value, $data)
    {
        $value = $value ?: ($data['is_audit'] ?? '');
        $list = $this->getIsAuditList();
        return $list[$value] ?? '';
    }


    public function getIsCustomOrderTextAttr($value, $data)
    {
        $value = $value ?: ($data['is_custom_order'] ?? '');
        $list = $this->getIsCustomOrderList();
        return $list[$value] ?? '';
    }

    public function getIsAuthEnabledTextAttr($value, $data)
    {
        $value = $value ?: ($data['is_auth_enabled'] ?? '');
        $list = $this->getIsAuthEnabledList();
        return $list[$value] ?? '';
    }

    public function getIsAudioEnabledTextAttr($value, $data)
    {
        $value = $value ?: ($data['is_audio_enabled'] ?? '');
        $list = $this->getIsAudioEnabledList();
        return $list[$value] ?? '';
    }

    public function getIsWithdrawAuditTextAttr($value, $data)
    {
        $value = $value ?: ($data['is_withdraw_audit'] ?? '');
        $list = $this->getIsWithdrawAuditList();
        return $list[$value] ?? '';
    }

    /**
     * 获取系统配置（单例模式，只返回ID为1的记录）
     * @return array
     */
    public static function getSystemConfig()
    {
        $config = self::where('id', 1)->find();
        if (!$config) {
            // 如果不存在，返回默认值
            return [
                'is_auth_enabled' => 0,
                'is_custom_order' => 0,
                'is_audio_enabled' => 0,
                'order_quota' => 0,
                'auto_settle_hours' => 0,
                'order_commission_rate' => 0,
                'is_withdraw_audit' => 0
            ];
        }
        return $config->toArray();
    }

}
