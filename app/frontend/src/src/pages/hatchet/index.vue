<template>
	<view>
		<commonTabbar :current="2" :placeholder="false"></commonTabbar>
		<up-tabs :list="tabList" :current="current" :scrollable="false" @change="changeTab">
		</up-tabs>
		<view class="p-2">
			<view class="flex align-center justify-between">
				<view class="font-28 text-light6">区服:手机端</view>
				<view class="font-28 text-light6">预计服务费 :￥99</view>
			</view>
			<up-line margin="10rpx"></up-line>
			<view class="flex align-start">
				<u-image src="/static/logo.png" mode="" width="168rpx" height="168rpx"></u-image>
				<view class="ml-1 flex align-start flex-1">
					<view class="font-30 text-main">四个大红</view>
					<view class="ml-auto mt-3">
						<up-text text="99" mode="price"></up-text>
					</view>
				</view>
			</view>
			<up-line margin="10rpx"></up-line>
			<view class="font-30 text-main">订单编号:2015456544545423454</view>
			<view class="font-30 text-main mt-2">游戏ID:接单后可查看</view>
			<view class="font-30 text-main mt-2">游戏区服:手机端</view>
			<view class="font-30 text-main mt-2">订单备注:备注</view>
			<view class="flex align-center justify-end mt-2">
				<view class="font-30 text-main">共2件商品 合计:<text class="font-lg">￥99</text></view>
			</view>
			<view class="flex align-center justify-end mt-2">

				<view class="jie" @click="throttle(jieClick,2000)">立即接单</view>
			</view>
		</view>

		<view style="height: 150rpx;"></view>
	</view>
</template>

<script setup>
	import {
		ref
	} from 'vue';
	import {
		throttle
	} from '../../uni_modules/uview-plus';



	const tabList = ref([{
		name: '全部'
	}, {
		name: '手机端'
	}, {
		name: '电脑端'
	}])
	const current = ref(0)

	const changeTab = (e) => {
		current.value = e.index
	}

	const jieClick = () => {

	}
</script>

<style lang="scss">
	page {
		min-height: 100vh;
		background: linear-gradient(180deg, #FFF5EF 0%, #FFB38A 50%, #ffeef8 100%);
	}
	.p-2 {
		background: rgba(255,255,255,0.45);
		backdrop-filter: blur(24rpx);
		-webkit-backdrop-filter: blur(24rpx);
		border: 1rpx solid rgba(255,255,255,0.6);
		box-shadow: 0 2rpx 20rpx rgba(0,0,0,0.04), inset 0 1rpx rgba(255,255,255,0.8);
		border-radius: 24rpx;
		margin: 24rpx;
		padding: 24rpx;
	}
	.jie {
		display: flex;
		align-items: center;
		justify-content: center;
		padding: 10rpx 30rpx;
		border-radius: 50rpx;
		background: linear-gradient(135deg, #FFB38A 0%, #FFB38A 100%);
		font-weight: 500;
		font-size: 30rpx;
		color: #fff;
		border: none;
	}
</style>