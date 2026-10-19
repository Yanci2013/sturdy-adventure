<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>会员开通</title>
    <meta name="viewport"
        content="width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1,user-scalable=no" />
    <link rel="stylesheet" href="./statics/css/swiper-bundle.min.css">
    <link rel="stylesheet" type="text/css" href="./statics/css/style.css" />
    <style>
        /* 新增的CSS样式 */
        body {
            background-color: #f5f5f5;
            font-family: 'PingFang SC', 'Helvetica Neue', Arial, sans-serif;
        }
        
        .top-bar {
            height: 44px;
            line-height: 44px;
            background-color: #fff;
            text-align: center;
            position: relative;
            border-bottom: 1px solid #eee;
        }
        
        .top-bar a {
            position: absolute;
            left: 15px;
            top: 0;
            width: 24px;
            height: 24px;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23333" d="M15.41 16.59L10.83 12l4.58-4.59L14 6l-6 6 6 6 1.41-1.41z"/></svg>') no-repeat center;
            margin-top: 10px;
        }
        
        .top-bar span {
            font-size: 17px;
            font-weight: bold;
            color: #333;
        }
        
        .swiper-container {
            width: 100%;
            height: 200px;
            margin-top: 10px;
        }
        
        .swiper-slide {
            width: 80%;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 10px;
            padding: 15px;
            box-sizing: border-box;
            position: relative;
            overflow: hidden;
        }
        
        .swiper-slide:nth-child(1) {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        
        .swiper-slide:nth-child(2) {
            background: linear-gradient(135deg, #e6e9f0 0%, #eef1f5 100%);
        }
        
        .swiper-slide:nth-child(3) {
            background: linear-gradient(135deg, #f3e7e9 0%, #e3eeff 100%);
        }
        
        .info h3 {
            font-size: 20px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .info span {
            display: inline-block;
            background-color: rgba(255,255,255,0.7);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .info p {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
        }
        
        .jz {
            position: absolute;
            right: 10px;
            bottom: 10px;
            width: 80px;
            height: 80px;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23FFD700" d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2z"/></svg>') no-repeat center;
            opacity: 0.2;
        }
        
        .instruct {
            margin: 15px;
            background-color: #fff;
            border-radius: 10px;
            padding: 15px;
        }
        
        .block {
            display: flex;
            margin-bottom: 20px;
        }
        
        .block:last-child {
            margin-bottom: 0;
        }
        
        .block i {
            width: 24px;
            height: 24px;
            margin-right: 10px;
            flex-shrink: 0;
        }
        
        .block i.hg {
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%234CAF50" d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/></svg>') no-repeat center;
        }
        
        .block i.sd {
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23FF9800" d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9 9-4.03 9-9-4.03-9-9-9zm0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7 7 3.14 7 7-3.14 7-7 7zm1-11h-2v6h2V8zm0 8h-2v2h2v-2z"/></svg>') no-repeat center;
        }
        
        .block h5 {
            font-size: 16px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .block .desc {
            font-size: 14px;
            color: #666;
            line-height: 1.5;
        }
        
        .block .desc p {
            margin-bottom: 5px;
        }
        
        .level {
            margin: 20px 15px;
        }
        
        .level button {
            width: 100%;
            height: 44px;
            background-color: #4CAF50;
            color: #fff;
            border: none;
            border-radius: 22px;
            font-size: 16px;
            font-weight: bold;
        }
        
        .tab-bar-holder {
            height: 50px;
        }
        
        .tab-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 50px;
            background-color: #fff;
            border-top: 1px solid #eee;
        }
        
        .tab-bar ul {
            display: flex;
            height: 100%;
        }
        
        .tab-bar li {
            flex: 1;
            text-align: center;
            padding-top: 5px;
        }
        
        .tab-bar li i {
            display: block;
            width: 24px;
            height: 24px;
            margin: 0 auto 3px;
        }
        
        .tab-bar li.find i {
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23999999" d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>') no-repeat center;
        }
        
        .tab-bar li.trading i {
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23999999" d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>') no-repeat center;
        }
        
        .tab-bar li.financial i {
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23999999" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.31-8.86c-1.77-.45-2.34-.94-2.34-1.67 0-.84.79-1.43 2.1-1.43 1.38 0 1.9.66 1.94 1.64h1.71c-.05-1.34-.87-2.57-2.49-2.97V5H10.9v1.69c-1.51.32-2.72 1.3-2.72 2.81 0 1.79 1.49 2.69 3.66 3.21 1.95.46 2.34 1.15 2.34 1.87 0 .53-.39 1.39-2.1 1.39-1.6 0-2.23-.72-2.32-1.64H8.04c.1 1.7 1.36 2.66 2.86 2.97V19h2.34v-1.67c1.52-.29 2.72-1.16 2.73-2.77-.01-2.2-1.9-2.96-3.66-3.42z"/></svg>') no-repeat center;
        }
        
        .tab-bar li.mine i {
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23999999" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>') no-repeat center;
        }
        
        .tab-bar li.on i {
            filter: brightness(0.5) sepia(1) hue-rotate(140deg) saturate(6);
        }
        
        .tab-bar li.on span {
            color: #4CAF50;
        }
        
        .tab-bar li span {
            font-size: 12px;
            color: #999;
        }
        
        /* 会员套餐选择样式 */
        .package-container {
            margin: 15px;
            background-color: #fff;
            border-radius: 10px;
            padding: 15px;
        }
        
        .package-title {
            font-size: 16px;
            color: #333;
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        .package-list {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -5px;
        }
        
        .package-item {
            width: calc(50% - 10px);
            margin: 5px;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 10px;
            box-sizing: border-box;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .package-item:hover, .package-item.selected {
            border-color: #4CAF50;
            background-color: rgba(76, 175, 80, 0.1);
        }
        
        .package-item.selected {
            position: relative;
        }
        
        .package-item.selected:after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 20px;
            height: 20px;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%234CAF50" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>') no-repeat center;
            background-size: contain;
        }
        
        .package-name {
            font-size: 14px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .package-price {
            font-size: 18px;
            color: #FF5722;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .package-desc {
            font-size: 12px;
            color: #999;
        }
        
        /* 支付方式选择 */
        .payment-container {
            margin: 15px;
            background-color: #fff;
            border-radius: 10px;
            padding: 15px;
        }
        
        .payment-title {
            font-size: 16px;
            color: #333;
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        .payment-list {
            margin: 0 -5px;
        }
        
        .payment-item {
            display: flex;
            align-items: center;
            padding: 10px 5px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }
        
        .payment-item:last-child {
            border-bottom: none;
        }
        
        .payment-icon {
            width: 24px;
            height: 24px;
            margin-right: 10px;
        }
        
        .payment-name {
            flex: 1;
            font-size: 14px;
            color: #333;
        }
        
        .payment-radio {
            width: 20px;
            height: 20px;
            border: 1px solid #ccc;
            border-radius: 50%;
            position: relative;
        }
        
        .payment-item.selected .payment-radio {
            border-color: #4CAF50;
        }
        
        .payment-item.selected .payment-radio:after {
            content: '';
            position: absolute;
            top: 4px;
            left: 4px;
            width: 10px;
            height: 10px;
            background-color: #4CAF50;
            border-radius: 50%;
        }
        
        /* 会员特权说明 */
        .privilege-container {
            margin: 15px;
            background-color: #fff;
            border-radius: 10px;
            padding: 15px;
        }
        
        .privilege-title {
            font-size: 16px;
            color: #333;
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        .privilege-list {
            margin-left: 15px;
        }
        
        .privilege-item {
            position: relative;
            padding-left: 20px;
            margin-bottom: 10px;
            font-size: 14px;
            color: #666;
        }
        
        .privilege-item:before {
            content: '';
            position: absolute;
            left: 0;
            top: 7px;
            width: 6px;
            height: 6px;
            background-color: #4CAF50;
            border-radius: 50%;
        }
        
        /* 底部固定栏 */
        .footer-bar {
            position: fixed;
            bottom: 50px;
            left: 0;
            right: 0;
            height: 50px;
            background-color: #fff;
            border-top: 1px solid #eee;
            display: flex;
            align-items: center;
            padding: 0 15px;
        }
        
        .price-info {
            flex: 1;
        }
        
        .price-label {
            font-size: 12px;
            color: #999;
        }
        
        .price-value {
            font-size: 18px;
            color: #FF5722;
            font-weight: bold;
        }
        
        .confirm-btn {
            width: 120px;
            height: 36px;
            background-color: #4CAF50;
            color: #fff;
            border: none;
            border-radius: 18px;
            font-size: 14px;
            font-weight: bold;
        }
    </style>
    <script src="statics/js/flexible.js" type="text/javascript" charset="utf-8"></script>
    <script src="statics/js/swiper-bundle.min.js"></script>
    <script src="statics/js/vue.min.js"></script>
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

    <div id="app">
        <div class="top-bar">
            <a href="javascript:history.back();"></a>
            <span>会员开通</span>
            <i></i>
        </div>
        
        <!-- 会员等级展示 -->
        <div class="swiper-container">
            <div class="swiper-wrapper">
                <div class="swiper-slide" :class="{'selected': selectedLevel.id === 1}" @click="selectLevel(1)">
                    <div class="info">
                        <h3>和平/pubg会员</h3>
                        <span v-if="user.member_level_id === 1">当前等级</span>
                        <p>可接和平/pubg订单</p>
                    </div>
                    <div class="jz"></div>
                </div>
                <div class="swiper-slide" :class="{'selected': selectedLevel.id === 2}" @click="selectLevel(2)">
                    <div class="info">
                        <h3>白银会员</h3>
                        <span v-if="user.member_level_id === 2">当前等级</span>
                        <p>可接更多游戏订单</p>
                    </div>
                    <div class="jz"></div>
                </div>
                <div class="swiper-slide" :class="{'selected': selectedLevel.id === 3}" @click="selectLevel(3)">
                    <div class="info">
                        <h3>黄金会员</h3>
                        <span v-if="user.member_level_id === 3">当前等级</span>
                        <p>可接所有游戏订单</p>
                    </div>
                    <div class="jz"></div>
                </div>
            </div>
        </div>
        
        <!-- 会员套餐选择 -->
        <div class="package-container">
            <div class="package-title">选择套餐</div>
            <div class="package-list">
                <div class="package-item" 
                     v-for="(item, index) in packages" 
                     :key="index" 
                     :class="{'selected': selectedPackage === index}"
                     @click="selectPackage(index)">
                    <div class="package-name">{{item.name}}</div>
                    <div class="package-price">¥{{item.price}}</div>
                    <div class="package-desc">{{item.desc}}</div>
                </div>
            </div>
        </div>
        
        <!-- 支付方式选择 -->
        <div class="payment-container">
            <div class="payment-title">支付方式</div>
            <div class="payment-list">
                <div class="payment-item" 
                     v-for="(item, index) in paymentMethods" 
                     :key="index" 
                     :class="{'selected': selectedPayment === index}"
                     @click="selectPayment(index)">
                    <img :src="item.icon" class="payment-icon" alt="">
                    <div class="payment-name">{{item.name}}</div>
                    <div class="payment-radio"></div>
                </div>
            </div>
        </div>
        
        <!-- 会员特权说明 -->
        <div class="privilege-container">
            <div class="privilege-title">会员特权</div>
            <div class="privilege-list">
                <div class="privilege-item">可接指定游戏订单</div>
                <div class="privilege-item">开通即送{{selectedLevel.points_given}}积分</div>
                <div class="privilege-item">积分价值1元={{selectedLevel.points_rate}}积分</div>
                <div class="privilege-item">积分低于{{selectedLevel.min_points_for_order}}分不能接单</div>
                <div class="privilege-item">积分低于{{selectedLevel.min_points_for_withdraw}}分不能提现</div>
            </div>
        </div>
        
        <!-- 底部固定栏 -->
        <div class="footer-bar">
            <div class="price-info">
                <div class="price-label">应付金额</div>
                <div class="price-value">¥{{selectedPackageObj.price}}</div>
            </div>
            <button class="confirm-btn" @click="confirmPayment">立即开通</button>
        </div>
        
        <!-- tab-bar导航 -->
        <div class="tab-bar-holder"></div>
        <div class="tab-bar">
            <ul>
                <li class="find" :class="activeTab==1?'on':''" @click="changeTab(1)">
                    <i></i>
                    <span>发现</span>
                </li>
                <li class="trading" :class="activeTab==2?'on':''" @click="changeTab(2)">
                    <i></i>
                    <span>交易</span>
                </li>
                <li class="financial" :class="activeTab==3?'on':''" @click="changeTab(3)">
                    <i></i>
                    <span>理财</span>
                </li>
                <li class="mine" :class="activeTab==4?'on':''" @click="changeTab(4)">
                    <i></i>
                    <span>我的</span>
                </li>
            </ul>
        </div>
    </div>
    
<script src="../assets/js/loader-v2.js"></script>
</body>

</html>
<script>
    new Vue({
        el: '#app',
        data: {
            activeTab: 3,
            swiper: null,
            selectedLevel: {
                id: 1,
                name: '和平/pubg会员',
                points_given: 50,
                points_rate: 1.00,
                min_points_for_order: 27,
                min_points_for_withdraw: 27
            },
            packages: [
                {name: '日付套餐', price: '1.00', desc: '仅需1元体验1天'},
                {name: '周付套餐', price: '7.00', desc: '7元体验7天'},
                {name: '月付套餐', price: '30.00', desc: '30元畅玩30天'},
                {name: '季付套餐', price: '80.00', desc: '80元畅玩90天'}
            ],
            selectedPackage: 2, // 默认选择月付套餐
            paymentMethods: [
                {name: '支付宝支付', icon: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%2300AAEF" d="M5.373 4.51A9.962 9.962 0 0 1 12 2c2.347 0 4.518.81 6.23 2.166.678.53 1.29 1.147 1.813 1.834H16.59c-.58 0-1.12.28-1.454.748l-3.38 5.27-1.203-1.873a1.75 1.75 0 0 0-1.454-.748H5.373zm-.102 1.84H9.45c.29 0 .56.14.727.374l2.46 3.828 3.473-5.416a1.75 1.75 0 0 1 1.453-.748h2.052a10.03 10.03 0 0 0-1.813-1.834A9.963 9.963 0 0 0 12 3.5a9.963 9.963 0 0 0-6.23 2.166c-.42.328-.805.7-1.147 1.105zM2 12c0 1.37.31 2.67.86 3.83.1.2.21.4.32.58.24.38.49.75.79 1.08.3.33.63.63.98.91.7.56 1.49 1.03 2.34 1.37.85.34 1.76.56 2.69.66.93.1 1.87.08 2.79-.07.92-.15 1.8-.43 2.62-.83.82-.4 1.57-.93 2.23-1.56.66-.63 1.22-1.36 1.67-2.16.45-.8.78-1.67.98-2.58.2-.91.28-1.85.23-2.79-.05-.94-.23-1.86-.55-2.74-.32-.88-.77-1.71-1.33-2.46-.56-.75-1.23-1.42-1.99-1.98-.76-.56-1.6-1.01-2.5-1.33-.9-.32-1.85-.5-2.8-.55A9.93 9.93 0 0 0 12 2a9.99 9.99 0 0 0-9.14 6H7.5c.58 0 1.12.28 1.454.748l3.38 5.27 1.203-1.873a1.75 1.75 0 0 1 1.454-.748h3.01c-.17.34-.36.67-.57.99-.44.66-.97 1.25-1.57 1.76-.6.51-1.27.93-2 1.25-.73.32-1.51.54-2.31.64-.8.1-1.62.08-2.41-.07-.79-.15-1.54-.41-2.24-.78-.7-.37-1.34-.84-1.9-1.39-.56-.55-1.04-1.19-1.42-1.89-.38-.7-.65-1.46-.8-2.25-.15-.79-.17-1.6-.07-2.4.1-.8.32-1.58.64-2.31.32-.73.74-1.4 1.25-2 .51-.6 1.1-1.13 1.76-1.57.33-.21.65-.4.99-.57v3.01c0 .58-.28 1.12-.748 1.454l-5.27 3.38 1.873 1.203a1.75 1.75 0 0 1 .748 1.454v3.01z"/></svg>'},
                {name: '微信支付', icon: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%2309BB07" d="M8.5 5.5c1.16 0 2.1.94 2.1 2.1s-.94 2.1-2.1 2.1-2.1-.94-2.1-2.1.94-2.1 2.1-2.1zm7 0c1.16 0 2.1.94 2.1 2.1s-.94 2.1-2.1 2.1-2.1-.94-2.1-2.1.94-2.1 2.1-2.1zM12 14.5c-2.33 0-4.31 1.21-5.5 3h11c-1.19-1.79-3.17-3-5.5-3zm0-12C8.41 2.5 5.5 5.41 5.5 9.5S8.41 16.5 12 16.5s6.5-2.91 6.5-7S15.59 2.5 12 2.5zm0 12c-2.76 0-5 2.24-5 5h10c0-2.76-2.24-5-5-5z"/></svg>'},
                {name: '余额支付', icon: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="%23FFC107" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.31-8.86c-1.77-.45-2.34-.94-2.34-1.67 0-.84.79-1.43 2.1-1.43 1.38 0 1.9.66 1.94 1.64h1.71c-.05-1.34-.87-2.57-2.49-2.97V5H10.9v1.69c-1.51.32-2.72 1.3-2.72 2.81 0 1.79 1.49 2.69 3.66 3.21 1.95.46 2.34 1.15 2.34 1.87 0 .53-.39 1.39-2.1 1.39-1.6 0-2.23-.72-2.32-1.64H8.04c.1 1.7 1.36 2.66 2.86 2.97V19h2.34v-1.67c1.52-.29 2.72-1.16 2.73-2.77-.01-2.2-1.9-2.96-3.66-3.42z"/></svg>'}
            ],
            selectedPayment: 0,
            user: {
                member_level_id: null,
                balance: '0.00',
                member_points: 0
            }
        },
        computed: {
            selectedPackageObj() {
                return this.packages[this.selectedPackage];
            }
        },
        mounted: function() {
            this.$nextTick(function() {
                this.swiper = new Swiper('.swiper-container', {
                    loop: true,
                    slidesPerView: 'auto',
                    centeredSlides: true,
                    spaceBetween: 10
                });
                
                // 模拟获取用户信息
                this.getUserInfo();
            });
        },
        methods: {
            // 切换tab-bar
            changeTab: function(item) {
                this.activeTab = item;
                // 实际应用中这里应该跳转到对应页面
            },
            
            // 选择会员等级
            selectLevel: function(levelId) {
                this.selectedLevel.id = levelId;
                // 实际应用中这里应该从数据库获取对应等级的详细信息
                switch(levelId) {
                    case 1:
                        this.selectedLevel.name = '和平/pubg会员';
                        this.selectedLevel.points_given = 50;
                        this.selectedLevel.points_rate = 1.00;
                        this.selectedLevel.min_points_for_order = 27;
                        this.selectedLevel.min_points_for_withdraw = 27;
                        break;
                    case 2:
                        this.selectedLevel.name = '白银会员';
                        this.selectedLevel.points_given = 100;
                        this.selectedLevel.points_rate = 1.20;
                        this.selectedLevel.min_points_for_order = 50;
                        this.selectedLevel.min_points_for_withdraw = 50;
                        break;
                    case 3:
                        this.selectedLevel.name = '黄金会员';
                        this.selectedLevel.points_given = 200;
                        this.selectedLevel.points_rate = 1.50;
                        this.selectedLevel.min_points_for_order = 100;
                        this.selectedLevel.min_points_for_withdraw = 100;
                        break;
                }
            },
            
            // 选择套餐
            selectPackage: function(index) {
                this.selectedPackage = index;
            },
            
            // 选择支付方式
            selectPayment: function(index) {
                this.selectedPayment = index;
            },
            
            // 获取用户信息
            getUserInfo: function() {
                // 实际应用中这里应该通过AJAX从服务器获取用户信息
                // 模拟数据
                this.user = {
                    member_level_id: null, // 假设用户当前不是会员
                    balance: '105.50', // 用户余额
                    member_points: 0 // 用户积分
                };
            },
            
            // 确认支付
            confirmPayment: function() {
                if (!this.selectedLevel) {
                    alert('请选择会员等级');
                    return;
                }
                
                if (!this.selectedPackageObj) {
                    alert('请选择套餐');
                    return;
                }
                
                if (!this.paymentMethods[this.selectedPayment]) {
                    alert('请选择支付方式');
                    return;
                }
                
                // 检查余额是否足够（如果是余额支付）
                if (this.selectedPayment === 2) { // 余额支付
                    if (parseFloat(this.user.balance) < parseFloat(this.selectedPackageObj.price)) {
                        alert('余额不足，请选择其他支付方式');
                        return;
                    }
                }
                
                // 确认支付
                if (confirm(`确认开通${this.selectedLevel.name} ${this.selectedPackageObj.name}，支付金额：¥${this.selectedPackageObj.price}？`)) {
                    // 实际应用中这里应该通过AJAX提交支付请求
                    alert('支付成功！会员已开通');
                    
                    // 更新用户信息
                    this.user.member_level_id = this.selectedLevel.id;
                    this.user.member_points += this.selectedLevel.points_given;
                    
                    // 如果是余额支付，扣除余额
                    if (this.selectedPayment === 2) {
                        this.user.balance = (parseFloat(this.user.balance) - parseFloat(this.selectedPackageObj.price)).toFixed(2);
                    }
                }
            }
        }
    });
</script>