<template>
	<view class="page">
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
			<text class="navbar-title">佣金提现</text>
			<view class="navbar-placeholder"></view>
		</view>

		<scroll-view scroll-y class="content">
			<view class="content-inner">
				<!-- 提现金额卡片 -->
				<view class="card card-glass">
					<view class="card-title">提现金额</view>
					<view class="amount-row">
						<text class="balance-hint">可用佣金 {{ ktx }} 元</text>
						<text class="link-all" @click="allClick">全部提现</text>
					</view>
					<view class="amount-input-wrap">
						<text class="amount-symbol">￥</text>
						<input class="amount-input" type="digit" v-model="money" placeholder="请输入提现金额" placeholder-class="amount-placeholder" />
					</view>
					<view class="card-actions">
						<view class="btn-secondary" @click="gotoSettlement">设置提现信息</view>
						<view class="btn-secondary" @click="gotoWithdrawLog">提现记录</view>
					</view>
				</view>

				<!-- 提现方式卡片 -->
				<view class="card card-glass">
					<view class="card-title">提现方式</view>
					<view class="option-list">
						<view
							v-for="(item, index) in methodList"
							:key="item.key"
							:class="['option-row', item.disabled ? 'disabled' : '']"
							@click="selectMethod(item)"
						>
							<image class="option-icon" :src="item.icon" mode="aspectFit"></image>
							<text class="option-label">{{ item.label }}</text>
							<text v-if="item.disabled" class="option-closed">未开放</text>
							<view v-else :class="['option-radio', currentMethod === item.key ? 'active' : '']">
								<text class="radio-check" v-if="currentMethod === item.key">✓</text>
							</view>
						</view>
					</view>
				</view>

				<!-- 底部按钮 -->
				<view class="footer-bar-static">
					<view class="btn-submit" @click="tiClick">发起提现</view>
				</view>

				<view class="bottom-spacer"></view>
			</view>
		</scroll-view>

	</view>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { thugMsg, thugApply, getUserInfo, userApply, configure } from '../../request/api'
import { base_url } from '../../request/env'

const ktx = ref('0.00')
const money = ref('')
const currentMethod = ref('')
const type = uni.getStorageSync('type')
const openChannels = ref([])

const allMethods = [
	{ key: 'alipay', label: '支付宝', icon: '/static/images/icon.png' },
	{ key: 'wechat', label: '微信', icon: '/static/images/wechat.png' },
	{ key: 'bank', label: '银行卡', icon: '/static/images/icon.png' }
]
const methodList = ref([])

onShow(() => {
	getData()
	loadConfig()
})

const loadConfig = async () => {
	try {
		const res = await configure()
		const channels = res.data.withdraw_channels || ['alipay']
		openChannels.value = channels
		methodList.value = allMethods.map(m => ({
			...m,
			disabled: !channels.includes(m.key)
		}))
		// 默认选中第一个开放的通道
		const firstOpen = methodList.value.find(m => !m.disabled)
		if (firstOpen) currentMethod.value = firstOpen.key
	} catch (e) {
		methodList.value = allMethods.map(m => ({ ...m, disabled: m.key !== 'alipay' }))
		currentMethod.value = 'alipay'
	}
}

const getData = () => {
	if (type == '用户') {
		getUserInfo().then(res => {
			ktx.value = Number(res.data.money || 0).toFixed(2)
		})
	} else {
		const thugId = uni.getStorageSync('id')
		console.log('ti.vue getData thugId:', thugId, 'type:', type)
		thugMsg({ thug_id: thugId }).then(res => {
			console.log('ti.vue thugMsg res:', JSON.stringify(res.data))
			// 新佣金系统：直接读 available_money
			ktx.value = Number(res.data.available_money || 0).toFixed(2)
		}).catch(err => {
			console.log('ti.vue thugMsg error:', err)
		})
	}
}

const allClick = () => {
	money.value = ktx.value
}

const selectMethod = (item) => {
	if (item.disabled) {
		uni.showToast({ title: '该通道未开放提现', icon: 'none' })
		return
	}
	currentMethod.value = item.key
}

const gotoSettlement = () => {
	uni.navigateTo({ url: '/pages/playerMain/settlement' })
}

const gotoWithdrawLog = () => {
	uni.navigateTo({ url: '/pages/playerMain/withdrawLog' })
}

