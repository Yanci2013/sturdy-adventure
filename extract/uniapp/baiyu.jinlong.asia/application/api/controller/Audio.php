<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 音频接口
 */
class Audio extends Api
{
    protected $noNeedLogin = ['list'];
    protected $noNeedRight = '*';

    /**
     * 获取音频列表
     */
    public function list()
    {
        $list = Db::name('audio')
            ->where('status', 'normal')
            ->order('sort desc,id desc')
            ->field('id, title, audio_url, image_url, sort')
            ->select();
        
        // 检查COS是否启用
        $useCos = is_cos_enabled();
        
        foreach ($list as &$item) {
            // 处理音频URL
            if (!empty($item['audio_url'])) {
                if (!preg_match('/^https?:\/\//i', $item['audio_url'])) {
                    $item['audio_url'] = $useCos ? cdnurl($item['audio_url'], true) : request()->domain() . $item['audio_url'];
                }
            }
            // 处理图片URL
            if (!empty($item['image_url'])) {
                if (!preg_match('/^https?:\/\//i', $item['image_url'])) {
                    $item['image_url'] = $useCos ? cdnurl($item['image_url'], true) : request()->domain() . $item['image_url'];
                }
            }
        }
        
        $this->success('获取成功', $list);
    }
}

