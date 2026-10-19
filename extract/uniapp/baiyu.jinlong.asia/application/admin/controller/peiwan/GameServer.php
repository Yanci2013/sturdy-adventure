<?php

namespace app\admin\controller\peiwan;

use app\common\controller\Backend;

/**
 * 游戏区服管理
 *
 * @icon fa fa-circle-o
 */
class GameServer extends Backend
{

    /**
     * GameServer模型对象
     * @var \app\admin\model\peiwan\GameServer
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\peiwan\GameServer;
        $this->view->assign("statusList", $this->model->getStatusList());
    }

    /**
     * 重写selectpage方法，支持按游戏ID过滤区服
     */
    protected function selectpage()
    {
        // 设置过滤方法
        $this->request->filter(['trim', 'strip_tags', 'htmlspecialchars']);

        // 搜索关键词
        $word = (array)$this->request->request("q_word/a");
        // 当前页
        $page = $this->request->request("pageNumber", 1);
        // 分页大小
        $pagesize = $this->request->request("pageSize", 10);
        // 显示的字段
        $field = $this->request->request("showField", 'server_name');
        // 主键
        $primarykey = $this->request->request("keyField", 'id');
        // 主键值
        $primaryvalue = $this->request->request("keyValue");
        // 自定义搜索条件
        $custom = (array)$this->request->request("custom/a");
        
        // 构建查询条件
        $where = [];
        
        // 如果有primaryvalue，说明是初始化传值
        if ($primaryvalue !== null) {
            $where[$primarykey] = ['in', $primaryvalue];
            $pagesize = 999999;
        } else {
            // 关键词搜索
            $word = array_filter(array_unique($word));
            if (!empty($word)) {
                $where['server_name'] = ['like', '%' . implode('%', $word) . '%'];
            }
        }
        
        // 游戏ID过滤（只有非空时才过滤）
        if (isset($custom['game_id']) && $custom['game_id'] !== '' && $custom['game_id'] !== null) {
            $where['game_id'] = intval($custom['game_id']);
        }
        
        // 查询数据
        $total = $this->model->where($where)->count();
        $list = [];
        
        if ($total > 0) {
            $datalist = $this->model
                ->where($where)
                ->order('id', 'desc')
                ->page($page, $pagesize)
                ->select();
            
            foreach ($datalist as $item) {
                $list[] = [
                    $primarykey => $item[$primarykey] ?? '',
                    $field => $item[$field] ?? '',
                ];
            }
        }
        
        return json(['list' => $list, 'total' => $total]);
    }


    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


    /**
     * 查看
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                    ->with(['game'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                
                
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

}
