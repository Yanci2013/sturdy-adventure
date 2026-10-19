<?php

namespace app\admin\controller\peiwan;

use app\common\controller\Backend;
use think\Db;

/**
 * 系统设置管理
 *
 * @icon fa fa-circle-o
 */
class SystemConfig extends Backend
{

    /**
     * SystemConfig模型对象
     * @var \app\admin\model\peiwan\SystemConfig
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\peiwan\SystemConfig;
        $this->view->assign("isAuditList", $this->model->getIsAuditList());
        $this->view->assign("isCustomOrderList", $this->model->getIsCustomOrderList());
        $this->view->assign("isAuthEnabledList", $this->model->getIsAuthEnabledList());
        $this->view->assign("isAudioEnabledList", $this->model->getIsAudioEnabledList());
        $this->view->assign("isWithdrawAuditList", $this->model->getIsWithdrawAuditList());
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
                'wxappid' => '',
                'wxappsecret' => '',
                'client_wxappid' => '',
                'client_wxappsecret' => '',
                'poster_image' => '',
                'client_poster_image' => '',
                'is_audit' => 0,
                'is_custom_order' => 0,
                'order_quota' => 0.00,
                'is_auth_enabled' => 0,
                'is_audio_enabled' => 0,
                'auto_settle_hours' => 0,
                'order_commission_rate' => 0.00,
                'is_withdraw_audit' => 0,
                'submit_time_limit' => 0,
                'createtime' => time(),
                'updatetime' => time()
            ]);
        }
        // 直接跳转到编辑页
        $this->redirect('peiwan/system_config/edit', ['ids' => 1]);
    }

    /**
     * 重写add方法，禁止添加
     */
    public function add()
    {
        $this->error('系统设置只能编辑，不能添加');
    }

    /**
     * 重写del方法，禁止删除
     */
    public function del($ids = "")
    {
        $this->error('系统设置不能删除');
    }

    /**
     * 重写multi方法，禁止批量操作
     */
    public function multi($ids = "")
    {
        $this->error('系统设置不支持批量操作');
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
                'wxappid' => '',
                'wxappsecret' => '',
                'client_wxappid' => '',
                'client_wxappsecret' => '',
                'poster_image' => '',
                'client_poster_image' => '',
                'login_page_text' => '',
                'is_audit' => 0,
                'is_custom_order' => 0,
                'order_quota' => 0.00,
                'is_auth_enabled' => 0,
                'is_audio_enabled' => 0,
                'auto_settle_hours' => 0,
                'order_commission_rate' => 0.00,
                'is_withdraw_audit' => 0,
                'submit_time_limit' => 0,
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
            $cosParams = $this->request->post("cos/a");
            $epayParams = $this->request->post("epay/a");
            $rechargeParams = $this->request->post("recharge/a");
            
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
                    
                    // 保存COS配置
                    if ($cosParams) {
                        $this->savePluginConfig('cos', $cosParams);
                        $this->syncCosConfig($cosParams);
                    }
                    
                    // 保存支付配置
                    if ($epayParams) {
                        $this->savePluginConfig('epay', $epayParams);
                        $this->syncEpayConfig($epayParams);
                    }
                    
                    // 保存充值配置
                    if ($rechargeParams) {
                        $this->savePluginConfig('recharge', $rechargeParams);
                        $this->syncRechargeConfig($rechargeParams);
                    }
                    
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
        
        // 读取插件配置
        $cosConfig = $this->getPluginConfig('cos');
        $epayConfig = $this->getPluginConfig('epay');
        $rechargeConfig = $this->getPluginConfig('recharge');
        
        // 如果数据库没有配置，从插件配置文件读取
        if (empty($cosConfig)) {
            $cosConfig = $this->loadCosConfigFromFile();
        }
        if (empty($epayConfig)) {
            $epayConfig = $this->loadEpayConfigFromFile();
        }
        if (empty($rechargeConfig)) {
            $rechargeConfig = $this->loadRechargeConfigFromFile();
        }
        
        $this->view->assign("row", $row);
        $this->view->assign("cos", $cosConfig);
        $this->view->assign("epay", $epayConfig);
        $this->view->assign("recharge", $rechargeConfig);
        return $this->view->fetch();
    }

    /**
     * 获取插件配置
     */
    protected function getPluginConfig($group)
    {
        $list = Db::name('plugin_config')->where('group', $group)->select();
        $config = [];
        foreach ($list as $item) {
            $config[$item['name']] = $item['value'];
        }
        return $config;
    }

    /**
     * 保存插件配置
     */
    protected function savePluginConfig($group, $params)
    {
        foreach ($params as $name => $value) {
            $exists = Db::name('plugin_config')->where(['group' => $group, 'name' => $name])->find();
            if ($exists) {
                Db::name('plugin_config')->where('id', $exists['id'])->update([
                    'value' => $value,
                    'updatetime' => time()
                ]);
            } else {
                Db::name('plugin_config')->insert([
                    'group' => $group,
                    'name' => $name,
                    'value' => $value,
                    'createtime' => time(),
                    'updatetime' => time()
                ]);
            }
        }
    }

    /**
     * 从COS插件配置文件读取配置
     */
    protected function loadCosConfigFromFile()
    {
        $configFile = ROOT_PATH . 'addons/cos/config.php';
        if (!file_exists($configFile)) {
            return [];
        }
        $config = include $configFile;
        $result = [];
        foreach ($config as $item) {
            $result[$item['name']] = $item['value'];
        }
        return $result;
    }

