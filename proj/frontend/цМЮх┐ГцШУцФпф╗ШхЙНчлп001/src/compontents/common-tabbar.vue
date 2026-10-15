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
				iconPath: '/static/ym/11.png',
				selectedIconPath: '/static/ym/1.png',
				text: '首页',
				url: '/pages/index/index'
			},
			{
				iconPath: '/static/ym/22.png',
				selectedIconPath: '/static/ym/2.png',
				text: '分类',
				url: '/pages/classify/index'
			},
			{
				iconPath: '/static/ym/33.png',
				selectedIconPath: '/static/ym/3.png',
				text: '挑选打手',
			url: '/pages/player/index'
			},
			{
				iconPath: '/static/ym/44.png',
				selectedIconPath: '/static/ym/4.png',
				text: '消息',
				url: '/pages/message/index'
			},
			{
				iconPath: '/static/ym/55.png',
				selectedIconPath: '/static/ym/5.png',
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
		bottom: 20rpx;
		left: 0;
		right: 0;
		z-index: 9999;
		padding: 0 32rpx calc(env(safe-area-inset-bottom));
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
		height: 108rpx !important;
		width: 100% !important;
		max-width: 710rpx !important;
		margin: 0 auto !important;
		background: linear-gradient(135deg, rgba(255, 255, 255, 0.85), rgba(255, 244, 235, 0.65)) !important;
		border-radius: 999rpx !important;
		box-shadow: 0 20rpx 48rpx rgba(255, 122, 69, 0.22), inset 0 2rpx 4rpx rgba(255, 255, 255, 0.88), inset 0 -8rpx 18rpx rgba(255, 122, 69, 0.06);
		border: 1rpx solid rgba(255, 255, 255, 0.82) !important;
		pointer-events: auto;
		backdrop-filter: blur(11px);
		-webkit-backdrop-filter: blur(11px);
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
	}

	.tabbar-active {
		background: transparent !important;
	}

	.tabbar-icon {
		width: 38rpx !important;
		height: 38rpx !important;
		margin-bottom: 2rpx !important;
		pointer-events: none;
		display: block !important;
		filter: drop-shadow(0 2px 4px rgba(255, 122, 69, 0.18));
	}

	.tabbar-text {
		font-size: 18rpx !important;
		line-height: 1 !important;
		color: rgba(60, 40, 30, 0.55) !important;
		font-weight: 400;
		pointer-events: none;
		display: block !important;
	}

	.tabbar-active .tabbar-text {
		color: #FF7A45 !important;
		font-weight: 600;
	}
</style>
