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
			<text class="navbar-title">余额明细</text>
			<view class="navbar-placeholder"></view>
		</view>

		<scroll-view scroll-y class="content" @scrolltolower="loadMore">
			<view class="content-inner">
				<!-- 余额概览卡片 -->
				<view class="balance-summary-card">
					<image class="balance-card-bg" src="/static/images/wallet_background.png" mode="aspectFill"></image>
					<view class="balance-summary-inner">
						<view class="balance-row">
							<view class="balance-item">
								<text class="balance-label">可提现佣金</text>
								<text class="balance-value">{{ userInfo.available_money || '0.00' }}</text>
							</view>
							<view class="balance-item">
								<text class="balance-label">总佣金</text>
								<text class="balance-value">{{ userInfo.money || '0.00' }}</text>
							</view>
							<view class="balance-item">
								<text class="balance-label">冻结佣金</text>
								<text class="balance-value">{{ userInfo.freeze || '0.00' }}</text>
							</view>
						</view>
						<view class="withdraw-btn" @click="gotoWithdraw">余额提现</view>
					</view>
				</view>

				<!-- 记录列表 -->
				<view class="record-list">
					<view
						class="record-card"
						v-for="(item, index) in list"
						:key="index"
					>
						<view class="record-left">
							<text class="record-title">{{ item.memo || '余额变动' }}</text>
							<text class="record-remark" v-if="item.before !== undefined">变动前: {{ item.before }} → 变动后: {{ item.after }}</text>
						</view>
						<view class="record-right">
							<text :class="['record-amount', item.balance < 0 ? 'negative' : '']">
								{{ item.balance >= 0 ? '+' : '' }}{{ item.balance }}
							</text>
							<text class="record-time">{{ item.createtime_text }}</text>
						</view>
					</view>

					<uv-empty v-if="!loading && !list.length" mode="data" text="暂无余额记录" style="margin-top:100rpx"></uv-empty>

					<view class="load-more" v-if="list.length">
						<text class="load-more-text">{{ loading ? '加载中...' : (noMore ? '没有更多了' : '') }}</text>
					</view>

					<view class="bottom-spacer"></view>
				</view>
			</view>
		</scroll-view>
	</view>
</template>

<script setup>
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { balanceLog, getUserInfo, thugMsg } from '../../request/api'

const userInfo = ref({})
const list = ref([])
const page = ref(1)
const loading = ref(false)
const noMore = ref(false)

onShow(() => {
	page.value = 1
	list.value = []
	noMore.value = false
	loadUserInfo()
	loadData()
})

const loadUserInfo = async () => {
	try {
		const res = await getUserInfo()
		userInfo.value = res.data || {}
	} catch (e) {
		console.log('getUserInfo error:', e)
	}
	// 打手身份时加载打手佣金和冻结数据（独立try-catch，不受getUserInfo影响）
	try {
		const type = uni.getStorageSync('type')
		const thugId = uni.getStorageSync('id')
		if (thugId && type !== '用户') {
			const thugRes = await thugMsg({ thug_id: thugId })
			console.log('thugMsg res:', JSON.stringify(thugRes))
			if (thugRes && thugRes.code == 1 && thugRes.data) {
				userInfo.value = Object.assign({}, userInfo.value, {
					available_money: Number(thugRes.data.available_money || 0).toFixed(2),
					money: Number(thugRes.data.money || 0).toFixed(2),
					freeze: Number(thugRes.data.freeze || 0).toFixed(2)
				})
			}
		}
	} catch (e) {
		console.log('thugMsg error:', e)
	}
}

const loadData = async () => {
	if (loading.value) return
	loading.value = true
	try {
		const res = await balanceLog({ page: page.value, limit: 15 })
		// 兼容多种返回结构
		let resData = res.data || res
		let newList = []
		if (Array.isArray(resData)) {
			newList = resData
		} else if (resData && Array.isArray(resData.list)) {
			newList = resData.list
		} else if (resData && resData.data && Array.isArray(resData.data.list)) {
			newList = resData.data.list
		}
		if (page.value === 1) {
			list.value = newList
		} else {
			list.value = [...list.value, ...newList]
		}
		if (newList.length < 15) {
			noMore.value = true
		}
	} catch (e) {
		console.log('balanceLog error:', e)
	} finally {
		loading.value = false
	}
}

const loadMore = () => {
	if (noMore.value || loading.value) return
	page.value++
	loadData()
}

const gotoWithdraw = () => {
	uni.navigateTo({ url: '/pages/playerMain/ti' })
}

