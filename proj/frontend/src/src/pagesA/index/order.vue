<template>
	<view class="page">
		<!-- 顶部背景 -->
		<image class="top-bg" src="/static/images/header_background.png" mode="aspectFill"></image>
		<!-- 顶部紫色电竞遮罩 -->
		<view class="top-mask"></view>

		<!-- 导航栏 -->
		<view class="navbar">
			<view class="navbar-capsule">
				<view class="navbar-btn" @click="goBack">
					<up-icon name="arrow-left" size="34rpx" color="#606266"></up-icon>
				</view>
				<view class="navbar-divider"></view>
				<view class="navbar-btn" @click="goHome">
					<up-icon name="home" size="36rpx" color="#606266"></up-icon>
				</view>
			</view>
			<text class="navbar-title">提交订单</text>
			<view class="navbar-placeholder"></view>
		</view>

		<!-- 未成年人下单警示（红色提醒） -->
		<view class="minor-tip">
			<view class="minor-tip-tag">提示</view>
			<text class="minor-tip-text">根据国家法律规定，未成年人禁止下单！</text>
		</view>

		<scroll-view scroll-y class="content">
			<view class="content-inner" v-if="dataInfo.title">
				<!-- 商品信息卡片 -->
				<view class="product-card">
					<text class="product-count">共1件商品</text>
					<view class="product-body">
						<image class="product-thumb" :src="dataInfo.logo" mode="aspectFill"></image>
						<view class="product-info">
							<text class="product-name">{{ dataInfo.title }}</text>
							<view class="product-price-row">
								<view>
									<text class="price-symbol">¥ </text>
									<text class="price-num">{{ goodsprice }}</text>
								</view>
								<text class="price-qty">x{{ queryParams.number }}</text>
							</view>
							<text class="product-desc">{{ dataInfo.synopsis }}</text>
						</view>
					</view>
					<!-- 接单打手 -->
					<view class="product-row" v-if="radiovalue1 === '指定打手' && currentItem && currentItem.name">
						<text class="product-label">接单打手</text>
						<text class="product-value">{{ currentItem.name }}</text>
					</view>
					<!-- 差价提示 -->
					<view class="product-row" v-if="priceGap > 0 && queryParams.game_qf === '电脑端'">
						<text class="product-label">差价 (电脑价格更高)</text>
						<text class="product-value" style="color:#da4a44;">¥{{ priceGap }}</text>
					</view>
					<!-- 实付款 -->
					<view class="product-row product-row-total">
						<text class="product-label">实付款</text>
						<view class="product-value-row">
							<text class="price-symbol">¥</text>
							<text class="price-num">{{ total }}</text>
						</view>
					</view>
				</view>

				<!-- 下单信息区域 -->
				<view class="form-card card-glass">
					<!-- 游戏昵称 -->
					<view class="form-row">
						<text class="form-label"><text class="req">*</text>游戏昵称</text>
						<input class="form-input" v-model="queryParams.wz_id" placeholder="请输入游戏昵称" placeholder-class="placeholder" />
					</view>
					<!-- ID账号 -->
					<view class="form-row">
						<text class="form-label"><text class="req">*</text>ID账号</text>
						<input class="form-input" v-model="queryParams.game_id" placeholder="请输入ID账号" placeholder-class="placeholder" />
					</view>
					<!-- 游戏区服 -->
					<view class="form-row">
						<text class="form-label">游戏区服</text>
						<view class="option-group">
							<view v-for="(item, index) in radiolist" :key="index"
								:class="['option-tag', radiovalue === item.name ? 'active' : '']"
								@click="radioChange(item.name)">
								<text>{{ item.name }}</text>
							</view>
						</view>
					</view>
					<!-- 接单方式 -->
					<view class="form-row">
						<text class="form-label">接单方式</text>
						<view class="option-group">
							<view v-for="(item, index) in radiolist1" :key="index"
								:class="['option-tag', radiovalue1 === item.name ? 'active' : '']"
								@click="radioChange1(item.name)">
								<text>{{ item.name }}</text>
							</view>
						</view>
					</view>
					<!-- 下单数量 -->
					<view class="form-row form-row-flex">
						<text class="form-label">下单数量</text>
						<view class="quantity-wrap">
							<view class="qty-btn" @click="changeQty(-1)">-</view>
							<text class="qty-num">{{ queryParams.number }}</text>
							<view class="qty-btn" @click="changeQty(1)">+</view>
						</view>
					</view>
					<!-- 下单备注 -->
					<view class="form-row">
						<text class="form-label">下单备注</text>
						<input class="form-input" v-model="queryParams.remarks" placeholder="选填" placeholder-class="placeholder" maxlength="128" />
					</view>
				</view>

				<!-- 下单说明 -->
				<view class="notice-card" v-if="orderPlace">
					<view class="notice-inner">
						<text>{{ orderPlace }}</text>
					</view>
				</view>

				<view style="height: 40rpx;"></view>
			</view>
		</scroll-view>

		<!-- 底部栏 -->
		<view class="footer-bar">
			<view class="footer-left">
				<text class="footer-label">合计:</text>
				<text class="price-symbol">¥</text>
				<text class="footer-total">{{ total }}</text>
			</view>
			<view class="btn-settle" @click="submit">立即下单</view>
		</view>

		<!-- 选择打手弹窗 -->
		<up-popup :show="show" mode="center" closeable @close="closePopup" round="20rpx">
			<view class="popup-wrap">
				<view class="popup-title">选择打手</view>
				<scroll-view scroll-y style="height: 500rpx;" @scrolltolower="scrolltolowerhandle">
					<view class="thug-item" v-for="(item, index) in thugAppointList" :key="index" @click="changeTuag(item, index)">
						<image class="thug-check" :src="current === index ? '/static/images/select.png' : '/static/images/normal.png'" mode="aspectFit"></image>
						<image class="thug-avatar" :src="item.avatar" mode="aspectFill"></image>
						<text class="thug-name">{{ item.name }}</text>
					</view>
				</scroll-view>
				<view class="popup-btn" @click="choosePlayer">确定</view>
			</view>
		</up-popup>
	</view>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import router from '../../utils/router';
