<template>
	<view class="chat-message-quote" :class="{reverse: isReverse && isSenderThemselve}">
		<view class="chat-message-quote-content" :class="{'flex-row': quoteMessage.type != 'text'}">
			<span class="sender">{{ quoteMessage?.senderName }}：</span>
			<!--文本-->
			<span v-if="quoteMessage.type == 'text'">{{ quoteMessage.content?.data }}</span>
			<!--文件-->
			<span v-if="quoteMessage.type == 'file'">{{ quoteMessage.content?.data?.name }}</span>
			<!--视频-->
			<view v-if="quoteMessage.type == 'video'" @click="playVideo" class="video flex-column" :style="videoStyle">
				<image class="video-cover" :src="quoteMessage.content?.data?.coverUrl" />
				<view class="video-mask flex-column">
					<el-icon class="video-play">
						<VideoPlay />
					</el-icon>
				</view>
			</view>
			<!--图片-->
			<image
				v-if="quoteMessage.type == 'image'"
				:src="imgSrc"
				:style="imgStyle"
				:preview-src-list="[quoteMessage?.content?.data?.url]" />
		</view>
	</view>
</template>

<script setup>
	import { computed } from 'vue'
	import { messageProps, useMessage } from '@/hooks/useMessage'
	import XBCIM from 'xbcim'

	const props = defineProps(messageProps)
	const { isSenderThemselve, messageContent } = useMessage(props)
	const quoteMessage = computed(() => {
		return messageContent.value?.quote
	})
	const imgSrc = computed(() => {
		const { thumbUrl, url } = quoteMessage.value?.content?.data
		return thumbUrl || url
	})
	const imgStyle = computed(() => {
		const { width, height } = quoteMessage.value?.content?.data
		return XBCIM.util.getImageSize(width, height, 80)
	})
	const videoStyle = computed(() => {
		const { width, height } = quoteMessage.value?.content?.data
		return XBCIM.util.getImageSize(width, height, 100)
	})

	// 右键菜单
	const commands = []

	function playVideo() {

	}
</script>

<style lang="scss" scoped>
@import '@/uni.scss';
	.chat-message-quote {
		margin-top: 10rpx;

		&.reverse {
			text-align: right;
		}

		&-content {
			color: #666;
			padding: 20rpx;
			font-size: 26rpx;
			max-width: 600rpx;
			line-height: 36rpx;
			border-radius: 20rpx;
			text-align: left;
			display: inline-block;
			word-break: break-all;
			align-items: flex-start;
			background-color: #eaeaea;

			.sender {
				white-space: nowrap;
			}

			&.flex-row {
				display: flex;
			}

			.video {
				cursor: pointer;
				overflow: hidden;
				position: relative;

				&.reverse {
					border-radius: 10rpx 0 10rpx 10rpx;
				}

				&-cover {
					max-width: 100%;
					max-height: 100%;
					object-fit: cover;
				}

				&-mask {
					@include fullsize();
					position: absolute;
					top: 0;
					left: 0;
					z-index: 1;
					background-color: rgba(0, 0, 0, 0.2);
				}

				&-play {
					color: #f5f5f5;
					font-size: 56rpx;
				}
			}
		}

		&-icon {
			margin-right: 4rpx;
			transform: translateY(4rpx);
		}
	}
</style>