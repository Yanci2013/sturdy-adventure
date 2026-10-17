<template>
	<view class="flex-row">
		<xbc-message-read :show="showReadStatus && !['group'].includes(message.conversationType) && isSenderThemselve" :isRead="isRead" />
		<image
			class="chat-message-image"
			mode="aspectFit"
			:style="style" 
			:class="{reverse: isReverse && isSenderThemselve}"
			:src="thumbUrl"
			@tap="preview"/>
	</view>
</template>

<script setup>
import { reactive, computed } from 'vue'
import { messageProps, useMessage } from '@/hooks/useMessage'

const MAX_SIZE = 200
const size = reactive({ width: 0, height: 0 })
const props = defineProps(messageProps)
const { isSenderThemselve, isRead, messageContent } = useMessage(props)
const data = computed(() => messageContent.value?.data || {})
const thumbUrl = computed(() => {
	return data.value.thumbUrl || data.value.url || props.message?.extra?.url || ''
})
const style = computed(() => {
	const { width, height } = data.value
	if (!width || !height) {
		if (!thumbUrl.value) return { width: MAX_SIZE + 'px', height: MAX_SIZE + 'px' }
		uni.getImageInfo({
			src: thumbUrl.value,
			success: ({ width: imgWidth, height: imgHeight }) => {
				size.width = imgWidth
				size.height = imgHeight
			}
		})
	} else {
		size.width = width
		size.height = height
	}

	let w = Number(size.width) || MAX_SIZE
	let h = Number(size.height) || MAX_SIZE
	// 按比例缩放，最大边不超过 MAX_SIZE
	if (w >= h) {
		h = Math.round(h / w * MAX_SIZE)
		w = MAX_SIZE
	} else {
		w = Math.round(w / h * MAX_SIZE)
		h = MAX_SIZE
	}
	return { width: w + 'px', height: h + 'px' }
})

function preview(){
	const url = data.value.url || thumbUrl.value
	if (!url) return
	uni.previewImage({
		urls: [url]
	})
}

// 右键菜单
const commands = [
	{ name: '引用', command: 'quote' },
	{ name: '删除', command: 'deleteMessage' }
]
</script>

<style lang="scss" scoped>
.chat-message-image {
	max-width: 400rpx;
	max-height: 400rpx;
	border-radius: 0 10rpx 10rpx 10rpx;

	&.reverse {
		border-radius: 10rpx 0 10rpx 10rpx;
	}
}
</style>
