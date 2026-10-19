<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 公告接口
 */
class Announcement extends Api
{
    protected $noNeedLogin = ['list'];
    protected $noNeedRight = '*';

    /**
     * 获取公告列表
     * @param string type 类型：shangjia=商家端, dashou=打手端
     */
    public function list()
    {
        $type = $this->request->get('type', '');
        
        if (!in_array($type, ['shangjia', 'dashou'])) {
            $this->error('类型参数错误');
        }
        
        $list = Db::name('announcement')
            ->where('type', $type)
            ->where('status', 'normal')
            ->order('sort desc,id desc')
            ->field('id, content, createtime')
            ->select();
        
        $this->success('获取成功', $list);
    }
}

