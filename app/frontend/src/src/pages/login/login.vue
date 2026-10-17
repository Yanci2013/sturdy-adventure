<template>
	<view class="login-page">
		<!-- 科技感背景层 -->
		<view class="bg-layer">
			<view class="grid-bg"></view>
			<view class="glow glow-1"></view>
			<view class="glow glow-2"></view>
			<view class="scan-line"></view>
		</view>

		<view class="content">
			<!-- 品牌头部 -->
			<view class="brand-header">
				<view class="brand-logo-wrap">
					<view class="brand-logo">
						<text class="logo-text">创</text>
						<view class="logo-ring"></view>
					</view>
				</view>
				<text class="brand-name">{{ brandName }}</text>
				<text class="brand-sub">创世网络工作室</text>
			</view>

			<!-- 数据展示 -->
			<view class="stat-row">
				<view class="stat-box">
					<text class="stat-num">1000+</text>
					<text class="stat-label">俱乐部合作</text>
				</view>
				<view class="stat-divider"></view>
				<view class="stat-box">
					<text class="stat-num">10+</text>
					<text class="stat-label">行业深耕</text>
				</view>
			</view>

			<!-- 主按钮 -->
			<view class="btn-area">
				<view class="login-btn" :class="{ disabled: !agreement_protocol || submitting }" @click="handleLogin">
					<text class="btn-glow"></text>
					<text class="btn-text">{{ submitting ? '正在登录...' : '微信一键授权登录' }}</text>
				</view>

				<!-- 协议 -->
				<view class="agreement-row" @click.stop="agreement_protocol = !agreement_protocol">
					<view class="check-icon" :class="{ active: agreement_protocol }">
						<u-icon v-if="agreement_protocol" name="checkmark" size="22rpx" color="#0a0f0a"></u-icon>
					</view>
					<text class="agreement-text">登录即代表您同意</text>
					<text class="agreement-link" @click.stop="toAgreement('用户协议')">《用户协议》</text>
					<text class="agreement-text">与</text>
					<text class="agreement-link" @click.stop="toAgreement('隐私保护政策')">《隐私政策》</text>
				</view>
			</view>
		</view>
	</view>
</template>

<script>
import { base_url } from '../../request/env';
import { quickRegister, configure, juheLogin, getJuheConfig } from '../../request/api';
import router from '../../utils/router';

