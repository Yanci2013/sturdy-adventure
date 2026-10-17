<template>
	<view class="msg-page">
		<view class="status-bar" :style="{height: statusBarHeight + 'px'}"></view>
		<view class="nav-bar">
			<view class="nav-back" @click="goBack"><up-icon name="arrow-left" size="20" color="#333"></up-icon></view>
			<text class="nav-title">消息中心</text>
			<view class="nav-right"></view>
		</view>

		<!-- 标签切换 -->
		<view class="tab-bar">
			<view :class="['tab-item', currentTab === 0 ? 'active' : '']" @click="switchTab(0)">
				<text class="tab-text">会话</text>
			</view>
			<view :class="['tab-item', currentTab === 1 ? 'active' : '']" @click="switchTab(1)">
				<text class="tab-text">系统消息</text>
			</view>
		</view>

		<!-- 搜索栏 -->
		<view class="search-wrap" v-if="currentTab === 0">
			<view class="search-box">
				<up-icon name="search" size="16" color="#999"></up-icon>
				<input class="search-input" type="text" v-model="keyword" placeholder="搜索名字、ID 或聊天记录" placeholder-class="search-placeholder" confirm-type="search" />
			</view>
			<view class="search-btn" @click="doSearch">搜索</view>
		</view>

		<!-- 联系客服入口 -->
		<view class="contact-card" v-if="currentTab === 0" @click="contactService">
			<view class="contact-left">
				<view class="contact-avatar">
					<up-icon name="chat" size="24" color="#FFD700"></up-icon>
				</view>
				<view class="contact-info">
					<text class="contact-name">联系客服</text>
					<text class="contact-desc">提交后由在线客服接入回复，无需指定客服</text>
				</view>
			</view>
			<view class="contact-btn">联系客服</view>
		</view>

		<!-- 会话列表 -->
		<view class="list-wrap" v-if="currentTab === 0">
			<view class="chat-item" v-for="(item, idx) in chatList" :key="idx">
				<image class="chat-avatar" :src="item.avatar || '/static/yun/default_avatar.png'" mode="aspectFill"></image>
				<view class="chat-info">
					<text class="chat-name">{{ item.name }}</text>
					<text class="chat-msg">{{ item.lastMsg }}</text>
				</view>
				<text class="chat-time">{{ item.time }}</text>
			</view>
			<view class="empty-state" v-if="!chatList.length">
				<view class="empty-icon">
					<text class="empty-emoji">📦</text>
				</view>
				<text class="empty-text">— 暂无订单或客服会话 —</text>
			</view>
		</view>

		<!-- 系统消息列表 -->
		<view class="list-wrap" v-if="currentTab === 1">
			<view class="sys-item" v-for="(item, idx) in sysList" :key="idx">
				<view class="sys-dot"></view>
				<view class="sys-info">
					<text class="sys-title">{{ item.title }}</text>
					<text class="sys-content">{{ item.content }}</text>
				</view>
				<text class="sys-time">{{ item.time }}</text>
			</view>
			<view class="empty-state" v-if="!sysList.length">
				<view class="empty-icon">
					<text class="empty-emoji">📭</text>
				</view>
				<text class="empty-text">— 暂无系统消息 —</text>
			</view>
		</view>
	</view>
</template>

<script setup>
import { ref } from 'vue'
import router from '../../utils/router'

const statusBarHeight = ref(44)
try {
	const sysInfo = uni.getSystemInfoSync()
	statusBarHeight.value = sysInfo.statusBarHeight || 44
} catch (e) {}

const currentTab = ref(0)
const keyword = ref('')
const chatList = ref([])
const sysList = ref([])

const switchTab = (idx) => {
	currentTab.value = idx
}

const doSearch = () => {}

const goBack = () => {
	const pages = getCurrentPages()
	if (pages.length > 1) {
		uni.navigateBack()
	} else {
		uni.reLaunch({ url: '/pages/playerMain/index' })
	}
}

