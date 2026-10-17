<template>
	<u-popup mode="center" :show="show" closeable @close="closeClick" bgColor="transparent">
		<view class="popouBg" :style="{height:height}">
			<view class="" style="margin-top: -150rpx;">
				<u-image :src="imageSrc" mode="" :width="imageWith" :height="imageHeight"></u-image>
			</view>
			<view class="mt-5 font-lg font-weight-bolder text-main">{{title}}</view>
			<view class="mt-4 text-light6 font-28">{{subtitle}}</view>
			<slot></slot>
			<view class="flex align-center" style="margin-top: 100rpx;">
				<view class="leftItem" @click="leftBtnClick">{{leftBtnText}}</view>
				<view class="rightItem" @click="rightBtnClick">{{rightBtnText}}</view>
			</view>

		</view>
	</u-popup>
</template>

<script setup>
	const props = defineProps({
		show: Boolean,
		imageSrc: String,
		imageWith: [Number, String],
		imageHeight: [Number, String],
		title: String,
		subtitle: String,
		leftBtnText: String,
		rightBtnText: String,
		height: [Number, String],
		width: [Number, String]
	})

	const emit = defineEmits(['left', 'right', 'close'])

	const leftBtnClick = () => {
		emit('left')
	}
	const rightBtnClick = () => {
		emit('right')
	}
	const closeClick = () => {
		emit('close')
	}
</script>

<style>
	.popouBg {
		background: linear-gradient(110deg, #FFF5EF 0%, #F5F7FA 100%);
		border-radius: 30rpx;
		padding: 30rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		flex-direction: column;

	}

	.leftItem {
		width: 264rpx;
		height: 80rpx;
		border-radius: 40rpx 40rpx 40rpx 40rpx;
		border: 2rpx solid rgba(130, 105, 255, 1);
		font-family: PingFang SC, PingFang SC;
		font-weight: 500;
		font-size: 30rpx;
		color: #FF7A45;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.rightItem {
		width: 264rpx;
		height: 80rpx;
		background: linear-gradient(180deg, #FF7A45 0%, #FFB38A 100%);
		border-radius: 40rpx 40rpx 40rpx 40rpx;
		font-family: PingFang SC, PingFang SC;
		font-weight: 500;
		font-size: 30rpx;
		color: #FFFFFF;
		display: flex;
		align-items: center;
		justify-content: center;
		margin-left: 22rpx;
	}
</style>