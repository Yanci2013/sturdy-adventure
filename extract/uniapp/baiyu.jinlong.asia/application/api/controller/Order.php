<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;
use think\Exception;

/**
 * 订单接口
 */
class Order extends Api
{
    protected $noNeedRight = '*';

    /**
     * 获取订单列表
     */
    public function list()
    {
        $status = $this->request->get('status', '');
        $gameId = $this->request->get('game_id/d', 0);
        $serverId = $this->request->get('server_id/d', 0);
        $page = $this->request->get('page/d', 1);
        $limit = $this->request->get('limit/d', 10);
        $keyword = $this->request->get('keyword', ''); // 搜索关键词
        $sortField = $this->request->get('sort_field', 'createtime'); // 排序字段
        $sortOrder = $this->request->get('sort_order', 'desc'); // 排序方向
        
        $where = [];
        
        // 根据用户角色筛选（直接仏user表读取role_type）
        $user = $this->auth->getUser();
        $roleType = $user ? $user->role_type : '';
                
        // 未认证用户不能查看任何订单
        if ($roleType === 'weironzheng' || empty($roleType)) {
            $this->success('获取成功', [
                'list' => [],
                'total' => 0,
                'stats' => ['total' => 0, 'totalAmount' => 0]
            ]);
            return;
        }
                
        if (in_array($roleType, ['dashou', 'jindashou'])) {
            // 打手/金牌打手：只看自己接的订单
            $where['dashou_id'] = $this->auth->id;
            
            // 状态筛选
            if ($status && $status != 'all') {
                $where['status'] = $status;
            }
        } elseif (in_array($roleType, ['shangjia', 'jinshangjia'])) {
            // 商家/金牌商家：查看自己发布的订单
            $where['shangjia_id'] = $this->auth->id;
            
            // 状态筛选
            if ($status && $status != 'all') {
                $where['status'] = $status;
            }
        } elseif ($roleType === 'kefu') {
            // 客服：查看上级商家的订单 + 自己接的订单
            $parentId = isset($user->parent_id) ? intval($user->parent_id) : 0;
            $userId = $this->auth->id;
            
            // 使用标记表示需要特殊处理客服条件
            $where['_kefu_condition'] = [
                'parent_id' => $parentId,
                'user_id' => $userId
            ];
            
            // 状态筛选
            if ($status && $status != 'all') {
                $where['status'] = $status;
            }
        } elseif ($roleType === 'shouhou') {
            // 售后：平台售后，可以查看客服介入状态的订单 + 自己处理过的订单
            // 使用标记表示需要特殊处理售后条件
            $where['_shouhou_condition'] = [
                'user_id' => $this->auth->id
            ];
            
            if ($status && $status != 'all') {
                $where['status'] = $status;
            }
        } elseif (in_array($roleType, ['guanshi', 'zuzhang', 'zongguan'])) {
            // 管事、组长、总管：只看自己接的订单
            $where['dashou_id'] = $this->auth->id;
            
            // 状态筛选
            if ($status && $status != 'all') {
                $where['status'] = $status;
            }
        } else {
            // 其他角色（如user）：返回空列表
            $this->success('获取成功', [
                'list' => [],
                'total' => 0,
                'stats' => ['total' => 0, 'totalAmount' => 0]
            ]);
            return;
        }
        
        // 游戏筛选
        if ($gameId) {
            $where['game_id'] = $gameId;
        }
        
        // 区服筛选
        if ($serverId) {
            $where['server_id'] = $serverId;
        }
        
        // 构建查询
        $query = Db::name('order');
        
        // 处理客服特殊条件：上级商家的订单 OR 自己接的订单
        if (isset($where['_kefu_condition'])) {
            $kefuCondition = $where['_kefu_condition'];
            unset($where['_kefu_condition']);
            
            $parentId = $kefuCondition['parent_id'];
            $userId = $kefuCondition['user_id'];
            
            $query->where(function($q) use ($parentId, $userId) {
                if ($parentId > 0) {
                    $q->where('shangjia_id', $parentId)
                      ->whereOr('dashou_id', $userId);
                } else {
                    $q->where('dashou_id', $userId);
                }
            });
        }
        
        // 处理售后特殊条件：客服介入状态的订单 OR 自己处理过的订单
        if (isset($where['_shouhou_condition'])) {
            $shouhouCondition = $where['_shouhou_condition'];
            unset($where['_shouhou_condition']);
            
            $userId = $shouhouCondition['user_id'];
            
            // 售后可以看到：客服介入状态的所有订单 OR 自己接的订单
            $query->where(function($q) use ($userId) {
                $q->where('status', 'kefujieru')
                  ->whereOr('dashou_id', $userId);
            });
            
            // 如果指定了状态筛选，且不是客服介入，则只看自己接的
            if (isset($where['status']) && $where['status'] !== 'kefujieru') {
                $query = Db::name('order');
                $query->where('dashou_id', $userId);
            } else {
                // 如果是客服介入状态，移除status条件，因为已经在上面处理了
                if (isset($where['status']) && $where['status'] === 'kefujieru') {
                    unset($where['status']);
                }
            }
        }
        
        // 搜索功能：搜索订单号、用户ID、老板联系方式
        if (!empty($keyword)) {
            $keywordNum = is_numeric($keyword) ? intval($keyword) : 0;
            if ($keywordNum > 0) {
                // 数字关键词：可能是订单ID、订单号、用户ID或老板联系方式
                $query->where(function($query) use ($keywordNum, $keyword) {
                    $query->where('id', $keywordNum)
                          ->whereOr('order_no', 'like', '%' . $keyword . '%')
                          ->whereOr('shangjia_id', $keywordNum)
                          ->whereOr('dashou_id', $keywordNum)
                          ->whereOr('boss_contact', 'like', '%' . $keyword . '%');
                });
            } else {
                // 文本关键词：搜索订单号或老板联系方式
                $query->where(function($query) use ($keyword) {
                    $query->where('order_no', 'like', '%' . $keyword . '%')
                          ->whereOr('boss_contact', 'like', '%' . $keyword . '%');
                });
            }
        }
        
        $query->where($where);
        
        // 计算统计数据（在分页之前）- 需要单独构建查询避免count后重置
        $statsWhere = $where;
        $statsTotal = Db::name('order');
        $statsAmount = Db::name('order');
        
        // 处理客服特殊条件
        if (isset($statsWhere['_kefu_condition'])) {
            $kefuCondition = $statsWhere['_kefu_condition'];
            unset($statsWhere['_kefu_condition']);
            $parentId = $kefuCondition['parent_id'];
            $userId = $kefuCondition['user_id'];
            $statsTotal->where(function($q) use ($parentId, $userId) {
                if ($parentId > 0) {
                    $q->where('shangjia_id', $parentId)->whereOr('dashou_id', $userId);
                } else {
                    $q->where('dashou_id', $userId);
                }
            });
            $statsAmount->where(function($q) use ($parentId, $userId) {
                if ($parentId > 0) {
                    $q->where('shangjia_id', $parentId)->whereOr('dashou_id', $userId);
                } else {
                    $q->where('dashou_id', $userId);
                }
            });
        }
        
        // 处理售后特殊条件
        if (isset($statsWhere['_shouhou_condition'])) {
            $shouhouCondition = $statsWhere['_shouhou_condition'];
            unset($statsWhere['_shouhou_condition']);
            $userId = $shouhouCondition['user_id'];
            if (isset($statsWhere['status']) && $statsWhere['status'] !== 'kefujieru') {
                $statsTotal->where('dashou_id', $userId);
                $statsAmount->where('dashou_id', $userId);
            } else {
                $statsTotal->where(function($q) use ($userId) {
                    $q->where('status', 'kefujieru')->whereOr('dashou_id', $userId);
                });
                $statsAmount->where(function($q) use ($userId) {
                    $q->where('status', 'kefujieru')->whereOr('dashou_id', $userId);
                });
                if (isset($statsWhere['status']) && $statsWhere['status'] === 'kefujieru') {
                    unset($statsWhere['status']);
                }
            }
        }
        
        // 搜索条件
        if (!empty($keyword)) {
            $keywordNum = is_numeric($keyword) ? intval($keyword) : 0;
            if ($keywordNum > 0) {
                $statsTotal->where(function($query) use ($keywordNum, $keyword) {
                    $query->where('id', $keywordNum)
                          ->whereOr('order_no', 'like', '%' . $keyword . '%')
                          ->whereOr('shangjia_id', $keywordNum)
                          ->whereOr('dashou_id', $keywordNum);
                });
                $statsAmount->where(function($query) use ($keywordNum, $keyword) {
                    $query->where('id', $keywordNum)
                          ->whereOr('order_no', 'like', '%' . $keyword . '%')
                          ->whereOr('shangjia_id', $keywordNum)
                          ->whereOr('dashou_id', $keywordNum);
                });
            } else {
                $statsTotal->where('order_no', 'like', '%' . $keyword . '%');
                $statsAmount->where('order_no', 'like', '%' . $keyword . '%');
            }
        }
        
        $statsTotal->where($statsWhere);
        $statsAmount->where($statsWhere);
        
        $stats = [
            'total' => $statsTotal->count(),
            'totalAmount' => floatval($statsAmount->sum('price') ?: 0)
        ];
        
        // 排序
        $allowedSortFields = ['createtime', 'price', 'updatetime'];
        $sortField = in_array($sortField, $allowedSortFields) ? $sortField : 'createtime';
        $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? strtolower($sortOrder) : 'desc';
        
        $list = $query->order($sortField . ' ' . $sortOrder)
            ->page($page, $limit)
            ->select();
        
        // 检查COS是否启用
        $useCos = is_cos_enabled();
        
        // 关联查询游戏和区服信息，以及商家信息
        foreach ($list as &$item) {
            $game = Db::name('game')->where('id', $item['game_id'])->find();
            $server = Db::name('game_server')->where('id', $item['server_id'])->find();
            $item['game_name'] = $game ? $game['name'] : '';
            $item['server_name'] = $server ? $server['server_name'] : '';
            
            // 查询商家信息
            if (!empty($item['shangjia_id'])) {
                $merchant = Db::name('user')->where('id', $item['shangjia_id'])->field('id, nickname, avatar')->find();
                if ($merchant) {
                    // 处理商家头像URL
                    if (!empty($merchant['avatar'])) {
                        if (!preg_match('/^https?:\/\//i', $merchant['avatar'])) {
                            $merchant['avatar'] = $useCos ? cdnurl($merchant['avatar'], true) : request()->domain() . $merchant['avatar'];
                        }
                    }
                    
                    // 查询商家标签
                    $tagIds = Db::name('user_tag')->where('user_id', $merchant['id'])->column('tag_id');
                    $tags = [];
                    if (!empty($tagIds)) {
                        $tagList = Db::name('tag')->where('id', 'in', $tagIds)->where('status', 'normal')->field('id, name, color')->select();
                        foreach ($tagList as $tag) {
                            $tags[] = [
                                'id' => $tag['id'],
                                'name' => $tag['name'],
                                'color' => $tag['color'] ?: '#007AFF'
                            ];
                        }
                    }
                    
                    $item['merchant'] = [
                        'id' => $merchant['id'],
                        'nickname' => $merchant['nickname'] ?? '',
                        'avatar' => $merchant['avatar'] ?? '',
                        'tags' => $tags
                    ];
                } else {
                    $item['merchant'] = null;
                }
            } else {
                $item['merchant'] = null;
            }
            
            // 处理游戏图标URL
            if (!empty($game['icon'])) {
                if ($useCos) {
                    $item['game_icon'] = cdnurl($game['icon'], true);
                } else {
                    $icon = $game['icon'];
                    if (strpos($icon, '/') !== 0) {
                        $icon = '/' . $icon;
                    }
                    $domain = request()->domain();
                    $domain = rtrim($domain, '/');
                    $item['game_icon'] = $domain . $icon;
                }
            } else {
                $item['game_icon'] = '';
            }
            
            // 处理证明图片URL
            if (!empty($item['proof_images'])) {
                $proofImages = json_decode($item['proof_images'], true);
                if (is_array($proofImages)) {
                    foreach ($proofImages as &$img) {
                        if (!empty($img)) {
                            // 如果已经是完整URL，不再处理
                            if (strpos($img, 'http://') === 0 || strpos($img, 'https://') === 0) {
                                // 已经是完整URL，直接使用
                            } elseif ($useCos) {
                                $img = cdnurl($img, true);
                            } else {
                                // 确保 $img 以 / 开头
                                if (strpos($img, '/') !== 0) {
                                    $img = '/' . $img;
                                }
                                $domain = request()->domain();
                                // 确保 domain 不以 / 结尾
                                $domain = rtrim($domain, '/');
                                $img = $domain . $img;
                            }
                        }
                    }
                    unset($img);
                    $item['proof_images'] = $proofImages;
                }
            }
            
            // 格式化时间
            $item['createtime'] = $item['createtime'];
            
            // 商家备注仅商家/客服可见
            $canSeeMerchantRemark = false;
            if (in_array($roleType, ['shangjia', 'jinshangjia']) && $item['shangjia_id'] == $this->auth->id) {
                $canSeeMerchantRemark = true;
            } elseif ($roleType === 'kefu') {
                $parentId = isset($user->parent_id) ? intval($user->parent_id) : 0;
                if ($parentId > 0 && $item['shangjia_id'] == $parentId) {
                    $canSeeMerchantRemark = true;
                }
            }
            if (!$canSeeMerchantRemark) {
                unset($item['merchant_remark']);
            }
        }
        
        $total = Db::name('order')->where($where)->count();
        
        $this->success('获取成功', [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'stats' => $stats
        ]);
    }

