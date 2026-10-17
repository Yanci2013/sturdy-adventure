<template>
	<view class="tabbar-wrap" :style="wrapStyle">
		<view class="tabbar-capsule" :style="capsuleStyle">
			<view
				v-for="(item, index) in tabList"
				:key="index"
				class="tabbar-item"
				:class="{ 'tabbar-active': current === index, 'tabbar-ellipse': current === index && styleMode === 'ellipse' }"
				@click="onChange(index)"
			>
				<svg class="tabbar-icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" v-html="item.icon"></svg>
				<text class="tabbar-text">{{ item.text }}</text>
			</view>
		</view>
	</view>
</template>
<script setup>
	import { onMounted, ref, computed } from 'vue';

	const props = defineProps({
		current: Number
	})

	const tabList = ref([]);
	const uiConfig = ref({});

	onMounted(() => {
		tabList.value = [
			{ icon: '<path d="M8 22 L24 8 L40 22"/><path d="M12 20 V40 H36 V20"/><path d="M20 40 V30 H28 V40"/>', text: '首页', url: '/pages/index/index' },
			{ icon: '<rect x="8" y="8" width="13" height="13" rx="2"/><rect x="27" y="8" width="13" height="13" rx="2"/><rect x="8" y="27" width="13" height="13" rx="2"/><rect x="27" y="27" width="13" height="13" rx="2"/>', text: '分类', url: '/pages/classify/index' },
			{ icon: '<path d="M14 14 H34 C40 14 44 18 44 24 C44 32 40 38 34 38 C30 38 28 36 24 36 C20 36 18 38 14 38 C8 38 4 32 4 24 C4 18 8 14 14 14 Z"/><line x1="11" y1="24" x2="17" y2="24"/><line x1="14" y1="21" x2="14" y2="27"/><circle cx="31" cy="22" r="1.8" fill="currentColor" stroke="none"/><circle cx="35" cy="26" r="1.8" fill="currentColor" stroke="none"/>', text: '挑选打手', url: '/pages/player/index' },
			{ icon: '<path d="M10 12 H38 C42 12 44 14 44 18 V32 C44 36 42 38 38 38 H22 L14 44 V38 H10 C6 38 4 36 4 32 V18 C4 14 6 12 10 12 Z"/><line x1="14" y1="22" x2="34" y2="22"/><line x1="14" y1="28" x2="26" y2="28"/>', text: '消息', url: '/pages/message/index' },
			{ icon: '<circle cx="24" cy="16" r="8"/><path d="M10 42 C10 32 16 26 24 26 C32 26 38 32 38 42"/>', text: '我的', url: '/pages/mine/index' }
		];
		try {
			const saved = uni.getStorageSync('ui_config');
			if (saved && typeof saved === 'object') uiConfig.value = saved;
		} catch (e) {}
	});

	const styleMode = computed(() => uiConfig.value.tabbar_style || 'ellipse');
	const activeColor = computed(() => uiConfig.value.tabbar_active_color || uiConfig.value.theme_primary || '#FF7A45');
	const inactiveColor = computed(() => uiConfig.value.tabbar_inactive_color || 'rgba(60,40,30,.55)');
	const bgColor = computed(() => uiConfig.value.tabbar_bg || '#ffffff');
	const ellipseColor = computed(() => uiConfig.value.tabbar_ellipse_color || (activeColor.value + '22'));

	const capsuleStyle = computed(() => ({ background: bgColor.value }));
	const wrapStyle = computed(() => ({
		'--tabbar-active': activeColor.value,
		'--tabbar-inactive': inactiveColor.value,
		'--tabbar-ellipse': ellipseColor.value
	}));

	const onChange = (index) => {
		uni.reLaunch({ url: tabList.value[index].url });
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
		background: #fff;
		border-radius: 999rpx;
		box-shadow: 0 8rpx 32rpx rgba(0, 0, 0, 0.12);
		overflow: hidden;
	}
	.tabbar-item {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		height: 100%;
		flex: 1;
		padding: 0 6rpx;
		color: var(--tabbar-inactive);
		transition: all 0.2s ease;
	}
	.tabbar-active {
		color: var(--tabbar-active);
	}
	.tabbar-ellipse {
		position: relative;
	}
	.tabbar-ellipse::before {
		content: '';
		position: absolute;
		top: 50%;
		left: 50%;
		transform: translate(-50%, -50%);
		width: 86rpx;
		height: 86rpx;
		border-radius: 50%;
		background: var(--tabbar-ellipse);
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
		font-weight: 500;
	}
</style>
