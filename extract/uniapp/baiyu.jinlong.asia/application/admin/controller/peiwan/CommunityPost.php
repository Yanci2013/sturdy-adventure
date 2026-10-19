<?php

namespace app\admin\controller\peiwan;

use app\common\controller\Backend;

/**
 * 社区帖子管理
 *
 * @icon fa fa-circle-o
 */
class CommunityPost extends Backend
{

    /**
     * CommunityPost模型对象
     * @var \app\admin\model\peiwan\CommunityPost
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\peiwan\CommunityPost;
        $this->view->assign("isTopList", $this->model->getIsTopList());
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
                    ->with(['user'])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 重写add方法，处理图片JSON格式
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                // 处理图片字段：如果是逗号分隔的字符串，转换为JSON数组
                if (isset($params['images']) && !empty($params['images'])) {
                    $images = $params['images'];
                    // 如果是逗号分隔的字符串，转换为数组再转JSON
                    if (is_string($images) && strpos($images, ',') !== false) {
                        $imageArray = array_filter(array_map('trim', explode(',', $images)));
                        $params['images'] = json_encode($imageArray);
                    } elseif (is_string($images) && !empty($images)) {
                        // 单个图片，转换为数组
                        $params['images'] = json_encode([trim($images)]);
                    }
                }
                $params = $this->preExcludeFields($params);
                
                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }
                $result = false;
                \think\Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                        $this->model->validateFailException(true)->validate($validate);
                    }
                    $result = $this->model->allowField(true)->save($params);
                    \think\Db::commit();
                } catch (\think\exception\PDOException $e) {
                    \think\Db::rollback();
                    $this->error($e->getMessage());
                } catch (\think\Exception $e) {
                    \think\Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }

    /**
     * 重写edit方法，处理图片JSON格式
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
                // 处理图片字段：如果是逗号分隔的字符串，转换为JSON数组
                if (isset($params['images']) && !empty($params['images'])) {
                    $images = $params['images'];
                    // 如果是逗号分隔的字符串，转换为数组再转JSON
                    if (is_string($images) && strpos($images, ',') !== false) {
                        $imageArray = array_filter(array_map('trim', explode(',', $images)));
                        $params['images'] = json_encode($imageArray);
                    } elseif (is_string($images) && !empty($images)) {
                        // 单个图片，转换为数组
                        $params['images'] = json_encode([trim($images)]);
                    }
                }
                $params = $this->preExcludeFields($params);
                $result = false;
                \think\Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    $result = $row->allowField(true)->save($params);
                    \think\Db::commit();
                } catch (\think\exception\PDOException $e) {
                    \think\Db::rollback();
                    $this->error($e->getMessage());
                } catch (\think\Exception $e) {
                    \think\Db::rollback();
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
        
        // 处理图片字段：如果是JSON格式，转换为逗号分隔的字符串用于显示
        if (!empty($row['images'])) {
            $images = json_decode($row['images'], true);
            if (is_array($images)) {
                $row['images'] = implode(',', $images);
            }
        }
        
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


}
