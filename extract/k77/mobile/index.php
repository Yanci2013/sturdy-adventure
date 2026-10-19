<?php
// 开启错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 引入头部文件（包含会话启动、数据库连接等）
require __DIR__ . '/header.php';

// 获取游戏大区数据
$game_regions_list = $pdo->query("SELECT id, name FROM game_regions WHERE status = 1 ORDER BY sort_order DESC, id ASC")->fetchAll();
if (empty($game_regions_list)) {
    $game_regions_list = [['id' => 1, 'name' => 'QQ区'], ['id' => 2, 'name' => '微信区']];
}

/**
 * 添加URL查询参数
 */
function addQueryParam($param, $value) {
    $query = $_GET;
    $query[$param] = $value;
    return '?' . http_build_query($query);
}
/**
 * 移除URL查询参数
 */
function removeQueryParam($param) {
    $query = $_GET;
    unset($query[$param]);
    return $query ? '?' . http_build_query($query) : $_SERVER['PHP_SELF'];
}
/**
 * 获取订单备注（从input_fields字段中提取）
 */
function getOrderNotes($inputFieldsJson, $truncate = true) {
    if (empty($inputFieldsJson) || !is_string($inputFieldsJson)) return '';
    $inputFields = json_decode($inputFieldsJson, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($inputFields)) return '';
    $notes = '';
    if (isset($inputFields['备注']) && !empty($inputFields['备注'])) $notes = $inputFields['备注'];
    elseif (isset($inputFields['note']) && !empty($inputFields['note'])) $notes = $inputFields['note'];
    elseif (isset($inputFields['remarks']) && !empty($inputFields['remarks'])) $notes = $inputFields['remarks'];
    if ($truncate && mb_strlen($notes) > 30) $notes = mb_substr($notes, 0, 30) . '...';
    return htmlspecialchars($notes);
}

