<template>
  <view class="system-message flex-column">
    {{ content }}
  </view>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  message: { type: Object, required: true }
})
const content = computed(() => {
  return JSON.parse(props?.message?.content || '{}')?.data || ''
})
</script>

<style lang="scss" scoped>
.system-message {
  color: #999;
  font-size: 28rpx;
  padding: 20rpx 20%;
  text-align: center;
}
</style>