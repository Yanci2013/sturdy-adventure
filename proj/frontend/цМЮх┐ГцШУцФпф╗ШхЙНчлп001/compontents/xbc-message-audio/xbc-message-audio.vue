<template>
	<view class="flex-row">
		<xbc-message-read :show="showReadStatus && !['group'].includes(message.conversationType) && isSenderThemselve" :isRead="isRead" />
		<view
			@tap="playAudio"
			class="chat-message-audio flex-row"
			:class="{reverse: isReverse && isSenderThemselve}" >
			<image v-show="isPlaying" class="audio" src="./img/audio-play.gif"></image>
			<image v-show="!isPlaying" class="audio" src="./img/audio.png"></image>
		</view>
	</view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { messageProps, useMessage } from '@/hooks/useMessage'

const props = defineProps(messageProps)
const isPlaying = ref(false)
const innerAudioContext = uni.createInnerAudioContext();
const { isSenderThemselve, isRead, messageContent } = useMessage(props)

// 更新源
innerAudioContext.src = messageContent.value?.data?.url;

// 监听播放
innerAudioContext.onPlay(() => {
	isPlaying.value = true
})
innerAudioContext.onPause(() => {
	isPlaying.value = false
})
innerAudioContext.onStop(() => {
	isPlaying.value = false
})
innerAudioContext.onEnded(() => {
	isPlaying.value = false
})

// 播放
function playAudio() {
	if(isPlaying.value){
		innerAudioContext.pause()
	}else{
		innerAudioContext.play()
	}
}
</script>

<style lang="scss" scoped>
.chat-message-audio {
	width: 30vw;
	color: #333;
	min-height: 88rpx;
	user-select: text;
	padding: 20rpx 24rpx;
	word-break: break-all;
	transition: all 0.1s ease;
	border-radius: 0 10rpx 10rpx 10rpx;
	background-color: rgba(0, 0, 0, 0.05);

	.audio {
		width: 32rpx;
		height: 44rpx;
	}
	
	&.reverse {
		justify-content: flex-end;
		
		.audio {
			transform: rotate(180deg);
		}
	}

	&:hover {
		background-color: rgba(0, 0, 0, 0.08);
	}

	&.reverse {
		background-color: rgba(131, 216, 88, 0.8);
		border-radius: 10rpx 0 10rpx 10rpx;

		&:hover {
			background-color: rgba(131, 216, 88, 0.9);
		}
	}
}
</style>