export default {
	data() {
		return {
			agreement_protocol: false,
			submitting: false,
			brandName: '创世网络',
			page: '',
			pageParams: {},
			juheConfig: { enabled: false, url: '', app_id: '' }
		}
	},
	onLoad(option) {
		configure().then(res => {
			if (res.code === 1 && res.data) {
				const name = res.data.club_name || res.data.brand_name || res.data.name
				if (name) this.brandName = name
			}
		}).catch(() => {})

		getJuheConfig().then(res => {
			if (res.code === 1 && res.data) {
				this.juheConfig = res.data
			}
		}).catch(() => {})

		if (option && option.page) {
			this.page = decodeURIComponent(option.page)
		}
		if (option && option.pageParams) {
			try {
				this.pageParams = JSON.parse(decodeURIComponent(option.pageParams))
			} catch (e) {
				this.pageParams = {}
			}
		}
		if (option && option.pid) {
			uni.setStorageSync('pid', option.pid)
		}
		if (option && option.code) {
			this.handleJuheCallback(option.code, option.type || 'wechat')
		}
	},
	methods: {
		toAgreement(name) {
			router.navigateTo({
				url: 'pagesA/mine/agreement',
				data: { name }
			})
		},
		checkAgreement() {
			if (this.agreement_protocol) return true
			uni.showModal({
				title: '提示',
				content: '请先阅读并同意用户协议和隐私政策',
				showCancel: false,
				confirmText: '确定'
			})
			return false
		},
		async handleLogin() {
			if (!this.checkAgreement() || this.submitting) return
			this.submitting = true
			uni.showLoading({ title: '正在登录...', mask: true })
			try {
				const mode = this.juheConfig.login_mode || 'both'
				const juheEnabled = this.juheConfig.enabled && this.juheConfig.url

				if (mode === 'juhe' && !juheEnabled) {
					uni.hideLoading()
					this.submitting = false
					return uni.showModal({
						title: '登录未启用',
						content: '一键登录需在后台管理配置聚合登录接口后才能使用，请联系管理员。',
						showCancel: false,
						confirmText: '我知道了'
					})
				}

				if (juheEnabled) {
					const cbUrl = window.location.origin + window.location.pathname
					const redirect = encodeURIComponent(cbUrl + '?type=wechat')
					window.location.href = this.juheConfig.url + '?app_id=' + this.juheConfig.app_id + '&redirect=' + redirect
					return
				}
				const mockCode = 'mock_' + Date.now()
				const res = await juheLogin({ code: mockCode, type: 'wechat', pid: uni.getStorageSync('pid') || 0 })
				if (res.code === 1 && res.data && res.data.userinfo) {
					const userType = this.saveSession(res.data.userinfo)
					uni.showToast({ title: '登录成功', icon: 'success' })
					setTimeout(() => this.goIndex(userType), 500)
				} else {
					uni.showToast({ title: res.msg || '登录失败', icon: 'none' })
				}
			} catch (err) {
				uni.showToast({ title: '网络异常，请重试', icon: 'none' })
			} finally {
				uni.hideLoading()
				this.submitting = false
			}
		},
		async handleJuheCallback(code, type) {
			uni.showLoading({ title: '正在登录...', mask: true })
			try {
				const res = await juheLogin({ code, type, pid: uni.getStorageSync('pid') || 0 })
				if (res.code === 1 && res.data && res.data.userinfo) {
					const userType = this.saveSession(res.data.userinfo)
					uni.showToast({ title: '登录成功', icon: 'success' })
					setTimeout(() => this.goIndex(userType), 500)
				} else {
					uni.showToast({ title: res.msg || '登录失败', icon: 'none' })
				}
			} catch (e) {
				uni.showToast({ title: '网络异常，请重试', icon: 'none' })
			} finally {
				uni.hideLoading()
			}
		},
		saveSession(data) {
			const userType = String(data.type) === '2' ? '打手' : '用户'
			uni.setStorageSync('token', data.token)
			uni.setStorageSync('type', userType)
			uni.setStorageSync('id', data.thug_id || 0)
			uni.setStorageSync('user_id', data.id || '')
			uni.setStorageSync('avatar', data.avatar || '')
			uni.setStorageSync('nickname', data.nickname || '')
			return userType
		},
		goIndex(userType) {
			if (this.page) {
				let url = (this.page.startsWith('/') ? '' : '/') + this.page
				if (this.pageParams && Object.keys(this.pageParams).length) {
					const query = Object.keys(this.pageParams)
						.map(key => `${encodeURIComponent(key)}=${encodeURIComponent(this.pageParams[key])}`)
						.join('&')
					url += '?' + query
				}
				uni.reLaunch({ url })
			} else if (userType === '打手') {
				router.reLaunch({ url: 'pages/playerMain/index' })
			} else {
				router.reLaunch({ url: 'pages/index/index' })
			}
		}
	}
}
</script>

<style scoped>
.login-page {
	min-height: 100vh;
	background: #050a05;
	display: flex;
	flex-direction: column;
	position: relative;
	overflow: hidden;
}

