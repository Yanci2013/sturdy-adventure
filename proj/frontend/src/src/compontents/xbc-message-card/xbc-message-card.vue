<template>
	<view class="flex-row">
		<xbc-message-read :show="showReadStatus && !['group'].includes(message.conversationType) && isSenderThemselve"
			:isRead="isRead" />
		<view @tap="openCard" class="chat-message-card flex-row" :class="{reverse: isReverse && isSenderThemselve}">
			<image class="image" :src="messageContent?.data?.imageUrl" mode="widthFix"></image>
			<view class="chat-message-card-info">
				<view v-if="messageContent?.data?.title" class="title" v-html="messageContent?.data?.title"></view>
				<view v-if="messageContent?.data?.summary" class="summary" v-html="messageContent?.data?.summary">
				</view>
			</view>
		</view>
	</view>
</template>

<script setup>
import XBCIM from 'xbcim'
import { computed } from 'vue'
import { messageProps, useMessage } from '@/hooks/useMessage'

const props = defineProps(messageProps)
const { isSenderThemselve, isRead, messageContent } = useMessage(props)

function openCard() {
	const href = messageContent.value?.data?.href
	// #ifdef APP-PLUS
	plus.runtime.openURL(href);
	// #endif
	// #ifdef H5
	window.open(href)
	// #endif
	// #ifdef MP
	uni.setClipboardData({
		data: href
	});
	uni.showModal({
		content: '链接复制成功',
		showCancel: false
	});
	// #endif
}
</script>

<style lang="scss" scoped>
@import '@/uni.scss';
.chat-message-card {
	flex: 1;
	color: #333;
	padding: 20rpx;
	width: fit-content;
	background-color: #fff;
	align-items: flex-start;
	border-radius: 0 10rpx 10rpx 10rpx;

	.image {
		width: 100rpx;
		max-height: 200rpx;
		margin-right: 20rpx;
	}

	&-info {
		width: calc(100% - 120rpx);

		.title {
			font-size: 30rpx;
			margin-bottom: 10rpx;
			@include text-ellipsis()
		}

		.summary {
			color: #666;
			font-size: 24rpx;
			@include text-ellipsis()
		}
	}
}
</style>