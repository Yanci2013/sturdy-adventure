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
			<text class="navbar-title">提现资料</text>
			<view class="navbar-placeholder"></view>
		</view>

		<!-- 方式切换 -->
		<view class="tabs-row">
			<view v-if="openChannels.includes('alipay')" :class="['method-tab', currentTab === 'alipay' ? 'active' : '']" @click="currentTab = 'alipay'">
				<image class="method-tab-icon" src="/static/images/icon.png" mode="aspectFit"></image>
				<text>支付宝</text>
			</view>
			<view v-if="openChannels.includes('wechat')" :class="['method-tab', currentTab === 'wechat' ? 'active' : '']" @click="currentTab = 'wechat'">
				<image class="method-tab-icon" src="/static/images/wechat.png" mode="aspectFit"></image>
				<text>微信</text>
			</view>
			<view v-if="openChannels.includes('bank')" :class="['method-tab', currentTab === 'bank' ? 'active' : '']" @click="currentTab = 'bank'">
				<image class="method-tab-icon" src="/static/images/icon.png" mode="aspectFit"></image>
				<text>银行卡</text>
			</view>
		</view>

		<scroll-view scroll-y class="content">
			<view class="content-inner">
				<!-- 支付宝 -->
				<view class="card card-glass" v-if="currentTab === 'alipay'">
					<text class="card-title">支付宝提现资料</text>
					<view class="form-row">
						<text class="form-label">支付宝账号</text>
						<input class="form-input" v-model="alipayForm.account" placeholder="请输入您的支付宝账号" placeholder-class="placeholder" />
					</view>
					<view class="form-row">
						<text class="form-label">支付宝实名</text>
						<input class="form-input" v-model="alipayForm.name" placeholder="请输入您的支付宝真实姓名" placeholder-class="placeholder" />
					</view>
					<view class="form-section">
						<view class="qr-label-row">
							<text class="form-label">支付宝收款码</text>
							<text class="link-reupload" @click="uploadQr('alipay')">重新上传</text>
						</view>
						<view class="qr-upload-wrap qr-upload-alipay" @click="uploadQr('alipay')">
							<image v-if="alipayForm.imagesFull" class="qr-preview" :src="alipayForm.imagesFull" mode="aspectFill" @click.stop="previewQr(alipayForm.imagesFull)"></image>
							<view v-else class="qr-placeholder">
								<text class="qr-placeholder-text">点击上传</text>
							</view>
						</view>
					</view>
				</view>

				<!-- 微信 -->
				<view class="card card-glass" v-if="currentTab === 'wechat'">
					<text class="card-title">微信提现资料</text>
					<view class="form-row">
						<text class="form-label">微信实名</text>
						<input class="form-input" v-model="wechatForm.name" placeholder="请输入您的微信真实姓名" placeholder-class="placeholder" />
					</view>
					<view class="form-row">
						<text class="form-label">微信账号</text>
						<input class="form-input" v-model="wechatForm.account" placeholder="请输入您的微信账号" placeholder-class="placeholder" />
					</view>
					<view class="form-section">
						<view class="qr-label-row">
							<text class="form-label">微信收款码</text>
							<text class="link-reupload" @click="uploadQr('wechat')">重新上传</text>
						</view>
						<view class="qr-upload-wrap qr-upload-wechat" @click="uploadQr('wechat')">
							<image v-if="wechatForm.imagesFull" class="qr-preview" :src="wechatForm.imagesFull" mode="aspectFill" @click.stop="previewQr(wechatForm.imagesFull)"></image>
							<view v-else class="qr-placeholder">
								<text class="qr-placeholder-text">点击上传</text>
							</view>
						</view>
					</view>
				</view>

				<!-- 银行卡 -->
				<view class="card card-glass" v-if="currentTab === 'bank'">
					<text class="card-title">银行卡提现资料</text>
					<view class="form-row">
						<text class="form-label">银行卡实名</text>
						<input class="form-input" v-model="bankForm.name" placeholder="请输入您的银行卡真实姓名" placeholder-class="placeholder" />
					</view>
					<view class="form-row">
						<text class="form-label">银行卡账号</text>
						<input class="form-input" v-model="bankForm.account" placeholder="请输入您的银行卡账号" placeholder-class="placeholder" type="number" />
					</view>
					<view class="form-row">
						<text class="form-label">开户行名称</text>
						<input class="form-input" v-model="bankForm.bankName" placeholder="请输入开户行名称" placeholder-class="placeholder" />
					</view>
				</view>

				<view class="bottom-spacer"></view>
			</view>
		</scroll-view>

		<!-- 底部保存按钮 -->
		<view class="footer-bar">
			<view class="btn-submit" @click="saveInfo">保存信息</view>
		</view>
	</view>
</template>

<script setup>
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { base_url } from '../../request/env'
import { configure } from '../../request/api'

const currentTab = ref('alipay')
const openChannels = ref(['alipay', 'wechat', 'bank'])

const alipayForm = ref({
	account: '',
	name: '',
	images: '',
	imagesFull: ''
})
const wechatForm = ref({
	account: '',
	name: '',
	images: '',
	imagesFull: ''
})
const bankForm = ref({
	account: '',
	name: '',
	bankName: '',
	images: '',
	imagesFull: ''
})

