<?php

namespace app\admin\model\peiwan;

use think\Model;


class Message extends Model
{

    

    

    // 表名
    protected $name = 'message';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'msg_type_text',
        'is_read_text'
    ];
    

    
    public function getMsgTypeList()
    {
        return ['text' => __('Msg_type text'), 'image' => __('Msg_type image'), 'file' => __('Msg_type file')];
    }

    public function getIsReadList()
    {
        return ['0' => __('Is_read 0'), '1' => __('Is_read 1')];
    }


    public function getMsgTypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['msg_type'] ?? '');
        $list = $this->getMsgTypeList();
        return $list[$value] ?? '';
    }


    public function getIsReadTextAttr($value, $data)
    {
        $value = $value ?: ($data['is_read'] ?? '');
        $list = $this->getIsReadList();
        return $list[$value] ?? '';
    }

    // 关联发送者
    public function fromuser()
    {
        return $this->belongsTo('app\admin\model\User', 'from_user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    // 关联接收者
    public function touser()
    {
        return $this->belongsTo('app\admin\model\User', 'to_user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    // 关联订单
    public function order()
    {
        return $this->belongsTo('app\admin\model\peiwan\Order', 'order_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

}
