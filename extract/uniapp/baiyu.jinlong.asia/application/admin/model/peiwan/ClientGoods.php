<?php

namespace app\admin\model\peiwan;

use think\Model;

class ClientGoods extends Model
{
    // 表名
    protected $name = 'client_goods';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text'
    ];

    public function getStatusList()
    {
        return ['normal' => __('Status normal'), 'hidden' => __('Status hidden')];
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }

    /**
     * 图片字段保存前处理
     * - 如果是完整URL（例如COS域名或站点域名），只保留 /uploads/... 相对路径，和游戏管理等保持一致
     */
    public function setImageAttr($value)
    {
        if (!$value) {
            return '';
        }

        // 如果是 http(s) 开头的完整URL，尝试截取 /uploads/ 后面的相对路径
        if (preg_match('#^https?://[^/]+(/uploads/.*)$#i', $value, $matches)) {
            // 去掉开头的斜杠，保持与其他模块一致的存储方式
            return ltrim($matches[1], '/');
        }

        // 其他情况按原样保存
        return $value;
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


