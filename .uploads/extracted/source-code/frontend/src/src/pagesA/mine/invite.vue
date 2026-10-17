<template>
	<view class="invite-page">
		<!-- 导航栏 -->
		<view class="navbar" :style="{paddingTop: statusBarHeight + 'px'}">
			<view class="nav-back" @click="goBack">
				<text class="back-icon">‹</text>
			</view>
		</view>

		<!-- Logo + 标题 -->
		<view class="brand-area">
			<image class="brand-logo" src="/static/eon.png" mode="aspectFit"></image>
			<text class="brand-name">独眼蛇网络</text>
		</view>

		<!-- 注册秘钥输入 -->
		<view class="form-area">
			<text class="form-label">请输入注册秘钥</text>
			<input class="form-input" v-model="inviteCode" placeholder="请输入注册秘钥" placeholder-class="placeholder" />
		</view>

		<!-- 去入驻按钮 -->
		<view class="btn-area">
			<view :class="['btn-enter', !canSubmit ? 'disabled' : '']" @click="onEnter">去入驻</view>
		</view>

		<!-- 协议勾选 -->
		<view class="agreement-area">
			<view :class="['checkbox', agreed ? 'checked' : '']" @click="agreed = !agreed">
				<text class="check-icon" v-if="agreed">✓</text>
			</view>
			<text class="agreement-text">我已阅读并同意</text>
			<text class="agreement-link" @click="showAgreement = true">《入驻协议》</text>
		</view>

		<!-- 入驻协议弹窗 -->
		<view class="modal-mask" v-if="showAgreement" @click="showAgreement = false">
			<view class="modal-box" @click.stop>
				<text class="modal-title">入驻协议提醒</text>
				<text class="modal-desc">为了保障你的权益，请仔细阅读并同意《入驻协议》后继续入驻。</text>
				<view class="modal-btns">
					<view class="modal-btn btn-cancel" @click="showAgreement = false">
						<text>不同意</text>
					</view>
					<view class="modal-btn btn-confirm" @click="agreeProtocol">
						<text>同意</text>
					</view>
				</view>
			</view>
		</view>

		<!-- 加载提示 -->
		<view class="loading-mask" v-if="loading">
			<view class="loading-box">
				<text class="loading-text">校验中...</text>
			</view>
		</view>
	</view>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { inviteCheck } from '../../request/api'
import router from '../../utils/router'

const statusBarHeight = ref(44)
const inviteCode = ref('')
const agreed = ref(false)
const showAgreement = ref(false)
const loading = ref(false)

try {
	const sysInfo = uni.getSystemInfoSync()
	statusBarHeight.value = sysInfo.statusBarHeight || 44
} catch (e) {}

const canSubmit = computed(() => {
	return inviteCode.value.trim() !== '' && agreed.value
})

onMounted(() => {
	// 支持从URL参数带入邀请码
	const pages = getCurrentPages()
	const page = pages[pages.length - 1]
	const opts = page && page.options ? page.options : {}
	if (opts.code) {
		inviteCode.value = opts.code
	}
})

const goBack = () => {
	const pages = getCurrentPages()
	if (pages.length > 1) {
		uni.navigateBack({ delta: 1 })
	} else {
		router.reLaunch({ url: 'pages/mine/index' })
	}
}

const agreeProtocol = () => {
	agreed.value = true
	showAgreement.value = false
}

const onEnter = async () => {
	if (!inviteCode.value.trim()) {
		return uni.showToast({ title: '请输入注册秘钥', icon: 'none' })
	}
	if (!agreed.value) {
		return uni.showToast({ title: '请先同意入驻协议', icon: 'none' })
	}
	loading.value = true
	try {
		const res = await inviteCheck({ code: inviteCode.value.trim() })
		loading.value = false
		if (res.code === 1 && res.data) {
			const type = res.data.type
			const name = res.data.name || ''
			uni.showToast({ title: `邀请人：${name}`, icon: 'none' })
			setTimeout(() => {
				if (type === 'cs') {
					// 客服邀请码 → 跳客服入驻
					router.navigateTo({
						url: 'pagesA/mine/csruzhu',
						data: { invite_code: inviteCode.value.trim(), inviter_name: name }
					})
				} else {
					// 打手邀请码 → 跳打手入驻
					router.navigateTo({
						url: 'pagesA/mine/ruzhu',
						data: { invite_code: inviteCode.value.trim(), inviter_name: name }
					})
				}
			}, 800)
		} else {
			uni.showToast({ title: res.msg || '邀请码无效', icon: 'none' })
		}
	} catch (e) {
		loading.value = false
		uni.showToast({ title: '网络异常，请重试', icon: 'none' })
	}
}
</script>

