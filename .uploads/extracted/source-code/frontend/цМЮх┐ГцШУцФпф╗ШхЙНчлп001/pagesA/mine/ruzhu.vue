<template>
	<view class="page">
		<!-- 导航栏 -->
		<view class="navbar">
			<view class="navbar-capsule">
				<view class="navbar-btn" @click="goBack">
					<up-icon name="arrow-left" size="34rpx" color="#fff"></up-icon>
				</view>
			</view>
			<text class="navbar-title">打手入驻</text>
			<view class="navbar-placeholder"></view>
		</view>

		<view class="content">
			<!-- Logo 区域 -->
			<view class="logo-area">
				<image class="logo-img" src="/static/logo.png" mode="aspectFit"></image>
				<text class="brand-name">独眼蛇网络</text>
			</view>

			<!-- 入驻表单 -->
			<view class="form-area">
				<view class="form-group">
					<text class="form-label">请输入注册秘钥</text>
					<input class="form-input" v-model="secretKey" placeholder="请输入注册秘钥" placeholder-class="placeholder" />
				</view>

				<view class="btn-area">
					<view :class="['btn-submit', submitting ? 'disabled' : '']" @click="submit">去入驻</view>
				</view>

				<!-- 入驻协议 -->
				<view class="agreement-row">
					<view class="agreement-box" @click="toggleAgree">
						<view :class="['agree-checkbox', agreed ? 'checked' : '']">
							<text v-if="agreed" class="agree-check-icon">✓</text>
						</view>
						<text class="agree-text">我已阅读并同意</text>
						<text class="agree-link" @click.stop="gotoAgreement">《入驻协议》</text>
					</view>
				</view>
			</view>
		</view>

		<!-- 入驻协议提醒弹窗 -->
		<view class="agreement-modal-mask" v-if="showAgreeModal" @click="showAgreeModal = false">
			<view class="agreement-modal" @click.stop>
				<text class="agreement-modal-title">入驻协议提醒</text>
				<text class="agreement-modal-desc">为了保障你的权益，请仔细阅读并同意《入驻协议》后继续入驻。</text>
				<view class="agreement-modal-btns">
					<view class="agree-btn-cancel" @click="showAgreeModal = false">不同意</view>
					<view class="agree-btn-confirm" @click="confirmAgree">同意</view>
				</view>
			</view>
		</view>
	</view>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { thugAdd, getUserInfo } from '../../request/api';
import router from '../../utils/router';

const secretKey = ref('');
const agreed = ref(false);
const submitting = ref(false);
const showAgreeModal = ref(false);

const toggleAgree = () => {
	agreed.value = !agreed.value;
};

const confirmAgree = () => {
	agreed.value = true;
	showAgreeModal.value = false;
};

const gotoAgreement = () => {
	uni.navigateTo({ url: '/pagesA/mine/agreement?name=入驻协议' });
};

onMounted(() => {
	if (!uni.getStorageSync('token')) {
		uni.setStorageSync('navtype', 'ruzhu')
		return router.navigateTo({ url: 'pages/login/login' });
	}
	checkStatus();
});

const checkStatus = async () => {
	try {
		const res = await getUserInfo();
		if (res.data.thug && !Array.isArray(res.data.thug)) {
			const thug = res.data.thug;
			if (thug.sh_status == 1) {
				uni.showModal({
					title: '提示',
					content: '您已通过打手入驻审核',
					showCancel: false,
					success: () => uni.navigateBack({ delta: 1 })
				});
			} else if (thug.sh_status == 0) {
				uni.showModal({
					title: '提示',
					content: '您的入驻申请正在审核中，请耐心等待',
					showCancel: false,
					success: () => uni.navigateBack({ delta: 1 })
				});
			}
		}
	} catch (e) {}
};

const submit = async () => {
	if (submitting.value) return;

	if (!agreed.value) {
		showAgreeModal.value = true;
		return;
	}

	if (!secretKey.value.trim()) {
		return uni.showToast({ title: '请输入注册秘钥', icon: 'none' });
	}

	submitting.value = true;
	uni.showLoading({ title: '提交中...', mask: true });
	try {
		const res = await thugAdd({ secret_key: secretKey.value.trim() });
		if (res.code === 1) {
			uni.showToast({ title: res.msg || '入驻成功', icon: 'success' });
			setTimeout(() => uni.navigateBack({ delta: 1 }), 1500);
		} else {
			uni.showToast({ title: res.msg || '入驻失败', icon: 'none' });
		}
	} catch (e) {
		uni.showToast({ title: '网络异常，请重试', icon: 'none' });
	} finally {
		uni.hideLoading();
		submitting.value = false;
	}
};

