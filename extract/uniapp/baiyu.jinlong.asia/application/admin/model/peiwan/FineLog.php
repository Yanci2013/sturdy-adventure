<?php

namespace app\admin\model\peiwan;

use think\Model;


class FineLog extends Model
{

    // 表名
    protected $name = 'fine_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'pay_time_text'
    ];
    

    public function getStatusList()
    {
        return ['pending' => __('Status pending'), 'paid' => __('Status paid')];
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }

    public function getPayTimeTextAttr($value, $data)
    {
        $value = $value ?: ($data['pay_time'] ?? '');
        return $value ? date("Y-m-d H:i:s", $value) : '';
    }

    // 关联商家
    public function fromUser()
    {
        return $this->belongsTo('app\admin\model\User', 'from_user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    // 关联打手
    public function toUser()
    {
        return $this->belongsTo('app\admin\model\User', 'to_user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    // 关联订单
    public function order()
    {
        return $this->belongsTo('app\admin\model\peiwan\Order', 'order_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

}
