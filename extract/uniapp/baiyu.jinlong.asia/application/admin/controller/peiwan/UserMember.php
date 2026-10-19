<?php

namespace app\admin\controller\peiwan;

use app\common\controller\Backend;

/**
 * 用户会员记录管理
 *
 * @icon fa fa-circle-o
 */
class UserMember extends Backend
{

    /**
     * UserMember模型对象
     * @var \app\admin\model\peiwan\UserMember
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\peiwan\UserMember;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("packageTypeList", $this->model->getPackageTypeList());
        
        // 获取游戏列表
        $gameList = \think\Db::name('game')->where('status', 'normal')->field('id,name')->select();
        $this->view->assign("gameList", $gameList);
        
        // 获取会员套餐列表
        $packageList = \think\Db::name('member_package')->where('status', 'normal')->field('id,game_id,trial_days,valid_days')->select();
        $this->view->assign("packageList", $packageList);
    }

    /**
     * 查看
     */
    public function index()
    {
        $this->relationSearch = true;
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                    ->with(['user'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */
    
    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                
                // 处理天数：如果有天数字段，根据开始时间和天数计算结束时间
                if (isset($params['days']) && $params['days'] > 0 && isset($params['start_time'])) {
                    $startTime = is_numeric($params['start_time']) ? $params['start_time'] : strtotime($params['start_time']);
                    if ($startTime) {
                        $params['end_time'] = $startTime + ($params['days'] * 86400);
                    }
                    unset($params['days']); // 移除天数字段，因为表中没有这个字段
                }
                
                $result = false;
                \think\Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                        $this->model->validateFailException(true)->validate($validate);
                    }
                    $result = $this->model->allowField(true)->save($params);
                    \think\Db::commit();
                } catch (\think\exception\PDOException $e) {
                    \think\Db::rollback();
                    $this->error($e->getMessage());
                } catch (\think\Exception $e) {
                    \think\Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }
    
    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                
                // 处理天数：如果有天数字段，根据开始时间和天数计算结束时间
                if (isset($params['days']) && $params['days'] > 0 && isset($params['start_time'])) {
                    $startTime = is_numeric($params['start_time']) ? $params['start_time'] : strtotime($params['start_time']);
                    if ($startTime) {
                        $params['end_time'] = $startTime + ($params['days'] * 86400);
                    }
                    unset($params['days']); // 移除天数字段，因为表中没有这个字段
                }
                
                $result = false;
                \think\Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    $result = $row->allowField(true)->save($params);
                    \think\Db::commit();
                } catch (\think\exception\PDOException $e) {
                    \think\Db::rollback();
                    $this->error($e->getMessage());
                } catch (\think\Exception $e) {
                    \think\Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        
        // 计算天数（用于显示）
        $days = 0;
        if ($row->start_time && $row->end_time) {
            $days = ceil(($row->end_time - $row->start_time) / 86400);
        }
        $row->days = $days > 0 ? $days : '';
        
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }


}
