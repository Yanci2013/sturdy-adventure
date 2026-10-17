<template>
	<view class="flex-row">
		<xbc-message-read :show="showReadStatus && !['group'].includes(message.conversationType) && isSenderThemselve" :isRead="isRead" />
		<view @tap="openFile" class="chat-message-file flex-row" :class="{reverse: isReverse && isSenderThemselve}">
			<view class="chat-message-file-info">
				<view class="chat-message-file-name">{{ file.name }}</view>
				<view class="chat-message-file-size">{{ XBCIM.util.formatBytes(file.size) }}</view>
			</view>
			<image class="chat-message-file-icon" :src="file.icon" />
		</view>
	</view>
</template>

<script setup>
import XBCIM from 'xbcim'
import { computed } from 'vue'
import { messageProps, useMessage } from '@/hooks/useMessage'

const props = defineProps(messageProps)
const { isSenderThemselve, isRead, messageContent } = useMessage(props)

// 文件信息
const file = computed(() => {
	const name = messageContent.value?.data?.name || '未知文件'
	const size = messageContent.value?.data?.size || ''
	const ext = name.split('.').pop()
	const icon = `/static/img/svg/${XBCIM.util.getFileType(ext)}.svg`
	return { name, ext, icon, size }
})

// 打开文件
function openFile() {
	uni.downloadFile({
		url: encodeURIComponent(messageContent.value.data.url),
		success: res => {
			uni.openDocument({
				filePath: res.tempFilePath,
				showMenu: true,
				fail(error){
					uni.showToast({
						icon: 'none',
						title: '文件打开失败'
					})
				}
			})
		},
		fail(){
			uni.showToast({
				icon: 'none',
				title: '文件下载失败'
			})
		}
	})
}
</script>

<style lang="scss" scoped>
@import '@/uni.scss';
.chat-message-file {
	flex: 1;
	color: #333;
	cursor: pointer;
	padding: 20rpx 24rpx;
	align-items: flex-start;
	transition: all 0.1s ease;
	border-radius: 0 10rpx 10rpx 10rpx;
	background-color: rgba(0, 0, 0, 0.05);

	&:hover {
		background-color: rgba(0, 0, 0, 0.08);
	}

	&.reverse {
		border-radius: 10rpx 0 10rpx 10rpx;
	}

	&-info {
		flex: 1;
		margin-right: 20rpx;
	}

	&-name {
		color: #333;
		max-width: 360rpx;
		font-size: 30rpx;
		font-weight: 500;
		margin-bottom: 10rpx;
		@include text-ellipsis(2);
	}

	&-size {
		font-size: 26rpx;
		color: #999;
	}

	&-icon {
		height: 96rpx;
		max-width: 100rpx;
	}
}
</style>