import { onLoad } from '@dcloudio/uni-app';
import { goodsMsg, orderAdd, configure, thugAppoint } from '../../request/api';
import { test } from '../../uni_modules/uview-plus';

const show = ref(false)
const current = ref(0)
const currentItem = ref({})
const radiovalue = ref('手机端')
const radiolist = reactive([{ name: '手机端' }, { name: '电脑端' }])
const radiovalue1 = ref('随机匹配')
const radiolist1 = reactive([{ name: '随机匹配' }, { name: '指定打手' }])
const dataInfo = ref({})
const id = ref('')
const orderPlace = ref('')
const thugId = ref('')
const maxNum = ref(0)
const goodsprice = ref('')
const priceGap = ref(0)
const thugAppointList = ref([])
const pagedata = ref({ page: 1, limit: 10, pageloading: true, isloading: false, total: 0 })

const queryParams = ref({
	type: '', id: '', number: 1, thug_id: '',
	game_id: '', wz_id: '', game_qf: '手机端', remarks: ''
})

onLoad((prop) => {
	id.value = prop.id
	queryParams.value.id = prop.id
	thugId.value = prop.thugId
	queryParams.value.thug_id = prop.thugId
	maxNum.value = prop.num == 0 ? 10000000000 : prop.num
	if (!test.isEmpty(prop.thugId)) {
		radiovalue1.value = '指定打手'
	}
	getData(prop.id)
})

const total = computed(() => {
	return (queryParams.value.number * goodsprice.value * 100) / 100
})

const getData = (id) => {
	goodsMsg({ id }).then(res => {
		dataInfo.value = res.data
		goodsprice.value = res.data.price
		priceGap.value = Number(res.data.com_price) - Number(res.data.price) > 0
			? Number(res.data.com_price) - Number(res.data.price) : 0
	})
	configure().then(res => { orderPlace.value = res.data.place_order }).catch(() => {})
	reset()
	thugAppoints()
}

const changeQty = (delta) => {
	const val = queryParams.value.number + delta
	if (val < 1 || val > maxNum.value) return
	queryParams.value.number = val
}

const radioChange = (n) => {
	radiovalue.value = n
	const { price, com_price } = dataInfo.value
	queryParams.value.game_qf = n
	goodsprice.value = n === '手机端' ? price : com_price
}