onShow(() => {
	loadConfig()
	// 从缓存读取已保存的资料
	const alipay = uni.getStorageSync('settlement_alipay')
	if (alipay) alipayForm.value = { ...alipayForm.value, ...alipay }
	const wechat = uni.getStorageSync('settlement_wechat')
	if (wechat) wechatForm.value = { ...wechatForm.value, ...wechat }
	const bank = uni.getStorageSync('settlement_bank')
	if (bank) bankForm.value = { ...bankForm.value, ...bank }
})

const loadConfig = async () => {
	try {
		const res = await configure()
		const channels = res.data.withdraw_channels || ['alipay']
		openChannels.value = channels
		if (channels.length && !channels.includes(currentTab.value)) {
			currentTab.value = channels[0]
		}
	} catch (e) {}
}

const uploadQr = (type) => {
	uni.chooseImage({
		count: 1,
		success: (res) => {
			uni.uploadFile({
				url: base_url + '/common/upload',
				filePath: res.tempFilePaths[0],
				name: 'file',
				header: { token: uni.getStorageSync('token') },
				success: (uploadRes) => {
					const data = JSON.parse(uploadRes.data)
					if (type === 'alipay') {
						alipayForm.value.imagesFull = data.data.fullurl
						alipayForm.value.images = data.data.url
					} else if (type === 'wechat') {
						wechatForm.value.imagesFull = data.data.fullurl
						wechatForm.value.images = data.data.url
					}
				}
			})
		}
	})
}

const previewQr = (url) => {
	if (url) uni.previewImage({ urls: [url] })
}

const saveInfo = () => {
	if (currentTab.value === 'alipay') {
		if (!alipayForm.value.account) return uni.showToast({ title: '请输入支付宝账号', icon: 'none' })
		if (!alipayForm.value.name) return uni.showToast({ title: '请输入支付宝实名', icon: 'none' })
		uni.setStorageSync('settlement_alipay', { ...alipayForm.value })
	} else if (currentTab.value === 'wechat') {
		if (!wechatForm.value.name) return uni.showToast({ title: '请输入微信实名', icon: 'none' })
		if (!wechatForm.value.account) return uni.showToast({ title: '请输入微信账号', icon: 'none' })
		uni.setStorageSync('settlement_wechat', { ...wechatForm.value })
	} else if (currentTab.value === 'bank') {
		if (!bankForm.value.name) return uni.showToast({ title: '请输入银行卡实名', icon: 'none' })
		if (!bankForm.value.account) return uni.showToast({ title: '请输入银行卡账号', icon: 'none' })
		if (!bankForm.value.bankName) return uni.showToast({ title: '请输入开户行名称', icon: 'none' })
		uni.setStorageSync('settlement_bank', { ...bankForm.value })
	}
	uni.showToast({ title: '保存成功', icon: 'success' })
	setTimeout(() => { uni.navigateBack() }, 1000)
}

const goBack = () => { uni.navigateBack() }
const goHome = () => { uni.reLaunch({ url: '/pages/index/index' }) }
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
.tabs-row {
	display: flex;
	align-items: stretch;
	gap: 16rpx;
	margin: 24rpx 24rpx 0;
	padding: 20rpx 24rpx;
	background: rgba(255,255,255,0.45);
	backdrop-filter: blur(24rpx);
	-webkit-backdrop-filter: blur(24rpx);
	border: 1rpx solid rgba(255,255,255,0.6);
	box-shadow: 0 2rpx 20rpx rgba(0,0,0,0.04);
	border-radius: 24rpx;
}
.method-tab {
	flex: 1;
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 12rpx;
	padding: 20rpx 16rpx;
	background: rgba(0,0,0,0.05);
	border-radius: 16rpx;
	font-size: 26rpx;
	color: #666;
}
.method-tab.active { background: #000; color: #fff; }
.method-tab-icon { width: 40rpx; height: 40rpx; flex-shrink: 0; }
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
	display: block;
	font-size: 32rpx;
	font-weight: 700;
	color: #000;
	margin-bottom: 28rpx;
	padding-bottom: 24rpx;
	border-bottom: 1rpx solid rgba(0,0,0,0.08);
}
.form-row {
	margin-bottom: 24rpx;
	padding-bottom: 24rpx;
	border-bottom: 1rpx solid rgba(0,0,0,0.08);
}
.form-row:last-of-type { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
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
	font-size: 28rpx;
	color: #333;
	box-sizing: border-box;
}
.placeholder { color: #999; }
.form-section { margin-top: 24rpx; padding-top: 24rpx; }
.qr-label-row {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 16rpx;
}
.link-reupload { font-size: 26rpx; color: #f67f1f; }
.qr-upload-wrap {
	width: 280rpx;
	height: 280rpx;
	border-radius: 16rpx;
	overflow: hidden;
	position: relative;
	background: #f5f5f5;
	box-shadow: 0 2rpx 12rpx rgba(0,0,0,0.06);
}
.qr-preview { width: 100%; height: 100%; display: block; }
.qr-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; }
.qr-placeholder-text { font-size: 26rpx; color: #999; }
.bottom-spacer { height: 40rpx; }
.footer-bar {
	position: fixed;
	left: 0;
	right: 0;
	bottom: 0;
	z-index: 10;
	padding: 20rpx 24rpx;
	padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
	background: #fff;
	box-shadow: 0 -2rpx 12rpx rgba(0,0,0,0.06);
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
