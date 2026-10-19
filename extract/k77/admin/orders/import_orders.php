<?php
ob_start();
require_once __DIR__.'/../../includes/common.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $mode = $_POST['mode'] ?? 'insert';
    
    // 检查文件是否上传成功
    if ($file['error'] !== UPLOAD_ERR_OK) {
        die("文件上传失败: " . $file['error']);
    }
    
    // 检查文件类型
    $allowed_extensions = ['csv', 'xlsx', 'xls'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_extensions)) {
        die("不支持的文件类型，请上传CSV或Excel文件");
    }
    
    try {
        $orders = [];
        
        if ($file_extension === 'csv') {
            // 处理CSV文件
            $handle = fopen($file['tmp_name'], 'r');
            if ($handle !== false) {
                // 读取表头
                $headers = fgetcsv($handle);
                
                // 读取数据行
                while (($row = fgetcsv($handle)) !== false) {
                    $order = [];
                    foreach ($headers as $index => $header) {
                        $order[strtolower(str_replace(' ', '_', $header))] = $row[$index] ?? '';
                    }
                    $orders[] = $order;
                }
                fclose($handle);
            }
        } else {
            // 处理Excel文件
            require_once __DIR__.'/../../includes/PHPExcel.php';
            
            $reader = PHPExcel_IOFactory::createReaderForFile($file['tmp_name']);
            $excel = $reader->load($file['tmp_name']);
            $sheet = $excel->getActiveSheet();
            
            // 获取表头
            $headers = [];
            $header_row = 1;
            $cell = $sheet->getCellByColumnAndRow(0, $header_row);
            $column = 0;
            while ($cell->getValue() !== null) {
                $headers[] = $cell->getValue();
                $column++;
                $cell = $sheet->getCellByColumnAndRow($column, $header_row);
            }
            
            // 读取数据行
            $row = 2;
            while (true) {
                $cell = $sheet->getCellByColumnAndRow(0, $row);
                if ($cell->getValue() === null) {
                    break;
                }
                
                $order = [];
                foreach ($headers as $index => $header) {
                    $cell = $sheet->getCellByColumnAndRow($index, $row);
                    $order[strtolower(str_replace(' ', '_', $header))] = $cell->getValue() ?? '';
                }
                $orders[] = $order;
                $row++;
            }
        }
        
        // 处理导入的数据
        foreach ($orders as $order) {
            // 验证必要字段
            if (!isset($order['order_no']) || empty($order['order_no'])) {
                continue;
            }
            
            // 检查订单是否已存在
            $stmt = $pdo->prepare("SELECT id FROM orders WHERE order_no = :order_no");
            $stmt->bindValue(':order_no', $order['order_no']);
            $stmt->execute();
            $existing_order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_order) {
                // 订单已存在，根据模式处理
                if ($mode === 'update') {
                    // 更新现有订单
                    $update_data = [];
                    $update_params = [':order_no' => $order['order_no']];
                    
                    if (isset($order['product_name'])) {
                        $update_data[] = "product_name = :product_name";
                        $update_params[':product_name'] = $order['product_name'];
                    }
                    if (isset($order['price'])) {
                        $update_data[] = "price = :price";
                        $update_params[':price'] = $order['price'];
                    }
                    if (isset($order['order_status'])) {
                        $update_data[] = "order_status = :order_status";
                        $update_params[':order_status'] = $order['order_status'];
                    }
                    if (isset($order['payment_status'])) {
                        $update_data[] = "payment_status = :payment_status";
                        $update_params[':payment_status'] = $order['payment_status'];
                    }
                    
                    if (!empty($update_data)) {
                        $update_sql = "UPDATE orders SET " . implode(', ', $update_data) . " WHERE order_no = :order_no";
                        $stmt = $pdo->prepare($update_sql);
                        $stmt->execute($update_params);
                    }
                } elseif ($mode === 'replace') {
                    // 替换现有订单
                    $delete_stmt = $pdo->prepare("DELETE FROM orders WHERE order_no = :order_no");
                    $delete_stmt->bindValue(':order_no', $order['order_no']);
                    $delete_stmt->execute();
                    
                    // 插入新订单
                    $insert_stmt = $pdo->prepare("INSERT INTO orders (order_no, product_name, price, order_status, payment_status, create_time) VALUES (:order_no, :product_name, :price, :order_status, :payment_status, NOW())");
                    $insert_stmt->bindValue(':order_no', $order['order_no']);
                    $insert_stmt->bindValue(':product_name', $order['product_name'] ?? '');
                    $insert_stmt->bindValue(':price', $order['price'] ?? 0);
                    $insert_stmt->bindValue(':order_status', $order['order_status'] ?? 1);
                    $insert_stmt->bindValue(':payment_status', $order['payment_status'] ?? 'pending');
                    $insert_stmt->execute();
                }
            } else {
                // 订单不存在，插入新订单
                $insert_stmt = $pdo->prepare("INSERT INTO orders (order_no, product_name, price, order_status, payment_status, create_time) VALUES (:order_no, :product_name, :price, :order_status, :payment_status, NOW())");
                $insert_stmt->bindValue(':order_no', $order['order_no']);
                $insert_stmt->bindValue(':product_name', $order['product_name'] ?? '');
                $insert_stmt->bindValue(':price', $order['price'] ?? 0);
                $insert_stmt->bindValue(':order_status', $order['order_status'] ?? 1);
                $insert_stmt->bindValue(':payment_status', $order['payment_status'] ?? 'pending');
                $insert_stmt->execute();
            }
        }
        
        echo "订单数据导入成功！";
        header('Location: orders.php');
        exit;
        
    } catch (Exception $e) {
        die("导入失败: " . $e->getMessage());
    }
} else {
    die("无效的请求");
}
?>