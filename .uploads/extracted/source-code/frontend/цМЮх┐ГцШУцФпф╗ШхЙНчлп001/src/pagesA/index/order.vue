<template>
	<view class="page">
		<!-- 顶部背景 -->
		<image class="top-bg" src="/static/images/header_background.png" mode="aspectFill"></image>

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
						<text class="form-label">游戏昵称</text>
						<input class="form-input" v-model="queryParams.wz_id" placeholder="请输入游戏昵称" placeholder-class="placeholder" />
					</view>
					<!-- ID账号 -->
					<view class="form-row">
						<text class="form-label">ID账号</text>
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
.page {
	min-height: 100vh;
	background: #f5f6f9;
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

/* 导航栏 */
.navbar {
	position: relative;
	z-index: 10;
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 0 24rpx;
	padding-top: calc(var(--status-bar-height, 0px) + 16rpx);
	background: #fff;
}
.navbar-capsule {
	display: flex;
	align-items: center;
	border: 1rpx solid #dadbde;
	border-radius: 100px;
	padding: 8rpx 20rpx;
	backdrop-filter: blur(10px);
	-webkit-backdrop-filter: blur(10px);
	opacity: 0.95;
	background: rgba(255,255,255,0.3);
}
.navbar-btn { padding: 4rpx 8rpx; }
.navbar-divider {
	width: 1rpx; height: 32rpx;
	background: #dadbde;
	margin: 0 8rpx;
}
.navbar-title {
	font-size: 34rpx;
	font-weight: 600;
	color: #333;
}
.navbar-placeholder { width: 120rpx; }

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
	padding: 24rpx 32rpx;
	background: #fff;
	margin-bottom: 24rpx;
	border-radius: 36rpx;
	box-shadow: 0 2rpx 20rpx rgba(0,0,0,0.04);
}
.product-count {
	display: block;
	font-size: 24rpx;
	color: #666;
	margin-bottom: 20rpx;
}
.product-body {
	display: flex;
	gap: 20rpx;
	padding-bottom: 20rpx;
	border-bottom: 1rpx solid rgba(0,0,0,0.08);
}
.product-thumb {
	width: 160rpx; height: 160rpx;
	flex-shrink: 0;
	border-radius: 26rpx;
	background: #f0f0f0;
}
.product-info {
	flex: 1;
	min-width: 0;
}
.product-name {
	font-size: 28rpx;
	font-weight: 600;
	color: #000;
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
	color: #da4a44;
	font-weight: 600;
}
.price-symbol {
	font-size: 26rpx;
	color: #da4a44;
	font-weight: 600;
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
.product-row-total .price-num { font-size: 32rpx; }

/* 表单卡片 */
.card-glass {
	background: rgba(255,255,255,0.92);
	backdrop-filter: blur(24rpx);
	-webkit-backdrop-filter: blur(24rpx);
	border: 1rpx solid rgba(255,255,255,0.8);
	border-radius: 36rpx;
	box-shadow: 0 2rpx 20rpx rgba(0,0,0,0.04);
	margin-bottom: 24rpx;
}
.form-card { padding: 32rpx; }
.form-row {
	margin-bottom: 24rpx;
	padding-bottom: 24rpx;
	border-bottom: 1rpx solid rgba(0,0,0,0.08);
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
	font-size: 24rpx;
	color: #000;
	font-weight: 400;
	margin-bottom: 16rpx;
}
.form-input {
	width: 100%;
	height: 80rpx;
	padding: 0 24rpx;
	background: rgba(0,0,0,0.05);
	border-radius: 12rpx;
	font-size: 24rpx;
	color: #333;
	box-sizing: border-box;
}
.placeholder { color: #999; }

/* 选项标签组 */
.option-group {
	display: flex;
	flex-wrap: wrap;
	gap: 16rpx;
}
.option-tag {
	padding: 16rpx 32rpx;
	background: rgba(0,0,0,0.06);
	border-radius: 12rpx;
	font-size: 26rpx;
	color: #666;
}
.option-tag.active {
	background: #000;
	color: #fff;
}

/* 数量 */
.quantity-wrap {
	display: flex;
	align-items: center;
	gap: 24rpx;
}
.qty-btn {
	width: 56rpx; height: 56rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	background: rgba(0,0,0,0.06);
	border-radius: 8rpx;
	font-size: 32rpx;
	color: #333;
}
.qty-num {
	font-size: 28rpx;
	color: #333;
	min-width: 48rpx;
	text-align: center;
}

/* 下单说明 */
.notice-card { padding: 24rpx 0 0; }
.notice-inner {
	font-size: 22rpx;
	color: #a26d47;
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
	box-shadow: 0 -2rpx 12rpx rgba(0,0,0,0.06);
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
	font-size: 32rpx;
	color: #da4a44;
	font-weight: 600;
}
.btn-settle {
	height: 80rpx;
	line-height: 80rpx;
	min-width: 320rpx;
	padding: 0 48rpx;
	font-size: 28rpx;
	color: #fff;
	background: linear-gradient(135deg, #ffc300, #faad14);
	border-radius: 16rpx;
	text-align: center;
}

/* 选择打手弹窗 */
.popup-wrap {
	width: 600rpx;
	padding: 32rpx;
	background: #fff;
	border-radius: 24rpx;
}
.popup-title {
	font-size: 30rpx;
	font-weight: 600;
	color: #000;
	text-align: center;
	margin-bottom: 24rpx;
}
.thug-item {
	display: flex;
	align-items: center;
	padding: 16rpx 0;
	gap: 16rpx;
}
.thug-check {
	width: 48rpx; height: 48rpx;
	flex-shrink: 0;
}
.thug-avatar {
	width: 90rpx; height: 90rpx;
	border-radius: 20rpx;
	flex-shrink: 0;
}
.thug-name {
	font-size: 28rpx;
	color: #333;
}
.popup-btn {
	margin-top: 24rpx;
	height: 80rpx;
	line-height: 80rpx;
	text-align: center;
	font-size: 28rpx;
	color: #fff;
	background: linear-gradient(135deg, #ffc300, #faad14);
	border-radius: 16rpx;
}
</style>
