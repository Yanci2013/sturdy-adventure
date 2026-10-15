<template>
	<view v-if="mode === 'default'" class="chat-message flex-row" :class="{reverse: isReverse && isSenderThemselve}">
		<xbc-avatar :src="avatar || message.senderAvatar" />
		<view class="chat-message-info flex-column" :style="{ maxWidth }">
			<view v-if="userName" class="chat-message-name">{{ userName }}</view>
			<slot>
				<xbc-message-system v-if="message.type == 'system'" :message="message" />
				<xbc-message-system v-if="message.type == 'notice'" :message="message" />
				<xbc-message-location v-if="message.type == 'location'" :message="message" />
				<xbc-message-card v-if="message.type == 'card'" :message="message" />
				<xbc-message-file v-if="message.type == 'file'" :message="message" />
				<xbc-message-image v-if="message.type == 'image'" :message="message" />
				<xbc-message-video v-if="message.type == 'video'" :message="message" />
				<xbc-message-audio v-if="message.type == 'audio'" :message="message" />
				<xbc-message-text v-if="message.type == 'text'" :message="message" />
				<xbc-message-text v-if="isQuote()" />
			</slot>
		</view>
	</view>
	<view v-if="mode === 'custom'">
		<slot></slot>
	</view>
</template>

<script setup>
import { useMessage } from '@/hooks/useMessage'
// H5分包中easycom不生效，手动导入子组件
import XbcAvatar from '@/compontents/xbc-avatar/xbc-avatar.vue'
import XbcMessageSystem from '@/compontents/xbc-message-system/xbc-message-system.vue'
import XbcMessageLocation from '@/compontents/xbc-message-location/xbc-message-location.vue'
import XbcMessageCard from '@/compontents/xbc-message-card/xbc-message-card.vue'
import XbcMessageFile from '@/compontents/xbc-message-file/xbc-message-file.vue'
import XbcMessageImage from '@/compontents/xbc-message-image/xbc-message-image.vue'
import XbcMessageVideo from '@/compontents/xbc-message-video/xbc-message-video.vue'
import XbcMessageAudio from '@/compontents/xbc-message-audio/xbc-message-audio.vue'
import XbcMessageText from '@/compontents/xbc-message-text/xbc-message-text.vue'

const props = defineProps({
  mode: { type: String, default: 'default' }, // 渲染模式
  avatar: { type: String, default: '' },
  message: { type: Object, default: () => {} },
  isReverse: { type: Boolean, default: true },  // 是否控制消息的方向
  maxWidth: { type: String, default: '80%' },  // 最大宽度
})

const { isSenderThemselve, userName } = useMessage(props)

function htmlDecode(str) {
  if (!str) return str
  return str.replace(/&quot;/g, '"')
            .replace(/&amp;/g, '&')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&nbsp;/g, ' ')
}

function isQuote() {
  try {
    const content = htmlDecode(props.message?.content || '{}')
    return JSON.parse(content)?.quote
  } catch (e) {
    return false
  }
}
</script>

<style lang="scss" scoped>
.chat-message {
  display: flex;
  flex-direction: row;
  padding: 20rpx 30rpx;
  align-items: flex-start;
  justify-content: flex-start;

  &-info {
    max-width: 70%;
    padding: 0 20rpx;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
  }

  &-name {
    color: #666;
    font-size: 28rpx;
    text-align: left;
    margin-bottom: 10rpx;
    transform: translateY(-6rpx);
  }

  &.reverse {
    flex-direction: row-reverse !important;

    .chat-message-info {
      align-items: flex-end;
    }

    .chat-message-name {
      text-align: right;
    }
  }
}
</style>