const radioChange1 = (n) => {
	radiovalue1.value = n
	if (n === '指定打手') show.value = true
}

const closePopup = () => {
	show.value = false
	if (!currentItem.value || !currentItem.value.id) radiovalue1.value = '随机匹配'
}

const reset = () => {
	pagedata.value = { page: 1, limit: 10, pageloading: true, isloading: false, total: 0 }
	thugAppointList.value = []
}

const thugAppoints = () => {
	pagedata.value.isloading = true
	thugAppoint({ pages: pagedata.value.page, limit: pagedata.value.limit }).then(res => {
		pagedata.value.isloading = false
		thugAppointList.value = [...thugAppointList.value, ...res.data.data]
		pagedata.value.total = res.data.total
		currentItem.value = thugAppointList.value.find(v => v.id == thugId.value) || {}
	}).catch(() => { pagedata.value.isloading = false })
}

const scrolltolowerhandle = () => {
	if (pagedata.value.pageloading && !pagedata.value.isloading) {
		if (thugAppointList.value.length < pagedata.value.total) {
			pagedata.value.page++
			thugAppoints()
		} else {
			pagedata.value.pageloading = false
		}
	}
}

const changeTuag = (item, index) => { current.value = index }

const choosePlayer = () => {
	currentItem.value = thugAppointList.value[current.value]
	show.value = false
	thugId.value = currentItem.value.id
}

const goBack = () => {
	const pages = getCurrentPages()
	if (pages.length > 1) uni.navigateBack({ delta: 1 })
	else uni.reLaunch({ url: '/pages/index/index' })
}

const goHome = () => { uni.reLaunch({ url: '/pages/index/index' }) }

const submit = async () => {
	if (test.isEmpty(queryParams.value.wz_id)) {
		return uni.showToast({ title: '请填写游戏昵称', icon: 'none' })
	}
	if (test.isEmpty(queryParams.value.game_id)) {
		return uni.showToast({ title: '请填写ID账号', icon: 'none' })
	}
	queryParams.value.type = radiovalue1.value === '随机匹配' ? '1' : '2'
	queryParams.value.thug_id = radiovalue1.value === '随机匹配' ? '0' : thugId.value
	const res = await orderAdd(queryParams.value)
	router.redirectTo({
		url: 'pagesA/index/desk',
		data: { id: res.data.order_id, price: total.value, award: res.data.award }
	})
}
</script>

<style scoped>
/* ================= 电竞风配色：主色 紫 + 橙 ================= */
.page {
	min-height: 100vh;
	background: #F4F2FC;
	display: flex;
	flex-direction: column;
	padding-bottom: calc(140rpx + env(safe-area-inset-bottom));
}

/* 顶部背景 */
.top-bg {
	position: fixed;
	top: 0; left: 0; right: 0;
	width: 100%; height: 480rpx;
	z-index: 0;
}
/* 顶部紫色电竞遮罩 */
.top-mask {
	position: fixed;
	top: 0; left: 0; right: 0;
	height: 480rpx;
	z-index: 0;
	background: linear-gradient(180deg, rgba(108,76,230,0.92) 0%, rgba(108,76,230,0.55) 40%, rgba(108,76,230,0) 100%);
}

/* 导航栏 */
.navbar {
	position: relative;
	z-index: 10;
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 0 24rpx;
	padding-top: calc(var(--status-bar-height, 0px) + 16rpx);
	background: transparent;
}
.navbar-capsule {
	display: flex;
	align-items: center;
	border: 1rpx solid rgba(255,255,255,0.5);
	border-radius: 100px;
	padding: 8rpx 20rpx;
	backdrop-filter: blur(10px);
	-webkit-backdrop-filter: blur(10px);
	background: rgba(255,255,255,0.85);
	box-shadow: 0 4rpx 16rpx rgba(75,47,176,0.18);
}
.navbar-btn { padding: 4rpx 8rpx; }
.navbar-divider {
	width: 1rpx; height: 32rpx;
	background: #dadbde;
	margin: 0 8rpx;
}
.navbar-title {
	font-size: 34rpx;
	font-weight: 700;
	color: #ffffff;
	text-shadow: 0 2rpx 8rpx rgba(75,47,176,0.5);
}
.navbar-placeholder { width: 120rpx; }