    /**
     * 获取订单详情
     */
    public function detail()
    {
        $orderId = $this->request->get('id/d', 0);
        
        if (!$orderId) {
            $this->error('订单ID不能为空');
        }
        
        $order = Db::name('order')->where('id', $orderId)->find();
        
        if (!$order) {
            $this->error('订单不存在');
        }
        
        // 权限检查（直接从user表读取role_type）
        $user = $this->auth->getUser();
        $roleType = $user ? $user->role_type : '';
        
        // 打手/金牌打手：可以查看待接单的订单（dashou_id=0表示未指定打手），或者自己接的订单
        if (in_array($roleType, ['dashou', 'jindashou'])) {
            // 如果是待接单状态，所有打手都可以查看（因为dashou_id=0表示未指定打手）
            if ($order['status'] != 'daijiedan') {
                // 如果不是待接单状态，只有接单的打手可以查看
                if ($order['dashou_id'] != $this->auth->id) {
                    $this->error('无权查看此订单');
                }
            }
            // 如果是待接单状态，允许查看（不限制）
        }
        // 商家/金牌商家：只能查看自己发布的订单
        if (in_array($roleType, ['shangjia', 'jinshangjia']) && $order['shangjia_id'] != $this->auth->id) {
            $this->error('无权查看此订单');
        }
        // 客服：只能查看其上级商家发布的订单
        if ($roleType === 'kefu') {
            $parentId = isset($user->parent_id) ? intval($user->parent_id) : 0;
            if ($parentId <= 0 || $order['shangjia_id'] != $parentId) {
                $this->error('无权查看此订单');
            }
        }
        
        // 检查COS是否启用
        $useCos = is_cos_enabled();
        
        // 关联查询
        $game = Db::name('game')->where('id', $order['game_id'])->find();
        $server = Db::name('game_server')->where('id', $order['server_id'])->find();
        $order['game_name'] = $game ? $game['name'] : '';
        $order['server_name'] = $server ? $server['server_name'] : '';
        
        // 处理游戏图标URL
        if (!empty($game['icon'])) {
            if ($useCos) {
                $order['game_icon'] = cdnurl($game['icon'], true);
            } else {
                $icon = $game['icon'];
                if (strpos($icon, '/') !== 0) {
                    $icon = '/' . $icon;
                }
                $domain = request()->domain();
                $domain = rtrim($domain, '/');
                $order['game_icon'] = $domain . $icon;
            }
        } else {
            $order['game_icon'] = '';
        }
        
        // 解析证明图片并处理URL
        if ($order['proof_images']) {
            $proofImages = json_decode($order['proof_images'], true);
            if (is_array($proofImages)) {
                foreach ($proofImages as &$img) {
                    if (!empty($img)) {
                        // 如果已经是完整URL，不再处理
                        if (strpos($img, 'http://') === 0 || strpos($img, 'https://') === 0) {
                            // 已经是完整URL，直接使用
                        } elseif ($useCos) {
                            $img = cdnurl($img, true);
                        } else {
                            // 确保 $img 以 / 开头
                            if (strpos($img, '/') !== 0) {
                                $img = '/' . $img;
                            }
                            $domain = request()->domain();
                            // 确保 domain 不以 / 结尾
                            $domain = rtrim($domain, '/');
                            $img = $domain . $img;
                        }
                    }
                }
                unset($img);
            }
            $order['proof_images'] = $proofImages;
        }
        
        // 获取打手信息（接单人）
        if ($order['dashou_id'] > 0) {
            $dashou = Db::name('user')->where('id', $order['dashou_id'])->field('id,nickname,avatar,role_type')->find();
            if ($dashou) {
                // 处理头像URL
                if (!empty($dashou['avatar'])) {
                    if (strpos($dashou['avatar'], 'http://') !== 0 && strpos($dashou['avatar'], 'https://') !== 0) {
                        if ($useCos) {
                            $dashou['avatar'] = cdnurl($dashou['avatar'], true);
                        } else {
                            $avatar = $dashou['avatar'];
                            if (strpos($avatar, '/') !== 0) {
                                $avatar = '/' . $avatar;
                            }
                            $domain = request()->domain();
                            $domain = rtrim($domain, '/');
                            $dashou['avatar'] = $domain . $avatar;
                        }
                    }
                }
                $order['dashou'] = $dashou;
            }
        }
        
        // 获取商家信息（发单人）
        if ($order['shangjia_id'] > 0) {
            $shangjia = Db::name('user')->where('id', $order['shangjia_id'])->field('id,nickname,avatar,role_type')->find();
            if ($shangjia) {
                // 处理头像URL
                if (!empty($shangjia['avatar'])) {
                    if (strpos($shangjia['avatar'], 'http://') !== 0 && strpos($shangjia['avatar'], 'https://') !== 0) {
                        if ($useCos) {
                            $shangjia['avatar'] = cdnurl($shangjia['avatar'], true);
                        } else {
                            $avatar = $shangjia['avatar'];
                            if (strpos($avatar, '/') !== 0) {
                                $avatar = '/' . $avatar;
                            }
                            $domain = request()->domain();
                            $domain = rtrim($domain, '/');
                            $shangjia['avatar'] = $domain . $avatar;
                        }
                    }
                }
                $order['shangjia'] = $shangjia;
            }
        }
        
        // 获取客服信息（派单人，仅当客服派单时有值）
        if (!empty($order['kefu_id']) && $order['kefu_id'] > 0) {
            $kefu = Db::name('user')->where('id', $order['kefu_id'])->field('id,nickname,avatar,role_type')->find();
            if ($kefu) {
                // 处理头像URL
                if (!empty($kefu['avatar'])) {
                    if (strpos($kefu['avatar'], 'http://') !== 0 && strpos($kefu['avatar'], 'https://') !== 0) {
                        if ($useCos) {
                            $kefu['avatar'] = cdnurl($kefu['avatar'], true);
                        } else {
                            $avatar = $kefu['avatar'];
                            if (strpos($avatar, '/') !== 0) {
                                $avatar = '/' . $avatar;
                            }
                            $domain = request()->domain();
                            $domain = rtrim($domain, '/');
                            $kefu['avatar'] = $domain . $avatar;
                        }
                    }
                }
                $order['kefu'] = $kefu;
            }
        }
        
        // 计算自动结算时间（待结算状态）
        $order['auto_settle_time'] = 0;
        if ($order['status'] === 'daijiesuan') {
            $systemConfig = Db::name('system_config')->where('id', 1)->find();
            $autoSettleHours = isset($systemConfig['auto_settle_hours']) ? intval($systemConfig['auto_settle_hours']) : 0;
            if ($autoSettleHours > 0 && !empty($order['wancheng_time'])) {
                // 自动结算时间 = 完成时间 + 自动结算小时数
                $order['auto_settle_time'] = intval($order['wancheng_time']) + ($autoSettleHours * 3600);
            }
        }
        
        // 格式化时间字段
        $order['createtime_text'] = !empty($order['createtime']) ? date('Y-m-d H:i:s', $order['createtime']) : '';
        $order['jiedan_time_text'] = !empty($order['jiedan_time']) ? date('Y-m-d H:i:s', $order['jiedan_time']) : '';
        $order['wancheng_time_text'] = !empty($order['wancheng_time']) ? date('Y-m-d H:i:s', $order['wancheng_time']) : '';
        $order['jiesuan_time_text'] = !empty($order['jiesuan_time']) ? date('Y-m-d H:i:s', $order['jiesuan_time']) : '';
        
        // 商家备注仅商家/客服可见（商家ID匹配或客服的上级商家ID匹配）
        $canSeeMerchantRemark = false;
        if (in_array($roleType, ['shangjia', 'jinshangjia']) && $order['shangjia_id'] == $this->auth->id) {
            $canSeeMerchantRemark = true;
        } elseif ($roleType === 'kefu') {
            $parentId = isset($user->parent_id) ? intval($user->parent_id) : 0;
            if ($parentId > 0 && $order['shangjia_id'] == $parentId) {
                $canSeeMerchantRemark = true;
            }
        }
        if (!$canSeeMerchantRemark) {
            unset($order['merchant_remark']);
        }
        
        $this->success('获取成功', $order);
    }

