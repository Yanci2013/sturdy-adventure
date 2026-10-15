<template>
	<view class="p-3 bg-white">
		<view class="text-main font-md font-weight-bold ">{{dataInfo.title}}</view>
		<view class="mt-3 font-sm text-light9">{{dataInfo.createtime}}</view>
		<view class="mt-3 font-sm text-light9">
			<rich-text :nodes="dataInfo.content"></rich-text>
		</view>
		<view class="mt-3">
			<up-image :src="dataInfo.image" mode="" width="690rpx" height="360rpx"></up-image>
		</view>
	</view>
</template>

<script setup>
	import {
		onLoad
	} from '@dcloudio/uni-app';
	import {
		noticeMsg
	} from '../../../request/api';
	import {
		ref
	} from 'vue';
	const dataInfo = ref({})
	onLoad((prop) => {
		noticeMsg({
			id: prop.id
		}).then(res => {
			dataInfo.value = res.data
		})
	})

	const ss = ref()
</script>

<style>
</style>