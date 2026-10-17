<template>
	<view v-if="count > 0" class="badge">{{ value }}</view>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  count: { type: Number, default: 0 }
})
const value = computed(() => {
  return props.count > 99 ? '99+' : props.count
})
</script>

<style lang="scss" scoped>
@import '@/uni.scss';
.badge{
  color: #fff;
  padding: 0 12rpx;
  font-size: 24rpx;
  border-radius: 40rpx;
  @include line-height(32rpx);
  background-color: #ff5722;
  position: absolute;
  left: 100rpx;
  top: 6rpx;
}
</style>