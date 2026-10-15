<template>
	<!-- :width="thugId?'307rpx"':' 357rpx'" :height="thugId?'300rpx':'300rpx' " -->
	<view class="mt-2 bg-white" @click="gotoPage('pagesA/index/detail',item.id)">
		<up-image :src="item.logo" :width="thugId?'337rpx':'357rpx'" :height="thugId?'300rpx':'300rpx'"
			radius="5rpx"></up-image>
		<view class="p-2">
			<view class="text-main font-30 font-weight-bolder mt-2 textline1">{{item.title}}</view>
			<view class="" v-if="item.synopsis" style="height: 100rpx;">
				<up-text margin="10rpx 0" :text="item.synopsis" color="#666666" lines="2"></up-text>
			</view>

			<view class="" v-else style="height: 100rpx;"></view>
			<view class="flex align-center">
				<up-text mode="price" :text="item.price" bold color="#FA5548" size="32rpx"></up-text>
				<up-text decoration="line-through" mode="price" color="#7F8996" :text="item.line_price" size="30rpx"></up-text>
			</view>
		</view>

	</view>
</template>

<script setup>
	import router from '../utils/router';

	const props = defineProps({
		item: Object,
		thugId: String
	})
	const gotoPage = (url, data) => {
		router.navigateTo({
			url: url,
			data: {
				id: data,
				thugId: props.thugId
			}
		})
	}
</script>

<style>
	.textline1 {
		word-break: break-all;
		text-overflow: ellipsis;
		display: -webkit-box;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 1;
		overflow: hidden;
	}
</style>