<style scoped>
.invite-page {
	min-height: 100vh;
	background: #0a0a0a;
	display: flex;
	flex-direction: column;
	padding: 0 48rpx;
	position: relative;
}

.navbar {
	height: 88rpx;
	display: flex;
	align-items: center;
}

.nav-back {
	width: 60rpx;
	height: 60rpx;
	display: flex;
	align-items: center;
	justify-content: center;
}

.back-icon {
	font-size: 56rpx;
	color: #fff;
	line-height: 1;
}

.brand-area {
	display: flex;
	flex-direction: column;
	align-items: center;
	margin-top: 80rpx;
	margin-bottom: 100rpx;
}

.brand-logo {
	width: 180rpx;
	height: 180rpx;
	border-radius: 36rpx;
	margin-bottom: 32rpx;
}

.brand-name {
	font-size: 48rpx;
	font-weight: 700;
	color: #fff;
	letter-spacing: 4rpx;
}

.form-area {
	margin-bottom: 60rpx;
}

.form-label {
	display: block;
	font-size: 28rpx;
	color: #fff;
	margin-bottom: 20rpx;
}

.form-input {
	width: 100%;
	height: 96rpx;
	background: rgba(255, 255, 255, 0.08);
	border-radius: 16rpx;
	padding: 0 28rpx;
	font-size: 30rpx;
	color: #fff;
	box-sizing: border-box;
}

.placeholder {
	color: rgba(255, 255, 255, 0.3);
}

.btn-area {
	margin-bottom: 40rpx;
}

.btn-enter {
	width: 100%;
	height: 96rpx;
	line-height: 96rpx;
	text-align: center;
	font-size: 32rpx;
	color: #fff;
	font-weight: 600;
	background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
	border-radius: 16rpx;
}

.btn-enter.disabled {
	background: rgba(255, 255, 255, 0.15);
	color: rgba(255, 255, 255, 0.4);
}

.agreement-area {
	display: flex;
	flex-direction: row;
	align-items: center;
	justify-content: center;
	gap: 8rpx;
}

.checkbox {
	width: 32rpx;
	height: 32rpx;
	border: 2rpx solid rgba(255, 255, 255, 0.4);
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
}

.checkbox.checked {
	background: #8b5cf6;
	border-color: #8b5cf6;
}

.check-icon {
	font-size: 22rpx;
	color: #fff;
	line-height: 1;
}

.agreement-text {
	font-size: 24rpx;
	color: rgba(255, 255, 255, 0.5);
}

.agreement-link {
	font-size: 24rpx;
	color: #8b5cf6;
}

/* 协议弹窗 */
.modal-mask {
	position: fixed;
	top: 0; left: 0; right: 0; bottom: 0;
	background: rgba(0, 0, 0, 0.6);
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 999;
}

.modal-box {
	width: 600rpx;
	background: #fff;
	border-radius: 24rpx;
	padding: 48rpx 40rpx 32rpx;
}

.modal-title {
	display: block;
	font-size: 34rpx;
	font-weight: 700;
	color: #000;
	text-align: center;
	margin-bottom: 24rpx;
}

.modal-desc {
	display: block;
	font-size: 28rpx;
	color: #666;
	line-height: 1.6;
	margin-bottom: 40rpx;
}

.modal-btns {
	display: flex;
	flex-direction: row;
	gap: 20rpx;
}

.modal-btn {
	flex: 1;
	height: 80rpx;
	line-height: 80rpx;
	text-align: center;
	border-radius: 999rpx;
	font-size: 28rpx;
}

.btn-cancel {
	background: #f5f5f5;
	color: #666;
}

.btn-confirm {
	background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
	color: #fff;
}

/* 加载 */
.loading-mask {
	position: fixed;
	top: 0; left: 0; right: 0; bottom: 0;
	background: rgba(0, 0, 0, 0.5);
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 1000;
}

.loading-box {
	background: rgba(0, 0, 0, 0.8);
	padding: 32rpx 48rpx;
	border-radius: 16rpx;
}

.loading-text {
	font-size: 28rpx;
	color: #fff;
}
</style>
