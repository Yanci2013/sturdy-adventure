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
	import { ref, onMounted } from 'vue';
	const props = defineProps({
		current: {
			type: Number,
			default: 0
		}
	})
	const tabList = ref([]);
	onMounted(() => {
		tabList.value = [
			{
				iconPath: '/static/ym/11.png',
				selectedIconPath: '/static/ym/1.png',
				text: '抢单大厅',
				url: '/pages/playerHome/index'
			},
			{
				iconPath: '/static/ym/55.png',
				selectedIconPath: '/static/ym/5.png',
				text: '我的',
				url: '/pages/playerMain/index'
			}
		]
	})
	const onChange = (index) => {
		const item = tabList.value[index]
		uni.reLaunch({
			url: item.url
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
		background: transparent !important;
		pointer-events: auto;
		display: block !important;
	}

	.tabbar-capsule {
		display: flex !important;
		flex-direction: row !important;
		flex-wrap: nowrap !important;
		align-items: center !important;
		justify-content: space-around !important;
		height: 112rpx !important;
		width: 100% !important;
		max-width: 710rpx !important;
		margin: 0 auto !important;
		background: var(--theme-tabbar-bg, linear-gradient(135deg, rgba(255, 255, 255, 0.85), rgba(255, 244, 235, 0.65))) !important;
		border-radius: 999rpx !important;
		box-shadow: 0 20rpx 52rpx rgba(255, 122, 69, 0.22), inset 0 2rpx 4rpx rgba(255, 255, 255, 0.88), inset 0 -8rpx 18rpx rgba(255, 122, 69, 0.06);
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
		height: 76rpx !important;
		min-width: 90rpx !important;
		padding: 0 16rpx !important;
		background: transparent !important;
		pointer-events: auto;
		cursor: pointer;
		flex: 1 !important;
		border-radius: 999rpx;
		transition: all 0.25s ease;
	}

	.tabbar-icon {
		width: 42rpx;
		height: 42rpx;
		flex-shrink: 0;
		filter: drop-shadow(0 2px 4px rgba(255, 122, 69, 0.2));
		transition: transform 0.25s ease;
	}

	.tabbar-active {
		background: var(--theme-tabbar-active, rgba(255, 122, 69, 0.12)) !important;
		box-shadow: 0 8rpx 20rpx rgba(255, 122, 69, 0.22);
	}

	.tabbar-active .tabbar-icon {
		transform: translateY(-2rpx) scale(1.08);
	}

	.tabbar-text {
		font-size: 20rpx !important;
		line-height: 1 !important;
		margin-top: 4rpx;
		color: var(--theme-tabbar-text, rgba(60, 40, 30, 0.55)) !important;
		font-weight: 500;
		pointer-events: none;
		display: block !important;
	}

	.tabbar-active .tabbar-text {
		color: var(--theme-tabbar-text-active, var(--theme-tabbar-active, #FF7A45)) !important;
		font-weight: 700;
	}
</style>
