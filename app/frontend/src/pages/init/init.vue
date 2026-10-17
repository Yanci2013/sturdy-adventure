<template>
	<view>

	</view>
</template>

<script setup>
	import {
		onMounted
	} from 'vue';

	import router from '../../utils/router';
	import {
		test
	} from '../../uni_modules/uview-plus';


	onMounted(() => {
		if (test.isEmpty(uni.getStorageSync('type'))) {
			uni.setStorageSync('type', '用户')
			router.reLaunch({
				url: 'pages/index/index'
			})
		} else {
			if (uni.getStorageSync('type') == '用户') {
				router.reLaunch({
					url: 'pages/index/index'
				})
			} else {

				router.reLaunch({
					url: 'pages/playerMain/index'
				})
			}

		}
	})
</script>

<style>

</style>