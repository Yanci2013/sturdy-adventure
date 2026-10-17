<template>
	<view class="tabbar-wrap">
		<view class="tabbar-capsule">
			<view 
				v-for="(item, index) in tabList" 
				:key="index" 
				class="tabbar-item"
				:class="{ 'tabbar-active': current === index }"
				@click="onChange(index)"
			>
				<image class="tabbar-icon" :src="current === index ? item.selectedIconPath : item.iconPath"></image>
				<text class="tabbar-text">{{ item.text }}</text>
			</view>
		</view>
	</view>
</template>
<script setup>
	import { onMounted, ref } from 'vue';
	const props = defineProps({
		current: Number
	})
	const tabList = ref([]);
	onMounted(() => {
		tabList.value = [{
				iconPath: '/static/tabbar/home.svg',
				selectedIconPath: '/static/tabbar/home-active.svg',
				text: '首页',
				url: '/pages/index/index'
			},
			{
				iconPath: '/static/tabbar/classify.svg',
				selectedIconPath: '/static/tabbar/classify-active.svg',
				text: '分类',
				url: '/pages/classify/index'
			},
			{
				iconPath: '/static/tabbar/player.svg',
				selectedIconPath: '/static/tabbar/player-active.svg',
				text: '挑选打手',
			url: '/pages/player/index'
			},
			{
				iconPath: '/static/tabbar/message.svg',
				selectedIconPath: '/static/tabbar/message-active.svg',
				text: '消息',
				url: '/pages/message/index'
			},
			{
				iconPath: '/static/tabbar/mine.svg',
				selectedIconPath: '/static/tabbar/mine-active.svg',
				text: '我的',
				url: '/pages/mine/index'
			}
		]
	})
	const onChange = (index) => {
		console.log('点击了:', tabList.value[index].text, tabList.value[index].url)
		uni.reLaunch({
			url: tabList.value[index].url,
			success: () => {
				console.log('跳转成功')
			},
			fail: (err) => {
				console.error('跳转失败:', err)
			}
		})
	};
</script>
<style scoped>
	.tabbar-wrap {
		position: fixed;
		bottom: 16rpx;
		left: 0;
		right: 0;
		z-index: 9999;
		padding: 0 24rpx calc(env(safe-area-inset-bottom));
		background: transparent;
		pointer-events: auto;
		display: block !important;
	}

	.tabbar-capsule {
		display: flex !important;
		flex-direction: row !important;
		flex-wrap: nowrap !important;
		align-items: center !important;
		justify-content: space-around !important;
		height: 110rpx !important;
		width: 100% !important;
		max-width: 690rpx !important;
		margin: 0 auto !important;
		background: #fff !important;
		border-radius: 999rpx !important;
		box-shadow: 0 8rpx 32rpx rgba(0, 0, 0, 0.12);
		pointer-events: auto;
		overflow: hidden;
	}

	.tabbar-item {
		display: flex !important;
		flex-direction: column !important;
		align-items: center !important;
		justify-content: center !important;
		height: 100% !important;
		min-width: 60rpx !important;
		padding: 0 10rpx !important;
		background: transparent !important;
		pointer-events: auto;
		cursor: pointer;
		flex: 1 !important;
		border-radius: 999rpx;
		transition: all 0.2s ease;
	}

	.tabbar-active {
		background: transparent !important;
	}

	.tabbar-icon {
		width: 44rpx !important;
		height: 44rpx !important;
		margin-bottom: 4rpx !important;
		pointer-events: none;
		display: block !important;
	}

	.tabbar-text {
		font-size: 20rpx !important;
		line-height: 1 !important;
		color: #999 !important;
		font-weight: 400;
		pointer-events: none;
		display: block !important;
	}

	.tabbar-active .tabbar-text {
		color: #FF7A45 !important;
		font-weight: 500;
	}
</style>