// 获取当前用户ID
$user_id = get_user_user_id() ?? 0;
if (!$user_id) {
    header('Location: login.php');
    exit;
}
// 获取系统设置
$settings = getSystemSettings($pdo);
$orderMode = $settings['order_mode'] ?? 'deposit';
// 获取当前用户数据
$user = getUserById($pdo, $user_id);
if (!$user) {
    $error = "用户信息获取失败，请重新登录";
    header('Location: login.php?error=' . urlencode($error));
    exit;
}
// 检查用户可接单状态
$isOnline = isset($user['available_for_orders']) && $user['available_for_orders'] == 1;
// 检查用户会员状态
$hasValidMember = false;
$userMemberLevel = null;
if (!empty($user['member_level_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM member_levels WHERE id = ? AND status = 1");
        $stmt->execute([$user['member_level_id']]);
        $userMemberLevel = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($userMemberLevel) {
            $currentTime = date('Y-m-d H:i:s');
            if (!empty($user['member_end_time']) && $currentTime < $user['member_end_time']) $hasValidMember = true;
            elseif (!empty($user['is_trial_member']) && $user['is_trial_member'] == 1) $hasValidMember = true;
        }
    } catch (PDOException $e) {
        $hasValidMember = false;
    }
}

// 初始化筛选变量
$categoryMap = [];
$category = $_GET['category'] ?? '';
$server_type = $_GET['server_type'] ?? '';
$search_no = $_GET['search_no'] ?? '';
$error = $success = '';

if (isset($_GET['report_success']) && $_GET['report_success'] == 1) $success = '举报成功，我们将尽快处理';
if (isset($_GET['success']) && $_GET['success'] == 1) $success = '接单成功！';

// 处理接单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'take_order') {
    $order_id = (int)$_POST['order_id'];
    if ($order_id > 0) {
        try {
            $pdo->beginTransaction();
            $checkStatus = $pdo->prepare("SELECT available_for_orders, deposit FROM users WHERE id = ?");
            $checkStatus->execute([$user_id]);
            $userStatus = $checkStatus->fetch();
            if (!$userStatus || $userStatus['available_for_orders'] != 1) {
                $pdo->rollBack();
                $error = '您当前处于不可接单状态，请先开启可接单状态后再抢单';
            } else {
                $minDeposit = floatval($settings['deposit'] ?? 0);
                if ($orderMode === 'deposit' && $minDeposit > 0) {
                    $userDeposit = floatval($userStatus['deposit'] ?? 0);
                    if ($userDeposit < $minDeposit) {
                        $pdo->rollBack();
                        $error = "抢单需要缴纳至少 {$minDeposit} 元保证金，<a href='bzj/index.php' style='color: #ff6b00; text-decoration: underline;'>点此充值保证金</a>";
                        goto skip_order_processing;
                    }
                }
                $check = $pdo->prepare("SELECT o.id, o.order_status, o.hunter_option, o.hunter_id, o.category_id, o.input_fields, o.lock_status, o.lock_time, o.lock_user_id FROM orders o WHERE o.id = ? FOR UPDATE");
                $check->execute([$order_id]);
                $order = $check->fetch();
                if (!$order) {
                    $pdo->rollBack();
                    $error = '订单不存在';
                } elseif ($order['order_status'] != 1) {
                    $pdo->rollBack();
                    $error = '订单状态不可接单';
                } else {
                    if ($order['lock_status'] == 1) {
                        $lock_time = strtotime($order['lock_time']);
                        $current_time = time();
                        if ($current_time - $lock_time < 300) {
                            if ($order['lock_user_id'] != $user_id) {
                                $pdo->rollBack();
                                $error = '订单正在被其他用户抢单，请稍后再试';
                                goto skip_order_processing;
                            }
                        } else {
                            $unlock = $pdo->prepare("UPDATE orders SET lock_status = 0, lock_time = NULL, lock_user_id = 0 WHERE id = ?");
                            $unlock->execute([$order_id]);
                        }
                    }
                    $lock = $pdo->prepare("UPDATE orders SET lock_status = 1, lock_time = NOW(), lock_user_id = ? WHERE id = ?");
                    $lock->execute([$user_id, $order_id]);
                    $hasMemberPermission = true;
                    $requiredMemberNames = [];
                    if ($orderMode === 'member') {
                        if (!$hasValidMember) {
                            $hasMemberPermission = false;
                            $requiredMemberNames = ['会员'];
                        }
                    }
                    if ($hasMemberPermission) {
                        $stmt = $pdo->prepare("SELECT ml.id, ml.name FROM member_level_categories mlc LEFT JOIN member_levels ml ON mlc.member_level_id = ml.id WHERE mlc.category_id = ?");
                        $stmt->execute([$order['category_id']]);
                        $requiredMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        if (!empty($requiredMembers)) {
                            $hasRequiredMember = false;
                            if ($hasValidMember && $userMemberLevel) {
                                foreach ($requiredMembers as $reqMember) {
                                    if ($userMemberLevel['id'] == $reqMember['id'] || $userMemberLevel['id'] > $reqMember['id']) {
                                        $hasRequiredMember = true;
                                        break;
                                    }
                                }
                            }
                            if (!$hasRequiredMember) {
                                $hasMemberPermission = false;
                                $requiredMemberNames = array_column($requiredMembers, 'name');
                            }
                        }
                    }
                    if (!$hasMemberPermission) {
                        $pdo->rollBack();
                        $memberNames = implode('、', $requiredMemberNames);
                        $error = "抢此订单需要开通 {$memberNames}，<a href='purchase_member.php' style='color: #ff6b00; text-decoration: underline;'>点此开通会员</a>";
                    } else {
                        $isTipOrder = false;
                        $tipPlayerId = 0;
                        if (!empty($order['input_fields'])) {
                            $inputFields = json_decode($order['input_fields'], true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($inputFields)) {
                                if (isset($inputFields['transaction_type']) && $inputFields['transaction_type'] === 'tip') {
                                    $isTipOrder = true;
                                    $tipPlayerId = isset($inputFields['tip_player_id']) ? intval($inputFields['tip_player_id']) : 0;
                                }
                            }
                        }
                        if ($isTipOrder && $tipPlayerId != $user_id) {
                            $pdo->rollBack();
                            $error = '这是打赏订单，只有被打赏的打手才能接单';
                        } elseif (!empty($order['hunter_option']) && $order['hunter_option'] === 'assign' && !empty($order['hunter_id'])) {
                            if ($order['hunter_id'] != $user_id) {
                                $pdo->rollBack();
                                $error = '这是指定单，只有被指定的打手才能接单';
                            } else {
                                $update = $pdo->prepare("UPDATE orders SET hunter_user_id = ?, order_status = 2, accept_time = NOW(),lock_status = 0, lock_time = NULL, lock_user_id = 0 WHERE id = ?");
                                if ($update->execute([$user_id, $order_id])) {
                                    if ($update->rowCount() > 0) {
                                        $pdo->commit();
                                        header('Location: ' . $_SERVER['PHP_SELF'] . '?success=1');
                                        exit;
                                    } else {
                                        $pdo->rollBack();
                                        $error = '订单可能已被其他打手接走';
                                    }
                                } else {
                                    $pdo->rollBack();
                                    $error = '数据库更新失败';
                                }
                            }
                        } else {
                            $update = $pdo->prepare("UPDATE orders SET hunter_user_id = ?, order_status = 2, accept_time = NOW(),lock_status = 0, lock_time = NULL, lock_user_id = 0 WHERE id = ?");
                            if ($update->execute([$user_id, $order_id])) {
                                if ($update->rowCount() > 0) {
                                    $pdo->commit();
                                    header('Location: ' . $_SERVER['PHP_SELF'] . '?success=1');
                                    exit;
                                } else {
                                    $pdo->rollBack();
                                    $error = '订单可能已被其他打手接走';
                                }
                            } else {
                                $pdo->rollBack();
                                $error = '数据库更新失败';
                            }
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = '接单失败: ' . $e->getMessage();
        }
        skip_order_processing:;
    } else {
        $error = '无效的订单ID';
    }
}

// 举报订单逻辑保留
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'report_order') {
    $reportLog = '举报流程开始: ' . date('Y-m-d H:i:s') . ' | 订单ID: ' . ($_POST['order_id'] ?? '未知') . ' | 用户ID: ' . $user_id . PHP_EOL;
    $logFile = __DIR__ . '/report_errors.log';
    try {
        $pdo->beginTransaction();
        $order_id = (int)$_POST['order_id'];
        $report_reason = isset($_POST['report_reason']) ? trim($_POST['report_reason']) : '';
        $report_content = isset($_POST['report_content']) ? trim($_POST['report_content']) : '';
        if (!$order_id) throw new Exception('订单ID不能为空');
        $stmt = $pdo->prepare("SELECT id, order_no FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        if (!$order) throw new Exception('订单不存在');
        $stmt = $pdo->prepare("SELECT id FROM order_reports WHERE order_id = ? AND user_id = ?");
        $stmt->execute([$order_id, $user_id]);
        if ($stmt->fetch()) throw new Exception('您已经举报过该订单');
        if (empty($report_reason)) throw new Exception('请选择举报原因');
        if (empty($report_content)) throw new Exception('请填写举报详细内容');
        $insert = $pdo->prepare("INSERT INTO order_reports (order_id, user_id, report_reason, report_content, report_time, status) VALUES (?, ?, ?, ?, NOW(), 1)");
        $insert->execute([$order_id, $user_id, $report_reason, $report_content]);
        $pdo->commit();
        header('Location: ' . $_SERVER['PHP_SELF'] . '?report_success=1');
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = '举报失败：' . $e->getMessage();
    }
}

// 查询订单
try {
    $query = "SELECT o.*, c.logo AS category_logo, hu.username AS assigned_hunter_username, lu.username AS lock_username FROM orders o LEFT JOIN product_categories c ON o.category_id = c.id LEFT JOIN users hu ON o.hunter_id = hu.id LEFT JOIN users lu ON o.lock_user_id = lu.id WHERE o.order_status = 1";
    $params = [];
    if (!empty($category) && $category !== 'all') {
        $query .= " AND o.category_id = ?";
        $params[] = $category;
    }
    if (!empty($server_type)) {
        $query .= " AND o.game_region LIKE ?";
        $params[] = '%' . $server_type . '%';
    }
    if (!empty($search_no)) {
        $query .= " AND o.order_no LIKE ?";
        $params[] = "%{$search_no}%";
    }
    $query .= " ORDER BY o.create_time DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $categoryMemberLevels = [];
    try {
        $stmt = $pdo->query("SELECT mlc.category_id,mlc.member_level_id,ml.name member_level_name FROM member_level_categories mlc LEFT JOIN member_levels ml ON mlc.member_level_id=ml.id");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categoryMemberLevels[$row['category_id']][] = $row;
        }
    } catch (PDOException $e) {
        $categoryMemberLevels = [];
    }
    foreach ($orders as &$order) {
        $order['required_member_levels'] = $categoryMemberLevels[$order['category_id']] ?? [];
    }
    unset($order);
} catch (PDOException $e) {
    die("订单查询失败: " . $e->getMessage());
}
// 读取全部分类用于顶部筛选
try {
    $stmt = $pdo->query("SELECT id,name,logo FROM product_categories WHERE status=1 AND level=1");
    while ($cat = $stmt->fetch()) $categoryMap[$cat['id']] = ['name' => $cat['name'], 'logo' => $cat['logo']];
} catch (PDOException $e) {}

// 后台配置全局接单配图（后台设置上传保存到settings）
$globalOrderImg = !empty($settings['order_goods_img']) ? $settings['order_goods_img'] : '';
$defaultImg = $globalOrderImg;
?>
<!DOCTYPE html>
  <html lang="zh-CN">
  <head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1,user-scalable=no">
  <title><?=$settings['site_title']??'广州小熠科技-接单大厅'?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/font-awesome@6.4/css/all.min.css">
  <style>
  *{margin:0;padding:0;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;-webkit-tap-highlight-color:transparent;}
  html{scroll-behavior:smooth;font-size:13px;}
  body{background:linear-gradient(180deg,#f3f7ff,#fffbf5);padding-bottom:30px;color:#333;}
  .top-title{font-size:22px;font-weight:600;text-align:center;padding:16px 12px 10px;background:linear-gradient(90deg,#3672ff,#9b56ff);-webkit-background-clip:text;color:transparent;letter-spacing:1px;}
  .search-box{padding:0 12px 12px;}
  .search-form{display:flex;gap:8px;flex-wrap:wrap;}
  .search-input{flex:1;height:40px;background:#fffdf0;border-radius:99px;border:1px solid #ffe270;padding:0 16px;font-size:14px;transition:all 0.24s;min-width:150px;}
  .search-input:focus{box-shadow:0 0 0 3px rgba(255,200,0,0.25);border-color:#ffcc00;outline:none;}
 .search-btn{width:76px;height:40px;background:linear-gradient(135deg,#ffd200,#ff9e00);border-radius:99px;border:none;font-weight:600;cursor:pointer;transition:0.22s;}
  .search-btn:active{transform:scale(0.95);}
  .reset-btn{line-height:40px;color:#666;padding:0 6px;font-size:12px;}
  .tab-group{display:flex;padding:0 12px 12px;gap:6px;flex-wrap:wrap;}
  .tab-item{padding:7px 14px;border-radius:99px;border:1px solid #e0e0e0;background:#fff;cursor:pointer;transition:all 0.23s;font-size:13px;}
  .tab-item.active{background:linear-gradient(135deg,#336cff,#794bff);color:#fff;border-color:transparent;box-shadow:0 3px 8px rgba(80,120,255,0.25);}
  .tab-item:hover:not(.active){border-color:#336cff;color:#336cff;}
  .order-wrap{padding:0 12px;}
  /*=====卡片改紧凑缩小=====*/
  .order-card{background:#fff;border-radius:12px;padding:12px;margin-bottom:12px;box-shadow:0 2px 8px rgba(0,0,0,0.05);border:1px solid #f5f5f5;transition:all 0.28s ease;}
  .order-card:active{transform:translateY(-2px);box-shadow:0 5px 12px rgba(0,0,0,0.07);}
  .card-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
  .area-name{font-size:15px;font-weight:600;color:#222;}
  .fee-desc{font-size:12px;color:#555;background:#f0f7ff;padding:2px 7px;border-radius:99px;}
  .goods-row{display:flex;gap:10px;margin-bottom:10px;align-items:center;}
  /*商品图片缩小*/
  .goods-pic{width:70px;height:70px;border-radius:10px;object-fit:cover;background:#f7f7f7;border:1px solid #eee;transition:transform 0.3s ease;overflow:hidden;}
  .goods-pic:hover{transform:scale(1.12);}
  .default-img{display:flex;align-items:center;justify-content:center;color:#bbbbbb;font-size:22px;}
  .goods-text{flex:1;}
  .goods-name{font-size:15px;font-weight:600;margin-bottom:4px;color:#1f1f1f;}
  .goods-price{font-size:18px;color:#f43737;font-weight:bold;}
  .goods-num{float:right;font-size:12px;color:#666;}
  .goods-note{font-size:12px;color:#727272;margin-top:4px;line-height:16px;display:block;}
  .info-row{display:flex;height:26px;align-items:center;font-size:12px;}
  .info-lab{width:100px;color:#595959;}
  .info-val{flex:1;color:#272727;}
  .price-red{color:#f43737;font-weight:bold;}
  .btn-box{margin-top:10px;text-align:right;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;}
  .take-btn{width:98px;height:40px;background:linear-gradient(135deg,#ffd100,#ffaa00);border:none;border-radius:99px;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 2px 8px rgba(255,180,0,0.22);transition:all 0.23s;color:#111;}
  .take-btn:active{transform:scale(0.94);box-shadow:none;}
  .report-btn{height:40px;padding:0 12px;background:#f1f1f1;border:none;border-radius:99px;font-size:12px;cursor:pointer;transition:0.22s;color:#555;}
  .report-btn:hover{background:#ff4f4f;color:#fff;}
  .btn-disable{background:#d3d3d3;background-image:none;cursor:not-allowed;box-shadow:none;color:#777;}
  .btn-link{display:inline-block;text-decoration:none;line-height:40px;color:#111;}
  .warn-block{padding:14px;background:linear-gradient(135deg,#fff6e8,#fff0dd);border:1px solid #ffd096;border-radius:12px;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;}
  .warn-left{display:flex;gap:10px;align-items:center;}
  .warn-icon{width:38px;height:38px;background:linear-gradient(135deg,#ff9927,#ff7822);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;}
  .warn-t1{color:#d45300;font-weight:600;font-size:14px;margin-bottom:3px;}
  .warn-t2{font-size:12px;color:#e16c20;}
  .warn-btn{background:linear-gradient(135deg,#ff9927,#ff7822);color:#fff;padding:8px 18px;border-radius:99px;text-decoration:none;font-size:13px;font-weight:550;transition:0.22s;}
  .warn-btn:active{transform:scale(0.95);}
  .pop-mask{position:fixed;left:0;top:0;right:0;bottom:0;background:rgba(0,0,0,0.62);display:none;z-index:9999;align-items:center;justify-content:center;padding:16px;opacity:0;transition:opacity 0.26s ease;}
  .pop-mask.show{display:flex;opacity:1;}
  .pop-box{width:100%;max-width:330px;background:#fff;border-radius:16px;padding:22px;transform:scale(0.82);transition:transform 0.26s ease;}
  .pop-mask.show .pop-box{transform:scale(1);}
  .pop-title{text-align:center;font-size:17px;font-weight:600;margin-bottom:18px;color:#222;}
  .code-text{font-size:34px;letter-spacing:10px;text-align:center;margin-bottom:20px;color:#222;user-select:none;background:#f8f8f8;padding:12px 0;border-radius:10px;}
  .code-inp{width:100%;height:44px;border:1px solid #ddd;border-radius:10px;padding:0 15px;font-size:16px;outline:#ffcc00 solid 1px;margin-bottom:18px;text-align:center;transition:0.22s;}
  .code-inp:focus{box-shadow:0 0 0 3px rgba(255,204,0,0.24);}
  .pop-btn-group{display:flex;gap:12px;}
  .btn-cancel{flex:1;height:44px;border:1px solid #dddddd;background:#fff;border-radius:10px;font-size:14px;transition:0.22s;}
  .btn-cancel:active{background:#f3f3f3;transform:scale(0.95);}
  .btn-sub{flex:1;height:44px;background:linear-gradient(135deg,#ffd100,#ffaa00);border:none;border-radius:10px;font-size:14px;font-weight:600;transition:0.22s;}
  .btn-sub:active{transform:scale(0.95);}
  .report-mask{position:fixed;left:0;top:0;right:0;bottom:0;background:rgba(0,0,0,0.62);display:none;z-index:9999;align-items:center;justify-content:center;padding:16px;opacity:0;transition:opacity 0.26s;}
  .report-mask.show{display:flex;opacity:1;}
  .report-box{width:100%;max-width:330px;background:#fff;border-radius:16px;padding:22px;transform:scale(0.82);transition:transform 0.26s;}
  .report-mask.show .report-box{transform:scale(1);}
  .report-title{text-align:center;font-size:17px;font-weight:600;margin-bottom:15px;}
  .report-select{width:100%;height:42px;border:1px solid #ddd;border-radius:10px;padding:0 12px;margin-bottom:12px;font-size:14px;}
  .report-textarea{width:100%;height:100px;border:1px solid #ddd;border-radius:10px;padding:10px;font-size:14px;resize:none;margin-bottom:15px;}
  .report-btn-group{display:flex;gap:12px;}
  .msg-toast{position:fixed;top:38px;left:50%;transform:translateX(-50%) scale(0.8);background:rgba(0,0,0,0.76);color:#fff;padding:11px 22px;border-radius:9px;display:none;z-index:99999;font-size:13px;opacity:0;transition:all 0.24s ease;}
  .msg-toast.open{display:block;opacity:1;transform:translateX(-50%) scale(1);}
  .toast-error{background:rgba(228,42,42,0.82);}
  .empty-box{text-align:center;padding:50px 20px;background:#fff;border-radius:12px;color:#999;}
  .empty-icon{font-size:42px;color:#ddd;margin-bottom:12px;}
  </style>
      <link rel="stylesheet" href="../assets/css/theme-v2.css">
</head>
  <body>
<!-- V2 加载动画 -->
<div class="v2-loader-overlay" id="v2Loader">
    <div class="v2-loader-logo-wrap">
        <div class="v2-loader-ring"></div>
        <div class="v2-loader-ring"></div>
        <div class="v2-loader-logo">小</div>
    </div>
    <div class="v2-loader-text">
        <span>加</span><span>载</span><span>中</span><span>...</span>
    </div>
    <div class="v2-loader-progress">
        <div class="v2-loader-progress-bar"></div>
    </div>
</div>

  <div class="pop-mask" id="popMask">
      <div class="pop-box">
          <div class="pop-title">输入4位验证码接单</div>
          <div class="code-text" id="codeShow"></div>
          <input class="code-inp" id="codeInput" maxlength="4" placeholder="填写上方数字">
          <div class="pop-btn-group">
              <button class="btn-cancel" id="popCancel">取消</button>
              <button class="btn-sub" id="popSubmit">确认接单</button>
          </div>
      </div>
  </div>
  <div class="report-mask" id="reportMask">
      <div class="report-box">
          <div class="report-title">订单举报</div>
          <select class="report-select" id="reportSel">
              <option value="">请选择举报原因</option>
              <option value="商家恶意取消">商家恶意取消</option>
              <option value="虚假订单">虚假订单</option>
              <option value="描述不符">商品描述不符</option>
              <option value="其他问题">其他问题</option>
          </select>
          <textarea class="report-textarea" id="reportContent" placeholder="详细描述举报内容"></textarea>
          <div class="report-btn-group">
              <button class="btn-cancel" id="reportCancel">取消</button>
              <button class="btn-sub" id="reportSubmit">提交举报</button>
          </div>
      </div>
  </div>
  <div class="msg-toast" id="toastTip"></div>
  <?php if($success):?>
  <div class="msg-toast open"><?=$success?></div>
  <script>setTimeout(()=>toastTip.classList.remove('open'),2400)</script>
  <?php endif;?>
  <?php if($error):?>
  <div class="msg-toast toast-error open"><?=$error?></div>
  <script>setTimeout(()=>toastTip.classList.remove('open'),2400)</script>
  <?php endif;?>
  <div class="top-title">接单大厅</div>
  <div class="search-box">
      <form class="search-form" action="" method="get">
          <input class="search-input" name="search_no" value="<?=htmlspecialchars($search_no)?>" placeholder="输入订单编号精准查询">
          <button class="search-btn" type="submit">搜索</button>
          <?php if(!empty($search_no)):?><a href="<?=removeQueryParam('search_no')?>" class="reset-btn">重置</a><?php endif;?>
      </form>
  </div>
  <div class="tab-group">
      <div class="tab-item <?=empty($server_type)?'active':''?>" data-ser="">全部大区</div>
      <div class="tab-item <?=$server_type=='QQ区'?'active':''?>" data-ser="QQ区">QQ区</div>
      <div class="tab-item <?=$server_type=='微信区'?'active':''?>" data-ser="微信区">微信区</div>
  </div>
  <div class="order-wrap">
  <?php
  if ($orderMode === 'deposit' && $isOnline):
      $minDeposit = floatval($settings['deposit']??0);
      if($minDeposit>0):
          $ud = floatval($user['deposit']??0);
          if($ud<$minDeposit):
              $need = $minDeposit-$ud;
  ?>
  <div class="warn-block">
      <div class="warn-left">
          <div class="warn-icon"><i class="fas fa-wallet"></i></div>
          <div>
              <div class="warn-t1">保证金不足，无法抢单</div>
              <div class="warn-t2">当前¥<?=number_format($ud,2)?>，需¥<?=number_format($minDeposit,2)?>，还差¥<?=number_format($need,2)?></div>
          </div>
      </div>
      <a href="bzj/index.php" class="warn-btn">立即充值</a>
  </div>
  <?php endif;endif;endif;?>
  <?php if(!empty($orders)):foreach($orders as $order):
  $orderNotes = getOrderNotes($order['input_fields']??'',false);
  $isAssignedOrder = !empty($order['hunter_option'])&&$order['hunter_option']==='assign'&&!empty($order['hunter_id']);
  $serverType = strpos($order['game_region']??'','微信')!==false?'微信区':(strpos($order['game_region']??'','QQ')!==false?'QQ区':'');
  $hunterCommissionRate = floatval($settings['hunter_commission_rate']??0);
  $expectedEarnings = $order['price']*($hunterCommissionRate/100);
  $inputFields = json_decode($order['input_fields']??'{}',true);
  $remark = $inputFields['备注']??$inputFields['remark']??'无';
  $canTakeOrder = $isOnline;$disableReason='';$needsDeposit=false;$needsMember=false;$requiredMemberNames=[];
  if(!$isOnline){$disableReason='请先开启可接单状态';}else{
  $isTipOrder=false;$tipPlayerId=0;
  if(!empty($order['input_fields'])){$inArr=json_decode($order['input_fields'],true);if(json_last_error()===JSON_ERROR_NONE&&is_array($inArr)){if(isset($inArr['transaction_type'])&&$inArr['transaction_type']==='tip'){$isTipOrder=true;$tipPlayerId = isset($inArr['tip_player_id'])?intval($inArr['tip_player_id']):0;}}}
  if($order['lock_status']==1&&$order['lock_user_id']!=$user_id){$canTakeOrder=false;$disableReason='订单正在被其他用户抢单';}
  elseif($isTipOrder&&$tipPlayerId!=$user_id){$canTakeOrder=false;$disableReason='这是打赏订单，仅指定打手可接单';}
  elseif($isAssignedOrder&&$order['hunter_id']!=$user_id){$canTakeOrder=false;$disableReason='指定订单，非绑定打手无法接单';}
  }
  if($canTakeOrder&&$orderMode==='deposit'){$minDeposit=floatval($settings['deposit']??0);if($minDeposit>0){$ud=floatval($user['deposit']??0);if($ud<$minDeposit){$canTakeOrder=false;$needsDeposit=true;$disableReason="需保证金{$minDeposit}元";}}}
  if($canTakeOrder&&$orderMode==='member'){if(!$hasValidMember){$canTakeOrder=false;$needsMember=true;$requiredMemberNames=['会员'];$disableReason='需要开通会员';}}
  if($canTakeOrder&&!empty($order['required_member_levels'])){$hasRM=false;$requiredMemberNames=array_column($order['required_member_levels'],'member_level_name');if($hasValidMember&&$userMemberLevel){foreach($order['required_member_levels'] as $rl){if($userMemberLevel['id']==$rl['member_level_id']||$userMemberLevel['id']>$rl['member_level_id']){$hasRM=true;break;}}}if(!$hasRM){$canTakeOrder=false;$needsMember=true;$disableReason='需开通'.implode('、',$requiredMemberNames).'会员';}}
  // ==========修改：从商品表真实读取goods_img图片=========
$proImg = '';
$pid = intval($order['product_id'] ?? 0);
if($pid > 0){
    try{
        $imgSql = $pdo->prepare("SELECT main_image FROM products WHERE id=? LIMIT 1");
        $imgSql->execute([$pid]);
        $imgData = $imgSql->fetch(PDO::FETCH_ASSOC);
        if(!empty($imgData['main_image'])){
            // 后台上传后存完整URL，直接赋值，不用拼接域名
            $proImg = $imgData['main_image'];
        }
    }catch(PDOException $e){}
}
// 无图/图片打不开自动用备用图
if(empty($proImg)){
    $proImg = 'https://s3.bmp.ovh/2026/05/10/TYotlHDG.png';
}
  if(empty($proImg)) $proImg = $defaultImg;
  ?>
  <div class="order-card" data-oid="<?=$order['id']?>">
      <div class="card-head">
          <span class="area-name"><?=$serverType?></span>
          <span class="fee-desc">预计服务费：¥<?=number_format($expectedEarnings,2)?></span>
      </div>
      <div class="goods-row">
          <?php if(!empty($proImg)):?>
              <img class="goods-pic" src="<?=htmlspecialchars($proImg)?>" onerror="this.className='goods-pic default-img';this.innerHTML='<i class=\'fas fa-image\'></i>';this.src=''">
          <?php else:?>
              <div class="goods-pic default-img"><i class="fas fa-image"></i></div>
          <?php endif;?>
          <div class="goods-text">
              <div class="goods-name"><?=htmlspecialchars($order['product_name'])?></div>
              <span class="goods-price">¥<?=$order['price']?></span><span class="goods-num">×<?=isset($order['buy_num'])?$order['buy_num']:1?></span>
              <span class="goods-note"><?=htmlspecialchars($orderNotes)?></span>
          </div>
      </div>
      <div class="info-row"><div class="info-lab">订单编号</div><div class="info-val"><?=$order['order_no']?></div></div>
      <div class="info-row"><div class="info-lab">商品分类</div><div class="info-val"><?=!empty($categoryMap[$order['category_id']]['name'])?$categoryMap[$order['category_id']]['name']:'未分类'?></div></div>
      <div class="info-row"><div class="info-lab">游戏ID</div><div class="info-val">接单后可查看</div></div>
      <div class="info-row"><div class="info-lab">游戏区服</div><div class="info-val"><?=!empty($order['game_region'])?htmlspecialchars($order['game_region']):'无'?></div></div>
      <div class="info-row"><div class="info-lab">订单备注</div><div class="info-val"><?=htmlspecialchars($remark)?></div></div>
      <div class="info-row"><div class="info-lab">实付款</div><div class="info-val price-red">¥<?=$order['price']*(isset($order['buy_num'])?$order['buy_num']:1)?></div></div>
      <div class="btn-box">
          <button class="report-btn report-open" data-rid="<?=$order['id']?>"><i class="fas fa-exclamation-circle"></i> 举报</button>
          <?php if($needsDeposit):?>
          <a href="bzj/index.php" class="take-btn btn-link">充值接单</a>
          <?php elseif($needsMember):?>
          <a href="purchase_member.php" class="take-btn btn-link">开通会员</a>
          <?php elseif($canTakeOrder):?>
          <button class="take-btn take-btn-act">接单</button>
          <?php else:?>
          <button class="take-btn btn-disable" disabled title="<?=$disableReason?>">接单</button>
          <?php endif;?>
      </div>
  </div>
  <?php endforeach;else:?>
  <div class="empty-box">
      <div class="empty-icon"><i class="fas fa-file-invoice"></i></div>
      <div>暂无符合筛选条件的可接订单</div>
  </div>
  <?php endif;?>
  </div>
  <form id="submitForm" method="post" style="display:none;">
      <input name="action" value="take_order">
      <input name="order_id" id="formOid">
  </form>
  <form id="reportForm" method="post" style="display:none;">
      <input name="action" value="report_order">
      <input name="order_id" id="reportOid">
      <input name="report_reason" id="repReason">
      <input name="report_content" id="repContent">
  </form>
  <script>
  let nowOrderId = 0;
  let nowRepId = 0;
  let correctCode = '';
  const popMask = document.getElementById('popMask');
  const codeShow = document.getElementById('codeShow');
  const codeInput = document.getElementById('codeInput');
  const popCancel = document.getElementById('popCancel');
  const popSubmit = document.getElementById('popSubmit');
  const submitForm = document.getElementById('submitForm');
  const formOid = document.getElementById('formOid');
  const toastTip = document.getElementById('toastTip');
  const reportMask = document.getElementById('reportMask');
  const reportCancel = document.getElementById('reportCancel');
  const reportSubmit = document.getElementById('reportSubmit');
  const reportSel = document.getElementById('reportSel');
  const reportContent = document.getElementById('reportContent');
  const reportOid = document.getElementById('reportOid');
  const repReason = document.getElementById('repReason');
  const repContent = document.getElementById('repContent');
  const reportForm = document.getElementById('reportForm');
  function createVerifyCode(){
      let code = '';
      for(let i=0;i<4;i++)code += Math.floor(Math.random()*10);
      correctCode = code;
      codeShow.innerText = code;
      codeInput.value = '';
  }
  document.querySelectorAll('.take-btn-act').forEach(btn=>{
      btn.onclick = function(){
          nowOrderId = this.closest('.order-card').dataset.oid;
          createVerifyCode();
          popMask.classList.add('show');
      }
  })
  popCancel.onclick = ()=>{popMask.classList.remove('show');nowOrderId=0;};
  popMask.onclick = e=>{if(e.target===popMask){popMask.classList.remove('show');nowOrderId=0;}};
  popSubmit.onclick = ()=>{
      let input = codeInput.value.trim();
      if(input === correctCode){
          formOid.value = nowOrderId;
          submitForm.submit();
      }else{
          showToast('验证码错误，请重新输入');
          createVerifyCode();
      }
  };
  document.querySelectorAll('.report-open').forEach(btn=>{
      btn.onclick = function(){
          nowRepId = this.dataset.rid;
          reportMask.classList.add('show');
          reportSel.value = '';
          reportContent.value = '';
      }
  })
  reportCancel.onclick = ()=>{reportMask.classList.remove('show');nowRepId=0;};
  reportMask.onclick = e=>{if(e.target===reportMask){reportMask.classList.remove('show');nowRepId=0;}};
  reportSubmit.onclick = ()=>{
      let sel = reportSel.value;
      let cont = reportContent.value.trim();
if(!sel){showToast('请选择举报原因');return;}
      if(!cont){showToast('请填写举报详情');return;}
      reportOid.value = nowRepId;
      repReason.value = sel;
      repContent.value = cont;
      reportForm.submit();
  }
  function showToast(txt){
      toastTip.innerText = txt;
      toastTip.className = 'msg-toast open';
      setTimeout(()=>toastTip.classList.remove('open'),2300);
  }
  document.querySelectorAll('.tab-item').forEach(item=>{
      item.onclick = function(){
          let s = this.dataset.ser;
          let url = new URL(location.href);
          url.searchParams.delete('search_no');
          if(s==='')url.searchParams.delete('server_type');
          else url.searchParams.set('server_type',s);
          location.href = url.toString();
      }
  })
  </script>
  <?php require __DIR__ . '/footer.php'; ?>
      
<script src="../assets/js/loader-v2.js"></script>
</body>
  </html>