<template>
	<view class="flex align-center justify-center" :class="border?'bor':''"
		:style="{color:color,background:btnBackground,margin:margin,height:height,borderRadius: typeof raduis === 'number' ? raduis + 'rpx' : raduis}"
		@click="click">{{title}}</view>
</template>

<script setup>
	import { ref, computed, onMounted } from 'vue';
	const props = defineProps({
		title: String,
		color: {
			type: String,
			default: '#FFFFFF'
		},
		background: {
			type: String,
			default: ''
		},
		margin: {
			type: String,
			default: '100rpx 22rpx 10rpx'
		},
		height: {
			type: String,
			default: '88rpx'
		},
		raduis: {
			type: [Number, String],
			default: 44
		},
		border: {
			type: Boolean,
			default: false
		}
	})

	const uiConfig = ref({});
	onMounted(() => {
		try {
			const saved = uni.getStorageSync('ui_config');
			if (saved && typeof saved === 'object') uiConfig.value = saved;
		} catch (e) {}
	})

	const btnBackground = computed(() => {
		if (props.background) return props.background;
		const primary = uiConfig.value.theme_primary || '#FF7A45';
		const secondary = uiConfig.value.theme_secondary || '#FF9D6E';
		return `linear-gradient(270deg, ${primary} 0%, ${secondary} 100%)`;
	})

	const emit = defineEmits(['click'])

	const click = () => {
		emit('click')
	}
</script>

<style>
	.bor {
		border: 1rpx solid #BABABA;
	}
</style>