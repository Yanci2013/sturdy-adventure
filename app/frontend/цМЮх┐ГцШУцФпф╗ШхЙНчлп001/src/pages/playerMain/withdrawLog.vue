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
			<text class="navbar-title">提现记录</text>
			<view class="navbar-placeholder"></view>
		</view>

		<scroll-view scroll-y class="content" @scrolltolower="loadMore">
			<view class="content-inner">
				<view class="record-card" v-for="(item, index) in list" :key="index">
					<view class="record-left">
						<text class="record-title">{{ item.channel_text }}提现</text>
						<text class="record-sub">{{ item.name }} · {{ item.account }}</text>
						<text class="record-time">{{ item.createtime }}</text>
					</view>
					<view class="record-right">
						<text class="record-amount">-{{ item.money }}</text>
						<text :class="['record-status', 'status-' + item.status]">{{ item.status_text }}</text>
					</view>
				</view>

				<uv-empty v-if="!loading && !list.length" mode="data" text="暂无提现记录" style="margin-top:100rpx"></uv-empty>
				<view class="load-more" v-if="list.length">
					<text class="load-more-text">{{ loading ? '加载中...' : (noMore ? '没有更多了' : '') }}</text>
				</view>
				<view class="bottom-spacer"></view>
			</view>
		</scroll-view>
	</view>
</template>

<script setup>
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { withdrawalList } from '../../request/api'

const list = ref([])
const page = ref(1)
const loading = ref(false)
const noMore = ref(false)
const type = uni.getStorageSync('type')

onShow(() => {
	page.value = 1
	list.value = []
	noMore.value = false
	loadData()
})

const loadData = async () => {
	if (loading.value) return
	loading.value = true
	try {
		const res = await withdrawalList({ page: page.value, limit: 15, type: type == '用户' ? 1 : 2 })
		let resData = res.data || res
		let newList = Array.isArray(resData) ? resData : (resData.data || [])
		if (page.value === 1) {
			list.value = newList
		} else {
			list.value = [...list.value, ...newList]
		}
		if (newList.length < 15) noMore.value = true
	} catch (e) {
		console.log('withdrawalList error:', e)
	} finally {
		loading.value = false
	}
}

const loadMore = () => {
	if (noMore.value || loading.value) return
	page.value++
	loadData()
}

const goBack = () => { uni.navigateBack() }
const goHome = () => { uni.reLaunch({ url: '/pages/index/index' }) }
</script>

<style scoped>
.page { min-height: 100vh; background: #f5f6f9; display: flex; flex-direction: column; }
.navbar {
	position: relative; z-index: 20; display: flex; align-items: center; justify-content: space-between;
	padding: 0 24rpx; padding-top: calc(var(--status-bar-height, 0px) + 16rpx); background: #fff;
}
.navbar-capsule { display: flex; align-items: center; border: 1rpx solid #dadbde; border-radius: 100px; padding: 8rpx 20rpx; background: rgba(255,255,255,0.3); }
.navbar-btn { padding: 4rpx 8rpx; }
.navbar-divider { width: 1rpx; height: 32rpx; background: #dadbde; margin: 0 8rpx; }
.navbar-title { font-size: 34rpx; font-weight: 600; color: #333; }
.navbar-placeholder { width: 120rpx; }
.content { flex: 1; }
.content-inner { padding: 24rpx; }
.record-card {
	display: flex; justify-content: space-between; align-items: flex-start;
	padding: 28rpx; margin-bottom: 16rpx;
	background: rgba(255,255,255,0.55); backdrop-filter: blur(24rpx); -webkit-backdrop-filter: blur(24rpx);
	border: 1rpx solid rgba(255,255,255,0.7); border-radius: 24rpx;
	box-shadow: 0 2rpx 20rpx rgba(0,0,0,0.04), inset 0 1rpx rgba(255,255,255,0.9);
}
.record-left { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 8rpx; }
.record-title { font-size: 28rpx; color: #000; font-weight: 600; }
.record-sub { font-size: 24rpx; color: #666; }
.record-time { font-size: 22rpx; color: #999; }
.record-right { flex-shrink: 0; display: flex; flex-direction: column; align-items: flex-end; gap: 8rpx; }
.record-amount { font-size: 32rpx; color: #000; font-weight: 700; }
.record-status { font-size: 22rpx; padding: 4rpx 16rpx; border-radius: 8rpx; }
.status-0 { color: #f5a623; background: rgba(245,166,35,0.1); }
.status-1 { color: #07c160; background: rgba(7,193,96,0.1); }
.status-2 { color: #ee0a24; background: rgba(238,10,36,0.1); }
.load-more { padding: 24rpx; text-align: center; }
.load-more-text { font-size: 24rpx; color: #999; }
.bottom-spacer { height: 80rpx; }
</style>
