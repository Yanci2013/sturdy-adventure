<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 点单端商品接口
 */
class ClientGoods extends Api
{
    // 商品列表不强制登录
    protected $noNeedLogin = ['list', 'debug'];
    protected $noNeedRight = ['list', 'debug'];

    /**
     * 调试接口 - 查看所有商品
     */
    public function debug()
    {
        $all = Db::name('client_goods')->select();
        $this->success('所有商品数据', $all);
    }

    /**
     * 商品列表
     */
    public function list()
    {
        $page  = $this->request->get('page/d', 1);
        $limit = $this->request->get('limit/d', 20);
        $keyword = $this->request->get('keyword', '');
        $serverId = $this->request->get('server_id/d', 0);

        $where = ['status' => 'normal'];

        // 搜索关键词（商品标题）
        if (!empty($keyword)) {
            $where['title'] = ['like', '%' . $keyword . '%'];
        }
        
        // 只用server_id来查询，server_id=0表示适用所有区服
        if ($serverId > 0) {
            $query = Db::name('client_goods')
                ->where($where)
                ->where(function($q) use ($serverId) {
                    $q->where('server_id', $serverId)->whereOr('server_id', 0);
                });
        } else {
            $query = Db::name('client_goods')->where($where);
        }
        
        $query->order('id DESC')->page($page, $limit);

        $list = $query->select();
        
        // 计算总数
        if ($serverId > 0) {
            $total = Db::name('client_goods')
                ->where($where)
                ->where(function($q) use ($serverId) {
                    $q->where('server_id', $serverId)->whereOr('server_id', 0);
                })
                ->count();
        } else {
            $total = Db::name('client_goods')->where($where)->count();
        }

        if ($list) {
            // 关联游戏和区服名称
            $gameIds = array_column($list, 'game_id');
            $serverIds = array_column($list, 'server_id');

            $games = $gameIds ? Db::name('game')->where('id', 'in', $gameIds)->column('name', 'id') : [];
            $servers = $serverIds ? Db::name('game_server')->where('id', 'in', $serverIds)->column('server_name', 'id') : [];

            // COS处理图片
            $useCos = is_cos_enabled();
            $domain = rtrim(request()->domain(), '/');

            foreach ($list as &$item) {
                $item['game_name'] = isset($games[$item['game_id']]) ? $games[$item['game_id']] : '';
                $item['server_name'] = isset($servers[$item['server_id']]) ? $servers[$item['server_id']] : '';

                if (!empty($item['image'])) {
                    if ($useCos) {
                        $item['image_full'] = cdnurl($item['image'], true);
                    } else {
                        $path = $item['image'];
                        if (strpos($path, '/') !== 0) {
                            $path = '/' . $path;
                        }
                        $item['image_full'] = $domain . $path;
                    }
                } else {
                    $item['image_full'] = '';
                }
            }
            unset($item);
        }

        $this->success('获取成功', [
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit
        ]);
    }
}