const goBack = () => {
	const pages = getCurrentPages();
	if (pages.length > 1) {
		uni.navigateBack({ delta: 1 });
	} else {
		router.reLaunch({ url: 'pages/mine/index' });
	}
};
</script>

<style scoped>
.page {
	min-height: 100vh;
	background: #0a0a0a;
	display: flex;
	flex-direction: column;
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
	background: #0a0a0a;
}
.navbar-capsule {
	display: flex;
	align-items: center;
	border: 1rpx solid rgba(255,255,255,0.15);
	border-radius: 100px;
	padding: 8rpx 20rpx;
	background: rgba(255, 255, 255, 0.06);
}
.navbar-btn {
	padding: 4rpx 8rpx;
}
.navbar-title {
	font-size: 34rpx;
	font-weight: 600;
	color: #fff;
}
.navbar-placeholder {
	width: 120rpx;
}

/* 内容区 */
.content {
	flex: 1;
	padding: 80rpx 48rpx 0;
}

/* Logo 区域 */
.logo-area {
	display: flex;
	flex-direction: column;
	align-items: center;
	margin-bottom: 80rpx;
}
.logo-img {
	width: 160rpx;
	height: 160rpx;
	border-radius: 32rpx;
	margin-bottom: 32rpx;
}
.brand-name {
	font-size: 44rpx;
	font-weight: 700;
	color: #fff;
	letter-spacing: 4rpx;
}

/* 表单区域 */
.form-area {
	margin-top: 40rpx;
}
.form-group {
	margin-bottom: 40rpx;
}
.form-label {
	display: block;
	font-size: 30rpx;
	color: #fff;
	font-weight: 500;
	margin-bottom: 16rpx;
}
.form-input {
	width: 100%;
	height: 96rpx;
	padding: 0 32rpx;
	background: rgba(255,255,255,0.08);
	border-radius: 16rpx;
	font-size: 30rpx;
	color: #fff;
	box-sizing: border-box;
}
.placeholder {
	color: rgba(255,255,255,0.3);
}

/* 提交按钮 */
.btn-area {
	margin-top: 48rpx;
}
.btn-submit {
	width: 100%;
	height: 96rpx;
	line-height: 96rpx;
	text-align: center;
	font-size: 32rpx;
	color: #fff;
	font-weight: 600;
	background: linear-gradient(135deg, #3b5bdb, #845ef7);
	border-radius: 48rpx;
}
.btn-submit.disabled {
	opacity: 0.6;
}

/* 入驻协议 */
.agreement-row {
	margin-top: 32rpx;
	display: flex;
	justify-content: center;
}
.agreement-box {
	display: flex;
	align-items: center;
	gap: 10rpx;
}
.agree-checkbox {
	width: 32rpx;
	height: 32rpx;
	border: 2rpx solid rgba(255,255,255,0.3);
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	box-sizing: border-box;
	flex-shrink: 0;
}
.agree-checkbox.checked {
	background: linear-gradient(135deg, #3b5bdb, #845ef7);
	border-color: #845ef7;
}
.agree-check-icon {
	font-size: 20rpx;
	color: #fff;
	line-height: 1;
}
.agree-text {
	font-size: 26rpx;
	color: rgba(255,255,255,0.5);
}
.agree-link {
	font-size: 26rpx;
	color: #845ef7;
}

/* 入驻协议提醒弹窗 */
.agreement-modal-mask {
	position: fixed;
	top: 0; left: 0; right: 0; bottom: 0;
	background: rgba(0,0,0,0.6);
	z-index: 9999;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 0 60rpx;
	box-sizing: border-box;
}
.agreement-modal {
	width: 100%;
	max-width: 560rpx;
	background: #fff;
	border-radius: 24rpx;
	padding: 48rpx 40rpx 32rpx;
	box-sizing: border-box;
}
.agreement-modal-title {
	display: block;
	font-size: 32rpx;
	font-weight: 700;
	color: #000;
	text-align: center;
	margin-bottom: 24rpx;
}
.agreement-modal-desc {
	display: block;
	font-size: 26rpx;
	color: #666;
	line-height: 1.6;
	text-align: center;
	margin-bottom: 36rpx;
}
.agreement-modal-btns {
	display: flex;
	gap: 20rpx;
}
.agree-btn-cancel {
	flex: 1;
	height: 72rpx;
	line-height: 72rpx;
	text-align: center;
	font-size: 28rpx;
	color: #333;
	background: #f5f5f5;
	border-radius: 36rpx;
}
.agree-btn-confirm {
	flex: 1;
	height: 72rpx;
	line-height: 72rpx;
	text-align: center;
	font-size: 28rpx;
	color: #fff;
	font-weight: 600;
	background: linear-gradient(135deg, #3b5bdb, #845ef7);
	border-radius: 36rpx;
}
</style>