const contactService = () => {
	router.navigateTo({ url: 'pages/playerMain/cs-center' })
}
</script>

<style scoped>
.msg-page { min-height: 100vh; background: #f5f5f7; }
.status-bar { width: 100%; background: #fff; }
.nav-bar { display: flex; align-items: center; justify-content: space-between; height: 88rpx; padding: 0 24rpx; background: #fff; border-bottom: 1rpx solid #eee; }
.nav-back { width: 60rpx; height: 60rpx; display: flex; align-items: center; justify-content: center; }
.nav-title { font-size: 34rpx; font-weight: 600; color: #333; }
.nav-right { width: 60rpx; height: 60rpx; }

.tab-bar { display: flex; background: #fff; padding: 0 40rpx; }
.tab-item { flex: 1; display: flex; align-items: center; justify-content: center; height: 80rpx; position: relative; }
.tab-text { font-size: 30rpx; color: #999; }
.tab-item.active .tab-text { font-size: 32rpx; font-weight: 600; color: #333; }
.tab-item.active::after { content: ''; position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); width: 48rpx; height: 6rpx; border-radius: 3rpx; background: #FFD700; }

.search-wrap { display: flex; align-items: center; padding: 20rpx 24rpx; background: #fff; gap: 16rpx; }
.search-box { flex: 1; display: flex; align-items: center; height: 72rpx; padding: 0 20rpx; border: 1rpx solid #333; border-radius: 36rpx; background: #fff; }
.search-input { flex: 1; margin-left: 12rpx; font-size: 26rpx; color: #333; }
.search-placeholder { color: #999; }
.search-btn { padding: 0 36rpx; height: 72rpx; line-height: 72rpx; background: #FFD700; border-radius: 36rpx; font-size: 28rpx; color: #333; font-weight: 600; }

.contact-card { display: flex; align-items: center; justify-content: space-between; margin: 16rpx 24rpx; padding: 24rpx; background: #fff; border-radius: 16rpx; }
.contact-left { display: flex; align-items: center; flex: 1; }
.contact-avatar { width: 80rpx; height: 80rpx; border-radius: 50%; background: #333; display: flex; align-items: center; justify-content: center; margin-right: 20rpx; }
.contact-info { display: flex; flex-direction: column; }
.contact-name { font-size: 30rpx; font-weight: 600; color: #333; }
.contact-desc { font-size: 24rpx; color: #999; margin-top: 6rpx; }
.contact-btn { padding: 12rpx 28rpx; background: #FFD700; border-radius: 32rpx; font-size: 26rpx; color: #333; font-weight: 600; }

.list-wrap { padding: 0 24rpx; }
.chat-item { display: flex; align-items: center; padding: 24rpx 0; border-bottom: 1rpx solid #f0f0f0; }
.chat-avatar { width: 88rpx; height: 88rpx; border-radius: 50%; margin-right: 20rpx; background: #ddd; }
.chat-info { flex: 1; display: flex; flex-direction: column; }
.chat-name { font-size: 28rpx; color: #333; font-weight: 500; }
.chat-msg { font-size: 24rpx; color: #999; margin-top: 6rpx; }
.chat-time { font-size: 22rpx; color: #bbb; }

.sys-item { display: flex; align-items: flex-start; padding: 24rpx 0; border-bottom: 1rpx solid #f0f0f0; }
.sys-dot { width: 16rpx; height: 16rpx; border-radius: 50%; background: #FFD700; margin-top: 12rpx; margin-right: 16rpx; flex-shrink: 0; }
.sys-info { flex: 1; display: flex; flex-direction: column; }
.sys-title { font-size: 28rpx; color: #333; font-weight: 500; }
.sys-content { font-size: 24rpx; color: #999; margin-top: 6rpx; }
.sys-time { font-size: 22rpx; color: #bbb; }

.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 160rpx 0; }
.empty-icon { margin-bottom: 24rpx; }
.empty-emoji { font-size: 100rpx; opacity: 0.5; }
.empty-text { font-size: 26rpx; color: #bbb; }
</style>
