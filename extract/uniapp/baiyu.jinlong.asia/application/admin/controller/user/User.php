<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;
use app\common\library\Auth;

/**
 * 会员管理
 *
 * @icon fa fa-user
 */
class User extends Backend
{

    protected $relationSearch = true;
    protected $searchFields = 'id,username,nickname';

    /**
     * @var \app\admin\model\User
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\User;
    }

    /**
     * 查看
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $k => $v) {
                $v->avatar = $v->avatar ? cdnurl($v->avatar, true) : letter_avatar($v->nickname);
                $v->hidden(['password', 'salt']);
                
                // 查询上级推荐人（检查是否有parent_id字段）
                $v->parent_nickname = '无';
                if (isset($v->parent_id) && $v->parent_id > 0) {
                    $parent = $this->model->where('id', $v->parent_id)->find();
                    if ($parent) {
                        $v->parent_nickname = $parent->nickname ?: $parent->username ?: '无';
                    }
                }
                
                // 查询推广上级
                $v->promote_parent_nickname = '无';
                if (isset($v->promote_parent_id) && $v->promote_parent_id > 0) {
                    $promoteParent = $this->model->where('id', $v->promote_parent_id)->find();
                    if ($promoteParent) {
                        $v->promote_parent_nickname = $promoteParent->nickname ?: $promoteParent->username ?: '无';
                    }
                }
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $this->token();
        }
        return parent::add();
    }

    /**
     * 获取会员套餐列表（用于selectpage，包含体验和正式两种类型）
     */
    public function getMemberPackageList()
    {
        if ($this->request->isAjax()) {
            // 直接从请求中获取参数，避免类型转换错误
            $requestData = $this->request->get();
            
            // 安全获取参数
            $keyField = isset($requestData['keyField']) && is_scalar($requestData['keyField']) ? (string)$requestData['keyField'] : 'id';
            $searchField = isset($requestData['searchField']) && is_scalar($requestData['searchField']) ? (string)$requestData['searchField'] : 'name';
            $keyword = isset($requestData['keyword']) && is_scalar($requestData['keyword']) ? (string)$requestData['keyword'] : '';
            $pageNumber = isset($requestData['pageNumber']) ? intval($requestData['pageNumber']) : 1;
            $pageSize = isset($requestData['pageSize']) ? intval($requestData['pageSize']) : 10;
            
            // 处理搜索关键词（selectpage可能传递q_word数组）
            if (isset($requestData['q_word']) && is_array($requestData['q_word'])) {
                $keyword = implode(' ', array_filter($requestData['q_word']));
            } elseif (empty($keyword) && isset($requestData['q_word']) && is_scalar($requestData['q_word'])) {
                $keyword = (string)$requestData['q_word'];
            }
            
            $packages = \think\Db::name('member_package')
                ->alias('mp')
                ->join('game g', 'mp.game_id = g.id', 'LEFT')
                ->where('mp.status', 'normal')
                ->field('mp.id,mp.game_id,mp.trial_days,mp.valid_days,g.name as game_name')
                ->select();
            
            $list = [];
            foreach ($packages as $package) {
                $gameName = $package['game_name'] ?: '未知游戏';
                // 添加体验会员选项
                $list[] = [
                    'id' => $package['id'] . '_trial',
                    'package_id' => $package['id'],
                    'game_id' => $package['game_id'],
                    'game_name' => $gameName,
                    'package_type' => 'trial',
                    'days' => $package['trial_days'],
                    'name' => $gameName . ' - 套餐' . $package['id'] . ' - 体验会员'
                ];
                // 添加正式会员选项
                $list[] = [
                    'id' => $package['id'] . '_formal',
                    'package_id' => $package['id'],
                    'game_id' => $package['game_id'],
                    'game_name' => $gameName,
                    'package_type' => 'formal',
                    'days' => $package['valid_days'],
                    'name' => $gameName . ' - 套餐' . $package['id'] . ' - 正式会员'
                ];
            }
            
            // 搜索过滤
            if ($keyword) {
                $list = array_filter($list, function($item) use ($keyword, $searchField) {
                    $value = $item[$searchField] ?? '';
                    return stripos($value, $keyword) !== false || 
                           stripos($item['game_name'], $keyword) !== false;
                });
            }
            
            $total = count($list);
            $offset = ($pageNumber - 1) * $pageSize;
            $list = array_slice($list, $offset, $pageSize);
            
            return json([
                'total' => $total,
                'list' => array_values($list)
            ]);
        }
        return json(['total' => 0, 'list' => []]);
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post("row/a");
            if (empty($params)) {
                $this->error(__('Parameter %s can not be empty', ''));
            }
            
            // 检查角色权限
            if (isset($params['role_type'])) {
                $allowedRoles = $this->getAllowedRoles();
                if (!in_array($params['role_type'], $allowedRoles)) {
                    $this->error('无权限设置该角色类型');
                }
            }
            
            // 处理标签
            $tagIds = [];
            if (isset($params['tag_ids']) && !empty($params['tag_ids'])) {
                if (is_string($params['tag_ids'])) {
                    $tagIds = explode(',', $params['tag_ids']);
                } elseif (is_array($params['tag_ids'])) {
                    $tagIds = $params['tag_ids'];
                }
                $tagIds = array_filter(array_map('intval', $tagIds));
            }
            unset($params['tag_ids']);
            
            $row = $this->model->get($ids);
            if (!$row) {
                $this->error(__('No Results were found'));
            }
            
            // 保存用户信息
            $result = $row->allowField(['nickname', 'mobile', 'avatar', 'money', 'quota', 'status', 'role_type', 'parent_id', 'promote_parent_id'])->save($params);
            if ($result === false) {
                $this->error($row->getError());
            }
            
            // 保存用户标签
            \think\Db::name('user_tag')->where('user_id', $ids)->delete();
            if (!empty($tagIds)) {
                $insertData = [];
                foreach ($tagIds as $tagId) {
                    $insertData[] = [
                        'user_id' => $ids,
                        'tag_id' => $tagId,
                        'createtime' => time()
                    ];
                }
                \think\Db::name('user_tag')->insertAll($insertData);
            }
            
            $this->success();
        }
        
