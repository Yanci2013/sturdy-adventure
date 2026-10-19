<?php

namespace app\admin\model\peiwan;

use think\Model;

class AgreementConfig extends Model
{
    // 表名
    protected $name = 'agreement_config';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    /**
     * 获取协议配置（单例模式，只返回ID为1的记录）
     * @return array|null
     */
    public static function getAgreementConfig()
    {
        $config = self::where('id', 1)->find();
        return $config ? $config->toArray() : null;
    }
}


