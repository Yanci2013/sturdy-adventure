<?php

namespace app\admin\controller\peiwan;

use app\common\controller\Backend;
use think\Db;

/**
 * 同款小程序管理
 *
 * @icon fa fa-mobile
 */
class MiniappIntro extends Backend
{

    /**
     * MiniappIntro模型对象
     * @var \app\admin\model\peiwan\MiniappIntro
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\peiwan\MiniappIntro;
        $this->view->assign("statusList", $this->model->getStatusList());
    }

    /**
     * 重写index方法，只显示一条记录，直接跳转到编辑页
     */
    public function index()
    {
        // 查找或创建唯一记录
        $row = $this->model->find(1);
        if (!$row) {
            // 如果没有记录，创建一条
            $row = $this->model->create([
                'id' => 1,
                'title' => '同款小程序',
                'icon' => '',
                'content' => '',
                'sort' => 0,
                'status' => 'normal',
                'createtime' => time(),
                'updatetime' => time()
            ]);
        }
        // 直接跳转到编辑页
        $this->redirect('peiwan/miniapp_intro/edit', ['ids' => 1]);
    }

    /**
     * 重写add方法，禁止添加
     */
    public function add()
    {
        $this->error('同款小程序只能编辑，不能添加');
    }

    /**
     * 重写del方法，禁止删除
     */
    public function del($ids = "")
    {
        $this->error('同款小程序不能删除');
    }

    /**
     * 重写multi方法，禁止批量操作
     */
    public function multi($ids = "")
    {
        $this->error('同款小程序不支持批量操作');
    }

    /**
     * 重写edit方法，确保只能编辑ID为1的记录
     */
    public function edit($ids = null)
    {
        // 强制使用ID=1
        $ids = 1;
        $row = $this->model->get($ids);
        if (!$row) {
            // 如果没有记录，创建一条
            $row = $this->model->create([
                'id' => 1,
                'title' => '同款小程序',
                'icon' => '',
                'content' => '',
                'sort' => 0,
                'status' => 'normal',
                'createtime' => time(),
                'updatetime' => time()
            ]);
            $row = $this->model->get(1);
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
                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    $result = $row->allowField(true)->save($params);
                    Db::commit();
                } catch (\think\exception\PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (\think\Exception $e) {
                    Db::rollback();
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
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }
}
