<?php

namespace app\admin\controller\peiwan;

use app\common\controller\Backend;
use think\Db;

/**
 * 提现审核管理
 *
 * @icon fa fa-money
 */
class WithdrawAudit extends Backend
{

    /**
     * WithdrawLog模型对象
     * @var \app\admin\model\peiwan\WithdrawLog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\peiwan\WithdrawLog;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("withdrawTypeList", $this->model->getWithdrawTypeList());
        $this->view->assign("accountTypeList", $this->model->getAccountTypeList());
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、bindall方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/bindall/default/add.html、bindall/default/edit.html复制到对应的目录中,否则会报错
     */

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
            
            // 只显示审核类型的提现记录
            $list = $this->model
                ->with(['user'])
                ->where($where)
                ->where('withdraw_type', 'audit')
                ->order($sort, $order)
                ->paginate($limit);

            foreach ($list as $row) {
                // 处理用户信息
                if ($row->user) {
                    $row->user_nickname = $row->user->nickname;
                    $row->user_avatar = $row->user->avatar;
                } else {
                    $row->user_nickname = '-';
                    $row->user_avatar = '';
                }
                
                // 处理图片URL
                if (!empty($row->qrcode_image) && !preg_match('/^https?:\/\//i', $row->qrcode_image)) {
                    $row->qrcode_image = is_cos_enabled() ? cdnurl($row->qrcode_image, true) : request()->domain() . $row->qrcode_image;
                }
            }

            $result = array("total" => $list->total(), "rows" => $list->items());
            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 审核通过
     */
    public function approve($ids = null)
    {
        if (!$ids) {
            $this->error('请选择要审核的记录');
        }
        
        $row = $this->model->where('id', $ids)->find();
        if (!$row) {
            $this->error('记录不存在');
        }
        
        if ($row['status'] != 'audit_pending') {
            $this->error('该记录状态不是待审核');
        }
        
        Db::startTrans();
        try {
            // 更新提现记录状态
            Db::name('withdraw_log')->where('id', $ids)->update([
                'status' => 'success',
                'audit_time' => time(),
                'audit_admin_id' => $this->auth->id,
                'remark' => '审核通过',
                'updatetime' => time()
            ]);
            
            // 处理上级分成（如果有）
            $shareAmount = floatval($row['parent_share_amount'] ?? 0);
            $parentId = intval($row['parent_share_user_id'] ?? 0);
            $shareRate = floatval($row['parent_share_rate'] ?? 0);
            
            if ($shareAmount > 0 && $parentId > 0) {
                // 给上级增加余额
                $parent = Db::name('user')->where('id', $parentId)->find();
                if ($parent) {
                    $parentMoney = floatval($parent['money'] ?? 0);
                    Db::name('user')->where('id', $parentId)->setInc('money', $shareAmount);
                    
                    // 获取提现用户信息
                    $user = Db::name('user')->where('id', $row['user_id'])->find();
                    $userNickname = $user['nickname'] ?? '用户' . $row['user_id'];
                    
                    // 记录上级余额变动
                    Db::name('user_money_log')->insert([
                        'user_id' => $parentId,
                        'money' => $shareAmount,
                        'before' => $parentMoney,
                        'after' => $parentMoney + $shareAmount,
                        'memo' => '下级提现分成：来自' . $userNickname . '的提现（' . $shareRate . '%）',
                        'createtime' => time()
                    ]);
                    
                    // 记录佣金日志
                    Db::name('commission_log')->insert([
                        'user_id' => $parentId,
                        'order_id' => 0,
                        'amount' => $shareAmount,
                        'type' => 'income',
                        'memo' => '下级提现分成（' . $shareRate . '%）- 来自' . $userNickname,
                        'createtime' => time()
                    ]);
                }
            }
            
            Db::commit();
            $this->success('审核通过');
        } catch (\think\exception\HttpResponseException $e) {
            throw $e; // 重新抛出，不要捕获
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('操作失败：' . $e->getMessage());
        }
    }

    /**
     * 审核拒绝
     */
    public function reject($ids = null)
    {
        if (!$ids) {
            $this->error('请选择要拒绝的记录');
        }
        
        $row = $this->model->where('id', $ids)->find();
        if (!$row) {
            $this->error('记录不存在');
        }
        
        if ($row['status'] != 'audit_pending') {
            $this->error('该记录状态不是待审核');
        }
        
        $reason = $this->request->post('reason', '管理员拒绝');
        
        Db::startTrans();
        try {
            // 更新状态为拒绝
            Db::name('withdraw_log')->where('id', $ids)->update([
                'status' => 'rejected',
                'audit_time' => time(),
                'audit_admin_id' => $this->auth->id,
                'remark' => '审核拒绝：' . $reason,
                'updatetime' => time()
            ]);
            
            // 计算退回金额（提现金额 + 分成金额）
            $amount = floatval($row['amount']);
            $shareAmount = floatval($row['parent_share_amount'] ?? 0);
            $totalRefund = $amount + $shareAmount;
            
            // 退回用户余额
            Db::name('user')->where('id', $row['user_id'])->setInc('money', $totalRefund);
            
            // 获取用户当前余额
            $user = Db::name('user')->where('id', $row['user_id'])->find();
            $userMoney = floatval($user['money'] ?? 0);
            
            // 记录余额变动
            $memo = '提现审核拒绝退回';
            if ($shareAmount > 0) {
                $memo .= '（含分成' . $shareAmount . '元）';
            }
            Db::name('user_money_log')->insert([
                'user_id' => $row['user_id'],
                'money' => $totalRefund,
                'before' => $userMoney - $totalRefund,
                'after' => $userMoney,
                'memo' => $memo,
                'createtime' => time()
            ]);
            
            Db::commit();
            $this->success('已拒绝并退回余额');
        } catch (\think\exception\HttpResponseException $e) {
            throw $e; // 重新抛出，不要捕获
        } catch (\Exception $e) {
            Db::rollback();
            $this->error('操作失败：' . $e->getMessage());
        }
    }

    /**
     * 查看详情
     */
    public function detail($ids = null)
    {
        $row = $this->model->where('withdraw_log.id', $ids)->with(['user'])->find();
        if (!$row) {
            $this->error('记录不存在');
        }
        
        // 处理图片URL
        if (!empty($row->qrcode_image) && !preg_match('/^https?:\/\//i', $row->qrcode_image)) {
            $row->qrcode_image = is_cos_enabled() ? cdnurl($row->qrcode_image, true) : request()->domain() . $row->qrcode_image;
        }
        
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }
}
