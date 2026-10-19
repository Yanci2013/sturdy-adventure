
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>如何接单 - 接单教程</title>
    <!-- 引入 Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-color: #ffffff;
            color: #333333;
            font-family: 'PingFang SC', 'Microsoft YaHei', sans-serif;
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        /* 头部样式 */
        .header {
            margin-bottom: 40px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 15px;
            color: #3498db;
        }
        
        .header p {
            font-size: 16px;
            color: #666666;
            max-width: 800px;
            margin: 0 auto;
        }
        
        /* 消息样式 */
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            text-align: center;
        }
        
        .message.error {
            background-color: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
        }
        
        .message.success {
            background-color: #e8f5e8;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }
        
        /* 教程步骤样式 */
        .tutorial-steps {
            margin-bottom: 40px;
        }
        
        .step {
            background-color: #f8f9fa;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        
        .step:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }
        
        .step-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #3498db;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .step-title {
            font-size: 20px;
            font-weight: 600;
            color: #333333;
        }
        
        .step-content {
            margin-left: 55px;
        }
        
        .step-content p {
            margin-bottom: 12px;
            color: #666666;
        }
        
        .step-content ul {
            margin-bottom: 15px;
            padding-left: 20px;
        }
        
        .step-content li {
            margin-bottom: 8px;
            color: #666666;
        }
        
        /* 订单列表样式 */
        .orders-section {
            background-color: #f8f9fa;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        
        .orders-section h2 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 25px;
            color: #333333;
            display: flex;
            align-items: center;
        }
        
        .orders-section h2 i {
            margin-right: 10px;
            color: #3498db;
        }
        
        .order-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .order-card {
            background-color: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
        }
        
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .order-id {
            font-size: 16px;
            font-weight: 500;
            color: #333333;
        }
        
        .order-status {
            padding: 6px 16px;
            border-radius: 16px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .order-content {
            margin-bottom: 20px;
        }
        
        .order-product {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #333333;
        }
        
        .order-meta {
            font-size: 14px;
            color: #666666;
            line-height: 1.6;
        }
        
        .order-meta p {
            margin-bottom: 8px;
        }
        
        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }
        
        .order-price {
            font-size: 20px;
            font-weight: 700;
            color: #3498db;
        }
        
        /* 按钮样式 */
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
        }
        
        .btn-primary {
            background-color: #3498db;
            color: #ffffff;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
        }
        
        /* 空状态样式 */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #999999;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #e0e0e0;
        }
        
        .empty-state h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #666666;
        }
        
        .empty-state p {
            font-size: 14px;
            color: #999999;
        }
        
        /* 提示框样式 */
        .tip-box {
            background-color: #e3f2fd;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid #3498db;
        }
        
        .tip-box h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #1565c0;
            display: flex;
            align-items: center;
        }
        
        .tip-box h3 i {
            margin-right: 8px;
        }
        
        .tip-box p {
            font-size: 14px;
            color: #37474f;
            margin-bottom: 8px;
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .step {
                padding: 20px;
            }
            
            .step-title {
                font-size: 18px;
            }
            
            .step-content {
                margin-left: 0;
                margin-top: 15px;
            }
            
            .orders-section {
                padding: 20px;
            }
            
            .order-card {
                padding: 20px;
            }
            
            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .order-footer {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .btn {
                width: 100%;
            }
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
    <!-- 头部 -->
    <div class="header">
        <h1><i class="fas fa-handshake"></i> 如何接单</h1>
        <p>本教程将指导您如何在系统中接取订单，开始您的任务</p>
    </div>
    
    <!-- 错误/成功消息 -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error']); ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- 提示框 -->
    <div class="tip-box">
        <h3><i class="fas fa-lightbulb"></i> 接单小贴士</h3>
        <p>• 接单前请仔细阅读订单详情，确保您有能力完成任务</p>
        <p>• 接单后请及时开始服务，避免订单超时</p>
        <p>• 完成订单后请认真填写完成备注，提高客户满意度</p>
        <p>• 保持良好的服务态度，有助于获得更多订单</p>
    </div>

    <!-- 教程步骤 -->
    <div class="tutorial-steps">
        <div class="step">
            <div class="step-header">
                <div class="step-number">1</div>
                <div class="step-title">查看待接单</div>
            </div>
            <div class="step-content">
                <p>在本页面，您可以看到所有待接单的订单列表。每个订单包含以下信息：</p>
                <ul>
                    <li>订单号：唯一标识订单的编号</li>
                    <li>订单状态：显示为"待接单"</li>
                    <li>商品名称：订单对应的商品或服务</li>
                    <li>订单金额：完成订单后您将获得的收益</li>
                    <li>订单描述：订单的详细说明和要求</li>
                    <li>下单时间：客户提交订单的时间</li>
                </ul>
                <p>请仔细阅读订单信息，选择您感兴趣且有能力完成的订单。</p>
            </div>
        </div>

        <div class="step">
            <div class="step-header">
                <div class="step-number">2</div>
                <div class="step-title">接取订单</div>
            </div>
            <div class="step-content">
                <p>找到您想要接取的订单后，点击订单卡片右下角的"接单"按钮。</p>
                <p>系统会自动：</p>
                <ul>
                    <li>检查订单状态是否为"待接单"</li>
                    <li>将订单状态更新为"已接单"</li>
                    <li>将您设置为该订单的服务提供者</li>
                    <li>记录接单时间</li>
                </ul>
                <p>如果接单成功，系统会显示"接单成功"的提示信息。</p>
            </div>
        </div>

        <div class="step">
            <div class="step-header">
                <div class="step-number">3</div>
                <div class="step-title">开始服务</div>
            </div>
            <div class="step-content">
                <p>接单成功后，您需要：</p>
                <ul>
                    <li>及时与客户沟通，了解具体需求</li>
                    <li>按照订单要求开始提供服务</li>
                    <li>保持与客户的良好沟通</li>
                    <li>在规定时间内完成服务</li>
                </ul>
                <p>服务过程中遇到问题，请及时与客户协商解决。</p>
            </div>
        </div>

        <div class="step">
            <div class="step-header">
                <div class="step-number">4</div>
                <div class="step-title">完成订单</div>
            </div>
            <div class="step-content">
                <p>服务完成后，您需要：</p>
                <ul>
                    <li>检查服务是否符合客户要求</li>
                    <li>填写完成备注，说明服务内容</li>
                    <li>提交完成申请</li>
                </ul>
                <p>客户确认后，订单将正式完成，您将获得相应的收益。</p>
            </div>
        </div>
    </div>

    <!-- 待接单列表 -->
<!--    <div class="orders-section">-->
<!--        <h2><i class="fas fa-clock"></i> 待接单</h2>-->
<!--        <div class="order-list">-->
<!--            <?php if (!empty($pendingOrders)): ?>-->
<!--                <?php foreach ($pendingOrders as $order): ?>-->
<!--                    <div class="order-card">-->
<!--                        <div class="order-header">-->
<!--                            <span class="order-id">订单号: <?php echo $order['order_no']; ?></span>-->
<!--                            <span class="order-status status-pending">待接单</span>-->
<!--                        </div>-->
<!--                        <div class="order-content">-->
<!--                            <div class="order-product"><?php echo $order['product_name']; ?></div>-->
<!--                            <div class="order-meta">-->
<!--                                <p>订单金额: ¥<?php echo number_format($order['amount'], 2); ?></p>-->
<!--                                <p>订单描述: <?php echo $order['order_description']; ?></p>-->
<!--                                <p>下单时间: <?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></p>-->
<!--                            </div>-->
<!--                        </div>-->
<!--                        <div class="order-footer">-->
<!--                            <span class="order-price">¥<?php echo number_format($order['amount'], 2); ?></span>-->
<!--                            <form method="POST" style="width: 100%; max-width: 200px;">-->
<!--                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">-->
<!--                                <button type="submit" name="accept_order" class="btn btn-primary">-->
<!--                                    <i class="fas fa-handshake"></i> 接单-->
<!--                                </button>-->
<!--                            </form>-->
<!--                        </div>-->
<!--                    </div>-->
<!--                <?php endforeach; ?>-->
<!--            <?php else: ?>-->
                <!--<div class="empty-state">-->
                <!--    <i class="fas fa-box-open"></i>-->
                <!--    <h3>暂无待接单</h3>-->
                <!--    <p>当前没有可用的订单，请稍后再刷新查看</p>-->
                <!--    <p>系统会定期更新订单列表，请耐心等待</p>-->
                <!--</div>-->
<!--            <?php endif; ?>-->
<!--        </div>-->
<!--    </div>-->
<!--</div>-->
    
<script src="../assets/js/loader-v2.js"></script>
</body>
</html>
<?php include 'footer.php'; ?>