const tiClick = () => {
	if (!money.value || Number(money.value) <= 0) {
		return uni.showToast({ title: '提现金额不能为空', icon: 'none' })
	}
	// 从缓存读取提现资料
	const settlementData = uni.getStorageSync('settlement_' + currentMethod.value) || {}
	if (!settlementData.account) {
		return uni.showToast({ title: '请先设置提现信息', icon: 'none' })
	}
	if (!settlementData.name) {
		return uni.showToast({ title: '请先设置提现信息', icon: 'none' })
	}

	const params = {
		name: settlementData.name,
		account: settlementData.account,
		money: money.value,
		images: settlementData.images || '',
		channel: currentMethod.value,
		bank_name: settlementData.bankName || ''
	}

	if (type == '用户') {
		userApply(params).then(res => {
			if (res.code == 1) {
				uni.showToast({ title: res.msg, icon: 'none' })
				setTimeout(() => { uni.navigateBack({ delta: 1 }) }, 1500)
			} else {
				uni.showToast({ title: res.msg, icon: 'none' })
			}
		})
	} else {
		thugApply(params).then(res => {
			if (res.code == 1) {
				uni.showToast({ title: res.msg, icon: 'none' })
				setTimeout(() => { uni.navigateBack({ delta: 1 }) }, 1500)
			} else {
				uni.showToast({ title: res.msg, icon: 'none' })
			}
		})
	}
}

const goBack = () => {
	uni.navigateBack()
}

const goHome = () => {
	uni.reLaunch({ url: '/pages/index/index' })
}
</script>

<style scoped>
.page {
	min-height: 100vh;
	background: #f5f6f9;
	display: flex;
	flex-direction: column;
	padding-bottom: calc(120rpx + env(safe-area-inset-bottom));
}
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
	background: rgba(255,255,255,0.3);
}
.navbar-btn { padding: 4rpx 8rpx; }
.navbar-divider { width: 1rpx; height: 32rpx; background: #dadbde; margin: 0 8rpx; }
.navbar-title { font-size: 34rpx; font-weight: 600; color: #333; }
.navbar-placeholder { width: 120rpx; }
.content { flex: 1; }
.content-inner { padding: 24rpx; box-sizing: border-box; }
.card { width: 100%; box-sizing: border-box; margin-bottom: 24rpx; }
.card-glass {
	background: rgba(255,255,255,0.55);
	backdrop-filter: blur(24rpx);
	-webkit-backdrop-filter: blur(24rpx);
	border: 1rpx solid rgba(255,255,255,0.7);
	border-radius: 24rpx;
	padding: 28rpx;
	box-shadow: 0 2rpx 20rpx rgba(0,0,0,0.04), inset 0 1rpx rgba(255,255,255,0.9);
}
.card-title {
	font-size: 32rpx;
	font-weight: 700;
	color: #000;
	margin-bottom: 24rpx;
	padding-bottom: 20rpx;
	border-bottom: 1rpx solid rgba(0,0,0,0.08);
}
.amount-row {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 16rpx;
}
.balance-hint { font-size: 26rpx; color: #666; }
.link-all { font-size: 26rpx; color: #333; }
.amount-input-wrap {
	display: flex;
	align-items: center;
	height: 96rpx;
	padding: 0 24rpx;
	background: rgba(0,0,0,0.05);
	border-radius: 16rpx;
	margin-bottom: 24rpx;
}
.amount-symbol { font-size: 40rpx; font-weight: 700; color: #000; margin-right: 12rpx; }
.amount-input { flex: 1; height: 96rpx; font-size: 36rpx; font-weight: 600; color: #000; }
.amount-placeholder { color: #999; font-weight: 400; }
.card-actions {
	display: flex;
	align-items: center;
	gap: 12rpx;
	flex-wrap: wrap;
	padding-top: 16rpx;
	border-top: 1rpx solid rgba(0,0,0,0.08);
}
.btn-secondary {
	flex: 1;
	min-width: 140rpx;
	height: 54rpx;
	line-height: 54rpx;
	text-align: center;
	font-size: 24rpx;
	color: #333;
	background: #f0f0f0;
	border-radius: 16rpx;
	padding: 0 20rpx;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.option-list { display: flex; flex-direction: column; gap: 0; }
.option-row {
	display: flex;
	align-items: center;
	padding: 24rpx 0;
	border-bottom: 1rpx solid rgba(0,0,0,0.06);
}
.option-row:last-child { border-bottom: none; }
.option-row.disabled { opacity: 0.5; }
.option-closed { font-size: 24rpx; color: #999; flex-shrink: 0; }
.option-icon { width: 48rpx; height: 48rpx; margin-right: 20rpx; flex-shrink: 0; }
.option-label { flex: 1; font-size: 28rpx; color: #333; }
.option-radio {
	width: 40rpx;
	height: 40rpx;
	border-radius: 50%;
	border: 2rpx solid #ddd;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
}
.option-radio.active {
	background: linear-gradient(135deg, #ffc300, #faad14);
	border-color: transparent;
}
.radio-check { font-size: 24rpx; color: #fff; font-weight: 700; }
.bottom-spacer { height: 40rpx; }
.footer-bar-static {
	padding: 0;
	margin-top: 20rpx;
}
.btn-submit {
	width: 100%;
	height: 88rpx;
	line-height: 88rpx;
	text-align: center;
	font-size: 30rpx;
	color: #fff;
	background: linear-gradient(135deg, #ffc300, #faad14);
	border-radius: 16rpx;
}
</style>
