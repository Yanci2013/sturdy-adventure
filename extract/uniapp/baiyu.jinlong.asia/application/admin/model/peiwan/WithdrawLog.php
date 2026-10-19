<?php

namespace app\admin\model\peiwan;

use think\Model;


class WithdrawLog extends Model
{

    // 表名
    protected $name = 'withdraw_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'withdraw_type_text',
        'account_type_text',
        'audit_time_text'
    ];
    

    public function getStatusList()
    {
        return [
            'pending' => '待处理',
            'processing' => '处理中',
            'audit_pending' => '待审核',
            'success' => '成功',
            'failed' => '失败',
            'rejected' => '已拒绝'
        ];
    }

    public function getWithdrawTypeList()
    {
        return [
            'auto' => '自动到账',
            'audit' => '审核提现'
        ];
    }

    public function getAccountTypeList()
    {
        return [
            'wechat' => '微信',
            'alipay' => '支付宝',
            'bank' => '银行卡',
            'qrcode' => '二维码'
        ];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }

    public function getWithdrawTypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['withdraw_type'] ?? '');
        $list = $this->getWithdrawTypeList();
        return $list[$value] ?? '';
    }

    public function getAccountTypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['account_type'] ?? '');
        $list = $this->getAccountTypeList();
        return $list[$value] ?? '';
    }

    public function getAuditTimeTextAttr($value, $data)
    {
        $value = $value ?: ($data['audit_time'] ?? '');
        return $value ? date("Y-m-d H:i:s", $value) : '-';
    }


    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
