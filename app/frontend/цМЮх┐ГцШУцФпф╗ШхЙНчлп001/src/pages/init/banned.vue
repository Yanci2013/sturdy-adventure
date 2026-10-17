<template>
	<view class="page">
		<uv-empty mode="permission" :text="banText" iconSize="180" textSize="28">
			<view class="ban-reason-card" v-if="banReason">
				<text class="ban-reason-label">封禁原因</text>
				<text class="ban-reason-text">{{ banReason }}</text>
			</view>
		</uv-empty>
	</view>
</template>

<script setup>
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'

const banReason = ref('')
const banText = ref('账号已被封禁')

onLoad((option) => {
	banReason.value = option.reason ? decodeURIComponent(option.reason) : ''
})
</script>

<style scoped>
.page {
	display: flex;
	justify-content: center;
	align-items: center;
	height: 100vh;
	background: #f5f6f9;
}
.ban-reason-card {
	width: 500rpx;
	background: rgba(255,255,255,0.7);
	border-radius: 16rpx;
	padding: 24rpx 28rpx;
	margin-top: 30rpx;
	text-align: center;
}
.ban-reason-label {
	display: block;
	font-size: 24rpx;
	color: #999;
	margin-bottom: 12rpx;
}
.ban-reason-text {
	display: block;
	font-size: 28rpx;
	color: #ee0a24;
	line-height: 1.6;
}
</style>
