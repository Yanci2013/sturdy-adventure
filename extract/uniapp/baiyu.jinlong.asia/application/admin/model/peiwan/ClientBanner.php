<?php

namespace app\admin\model\peiwan;

use think\Model;


class ClientBanner extends Model
{

    // 表名
    protected $name = 'client_banner';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'link_type_text',
        'status_text'
    ];
    

    
    public function getLinkTypeList()
    {
        return ['none' => __('Link_type none'), 'page' => __('Link_type page'), 'url' => __('Link_type url')];
    }

    public function getStatusList()
    {
        return ['normal' => __('Status normal'), 'hidden' => __('Status hidden')];
    }


    public function getLinkTypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['link_type'] ?? '');
        $list = $this->getLinkTypeList();
        return $list[$value] ?? '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }

}
