<?php

namespace app\admin\controller\peiwan;

use app\common\controller\Backend;
use think\Db;

/**
 * 订单管理
 *
 * @icon fa fa-circle-o
 */
class Order extends Backend
{

    /**
     * Order模型对象
     * @var \app\admin\model\peiwan\Order
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\peiwan\Order;
        $this->view->assign("orderTypeList", $this->model->getOrderTypeList());
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
                    ->with(['shangjia', 'dashou', 'game', 'server'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
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
                $result = false;
                Db::startTrans();
                try {
                    // 只允许修改特定字段：价格、状态、备注
                    $allowFields = ['price', 'status', 'remark'];
                    $updateData = [];
                    foreach ($allowFields as $field) {
                        if (isset($params[$field])) {
                            $updateData[$field] = $params[$field];
                        }
                    }
                    if (!empty($updateData)) {
                        $result = $row->allowField($allowFields)->save($updateData);
                    } else {
                        $result = true;
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
        
        // 查询关联数据用于显示
        $row = $row->toArray();
        
        // 商家昵称
        $shangjia = Db::name('user')->where('id', $row['shangjia_id'])->field('nickname')->find();
        $row['shangjia_nickname'] = $shangjia ? $shangjia['nickname'] : '';
        
        // 打手昵称
        $dashou = Db::name('user')->where('id', $row['dashou_id'])->field('nickname')->find();
        $row['dashou_nickname'] = $dashou ? $dashou['nickname'] : '';
        
        // 客服昵称
        $kefu = Db::name('user')->where('id', $row['kefu_id'])->field('nickname')->find();
        $row['kefu_nickname'] = $kefu ? $kefu['nickname'] : '';
        
        // 游戏名称
        $game = Db::name('game')->where('id', $row['game_id'])->field('name')->find();
        $row['game_name'] = $game ? $game['name'] : '';
        
        // 区服名称
        $server = Db::name('game_server')->where('id', $row['server_id'])->field('server_name')->find();
        $row['server_name'] = $server ? $server['server_name'] : '';
        
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }
    
    /**
     * 查看订单详情（全部记录）
     */
    public function detail($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        
        $order = $row->toArray();
        
        // 获取商家信息
        $shangjia = Db::name('user')->where('id', $order['shangjia_id'])->field('id,nickname,avatar,role_type')->find();
        $order['shangjia'] = $shangjia;
        
        // 获取打手信息
        $dashou = Db::name('user')->where('id', $order['dashou_id'])->field('id,nickname,avatar,role_type')->find();
        $order['dashou'] = $dashou;
        
        // 获取客服信息
        $kefu = Db::name('user')->where('id', $order['kefu_id'])->field('id,nickname,avatar,role_type')->find();
        $order['kefu'] = $kefu;
        
        // 获取仲裁申请人信息
        if (!empty($order['arbitration_user_id'])) {
            $arbitrationUser = Db::name('user')->where('id', $order['arbitration_user_id'])->field('id,nickname,avatar,role_type')->find();
            $order['arbitration_user'] = $arbitrationUser;
        }
        
        // 获取游戏信息
        $game = Db::name('game')->where('id', $order['game_id'])->field('id,name,icon')->find();
        $order['game'] = $game;
        
        // 获取区服信息
        $server = Db::name('game_server')->where('id', $order['server_id'])->field('id,server_name')->find();
        $order['server'] = $server;
        
        // 获取举证记录
        $evidenceList = Db::name('order_evidence')
            ->alias('e')
            ->join('user u', 'e.user_id = u.id', 'LEFT')
            ->where('e.order_id', $ids)
            ->field('e.*, u.nickname, u.avatar, u.role_type')
            ->order('e.createtime asc')
            ->select();
        
        // 处理举证图片
        foreach ($evidenceList as &$evidence) {
            if (!empty($evidence['images'])) {
                // 先解码HTML实体（数据库可能存储了&quot;等编码）
                $imagesStr = html_entity_decode($evidence['images']);
                $images = json_decode($imagesStr, true);
                if (is_array($images)) {
                    $evidence['images_arr'] = $images;
                } else {
                    $evidence['images_arr'] = [];
                }
            } else {
                $evidence['images_arr'] = [];
            }
        }
        unset($evidence);
        
        $order['evidence_list'] = $evidenceList;
        
        // 获取售后处理记录
        $interveneList = Db::name('order_intervene_log')
            ->alias('l')
            ->join('user u', 'l.kefu_id = u.id', 'LEFT')
            ->where('l.order_id', $ids)
            ->field('l.*, u.nickname as handler_name, u.avatar as handler_avatar, u.role_type as handler_role')
            ->order('l.createtime asc')
            ->select();
        
        // 处理类型映射
        $interveneTypeMap = [
            'cancel_order' => '取消/撤销订单',
            'complete_order' => '已完成订单',
            'return_hall' => '发回大厅',
            'tip_player' => '打赏',
            'fine_player' => '罚款'
        ];
        
        foreach ($interveneList as &$intervene) {
            $intervene['type_text'] = $interveneTypeMap[$intervene['type']] ?? $intervene['type'];
            $intervene['createtime_text'] = date('Y-m-d H:i:s', $intervene['createtime']);
        }
        unset($intervene);
        
        $order['intervene_list'] = $interveneList;
        
        // 格式化时间
        $order['createtime_text'] = $order['createtime'] ? date('Y-m-d H:i:s', $order['createtime']) : '';
        $order['jiedan_time_text'] = $order['jiedan_time'] ? date('Y-m-d H:i:s', $order['jiedan_time']) : '';
        $order['wancheng_time_text'] = $order['wancheng_time'] ? date('Y-m-d H:i:s', $order['wancheng_time']) : '';
        $order['jiesuan_time_text'] = $order['jiesuan_time'] ? date('Y-m-d H:i:s', $order['jiesuan_time']) : '';
        $order['arbitration_time_text'] = !empty($order['arbitration_time']) ? date('Y-m-d H:i:s', $order['arbitration_time']) : '';
        
        // 订单类型文本
        $orderTypeMap = [
            'standard' => '标准订单',
            'custom' => '自定义订单'
        ];
        $order['order_type_text'] = $orderTypeMap[$order['order_type']] ?? $order['order_type'];
        
        // 订单状态文本
        $statusMap = [
            'daijiedan' => '待接单',
            'daifuwu' => '待服务',
            'daijiesuan' => '待结算',
            'yiwancheng' => '已完成',
            'guanbizhong' => '关闭中',
            'kefujieru' => '客服介入',
            'yichexiao' => '已撤销'
        ];
        $order['status_text'] = $statusMap[$order['status']] ?? $order['status'];
        
        // 角色类型文本映射
        $roleTypeMap = [
            'dashou' => '打手',
            'jindashou' => '金牌打手',
            'guanshi' => '管事',
            'zuzhang' => '组长',
            'zongguan' => '总管',
            'shangjia' => '商家',
            'jinshangjia' => '金牌商家',
            'kefu' => '客服',
            'shouhou' => '售后',
            'user' => '用户',
            'weironzheng' => '未认证'
        ];
        $order['role_type_map'] = $roleTypeMap;
        
        if ($this->request->isAjax()) {
            $this->success('获取成功', $order);
        }
        
        $this->view->assign("row", $order);
        $this->view->assign("roleTypeMap", $roleTypeMap);
        return $this->view->fetch();
    }
}
