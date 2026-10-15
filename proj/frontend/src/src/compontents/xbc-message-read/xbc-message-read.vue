<template>
  <text v-if="show" class="chat-message-read">{{ isRead ? '已读' : '未读' }}</text>
</template>

<script setup>
const props = defineProps({
  show: { type: Boolean, default: false },
  isRead: { type: [Boolean, Number], default: false },
})
</script>

<style lang="scss" scoped>
.chat-message-read {
  display: inline-block;
  width: 56rpx;
  color: #666;
  font-size: 26rpx;
  margin: 0 20rpx;
  align-self: flex-end;
  font-family: 'Franklin Gothic Medium', 'Arial Narrow', Arial, sans-serif;
}
</style>