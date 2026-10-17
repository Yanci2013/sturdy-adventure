<template>
	<view class="xbc-time-wrap flex-column">
		<view class="xbc-time">{{ time }}</view>
	</view>
</template>

<script setup>
const props = defineProps({
	time: { type: String, default: '' }
})
</script>

<style lang="scss" scoped>
@import '@/uni.scss';
.xbc-time-wrap {
  text-align: center;

  .xbc-time {
    color: #777;
    font-size: 26rpx;
    padding: 0 16rpx;
    margin-top: 20rpx;
    border-radius: 10rpx;
    display: inline-block;
    background-color: #eee;
    @include line-height(48rpx);
  }
}
</style>