<template>
	<scroll-view :style="{height: height + 'px'}" class="emoji" :class="{show}" scroll-y>
		<view class="emoji-wrapper">
			<view @tap="select(emoji)" class="emoji-item" v-for="(emoji, index) in emojis" :key="index">{{ emoji }}</view>
		</view>
	</scroll-view>
</template>

<script setup>
import { ref } from 'vue'
import emojiList from './emoji'

const emojis = ref(emojiList)
const emit = defineEmits(['select'])
const height = ref(uni.getStorageSync('uni_keyboard_height') || '260')
const props = defineProps({
	show: { type: Boolean, default: false }
})

function select(emoji) {
	emit('select', emoji)
}
</script>

<style lang="scss" scoped>
.emoji {
	height: 300px;
	opacity: 0;
	transition: opacity 0.2s ease;
	
	&.show {
		opacity: 1;
	}
	
	&-wrapper {
		padding: 0 20rpx 20rpx;
		display: flex;
		flex-wrap: wrap;
		
		.emoji-item {
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			height: 80rpx;
			font-size: 48rpx;
			width: calc(100% / 8);
			border-radius: 6rpx;
		
			&:active {
				background-color: #e5e5e5;
			}
		}
	}
}
</style>