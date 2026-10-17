<template>
	<view v-if="showWave" class="layer">
		<wave />
	</view>
	<view
		@touchstart="onTouchStart"
		@touchend="onTouchEnd"
		class="textarea audio">
		按住 说话
	</view>
</template>

<script setup>
import { ref } from 'vue'
import Wave from './wave.vue'

const emit = defineEmits(['success'])
const showWave = ref(false)

let recorderManager = null
let innerAudioContext = null
try {
	recorderManager = uni.getRecorderManager()
	innerAudioContext = uni.createInnerAudioContext()
} catch (e) {
	console.warn('录音功能不可用(H5)', e)
}

if (recorderManager) {
	recorderManager.onStop(res => {
		if (!innerAudioContext) return
		innerAudioContext.src = res.tempFilePath
		innerAudioContext.onCanplay(() => {
			const duration = innerAudioContext.duration
			if (duration < 1) {
				uni.showToast({ icon: 'none', title: '录音时间太短' })
				return
			}
			setTimeout(() => { emit('success', { duration, url: res.tempFilePath }) }, 500)
		})
	})
}

function onTouchStart() {
	if (!recorderManager) { uni.showToast({ icon: 'none', title: '当前环境不支持录音' }); return }
	showWave.value = true
	recorderManager.start()
}
function onTouchEnd() {
	if (!recorderManager) return
	showWave.value = false
	recorderManager.stop()
}
</script>

<style lang="scss" scoped>
@import '@/uni.scss';
.layer {
	position: fixed; top: 0; right: 0; bottom: 0; left: 0; z-index: 999;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
}
.textarea {
	width: 100%; color: #333; font-size: 30rpx; max-height: 160px;
	padding-top: 20rpx; padding-bottom: 12rpx; background-color: #fff;
	&.audio {
		pointer-events: all; padding-top: 12rpx;
		@include fullsize(); position: absolute; top: 0; left: 0;
	}
}
</style>