const goHome = () => {
	uni.reLaunch({ url: '/pages/index/index' })
}

const goBack = () => {
	// #ifdef H5
	const pages = getCurrentPages()
	if (pages.length > 1) {
		uni.navigateBack()
	} else {
		uni.reLaunch({ url: '/pages/mine/index' })
	}
	// #endif
	// #ifndef H5
	uni.navigateBack()
	// #endif
}
</script>

<style scoped>
.page {
	min-height: 100vh;
	background: #f5f6f9;
	display: flex;
	flex-direction: column;
}

/* 顶部背景 */
.top-bg {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	width: 100%;
	height: 480rpx;
	z-index: 0;
}

/* 导航栏 */
.navbar {
	position: relative;
	z-index: 20;
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
.navbar-btn {
	padding: 4rpx 8rpx;
}
.navbar-divider {
	width: 1rpx;
	height: 32rpx;
	background: #dadbde;
	margin: 0 8rpx;
}
.navbar-title {
	font-size: 34rpx;
	font-weight: 600;
	color: #333;
}
.navbar-placeholder {
	width: 120rpx;
}

/* 内容区 */
.content {
	position: relative;
	z-index: 10;
	flex: 1;
	min-height: 0;
	margin-top: -80rpx;
	padding: 120rpx 0 32rpx;
	box-sizing: border-box;
}
.content-inner {
	padding: 0;
	min-height: 100%;
	display: flex;
	flex-direction: column;
}

/* 余额概览卡片 */
.balance-summary-card {
	position: relative;
	border-radius: 24rpx;
	padding: 40rpx 32rpx;
	padding-bottom: 80rpx;
	margin: 0 24rpx 24rpx;
	box-shadow: 0 4rpx 20rpx rgba(0,0,0,0.1);
	overflow: hidden;
	min-height: 300rpx;
	box-sizing: border-box;
	background-color: #e8d5c4;
}
.balance-summary-card::before {
	content: "";
	position: absolute;
	top: -50%;
	right: -20%;
	width: 300rpx;
	height: 300rpx;
	background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
	border-radius: 50%;
	pointer-events: none;
}
.balance-card-bg {
	position: absolute;
	left: 0;
	top: 0;
	right: 0;
	bottom: 0;
	width: 100%;
	height: 100%;
	z-index: 0;
	display: block;
}
.balance-summary-inner {
	position: relative;
	z-index: 2;
	min-height: 200rpx;
	color: #1c0f05;
}
.balance-row {
	display: flex;
	justify-content: space-between;
	margin-bottom: 32rpx;
	gap: 20rpx;
}
.balance-item {
	flex: 1;
	display: flex;
	flex-direction: column;
	gap: 12rpx;
}
.balance-label {
	font-size: 24rpx;
	color: #1c0f05;
}
.balance-value {
	font-size: 44rpx;
	font-weight: 700;
	color: #000;
}
.withdraw-btn {
	width: 100%;
	height: 80rpx;
	background: linear-gradient(to right, #000, #291708);
	border-radius: 16rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 28rpx;
	color: #fef0e3;
	font-weight: 500;
}

/* 记录列表 */
.record-list {
	margin-top: -64rpx;
	padding: 32rpx 24rpx 24rpx;
	background: rgba(255,255,255,0.75);
	backdrop-filter: blur(24rpx);
	-webkit-backdrop-filter: blur(24rpx);
	border-radius: 32rpx 32rpx 0 0;
	box-shadow: 0 -2rpx 16rpx rgba(0,0,0,0.06);
	flex: 1;
}
.record-card {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	padding: 24rpx 0;
	border-bottom: 1rpx solid rgba(0,0,0,0.06);
}
.record-card:last-child {
	border-bottom: none;
}
.record-left {
	flex: 1;
	min-width: 0;
	display: flex;
	flex-direction: column;
	gap: 12rpx;
}
.record-title {
	font-size: 28rpx;
	color: #16181f;
	font-weight: 500;
}
.record-remark {
	font-size: 24rpx;
	color: #999;
}
.record-right {
	flex-shrink: 0;
	display: flex;
	flex-direction: column;
	align-items: flex-end;
	gap: 12rpx;
}
.record-amount {
	font-size: 32rpx;
	color: #a26d47;
	font-weight: 700;
}
.record-amount.negative {
	color: #000;
}
.record-time {
	font-size: 24rpx;
	color: #999;
}

.bottom-spacer {
	height: 80rpx;
}
.load-more {
	padding: 24rpx;
	text-align: center;
}
.load-more-text {
	font-size: 24rpx;
	color: #999;
}
</style>