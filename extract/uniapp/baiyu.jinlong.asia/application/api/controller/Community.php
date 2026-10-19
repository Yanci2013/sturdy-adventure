<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;
use think\Exception;

/**
 * 社区接口
 */
class Community extends Api
{
    protected $noNeedRight = '*';

    /**
     * 获取帖子列表
     */
    public function postList()
    {
        $page = $this->request->get('page/d', 1);
        $limit = $this->request->get('limit/d', 20);
        
        // 查询条件：只显示正常状态的帖子
        $where = ['status' => 'normal'];
        
        // 查询帖子列表，置顶的排在前面
        $list = Db::name('community_post')
            ->where($where)
            ->order('is_top desc, createtime desc')
            ->page($page, $limit)
            ->select();
        
        // 检查COS是否启用
        $useCos = is_cos_enabled();
        
        // 关联查询用户信息和处理图片
        foreach ($list as &$item) {
            $user = Db::name('user')->where('id', $item['user_id'])->find();
            $item['user'] = [
                'id' => $user ? $user['id'] : 0,
                'nickname' => ($user && $user['nickname']) ? $user['nickname'] : '匿名用户',
                'avatar' => $this->processImageUrl($user ? $user['avatar'] : '', $useCos)
            ];
            
            // 处理帖子图片
            if (!empty($item['images'])) {
                $images = json_decode($item['images'], true);
                if (is_array($images)) {
                    foreach ($images as &$img) {
                        $img = $this->processImageUrl($img, $useCos);
                    }
                    unset($img);
                    $item['images'] = $images;
                } else {
                    $item['images'] = [];
                }
            } else {
                $item['images'] = [];
            }
            
            // 检查当前用户是否已点赞
            $item['is_liked'] = 0;
            if ($this->auth->id) {
                $like = Db::name('community_post_like')
                    ->where('post_id', $item['id'])
                    ->where('user_id', $this->auth->id)
                    ->find();
                $item['is_liked'] = $like ? 1 : 0;
            }
        }
        unset($item);
        
        $total = Db::name('community_post')->where($where)->count();
        
        $this->success('获取成功', [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);
    }

    /**
     * 获取帖子详情
     */
    public function postDetail()
    {
        $postId = $this->request->get('id/d', 0);
        
        if (!$postId) {
            $this->error('帖子ID不能为空');
        }
        
        $post = Db::name('community_post')->where('id', $postId)->find();
        
        if (!$post || $post['status'] != 'normal') {
            $this->error('帖子不存在');
        }
        
        // 增加浏览量
        Db::name('community_post')->where('id', $postId)->setInc('view_count');
        $post['view_count']++;
        
        // 检查COS是否启用
        $useCos = is_cos_enabled();
        
        // 关联查询用户信息
        $user = Db::name('user')->where('id', $post['user_id'])->find();
        $post['user'] = [
            'id' => $user ? $user['id'] : 0,
            'nickname' => ($user && $user['nickname']) ? $user['nickname'] : '匿名用户',
            'avatar' => $this->processImageUrl($user ? $user['avatar'] : '', $useCos)
        ];
        
        // 处理帖子图片
        if (!empty($post['images'])) {
            $images = json_decode($post['images'], true);
            if (is_array($images)) {
                foreach ($images as &$img) {
                    $img = $this->processImageUrl($img, $useCos);
                }
                unset($img);
                $post['images'] = $images;
            } else {
                $post['images'] = [];
            }
        } else {
            $post['images'] = [];
        }
        
        // 检查当前用户是否已点赞
        $post['is_liked'] = 0;
        if ($this->auth->id) {
            $like = Db::name('community_post_like')
                ->where('post_id', $postId)
                ->where('user_id', $this->auth->id)
                ->find();
            $post['is_liked'] = $like ? 1 : 0;
        }
        
        $this->success('获取成功', $post);
    }

    /**
     * 发布帖子
     */
    public function publishPost()
    {
        $title = $this->request->post('title', '');
        $content = $this->request->post('content', '');
        $images = $this->request->post('images/a', []);
        
        if (!$title) {
            $this->error('标题不能为空');
        }
        
        if (!$content && empty($images)) {
            $this->error('内容或图片至少填写一项');
        }
        
        // 检查频道解锁（使用提现/充值解锁次数）
        $unlockConfig = Db::name('unlock_merchant_config')->where('id', 1)->where('status', 'normal')->find();
        if ($unlockConfig && $unlockConfig['require_channel_unlock'] == 1) {
            $userInfo = Db::name('user')->where('id', $this->auth->id)->find();
            $remainingTimes = intval($userInfo['unlock_times_remaining'] ?? 0);
            if ($remainingTimes <= 0) {
                $this->error('发布次数已用完，请先购买解锁', [
                    'need_unlock' => true,
                    'unlock_price' => floatval($unlockConfig['unlock_price']),
                    'unlock_times' => intval($unlockConfig['unlock_times'])
                ]);
            }
        }
        
        $data = [
            'user_id' => $this->auth->id,
            'title' => $title,
            'content' => $content,
            'images' => !empty($images) ? json_encode($images) : '',
            'status' => 'normal',
            'createtime' => time(),
            'updatetime' => time()
        ];
        
        $postId = Db::name('community_post')->insertGetId($data);
        
        // 发布成功后扣除解锁次数
        if ($unlockConfig && $unlockConfig['require_channel_unlock'] == 1) {
            Db::name('user')->where('id', $this->auth->id)->where('unlock_times_remaining', '>', 0)->setDec('unlock_times_remaining', 1);
        }
        
        $this->success('发布成功', ['post_id' => $postId]);
    }

    /**
     * 点赞/取消点赞帖子
     */
    public function likePost()
    {
        $postId = $this->request->post('post_id/d', 0);
        
        if (!$postId) {
            $this->error('帖子ID不能为空');
        }
        
        $post = Db::name('community_post')->where('id', $postId)->find();
        if (!$post) {
            $this->error('帖子不存在');
        }
        
        $like = Db::name('community_post_like')
            ->where('post_id', $postId)
            ->where('user_id', $this->auth->id)
            ->find();
        
        Db::startTrans();
        try {
            if ($like) {
                // 取消点赞
                Db::name('community_post_like')->where('id', $like['id'])->delete();
                Db::name('community_post')->where('id', $postId)->setDec('like_count');
                $isLiked = 0;
            } else {
                // 点赞
                Db::name('community_post_like')->insert([
                    'post_id' => $postId,
                    'user_id' => $this->auth->id,
                    'createtime' => time()
                ]);
                Db::name('community_post')->where('id', $postId)->setInc('like_count');
                $isLiked = 1;
                
                // 创建通知（如果不是自己点赞自己的帖子）
                if ($post['user_id'] != $this->auth->id) {
                    $fromUser = Db::name('user')->where('id', $this->auth->id)->find();
                    $content = ($fromUser['nickname'] ?: '用户') . ' 赞了你的帖子';
                    
                    Db::name('system_notification')->insert([
                        'user_id' => $post['user_id'],
                        'type' => 'post_like',
                        'post_id' => $postId,
                        'from_user_id' => $this->auth->id,
                        'content' => $content,
                        'is_read' => 0,
                        'createtime' => time(),
                        'updatetime' => time()
                    ]);
                }
            }
            
            Db::commit();
            $this->success($isLiked ? '点赞成功' : '取消点赞成功', ['is_liked' => $isLiked]);
        } catch (Exception $e) {
            Db::rollback();
            $this->error('操作失败');
        }
    }

    /**
     * 获取评论列表
     */
    public function commentList()
    {
        $postId = $this->request->get('post_id/d', 0);
        $page = $this->request->get('page/d', 1);
        $limit = $this->request->get('limit/d', 20);
        
        if (!$postId) {
            $this->error('帖子ID不能为空');
        }
        
        // 查询一级评论（parent_id=0）
        $where = [
            'post_id' => $postId,
            'parent_id' => 0,
            'status' => 'normal'
        ];
        
        $list = Db::name('community_comment')
            ->where($where)
            ->order('createtime desc')
            ->page($page, $limit)
            ->select();
        
        // 检查COS是否启用
        $useCos = is_cos_enabled();
        
        foreach ($list as &$item) {
            $user = Db::name('user')->where('id', $item['user_id'])->find();
            $item['user'] = [
                'id' => $user ? $user['id'] : 0,
                'nickname' => ($user && $user['nickname']) ? $user['nickname'] : '匿名用户',
                'avatar' => $this->processImageUrl($user ? $user['avatar'] : '', $useCos)
            ];
            
            // 处理评论图片
            if (!empty($item['images'])) {
                $images = json_decode($item['images'], true);
                if (is_array($images)) {
                    foreach ($images as &$img) {
                        $img = $this->processImageUrl($img, $useCos);
                    }
                    unset($img);
                    $item['images'] = $images;
                } else {
                    $item['images'] = [];
                }
            } else {
                $item['images'] = [];
            }
            
            // 查询回复（二级评论）
            $replies = Db::name('community_comment')
                ->where('parent_id', $item['id'])
                ->where('status', 'normal')
                ->order('createtime asc')
                ->limit(5) // 最多显示5条回复
                ->select();
            
            foreach ($replies as &$reply) {
                $replyUser = Db::name('user')->where('id', $reply['user_id'])->find();
                $reply['user'] = [
                    'id' => $replyUser ? $replyUser['id'] : 0,
                    'nickname' => ($replyUser && $replyUser['nickname']) ? $replyUser['nickname'] : '匿名用户',
                    'avatar' => $this->processImageUrl($replyUser ? $replyUser['avatar'] : '', $useCos)
                ];
                
                // 处理回复图片
                if (!empty($reply['images'])) {
                    $replyImages = json_decode($reply['images'], true);
                    if (is_array($replyImages)) {
                        foreach ($replyImages as &$img) {
                            $img = $this->processImageUrl($img, $useCos);
                        }
                        unset($img);
                        $reply['images'] = $replyImages;
                    } else {
                        $reply['images'] = [];
                    }
                } else {
                    $reply['images'] = [];
                }
            }
            unset($reply);
            
            $item['replies'] = $replies;
            $item['reply_count'] = Db::name('community_comment')
                ->where('parent_id', $item['id'])
                ->where('status', 'normal')
                ->count();
            
            // 检查当前用户是否已点赞
            $item['is_liked'] = 0;
            if ($this->auth->id) {
                $like = Db::name('community_comment_like')
                    ->where('comment_id', $item['id'])
                    ->where('user_id', $this->auth->id)
                    ->find();
                $item['is_liked'] = $like ? 1 : 0;
            }
        }
        unset($item);
        
        $total = Db::name('community_comment')->where($where)->count();
        
        $this->success('获取成功', [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);
    }

    /**
     * 发表评论
     */
    public function publishComment()
    {
        $postId = $this->request->post('post_id/d', 0);
        $parentId = $this->request->post('parent_id/d', 0);
        $content = $this->request->post('content', '');
        $images = $this->request->post('images/a', []);
        
        if (!$postId) {
            $this->error('帖子ID不能为空');
        }
        
        if (!$content && empty($images)) {
            $this->error('评论内容或图片至少填写一项');
        }
        
        $post = Db::name('community_post')->where('id', $postId)->find();
        if (!$post) {
            $this->error('帖子不存在');
        }
        
        // 如果parent_id不为0，检查父评论是否存在
        if ($parentId > 0) {
            $parentComment = Db::name('community_comment')->where('id', $parentId)->find();
            if (!$parentComment) {
                $this->error('父评论不存在');
            }
        }
        
        Db::startTrans();
        try {
            $data = [
                'post_id' => $postId,
                'user_id' => $this->auth->id,
                'parent_id' => $parentId,
                'content' => $content,
                'images' => !empty($images) ? json_encode($images) : '',
                'status' => 'normal',
                'createtime' => time(),
                'updatetime' => time()
            ];
            
            $commentId = Db::name('community_comment')->insertGetId($data);
            
            // 更新帖子评论数（只统计一级评论）
            if ($parentId == 0) {
                Db::name('community_post')->where('id', $postId)->setInc('comment_count');
            }
            
            // 创建通知（如果不是自己评论自己的帖子）
            if ($post['user_id'] != $this->auth->id) {
                $fromUser = Db::name('user')->where('id', $this->auth->id)->find();
                $content = ($fromUser['nickname'] ?: '用户') . ' 评论了你的帖子';
                
                Db::name('system_notification')->insert([
                    'user_id' => $post['user_id'],
                    'type' => 'post_comment',
                    'post_id' => $postId,
                    'from_user_id' => $this->auth->id,
                    'comment_id' => $commentId,
                    'content' => $content,
                    'is_read' => 0,
                    'createtime' => time(),
                    'updatetime' => time()
                ]);
            }
            
            Db::commit();
            $this->success('评论成功', ['comment_id' => $commentId]);
        } catch (Exception $e) {
            Db::rollback();
            $this->error('评论失败');
        }
    }

    /**
     * 点赞/取消点赞评论
     */
    public function likeComment()
    {
        $commentId = $this->request->post('comment_id/d', 0);
        
        if (!$commentId) {
            $this->error('评论ID不能为空');
        }
        
        $comment = Db::name('community_comment')->where('id', $commentId)->find();
        if (!$comment) {
            $this->error('评论不存在');
        }
        
        $like = Db::name('community_comment_like')
            ->where('comment_id', $commentId)
            ->where('user_id', $this->auth->id)
            ->find();
        
        Db::startTrans();
        try {
            if ($like) {
                // 取消点赞
                Db::name('community_comment_like')->where('id', $like['id'])->delete();
                Db::name('community_comment')->where('id', $commentId)->setDec('like_count');
                $isLiked = 0;
            } else {
                // 点赞
                Db::name('community_comment_like')->insert([
                    'comment_id' => $commentId,
                    'user_id' => $this->auth->id,
                    'createtime' => time()
                ]);
                Db::name('community_comment')->where('id', $commentId)->setInc('like_count');
                $isLiked = 1;
            }
            
            Db::commit();
            $this->success($isLiked ? '点赞成功' : '取消点赞成功', ['is_liked' => $isLiked]);
        } catch (Exception $e) {
            Db::rollback();
            $this->error('操作失败');
        }
    }

    /**
     * 处理图片URL
     */
    private function processImageUrl($url, $useCos)
    {
        if (empty($url)) {
            return '';
        }
        
        // 如果已经是完整URL，直接返回
        if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
            return $url;
        }
        
        if ($useCos) {
            return cdnurl($url, true);
        } else {
            // 确保 $url 以 / 开头
            if (strpos($url, '/') !== 0) {
                $url = '/' . $url;
            }
            $domain = request()->domain();
            $domain = rtrim($domain, '/');
            return $domain . $url;
        }
    }
}

