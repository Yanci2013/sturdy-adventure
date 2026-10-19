<?php

namespace app\admin\controller\peiwan;

use app\common\controller\Backend;

/**
 * 扣款记录管理
 *
 * @icon fa fa-circle-o
 */
class DeductionLog extends Backend
{

    /**
     * DeductionLog模型对象
     * @var \app\admin\model\peiwan\DeductionLog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\peiwan\DeductionLog;
        $this->view->assign("typeList", $this->model->getTypeList());
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
                    ->with(['user', 'order'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

}
