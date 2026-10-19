<?php
// 开启输出缓冲
ob_start();

// 引入数据库连接文件
require_once __DIR__.'/../../includes/common.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die('<div class="alert alert-danger">无效的提现ID</div>');
}

$sql = "SELECT w.*, u.username, u.email, u.phone 
        FROM withdrawals w 
        LEFT JOIN users u ON w.user_id = u.id 
        WHERE w.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$withdrawal) {
    die('<div class="alert alert-danger">提现记录不存在</div>');
}

// 状态中文映射
$statusText = [
    'pending' => '待处理',
    'completed' => '已完成',
    'rejected' => '已拒绝'
];

// 支付方式中文映射
$paymentMethodText = [
    'wechat' => '微信',
    'alipay' => '支付宝'
];

// 构建详情HTML
$html = '
<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h6 class="mb-0">基本信息</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <th width="120">提现ID</th>
                        <td>#' . $withdrawal['id'] . '</td>
                    </tr>
                    <tr>
                        <th>用户</th>
                        <td>' . htmlspecialchars($withdrawal['username'] ?? '用户已删除') . '</td>
                    </tr>
                    <tr>
                        <th>支付方式</th>
                        <td>' . $paymentMethodText[$withdrawal['payment_method']] . '</td>
                    </tr>
                    <tr>
                        <th>申请时间</th>
                        <td>' . date('Y-m-d H:i', strtotime($withdrawal['created_at'])) . '</td>
                    </tr>
                    <tr>
                        <th>处理时间</th>
                        <td>' . ($withdrawal['processed_at'] ? date('Y-m-d H:i', strtotime($withdrawal['processed_at'])) : '--') . '</td>
                    </tr>
                    <tr>
                        <th>状态</th>
                        <td><span class="badge bg-' . ($withdrawal['status'] == 'completed' ? 'success' : ($withdrawal['status'] == 'rejected' ? 'danger' : 'warning')) . '">' . $statusText[$withdrawal['status']] . '</span></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h6 class="mb-0">金额信息</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <th width="120">提现金额</th>
                        <td class="text-primary fw-bold">¥' . number_format($withdrawal['amount'], 2) . '</td>
                    </tr>
                    <tr>
                        <th>手续费</th>
                        <td class="text-danger">-¥' . number_format($withdrawal['fee'], 2) . '</td>
                    </tr>
                    <tr>
                        <th>实际到账</th>
                        <td class="text-success fw-bold">¥' . number_format($withdrawal['actual_amount'], 2) . '</td>
                    </tr>
                    <tr>
                        <th>交易ID</th>
                        <td>' . ($withdrawal['wechat_transaction_id'] ? htmlspecialchars($withdrawal['wechat_transaction_id']) : '--') . '</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-light">
        <h6 class="mb-0">收款信息</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr>
                        <th width="120">真实姓名</th>
                        <td>' . htmlspecialchars($withdrawal['real_name']) . '</td>
                    </tr>
                    <tr>
                        <th>' . $paymentMethodText[$withdrawal['payment_method']] . '账号</th>
                        <td>' . htmlspecialchars($withdrawal[$withdrawal['payment_method'] . '_account'] ?? '--') . '</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6 text-center">
                <h6>' . $paymentMethodText[$withdrawal['payment_method']] . '收款码</h6>';
                
if ($withdrawal['payment_method'] === 'wechat' && $withdrawal['wechat_qrcode']) {
    $html .= '<img src="/assets/images/user/' . htmlspecialchars($withdrawal['wechat_qrcode']) . '" class="img-fluid" style="max-height: 200px;">';
} elseif ($withdrawal['payment_method'] === 'alipay' && $withdrawal['alipay_qrcode']) {
    $html .= '<img src="/assets/images/user/' . htmlspecialchars($withdrawal['alipay_qrcode']) . '" class="img-fluid" style="max-height: 200px;">';
} else {
    $html .= '<div class="text-muted">无收款码</div>';
}

$html .= '
            </div>
        </div>
    </div>
</div>';

echo $html;
?>