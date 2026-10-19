<?php

namespace app\admin\controller\peiwan;

use app\common\controller\Backend;
use think\Db;

/**
 * 解锁商户配置管理
 *
 * @icon fa fa-unlock
 */
class UnlockMerchantConfig extends Backend
{
    /**
     * UnlockMerchantConfig模型对象
     * @var \app\admin\model\peiwan\UnlockMerchantConfig
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\peiwan\UnlockMerchantConfig;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("requireWithdrawUnlockList", $this->model->getRequireWithdrawUnlockList());
        $this->view->assign("requireRechargeUnlockList", $this->model->getRequireRechargeUnlockList());
        $this->view->assign("requireChannelUnlockList", $this->model->getRequireChannelUnlockList());
        $this->view->assign("requireChatUnlockList", $this->model->getRequireChatUnlockList());
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
                'merchant_id' => '',
                'api_key' => '',
                'cert_path' => '',
                'cert_key_path' => '',
                'unlock_price' => 0.00,
                'unlock_times' => 10,
                'require_withdraw_unlock' => 1,
                'require_recharge_unlock' => 0,
                'require_channel_unlock' => 0,
                'require_chat_unlock' => 0,
                'chat_unlock_price' => 0.00,
                'chat_unlock_times' => 10,
                'status' => 'normal',
                'createtime' => time(),
                'updatetime' => time()
            ]);
        }
        // 直接跳转到编辑页
        $this->redirect('peiwan/unlock_merchant_config/edit', ['ids' => 1]);
    }

    /**
     * 重写add方法，禁止添加
     */
    public function add()
    {
        $this->error('解锁商户配置只能编辑，不能添加');
    }

    /**
     * 重写del方法，禁止删除
     */
    public function del($ids = "")
    {
        $this->error('解锁商户配置不能删除');
    }

    /**
     * 重写multi方法，禁止批量操作
     */
    public function multi($ids = "")
    {
        $this->error('解锁商户配置不支持批量操作');
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
                'merchant_id' => '',
                'api_key' => '',
                'cert_path' => '',
                'cert_key_path' => '',
                'unlock_price' => 0.00,
                'unlock_times' => 10,
                'require_withdraw_unlock' => 1,
                'require_recharge_unlock' => 0,
                'require_channel_unlock' => 0,
                'require_chat_unlock' => 0,
                'chat_unlock_price' => 0.00,
                'chat_unlock_times' => 10,
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
                
                // 处理证书文件上传
                $certDir = ROOT_PATH . 'public' . DS . 'uploads' . DS . 'cert';
                if (!is_dir($certDir)) {
                    mkdir($certDir, 0755, true);
                }
                
                if (isset($_FILES['cert_file']) && $_FILES['cert_file']['error'] == 0) {
                    $certFile = request()->file('cert_file');
                    if ($certFile) {
                        $info = $certFile->validate(['size' => 1024 * 1024, 'ext' => 'pem,crt'])->move($certDir);
                        if ($info) {
                            $params['cert_path'] = '/uploads/cert/' . $info->getSaveName();
                        } else {
                            $this->error('证书文件上传失败：' . $certFile->getError());
                        }
                    }
                }
                
                if (isset($_FILES['cert_key_file']) && $_FILES['cert_key_file']['error'] == 0) {
                    $certKeyFile = request()->file('cert_key_file');
                    if ($certKeyFile) {
                        $info = $certKeyFile->validate(['size' => 1024 * 1024, 'ext' => 'pem,key'])->move($certDir);
                        if ($info) {
                            $params['cert_key_path'] = '/uploads/cert/' . $info->getSaveName();
                        } else {
                            $this->error('证书密钥文件上传失败：' . $certKeyFile->getError());
                        }
                    }
                }
                
                // 如果未上传新文件，保留原有路径
                if (!isset($params['cert_path'])) {
                    $params['cert_path'] = $row->cert_path;
                }
                if (!isset($params['cert_key_path'])) {
                    $params['cert_key_path'] = $row->cert_key_path;
                }
                
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
