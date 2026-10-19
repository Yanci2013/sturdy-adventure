<?php

namespace app\admin\controller\peiwan;

use app\common\controller\Backend;

/**
 * 消息管理
 *
 * @icon fa fa-circle-o
 */
class Message extends Backend
{

    /**
     * Message模型对象
     * @var \app\admin\model\peiwan\Message
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\peiwan\Message;
        $this->view->assign("msgTypeList", $this->model->getMsgTypeList());
        $this->view->assign("isReadList", $this->model->getIsReadList());
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
                    ->with(['fromuser', 'touser', 'order'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

}
