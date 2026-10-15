<template>
	<view class="common-navbar" :style="{ background: bgColor }">
		<view class="navbar-capsule">
			<view class="navbar-btn" @click="goBack">
				<up-icon name="arrow-left" size="34rpx" color="#606266"></up-icon>
			</view>
			<view class="navbar-divider"></view>
			<view class="navbar-btn" @click="goHome">
				<up-icon name="home" size="36rpx" color="#606266"></up-icon>
			</view>
		</view>
		<text class="navbar-title">{{ title || clubName }}</text>
		<view class="navbar-placeholder"></view>
	</view>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const props = defineProps({
	title: { type: String, default: '' },
	bgColor: { type: String, default: '#fff' }
});

const clubName = ref(uni.getStorageSync('club_name') || '')

// 若缓存暂时为空（冷启动竞态），稍后重试一次
onMounted(() => {
	if (!clubName.value) {
		setTimeout(() => {
			clubName.value = uni.getStorageSync('club_name') || ''
		}, 1000)
	}
})

const goBack = () => {
	const p = getCurrentPages();
	if (p.length > 1) uni.navigateBack({ delta: 1 });
	else uni.reLaunch({ url: '/pages/index/index' });
};
const goHome = () => uni.reLaunch({ url: '/pages/index/index' });
</script>

<style scoped>
.common-navbar {
	position: relative;
	z-index: 10;
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 16rpx 24rpx;
	padding-top: calc(var(--status-bar-height, 0px) + 16rpx);
}
.navbar-capsule {
	display: flex;
	align-items: center;
	border: 1rpx solid #dadbde;
	border-radius: 100px;
	padding: 8rpx 16rpx;
	background: rgba(255,255,255,0.9);
}
.navbar-btn { padding: 4rpx 8rpx; }
.navbar-divider {
	width: 1rpx; height: 32rpx;
	background: #dadbde;
	margin: 0 8rpx;
}
.navbar-title { font-size: 34rpx; font-weight: 600; color: #333; }
.navbar-placeholder { width: 120rpx; }
</style>