        $row = $this->model->get($ids);
        $this->modelValidate = true;
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        
        // 获取用户的标签ID
        $tagIds = \think\Db::name('user_tag')->where('user_id', $ids)->column('tag_id');
        $this->view->assign('tag_ids', implode(',', $tagIds));
        
        // 获取当前管理员允许管理的角色列表
        $allowedRoles = $this->getAllowedRoles();
        $this->view->assign('allowed_roles', $allowedRoles);
        
        return parent::edit($ids);
    }
    
    /**
     * 获取当前管理员允许管理的角色类型
     */
    protected function getAllowedRoles()
    {
        // 超级管理员拥有所有权限
        if ($this->auth->isSuperAdmin()) {
            return ['weironzheng', 'user', 'dashou', 'jindashou', 'guanshi', 'zuzhang', 'zongguan', 'jinshangjia', 'shangjia', 'kefu', 'shouhou'];
        }
        
        // 角色权限映射
        $rolePermissions = [
            'user/user/role_weironzheng' => 'weironzheng',
            'user/user/role_user' => 'user',
            'user/user/role_dashou' => 'dashou',
            'user/user/role_jindashou' => 'jindashou',
            'user/user/role_guanshi' => 'guanshi',
            'user/user/role_zuzhang' => 'zuzhang',
            'user/user/role_zongguan' => 'zongguan',
            'user/user/role_shangjia' => 'shangjia',
            'user/user/role_jinshangjia' => 'jinshangjia',
            'user/user/role_kefu' => 'kefu',
            'user/user/role_shouhou' => 'shouhou',
        ];
        
        $allowedRoles = [];
        foreach ($rolePermissions as $permission => $role) {
            if ($this->auth->check($permission)) {
                $allowedRoles[] = $role;
            }
        }
        
        return $allowedRoles;
    }

    /**
     * 会员设置
     */
    public function member($ids = null)
    {
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post("row/a");
            $ids = $ids ?: $params['user_id'];
            
            if (empty($ids)) {
                $this->error('用户ID不能为空');
            }
            
            // 处理会员套餐（从复选框获取）
            $memberPackageIds = $this->request->post('member_package_ids/a', []);
            $memberPackageIds = array_filter($memberPackageIds);
            
            $row = $this->model->get($ids);
            if (!$row) {
                $this->error(__('No Results were found'));
            }
            
            \think\Db::startTrans();
            try {
                // 保存会员套餐：删除旧的，添加新的
                if (!empty($memberPackageIds)) {
                    // 解析选中的套餐ID（格式：package_id_type，如：1_trial, 2_formal）
                    $selectedPackages = [];
                    foreach ($memberPackageIds as $id) {
                        if (strpos($id, '_') !== false) {
                            list($packageId, $packageType) = explode('_', $id, 2);
                            $selectedPackages[] = [
                                'package_id' => intval($packageId),
                                'package_type' => $packageType
                            ];
                        }
                    }
                    
                    // 获取所有选中的套餐信息
                    $packageIds = array_unique(array_column($selectedPackages, 'package_id'));
                    $packages = \think\Db::name('member_package')->where('id', 'in', $packageIds)->select();
                    $packageMap = [];
                    foreach ($packages as $pkg) {
                        $packageMap[$pkg['id']] = $pkg;
                    }
                    
                    // 按游戏分组，确保同一游戏只有一种类型
                    $gamePackages = [];
                    foreach ($selectedPackages as $selected) {
                        $packageId = $selected['package_id'];
                        if (!isset($packageMap[$packageId])) {
                            continue;
                        }
                        $gameId = $packageMap[$packageId]['game_id'];
                        // 如果该游戏已有记录，保留最后选择的类型
                        $gamePackages[$gameId] = $selected;
                    }
                    
                    // 删除用户所有现有的会员记录
                    \think\Db::name('user_member')->where('user_id', $ids)->delete();
                    
                    // 添加新选中的会员记录
                    if (!empty($gamePackages)) {
                        $insertData = [];
                        $now = time();
                        foreach ($gamePackages as $gameId => $selected) {
                            $packageId = $selected['package_id'];
                            $packageType = $selected['package_type'];
                            $package = $packageMap[$packageId];
                            
                            // 根据类型确定天数
                            $days = $packageType === 'trial' 
                                ? intval($package['trial_days'] ?? 7) 
                                : intval($package['valid_days'] ?? 30);
                            
                            $insertData[] = [
                                'user_id' => $ids,
                                'game_id' => intval($gameId),
                                'package_id' => intval($packageId),
                                'package_type' => $packageType,
                                'start_time' => $now,
                                'end_time' => $now + ($days * 86400),
                                'status' => 'normal',
                                'createtime' => $now,
                                'updatetime' => $now
                            ];
                        }
                        if (!empty($insertData)) {
                            \think\Db::name('user_member')->insertAll($insertData);
                        }
                    }
                } else {
                    // 如果清空了所有选择，删除所有会员记录
                    \think\Db::name('user_member')->where('user_id', $ids)->delete();
                }
                
                \think\Db::commit();
                // 确保返回 JSON 格式
                if ($this->request->isAjax()) {
                    return json(['code' => 1, 'msg' => '会员设置成功', 'data' => '', 'url' => '', 'wait' => 3]);
                }
                $this->success('会员设置成功');
            } catch (\Exception $e) {
                \think\Db::rollback();
                $this->error($e->getMessage());
            }
        }
        
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        
        // 获取所有会员套餐
        $packages = \think\Db::name('member_package')
            ->alias('mp')
            ->join('game g', 'mp.game_id = g.id', 'LEFT')
            ->where('mp.status', 'normal')
            ->field('mp.id,mp.game_id,mp.trial_days,mp.valid_days,g.name as game_name')
            ->order('g.id ASC, mp.id ASC')
            ->select();
        
        // 按游戏分组
        $gamePackages = [];
        foreach ($packages as $package) {
            $gameId = $package['game_id'];
            if (!isset($gamePackages[$gameId])) {
                $gamePackages[$gameId] = [
                    'game_id' => $gameId,
                    'game_name' => $package['game_name'] ?: '未知游戏',
                    'packages' => []
                ];
            }
            $gamePackages[$gameId]['packages'][] = $package;
        }
        
        // 获取用户当前已选择的会员套餐
        $userMembers = \think\Db::name('user_member')->where('user_id', $ids)->field('package_id,package_type')->select();
        $selectedPackages = [];
        foreach ($userMembers as $member) {
            $selectedPackages[$member['package_id'] . '_' . $member['package_type']] = true;
        }
        
        // 获取用户现有的会员详情（包含到期时间）
        $currentMembers = \think\Db::name('user_member')
            ->alias('um')
            ->join('member_package mp', 'um.package_id = mp.id', 'LEFT')
            ->join('game g', 'um.game_id = g.id', 'LEFT')
            ->where('um.user_id', $ids)
            ->field('um.id,um.game_id,um.package_id,um.package_type,um.start_time,um.end_time,um.status,g.name as game_name,mp.trial_days,mp.valid_days')
            ->select();
        
        // 处理会员信息，添加状态标记
        $now = time();
        foreach ($currentMembers as &$member) {
            $member['type_text'] = $member['package_type'] === 'trial' ? '体验会员' : '正式会员';
            $member['start_time_text'] = $member['start_time'] ? date('Y-m-d H:i', $member['start_time']) : '-';
            $member['end_time_text'] = $member['end_time'] ? date('Y-m-d H:i', $member['end_time']) : '-';
            $member['is_expired'] = $member['end_time'] && $member['end_time'] < $now;
            $member['status_text'] = $member['is_expired'] ? '已过期' : ($member['status'] === 'normal' ? '正常' : '已禁用');
        }
        unset($member);
        
        // 为每个套餐添加选中状态
        foreach ($gamePackages as &$game) {
            foreach ($game['packages'] as &$package) {
                $package['selected_trial'] = isset($selectedPackages[$package['id'] . '_trial']);
                $package['selected_formal'] = isset($selectedPackages[$package['id'] . '_formal']);
            }
        }
        unset($game, $package);
        
        $this->view->assign('gamePackages', $gamePackages);
        $this->view->assign('currentMembers', $currentMembers);
        $this->view->assign('row', $row);
        
        return $this->view->fetch();
    }

    /**
     * 删除
     */
    public function del($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        $row = $this->model->get($ids);
        $this->modelValidate = true;
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        Auth::instance()->delete($row['id']);
        $this->success();
    }

}
