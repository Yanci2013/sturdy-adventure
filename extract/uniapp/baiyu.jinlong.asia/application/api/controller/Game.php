<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 游戏和区服接口
 */
class Game extends Api
{
    protected $noNeedLogin = ['list', 'serverList'];
    protected $noNeedRight = '*';

    /**
     * 获取游戏列表
     */
    public function list()
    {
        $list = Db::name('game')
            ->where('status', 'normal')
            ->order('sort desc,id desc')
            ->select();
        
        // 处理图片URL（自动判断云存储或本地存储）
        // 检查COS是否启用
        $useCos = is_cos_enabled();
        
        foreach ($list as &$item) {
            if (!empty($item['icon'])) {
                if ($useCos) {
                    // 插件启用，使用cdnurl处理（可能是云存储）
                    $item['icon'] = cdnurl($item['icon'], true);
                } else {
                    // 插件禁用，使用本地URL
                    $item['icon'] = request()->domain() . $item['icon'];
                }
            }
        }
        unset($item);
        
        $this->success('获取成功', $list);
    }

    /**
     * 获取区服列表
     */
    public function serverList()
    {
        $gameId = $this->request->get('game_id/d', 0);
        
        if (!$gameId) {
            $this->error('游戏ID不能为空');
        }
        
        $list = Db::name('game_server')
            ->where('game_id', $gameId)
            ->where('status', 'normal')
            ->order('sort desc,id desc')
            ->select();
        
        $this->success('获取成功', $list);
    }
}

