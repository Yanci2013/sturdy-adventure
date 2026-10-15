<template>
	<view class="cs-settings-page">
		<view class="status-bar" :style="{height: statusBarHeight + 'px'}"></view>
		<view class="nav-bar">
			<view class="nav-back" @click="goBack"><up-icon name="arrow-left" size="20" color="#333"></up-icon></view>
			<text class="nav-title">客服会话</text>
			<view class="nav-right"><up-icon name="more-dot-fill" size="20" color="#333"></up-icon></view>
		</view>

		<view class="sub-header">
			<view class="sub-close" @click="goBack"><text class="close-icon">×</text></view>
			<text class="sub-title">设置</text>
			<view class="sub-manage">
				<up-icon name="grid" size="18" color="#666"></up-icon>
				<text class="manage-text">管理</text>
			</view>
		</view>

		<scroll-view scroll-y class="content">
			<!-- 通知 -->
			<view class="section">
				<text class="section-title">通知</text>
				<view class="card">
					<view class="row" v-for="item in notifyList" :key="item.key">
						<text class="row-text">{{ item.label }}</text>
						<view :class="['switch', item.value ? 'on' : '']" @click="toggleSwitch(item)">
							<view class="switch-knob"></view>
						</view>
					</view>
				</view>
			</view>

			<!-- 通用 -->
			<view class="section">
				<text class="section-title">通用</text>
				<view class="card">
					<view class="row" @click="clearCache">
						<text class="row-text">清除缓存</text>
						<view class="row-right">
							<text class="row-value">{{ cacheSize }}</text>
							<up-icon name="arrow-right" size="14" color="#ccc"></up-icon>
						</view>
					</view>
					<view class="row" @click="goLanguage">
						<text class="row-text">语言</text>
						<view class="row-right">
							<text class="row-value">简体中文</text>
							<up-icon name="arrow-right" size="14" color="#ccc"></up-icon>
						</view>
					</view>
					<view class="row" @click="goDarkMode">
						<text class="row-text">深色模式</text>
						<view class="row-right">
							<text class="row-value">跟随系统</text>
							<up-icon name="arrow-right" size="14" color="#ccc"></up-icon>
						</view>
					</view>
				</view>
			</view>

			<!-- 关于 -->
			<view class="section">
				<text class="section-title">关于</text>
				<view class="card">
					<view class="row" @click="goAgreement('user')">
						<text class="row-text">用户协议</text>
						<up-icon name="arrow-right" size="14" color="#ccc"></up-icon>
					</view>
					<view class="row" @click="goAgreement('privacy')">
						<text class="row-text">隐私政策</text>
						<up-icon name="arrow-right" size="14" color="#ccc"></up-icon>
					</view>
				</view>
			</view>
		</scroll-view>
	</view>
</template>

<script setup>
import { ref } from 'vue'

const statusBarHeight = ref(44)
try {
	const sysInfo = uni.getSystemInfoSync()
	statusBarHeight.value = sysInfo.statusBarHeight || 44
} catch (e) {}

const cacheSize = ref('12.6 MB')

const notifyList = ref([
	{ key: 'msg', label: '新消息通知', value: true },
	{ key: 'order', label: '新订单提醒', value: true },
	{ key: 'sound', label: '声音提醒', value: false },
	{ key: 'vibrate', label: '震动提醒', value: true }
])

const toggleSwitch = (item) => {
	item.value = !item.value
	uni.showToast({ title: item.value ? '已开启' : '已关闭', icon: 'none' })
}

const clearCache = () => {
	uni.showModal({
		title: '提示',
		content: '确定要清除缓存吗？',
		success: (res) => {
			if (res.confirm) {
				cacheSize.value = '0 MB'
				uni.showToast({ title: '已清除', icon: 'success' })
			}
		}
	})
}

const goLanguage = () => {
	uni.showToast({ title: '当前为简体中文', icon: 'none' })
}

const goDarkMode = () => {
	uni.showToast({ title: '跟随系统', icon: 'none' })
}

const goAgreement = (type) => {
	const url = type === 'user' ? '/pagesA/mine/agreement?name=用户协议' : '/pagesA/mine/agreement?name=隐私政策'
	uni.navigateTo({ url })
}

const goBack = () => {
	uni.navigateBack()
}
</script>

<style scoped>
.cs-settings-page { min-height: 100vh; background: #f5f7fa; display: flex; flex-direction: column; }
.status-bar { background: #fff; }
.nav-bar { height: 88rpx; padding: 0 24rpx; display: flex; align-items: center; justify-content: space-between; background: #fff; }
.nav-back { width: 56rpx; height: 56rpx; display: flex; align-items: center; justify-content: center; }
.nav-title { font-size: 32rpx; font-weight: 600; color: #111; }
.nav-right { width: 56rpx; height: 56rpx; display: flex; align-items: center; justify-content: center; }

.sub-header { display: flex; align-items: center; justify-content: space-between; padding: 20rpx 24rpx; background: linear-gradient(180deg, #E6F4FF 0%, #ffffff 100%); }
.sub-close { width: 56rpx; height: 56rpx; display: flex; align-items: center; justify-content: center; }
.close-icon { font-size: 40rpx; color: #333; line-height: 1; }
.sub-title { font-size: 30rpx; font-weight: 600; color: #111; }
.sub-manage { display: flex; align-items: center; gap: 6rpx; }
.manage-text { font-size: 24rpx; color: #666; }

.content { flex: 1; padding: 24rpx; }

.section { margin-bottom: 32rpx; }
.section-title { display: block; font-size: 28rpx; font-weight: 600; color: #111; margin-bottom: 16rpx; }
.card { background: #fff; border-radius: 16rpx; padding: 0 24rpx; }
.row { display: flex; align-items: center; justify-content: space-between; padding: 28rpx 0; border-bottom: 1rpx solid #f5f5f5; }
.row:last-child { border-bottom: none; }
.row-text { font-size: 28rpx; color: #333; }
.row-right { display: flex; align-items: center; gap: 8rpx; }
.row-value { font-size: 26rpx; color: #999; }

.switch { width: 80rpx; height: 44rpx; border-radius: 22rpx; background: #e4e7eb; position: relative; transition: background 0.2s; }
.switch.on { background: #1890ff; }
.switch-knob { position: absolute; top: 4rpx; left: 4rpx; width: 36rpx; height: 36rpx; border-radius: 50%; background: #fff; box-shadow: 0 2rpx 6rpx rgba(0,0,0,0.15); transition: left 0.2s; }
.switch.on .switch-knob { left: 40rpx; }
</style>
