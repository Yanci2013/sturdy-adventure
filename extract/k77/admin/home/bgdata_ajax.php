<?php
header('Content-Type: application/json; charset=utf-8');

// 数据库配置
$db_host = 'localhost';
$db_name = 'sydj_bcorn_cn';
$db_user = 'sydj_bcorn_cn';
$db_pass = 'BdB7s2j5ZaJzXWFC';

// 错误处理函数
function handleError($message, $code = 50000) {
    http_response_code(500);
    echo json_encode([
        'code' => $code,
        'message' => $message,
        'data' => null
    ]);
    exit;
}

try {
    // 创建PDO连接
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // 获取今日日期和昨日日期
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    // 1. 获取今日收款总额和订单数
    $stmt = $pdo->prepare("
        SELECT 
            SUM(payment_amount) as today_sum,
            COUNT(*) as today_paid
        FROM orders 
        WHERE DATE(payment_time) = :today 
        AND payment_status = 'paid'
    ");
    $stmt->execute([':today' => $today]);
    $todayData = $stmt->fetch();
    
    // 2. 获取昨日收款总额和订单数
    $stmt = $pdo->prepare("
        SELECT 
            SUM(payment_amount) as yester_sum,
            COUNT(*) as yester_paid
        FROM orders 
        WHERE DATE(payment_time) = :yesterday 
        AND payment_status = 'paid'
    ");
    $stmt->execute([':yesterday' => $yesterday]);
    $yesterdayData = $stmt->fetch();
    
    // 3. 获取平台总收款
    $stmt = $pdo->prepare("
        SELECT SUM(payment_amount) as all_sum
        FROM orders 
        WHERE payment_status = 'paid'
    ");
    $stmt->execute();
    $allSum = $stmt->fetchColumn();
    
    // 4. 获取今日和昨日注册用户数
    $todayUcount = 0;
    $yesterUcount = 0;
    
    // 5. 获取待审核商户数
    $notpassUcount = 0;
    
    // 6. 获取今日和昨日申请提现金额
    $stmt = $pdo->prepare("
        SELECT 
            SUM(amount) as today_applycash
        FROM withdrawals 
        WHERE DATE(created_at) = :today
    ");
    $stmt->execute([':today' => $today]);
    $todayApplycash = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("
        SELECT 
            SUM(amount) as yester_applycash
        FROM withdrawals 
        WHERE DATE(created_at) = :yesterday
    ");
    $stmt->execute([':yesterday' => $yesterday]);
    $yesterApplycash = $stmt->fetchColumn();
    
    // 7. 获取今日和昨日提现成功金额
    $stmt = $pdo->prepare("
        SELECT 
            SUM(amount) as today_cashed
        FROM withdrawals 
        WHERE DATE(processed_at) = :today
        AND status = 'completed'
    ");
    $stmt->execute([':today' => $today]);
    $todayCashed = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("
        SELECT 
            SUM(amount) as yester_cashed
        FROM withdrawals 
        WHERE DATE(processed_at) = :yesterday
        AND status = 'completed'
    ");
    $stmt->execute([':yesterday' => $yesterday]);
    $yesterCashed = $stmt->fetchColumn();
    
    // 8. 获取今日未付款订单数
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as today_unpaid
        FROM orders 
        WHERE DATE(create_time) = :today 
        AND payment_status = 'pending'
    ");
    $stmt->execute([':today' => $today]);
    $todayUnpaid = $stmt->fetchColumn();
    
    // 9. 获取总冻结用户数
    $isFreezeUcount = 0;
    
    // 10. 获取今日支付通道分布
    $stmt = $pdo->prepare("
        SELECT 
            payment_type as name,
            COUNT(*) as value
        FROM orders 
        WHERE DATE(payment_time) = :today 
        AND payment_status = 'paid'
        GROUP BY payment_type
    ");
    $stmt->execute([':today' => $today]);
    $paymentChannels = $stmt->fetchAll();
    
    // 11. 获取近30日收款统计
    $startDate = date('Y-m-d', strtotime('-30 days'));
    $stmt = $pdo->prepare("
        SELECT 
            DATE(payment_time) as date,
            SUM(payment_amount) as amount
        FROM orders 
        WHERE payment_time >= :start_date 
        AND payment_status = 'paid'
        GROUP BY DATE(payment_time)
        ORDER BY DATE(payment_time)
    ");
    $stmt->execute([':start_date' => $startDate]);
    $monthlyStats = $stmt->fetchAll();
    
    // 处理近30日数据
    $dates = [];
    $values = [];
    foreach ($monthlyStats as $stat) {
        $dates[] = date('j日', strtotime($stat['date']));
        $values[] = floatval($stat['amount']);
    }
    
    // 12. 获取年度收款统计
    $year = date('Y');
    $stmt = $pdo->prepare("
        SELECT 
            MONTH(payment_time) as month,
            SUM(payment_amount) as amount
        FROM orders 
        WHERE YEAR(payment_time) = :year 
        AND payment_status = 'paid'
        GROUP BY MONTH(payment_time)
        ORDER BY MONTH(payment_time)
    ");
    $stmt->execute([':year' => $year]);
    $yearlyStats = $stmt->fetchAll();
    
    // 处理年度数据
    $months = [];
    $yearValues = [];
    for ($i = 1; $i <= 12; $i++) {
        $months[] = $i . '月';
        $found = false;
        foreach ($yearlyStats as $stat) {
            if ($stat['month'] == $i) {
                $yearValues[] = floatval($stat['amount']);
                $found = true;
                break;
            }
        }
        if (!$found) {
            $yearValues[] = 0;
        }
    }
    
    // 13. 获取今日商户流水排行
    $stmt = $pdo->prepare("
        SELECT 
            fadan_id as merchant_id,
            fadan_name as merchant_name,
            SUM(payment_amount) as amount,
            COUNT(*) as order_count
        FROM orders 
        WHERE DATE(payment_time) = :today 
        AND payment_status = 'paid'
        AND fadan_id != 1  -- 排除系统发单
        GROUP BY fadan_id, fadan_name
        ORDER BY amount DESC
        LIMIT 10
    ");
    $stmt->execute([':today' => $today]);
    $dayTopStatis = $stmt->fetchAll();
    
    // 14. 获取月度商户流水排行
    $stmt = $pdo->prepare("
        SELECT 
            fadan_id as merchant_id,
            fadan_name as merchant_name,
            SUM(payment_amount) as amount,
            COUNT(*) as order_count
        FROM orders 
        WHERE MONTH(payment_time) = MONTH(CURRENT_DATE())
        AND YEAR(payment_time) = YEAR(CURRENT_DATE())
        AND payment_status = 'paid'
        AND fadan_id != 1  -- 排除系统发单
        GROUP BY fadan_id, fadan_name
        ORDER BY amount DESC
        LIMIT 10
    ");
    $stmt->execute();
    $monthTopStatis = $stmt->fetchAll();
    
    // 15. 获取总流水排行
    $stmt = $pdo->prepare("
        SELECT 
            fadan_id as merchant_id,
            fadan_name as merchant_name,
            SUM(payment_amount) as amount,
            COUNT(*) as order_count
        FROM orders 
        WHERE payment_status = 'paid'
        AND fadan_id != 1  -- 排除系统发单
        GROUP BY fadan_id, fadan_name
        ORDER BY amount DESC
        LIMIT 10
    ");
    $stmt->execute();
    $allTopStatis = $stmt->fetchAll();
    
    // 16. 获取全国交易额分布
    $stmt = $pdo->prepare("
        SELECT 
            payment_ip,
            SUM(payment_amount) as amount
        FROM orders 
        WHERE payment_status = 'paid'
        AND payment_ip IS NOT NULL
        AND payment_ip != ''
        GROUP BY payment_ip
    ");
    $stmt->execute();
    $ipAmounts = $stmt->fetchAll();
    
    $mapData = [];
    $ipCache = [];
    $ipsToQuery = [];
    
    // 第一步：收集所有需要查询的IP
    foreach ($ipAmounts as $item) {
        $ip = trim($item['payment_ip']);
        $amount = floatval($item['amount']);
        
        if (empty($ip) || $ip == '127.0.0.1') {
            continue;
        }
        
        // 如果IP不在缓存中，加入待查询列表
        if (!isset($ipCache[$ip])) {
            $ipsToQuery[$ip] = $amount;
        } else {
            // 如果IP已在缓存中，直接使用缓存结果
            $province = $ipCache[$ip];
            if ($province && $province != '未知') {
                updateMapData($mapData, $province, $amount);
            }
        }
    }
    
    // 第二步：批量查询IP信息（每次最多查询50个IP）
    if (!empty($ipsToQuery)) {
        $ipChunks = array_chunk(array_keys($ipsToQuery), 50, true);
        
        foreach ($ipChunks as $chunk) {
            $batchResults = getBatchProvinceByIp($chunk);
            
            // 处理批量查询结果
            foreach ($chunk as $ip) {
                $amount = $ipsToQuery[$ip];
                $province = $batchResults[$ip] ?? '未知';
                
                // 更新缓存
                $ipCache[$ip] = $province;
                
                if ($province && $province != '未知') {
                    updateMapData($mapData, $province, $amount);
                }
            }
        }
    }
    
    // 确保所有省份都有数据
    $allProvinces = getChinaProvinces();
    foreach ($allProvinces as $province) {
        $found = false;
        foreach ($mapData as $data) {
            if ($data['name'] == $province) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $mapData[] = [
                'name' => $province,
                'value' => 0
            ];
        }
    }
    
    // 17. 获取提现统计数据
    $stmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as count,
            SUM(amount) as total_amount
        FROM withdrawals
        GROUP BY status
    ");
    $stmt->execute();
    $withdrawalStats = $stmt->fetchAll();
    
    // 处理提现统计数据
    $withdrawalData = [
        'pending' => ['count' => 0, 'amount' => 0],
        'processing' => ['count' => 0, 'amount' => 0],
        'completed' => ['count' => 0, 'amount' => 0],
        'failed' => ['count' => 0, 'amount' => 0],
        'rejected' => ['count' => 0, 'amount' => 0]
    ];
    
    foreach ($withdrawalStats as $stat) {
        $status = $stat['status'];
        $withdrawalData[$status] = [
            'count' => (int)$stat['count'],
            'amount' => (float)$stat['total_amount']
        ];
    }
    
    // 18. 获取最近7天提现趋势
    $startDate7 = date('Y-m-d', strtotime('-7 days'));
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as count,
            SUM(amount) as amount
        FROM withdrawals
        WHERE created_at >= :start_date
        GROUP BY DATE(created_at)
        ORDER BY DATE(created_at)
    ");
    $stmt->execute([':start_date' => $startDate7]);
    $withdrawalTrend = $stmt->fetchAll();
    
    // 处理提现趋势数据
    $withdrawalDates = [];
    $withdrawalCounts = [];
    $withdrawalAmounts = [];
    
    for ($i = 0; $i < 7; $i++) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $found = false;
        
        foreach ($withdrawalTrend as $trend) {
            if ($trend['date'] == $date) {
                $withdrawalDates[] = date('m-d', strtotime($date));
                $withdrawalCounts[] = (int)$trend['count'];
                $withdrawalAmounts[] = (float)$trend['amount'];
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $withdrawalDates[] = date('m-d', strtotime($date));
            $withdrawalCounts[] = 0;
            $withdrawalAmounts[] = 0;
        }
    }
    
    // 反转数组，使日期按时间顺序排列
    $withdrawalDates = array_reverse($withdrawalDates);
    $withdrawalCounts = array_reverse($withdrawalCounts);
    $withdrawalAmounts = array_reverse($withdrawalAmounts);
    
    // 19. 获取提现方式分布
    $stmt = $pdo->prepare("
        SELECT 
            payment_method,
            COUNT(*) as count,
            SUM(amount) as amount
        FROM withdrawals
        GROUP BY payment_method
    ");
    $stmt->execute();
    $withdrawalMethods = $stmt->fetchAll();
    
    // 构建返回数据
    $response = [
        'code' => 5000,
        'message' => 'success',
        'data' => [
            'today_sum' => $todayData['today_sum'] ? number_format($todayData['today_sum'], 2) : '0',
            'today_paid' => $todayData['today_paid'] ?? '0',
            'yester_sum' => $yesterdayData['yester_sum'] ? number_format($yesterdayData['yester_sum'], 2) : '0',
            'yester_paid' => $yesterdayData['yester_paid'] ?? '0',
            'today_ucount' => $todayUcount,
            'yester_ucount' => $yesterUcount,
            'all_sum' => $allSum ? number_format($allSum, 2) : '0',
            'notpass_ucount' => $notpassUcount,
            'today_applycash' => $todayApplycash ? number_format($todayApplycash, 2) : '0',
            'yester_applycash' => $yesterApplycash ? number_format($yesterApplycash, 2) : '0',
            'today_cashed' => $todayCashed ? number_format($todayCashed, 2) : '0',
            'yester_cashed' => $yesterCashed ? number_format($yesterCashed, 2) : '0',
            'today_unpaid' => $todayUnpaid,
            'is_freeze_ucount' => $isFreezeUcount,
            'payment_channels' => $paymentChannels,
            'monthly_stats' => [
                'dates' => $dates,
                'values' => $values
            ],
            'yearly_stats' => [
                'months' => $months,
                'values' => $yearValues
            ],
            'day_top_statis' => $dayTopStatis,
            'month_top_statis' => $monthTopStatis,
            'all_top_statis' => $allTopStatis,
            'map_data' => $mapData,
            'withdrawal_stats' => [
                'status' => $withdrawalData,
                'trend' => [
                    'dates' => $withdrawalDates,
                    'counts' => $withdrawalCounts,
                    'amounts' => $withdrawalAmounts
                ],
                'methods' => $withdrawalMethods
            ]
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    handleError('数据库连接失败: ' . $e->getMessage());
} catch (Exception $e) {
    handleError('服务器错误: ' . $e->getMessage());
}

/**
 * 批量获取IP省份信息
 */
function getBatchProvinceByIp($ips) {
    if (empty($ips)) {
        return [];
    }
    
    $results = [];
    $url = "https://ip9.com.cn/batch"; // 假设API支持批量查询
    
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['ips' => $ips]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            error_log("批量IP查询CURL错误: " . curl_error($ch));
            // 如果批量查询失败，回退到单IP查询
            foreach ($ips as $ip) {
                $results[$ip] = getSingleProvinceByIp($ip);
            }
            return $results;
        }
        
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['ret']) || $data['ret'] != 200) {
            error_log("批量IP查询API返回异常: " . $response);
            // 如果批量查询失败，回退到单IP查询
            foreach ($ips as $ip) {
                $results[$ip] = getSingleProvinceByIp($ip);
            }
            return $results;
        }
        
        // 处理批量返回结果
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $item) {
                if (isset($item['ip']) && isset($item['prov'])) {
                    $results[$item['ip']] = $item['prov'];
                }
            }
        }
        
        // 确保所有请求的IP都有结果
        foreach ($ips as $ip) {
            if (!isset($results[$ip])) {
                $results[$ip] = '未知';
            }
        }
        
        return $results;
        
    } catch (Exception $e) {
        error_log("批量IP查询异常: " . $e->getMessage());
        // 如果批量查询失败，回退到单IP查询
        foreach ($ips as $ip) {
            $results[$ip] = getSingleProvinceByIp($ip);
        }
        return $results;
    }
}

/**
 * 单IP获取省份信息（回退方法）
 */
function getSingleProvinceByIp($ip) {
    if (empty($ip) || $ip == '127.0.0.1') {
        return '未知';
    }
    
    $url = "https://ip9.com.cn/get?ip=" . urlencode($ip);
    
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            error_log("单IP查询CURL错误: " . curl_error($ch) . " IP: {$ip}");
            return '未知';
        }
        
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['ret']) || $data['ret'] != 200) {
            error_log("单IP查询API返回异常: " . $response . " IP: {$ip}");
            return '未知';
        }
        
        if (isset($data['data']['prov'])) {
            return $data['data']['prov'];
        }
        
        error_log("单IP查询缺少prov字段: " . $response . " IP: {$ip}");
        return '未知';
        
    } catch (Exception $e) {
        error_log("单IP查询异常: " . $e->getMessage() . " IP: {$ip}");
        return '未知';
    }
}

/**
 * 更新地图数据
 */
function updateMapData(&$mapData, $province, $amount) {
    $province = standardizeProvinceName($province);
    
    $found = false;
    foreach ($mapData as &$data) {
        if ($data['name'] == $province) {
            $data['value'] += $amount;
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $mapData[] = [
            'name' => $province,
            'value' => $amount
        ];
    }
}

/**
 * 标准化省份名称
 */
function standardizeProvinceName($province) {
    // 统一去掉后缀
    $province = preg_replace('/省|市|自治区|特别行政区/', '', $province);
    
    // 处理特殊地区
    $specialRegions = [
        '内蒙古' => '内蒙古',
        '广西' => '广西',
        '西藏' => '西藏',
        '宁夏' => '宁夏',
        '新疆' => '新疆',
        '香港' => '香港',
        '澳门' => '澳门'
    ];
    
    return $specialRegions[$province] ?? $province;
}

/**
 * 获取中国所有省份列表
 */
function getChinaProvinces() {
    return [
        '北京', '天津', '上海', '重庆', '河北', '山西', '辽宁', '吉林', '黑龙江',
        '江苏', '浙江', '安徽', '福建', '江西', '山东', '河南', '湖北', '湖南',
        '广东', '海南', '四川', '贵州', '云南', '陕西', '甘肃', '青海', '台湾',
        '内蒙古', '广西', '西藏', '宁夏', '新疆', '香港', '澳门'
    ];
}