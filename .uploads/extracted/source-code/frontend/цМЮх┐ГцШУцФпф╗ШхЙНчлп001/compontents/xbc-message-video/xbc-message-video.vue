<template>
  <view class="flex-row">
    <xbc-message-read :show="showReadStatus && !['group'].includes(message.conversationType) && isSenderThemselve" :isRead="isRead" />
    <view
      @tap="playVideo"
      class="chat-message-video flex-column" 
      :style="style" 
      :class="{reverse: isReverse && isSenderThemselve}">
      <image v-if="data.coverUrl" class="chat-message-video-cover" :src="data.coverUrl" mode="aspectFill" />
      <view v-else class="chat-message-video-empty"></view>
      <view class="chat-message-video-mask flex-column">
        <text class="chat-message-video-play iconfont icon-play"></text>
      </view>
      <view class="chat-message-video-info flex-row">
        <text>{{ sizeText }}</text>
        <text>{{ durationText }}</text>
      </view>
    </view>
  </view>
</template>

<script setup>
import { computed } from 'vue'
import { messageProps, useMessage } from '@/hooks/useMessage'

const props = defineProps(messageProps)
const { isSenderThemselve, isRead, messageContent } = useMessage(props)
const data = computed(() => messageContent.value?.data || {})
const style = computed(() => {
  let width = Number(data.value.width || 320)
  let height = Number(data.value.height || 176)
  const max = 200
  if (width >= height) {
    height = Math.round(height / width * max)
    width = max
  } else {
    width = Math.round(width / height * max)
    height = max
  }
  return { width: width + 'px', height: height + 'px' }
})
const sizeText = computed(() => formatBytes(data.value.size))
const durationText = computed(() => formatDuration(data.value.duration))

function formatBytes(size) {
  const num = Number(size || 0)
  if (!num) return '0B'
  if (num < 1024) return num + 'B'
  if (num < 1024 * 1024) return (num / 1024).toFixed(1) + 'KB'
  return (num / 1024 / 1024).toFixed(1) + 'MB'
}

function formatDuration(duration) {
  const total = Math.round(Number(duration || 0))
  const m = String(Math.floor(total / 60)).padStart(2, '0')
  const s = String(total % 60).padStart(2, '0')
  return `${m}:${s}`
}

function playVideo() {
  const url = data.value.url
  if (!url) return uni.showToast({ title: '视频地址为空', icon: 'none' })
  uni.previewMedia({
    sources: [{ url, type: 'video' }]
  })
}
</script>

<style lang="scss" scoped>
@import '@/uni.scss';
.chat-message-video {
  width: 400rpx;
  height: 250rpx;
  cursor: pointer;
  overflow: hidden;
  position: relative;
  border-radius: 0 10rpx 10rpx 10rpx;

  &.reverse {
    border-radius: 10rpx 0 10rpx 10rpx;
  }

  &-cover {
    width: 100%;
    height: 100%;
  }

  &-empty {
    width: 100%;
    height: 100%;
    background: #222;
  }

  &-mask {
    @include fullsize();
    position: absolute;
    top: 0;
    left: 0;
    z-index: 1;
    background-color: rgba(0, 0, 0, 0.2);
  }

  &-info {
    width: 100%;
    color: #f5f5f5;
    font-size: 24rpx;
    padding: 10rpx 16rpx;
    position: absolute;
    bottom: 0;
    left: 0;
    z-index: 2;
    background: linear-gradient(to bottom, rgba(0, 0, 0, 0) 0%, rgba(0, 0, 0, 0.1) 30%, rgba(0, 0, 0, 0.8) 100%);
  }

  &-play {
    color: #f5f5f5;
    font-size: 76rpx;
  }
}
</style>