/* 暗黑绿科技背景 */
.bg-layer {
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	z-index: 0;
	overflow: hidden;
	background: radial-gradient(ellipse at 50% 30%, #0a1f12 0%, #050a05 60%, #020402 100%);
}

.grid-bg {
	position: absolute;
	inset: 0;
	background-image:
		linear-gradient(rgba(34, 197, 94, 0.06) 1px, transparent 1px),
		linear-gradient(90deg, rgba(34, 197, 94, 0.06) 1px, transparent 1px);
	background-size: 40rpx 40rpx;
	mask-image: radial-gradient(ellipse at center, #000 0%, transparent 75%);
	-webkit-mask-image: radial-gradient(ellipse at center, #000 0%, transparent 75%);
}

.glow {
	position: absolute;
	border-radius: 50%;
	filter: blur(80rpx);
	opacity: 0.55;
}
.glow-1 {
	width: 520rpx;
	height: 520rpx;
	background: radial-gradient(circle, #22c55e 0%, transparent 70%);
	top: -120rpx;
	left: 50%;
	transform: translateX(-50%);
	opacity: 0.45;
}
.glow-2 {
	width: 600rpx;
	height: 600rpx;
	background: radial-gradient(circle, #15803d 0%, transparent 70%);
	bottom: -200rpx;
	right: -120rpx;
	opacity: 0.35;
}

.scan-line {
	position: absolute;
	left: 0;
	right: 0;
	height: 2rpx;
	background: linear-gradient(90deg, transparent, #22c55e, transparent);
	box-shadow: 0 0 20rpx #22c55e;
	animation: scanMove 4s linear infinite;
}
@keyframes scanMove {
	0% { top: 10%; opacity: 0; }
	10% { opacity: 1; }
	90% { opacity: 1; }
	100% { top: 90%; opacity: 0; }
}

.content {
	flex: 1;
	min-height: 0;
	position: relative;
	z-index: 1;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 0 48rpx;
	box-sizing: border-box;
	padding-top: calc(var(--status-bar-height, 0px) + 60rpx);
	padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 180rpx);
}

/* 品牌头部 */
.brand-header {
	display: flex;
	flex-direction: column;
	align-items: center;
	margin-bottom: 64rpx;
}

.brand-logo-wrap {
	width: 180rpx;
	height: 180rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	position: relative;
}

.brand-logo {
	width: 160rpx;
	height: 160rpx;
	border-radius: 40rpx;
	background: linear-gradient(135deg, #22c55e 0%, #15803d 100%);
	display: flex;
	align-items: center;
	justify-content: center;
	box-shadow: 0 0 40rpx rgba(34, 197, 94, 0.5), inset 0 2rpx 8rpx rgba(255, 255, 255, 0.3);
	position: relative;
	overflow: hidden;
}

.logo-text {
	font-size: 80rpx;
	font-weight: 900;
	color: #fff;
	font-family: 'Microsoft YaHei', sans-serif;
	text-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.4);
	z-index: 1;
}

.logo-ring {
	position: absolute;
	inset: 8rpx;
	border: 2rpx solid rgba(255, 255, 255, 0.3);
	border-radius: 32rpx;
}

.brand-name {
	margin-top: 32rpx;
	font-size: 52rpx;
	font-weight: 800;
	color: #ffffff;
	letter-spacing: 6rpx;
	text-shadow: 0 0 24rpx rgba(34, 197, 94, 0.6);
}

.brand-sub {
	margin-top: 10rpx;
	font-size: 24rpx;
	color: #22c55e;
	letter-spacing: 8rpx;
	opacity: 0.85;
}

/* 数据展示 */
.stat-row {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 48rpx;
	margin-bottom: 80rpx;
	padding: 28rpx 48rpx;
	background: rgba(34, 197, 94, 0.06);
	border: 1rpx solid rgba(34, 197, 94, 0.2);
	border-radius: 24rpx;
	backdrop-filter: blur(10rpx);
}

.stat-box {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 6rpx;
}

.stat-num {
	font-size: 44rpx;
	font-weight: 800;
	color: #22c55e;
	text-shadow: 0 0 16rpx rgba(34, 197, 94, 0.6);
	font-family: 'Arial', sans-serif;
}

.stat-label {
	font-size: 22rpx;
	color: #9ca3af;
	letter-spacing: 2rpx;
}

.stat-divider {
	width: 2rpx;
	height: 60rpx;
	background: linear-gradient(180deg, transparent, rgba(34, 197, 94, 0.4), transparent);
}

/* 主按钮区 */
.btn-area {
	width: 100%;
	display: flex;
	flex-direction: column;
	align-items: center;
}

.login-btn {
	width: 100%;
	height: 96rpx;
	border-radius: 48rpx;
	background: linear-gradient(90deg, #22c55e 0%, #15803d 100%);
	display: flex;
	align-items: center;
	justify-content: center;
	position: relative;
	overflow: hidden;
	box-shadow: 0 0 36rpx rgba(34, 197, 94, 0.5), 0 8rpx 24rpx rgba(21, 128, 61, 0.4);
	transition: transform 0.2s;
}

.login-btn:active {
	transform: scale(0.98);
}

.btn-glow {
	position: absolute;
	top: 0;
	left: -100%;
	width: 60%;
	height: 100%;
	background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.35), transparent);
	animation: shine 2.5s infinite;
}
@keyframes shine {
	0% { left: -100%; }
	100% { left: 200%; }
}

.btn-text {
	font-size: 30rpx;
	color: #fff;
	font-weight: 600;
	letter-spacing: 2rpx;
	z-index: 1;
}

.login-btn.disabled {
	background: linear-gradient(90deg, #1f2937 0%, #111827 100%);
	color: #6b7280;
	box-shadow: none;
}

.login-btn.disabled .btn-text {
	color: #6b7280;
}

.login-btn.disabled .btn-glow {
	display: none;
}

.agreement-row {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: center;
	margin-top: 32rpx;
	gap: 0 4rpx;
	font-size: 22rpx;
	line-height: 32rpx;
}

.check-icon {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 28rpx;
	height: 28rpx;
	border: 2rpx solid #22c55e;
	border-radius: 50%;
	box-sizing: border-box;
	margin-right: 6rpx;
	flex-shrink: 0;
}

.check-icon.active {
	background: #22c55e;
	border-color: #22c55e;
}

.agreement-text {
	color: #9ca3af;
}

.agreement-link {
	color: #22c55e;
}
</style>
