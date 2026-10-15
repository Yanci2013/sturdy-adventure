<template>
	<view class="page">
		<view class="top-bg"></view>

		<view class="content">
			<!-- 品牌头部 -->
			<view class="brand-header">
				<view class="brand-logo-wrap">
					<image class="brand-logo" :src="siteLogo" mode="aspectFit"></image>
				</view>
				<text class="brand-name">{{ brandName }}</text>
			</view>

			<!-- 主按钮 -->
			<view class="btn-area">
				<view class="login-btn" :class="{ disabled: !agreement_protocol || submitting }" @click="handleLogin">
					<text>{{ submitting ? '正在登录...' : '微信一键授权登录' }}</text>
				</view>

				<!-- 协议 -->
				<view class="agreement-row" @click.stop="agreement_protocol = !agreement_protocol">
					<view class="check-icon" :class="{ active: agreement_protocol }">
						<u-icon v-if="agreement_protocol" name="checkmark" size="22rpx" color="#ffffff"></u-icon>
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
			siteLogo: '/static/eon.png',
			brandName: '创世网络',
			page: '',
			pageParams: {},
			juheConfig: { enabled: false, url: '', app_id: '' }
		}
	},
	onLoad(option) {
		configure().then(res => {
			if (res.code === 1 && res.data) {
				if (res.data.site_logo) {
					let logo = res.data.site_logo
					if (logo && !logo.startsWith('http')) {
						logo = base_url.replace(/\/api$/, '') + logo
					}
					if (logo && logo.match(/\.(png|jpg|jpeg|gif|webp|svg|ico)/i)) {
						this.siteLogo = logo
					}
				}
				const name = res.data.club_name || res.data.brand_name || res.data.name
				if (name) this.brandName = name
			}
		}).catch(() => {})

		// 加载聚合登录配置
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
		// 处理聚合登录回调（URL 中带 code 参数）
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
				const mode = this.juheConfig.login_mode || 'juhe'
				const juheEnabled = this.juheConfig.enabled && this.juheConfig.url && this.juheConfig.app_id

				// 未配置聚合登录：禁止登录（除开发模式 mock 外）
				if (!juheEnabled && mode !== 'mock') {
					uni.hideLoading()
					this.submitting = false
					return uni.showModal({
						title: '登录未启用',
						content: '一键登录需在后台管理配置聚合登录接口地址、商户ID和密钥后才能使用，请联系管理员。',
						showCancel: false,
						confirmText: '我知道了'
					})
				}

				// 聚合登录已启用：跳转到聚合登录服务地址
				if (juheEnabled) {
					const cbUrl = window.location.origin + window.location.pathname
					const redirect = encodeURIComponent(cbUrl + '?type=wechat')
					window.location.href = this.juheConfig.url + '?app_id=' + this.juheConfig.app_id + '&redirect=' + redirect
					return
				}
				// mock 模式（仅开发调试用，需后台显式开启）
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
			// 处理聚合登录服务回调
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
.page {
	min-height: 100vh;
	background: linear-gradient(180deg, #FF7A45 0%, #ffffff 50%, #ffffff 100%);
	display: flex;
	flex-direction: column;
	position: relative;
	overflow: hidden;
}

.top-bg {
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	z-index: 0;
	background: linear-gradient(180deg, #FF7A45 0%, #ffffff 50%, #ffffff 100%);
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
	padding-top: calc(var(--status-bar-height, 0px) + 40rpx);
	padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 200rpx);
}

/* 品牌头部 */
.brand-header {
	display: flex;
	flex-direction: column;
	align-items: center;
	margin-bottom: 120rpx;
}

.brand-logo-wrap {
	width: 200rpx;
	height: 200rpx;
	border-radius: 24rpx;
	overflow: hidden;
	background: transparent;
	box-shadow: none;
}

.brand-logo {
	width: 100%;
	height: 100%;
	display: block;
}

.brand-name {
	margin-top: 32rpx;
	font-size: 56rpx;
	font-weight: 800;
	color: #1a1a1a;
	letter-spacing: 4rpx;
	font-style: italic;
	transform: skewX(-6deg);
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
	background: linear-gradient(90deg, #FF7A45 0%, #FFB38A 100%);
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 30rpx;
	color: #fff;
	font-weight: 600;
}

.login-btn.disabled {
	background: #f5f5f5;
	color: #666;
	box-shadow: none;
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
	border: 2rpx solid #ccc;
	border-radius: 50%;
	box-sizing: border-box;
	margin-right: 6rpx;
	flex-shrink: 0;
}

.check-icon.active {
	background: #FF7A45;
	border-color: #FF7A45;
}

.agreement-text {
	color: #999;
}

.agreement-link {
	color: #4A90D9;
}
</style>
