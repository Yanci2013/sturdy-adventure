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
				<view class="tabbar-ellipse" v-if="current === index"></view>
				<svg class="tabbar-icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" v-html="item.icon"></svg>
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
				icon: '<path d="M8 22 L24 8 L40 22"/><path d="M12 20 V40 H36 V20"/><path d="M20 40 V30 H28 V40"/>',
				text: '抢单大厅',
				url: '/pages/playerHome/index'
			},
			{
				icon: '<path d="M10 12 H38 C42 12 44 14 44 18 V32 C44 36 42 38 38 38 H22 L14 44 V38 H10 C6 38 4 36 4 32 V18 C4 14 6 12 10 12 Z"/><line x1="14" y1="22" x2="34" y2="22"/><line x1="14" y1="28" x2="26" y2="28"/>',
				text: '消息',
				url: '/pages/playerMain/message'
			},
			{
				icon: '<rect x="6" y="10" width="36" height="28" rx="4"/><path d="M6 18 H42"/><circle cx="12" cy="28" r="1.6" fill="currentColor" stroke="none"/><path d="M18 28 H36 M18 34 H28"/>',
				text: '订单',
				url: '/pages/playerOrder/index'
			},
			{
				icon: '<circle cx="24" cy="16" r="8"/><path d="M10 42 C10 32 16 26 24 26 C32 26 38 32 38 42"/>',
				text: '我的',
				url: '/pages/playerMain/index'
			}
		]
	})
	const onChange = (index) => {
		if (current === index) return;
		uni.reLaunch({
			url: tabList.value[index].url
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
	}

	.tabbar-capsule {
		display: flex;
		flex-direction: row;
		align-items: center;
		justify-content: space-around;
		height: 110rpx;
		width: 100%;
		max-width: 690rpx;
		margin: 0 auto;
		background: #ffffff;
		border-radius: 999rpx;
		box-shadow: 0 8rpx 32rpx rgba(0, 0, 0, 0.12);
		overflow: hidden;
		pointer-events: auto;
	}

	.tabbar-item {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		height: 100%;
		flex: 1;
		padding: 0 6rpx;
		color: rgba(60, 40, 30, 0.55);
		transition: all 0.2s ease;
		position: relative;
		pointer-events: auto;
		cursor: pointer;
	}

	.tabbar-active {
		color: #FF7A45;
	}

	.tabbar-ellipse {
		position: absolute;
		top: 50%;
		left: 50%;
		transform: translate(-50%, -50%);
		width: 90rpx;
		height: 90rpx;
		border-radius: 50%;
		background: rgba(255, 122, 69, 0.12);
		z-index: 0;
	}

	.tabbar-icon {
		width: 44rpx;
		height: 44rpx;
		margin-bottom: 4rpx;
		position: relative;
		z-index: 1;
	}

	.tabbar-text {
		font-size: 20rpx;
		line-height: 1;
		font-weight: 400;
		position: relative;
		z-index: 1;
	}

	.tabbar-active .tabbar-text {
		font-weight: 600;
	}
</style>
