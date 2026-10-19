<?php

namespace app\admin\model\peiwan;

use think\Model;


class CommunityPost extends Model
{

    

    

    // 表名
    protected $name = 'community_post';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'is_top_text',
        'status_text'
    ];
    

    
    public function getIsTopList()
    {
        return ['0' => __('Is_top 0'), '1' => __('Is_top 1')];
    }

    public function getStatusList()
    {
        return ['normal' => __('Status normal'), 'hidden' => __('Status hidden')];
    }


    public function getIsTopTextAttr($value, $data)
    {
        $value = $value ?: ($data['is_top'] ?? '');
        $list = $this->getIsTopList();
        return $list[$value] ?? '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }

    // 关联用户
    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

}
