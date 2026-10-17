<template>
	<view class="cs-avatar-page">
		<view class="status-bar" :style="{height: statusBarHeight + 'px'}"></view>

		<!-- 顶部导航 -->
		<view class="nav-bar">
			<view class="nav-back" @click="goBack"><up-icon name="arrow-left" size="20" color="#fff"></up-icon></view>
			<text class="nav-title">客服头像</text>
			<view class="nav-right"><up-icon name="more-dot-fill" size="20" color="#fff"></up-icon></view>
		</view>

		<!-- 内容区 -->
		<view class="content">
			<!-- 身份标识 -->
			<view class="identity-badge">客服身份</view>

			<!-- 头像选择区 -->
			<view class="avatar-area" @click="chooseImage">
				<view class="avatar-circle">
					<image v-if="avatarUrl" class="avatar-img" :src="avatarUrl" mode="aspectFill"></image>
					<view v-else class="avatar-placeholder">
						<up-icon name="account-fill" size="60" color="#ccc"></up-icon>
					</view>
					<view class="avatar-change-btn">更换</view>
				</view>
			</view>
			<text class="avatar-tip">点击头像重新选择</text>
			<text class="avatar-subtip">该头像只属于客服身份，不会覆盖其他身份头像。</text>

			<!-- 操作按钮 -->
			<view class="primary-btn" @click="chooseImage">
				<text>选择图片</text>
			</view>
			<text class="format-tip">支持 JPG、PNG、GIF、WEBP，单张图片最大 5MB</text>
		</view>
	</view>
</template>

<script setup>
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { userEdit, getUserInfo } from '../../request/api'
import { base_url } from '../../request/env'

const statusBarHeight = ref(44)
try {
	const sysInfo = uni.getSystemInfoSync()
	statusBarHeight.value = sysInfo.statusBarHeight || 44
} catch (e) {}

const avatarUrl = ref('')

const formatAvatar = (url) => {
	if (!url) return ''
	if (url.startsWith('http')) return url
	if (url.startsWith('blob:') || url.startsWith('/uploads/')) return url
	if (url.startsWith('/static')) {
		// 同源部署：直接用 /h5 前缀
		return '/h5' + url
	}
	return url
}

const loadCurrentAvatar = async () => {
	const cached = uni.getStorageSync('cs_avatar') || uni.getStorageSync('avatar') || ''
	avatarUrl.value = formatAvatar(cached)
	try {
		const res = await getUserInfo()
		if (res.code === 1 && res.data) {
			const a = res.data.cs_avatar || res.data.avatar || ''
			if (a) avatarUrl.value = formatAvatar(a)
		}
	} catch (e) {}
}

onShow(() => {
	loadCurrentAvatar()
})

const goBack = () => {
	uni.navigateBack({ delta: 1, fail: () => {
		uni.reLaunch({ url: '/pages/playerMain/customer-service' })
	}})
}

const MAX_SIZE = 5 * 1024 * 1024 // 5MB

const chooseImage = () => {
	uni.chooseImage({
		count: 1,
		sizeType: ['compressed'],
		sourceType: ['album', 'camera'],
		success: (res) => {
			const tempPath = res.tempFilePaths && res.tempFilePaths[0]
			const tempFile = res.tempFiles && res.tempFiles[0]
			if (tempFile && tempFile.size > MAX_SIZE) {
				uni.showToast({ title: '图片大小不能超过5MB', icon: 'none' })
				return
			}
			uploadAvatar(tempPath)
		},
		fail: (err) => {
			if (err && err.errMsg && err.errMsg.indexOf('cancel') === -1) {
				uni.showToast({ title: '选择图片失败', icon: 'none' })
			}
		}
	})
}

