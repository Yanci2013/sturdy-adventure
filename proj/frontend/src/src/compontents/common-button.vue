<template>
	<view class="flex align-center justify-center" :class="border?'bor':''"
		:style="{color:color,background:background,margin:margin,height:height,borderRadius: typeof raduis === 'number' ? raduis + 'rpx' : raduis}"
		@click="click">{{title}}</view>
</template>

<script setup>
	const props = defineProps({
		title: String,
		color: {
			type: String,
			default: '#FFFFFF'
		},
		background: {
			type: String,
			default: 'linear-gradient( 270deg, #FF7A45 0%, #FF9D6E 100%)'
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