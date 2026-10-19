<?php

namespace app\admin\controller\peiwan;

use app\common\controller\Backend;
use think\Db;

/**
 * 协议配置
 *
 * @icon fa fa-file-text-o
 */
class AgreementConfig extends Backend
{
    /**
     * AgreementConfig模型对象
     * @var \app\admin\model\peiwan\AgreementConfig
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\peiwan\AgreementConfig;
    }

    /**
     * 重写index方法，只显示一条记录，直接跳转到编辑页
     */
    public function index()
    {
        $row = $this->model->find(1);
        if (!$row) {
            $this->model->create([
                'id' => 1,
                'user_service_agreement' => '',
                'privacy_policy' => '',
                'member_purchase_agreement' => '',
                'withdraw_agreement' => '',
                'recharge_agreement' => '',
                'createtime' => time(),
                'updatetime' => time()
            ]);
        }
        $this->redirect('peiwan/agreement_config/edit', ['ids' => 1]);
    }

    public function add()
    {
        $this->error('协议配置只能编辑，不能添加');
    }

    public function del($ids = "")
    {
        $this->error('协议配置不能删除');
    }

    public function multi($ids = "")
    {
        $this->error('协议配置不支持批量操作');
    }

    /**
     * 重写edit方法，确保只能编辑ID为1的记录
     */
    public function edit($ids = null)
    {
        $ids = 1;
        $row = $this->model->get($ids);
        if (!$row) {
            $row = $this->model->create([
                'id' => 1,
                'user_service_agreement' => '',
                'privacy_policy' => '',
                'member_purchase_agreement' => '',
                'withdraw_agreement' => '',
                'recharge_agreement' => '',
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
                $params['updatetime'] = time();

                $result = false;
                Db::startTrans();
                try {
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


