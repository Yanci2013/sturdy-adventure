<template>
	<view class="flex-row">
		<xbc-message-read :show="showReadStatus && !['group'].includes(message.conversationType) && isSenderThemselve" :isRead="isRead" />
		<view @tap="openLocation" class="xbc-message-location">
			<view class="head">
				<view class="name">{{ messageContent?.data?.name }}</view>
				<view class="address text-ellipsis">{{ messageContent?.data?.address }}</view>
			</view>
			<image class="image" src="@/static/img/location.jpg" mode="widthFix"></image>
		</view>
	</view>
</template>

<script setup>
import XBCIM from 'xbcim'
import { computed } from 'vue'
import { messageProps, useMessage } from '@/hooks/useMessage'

const props = defineProps(messageProps)
const { isSenderThemselve, isRead, messageContent } = useMessage(props)

function openLocation() {
	const { latitude, longitude } = messageContent.value?.data
	uni.openLocation({
		latitude,
		longitude,
		complete(res){
			console.log(111, res)
		}
	})
}
</script>

<style lang="scss" scoped>

.xbc-message-location {
	width: 440rpx;
	cursor: pointer;
	overflow: hidden;
	border: 2rpx solid #fff;
	border-radius: 0 10rpx 10rpx 10rpx;
	
	&.reverse {
		border-radius: 10rpx 0 10rpx 10rpx;
	}
	
	.head {
		padding: 10rpx 20rpx;
		background-color: #fff;
		
		.name {
			color: #333;
			font-size: 30rpx;
		}
		
		.address {
			color: #999;
			font-size: 24rpx;
			margin-top: 2rpx;
		}
	}
	
	.image {
		width: 100%;
		max-height: 200rpx;
	}
}
</style>