    /**
     * 从Epay插件配置文件读取配置
     */
    protected function loadEpayConfigFromFile()
    {
        $configFile = ROOT_PATH . 'addons/epay/config.php';
        if (!file_exists($configFile)) {
            return [];
        }
        $config = include $configFile;
        $result = [];
        foreach ($config as $item) {
            if ($item['name'] == 'wechat' && $item['type'] == 'array') {
                $result['wechat_miniapp_id'] = isset($item['value']['miniapp_id']) ? $item['value']['miniapp_id'] : '';
                $result['wechat_mch_id'] = isset($item['value']['mch_id']) ? $item['value']['mch_id'] : '';
                $result['wechat_key'] = isset($item['value']['key']) ? $item['value']['key'] : '';
                $result['wechat_cert_client'] = isset($item['value']['cert_client']) ? $item['value']['cert_client'] : '';
                $result['wechat_cert_key'] = isset($item['value']['cert_key']) ? $item['value']['cert_key'] : '';
            }
            if ($item['name'] == 'alipay' && $item['type'] == 'array') {
                $result['alipay_app_id'] = isset($item['value']['app_id']) ? $item['value']['app_id'] : '';
                $result['alipay_private_key'] = isset($item['value']['private_key']) ? $item['value']['private_key'] : '';
                $result['alipay_public_key'] = isset($item['value']['ali_public_key']) ? $item['value']['ali_public_key'] : '';
            }
        }
        return $result;
    }

    /**
     * 从Recharge插件配置文件读取配置
     */
    protected function loadRechargeConfigFromFile()
    {
        $configFile = ROOT_PATH . 'addons/recharge/config.php';
        if (!file_exists($configFile)) {
            return [];
        }
        $config = include $configFile;
        $result = [];
        $nameMap = [
            'rechargetips' => 'tips',
            'moneylist' => 'money_list',
            'defaultmoney' => 'default_money',
            'minmoney' => 'min_money',
            'iscustommoney' => 'is_custom_money',
            'defaultpaytype' => 'default_pay_type'
        ];
        foreach ($config as $item) {
            $name = isset($nameMap[$item['name']]) ? $nameMap[$item['name']] : $item['name'];
            if ($item['name'] == 'moneylist') {
                // 金额列表转换为逗号分隔
                $moneyList = [];
                foreach ($item['value'] as $money) {
                    $moneyList[] = $money;
                }
                $result[$name] = implode(',', $moneyList);
            } else {
                $result[$name] = $item['value'];
            }
        }
        return $result;
    }

    /**
     * 同步COS配置到插件配置文件
     */
    protected function syncCosConfig($params)
    {
        $configFile = ROOT_PATH . 'addons/cos/config.php';
        if (!file_exists($configFile)) {
            return;
        }
        
        $config = include $configFile;
        foreach ($config as &$item) {
            if (isset($params[$item['name']])) {
                $item['value'] = $params[$item['name']];
            }
        }
        
        $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        file_put_contents($configFile, $content);
    }

    /**
     * 同步支付配置到插件配置文件
     */
    protected function syncEpayConfig($params)
    {
        $configFile = ROOT_PATH . 'addons/epay/config.php';
        if (!file_exists($configFile)) {
            return;
        }
        
        $config = include $configFile;
        foreach ($config as &$item) {
            if ($item['name'] == 'wechat' && $item['type'] == 'array') {
                // 微信支付配置
                if (isset($params['wechat_miniapp_id'])) $item['value']['miniapp_id'] = $params['wechat_miniapp_id'];
                if (isset($params['wechat_mch_id'])) $item['value']['mch_id'] = $params['wechat_mch_id'];
                if (isset($params['wechat_key'])) $item['value']['key'] = $params['wechat_key'];
                if (isset($params['wechat_cert_client'])) $item['value']['cert_client'] = $params['wechat_cert_client'];
                if (isset($params['wechat_cert_key'])) $item['value']['cert_key'] = $params['wechat_cert_key'];
            }
            if ($item['name'] == 'alipay' && $item['type'] == 'array') {
                // 支付宝配置
                if (isset($params['alipay_app_id'])) $item['value']['app_id'] = $params['alipay_app_id'];
                if (isset($params['alipay_private_key'])) $item['value']['private_key'] = $params['alipay_private_key'];
                if (isset($params['alipay_public_key'])) $item['value']['ali_public_key'] = $params['alipay_public_key'];
            }
        }
        
        $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        file_put_contents($configFile, $content);
    }

    /**
     * 同步充值配置到插件配置文件
     */
    protected function syncRechargeConfig($params)
    {
        $configFile = ROOT_PATH . 'addons/recharge/config.php';
        if (!file_exists($configFile)) {
            return;
        }
        
        $config = include $configFile;
        $nameMap = [
            'tips' => 'rechargetips',
            'money_list' => 'moneylist',
            'default_money' => 'defaultmoney',
            'min_money' => 'minmoney',
            'is_custom_money' => 'iscustommoney',
            'default_pay_type' => 'defaultpaytype'
        ];
        
        foreach ($config as &$item) {
            foreach ($nameMap as $paramName => $configName) {
                if ($item['name'] == $configName && isset($params[$paramName])) {
                    if ($configName == 'moneylist') {
                        // 金额列表需要转换格式
                        $moneyArr = explode(',', $params[$paramName]);
                        $moneyList = [];
                        foreach ($moneyArr as $money) {
                            $money = trim($money);
                            if ($money !== '') {
                                $moneyList['￥' . $money] = $money;
                            }
                        }
                        $item['value'] = $moneyList;
                    } else {
                        $item['value'] = $params[$paramName];
                    }
                }
            }
        }
        
        $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        file_put_contents($configFile, $content);
    }
}
