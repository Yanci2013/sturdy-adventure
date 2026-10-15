<template>
	<view class="page">
		<!-- 顶部背景 -->
		<image class="top-bg" src="/static/images/header_background.png" mode="aspectFill"></image>

		<!-- 导航栏 -->
		<view class="navbar" :style="{paddingTop: statusBarHeight + 'px'}">
			<view class="navbar-capsule">
				<view class="navbar-btn" @click="goBack">
					<up-icon name="arrow-left" size="34rpx" color="#606266"></up-icon>
				</view>
				<view class="navbar-divider"></view>
				<view class="navbar-btn" @click="goHome">
					<up-icon name="home" size="36rpx" color="#606266"></up-icon>
				</view>
			</view>
			<text class="navbar-title">保证金充值</text>
			<view class="navbar-placeholder"></view>
		</view>

		<scroll-view scroll-y class="content">
			<view class="content-inner">
				<!-- 当前保证金卡片 -->
				<view class="card card-glass balance-card">
					<view class="balance-row">
						<image class="balance-icon" src="/static/icon/money.png" mode="aspectFit"></image>
						<text class="balance-label">当前保证金</text>
						<text class="balance-num">{{ bond }}</text>
						<text class="balance-unit">元</text>
					</view>
				</view>

				<!-- 选择充值金额 -->
				<view class="card card-glass amount-card">
					<view class="section-label">选择充值金额</view>
					<view class="amount-grid">
						<view
							v-for="(item, index) in amountList"
							:key="index"
							:class="['amount-item', selectedIndex === index && !isCustom ? 'amount-item--active' : '']"
							@click="selectAmount(index)"
						>
							<view class="amount-tag" v-if="item.recommend">推荐</view>
							<text class="amount-value">{{ item.label }}</text>
							<text class="amount-desc">{{ item.amount }}元</text>
						</view>
						<view
							:class="['amount-item', isCustom ? 'amount-item--active' : '']"
							@click="selectCustom"
						>
							<text class="amount-value">自定义</text>
							<text class="amount-desc">自定义充值</text>
						</view>
					</view>
				</view>

				<!-- 按钮区 -->
				<view class="card card-glass btn-card">
					<button class="btn-primary" @click="openCashier">确定充值</button>
				</view>

				<view class="bottom-spacer"></view>
			</view>
		</scroll-view>

		<!-- 自定义金额弹窗 -->
		<uv-modal ref="customModal" title="自定义金额" :showConfirmButton="true" confirmText="确定" @confirm="confirmCustomAmount">
			<view class="slot-content">
				<uv-input v-model="customAmount" type="number" placeholder="请输入充值金额" />
			</view>
		</uv-modal>

		<!-- 收银台弹窗 -->
		<view class="cashier-mask" v-if="showCashier" @click="closeCashier">
			<view class="cashier-popup" @click.stop>
				<view class="cashier-header">
					<text class="cashier-title">收银台</text>
					<view class="cashier-close" @click="closeCashier"><text class="close-x">×</text></view>
				</view>
				<view class="cashier-amount-row">
					<text class="cashier-symbol">¥</text>
					<text class="cashier-amount">{{ currentAmount }}</text>
				</view>
				<view class="cashier-methods">
					<view class="cashier-method" @click="payType = 'balance'">
						<view class="cashier-method-left">
							<uv-icon name="red-packet" size="44rpx"></uv-icon>
							<text class="cashier-method-name">余额支付</text>
						</view>
						<view :class="['cashier-radio', payType === 'balance' ? 'cashier-radio-active' : '']"></view>
					</view>
					<view class="cashier-method" @click="payType = 'yipay'">
						<view class="cashier-method-left">
							<uv-icon name="weixin-circle-fill" size="44rpx" color="#07c160"></uv-icon>
							<text class="cashier-method-name">在线支付</text>
						</view>
						<view :class="['cashier-radio', payType === 'yipay' ? 'cashier-radio-active' : '']"></view>
					</view>
				</view>
				<button class="cashier-pay-btn" @click="confirmPay">立即支付</button>
			</view>
		</view>
	</view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { thugMsg, thugBond } from '../../request/api'
