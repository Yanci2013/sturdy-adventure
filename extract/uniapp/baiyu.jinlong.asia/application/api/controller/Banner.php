<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 轮播图接口
 */
class Banner extends Api
{
    protected $noNeedLogin = ['list'];
    protected $noNeedRight = '*';

    /**
     * 获取轮播图列表
     */
    public function list()
    {
        $list = Db::name('banner')
            ->where('status', 'normal')
            ->order('sort desc,id desc')
            ->select();
        
        // 处理图片URL（自动判断云存储或本地存储）
        // 检查COS是否启用
        $useCos = is_cos_enabled();
        
        foreach ($list as &$item) {
            if (!empty($item['image'])) {
                if ($useCos) {
                    // 插件启用，使用cdnurl处理（可能是云存储）
                    $item['image'] = cdnurl($item['image'], true);
                } else {
                    // 插件禁用，使用本地URL
                    $item['image'] = request()->domain() . $item['image'];
                }
            }
        }
        unset($item);
        
        $this->success('获取成功', $list);
    }
}

