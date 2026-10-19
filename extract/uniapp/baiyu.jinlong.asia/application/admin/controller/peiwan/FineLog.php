<?php

namespace app\admin\controller\peiwan;

use app\common\controller\Backend;
use think\Db;
use think\Exception;

/**
 * 罚款记录管理
 *
 * @icon fa fa-circle-o
 */
class FineLog extends Backend
{

    /**
     * FineLog模型对象
     * @var \app\admin\model\peiwan\FineLog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\peiwan\FineLog;
        $this->view->assign("statusList", $this->model->getStatusList());
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
                    ->with(['fromUser', 'toUser', 'order'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 撤销罚款
     */
    public function revoke($ids = null)
    {
        if (!$ids) {
            $this->error('请选择要撤销的记录');
        }
        
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error('记录不存在');
        }
        
        Db::startTrans();
        try {
            // 如果已经缴纳，需要退回金额
            if ($row['status'] === 'paid') {
                $toUserId = $row['to_user_id']; // 被罚款人
                $amount = floatval($row['amount']);
                
                // 退回金额给被罚款人
                $user = Db::name('user')->where('id', $toUserId)->lock(true)->find();
                if ($user) {
                    Db::name('user')->where('id', $toUserId)->setInc('money', $amount);
                    
                    // 记录余额变动
                    Db::name('user_money_log')->insert([
                        'user_id' => $toUserId,
                        'money' => $amount,
                        'before' => $user['money'],
                        'after' => $user['money'] + $amount,
                        'memo' => '罚款撤销退回',
                        'createtime' => time()
                    ]);
                }
                
                // 如果有受益人，也需要扣回
                $beneficiaryId = $row['beneficiary_id'] ?? 0;
                if ($beneficiaryId > 0) {
                    $beneficiary = Db::name('user')->where('id', $beneficiaryId)->lock(true)->find();
                    if ($beneficiary && $beneficiary['money'] >= $amount) {
                        Db::name('user')->where('id', $beneficiaryId)->setDec('money', $amount);
                        
                        Db::name('user_money_log')->insert([
                            'user_id' => $beneficiaryId,
                            'money' => -$amount,
                            'before' => $beneficiary['money'],
                            'after' => $beneficiary['money'] - $amount,
                            'memo' => '罚款撤销扣回',
                            'createtime' => time()
                        ]);
                    }
                }
            }
            
            // 删除罚款记录
            $row->delete();
            
            Db::commit();
            $this->success('撤销成功');
        } catch (Exception $e) {
            Db::rollback();
            $this->error('撤销失败：' . $e->getMessage());
        }
    }

}
