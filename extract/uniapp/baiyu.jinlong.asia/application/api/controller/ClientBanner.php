<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 点单端轮播图接口
 */
class ClientBanner extends Api
{
    protected $noNeedLogin = ['list'];
    protected $noNeedRight = '*';

    /**
     * 获取点单端轮播图列表
     */
    public function list()
    {
        $list = Db::name('client_banner')
            ->where('status', 'normal')
            ->order('sort desc,id desc')
            ->select();
        
        // 检查云存储是否启用
        $useCos = is_cos_enabled();
        
        foreach ($list as &$item) {
            // 处理图片URL
            if (!empty($item['image'])) {
                if (!preg_match('/^https?:\/\//i', $item['image'])) {
                    $item['image'] = $useCos ? cdnurl($item['image'], true) : request()->domain() . $item['image'];
                }
            }
        }
        
        $this->success('获取成功', $list);
    }
}
