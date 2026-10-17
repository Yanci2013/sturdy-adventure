<template>
	<view class="tabbar-wrap" :style="wrapStyle">
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
	import { ref, computed, onMounted } from 'vue';
	const props = defineProps({
		current: {
			type: Number,
			default: 0
		}
	})
	const tabList = ref([]);
	const uiConfig = ref({});
	onMounted(() => {
		tabList.value = [
			{
				icon: '<path d="M8 22 L24 8 L40 22"/><path d="M12 20 V40 H36 V20"/><path d="M20 40 V30 H28 V40"/>',
				text: '抢单大厅',
				url: '/pages/playerHome/index'
			},
			{
				icon: '<circle cx="24" cy="16" r="8"/><path d="M10 42 C10 32 16 26 24 26 C32 26 38 32 38 42"/>',
				text: '我的',
				url: '/pages/playerMain/index'
			}
		]
		try {
			const saved = uni.getStorageSync('ui_config');
			if (saved && typeof saved === 'object') uiConfig.value = saved;
		} catch (e) {}
	})
	const activeColor = computed(() => uiConfig.value.theme_primary || '#FF7A45');
	const ellipseColor = computed(() => uiConfig.value.tabbar_ellipse_color || (activeColor.value + '22'));
	const wrapStyle = computed(() => ({
		'--tabbar-active': activeColor.value,
		'--tabbar-ellipse': ellipseColor.value
	}));
	const onChange = (index) => {
		if (props.current === index) return;
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
		color: var(--tabbar-active, #FF7A45);
	}

	.tabbar-ellipse {
		position: absolute;
		top: 50%;
		left: 50%;
		transform: translate(-50%, -50%);
		width: 90rpx;
		height: 90rpx;
		border-radius: 50%;
		background: var(--tabbar-ellipse, rgba(255, 122, 69, 0.12));
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
