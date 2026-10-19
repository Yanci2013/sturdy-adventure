<?php
// 调试页面 - 检查新订单
echo "<h1>订单调试页面</h1>";

try {
    require __DIR__ . '/header.php';
    
    if (isset($pdo)) {
        echo "<h2>✅ 数据库连接成功</h2>";
        
        // 1. 显示最近20个订单
        echo "<h3>📋 最近20个订单：</h3>";
        $stmt = $pdo->query("SELECT order_no, product_name, order_status, create_time FROM orders ORDER BY create_time DESC LIMIT 20");
        $orders = $stmt->fetchAll();
        
        if (count($orders) > 0) {
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
            echo "<tr><th>订单号</th><th>商品名称</th><th>状态</th><th>创建时间</th></tr>";
            foreach ($orders as $order) {
                $statusText = '';
                switch ($order['order_status']) {
                    case 0: $statusText = '待支付'; break;
                    case 1: $statusText = '待接单'; break;
                    case 2: $statusText = '待服务'; break;
                    case 3: $statusText = '服务中'; break;
                    case 4: $statusText = '已完成'; break;
                    case 5: $statusText = '已取消'; break;
                    default: $statusText = '未知';
                }
                echo "<tr>";
                echo "<td>{$order['order_no']}</td>";
                echo "<td>{$order['product_name']}</td>";
                echo "<td>{$statusText} ({$order['order_status']})</td>";
                echo "<td>{$order['create_time']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>暂无订单</p>";
        }
        
        // 2. 检查最近10秒的订单
        echo "<h3>⏰ 最近10秒内的新订单：</h3>";
        $stmt2 = $pdo->prepare("SELECT order_no, product_name, create_time FROM orders WHERE create_time >= DATE_SUB(NOW(), INTERVAL 10 SECOND) ORDER BY create_time DESC");
        $stmt2->execute();
        $recentOrders = $stmt2->fetchAll();
        
        if (count($recentOrders) > 0) {
            echo "<p style='color: green; font-size: 18px;'>🎉 发现 " . count($recentOrders) . " 个新订单！</p>";
            echo "<pre>";
            print_r($recentOrders);
            echo "</pre>";
        } else {
            echo "<p>❌ 最近10秒内没有新订单</p>";
        }
        
        // 3. 显示当前时间
        echo "<h3>🕐 服务器当前时间：</h3>";
        echo "<p>" . date('Y-m-d H:i:s') . "</p>";
        
        // 4. 检查最近60秒的订单（修改后）
        echo "<h3>⏰ 最近60秒内的新订单：</h3>";
        $stmt4 = $pdo->prepare("SELECT order_no, product_name, create_time FROM orders WHERE create_time >= DATE_SUB(NOW(), INTERVAL 60 SECOND) ORDER BY create_time DESC");
        $stmt4->execute();
        $recentOrders2 = $stmt4->fetchAll();
        
        if (count($recentOrders2) > 0) {
            echo "<p style='color: green; font-size: 18px;'>🎉 发现 " . count($recentOrders2) . " 个新订单！</p>";
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
            echo "<tr><th>订单号</th><th>商品名称</th><th>创建时间</th></tr>";
            foreach ($recentOrders2 as $order) {
                echo "<tr>";
                echo "<td>{$order['order_no']}</td>";
                echo "<td>{$order['product_name']}</td>";
                echo "<td>{$order['create_time']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>❌ 最近60秒内没有新订单</p>";
        }
        
    } else {
        echo "<h2 style='color: red;'>❌ 数据库连接失败</h2>";
    }
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ 错误：" . $e->getMessage() . "</h2>";
    echo "<pre>";
    echo $e->getTraceAsString();
    echo "</pre>";
}
?>