const uploadAvatar = (tempPath) => {
	uni.showLoading({ title: '上传中...', mask: true })
	// 本地预览先显示
	avatarUrl.value = tempPath
	uni.uploadFile({
		url: base_url + '/user/avatarUpload',
		filePath: tempPath,
		name: 'file',
		header: { token: uni.getStorageSync('token') || '' },
		formData: { identity: 'cs' },
		success: (res) => {
			uni.hideLoading()
			try {
				const data = typeof res.data === 'string' ? JSON.parse(res.data) : res.data
				if (data.code === 1 && data.data && data.data.url) {
					const url = data.data.url
					avatarUrl.value = formatAvatar(url)
					uni.setStorageSync('cs_avatar', url)
					uni.showToast({ title: '头像已更新', icon: 'success' })
				} else if (data.code === 1 && data.data && data.data.avatar) {
					const url = data.data.avatar
					avatarUrl.value = formatAvatar(url)
					uni.setStorageSync('cs_avatar', url)
					uni.showToast({ title: '头像已更新', icon: 'success' })
				} else {
					// 后端未对接时仍保留本地预览
					uni.setStorageSync('cs_avatar', tempPath)
					uni.showToast({ title: '已保存（本地预览）', icon: 'none' })
				}
			} catch (e) {
				uni.setStorageSync('cs_avatar', tempPath)
				uni.showToast({ title: '已保存（本地预览）', icon: 'none' })
			}
		},
		fail: () => {
			uni.hideLoading()
			// 离线/mock 情况下保存到本地
			uni.setStorageSync('cs_avatar', tempPath)
			uni.showToast({ title: '已保存（本地预览）', icon: 'none' })
		}
	})
}
</script>

<style scoped>
.cs-avatar-page {
	min-height: 100vh;
	background: linear-gradient(180deg, #FFF1E8 0%, #FFFFFF 40%, #FFFFFF 100%);
	display: flex;
	flex-direction: column;
}

.status-bar {
	width: 100%;
	background: linear-gradient(180deg, #FF7A45 0%, #FF7A45 100%);
}

.nav-bar {
	height: 88rpx;
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 0 24rpx;
	background: linear-gradient(180deg, #FF7A45 0%, #FF8E5A 100%);
}

.nav-back, .nav-right {
	width: 60rpx;
	height: 60rpx;
	display: flex;
	align-items: center;
	justify-content: center;
}

.nav-title {
	font-size: 34rpx;
	font-weight: 700;
	color: #fff;
	letter-spacing: 2rpx;
}

.content {
	flex: 1;
	display: flex;
	flex-direction: column;
	align-items: center;
	padding: 60rpx 48rpx 80rpx;
	box-sizing: border-box;
}

.identity-badge {
	padding: 10rpx 32rpx;
	background: linear-gradient(135deg, #FF7A45 0%, #FF9F7A 100%);
	border-radius: 32rpx;
	color: #fff;
	font-size: 24rpx;
	font-weight: 700;
	margin-bottom: 60rpx;
	box-shadow: 0 6rpx 16rpx rgba(255, 122, 69, 0.3);
	letter-spacing: 2rpx;
}

.avatar-area {
	width: 320rpx;
	height: 320rpx;
	display: flex;
	align-items: center;
	justify-content: center;
}

.avatar-circle {
	width: 320rpx;
	height: 320rpx;
	border-radius: 50%;
	background: #fff;
	box-shadow: 0 12rpx 40rpx rgba(255, 122, 69, 0.2);
	position: relative;
	display: flex;
	align-items: center;
	justify-content: center;
	overflow: visible;
	border: 4rpx solid rgba(255, 122, 69, 0.15);
}

.avatar-img {
	width: 100%;
	height: 100%;
	border-radius: 50%;
	object-fit: cover;
}

.avatar-placeholder {
	width: 100%;
	height: 100%;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	background: linear-gradient(135deg, #FFF1E8 0%, #FFE0C8 100%);
}

.avatar-change-btn {
	position: absolute;
	right: 12rpx;
	bottom: 12rpx;
	padding: 12rpx 28rpx;
	background: linear-gradient(135deg, #FF7A45 0%, #FF9F7A 100%);
	color: #fff;
	font-size: 22rpx;
	border-radius: 32rpx;
	font-weight: 600;
	box-shadow: 0 6rpx 16rpx rgba(255, 122, 69, 0.4);
}

.avatar-tip {
	margin-top: 40rpx;
	font-size: 30rpx;
	color: #1a1a1a;
	font-weight: 600;
}

.avatar-subtip {
	margin-top: 14rpx;
	font-size: 24rpx;
	color: #999;
	text-align: center;
	line-height: 1.5;
	max-width: 540rpx;
}

.primary-btn {
	margin-top: 80rpx;
	width: 100%;
	height: 96rpx;
	border-radius: 48rpx;
	background: linear-gradient(135deg, #FF7A45 0%, #FF9F7A 100%);
	color: #fff;
	font-size: 30rpx;
	font-weight: 700;
	display: flex;
	align-items: center;
	justify-content: center;
	box-shadow: 0 8rpx 24rpx rgba(255, 122, 69, 0.4);
	letter-spacing: 2rpx;
}

.format-tip {
	margin-top: 24rpx;
	font-size: 22rpx;
	color: #999;
	text-align: center;
}
</style>
