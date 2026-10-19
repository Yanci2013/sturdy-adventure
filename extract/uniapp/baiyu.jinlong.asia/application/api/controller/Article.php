<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;

/**
 * 文章接口
 */
class Article extends Api
{
    protected $noNeedLogin = ['list', 'detail'];
    protected $noNeedRight = '*';

    /**
     * 获取文章列表
     */
    public function list()
    {
        $list = Db::name('article')
            ->where('status', 'normal')
            ->order('sort desc, id desc')
            ->field('id, title, icon, createtime')
            ->select();
        
        $this->success('获取成功', ['list' => $list]);
    }

    /**
     * 获取文章详情
     * @param int id 文章ID
     */
    public function detail()
    {
        $id = $this->request->get('id', 0);
        
        if (!$id) {
            $this->error('缺少文章ID');
        }
        
        $article = Db::name('article')
            ->where('id', $id)
            ->where('status', 'normal')
            ->field('id, title, icon, content, createtime')
            ->find();
        
        if (!$article) {
            $this->error('文章不存在');
        }
        
        $this->success('获取成功', $article);
    }
}
