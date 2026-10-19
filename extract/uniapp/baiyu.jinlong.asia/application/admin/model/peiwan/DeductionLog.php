<?php

namespace app\admin\model\peiwan;

use think\Model;


class DeductionLog extends Model
{

    

    

    // 表名
    protected $name = 'deduction_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'type_text'
    ];
    

    
    public function getTypeList()
    {
        return ['fine' => __('Type fine'), 'refund' => __('Type refund')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['type'] ?? '');
        $list = $this->getTypeList();
        return $list[$value] ?? '';
    }

    // 关联用户
    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    // 关联订单
    public function order()
    {
        return $this->belongsTo('app\admin\model\peiwan\Order', 'order_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

}
