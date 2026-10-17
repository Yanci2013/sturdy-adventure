<template>
	<view>
		<view class="font-24" :style="{color:color}">￥<text class="font-md">{{price}}</text>
		</view>
	</view>
</template>

<script setup>
	const props = defineProps({
		price: [String, Number],
		color: {
			type: String,
			default: '#FA5548'
		},
	})
</script>

<style>
	.plus {
		font-size: 52rpx;
	}
</style>