    /**
     * 抢单/接单
     */
    public function grab()
    {
        $orderId = $this->request->post('order_id/d', 0);
        
        if (!$orderId) {
            $this->error('订单ID不能为空');
        }
        
        // 检查用户角色（直接从user表读取role_type）
        $user = $this->auth->getUser();
        $roleType = $user ? $user->role_type : '';
        if (!in_array($roleType, ['dashou', 'jindashou', 'guanshi', 'zuzhang', 'zongguan'])) {
            $this->error('您的角色无法抢单');
        }
        
        // 检查是否有未缴纳的罚款
        $unpaidFineCount = Db::name('fine_log')
            ->where('to_user_id', $this->auth->id)
            ->where('status', 'pending')
            ->count();
        
        if ($unpaidFineCount > 0) {
            $unpaidTotal = Db::name('fine_log')
                ->where('to_user_id', $this->auth->id)
                ->where('status', 'pending')
                ->sum('amount');
            $this->error('您有' . $unpaidFineCount . '笔未缴纳的罚款（共¥' . number_format($unpaidTotal, 2) . '），请先缴纳后再接单');
        }
        
        // 检查打手是否有异常订单未处理（客服介入、待服务、关闭中）
        $abnormalOrders = Db::name('order')
            ->where('dashou_id', $this->auth->id)
            ->where('status', 'in', ['kefujieru', 'daifuwu', 'guanbizhong'])
            ->field('id, status, order_no')
            ->select();
        
        if (!empty($abnormalOrders)) {
            $statusMap = [
                'kefujieru' => '仲裁中',
                'daifuwu' => '待服务',
                'guanbizhong' => '关闭中'
            ];
            $abnormalCount = count($abnormalOrders);
            $firstOrder = $abnormalOrders[0];
            $statusText = $statusMap[$firstOrder['status']] ?? $firstOrder['status'];
            $this->error('您有' . $abnormalCount . '个订单未处理完成（' . $statusText . '），请先处理后再接单');
        }
        
        Db::startTrans();
        try {
            $order = Db::name('order')->where('id', $orderId)->lock(true)->find();
            
            if (!$order) {
                throw new Exception('订单不存在');
            }
            
            // 检查订单状态
            // 检查订单状态
            if ($order['status'] != 'daijiedan') {
                throw new Exception('订单状态不允许抢单');
            }
            
            // 检查金牌商家订单限制：金牌商家的订单只能金牌打手接
            if (!empty($order['shangjia_id'])) {
                $shangjia = Db::name('user')->where('id', $order['shangjia_id'])->field('role_type')->find();
                if ($shangjia && $shangjia['role_type'] === 'jinshangjia') {
                    if ($roleType !== 'jindashou') {
                        throw new Exception('金牌商家的订单只能金牌打手接单');
                    }
                }
            }
            
            // 检查订单是否指定了打手
            if ($order['dashou_id'] > 0) {
                // 如果指定了打手，只有指定的打手可以接单
                if ($order['dashou_id'] != $this->auth->id) {
                    throw new Exception('此订单已指定给其他打手，您无法接单');
                }
                // 指定的打手接单（这种情况理论上不应该出现，因为指定打手后状态应该是daifuwu）
                // 但为了安全起见，还是检查一下
            }
            
            // 检查用户是否有该游戏的会员且未过期
            $gameId = intval($order['game_id'] ?? 0);
            if ($gameId > 0) {
                $userMember = Db::name('user_member')
                    ->where('user_id', $this->auth->id)
                    ->where('game_id', $gameId)
                    ->where('status', 'normal')
                    ->where('end_time', '>', time())
                    ->order('end_time DESC')
                    ->find();
                
                if (!$userMember) {
                    $game = Db::name('game')->where('id', $gameId)->find();
                    $gameName = $game ? $game['name'] : '该游戏';
                    throw new Exception('您没有' . $gameName . '的会员或会员已过期，无法抢单');
                }
            }
            
            // 更新订单
            Db::name('order')->where('id', $orderId)->update([
                'dashou_id' => $this->auth->id,
                'status' => 'daifuwu',
                'jiedan_time' => time(),
                'updatetime' => time()
            ]);
            
            Db::commit();
            $this->success('抢单成功');
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    /**
     * 发布订单
     */
    public function publish()
    {
        $data = $this->request->post();
        
        // 检查用户角色（直接从user表读取role_type）
        $user = $this->auth->getUser();
        $roleType = $user ? $user->role_type : '';
        if (!in_array($roleType, ['shangjia', 'jinshangjia', 'kefu'])) {
            $this->error('只有商家或客服可以发布订单');
        }
        
        // 验证数据
        $validate = [
            'game_id' => 'require|integer',
            'server_id' => 'require|integer',
            'price' => 'require|float|>:0',
            'order_type' => 'require|in:standard,custom'
        ];
        
        if (!$this->validate($data, $validate)) {
            $this->error('参数错误');
        }
        
        $price = floatval($data['price']);
        
        // 获取系统配置
        $systemConfig = Db::name('system_config')->where('id', 1)->find();
        $orderQuota = floatval($systemConfig['order_quota'] ?? 0);
        
        Db::startTrans();
        try {
            // 确定实际扣费的商家ID（如果是客服，使用其上级商家）
            $actualShangjiaId = $this->auth->id;
            $kefuId = 0;
            
            if ($roleType === 'kefu') {
                // 检查客服是否有发单权限
                $canPublishOrder = isset($user->can_publish_order) ? intval($user->can_publish_order) : 0;
                if (!$canPublishOrder) {
                    throw new Exception('您没有发单权限，请联系商家开通');
                }
                
                // 客服发布订单，使用其上级商家
                $kefuId = $this->auth->id;
                $parentId = isset($user->parent_id) ? intval($user->parent_id) : 0;
                if ($parentId <= 0) {
                    throw new Exception('客服未绑定商家，无法发布订单');
                }
                
                // 验证上级是否为商家
                $parent = Db::name('user')->where('id', $parentId)->find();
                if (!$parent || !in_array($parent['role_type'], ['shangjia', 'jinshangjia'])) {
                    throw new Exception('上级不是商家，无法发布订单');
                }
                
                // 检查客服是否可以使用商家余额
                $canUseMerchantBalance = isset($user->can_use_merchant_balance) ? intval($user->can_use_merchant_balance) : 0;
                
                $actualShangjiaId = $parentId;
            }
            
            // 获取商家信息（加锁）
            $shangjia = Db::name('user')->where('id', $actualShangjiaId)->lock(true)->find();
            if (!$shangjia) {
                throw new Exception('商家信息不存在');
            }
            
            $shangjiaQuota = floatval($shangjia['quota'] ?? 0);
            $shangjiaMoney = floatval($shangjia['money'] ?? 0);
            
            // 客服发单时检查是否可以使用商家余额（如果未设置变量，默认为可以）
            $kefuCanUseBalance = isset($canUseMerchantBalance) ? $canUseMerchantBalance : 1;
            
            // 如果系统设置了发单额度阈值（大于0），则判断商家额度是否达到阈值
            if ($orderQuota > 0) {
                // 如果商家额度 < 系统设置的额度阈值
                if ($shangjiaQuota < $orderQuota) {
                    // 计算发单后商家额度是否会超过阈值
                    $newQuota = $shangjiaQuota + $price;
                    if ($newQuota <= $orderQuota) {
                        // 发单后额度不会超过阈值，免费发单并累积增加商家额度
                        $deductQuota = 0;
                        $deductMoney = 0;
                        // 累积增加商家额度
                        Db::name('user')->where('id', $actualShangjiaId)->setInc('quota', $price);
                    } else {
                        // 发单后额度会超过阈值，需要扣余额，商家额度只能累积到阈值
                        $freeQuota = $orderQuota - $shangjiaQuota; // 可以免费的部分
                        $deductMoney = $price - $freeQuota; // 需要扣余额的部分
                        // 客服发单检查使用商家余额权限
                        if ($roleType === 'kefu' && !$kefuCanUseBalance && $deductMoney > 0) {
                            throw new Exception('您没有使用商家余额的权限，请联系商家开通');
                        }
                        if ($shangjiaMoney < $deductMoney) {
                            throw new Exception('商家余额不足，当前余额：¥' . number_format($shangjiaMoney, 2) . '，需要：¥' . number_format($deductMoney, 2));
                        }
                        Db::name('user')->where('id', $actualShangjiaId)->setDec('money', $deductMoney);
                        // 商家额度累积到阈值（不能超过）
                        Db::name('user')->where('id', $actualShangjiaId)->update(['quota' => $orderQuota]);
                        $deductQuota = 0;
                    }
                } else {
                    // 商家额度 >= 系统设置的额度阈值，扣额度
                    if ($shangjiaQuota >= $price) {
                        // 额度足够，直接扣额度
                        Db::name('user')->where('id', $actualShangjiaId)->setDec('quota', $price);
                        $deductQuota = $price;
                        $deductMoney = 0;
                    } else {
                        // 额度不足但达到阈值，先扣完额度，剩余扣余额
                        $deductQuota = $shangjiaQuota;
                        $deductMoney = $price - $shangjiaQuota;
                        // 客服发单检查使用商家余额权限
                        if ($roleType === 'kefu' && !$kefuCanUseBalance && $deductMoney > 0) {
                            throw new Exception('您没有使用商家余额的权限，请联系商家开通');
                        }
                        Db::name('user')->where('id', $actualShangjiaId)->setDec('quota', $deductQuota);
                        if ($shangjiaMoney < $deductMoney) {
                            throw new Exception('商家余额不足，当前余额：¥' . number_format($shangjiaMoney, 2) . '，需要：¥' . number_format($deductMoney, 2));
                        }
                        Db::name('user')->where('id', $actualShangjiaId)->setDec('money', $deductMoney);
                    }
                }
            } else {
                // 系统未设置发单额度阈值（为0），直接扣余额
                $deductQuota = 0;
                $deductMoney = $price;
                // 客服发单检查使用商家余额权限
                if ($roleType === 'kefu' && !$kefuCanUseBalance && $deductMoney > 0) {
                    throw new Exception('您没有使用商家余额的权限，请联系商家开通');
                }
                if ($shangjiaMoney < $deductMoney) {
                    throw new Exception('商家余额不足，当前余额：¥' . number_format($shangjiaMoney, 2) . '，需要：¥' . number_format($deductMoney, 2));
                }
                Db::name('user')->where('id', $actualShangjiaId)->setDec('money', $deductMoney);
            }
            
            // 检查是否指定了打手（仅自定义订单支持）
            $dashouId = 0;
            $orderStatus = 'daijiedan'; // 默认待接单
            
            if ($data['order_type'] === 'custom' && isset($data['dashou_id']) && intval($data['dashou_id']) > 0) {
                $dashouId = intval($data['dashou_id']);
                
                // 验证打手是否存在且角色正确
                $dashou = Db::name('user')->where('id', $dashouId)->find();
                if (!$dashou || !in_array($dashou['role_type'], ['dashou', 'jindashou'])) {
                    throw new Exception('指定的打手不存在或角色不正确');
                }
                
                // 如果指定了打手，订单状态改为待服务
                $orderStatus = 'daifuwu';
            }
            
            // 生成订单号
            $orderNo = 'PW' . date('YmdHis') . rand(1000, 9999);
            
            $orderData = [
                'order_no' => $orderNo,
                'shangjia_id' => $actualShangjiaId,
                'kefu_id' => $kefuId, // 如果是客服发布的，记录客服ID
                'dashou_id' => $dashouId,
                'game_id' => $data['game_id'],
                'server_id' => $data['server_id'],
                'order_type' => $data['order_type'],
                'title' => isset($data['title']) ? $data['title'] : '',
                'content' => isset($data['content']) ? $data['content'] : '',
                'price' => $price,
                'boss_contact' => isset($data['boss_contact']) ? $data['boss_contact'] : '', // 老板联系方式
                'merchant_remark' => isset($data['merchant_remark']) ? $data['merchant_remark'] : '', // 商家备注
                'status' => $orderStatus,
                'createtime' => time(),
                'updatetime' => time()
            ];
            
            // 如果指定了打手，记录接单时间
            if ($dashouId > 0) {
                $orderData['jiedan_time'] = time();
            }
            
            $orderId = Db::name('order')->insertGetId($orderData);
            
            // 记录扣费日志
            if ($deductQuota > 0 || $deductMoney > 0) {
                Db::name('commission_log')->insert([
                    'user_id' => $actualShangjiaId,
                    'order_id' => $orderId,
                    'amount' => $price,
                    'type' => 'expense',
                    'memo' => '发布订单扣费（额度：¥' . number_format($deductQuota, 2) . '，余额：¥' . number_format($deductMoney, 2) . '）',
                    'createtime' => time()
                ]);
                
                // 记录余额明细（只记录实际扣除余额的部分）
                if ($deductMoney > 0) {
                    Db::name('user_money_log')->insert([
                        'user_id' => $actualShangjiaId,
                        'money' => -$deductMoney,
                        'before' => $shangjiaMoney,
                        'after' => $shangjiaMoney - $deductMoney,
                        'memo' => '发布订单扣除余额',
                        'createtime' => time()
                    ]);
                }
            }
            
            Db::commit();
            $this->success('发布成功', ['order_id' => $orderId]);
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    /**
     * 提交订单完成证明
     */
    public function submitProof()
    {
        $orderId = $this->request->post('order_id/d', 0);
        $images = $this->request->post('images/a', []);
        $content = $this->request->post('content', ''); // 完成说明
        
        if (!$orderId) {
            $this->error('订单ID不能为空');
        }
        
        if (empty($images)) {
            $this->error('请上传完成证明图片');
        }
        
        // 检查用户角色和订单
        // 检查用户角色（直接从user表读取role_type）
        $user = $this->auth->getUser();
        $roleType = $user ? $user->role_type : '';
        $dashouRoles = ['dashou', 'jindashou', 'guanshi', 'zuzhang', 'zongguan'];
        if (!in_array($roleType, $dashouRoles)) {
            $this->error('只有打手可以提交证明');
        }
        
        $order = Db::name('order')->where('id', $orderId)->find();
        if (!$order) {
            $this->error('订单不存在');
        }
        
        if ($order['dashou_id'] != $this->auth->id) {
            $this->error('无权操作此订单');
        }
        
        if ($order['status'] != 'daifuwu') {
            $this->error('订单状态不允许提交证明');
        }
        
        // 检查提交时间限制
        $systemConfig = Db::name('system_config')->where('id', 1)->find();
        $submitTimeLimit = intval($systemConfig['submit_time_limit'] ?? 0);
        if ($submitTimeLimit > 0 && !empty($order['jiedan_time'])) {
            $jiedanTime = intval($order['jiedan_time']);
            $minSubmitTime = $jiedanTime + ($submitTimeLimit * 60); // 转换为秒
            if (time() < $minSubmitTime) {
                $remainingSeconds = $minSubmitTime - time();
                $remainingMinutes = ceil($remainingSeconds / 60);
                $this->error('接单后需等待' . $submitTimeLimit . '分钟才能提交，还需等待' . $remainingMinutes . '分钟');
            }
        }
        
        // 更新订单
        Db::name('order')->where('id', $orderId)->update([
            'proof_images' => json_encode($images),
            'proof_content' => $content,
            'status' => 'daijiesuan',
            'wancheng_time' => time(),
            'updatetime' => time()
        ]);
        
        $this->success('提交成功');
    }

    /**
     * 确认订单完成
     */
    public function confirm()
    {
        $orderId = $this->request->post('order_id/d', 0);
        
        if (!$orderId) {
            $this->error('订单ID不能为空');
        }
        
        // 检查用户角色
        // 检查用户角色（直接从user表读取role_type）
        $user = $this->auth->getUser();
        $roleType = $user ? $user->role_type : '';
        if (!in_array($roleType, ['shangjia', 'jinshangjia', 'kefu'])) {
            $this->error('只有商家或客服可以确认订单');
        }
        
        // 确定实际操作的商家ID（如果是客服，使用其上级商家）
        $actualShangjiaId = $this->auth->id;
        
        if ($roleType === 'kefu') {
            $parentId = isset($user->parent_id) ? intval($user->parent_id) : 0;
            if ($parentId <= 0) {
                $this->error('客服未绑定商家，无法确认订单');
            }
            
            // 验证上级是否为商家
            $parent = Db::name('user')->where('id', $parentId)->find();
            if (!$parent || !in_array($parent['role_type'], ['shangjia', 'jinshangjia'])) {
                $this->error('上级不是商家，无法确认订单');
            }
            
            $actualShangjiaId = $parentId;
        }
        
        Db::startTrans();
        try {
            $order = Db::name('order')->where('id', $orderId)->lock(true)->find();
            
            if (!$order) {
                throw new Exception('订单不存在');
            }
            
            if ($order['shangjia_id'] != $actualShangjiaId) {
                throw new Exception('无权操作此订单');
            }
            
            if ($order['status'] != 'daijiesuan') {
                throw new Exception('订单状态不允许确认');
            }
            
            // 更新订单状态
            Db::name('order')->where('id', $orderId)->update([
                'status' => 'yiwancheng',
                'jiesuan_time' => time(),
                'updatetime' => time()
            ]);
            
            // 创建结算记录
            Db::name('settlement_log')->insert([
                'order_id' => $orderId,
                'shangjia_id' => $order['shangjia_id'],
                'dashou_id' => $order['dashou_id'],
                'amount' => $order['price'],
                'createtime' => time()
            ]);
            
            // 增加打手佣金
            Db::name('commission_log')->insert([
                'user_id' => $order['dashou_id'],
                'order_id' => $orderId,
                'amount' => $order['price'],
                'type' => 'income',
                'memo' => '订单结算',
                'createtime' => time()
            ]);
            
            // 更新打手余额
            Db::name('user')->where('id', $order['dashou_id'])->setInc('money', $order['price']);
            
            // 记录打手余额变动日志
            $dashouUser = Db::name('user')->where('id', $order['dashou_id'])->find();
            Db::name('user_money_log')->insert([
                'user_id' => $order['dashou_id'],
                'money' => $order['price'],
                'before' => floatval($dashouUser['money']) - $order['price'],
                'after' => floatval($dashouUser['money']),
                'memo' => '订单结算收入 - 订单号：' . $order['order_no'],
                'createtime' => time()
            ]);
            
            // 推广分佣逻辑（给点单用户的推广上级分佣）
            $clientInfo = Db::name('user')->where('id', $order['shangjia_id'])->find();
            if ($clientInfo && $clientInfo['promote_parent_id'] > 0) {
                // 获取系统配置的分佣比例
                $systemConfig = Db::name('system_config')->where('id', 1)->find();
                $commissionRate = floatval($systemConfig['order_commission_rate'] ?? 0);
                
                if ($commissionRate > 0) {
                    // 计算分佣金额（抢单价格 * 分佣比例 / 100）
                    $commissionAmount = round($order['price'] * $commissionRate / 100, 2);
                    
                    if ($commissionAmount > 0) {
                        // 检查推广上级是否存在
                        $promoteParent = Db::name('user')->where('id', $clientInfo['promote_parent_id'])->where('status', 'normal')->find();
                        
                        if ($promoteParent) {
                            // 增加推广上级余额
                            Db::name('user')->where('id', $promoteParent['id'])->setInc('money', $commissionAmount);
                            
                            // 记录推广上级佣金日志
                            Db::name('commission_log')->insert([
                                'user_id' => $promoteParent['id'],
                                'order_id' => $orderId,
                                'amount' => $commissionAmount,
                                'type' => 'income',
                                'memo' => '推广分佣（' . $commissionRate . '%）- 订单号：' . $order['order_no'],
                                'createtime' => time()
                            ]);
                            
                            // 记录推广上级余额变动
                            Db::name('user_money_log')->insert([
                                'user_id' => $promoteParent['id'],
                                'money' => $commissionAmount,
                                'before' => $promoteParent['money'],
                                'after' => $promoteParent['money'] + $commissionAmount,
                                'memo' => '推广分佣：来自' . ($clientInfo['nickname'] ?: '用户' . $clientInfo['id']) . '的订单',
                                'createtime' => time()
                            ]);
                        }
                    }
                }
            }
            
            // 更新排行榜统计
            $this->updateRanking($order['dashou_id'], 'dashou', $order['price']);
            
            Db::commit();
            $this->success('确认成功');
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    /**
     * 打赏
     */
    public function reward()
    {
        $orderId = $this->request->post('order_id/d', 0);
        $amount = $this->request->post('amount/f', 0);
        $memo = $this->request->post('memo', '');
        
        if (!$orderId || $amount <= 0) {
            $this->error('参数错误');
        }
        
        // 检查用户角色
        // 检查用户角色（直接从user表读取role_type）
        $user = $this->auth->getUser();
        $roleType = $user ? $user->role_type : '';
        if (!in_array($roleType, ['shangjia', 'jinshangjia', 'kefu'])) {
            $this->error('只有商家或客服可以打赏');
        }
        
        // 确定实际扣费的商家ID（如果是客服，使用其上级商家）
        $actualShangjiaId = $this->auth->id;
        
        if ($roleType === 'kefu') {
            $parentId = isset($user->parent_id) ? intval($user->parent_id) : 0;
            if ($parentId <= 0) {
                $this->error('客服未绑定商家，无法打赏');
            }
            
            // 验证上级是否为商家
            $parent = Db::name('user')->where('id', $parentId)->find();
            if (!$parent || !in_array($parent['role_type'], ['shangjia', 'jinshangjia'])) {
                $this->error('上级不是商家，无法打赏');
            }
            
            $actualShangjiaId = $parentId;
        }
        
        $order = Db::name('order')->where('id', $orderId)->find();
        if (!$order || $order['shangjia_id'] != $actualShangjiaId) {
            $this->error('订单不存在或无权操作');
        }
        
        if (!$order['dashou_id']) {
            $this->error('订单未接单');
        }
        
        Db::startTrans();
        try {
            // 检查商家余额（使用实际商家ID）
            $shangjia = Db::name('user')->where('id', $actualShangjiaId)->lock(true)->find();
            if (!$shangjia) {
                throw new Exception('商家信息不存在');
            }
            
            $shangjiaMoney = floatval($shangjia['money'] ?? 0);
            if ($shangjiaMoney < $amount) {
                throw new Exception('商家余额不足，当前余额：¥' . number_format($shangjiaMoney, 2) . '，需要：¥' . number_format($amount, 2));
            }
            
            // 扣除商家余额
            Db::name('user')->where('id', $actualShangjiaId)->setDec('money', $amount);
            
            // 增加打手余额
            Db::name('user')->where('id', $order['dashou_id'])->setInc('money', $amount);
            
            // 记录商家余额变动
            Db::name('user_money_log')->insert([
                'user_id' => $actualShangjiaId,
                'money' => -$amount,
                'before' => $shangjiaMoney,
                'after' => $shangjiaMoney - $amount,
                'memo' => ($roleType === 'kefu' ? '客服代打赏：' : '打赏：') . ($memo ?: '无') . ' - 订单号：' . $order['order_no'],
                'createtime' => time()
            ]);
            
            // 记录打手余额变动
            $dashou = Db::name('user')->where('id', $order['dashou_id'])->find();
            $dashouMoneyBefore = floatval($dashou['money'] ?? 0);
            Db::name('user_money_log')->insert([
                'user_id' => $order['dashou_id'],
                'money' => $amount,
                'before' => $dashouMoneyBefore,
                'after' => $dashouMoneyBefore + $amount,
                'memo' => '打赏收入：' . ($memo ?: '无') . ' - 订单号：' . $order['order_no'],
                'createtime' => time()
            ]);
            
            // 记录打赏日志
            Db::name('reward_log')->insert([
                'order_id' => $orderId,
                'from_user_id' => $actualShangjiaId,
                'kefu_id' => $roleType === 'kefu' ? $this->auth->id : 0, // 如果是客服执行的，记录客服ID
                'to_user_id' => $order['dashou_id'],
                'amount' => $amount,
                'memo' => $memo,
                'createtime' => time()
            ]);
            
            // 记录佣金日志（打手收入）
            Db::name('commission_log')->insert([
                'user_id' => $order['dashou_id'],
                'order_id' => $orderId,
                'amount' => $amount,
                'type' => 'income',
                'memo' => '打赏：' . ($memo ?: '无') . ' - 订单号：' . $order['order_no'],
                'createtime' => time()
            ]);
            
            // 记录佣金日志（商家支出）
            Db::name('commission_log')->insert([
                'user_id' => $actualShangjiaId,
                'order_id' => $orderId,
                'amount' => $amount,
                'type' => 'expense',
                'memo' => ($roleType === 'kefu' ? '客服代打赏：' : '打赏：') . ($memo ?: '无') . ' - 订单号：' . $order['order_no'],
                'createtime' => time()
            ]);
            
            Db::commit();
            $this->success('打赏成功');
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    /**
     * 罚款（创建待缴纳罚款记录，不直接扣钱）
     */
    public function fine()
    {
        $orderId = $this->request->post('order_id/d', 0);
        $amount = $this->request->post('amount/f', 0);
        $reason = $this->request->post('reason', '');
        $proofImages = $this->request->post('proof_images/a', []);
        
        if (!$orderId || $amount <= 0 || !$reason) {
            $this->error('参数错误');
        }
        
        if (empty($proofImages)) {
            $this->error('请上传罚款凭证');
        }
        
        // 检查用户角色（只有售后可以执行罚款）
        $user = $this->auth->getUser();
        $roleType = $user ? $user->role_type : '';
        if (!in_array($roleType, ['kefu', 'shouhou'])) {
            $this->error('只有售后人员可以执行罚款');
        }
        
        $order = Db::name('order')->where('id', $orderId)->find();
        if (!$order) {
            $this->error('订单不存在');
        }
        
        if (!$order['dashou_id']) {
            $this->error('订单未接单');
        }
        
        Db::startTrans();
        try {
            // 创建待缴纳的罚款记录（不直接扣钱），罚款受益人为订单商家
            Db::name('fine_log')->insert([
                'order_id' => $orderId,
                'from_user_id' => $this->auth->id, // 售后创建
                'to_user_id' => $order['dashou_id'], // 打手需缴纳
                'amount' => $amount,
                'reason' => $reason,
                'proof_images' => json_encode($proofImages),
                'status' => 'pending', // 待缴纳状态
                'beneficiary_id' => $order['shangjia_id'], // 受益人为商家
                'pay_time' => null,
                'createtime' => time(),
                'updatetime' => time()
            ]);
            
            Db::commit();
            $this->success('罚款已创建，等待打手缴纳');
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    /**
     * 打手缴纳罚款
     */
    public function payFine()
    {
        $fineId = $this->request->post('fine_id/d', 0);
        
        if (!$fineId) {
            $this->error('罚款ID不能为空');
        }
        
        // 检查用户角色
        $user = $this->auth->getUser();
        $roleType = $user ? $user->role_type : '';
        if (!in_array($roleType, ['dashou', 'jindashou', 'guanshi', 'zuzhang', 'zongguan'])) {
            $this->error('只有打手可以缴纳罚款');
        }
        
        Db::startTrans();
        try {
            // 查找罚款记录
            $fine = Db::name('fine_log')->where('id', $fineId)->lock(true)->find();
            if (!$fine) {
                throw new Exception('罚款记录不存在');
            }
            
            // 检查是否是该打手的罚款
            if ($fine['to_user_id'] != $this->auth->id) {
                throw new Exception('无权操作此罚款');
            }
            
            // 检查状态
            if ($fine['status'] == 'paid') {
                throw new Exception('该罚款已缴纳');
            }
            
            $amount = floatval($fine['amount']);
            
            // 获取受益人ID（商家）
            $beneficiaryId = isset($fine['beneficiary_id']) && $fine['beneficiary_id'] > 0 
                ? $fine['beneficiary_id'] 
                : $fine['from_user_id'];
            
            // 检查打手余额
            $dashou = Db::name('user')->where('id', $this->auth->id)->lock(true)->find();
            if ($dashou['money'] < $amount) {
                throw new Exception('余额不足，当前余额：¥' . number_format($dashou['money'], 2) . '，需缴纳：¥' . number_format($amount, 2));
            }
            
            // 获取订单信息
            $order = Db::name('order')->where('id', $fine['order_id'])->find();
            $orderNo = $order ? $order['order_no'] : '';
            
            // 扣除打手余额
            Db::name('user')->where('id', $this->auth->id)->setDec('money', $amount);
            
            // 增加受益人（商家）余额
            Db::name('user')->where('id', $beneficiaryId)->setInc('money', $amount);
            
            // 记录打手余额变动
            $dashouMoneyBefore = floatval($dashou['money']);
            Db::name('user_money_log')->insert([
                'user_id' => $this->auth->id,
                'money' => -$amount,
                'before' => $dashouMoneyBefore,
                'after' => $dashouMoneyBefore - $amount,
                'memo' => '缴纳罚款：' . ($fine['reason'] ?: '违规处罚') . ($orderNo ? ' - 订单号：' . $orderNo : ''),
                'createtime' => time()
            ]);
            
            // 记录商家余额变动
            $shangjia = Db::name('user')->where('id', $beneficiaryId)->find();
            $shangjiaMoneyBefore = floatval($shangjia['money'] ?? 0) - $amount;
            Db::name('user_money_log')->insert([
                'user_id' => $beneficiaryId,
                'money' => $amount,
                'before' => $shangjiaMoneyBefore,
                'after' => $shangjiaMoneyBefore + $amount,
                'memo' => '罚款收入：' . ($fine['reason'] ?: '违规处罚') . ($orderNo ? ' - 订单号：' . $orderNo : ''),
                'createtime' => time()
            ]);
            
            // 更新罚款状态
            Db::name('fine_log')->where('id', $fineId)->update([
                'status' => 'paid',
                'pay_time' => time(),
                'updatetime' => time()
            ]);
            
            // 记录扣款日志
            Db::name('deduction_log')->insert([
                'order_id' => $fine['order_id'],
                'user_id' => $this->auth->id,
                'amount' => $amount,
                'type' => 'fine',
                'reason' => $fine['reason'] ?: '违规处罚',
                'createtime' => time()
            ]);
            
            // 记录佣金日志（打手支出）
            Db::name('commission_log')->insert([
                'user_id' => $this->auth->id,
                'order_id' => $fine['order_id'],
                'amount' => $amount,
                'type' => 'expense',
                'memo' => '缴纳罚款：' . ($fine['reason'] ?: '违规处罚') . ($orderNo ? ' - 订单号：' . $orderNo : ''),
                'createtime' => time()
            ]);
            
            // 记录佣金日志（商家收入）
            Db::name('commission_log')->insert([
                'user_id' => $beneficiaryId,
                'order_id' => $fine['order_id'],
                'amount' => $amount,
                'type' => 'income',
                'memo' => '罚款收入：' . ($fine['reason'] ?: '违规处罚') . ($orderNo ? ' - 订单号：' . $orderNo : ''),
                'createtime' => time()
            ]);
            
            Db::commit();
            $this->success('罚款缴纳成功');
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    /**
     * 获取未缴纳罚款列表
     */
    public function getUnpaidFines()
    {
        $user = $this->auth->getUser();
        if (!$user) {
            $this->error('请先登录');
        }
        
        // 查询当前用户未缴纳的罚款
        $list = Db::name('fine_log')
            ->alias('f')
            ->join('order o', 'f.order_id = o.id', 'LEFT')
            ->join('user u', 'f.from_user_id = u.id', 'LEFT')
            ->where('f.to_user_id', $this->auth->id)
            ->where('f.status', 'pending')
            ->field('f.*, o.order_no, o.title as order_title, u.nickname as from_nickname')
            ->order('f.createtime DESC')
            ->select();
        
        foreach ($list as &$item) {
            $item['createtime_text'] = date('Y-m-d H:i', $item['createtime']);
            if (!empty($item['proof_images'])) {
                $item['proof_images'] = json_decode($item['proof_images'], true) ?: [];
            } else {
                $item['proof_images'] = [];
            }
        }
        unset($item);
        
        // 统计未缴纳罚款总额
        $totalAmount = Db::name('fine_log')
            ->where('to_user_id', $this->auth->id)
            ->where('status', 'pending')
            ->sum('amount');
        
        // 统计已缴纳罚款总额
        $paidAmount = Db::name('fine_log')
            ->where('to_user_id', $this->auth->id)
            ->where('status', 'paid')
            ->sum('amount');
        
        $this->success('获取成功', [
            'list' => $list,
            'total_count' => count($list),
            'total_amount' => floatval($totalAmount),
            'paid_amount' => floatval($paidAmount)
        ]);
    }

    /**
     * 获取罚款记录列表（包括已缴纳和未缴纳）
     */
    public function getFineList()
    {
        $user = $this->auth->getUser();
        if (!$user) {
            $this->error('请先登录');
        }
        
        $status = $this->request->get('status', ''); // pending/paid/空=全部
        $page = $this->request->get('page/d', 1);
        $limit = $this->request->get('limit/d', 20);
        
        // 构建基础条件
        $where = ['f.to_user_id' => $this->auth->id];
        if ($status) {
            $where['f.status'] = $status;
        }
        
        // 统计总数
        $total = Db::name('fine_log')->alias('f')->where($where)->count();
        
        // 查询列表
        $list = Db::name('fine_log')
            ->alias('f')
            ->join('order o', 'f.order_id = o.id', 'LEFT')
            ->join('user u', 'f.from_user_id = u.id', 'LEFT')
            ->where($where)
            ->field('f.*, o.order_no, o.title as order_title, u.nickname as from_nickname')
            ->order('f.createtime DESC')
            ->page($page, $limit)
            ->select();
        
        foreach ($list as &$item) {
            $item['createtime_text'] = date('Y-m-d H:i', $item['createtime']);
            $item['pay_time_text'] = $item['pay_time'] ? date('Y-m-d H:i', $item['pay_time']) : '';
            $item['status_text'] = $item['status'] == 'paid' ? '已缴纳' : '待缴纳';
            if (!empty($item['proof_images'])) {
                $item['proof_images'] = json_decode($item['proof_images'], true) ?: [];
            } else {
                $item['proof_images'] = [];
            }
        }
        unset($item);
        
        $this->success('获取成功', [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);
    }

    /**
     * 获取售后客服列表
     */
    public function getAfterSalesList()
    {
        $list = Db::name('user')
            ->where('role_type', 'shouhou')
            ->where('status', 'normal')
            ->field('id, nickname, avatar')
            ->order('id DESC')
            ->select();
        
        // 检查COS是否启用
        $useCos = is_cos_enabled();
        
        foreach ($list as &$item) {
            // 处理头像URL
            if (!empty($item['avatar'])) {
                if (!preg_match('/^https?:\/\//i', $item['avatar'])) {
                    $item['avatar'] = $useCos ? cdnurl($item['avatar'], true) : request()->domain() . $item['avatar'];
                }
            }
        }
        unset($item);
        
        $this->success('获取成功', ['list' => $list]);
    }

    /**
     * 取消订单
     * 商家：可以取消待接单的订单（没人接单时），款项退回
     * 打手：可以放弃已接的订单（待服务状态），订单恢复为待接单
     */
    public function cancel()
    {
        $orderId = $this->request->post('order_id/d', 0);
        
        if (!$orderId) {
            $this->error('订单ID不能为空');
        }
        
        $user = $this->auth->getUser();
        $roleType = $user ? $user->role_type : '';
        
        Db::startTrans();
        try {
            $order = Db::name('order')->where('id', $orderId)->lock(true)->find();
            
            if (!$order) {
                throw new Exception('订单不存在');
            }
            
            // 根据角色和订单状态判断是否可以取消
            if (in_array($roleType, ['shangjia', 'jinshangjia', 'kefu'])) {
                // 商家/客服取消订单
                $actualShangjiaId = $this->auth->id;
                
                if ($roleType === 'kefu') {
                    // 客服操作其上级商家的订单
                    $parentId = isset($user->parent_id) ? intval($user->parent_id) : 0;
                    if ($parentId <= 0) {
                        throw new Exception('客服未绑定商家，无法取消订单');
                    }
                    $actualShangjiaId = $parentId;
                }
                
                // 验证是否是自己的订单
                if ($order['shangjia_id'] != $actualShangjiaId) {
                    throw new Exception('无权操作此订单');
                }
                
                // 只能取消待接单的订单（没人接单时）
                if ($order['status'] != 'daijiedan') {
                    throw new Exception('只能取消待接单的订单');
                }
                
                // 如果已有打手接单，不能取消
                if ($order['dashou_id'] > 0) {
                    throw new Exception('订单已被接单，无法取消');
                }
                
                // 退款给商家
                $refundAmount = floatval($order['price']);
                Db::name('user')->where('id', $actualShangjiaId)->setInc('money', $refundAmount);
                
                // 记录退款日志
                $shangjia = Db::name('user')->where('id', $actualShangjiaId)->find();
                $beforeMoney = floatval($shangjia['money']) - $refundAmount;
                Db::name('user_money_log')->insert([
                    'user_id' => $actualShangjiaId,
                    'money' => $refundAmount,
                    'before' => $beforeMoney,
                    'after' => $shangjia['money'],
                    'memo' => '取消订单退款 - 订单号：' . $order['order_no'],
                    'createtime' => time()
                ]);
                
                // 记录佣金日志
                Db::name('commission_log')->insert([
                    'user_id' => $actualShangjiaId,
                    'order_id' => $orderId,
                    'amount' => $refundAmount,
                    'type' => 'income',
                    'memo' => '取消订单退款 - 订单号：' . $order['order_no'],
                    'createtime' => time()
                ]);
                
                // 更新订单状态为已撤销
                Db::name('order')->where('id', $orderId)->update([
                    'status' => 'yichexiao',
                    'updatetime' => time()
                ]);
                
                Db::commit();
                $this->success('取消成功，款项已退回', ['refund_amount' => $refundAmount]);
                
            } elseif (in_array($roleType, ['dashou', 'jindashou', 'guanshi', 'zuzhang', 'zongguan'])) {
                // 打手/管事/组长/总管放弃接单
                
                // 验证是否是自己接的订单
                if ($order['dashou_id'] != $this->auth->id) {
                    throw new Exception('无权操作此订单');
                }
                
                // 只能在待服务状态下放弃
                if ($order['status'] != 'daifuwu') {
                    throw new Exception('只能放弃待服务的订单');
                }
                
                // 恢复订单为待接单状态
                Db::name('order')->where('id', $orderId)->update([
                    'status' => 'daijiedan',
                    'dashou_id' => 0,
                    'jiedan_time' => 0,
                    'updatetime' => time()
                ]);
                
                Db::commit();
                $this->success('放弃成功，订单已恢复为待接单');
                
            } else {
                throw new Exception('无权取消订单');
            }
            
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    /**
     * 申请仲裁（售后处理）
     * 简化流程：直接变成售后处理中，不需要选择客服
     */
    public function applyArbitration()
    {
        $orderId = $this->request->post('order_id/d', 0);
        
        if (!$orderId) {
            $this->error('订单ID不能为空');
        }
        
        // 检查用户角色（商家或打手都可以申请仲裁）
        $user = $this->auth->getUser();
        $roleType = $user ? $user->role_type : '';
        
        $order = Db::name('order')->where('id', $orderId)->find();
        if (!$order) {
            $this->error('订单不存在');
        }
        
        // 验证订单是否属于当前用户
        $isOwner = false;
        
        // 商家可以操作自己发布的订单
        if (in_array($roleType, ['shangjia', 'jinshangjia']) && $order['shangjia_id'] == $this->auth->id) {
            $isOwner = true;
        }
        // 客服可以操作其上级商家的订单
        if ($roleType === 'kefu') {
            $parentId = isset($user->parent_id) ? intval($user->parent_id) : 0;
            if ($parentId > 0 && $order['shangjia_id'] == $parentId) {
                $isOwner = true;
            }
        }
        // 打手可以操作自己接的订单（包括所有打手角色）
        $dashouRoles = ['dashou', 'jindashou', 'guanshi', 'zuzhang', 'zongguan'];
        if (in_array($roleType, $dashouRoles) && $order['dashou_id'] == $this->auth->id) {
            $isOwner = true;
        }
        
        if (!$isOwner) {
            $this->error('无权操作此订单');
        }
        
        // 检查订单状态（只有进行中的订单可以申请仲裁）
        if (!in_array($order['status'], ['daifuwu', 'daijiesuan'])) {
            $this->error('订单状态不允许申请仲裁');
        }
        
        // 检查是否已经申请过仲裁
        if ($order['status'] === 'kefujieru') {
            $this->error('订单已在售后处理中');
        }
        
        // 更新订单状态为客服介入（售后处理中）
        Db::name('order')->where('id', $orderId)->update([
            'status' => 'kefujieru',
            'arbitration_user_id' => $this->auth->id, // 记录申请仲裁的用户
            'arbitration_time' => time(), // 记录申请时间
            'updatetime' => time()
        ]);
        
        $this->success('申请仲裁成功，请等待售后处理');
    }

    /**
     * 获取待处理仲裁订单列表（售后工作台）
     */
    public function getArbitrationList()
    {
        // 检查用户角色，只有售后可以查看
        $user = $this->auth->getUser();
        $roleType = $user ? $user->role_type : '';
        if ($roleType !== 'shouhou') {
            $this->error('只有售后人员可以查看仲裁订单');
        }
        
        $page = $this->request->get('page/d', 1);
        $limit = $this->request->get('limit/d', 20);
        $status = $this->request->get('status', 'kefujieru'); // 默认显示待处理
        $keyword = $this->request->get('keyword', ''); // 搜索关键词
        
        $where = [];
        if ($status === 'all') {
            // 显示所有仲裁过的订单
            $where['o.status'] = ['in', ['kefujieru', 'yiwancheng', 'yichexiao']];
        } else {
            $where['o.status'] = $status;
        }
        
        // 搜索订单号
        if (!empty($keyword)) {
            $keyword = trim($keyword);
            $where['o.order_no'] = ['like', '%' . $keyword . '%'];
        }
        
        // 查询订单列表
        $list = Db::name('order')
            ->alias('o')
            ->join('user m', 'o.shangjia_id = m.id', 'LEFT')
            ->join('user p', 'o.dashou_id = p.id', 'LEFT')
            ->join('game g', 'o.game_id = g.id', 'LEFT')
            ->where($where)
            ->field('o.*, m.nickname as shangjia_nickname, m.avatar as shangjia_avatar, 
                    p.nickname as dashou_nickname, p.avatar as dashou_avatar,
                    g.name as game_name, g.icon as game_icon')
            ->order('o.arbitration_time desc, o.updatetime desc')
            ->page($page, $limit)
            ->select();
        
        // 检查COS是否启用
        $useCos = is_cos_enabled();
        
        // 格式化数据
        foreach ($list as &$item) {
            $item['shangjia'] = [
                'id' => $item['shangjia_id'],
                'nickname' => $item['shangjia_nickname'] ?? '',
                'avatar' => $this->formatImageUrl($item['shangjia_avatar'], $useCos)
            ];
            $item['dashou'] = [
                'id' => $item['dashou_id'],
                'nickname' => $item['dashou_nickname'] ?? '',
                'avatar' => $this->formatImageUrl($item['dashou_avatar'], $useCos)
            ];
            $item['game_icon'] = $this->formatImageUrl($item['game_icon'], $useCos);
            
            // 格式化时间
            $item['createtime_text'] = date('Y-m-d H:i', $item['createtime']);
            $item['arbitration_time_text'] = $item['arbitration_time'] ? date('Y-m-d H:i', $item['arbitration_time']) : '';
            
            unset($item['shangjia_nickname'], $item['shangjia_avatar'], 
                  $item['dashou_nickname'], $item['dashou_avatar']);
        }
        unset($item);
        
        // 统计总数（使用不带别名的条件）
        $countWhere = [];
        if ($status === 'all') {
            $countWhere['status'] = ['in', ['kefujieru', 'yiwancheng', 'yichexiao']];
        } else {
            $countWhere['status'] = $status;
        }
        // 搜索条件
        if (!empty($keyword)) {
            $countWhere['order_no'] = ['like', '%' . $keyword . '%'];
        }
        $total = Db::name('order')->where($countWhere)->count();
        
        $this->success('获取成功', [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);
    }
    
    /**
     * 获取售后处理统计（已处理订单数）
     */
    public function getArbitrationStats()
    {
        // 检查用户角色，只有售后可以查看
        $user = $this->auth->getUser();
        $roleType = $user ? $user->role_type : '';
        if ($roleType !== 'shouhou') {
            $this->error('只有售后人员可以查看');
        }
        
        // 统计当前售后处理过的订单数
        $userId = $user->id;
        
        // 查询该售后处理过的订单数量（去重）
        $processedCount = Db::name('order_intervene_log')
            ->where('kefu_id', $userId)
            ->group('order_id')
            ->count();
        
        $this->success('获取成功', [
            'processed_count' => $processedCount
        ]);
    }
    
    /**
     * 格式化图片URL
     */
    private function formatImageUrl($url, $useCos = false)
    {
        if (empty($url)) return '';
        if (preg_match('/^https?:\/\//i', $url)) return $url;
        return $useCos ? cdnurl($url, true) : request()->domain() . $url;
    }

    /**
     * 更新排行榜统计
     */
    private function updateRanking($userId, $roleType, $amount)
    {
        $ranking = Db::name('ranking')->where('user_id', $userId)->where('role_type', $roleType)->find();
        
        if ($ranking) {
            Db::name('ranking')->where('id', $ranking['id'])->update([
                'total_orders' => $ranking['total_orders'] + 1,
                'completed_orders' => $ranking['completed_orders'] + 1,
                'total_commission' => $ranking['total_commission'] + $amount,
                'updatetime' => time()
            ]);
        } else {
            Db::name('ranking')->insert([
                'user_id' => $userId,
                'role_type' => $roleType,
                'total_orders' => 1,
                'completed_orders' => 1,
                'total_commission' => $amount,
                'rating' => 5.00,
                'updatetime' => time()
            ]);
        }
    }

    /**
     * 搜索订单（售后管理用）
     */
    public function search()
    {
        // 检查用户角色，只有售后可以搜索全平台订单
        $user = $this->auth->getUser();
        $roleType = $user ? $user->role_type : '';
        if ($roleType !== 'shouhou') {
            $this->error('只有售后人员可以搜索订单');
        }

        $keyword = $this->request->get('keyword', '');
        $page = $this->request->get('page/d', 1);
        $limit = $this->request->get('limit/d', 20);

        if (empty($keyword)) {
            $this->error('请输入搜索关键词');
        }

        $where = [];
        
        // 搜索订单号、用户ID或昵称
        // 尝试将关键词转换为数字（可能是订单ID或用户ID）
        $keywordNum = is_numeric($keyword) ? intval($keyword) : 0;
        
        if ($keywordNum > 0) {
            // 可能是订单ID或用户ID
            $where['_complex'] = [
                'id' => $keywordNum,
                'order_no' => ['like', '%' . $keyword . '%'],
                'shangjia_id' => $keywordNum,
                'dashou_id' => $keywordNum,
                '_logic' => 'or'
            ];
        } else {
            // 搜索订单号或用户昵称
            $where['order_no'] = ['like', '%' . $keyword . '%'];
        }

        // 关联查询商家和打手信息
        $list = Db::name('order')
            ->alias('o')
            ->join('user m', 'o.shangjia_id = m.id', 'LEFT')
            ->join('user p', 'o.dashou_id = p.id', 'LEFT')
            ->where($where)
            ->field('o.*, m.nickname as merchant_nickname, m.avatar as merchant_avatar, p.nickname as player_nickname, p.avatar as player_avatar')
            ->order('o.createtime desc')
            ->page($page, $limit)
            ->select();

        // 如果通过用户昵称搜索，需要额外查询
        if ($keywordNum <= 0) {
            // 查找匹配昵称的用户ID
            $userIds = Db::name('user')
                ->where('nickname', 'like', '%' . $keyword . '%')
                ->column('id');
            
            if (!empty($userIds)) {
                $additionalList = Db::name('order')
                    ->alias('o')
                    ->join('user m', 'o.shangjia_id = m.id', 'LEFT')
                    ->join('user p', 'o.dashou_id = p.id', 'LEFT')
                    ->where(function($query) use ($userIds) {
                        $query->where('o.shangjia_id', 'in', $userIds)
                              ->whereOr('o.dashou_id', 'in', $userIds);
                    })
                    ->where('o.order_no', 'not like', '%' . $keyword . '%')
                    ->field('o.*, m.nickname as merchant_nickname, m.avatar as merchant_avatar, p.nickname as player_nickname, p.avatar as player_avatar')
                    ->order('o.createtime desc')
                    ->page($page, $limit)
                    ->select();
                
                // 合并结果并去重
                $existingIds = array_column($list, 'id');
                foreach ($additionalList as $item) {
                    if (!in_array($item['id'], $existingIds)) {
                        $list[] = $item;
                        $existingIds[] = $item['id'];
                    }
                }
                
                // 重新排序
                usort($list, function($a, $b) {
                    return $b['createtime'] - $a['createtime'];
                });
                
                // 分页处理
                $list = array_slice($list, ($page - 1) * $limit, $limit);
            }
        }

        // 格式化数据
        foreach ($list as &$item) {
            $item['merchant'] = [
                'nickname' => $item['merchant_nickname'] ?? '',
                'avatar' => $item['merchant_avatar'] ?? ''
            ];
            $item['player'] = [
                'nickname' => $item['player_nickname'] ?? '',
                'avatar' => $item['player_avatar'] ?? ''
            ];
            unset($item['merchant_nickname'], $item['merchant_avatar'], $item['player_nickname'], $item['player_avatar']);
        }
        unset($item);

        $this->success('搜索成功', [
            'list' => $list,
            'page' => $page,
            'limit' => $limit
        ]);
    }

    /**
     * 介入处理订单（售后管理用）
     */
    public function intervene()
    {
        // 检查用户角色，只有售后可以介入处理
        $user = $this->auth->getUser();
        $roleType = $user ? $user->role_type : '';
        if ($roleType !== 'shouhou') {
            $this->error('只有售后人员可以介入处理订单');
        }

        $orderId = $this->request->post('order_id/d', 0);
        $type = $this->request->post('type', '');
        $amount = $this->request->post('amount/f', 0);
        $remark = $this->request->post('remark', '');

        if (!$orderId) {
            $this->error('订单ID不能为空');
        }

        if (empty($type)) {
            $this->error('处理类型不能为空');
        }

        if (empty($remark)) {
            $this->error('处理说明不能为空');
        }

        Db::startTrans();
        try {
            $order = Db::name('order')->where('id', $orderId)->lock(true)->find();
            if (!$order) {
                throw new Exception('订单不存在');
            }

            $shangjiaId = intval($order['shangjia_id']);
            $dashouId = intval($order['dashou_id']);
            $orderPrice = floatval($order['price']);

            // 检查打赏和罚款限制
            if ($type === 'tip_player') {
                // 检查是否已打赏过
                $hasTipped = Db::name('order_intervene_log')
                    ->where('order_id', $orderId)
                    ->where('type', 'tip_player')
                    ->find();
                if ($hasTipped) {
                    throw new Exception('该订单已打赏过，不能重复打赏');
                }
            }
            
            if ($type === 'fine_player') {
                // 检查是否已罚款过
                $hasFined = Db::name('order_intervene_log')
                    ->where('order_id', $orderId)
                    ->where('type', 'fine_player')
                    ->find();
                if ($hasFined) {
                    throw new Exception('该订单已罚款过，不能重复罚款');
                }
            }

            // 根据处理类型执行相应操作
            switch ($type) {
                case 'cancel_order':
                    // 取消/撤销订单：金额返还商家，订单状态标已撤销
                    // 增加商家余额（返还订单金额）
                    Db::name('user')->where('id', $shangjiaId)->setInc('money', $orderPrice);
                    // 记录资金日志
                    $merchantAfter = Db::name('user')->where('id', $shangjiaId)->value('money');
                    Db::name('user_money_log')->insert([
                        'user_id' => $shangjiaId,
                        'money' => $orderPrice,
                        'before' => $merchantAfter - $orderPrice,
                        'after' => $merchantAfter,
                        'memo' => '订单撤销退款 - 订单号：' . ($order['order_no'] ?? $orderId) . '，' . $remark,
                        'createtime' => time()
                    ]);
                    // 更新订单状态为已撤销
                    Db::name('order')->where('id', $orderId)->update([
                        'status' => 'yichexiao',
                        'updatetime' => time()
                    ]);
                    break;

                case 'complete_order':
                    // 已完成订单：订单金额结算给打手
                    if ($dashouId <= 0) {
                        throw new Exception('订单未接单，无法完成');
                    }
                    
                    // 更新订单状态为已完成
                    Db::name('order')->where('id', $orderId)->update([
                        'status' => 'yiwancheng',
                        'jiesuan_time' => time(),
                        'updatetime' => time()
                    ]);
                    
                    // 创建结算记录
                    Db::name('settlement_log')->insert([
                        'order_id' => $orderId,
                        'shangjia_id' => $shangjiaId,
                        'dashou_id' => $dashouId,
                        'amount' => $orderPrice,
                        'createtime' => time()
                    ]);
                    
                    // 增加打手佣金
                    Db::name('commission_log')->insert([
                        'user_id' => $dashouId,
                        'order_id' => $orderId,
                        'amount' => $orderPrice,
                        'type' => 'income',
                        'memo' => '售后介入结算：订单' . ($order['order_no'] ?? $orderId) . '，' . $remark,
                        'createtime' => time()
                    ]);
                    
                    // 更新打手余额
                    Db::name('user')->where('id', $dashouId)->setInc('money', $orderPrice);
                    
                    // 记录打手余额变动日志
                    $dashouAfter = Db::name('user')->where('id', $dashouId)->value('money');
                    Db::name('user_money_log')->insert([
                        'user_id' => $dashouId,
                        'money' => $orderPrice,
                        'before' => $dashouAfter - $orderPrice,
                        'after' => $dashouAfter,
                        'memo' => '订单结算收入 - 订单号：' . ($order['order_no'] ?? $orderId),
                        'createtime' => time()
                    ]);
                    
                    // 推广分佣逻辑
                    $dashouInfo = Db::name('user')->where('id', $dashouId)->find();
                    if ($dashouInfo && $dashouInfo['promote_parent_id'] > 0) {
                        $systemConfig = Db::name('system_config')->where('id', 1)->find();
                        $commissionRate = floatval($systemConfig['order_commission_rate'] ?? 0);
                        
                        if ($commissionRate > 0) {
                            $commissionAmount = round($orderPrice * $commissionRate / 100, 2);
                            
                            if ($commissionAmount > 0) {
                                $promoteParent = Db::name('user')->where('id', $dashouInfo['promote_parent_id'])->where('status', 'normal')->find();
                                
                                if ($promoteParent) {
                                    Db::name('user')->where('id', $promoteParent['id'])->setInc('money', $commissionAmount);
                                    
                                    Db::name('commission_log')->insert([
                                        'user_id' => $promoteParent['id'],
                                        'order_id' => $orderId,
                                        'amount' => $commissionAmount,
                                        'type' => 'income',
                                        'memo' => '推广分佣（' . $commissionRate . '%）- 订单号：' . ($order['order_no'] ?? $orderId),
                                        'createtime' => time()
                                    ]);
                                    
                                    Db::name('user_money_log')->insert([
                                        'user_id' => $promoteParent['id'],
                                        'money' => $commissionAmount,
                                        'before' => $promoteParent['money'],
                                        'after' => $promoteParent['money'] + $commissionAmount,
                                        'memo' => '推广分佣：来自' . ($dashouInfo['nickname'] ?: '用户' . $dashouId) . '的订单',
                                        'createtime' => time()
                                    ]);
                                }
                            }
                        }
                    }
                    
                    // 更新排行榜统计
                    $this->updateRanking($dashouId, 'dashou', $orderPrice);
                    break;

                case 'return_hall':
                    // 发回大厅：无金额流转，订单重新进入大厅派单
                    // 清除打手信息，重置订单状态为待接单
                    Db::name('order')->where('id', $orderId)->update([
                        'status' => 'daijiedan',
                        'dashou_id' => 0,
                        'jiedan_time' => null,
                        'updatetime' => time()
                    ]);
                    break;

                case 'tip_player':
                    // 打赏：从商家余额扣除打赏金额，结算给接单打手
                    if ($amount <= 0) {
                        throw new Exception('打赏金额无效');
                    }
                    if ($dashouId <= 0) {
                        throw new Exception('订单未接单，无法打赏');
                    }
                    $merchantMoney = Db::name('user')->where('id', $shangjiaId)->value('money');
                    if ($merchantMoney < $amount) {
                        throw new Exception('商家余额不足');
                    }
                    
                    // 扣除商家余额
                    Db::name('user')->where('id', $shangjiaId)->setDec('money', $amount);
                    Db::name('user_money_log')->insert([
                        'user_id' => $shangjiaId,
                        'money' => -$amount,
                        'before' => $merchantMoney,
                        'after' => $merchantMoney - $amount,
                        'memo' => '打赏打手 - 订单号：' . ($order['order_no'] ?? $orderId) . '，' . $remark,
                        'createtime' => time()
                    ]);
                    
                    // 增加打手余额
                    Db::name('user')->where('id', $dashouId)->setInc('money', $amount);
                    $dashouAfter = Db::name('user')->where('id', $dashouId)->value('money');
                    Db::name('user_money_log')->insert([
                        'user_id' => $dashouId,
                        'money' => $amount,
                        'before' => $dashouAfter - $amount,
                        'after' => $dashouAfter,
                        'memo' => '收到打赏 - 订单号：' . ($order['order_no'] ?? $orderId) . '，' . $remark,
                        'createtime' => time()
                    ]);
                    
                    // 记录打手佣金日志
                    Db::name('commission_log')->insert([
                        'user_id' => $dashouId,
                        'order_id' => $orderId,
                        'amount' => $amount,
                        'type' => 'income',
                        'memo' => '收到打赏 - ' . $remark,
                        'createtime' => time()
                    ]);
                    break;

                case 'fine_player':
                    // 罚款：售后生成罚单，由打手缴纳罚款，该罚款金额最终归属商家
                    if ($amount <= 0) {
                        throw new Exception('罚款金额无效');
                    }
                    if ($dashouId <= 0) {
                        throw new Exception('订单未接单，无法罚款');
                    }
                    
                    // 创建罚款记录
                    Db::name('fine_log')->insert([
                        'order_id' => $orderId,
                        'from_user_id' => $this->auth->id, // 售后创建
                        'to_user_id' => $dashouId, // 打手需缴纳
                        'amount' => $amount,
                        'reason' => $remark,
                        'status' => 'pending', // 待缴纳
                        'proof_images' => '', // 售后可以不上传凭证
                        'beneficiary_id' => $shangjiaId, // 受益人为商家
                        'createtime' => time(),
                        'updatetime' => time()
                    ]);
                    break;

                default:
                    throw new Exception('未知的处理类型');
            }

            // 更新订单状态（cancel_order、complete_order、return_hall已在case中处理）
            $noUpdateTypes = ['cancel_order', 'complete_order', 'return_hall'];
            if (!in_array($type, $noUpdateTypes)) {
                // tip_player和fine_player不改变订单状态，保持当前状态
            }

            // 记录介入日志（如果表不存在，先创建）
            $logTableExists = Db::query("SHOW TABLES LIKE 'fa_order_intervene_log'");
            if (empty($logTableExists)) {
                // 表不存在，创建表
                Db::execute("CREATE TABLE IF NOT EXISTS `fa_order_intervene_log` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `order_id` int(11) NOT NULL COMMENT '订单ID',
                    `kefu_id` int(11) NOT NULL COMMENT '售后ID',
                    `type` varchar(50) NOT NULL COMMENT '处理类型',
                    `amount` decimal(10,2) DEFAULT '0.00' COMMENT '操作金额',
                    `remark` text COMMENT '处理说明',
                    `createtime` int(11) DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `order_id` (`order_id`),
                    KEY `kefu_id` (`kefu_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='订单介入处理日志表'");
            }
            
            Db::name('order_intervene_log')->insert([
                'order_id' => $orderId,
                'kefu_id' => $this->auth->id,
                'type' => $type,
                'amount' => $amount,
                'remark' => $remark,
                'createtime' => time()
            ]);

            Db::commit();
            $this->success('处理成功');
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
    }
    
    /**
     * 获取商家财务统计（待结算订单数和金额）
     */
    public function merchantStats()
    {
        // 检查用户角色
        $user = $this->auth->getUser();
        $roleType = $user ? $user->role_type : '';
        
        // 只有商家、金牌商家、客服可以查看
        if (!in_array($roleType, ['shangjia', 'jinshangjia', 'kefu'])) {
            $this->success('获取成功', [
                'pending_count' => 0,
                'pending_amount' => '0.00'
            ]);
            return;
        }
        
        // 确定商家ID
        $shangjiaId = $this->auth->id;
        if ($roleType === 'kefu') {
            // 客服使用上级商家ID
            $parentId = isset($user->parent_id) ? intval($user->parent_id) : 0;
            if ($parentId > 0) {
                $shangjiaId = $parentId;
            } else {
                $this->success('获取成功', [
                    'pending_count' => 0,
                    'pending_amount' => '0.00'
                ]);
                return;
            }
        }
        
        // 查询待结算订单统计
        $stats = Db::name('order')
            ->where('shangjia_id', $shangjiaId)
            ->where('status', 'daijiesuan')
            ->field('COUNT(*) as count, COALESCE(SUM(price), 0) as amount')
            ->find();
        
        $this->success('获取成功', [
            'pending_count' => intval($stats['count'] ?? 0),
            'pending_amount' => number_format(floatval($stats['amount'] ?? 0), 2, '.', '')
        ]);
    }
    
    /**
     * 获取可抢订单列表（首页抢单用）
     */
    public function grabList()
    {
        $gameId = $this->request->get('game_id/d', 0);
        $serverId = $this->request->get('server_id/d', 0);
        $page = $this->request->get('page/d', 1);
        $limit = $this->request->get('limit/d', 10);
        
        // 检查用户角色
        $user = $this->auth->getUser();
        $roleType = $user ? $user->role_type : '';
        
        // 只有这些角色可以抢单
        $allowedRoles = ['dashou', 'jindashou', 'guanshi', 'zuzhang', 'zongguan'];
        if (!in_array($roleType, $allowedRoles)) {
            $this->success('获取成功', [
                'list' => [],
                'total' => 0
            ]);
            return;
        }
        
        $where = [
            'status' => 'daijiedan'
        ];
        
        // 游戏筛选
        if ($gameId) {
            $where['game_id'] = $gameId;
        }
        
        // 区服筛选
        if ($serverId) {
            $where['server_id'] = $serverId;
        }
        
        // 构建查询：未指定打手或指定给自己的订单
        $userId = $this->auth->id;
        $query = Db::name('order')
            ->where($where)
            ->where(function($q) use ($userId) {
                $q->where('dashou_id', 0)
                  ->whereOr('dashou_id', $userId);
            });
        
        $total = $query->count();
        
        $list = Db::name('order')
            ->where($where)
            ->where(function($q) use ($userId) {
                $q->where('dashou_id', 0)
                  ->whereOr('dashou_id', $userId);
            })
            ->order('createtime DESC')
            ->page($page, $limit)
            ->select();
        
        // 检查COS是否启用
        $useCos = is_cos_enabled();
        
        // 关联查询游戏和区服信息，以及商家信息
        foreach ($list as &$item) {
            $game = Db::name('game')->where('id', $item['game_id'])->find();
            $server = Db::name('game_server')->where('id', $item['server_id'])->find();
            $item['game_name'] = $game ? $game['name'] : '';
            $item['server_name'] = $server ? $server['server_name'] : '';
            
            // 查询商家信息
            if (!empty($item['shangjia_id'])) {
                $merchant = Db::name('user')->where('id', $item['shangjia_id'])->field('id, nickname, avatar, role_type')->find();
                if ($merchant) {
                    if (!empty($merchant['avatar']) && !preg_match('/^https?:\/\//i', $merchant['avatar'])) {
                        $merchant['avatar'] = $useCos ? cdnurl($merchant['avatar'], true) : request()->domain() . $merchant['avatar'];
                    }
                    
                    // 查询商家标签
                    $tagIds = Db::name('user_tag')->where('user_id', $merchant['id'])->column('tag_id');
                    $tags = [];
                    if (!empty($tagIds)) {
                        $tagList = Db::name('tag')->where('id', 'in', $tagIds)->where('status', 'normal')->field('id, name, color')->select();
                        foreach ($tagList as $tag) {
                            $tags[] = [
                                'id' => $tag['id'],
                                'name' => $tag['name'],
                                'color' => $tag['color']
                            ];
                        }
                    }
                    
                    $item['merchant'] = [
                        'id' => $merchant['id'],
                        'nickname' => $merchant['nickname'] ?? '',
                        'avatar' => $merchant['avatar'] ?? '',
                        'role_type' => $merchant['role_type'] ?? '',
                        'tags' => $tags
                    ];
                } else {
                    $item['merchant'] = null;
                }
            } else {
                $item['merchant'] = null;
            }
            
            // 处理游戏图标URL
            if (!empty($game['icon'])) {
                if ($useCos) {
                    $item['game_icon'] = cdnurl($game['icon'], true);
                } else {
                    $icon = $game['icon'];
                    if (strpos($icon, '/') !== 0) {
                        $icon = '/' . $icon;
                    }
                    $item['game_icon'] = request()->domain() . $icon;
                }
            } else {
                $item['game_icon'] = '';
            }
        }
        
        $this->success('获取成功', [
            'list' => $list,
            'total' => $total
        ]);
    }
    
    /**
     * 提交举证（仲裁订单）
     */
    public function submitEvidence()
    {
        $orderId = $this->request->post('order_id/d', 0);
        $content = $this->request->post('content', '');
        $images = $this->request->post('images', '');
        
        if (!$orderId) {
            $this->error('缺少订单ID');
        }
        
        // 查询订单
        $order = Db::name('order')->where('id', $orderId)->find();
        if (!$order) {
            $this->error('订单不存在');
        }
        
        // 检查订单状态，只有客服介入状态可以提交举证
        if ($order['status'] !== 'kefujieru') {
            $this->error('只有仲裁中的订单可以提交举证');
        }
        
        // 检查用户是否有权提交举证（商家或打手）
        $userId = $this->auth->id;
        $user = $this->auth->getUser();
        $roleType = $user ? $user->role_type : '';
        
        // 检查是否是订单的商家或打手
        $isShangjia = ($order['shangjia_id'] == $userId);
        $isDashou = ($order['dashou_id'] == $userId);
        
        // 客服也可以代表商家提交
        if ($roleType === 'kefu') {
            $parentId = isset($user->parent_id) ? intval($user->parent_id) : 0;
            if ($parentId > 0 && $order['shangjia_id'] == $parentId) {
                $isShangjia = true;
            }
        }
        
        if (!$isShangjia && !$isDashou) {
            $this->error('只有订单的商家或打手可以提交举证');
        }
        
        // 检查内容或图片是否为空
        if (empty($content) && empty($images)) {
            $this->error('请填写举证说明或上传图片');
        }
        
        // 处理图片
        $imagesJson = '';
        if (!empty($images)) {
            if (is_array($images)) {
                $imagesJson = json_encode($images);
            } else {
                $imagesJson = $images;
            }
        }
        
        // 插入举证记录
        $evidenceId = Db::name('order_evidence')->insertGetId([
            'order_id' => $orderId,
            'user_id' => $userId,
            'user_role' => $roleType,
            'content' => $content,
            'images' => $imagesJson,
            'createtime' => time()
        ]);
        
        if ($evidenceId) {
            $this->success('举证提交成功');
        } else {
            $this->error('举证提交失败');
        }
    }
    
    /**
     * 获取订单举证列表
     */
    public function getEvidenceList()
    {
        $orderId = $this->request->get('order_id/d', 0);
        
        if (!$orderId) {
            $this->error('缺少订单ID');
        }
        
        // 查询订单
        $order = Db::name('order')->where('id', $orderId)->find();
        if (!$order) {
            $this->error('订单不存在');
        }
        
        // 检查权限：商家、打手、售后都可以查看
        $userId = $this->auth->id;
        $user = $this->auth->getUser();
        $roleType = $user ? $user->role_type : '';
        
        $canView = false;
        
        // 售后可以查看所有仲裁订单的举证
        if ($roleType === 'shouhou') {
            $canView = true;
        }
        // 订单的商家可以查看
        if ($order['shangjia_id'] == $userId) {
            $canView = true;
        }
        // 订单的打手可以查看
        if ($order['dashou_id'] == $userId) {
            $canView = true;
        }
        // 客服可以查看上级商家的订单
        if ($roleType === 'kefu') {
            $parentId = isset($user->parent_id) ? intval($user->parent_id) : 0;
            if ($parentId > 0 && $order['shangjia_id'] == $parentId) {
                $canView = true;
            }
        }
        
        if (!$canView) {
            // 无权查看时返回空列表，而不是报错
            $this->success('获取成功', ['list' => []]);
            return;
        }
        
        // 查询举证列表
        $list = Db::name('order_evidence')
            ->where('order_id', $orderId)
            ->order('createtime ASC')
            ->select();
        
        // 检查COS是否启用
        $useCos = is_cos_enabled();
        
        // 处理数据
        foreach ($list as &$item) {
            // 查询提交者信息
            $submitter = Db::name('user')->where('id', $item['user_id'])->field('id, nickname, avatar, role_type')->find();
            if ($submitter) {
                if (!empty($submitter['avatar']) && !preg_match('/^https?:\/\//i', $submitter['avatar'])) {
                    $submitter['avatar'] = $useCos ? cdnurl($submitter['avatar'], true) : request()->domain() . $submitter['avatar'];
                }
                $item['submitter'] = $submitter;
            } else {
                $item['submitter'] = null;
            }
            
            // 处理图片
            if (!empty($item['images'])) {
                // 先解码HTML实体（数据库可能存储了&quot;等转义字符）
                $imagesStr = html_entity_decode($item['images'], ENT_QUOTES, 'UTF-8');
                $images = json_decode($imagesStr, true);
                if (is_array($images)) {
                    foreach ($images as &$img) {
                        if (!preg_match('/^https?:\/\//i', $img)) {
                            $img = $useCos ? cdnurl($img, true) : request()->domain() . $img;
                        }
                    }
                    $item['images'] = $images;
                } else {
                    $item['images'] = [];
                }
            } else {
                $item['images'] = [];
            }
            
            // 格式化时间
            $item['createtime_text'] = date('Y-m-d H:i:s', $item['createtime']);
        }
        unset($item);
        
        $this->success('获取成功', ['list' => $list]);
    }
}

