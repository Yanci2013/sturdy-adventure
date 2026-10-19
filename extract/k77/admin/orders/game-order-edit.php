<?php
// 开启输出缓冲
ob_start();

// 引入数据库连接文件
require_once __DIR__.'/../../includes/common.php';

// 开启会话
session_start();

// 检查订单ID参数
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("无效的订单ID");
}

$order_id = intval($_GET['id']);

// 获取订单信息
try {
    $stmt = $pdo->prepare("SELECT * FROM game_orders WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        die("订单不存在");
    }
    
    // 获取用户余额信息
    $user_stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $user_stmt->execute([$order['user_id']]);
    $user_balance = $user_stmt->fetchColumn();
    
    // 获取发布者余额信息
    $publisher_stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $publisher_stmt->execute([$order['publisher_id']]);
    $publisher_balance = $publisher_stmt->fetchColumn();
    
} catch (PDOException $e) {
    die("数据库查询失败: " . $e->getMessage());
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 获取表单数据
    $order_status = intval($_POST['order_status']);
    $final_price = floatval($_POST['final_price']);
    $payment_method = $_POST['payment_method'];
    $notes = $_POST['notes'];
    $user_balance_adjust = floatval($_POST['user_balance_adjust']);
    $publisher_balance_adjust = floatval($_POST['publisher_balance_adjust']);
    
    try {
        // 开始事务
        $pdo->beginTransaction();
        
        // 更新订单信息
        $update_stmt = $pdo->prepare("UPDATE game_orders SET 
            order_status = ?, 
            final_price = ?, 
            payment_method = ?, 
            notes = ?, 
            updated_at = NOW() 
            WHERE order_id = ?");
        
        $update_stmt->execute([
            $order_status,
            $final_price,
            $payment_method,
            $notes,
            $order_id
        ]);
        
        // 调整用户余额
        if ($user_balance_adjust != 0) {
            $adjust_user_stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $adjust_user_stmt->execute([$user_balance_adjust, $order['user_id']]);
            
            // 记录余额变动日志
            $log_user_stmt = $pdo->prepare("INSERT INTO balance_logs 
                (user_id, order_id, amount, balance_before, balance_after, type, description, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $new_user_balance = $user_balance + $user_balance_adjust;
            $log_user_stmt->execute([
                $order['user_id'],
                $order_id,
                $user_balance_adjust,
                $user_balance,
                $new_user_balance,
                'adjustment',
                '管理员手动调整余额'
            ]);
        }
        
        // 调整发布者余额
        if ($publisher_balance_adjust != 0) {
            $adjust_publisher_stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $adjust_publisher_stmt->execute([$publisher_balance_adjust, $order['publisher_id']]);
            
            // 记录余额变动日志
            $log_publisher_stmt = $pdo->prepare("INSERT INTO balance_logs 
                (user_id, order_id, amount, balance_before, balance_after, type, description, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $new_publisher_balance = $publisher_balance + $publisher_balance_adjust;
            $log_publisher_stmt->execute([
                $order['publisher_id'],
                $order_id,
                $publisher_balance_adjust,
                $publisher_balance,
                $new_publisher_balance,
                'adjustment',
                '管理员手动调整余额'
            ]);
        }
        
        // 提交事务
        $pdo->commit();
        
        // 刷新页面获取最新数据
        header("Location: game-order-edit.php?id=" . $order_id);
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        die("更新失败: " . $e->getMessage());
    }
}

// 状态映射
$statusText = [
    0 => '待支付',
    1 => '待发货',
    2 => '待确认',
    3 => '已完成',
    4 => '已取消',
    5 => '已退款'
];

// 页面标题
$pageTitle = '编辑订单 - ' . $order['order_no'];
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title><?php echo $pageTitle; ?></title>
    <link href="../res/layui/css/layui.css" rel="stylesheet">
    <link href="../res/adminui/dist/css/admin.css" rel="stylesheet">
    <style>
        .order-edit-container {
            padding: 20px;
        }
        
        .order-info-section {
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
        }
        
        .order-info-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }
        
        .balance-adjust-section {
            background-color: #f9f9f9;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .balance-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .balance-card {
            width: 48%;
            padding: 15px;
            border-radius: 5px;
            background-color: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .balance-title {
            font-weight: bold;
            margin-bottom: 10px;
            color: #666;
        }
        
        .balance-amount {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        
        .balance-adjust-form {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }
        
        .balance-adjust-input {
            flex: 1;
        }
        
        .form-submit-btn {
            margin-top: 20px;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .balance-info {
                flex-direction: column;
            }
            
            .balance-card {
                width: 100%;
                margin-bottom: 15px;
            }
            
            .balance-adjust-form {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
<div class="layui-fluid">
    <div class="layui-row layui-col-space15">
        <div class="layui-col-md12">
            <div class="layui-card">
                <div class="layui-card-header">
                    <h2><?php echo $pageTitle; ?></h2>
                </div>
                
                <div class="layui-card-body order-edit-container">
                    <form class="layui-form" method="POST">
                        <!-- 订单基本信息 -->
                        <div class="order-info-section">
                            <div class="order-info-title">订单基本信息</div>
                            <div class="layui-form-item">
                                <label class="layui-form-label">订单状态</label>
                                <div class="layui-input-block">
                                    <select name="order_status" lay-verify="required">
                                        <?php foreach ($statusText as $key => $text): ?>
                                            <option value="<?php echo $key; ?>" <?php echo $order['order_status'] == $key ? 'selected' : ''; ?>>
                                                <?php echo $text; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="layui-form-item">
                                <label class="layui-form-label">最终价格</label>
                                <div class="layui-input-block">
                                    <input type="number" name="final_price" class="layui-input" value="<?php echo $order['final_price']; ?>" step="0.01" min="0" lay-verify="required|number">
                                </div>
                            </div>
                            
                            <div class="layui-form-item">
                                <label class="layui-form-label">支付方式</label>
                                <div class="layui-input-block">
                                    <select name="payment_method">
                                        <option value="">请选择支付方式</option>
                                        <option value="wxpay" <?php echo $order['payment_method'] == 'wxpay' ? 'selected' : ''; ?>>微信支付</option>
                                        <option value="alipay" <?php echo $order['payment_method'] == 'alipay' ? 'selected' : ''; ?>>支付宝</option>
                                        <option value="qqpay" <?php echo $order['payment_method'] == 'qqpay' ? 'selected' : ''; ?>>QQ支付</option>
                                        <option value="bank" <?php echo $order['payment_method'] == 'bank' ? 'selected' : ''; ?>>银行卡</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="layui-form-item">
                                <label class="layui-form-label">备注信息</label>
                                <div class="layui-input-block">
                                    <textarea name="notes" class="layui-textarea" placeholder="请输入备注信息"><?php echo htmlspecialchars($order['notes'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 余额调整 -->
                        <div class="balance-adjust-section">
                            <div class="order-info-title">余额调整</div>
                            
                            <div class="balance-info">
                                <div class="balance-card">
                                    <div class="balance-title">买家余额</div>
                                    <div class="balance-amount">¥<?php echo number_format($user_balance, 2); ?></div>
                                    <div class="balance-adjust-form">
                                        <div class="balance-adjust-input">
                                            <input type="number" name="user_balance_adjust" class="layui-input" placeholder="调整金额" step="0.01">
                                        </div>
                                        <div>
                                            <button type="button" class="layui-btn layui-btn-primary" onclick="document.getElementsByName('user_balance_adjust')[0].value = ''">清空</button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="balance-card">
                                    <div class="balance-title">卖家余额</div>
                                    <div class="balance-amount">¥<?php echo number_format($publisher_balance, 2); ?></div>
                                    <div class="balance-adjust-form">
                                        <div class="balance-adjust-input">
                                            <input type="number" name="publisher_balance_adjust" class="layui-input" placeholder="调整金额" step="0.01">
                                        </div>
                                        <div>
                                            <button type="button" class="layui-btn layui-btn-primary" onclick="document.getElementsByName('publisher_balance_adjust')[0].value = ''">清空</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="layui-form-item">
                                <label class="layui-form-label">调整说明</label>
                                <div class="layui-input-block">
                                    <input type="text" name="adjust_reason" class="layui-input" placeholder="请输入调整原因">
                                </div>
                            </div>
                        </div>
                        
                        <!-- 提交按钮 -->
                        <div class="form-submit-btn">
                            <button type="submit" class="layui-btn layui-btn-normal">保存更改</button>
                            <a href="game-orders.php" class="layui-btn layui-btn-primary">返回列表</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../res/layui/layui.js"></script>
<script>
layui.use(['form'], function(){
    var form = layui.form;
    
    // 表单验证
    form.verify({
        number: function(value, item){ //value：表单的值、item：表单的DOM对象
            if(!/^-?\d+(\.\d+)?$/.test(value)){
                return '请输入正确的数字';
            }
        }
    });
});
</script>
</body>
</html>