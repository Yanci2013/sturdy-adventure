<template>
	<z-paging ref="paging" v-model="dataList" @query="queryList">
		<template #top>
			<common-navbar title="分销中心"></common-navbar>
		</template>
		<template #loadingMoreNoMore>
			<view></view>
		</template>
		<view class="position-relative">
			<u-image src="/static/images/bg.png" mode="" width="750rpx" height="300rpx"></u-image>
			<view class="position-absolute left-0 right-0 bottom-0" style="top: 10rpx;">
				<view class="mt-4 position-relative mx-3">
					<u-image src="/static/mine/fen.png" width="690rpx" height="189rpx"></u-image>
					<view class="position-absolute top-0 left-0 right-0 bottom-0 p-4">
						<view class="flex align-center">
							<view class="w-50" @click="gotoPage('pages/playerMain/ti')">
								<view class="font-sm" style="color: #B57148;">累计佣金</view>
								<view class="mt-2" style="font-size: 48rpx;color: #743518;">{{dataInfo.lj_money}}</view>
							</view>
							<view class="" style="margin-left: 100rpx;">
								<view class="font-sm" style="color: #B57148;">下线总数</view>
								<view class="mt-2" style="font-size: 48rpx;color: #743518;">{{dataInfo.share_count}}</view>
							</view>
						</view>
					</view>
				</view>
				<view class="mt-4 mx-4">
					<view class="font-28 text-main font-weight-bold">分销说明</view>
					<view class="mt-2 font-sm text-light6">
						{{shareName}}
					</view>
				</view>
				<view class=" bg-white mt-4 px-2 pb-3 flex align-center">
					<up-tabs :list="tabList" :current="current" lineColor="linear-gradient( 90deg, #FF7A45 0%, #FFB38A 100%)"
						@change="changeTab"></up-tabs>
					<view class="ml-auto tui flex align-center justify-center" @click="gotoPage('pagesA/mine/tui')">推广海报</view>
				</view>
				<view class="bg-white px-4 py-2" v-for="(item,index) in dataList" :key="index">
					<template v-if="current == 0 || current == 1">
						<view class="flex align-center justify-between">
							<view class="">
								<up-text :lines="1" :text="item.memo?item.memo:'佣金提现'"></up-text>
								<view class="mt-2 font-24" style="color: #7F8996;">{{item.createtime}}</view>
							</view>
							<view class="">
								<view class="font text-main font-weight-bolder">{{item.money}}</view>
								<view v-if="current==0" class="mt-2 font-24" style="color: #7F8996;">已入账</view>
								<view v-if="current==1" class="mt-2 font-24" style="color: #7F8996;">
									{{item.status==0?'审核中':item.status==1?'审核成功':'审核失败'}}
								</view>
							</view>
						</view>
					</template>
					<template v-else>
						<view class="flex align-center">
							<up-avatar :src="item.avatar" size="72rpx"></up-avatar>
							<view class="font text-main ml-2">{{item.nickname}}</view>
						</view>
					</template>

				</view>

			</view>
		</view>

	</z-paging>

</template>

<script setup>
	import {
		onMounted,
		ref
	} from 'vue';
	import {
		onShow
	} from '@dcloudio/uni-app';
	import {
		moneyList,
		getUserInfo,
		withdrawalList,
		userShare,
		configure
	} from '../../request/api';
	import router from '../../utils/router';
	import commonNavbar from '../../compontents/common-navbar.vue';
	import {
		loginUrl
	} from '../../request/env';

	const tabList = [{
		name: '佣金记录'
	}, {
		name: '提现记录'
	}, {
		name: '我的团队'
	}]
	const current = ref(0)
	const paging = ref(null)
	const dataList = ref([])
	const dataInfo = ref({})
	const shareName = ref('')

	onShow(() => {
		getUserInfo().then(res => {
			dataInfo.value = res.data
		})
	})
	onMounted(() => {
		configure().then(res => {
			shareName.value = res.data.share_name
		})
	})

	const queryList = (pageNo, pageSize) => {


		if (current.value == 0) {
			moneyList({
				page: pageNo,
				limint: pageSize
			}).then(res => {
				paging.value.complete(res.data.data);
			})
		}
		if (current.value == 1) {
			withdrawalList({
				page: pageNo,
				limint: pageSize,
				type: '1'
			}).then(res => {
				paging.value.complete(res.data.data);
			})
		}

		if (current.value == 2) {
			userShare({
				page: pageNo,
				limint: pageSize
			}).then(res => {
				paging.value.complete(res.data.data);
			})
		}





	}

	const changeTab = (e) => {
		current.value = e.index
		paging.value.reload()
	}
	const gotoPage = (url) => {

		router.navigateTo({
			url: url
		})

	}
</script>

<style>
	.tui {
		width: 180rpx;
		height: 64rpx;
		background: linear-gradient(270deg, #FF7A45 0%, #FF9D6E 100%);
		border-radius: 50rpx 50rpx 50rpx 50rpx;
		font-family: PingFang SC, PingFang SC;
		font-weight: 500;
		font-size: 30rpx;
		color: #FFFFFF;
	}
</style>