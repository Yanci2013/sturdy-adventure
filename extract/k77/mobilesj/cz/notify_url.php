<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();
header('Content-Type: text/plain; charset=utf-8');

require_once("lib/epay.config.php");
require_once("lib/EpayCore.class.php");
require_once '../../includes/common.php';

$partner_id_from_url = $_GET['partner_id'] ?? null;
unset($_GET['partner_id']);

$epay = new EpayCore($epay_config);
$verify_result = $epay->verifyNotify();

if ($partner_id_from_url) {
    $_GET['partner_id'] = $partner_id_from_url;
}

if($verify_result) {
    $out_trade_no = $_GET['out_trade_no'] ?? '';
    $trade_no = $_GET['trade_no'] ?? '';
    $trade_status = $_GET['trade_status'] ?? '';
    $type = $_GET['type'] ?? '';
    $money = floatval($_GET['money'] ?? 0);

    if ($trade_status == 'TRADE_SUCCESS') {
        try {
            global $pdo;
            
            if (!($pdo instanceof PDO)) {
                throw new Exception("数据库连接未初始化");
            }
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("SELECT id FROM fund_details WHERE order_no = ?");
            $stmt->execute([$out_trade_no]);
            if ($stmt->fetch()) {
                $pdo->commit();
                ob_end_clean();
                echo "success";
                exit;
            }
            
            $partner_id = null;
            
            if (strpos($out_trade_no, '_') !== false) {
                $parts = explode('_', $out_trade_no);
                $partner_id = $parts[0];
            }
            
            if (empty($partner_id)) {
                $partner_id = $_POST['partner_id'] ?? $_GET['partner_id'] ?? null;
            }
            
            if (empty($partner_id)) {
                throw new Exception("无法确定商家ID，订单号: {$out_trade_no}");
            }
            
            $stmt = $pdo->prepare("SELECT id, name FROM partners WHERE id = ?");
            $stmt->execute([$partner_id]);
            $partner = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$partner) {
                throw new Exception("商家不存在: {$partner_id}");
            }
            
            $stmt = $pdo->prepare("UPDATE partners SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$money, $partner_id]);
            
            if ($stmt->rowCount() == 0) {
                throw new Exception("更新商家账户余额失败，商家ID: {$partner_id}");
            }
            
            $stmt = $pdo->prepare("INSERT INTO fund_details 
                                (partner_id, source, destination, amount, order_no, note, create_time)
                                VALUES (?, 'recharge', 'partner_balance', ?, ?, '商家账户余额充值', NOW())");
            $stmt->execute([
                $partner_id,
                $money,
                $out_trade_no
            ]);
            
            $pdo->commit();
            
            error_log("商家余额充值成功: 商家ID {$partner_id} 充值 {$money}元，订单号 {$out_trade_no}");
            
            ob_end_clean();
            echo "success";
            exit;
            
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("处理商家余额充值回调时出错: " . $e->getMessage());
            error_log("订单号: {$out_trade_no}, 金额: {$money}, 错误: " . $e->getMessage());
            ob_end_clean();
            echo "fail";
            exit;
        }
    } else {
        ob_end_clean();
        echo "fail";
        exit;
    }
} else {
    ob_end_clean();
    echo "fail";
    exit;
}
