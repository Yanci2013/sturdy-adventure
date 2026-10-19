<?php

namespace app\admin\model\peiwan;

use think\Model;


class Order extends Model
{

    

    

    // 表名
    protected $name = 'order';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'order_type_text',
        'status_text',
        'jiedan_time_text',
        'wancheng_time_text',
        'jiesuan_time_text'
    ];
    

    
    public function getOrderTypeList()
    {
        return ['standard' => __('Order_type standard'), 'custom' => __('Order_type custom')];
    }

    public function getStatusList()
    {
        return ['daijiedan' => __('Status daijiedan'), 'daifuwu' => __('Status daifuwu'), 'daijiesuan' => __('Status daijiesuan'), 'yiwancheng' => __('Status yiwancheng'), 'guanbizhong' => __('Status guanbizhong'), 'kefujieru' => __('Status kefujieru'), 'yichexiao' => __('Status yichexiao')];
    }


    public function getOrderTypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['order_type'] ?? '');
        $list = $this->getOrderTypeList();
        return $list[$value] ?? '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }


    public function getJiedanTimeTextAttr($value, $data)
    {
        $value = $value ?: ($data['jiedan_time'] ?? '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getWanchengTimeTextAttr($value, $data)
    {
        $value = $value ?: ($data['wancheng_time'] ?? '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getJiesuanTimeTextAttr($value, $data)
    {
        $value = $value ?: ($data['jiesuan_time'] ?? '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setJiedanTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setWanchengTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setJiesuanTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    // 关联商家
    public function shangjia()
    {
        return $this->belongsTo('app\admin\model\User', 'shangjia_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    // 关联打手
    public function dashou()
    {
        return $this->belongsTo('app\admin\model\User', 'dashou_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    // 关联游戏
    public function game()
    {
        return $this->belongsTo('app\admin\model\peiwan\Game', 'game_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    // 关联区服
    public function server()
    {
        return $this->belongsTo('app\admin\model\peiwan\GameServer', 'server_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


}
