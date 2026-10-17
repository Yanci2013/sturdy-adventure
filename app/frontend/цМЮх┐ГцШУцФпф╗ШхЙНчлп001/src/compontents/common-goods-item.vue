<template>
	<view class="flex align-start">
		<u-image :src="status=='order'?item.logo:item.image" width="120rpx" height="120rpx" radius="20rpx"></u-image>
		<view class="ml-2 flex-1">
			<view class="flex align-center justify-between">
				<view class="text-main font-30">{{status=='order'?item.title:item.name}}</view>
				<view class="text-lightRed font-30">￥{{status=='order'?item.price:'0.00'}}</view>
			</view>
			<view class="flex align-center justify-between mt-2" v-if="status=='order'">
				<view class="text-light9 font-24">{{item.synopsis}}</view>
				<view class="text-light9 font-24">×{{item.number}}</view>
			</view>
		</view>
	</view>
</template>

<script setup>
	const props = defineProps({
		item: Object,
		status: {
			type: String,
			default: 'order'
		}
	})
</script>

<style>
</style>