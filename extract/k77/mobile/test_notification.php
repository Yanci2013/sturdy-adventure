<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新订单通知测试</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
        }
        .status {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
        }
        .status.info {
            background: #d1ecf1;
            color: #0c5460;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
        }
        button:hover {
            background: #0056b3;
        }
        .log {
            background: #333;
            color: #0f0;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            max-height: 400px;
            overflow-y: auto;
            margin-top: 20px;
        }
        .log-line {
            margin: 5px 0;
        }
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

    <div class="container">
        <h1>🔔 新订单通知测试</h1>
        
        <div class="status info" id="status">
            准备就绪...
        </div>
        
        <button onclick="testCheck()">🔍 手动检测一次</button>
        <button onclick="testNotification()">🔔 测试通知</button>
        <button onclick="startAutoCheck()" id="autoBtn">▶️ 启动自动检测（每5秒）</button>
        <button onclick="stopAutoCheck()">⏹️ 停止自动检测</button>
        
        <div class="log" id="log"></div>
    </div>

    <script>
        // 全局变量
        var lastDetectedOrderNo = null;
        var autoCheckInterval = null;
        var isAutoChecking = false;

        // 日志函数
        function log(message, type = 'info') {
            var logDiv = document.getElementById('log');
            var time = new Date().toLocaleTimeString();
            var prefix = '';
            
            switch(type) {
                case 'success': prefix = '✅ '; break;
                case 'error': prefix = '❌ '; break;
                case 'warning': prefix = '⚠️ '; break;
                case 'info': prefix = 'ℹ️ '; break;
            }
            
            var line = document.createElement('div');
            line.className = 'log-line';
            line.textContent = '[' + time + '] ' + prefix + message;
            logDiv.appendChild(line);
            logDiv.scrollTop = logDiv.scrollHeight;
        }

        // 更新状态
        function updateStatus(text, type = 'info') {
            var statusDiv = document.getElementById('status');
            statusDiv.className = 'status ' + type;
            statusDiv.textContent = text;
        }

        // 发送通知
        function sendNotification(title, content) {
            log('准备发送通知: ' + title + ' - ' + content, 'info');
            
            // 方式1: plus.push（模拟APP）
            if (typeof plus !== 'undefined' && plus.push) {
                try {
                    plus.push.createMessage(content, '', {
                        title: title,
                        cover: false,
                        when: Date.now()
                    });
                    log('plus.push 通知已发送', 'success');
                    return;
                } catch (e) {
                    log('plus.push 失败: ' + e.message, 'error');
                }
            } else {
                log('不是APP环境（没有plus对象）', 'warning');
            }
            
            // 方式2: HTML5 Notification
            if ('Notification' in window) {
                if (Notification.permission === 'granted') {
                    var notification = new Notification(title, {
                        body: content,
                        icon: 'https://sxjiyuan.top/favicon.ico'
                    });
                    log('HTML5 通知已发送', 'success');
                    
                    notification.onclick = function() {
                        window.focus();
                        notification.close();
                    };
                } else if (Notification.permission !== 'denied') {
                    Notification.requestPermission().then(function(permission) {
                        if (permission === 'granted') {
                            log('通知权限已授权', 'success');
                        }
                    });
                } else {
                    log('通知权限被拒绝', 'warning');
                }
            } else {
                log('浏览器不支持Notification', 'warning');
            }
            
            // 方式3: 页面alert（兜底）
            alert('【' + title + '】\n' + content);
            log('已显示页面alert', 'info');
        }

        // 检查新订单
        function checkNewOrders() {
            log('🔍 检查新订单...', 'info');
            
            var xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4) {
                    if (xhr.status == 200) {
                        try {
                            var data = JSON.parse(xhr.responseText);
                            log('📡 服务器返回: ' + JSON.stringify(data), 'info');
                            
                            if (data.success) {
                                updateStatus('检测成功！发现 ' + data.count + ' 个订单', 'success');
                                
                                if (data.orders && data.orders.length > 0) {
                                    var foundNew = false;
                                    
                                    for (var i = 0; i < data.orders.length; i++) {
                                        var order = data.orders[i];
                                        
                                        log('📋 订单: ' + order.order_no + ' - ' + order.product_name, 'info');
                                        
                                        if (order.order_no !== lastDetectedOrderNo) {
                                            log('🎉 发现新订单！订单号: ' + order.order_no, 'success');
                                            foundNew = true;
                                            
                                            if (i === 0) {
                                                lastDetectedOrderNo = order.order_no;
                                            }
                                            
                                            // 根据待接单数量决定通知文案
                                            var notificationContent = '';
                                            if (data.total_pending > 1) {
                                                // 多个订单
                                                notificationContent = '尊敬的用户，您有' + data.total_pending + '个订单还在抢单池';
                                            } else {
                                                // 单个订单
                                                notificationContent = '尊敬的用户，您有新订单已加入抢单池，请尽快抢单！';
                                            }
                                            
                                            sendNotification('新订单', notificationContent);
                                            
                                            break;
                                        }
                                    }
                                    
                                    if (!foundNew) {
                                        log('没有新订单（已检测过）', 'info');
                                    }
                                } else {
                                    log('最近60秒内没有新订单', 'info');
                                }
                            } else {
                                updateStatus('检测失败: ' + (data.error || '未知错误'), 'error');
                            }
                        } catch (e) {
                            log('解析失败: ' + e.message, 'error');
                            updateStatus('解析响应失败', 'error');
                        }
                    } else {
                        log('AJAX请求失败，状态码: ' + xhr.status, 'error');
                        updateStatus('请求失败: ' + xhr.status, 'error');
                    }
                }
            };
            xhr.open('GET', 'api_check_orders.php', true);
            xhr.send();
        }

        // 初始化
        function init() {
            log('通知系统初始化...', 'info');
            
            // 先获取一次当前最新订单
            var xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data.success && data.orders && data.orders.length > 0) {
                            lastDetectedOrderNo = data.orders[0].order_no;
                            log('初始化完成，最新订单: ' + lastDetectedOrderNo, 'success');
                        } else {
                            log('初始化完成，暂无新订单', 'success');
                        }
                        updateStatus('系统就绪', 'success');
                    } catch (e) {
                        log('初始化失败: ' + e.message, 'error');
                    }
                }
            };
            xhr.open('GET', 'api_check_orders.php', true);
            xhr.send();
            
            // 请求通知权限
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        }

        // 测试通知
        function testNotification() {
            log('🧪 测试通知...', 'info');
            sendNotification('测试通知', '恭喜！通知系统工作正常！');
        }

        // 启动自动检测
        function startAutoCheck() {
            if (isAutoChecking) {
                log('已经在自动检测中', 'warning');
                return;
            }
            
            isAutoChecking = true;
            log('🚀 启动自动检测，每5秒一次', 'success');
            updateStatus('自动检测中...', 'info');
            document.getElementById('autoBtn').disabled = true;
            
            // 立即检测一次
            checkNewOrders();
            
            autoCheckInterval = setInterval(checkNewOrders, 5000);
        }

        // 停止自动检测
        function stopAutoCheck() {
            if (autoCheckInterval) {
                clearInterval(autoCheckInterval);
                autoCheckInterval = null;
                isAutoChecking = false;
                log('⏹️ 停止自动检测', 'info');
                updateStatus('已停止', 'info');
                document.getElementById('autoBtn').disabled = false;
            }
        }

        // 页面加载完成
        window.onload = function() {
            log('页面加载完成', 'success');
            init();
        };
    </script>
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>

