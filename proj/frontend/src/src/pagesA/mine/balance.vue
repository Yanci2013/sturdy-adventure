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
			<text class="navbar-title">余额充值</text>
			<view class="navbar-placeholder"></view>
		</view>

		<scroll-view scroll-y class="content">
			<view class="content-inner">
				<!-- 我的余额卡片 -->
				<view class="balance-box">
					<text class="balance-label">我的余额</text>
					<view class="balance-amount">
						<text class="currency">¥</text>
						<text class="amount-num">{{ balance }}</text>
					</view>
				</view>

				<!-- 充值金额选择 -->
				<view class="amount-grid">
					<view
						v-for="(item, index) in amountList"
						:key="index"
						:class="['amount-item', selectedIndex === index ? 'active' : '']"
						@click="selectAmount(index)"
					>
						<text class="amount-receive">到账{{ item }}元</text>
						<text class="amount-pay">支付{{ item }}元</text>
					</view>
				</view>

				<view class="bottom-spacer"></view>
			</view>
		</scroll-view>

		<!-- 底部按钮区 -->
		<view class="footer-bar">
			<view class="agreement" @click="toggleAgree">
				<view :class="['checkbox', agreed ? 'checked' : '']">
					<text v-if="agreed" class="check-icon">✓</text>
				</view>
				<text class="agree-text">我已阅读并同意</text>
				<text class="agree-link" @click.stop="gotoAgreement">《用户充值协议》</text>
			</view>
			<view :class="['btn-pay', agreed ? '' : 'disabled']" @click="doRecharge">立即支付</view>
			<view class="log-link" @click="gotoBalanceLog">余额明细</view>
		</view>
	</view>
</template>

<script setup>
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getUserInfo, balanceRecharge } from '../../request/api'
import payUtil from '../../utils/pay'

const balance = ref('0.00')
const amountList = [1, 3, 5, 10, 30, 50, 100]
const selectedIndex = ref(0)
const agreed = ref(false)

onShow(() => {
	loadBalance()
})

const loadBalance = async () => {
	try {
		const res = await getUserInfo()
		balance.value = res.data.balance || '0.00'
	} catch (e) {
		console.log(e)
	}
}

const selectAmount = (index) => {
	selectedIndex.value = index
}

const toggleAgree = () => {
	agreed.value = !agreed.value
}

const getFinalAmount = () => {
	return amountList[selectedIndex.value] || 0
}

const doRecharge = async () => {
	if (!agreed.value) {
		return uni.showToast({ title: '请先阅读并同意充值协议', icon: 'none' })
	}
	const amount = getFinalAmount()
	if (amount <= 0) {
		return uni.showToast({ title: '请选择充值金额', icon: 'none' })
	}
	try {
		uni.showLoading({ title: '发起支付...' })
		const res = await balanceRecharge({ amount })
		uni.hideLoading()
		if (res.code != 1) {
			return uni.showToast({
				title: res.msg || '充值失败',
				icon: 'none',
				duration: 3000
			})
		}
		payUtil.redirectPay(res.data, (err) => {
			uni.showToast({ title: err || '无法打开易支付', icon: 'none', duration: 3000 })
		})
	} catch (e) {
		uni.hideLoading()
		const msg = e?.msg || e?.message || e?.data?.msg || e?.errMsg || '充值失败'
		uni.showToast({ title: msg, icon: 'none', duration: 3000 })
	}
}

const gotoBalanceLog = () => {
	uni.navigateTo({ url: '/pagesA/mine/balanceLog' })
}

const gotoAgreement = () => {
	uni.navigateTo({ url: '/pagesA/mine/agreement' })
}

const goHome = () => {
	uni.reLaunch({ url: '/pages/index/index' })
}

const goBack = () => {
	const pages = getCurrentPages()
	if (pages.length > 1) {
		uni.navigateBack()
	} else {
		uni.reLaunch({ url: '/pages/mine/index' })
	}
}
</script>

<style scoped>
.page {
	min-height: 100vh;
	background: #f5f5f5;
	display: flex;
	flex-direction: column;
}

/* 导航栏 */
.navbar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 0 24rpx;
	padding-top: calc(var(--status-bar-height, 0px) + 16rpx);
	background: #f5f5f5;
}
.navbar-capsule {
	display: flex;
	align-items: center;
	border: 1rpx solid #dadbde;
	border-radius: 100px;
	padding: 8rpx 20rpx;
}
.navbar-btn { padding: 4rpx 8rpx; }
.navbar-divider { width: 1rpx; height: 32rpx; background: #dadbde; margin: 0 8rpx; }
.navbar-title { font-size: 34rpx; font-weight: 600; color: #333; }
.navbar-placeholder { width: 120rpx; }

/* 内容区 */
.content { flex: 1; }
.content-inner { padding: 24rpx; }

/* 余额卡片 */
.balance-box {
	background: #fff;
	border: 2rpx solid #000;
	border-radius: 16rpx;
	padding: 32rpx;
	margin-bottom: 32rpx;
}
.balance-label {
	display: block;
	font-size: 26rpx;
	color: #333;
	margin-bottom: 16rpx;
}
.balance-amount {
	display: flex;
	align-items: baseline;
}
.currency {
	font-size: 36rpx;
	font-weight: 700;
	color: #000;
}
.amount-num {
	font-size: 56rpx;
	font-weight: 700;
	color: #000;
	line-height: 1;
}

/* 金额选择 */
.amount-grid {
	display: flex;
	flex-wrap: wrap;
	gap: 16rpx;
}
.amount-item {
	width: calc((100% - 32rpx) / 3);
	box-sizing: border-box;
	padding: 28rpx 16rpx;
	background: #f0f0f0;
	border: 2rpx solid transparent;
	border-radius: 16rpx;
	text-align: center;
	transition: all 0.2s ease;
}
.amount-item.active {
	background: #FFD84D;
	border-color: #FF7A45;
}
.amount-receive {
	display: block;
	font-size: 28rpx;
	font-weight: 600;
	color: #000;
	margin-bottom: 8rpx;
}
.amount-pay {
	display: block;
	font-size: 24rpx;
	color: #666;
}

.bottom-spacer { height: 40rpx; }

/* 底部按钮区 */
.footer-bar {
	padding: 20rpx 24rpx calc(20rpx + env(safe-area-inset-bottom));
	background: #f5f5f5;
}
.agreement {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8rpx;
	margin-bottom: 20rpx;
}
.checkbox {
	width: 32rpx;
	height: 32rpx;
	border: 2rpx solid #ccc;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	box-sizing: border-box;
}
.checkbox.checked {
	background: #FF7A45;
	border-color: #FF7A45;
}
.check-icon {
	font-size: 20rpx;
	color: #fff;
	line-height: 1;
}
.agree-text {
	font-size: 26rpx;
	color: #333;
}
.agree-link {
	font-size: 26rpx;
	color: #FF7A45;
}
.btn-pay {
	width: 100%;
	height: 88rpx;
	line-height: 88rpx;
	text-align: center;
	font-size: 32rpx;
	font-weight: 600;
	color: #000;
	background: #FFD84D;
	border-radius: 44rpx;
}
.btn-pay.disabled {
	opacity: 0.5;
}
.log-link {
	text-align: center;
	margin-top: 16rpx;
	font-size: 26rpx;
	color: #999;
}
</style>