/* 未成年人下单警示 */
.minor-tip {
	position: relative;
	z-index: 10;
	display: flex;
	align-items: center;
	margin: 20rpx 24rpx 0;
	padding: 16rpx 20rpx;
	background: #FFF1F0;
	border: 1rpx solid #FFCCC7;
	border-left: 8rpx solid #E5484D;
	border-radius: 16rpx;
	box-shadow: 0 4rpx 14rpx rgba(229,72,77,0.10);
}
.minor-tip-tag {
	flex-shrink: 0;
	margin-right: 14rpx;
	padding: 4rpx 16rpx;
	font-size: 22rpx;
	color: #ffffff;
	font-weight: 600;
	background: linear-gradient(135deg, #E5484D, #FF6B6B);
	border-radius: 999rpx;
}
.minor-tip-text {
	font-size: 24rpx;
	color: #E5484D;
	font-weight: 600;
}

/* 内容区 */
.content {
	flex: 1;
	padding: 24rpx 0 32rpx;
	box-sizing: border-box;
	position: relative;
	z-index: 1;
}
.content-inner { padding: 0 24rpx; }

/* 商品卡片 */
.product-card {
	padding: 28rpx 32rpx;
	background: #fff;
	margin-bottom: 24rpx;
	border-radius: 28rpx;
	box-shadow: 0 10rpx 26rpx rgba(75,47,176,0.08), 0 2rpx 6rpx rgba(0,0,0,0.03);
}
.product-count {
	display: block;
	font-size: 24rpx;
	color: #8A8A99;
	margin-bottom: 20rpx;
}
.product-body {
	display: flex;
	gap: 20rpx;
	padding-bottom: 20rpx;
	border-bottom: 1rpx solid rgba(108,76,230,0.10);
}
.product-thumb {
	width: 160rpx; height: 160rpx;
	flex-shrink: 0;
	border-radius: 20rpx;
	background: #f0f0f0;
}
.product-info {
	flex: 1;
	min-width: 0;
}
.product-name {
	font-size: 28rpx;
	font-weight: 700;
	color: #1B1330;
	line-height: 1.4;
	display: -webkit-box;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
}
.product-price-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 12rpx 0;
	margin-bottom: 12rpx;
}
.price-num {
	font-size: 30rpx;
	color: #FF7A45;
	font-weight: 700;
}
.price-symbol {
	font-size: 26rpx;
	color: #FF7A45;
	font-weight: 700;
}
.price-qty {
	font-size: 24rpx;
	color: #999;
}
.product-desc {
	font-size: 24rpx;
	color: #8f8f8f;
	line-height: 1.5;
	display: -webkit-box;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
	margin-bottom: 20rpx;
}
.product-row {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-top: 20rpx;
	font-size: 26rpx;
}
.product-label { color: #666; }
.product-value { color: #333; }
.product-value-row {
	display: flex;
	align-items: center;
	gap: 6rpx;
}
.product-row-total .price-num { font-size: 34rpx; }

/* 表单卡片 */
.card-glass {
	background: #fff;
	border: 1rpx solid rgba(108,76,230,0.08);
	border-radius: 28rpx;
	box-shadow: 0 10rpx 26rpx rgba(75,47,176,0.08), 0 2rpx 6rpx rgba(0,0,0,0.03);
	margin-bottom: 24rpx;
}
.form-card { padding: 32rpx; }
.form-row {
	margin-bottom: 24rpx;
	padding-bottom: 24rpx;
	border-bottom: 1rpx solid rgba(108,76,230,0.08);
}
.form-row:last-child {
	margin-bottom: 0;
	padding-bottom: 0;
	border-bottom: none;
}
.form-row-flex {
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.form-row-flex .form-label { margin-bottom: 0; }
.form-label {
	display: block;
	font-size: 26rpx;
	color: #1B1330;
	font-weight: 600;
	margin-bottom: 16rpx;
}
/* 必填星号 */
.req {
	color: #E5484D;
	font-size: 28rpx;
	font-weight: 700;
	margin-right: 6rpx;
}
.form-input {
	width: 100%;
	height: 84rpx;
	padding: 0 26rpx;
	background: #F4F2FC;
	border: 1rpx solid rgba(108,76,230,0.12);
	border-radius: 16rpx;
	font-size: 26rpx;
	color: #1B1330;
	box-sizing: border-box;
	transition: all .2s ease;
}
.form-input:focus {
	border-color: #6C4CE6;
	box-shadow: 0 0 0 4rpx rgba(108,76,230,0.12);
}
.placeholder { color: #A6A6B4; }

/* 选项标签组 */
.option-group {
	display: flex;
	flex-wrap: wrap;
	gap: 16rpx;
}
.option-tag {
	padding: 16rpx 40rpx;
	background: #F4F2FC;
	border: 1rpx solid rgba(108,76,230,0.12);
	border-radius: 16rpx;
	font-size: 26rpx;
	color: #6B6B7B;
	transition: all .2s ease;
}
.option-tag.active {
	background: linear-gradient(135deg, #6C4CE6, #8B6BFF);
	border-color: transparent;
	color: #fff;
	font-weight: 600;
	box-shadow: 0 6rpx 16rpx rgba(108,76,230,0.32);
}

/* 数量 */
.quantity-wrap {
	display: flex;
	align-items: center;
	gap: 24rpx;
}
.qty-btn {
	width: 60rpx; height: 60rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	background: #F4F2FC;
	border: 1rpx solid rgba(108,76,230,0.14);
	border-radius: 12rpx;
	font-size: 34rpx;
	color: #6C4CE6;
	font-weight: 600;
}
.qty-num {
	font-size: 30rpx;
	color: #1B1330;
	font-weight: 700;
	min-width: 48rpx;
	text-align: center;
}

/* 下单说明 */
.notice-card { padding: 24rpx 0 0; }
.notice-inner {
	font-size: 22rpx;
	color: #8A7BA0;
	line-height: 1.8;
}

/* 底部栏 */
.footer-bar {
	position: fixed;
	left: 0; right: 0; bottom: 0;
	z-index: 10;
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 20rpx 24rpx;
	padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
	background: #fff;
	box-shadow: 0 -4rpx 20rpx rgba(75,47,176,0.10);
}
.footer-left {
	display: flex;
	align-items: center;
	gap: 8rpx;
}
.footer-label {
	font-size: 28rpx;
	color: #333;
}
.footer-total {
	font-size: 34rpx;
	color: #FF7A45;
	font-weight: 700;
}
.btn-settle {
	height: 84rpx;
	line-height: 84rpx;
	min-width: 320rpx;
	padding: 0 48rpx;
	font-size: 30rpx;
	font-weight: 600;
	color: #fff;
	background: linear-gradient(135deg, #FF9D6E, #FF7A45);
	border-radius: 42rpx;
	text-align: center;
	box-shadow: 0 8rpx 20rpx rgba(255,122,69,0.4);
}
.btn-settle:active {
	transform: scale(0.98);
}

/* 选择打手弹窗 */
.popup-wrap {
	width: 600rpx;
	padding: 36rpx 32rpx 32rpx;
	background: #fff;
	border-radius: 32rpx;
}
.popup-title {
	font-size: 32rpx;
	font-weight: 700;
	color: #1B1330;
	text-align: center;
	margin-bottom: 28rpx;
}
.thug-item {
	display: flex;
	align-items: center;
	padding: 18rpx 12rpx;
	gap: 16rpx;
	border-radius: 16rpx;
}
.thug-check {
	width: 48rpx; height: 48rpx;
	flex-shrink: 0;
}
.thug-avatar {
	width: 90rpx; height: 90rpx;
	border-radius: 20rpx;
	flex-shrink: 0;
	background: #f5f5f5;
}
.thug-name {
	font-size: 28rpx;
	color: #333;
}
.popup-btn {
	margin-top: 28rpx;
	height: 84rpx;
	line-height: 84rpx;
	text-align: center;
	font-size: 30rpx;
	font-weight: 600;
	color: #fff;
	background: linear-gradient(135deg, #FF9D6E, #FF7A45);
	border-radius: 42rpx;
	box-shadow: 0 8rpx 20rpx rgba(255,122,69,0.4);
}
</style>
