<template>
  <view class="flex-row">
    <xbc-message-read :show="showReadStatus && !['group'].includes(message.conversationType) && isSenderThemselve" :isRead="isRead" />
    <view
      id="chat-message-text"
      v-html="messageText"
      class="chat-message-text"
      :class="{reverse: isReverse && isSenderThemselve}"
      @tap="onClick">
    </view>
  </view>
</template>

<script setup>
import { computed, reactive } from 'vue'
import { messageProps, useMessage } from '@/hooks/useMessage'
import XBCIM from 'xbcim'

const props = defineProps(messageProps)
const { isSenderThemselve, isRead, messageContent } = useMessage(props)

const messageText = computed(() => {
  const regex = /(https?:\/\/[\w\-._~:/?#\[\]@!$&'()*+,;=%]+)/g
  const message = messageContent.value?.data || ''
  return message ? message.replace(regex, '<a class="chat-message-text-link" href="$1" target="_blank">$1</a>') : ''
})

// 右键菜单
const commands = reactive([
  { name: '复制', command: 'copy'},
  { name: '引用', command: 'quote'},
  { name: '撤回', command: 'revoke'},
  { name: '删除', command: 'deleteMessage'}
])

// 点击链接
function onClick(event) {
  const target = event.target
  if (target.tagName === 'A') {
	  // todo
  }
}
</script>

<style lang="scss">
.chat-message-text-link {
  color: #FF7A45;
  text-decoration: none;

  &:hover {
    text-decoration: underline;
  }
}
</style>
<style lang="scss" scoped>
.chat-message-text {
  flex: 1;
  color: #333;
  width: fit-content;
  user-select: text;
  padding: 20rpx 24rpx;
  word-break: break-all;
  transition: all 0.1s ease;
  border-radius: 0 10rpx 10rpx 10rpx;
  background-color: rgba(0, 0, 0, 0.05);

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