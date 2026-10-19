<template>
	<z-paging ref="paging" v-model="dataList" @query="queryList">
		<template #top>
			<common-navbar title="佣金提现"></common-navbar>
		</template>
		<template #loadingMoreNoMore>
			<view></view>
		</template>
		<template #loadingMoreDefault>
			<view></view>
		</template>

		<view class="position-relative">
			<u-image src="/static/images/bg.png" mode="" width="750rpx" height="300rpx"></u-image>
			<view class="position-absolute left-0 right-0 bottom-0" style="top: 10rpx;">
				<view class="mt-4 position-relative mx-3">
					<u-image src="/static/mine/bg.png" mode="" width="690rpx" height="289rpx"></u-image>
					<view class="position-absolute top-0 left-0 right-0 bottom-0 p-4">
						<view class="font-sm text-white">累计金额</view>
						<view class="mt-2" style="font-size: 48rpx;color: #FFE312;">{{dataInfo.lj_money}}</view>

						<view class="flex align-center mt-3">
							<view class="span24-8 flex   justify-center flex-column"
								@click="gotoPage('pages/playerMain/ti')">
								<view class="font-24 text-white">可提现金额</view>
								<view class="font text-white mt-1">{{ktx }}</view>
							</view>
							<view class="span24-8 flex  justify-center  flex-column ml-5"
								@click="gotoPage('pages/playerMain/bound')">
								<view class="font-24 text-white">保证金</view>
								<view class="font text-white mt-1">{{dataInfo.bond}}</view>
							</view>
							<view class="span24-8 flex  justify-center flex-column ml-5">
								<view class="font-24 text-white">冻结金额</view>
								<view class="font text-white mt-1">{{dataInfo.freeze}}</view>
							</view>
						</view>
					</view>
				</view>
				<view class=" bg-white mt-4">
					<up-tabs :list="tabList" :current="current"
						lineColor="linear-gradient( 90deg, #FF7A45 0%, #FFB38A 100%)" @change="changeTab"></up-tabs>
				</view>
				<view class="flex align-center justify-between bg-white bg-white p-3" v-for="(item,index) in dataList"
					:key="index">
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
		moneyList,
		withdrawalList,
		thugRewardList,
		thugMsg
	} from '../../request/api';
	import commonNavbar from '../../compontents/common-navbar.vue';
	import router from '../../utils/router';
	import {
		onShow
	} from '@dcloudio/uni-app';

	const tabList = [{
		name: '佣金记录'
	}, {
		name: '提现记录'
	}, {
		name: '打赏记录'
	}]
	const current = ref(0)
	const paging = ref(null)
	const dataList = ref([])
	const dataInfo = ref({})
	const ktx = ref(0)

	onShow(() => {
		thugMsg({
			thug_id: uni.getStorageSync('id')
		}).then(res => {
			dataInfo.value = res.data
			ktx.value = Number(res.data.money - res.data.freeze).toFixed(2)

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
				type: '2'
			}).then(res => {
				paging.value.complete(res.data.data);
			})
		}
		if (current.value == 2) {
			thugRewardList({
				page: pageNo,
				limint: pageSize,
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
</style>