<?php

namespace app\admin\controller\peiwan;

use app\common\controller\Backend;

/**
 * 点单端轮播图管理
 *
 * @icon fa fa-mobile
 */
class ClientBanner extends Backend
{

    /**
     * ClientBanner模型对象
     * @var \app\admin\model\peiwan\ClientBanner
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\peiwan\ClientBanner;
        $this->view->assign("linkTypeList", $this->model->getLinkTypeList());
        $this->view->assign("statusList", $this->model->getStatusList());
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、bindSelect/changeStatus/import/export四个扩展方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/bindSelect.php 和 application/common/library/traits/Backend.php 中对应的方法复制到当前控制器,然后进行修改
     */

}