import payUtil from '../../utils/pay.js'
import router from '../../utils/router'

const statusBarHeight = ref(44)
try {
	const sysInfo = uni.getSystemInfoSync()
	statusBarHeight.value = sysInfo.statusBarHeight || 44
} catch (e) { statusBarHeight.value = 44 }

const bond = ref(0)
const bondMoney = ref(0)
const selectedIndex = ref(0)
const isCustom = ref(false)
const customAmount = ref('')
const customModal = ref(null)
const showCashier = ref(false)
const payType = ref('yipay')

const amountList = ref([
	{ label: '50', amount: 50, recommend: false },
	{ label: '100', amount: 100, recommend: true },
	{ label: '200', amount: 200, recommend: false },
	{ label: '500', amount: 500, recommend: false },
])

const currentAmount = computed(() => {
	if (isCustom.value) return Number(customAmount.value) || 0
	return amountList.value[selectedIndex.value]?.amount || 0
})

onShow(() => {
	thugMsg({ thug_id: uni.getStorageSync('id') }).then(res => {
		bond.value = Number(res.data.bond) || 0
		bondMoney.value = Number(res.data.bond_money) || 0
		const cfg = Number(res.data.bond_money) || 100
		amountList.value = [
			{ label: '10', amount: 10, recommend: false },
			{ label: '20', amount: 20, recommend: true },
			{ label: '50', amount: 50, recommend: false },
			{ label: '100', amount: 100, recommend: false },
		]
	})
})

const selectAmount = (index) => { selectedIndex.value = index; isCustom.value = false }
const selectCustom = () => { isCustom.value = true; customModal.value.open() }
const confirmCustomAmount = () => {
	if (!customAmount.value || Number(customAmount.value) <= 0) {
		uni.showToast({ title: '请输入有效金额', icon: 'none' }); return
	}
	customModal.value.close()
}

const openCashier = () => {
	if (currentAmount.value <= 0) {
		uni.showToast({ title: '请选择充值金额', icon: 'none' }); return
	}
	showCashier.value = true
}
const closeCashier = () => { showCashier.value = false }

const confirmPay = async () => {
	const amount = currentAmount.value
	if (amount <= 0) return
	closeCashier()
	try {
		uni.showLoading({ title: '正在支付...', mask: true })
		const res = await thugBond({ money: amount, pay_type: payType.value })
		uni.hideLoading()
		if (payType.value === 'balance') {
			if (res.code == 1) {
				uni.showToast({ title: '保证金充值成功', icon: 'success' })
				setTimeout(() => { router.navigateBack() }, 1500)
			}
		} else {
			const payParams = res.data
			payUtil.redirectPay(payParams, (err) => {
				uni.showToast({ title: err || '无法打开易支付', icon: 'none' })
			})
		}
	} catch (e) {
		uni.hideLoading()
		uni.showToast({ title: '充值失败', icon: 'none' })
	}
}

const goBack = () => { uni.navigateBack() }
const goHome = () => { uni.reLaunch({ url: '/pages/index/index' }) }
</script>

<style scoped>
.page { min-height: 100vh; background: #f5f7fa; display: flex; flex-direction: column; }
.top-bg { position: fixed; top: 0; left: 0; right: 0; width: 100%; height: 480rpx; z-index: 0; }
.navbar { position: relative; z-index: 20; display: flex; align-items: center; justify-content: space-between; padding: 0 24rpx; padding-bottom: 16rpx; }
.navbar-capsule { display: flex; align-items: center; border: 1rpx solid #dadbde; border-radius: 100px; padding: 8rpx 20rpx; background: rgba(255,255,255,0.3); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); }
.navbar-btn { padding: 4rpx 8rpx; }
.navbar-divider { width: 1rpx; height: 32rpx; background: #dadbde; margin: 0 8rpx; }
.navbar-title { font-size: 34rpx; font-weight: 600; color: #333; }
.navbar-placeholder { width: 120rpx; }
.content { position: relative; z-index: 10; flex: 1; min-height: 0; padding: 0 0 32rpx; box-sizing: border-box; }
.content-inner { padding: 24rpx; box-sizing: border-box; }
.card { width: 100%; box-sizing: border-box; margin-bottom: 24rpx; }
.card-glass { background: rgba(255,255,255,0.55); backdrop-filter: blur(24rpx); -webkit-backdrop-filter: blur(24rpx); border: 1rpx solid rgba(255,255,255,0.7); border-radius: 24rpx; padding: 28rpx; box-shadow: 0 2rpx 20rpx rgba(0,0,0,0.04), inset 0 1rpx rgba(255,255,255,0.9); }
.balance-card { padding: 28rpx 32rpx; }
.balance-row { display: flex; align-items: center; gap: 12rpx; }
.balance-icon { width: 40rpx; height: 40rpx; flex-shrink: 0; }
.balance-label { font-size: 28rpx; color: #666; }
.balance-num { font-size: 36rpx; font-weight: 700; color: #000; }
.balance-unit { font-size: 26rpx; color: #666; }
.amount-card { padding: 28rpx 32rpx; }
.section-label { font-size: 28rpx; font-weight: 600; color: #333; margin-bottom: 24rpx; }
.amount-grid { display: flex; flex-wrap: wrap; gap: 24rpx; }
.amount-item { position: relative; width: calc((100% - 24rpx) / 2); box-sizing: border-box; padding: 32rpx 24rpx; background: rgba(255,255,255,0.6); border: 2rpx solid transparent; border-radius: 20rpx; text-align: center; transition: all 0.2s ease; }
.amount-item--active { background: #fff; border-color: #fcc10d; box-shadow: 0 4rpx 16rpx rgba(252,193,13,0.25); }
.amount-tag { position: absolute; top: 0; right: 0; padding: 4rpx 16rpx; background: #fcc10d; border-radius: 0 20rpx 0 12rpx; font-size: 20rpx; font-weight: 600; color: #333; }
.amount-value { display: block; font-size: 32rpx; font-weight: 700; color: #333; margin-bottom: 8rpx; }
.amount-desc { font-size: 24rpx; color: #999; }
.btn-card { padding: 28rpx 32rpx; display: flex; flex-direction: column; gap: 24rpx; }
.btn-primary { width: 100%; height: 88rpx; line-height: 88rpx; font-size: 30rpx; font-weight: 600; color: #fff; background: #000; border: none; border-radius: 32rpx; }
.bottom-spacer { height: 48rpx; }
.slot-content { width: 100%; padding: 0 8rpx; }
/* 收银台弹窗 */
.cashier-mask { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; display: flex; align-items: flex-end; justify-content: center; }
.cashier-popup { width: 100%; background: #fff; border-radius: 32rpx 32rpx 0 0; padding: 32rpx 32rpx calc(48rpx + env(safe-area-inset-bottom)); }
.cashier-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 32rpx; }
.cashier-title { font-size: 34rpx; font-weight: 700; color: #333; }
.cashier-close { width: 56rpx; height: 56rpx; display: flex; align-items: center; justify-content: center; }
.close-x { font-size: 48rpx; color: #999; line-height: 1; }
.cashier-amount-row { display: flex; align-items: baseline; justify-content: center; margin-bottom: 40rpx; }
.cashier-symbol { font-size: 32rpx; color: #da4a44; font-weight: 600; margin-right: 4rpx; }
.cashier-amount { font-size: 56rpx; color: #da4a44; font-weight: 700; }
.cashier-methods { margin-bottom: 40rpx; }
.cashier-method { display: flex; align-items: center; justify-content: space-between; padding: 24rpx 0; border-bottom: 1rpx solid rgba(0,0,0,0.06); }
.cashier-method:last-child { border-bottom: none; }
.cashier-method-left { display: flex; align-items: center; gap: 20rpx; }
.cashier-method-name { font-size: 28rpx; color: #333; }
.cashier-radio { width: 40rpx; height: 40rpx; border-radius: 50%; border: 3rpx solid #ddd; box-sizing: border-box; }
.cashier-radio-active { border-color: #faad14; background: #faad14; }
.cashier-pay-btn { width: 100%; height: 88rpx; line-height: 88rpx; font-size: 30rpx; font-weight: 600; color: #fff; background: linear-gradient(135deg, #ffc300, #faad14); border: none; border-radius: 32rpx; }
</style>
