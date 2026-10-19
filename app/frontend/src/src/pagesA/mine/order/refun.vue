<template>
	<z-paging ref="paging" v-model="dataList" @query="queryList">
		<template #top>
			<common-navbar title="售后管理"></common-navbar>
			<view class="bg-white">
				<up-tabs :list="tabList" :current="current" :scrollable="false" @change="changeTab"></up-tabs>
			</view>

		</template>


		<view class="p-3 mt-2 bg-white rounded-lg mx-2" v-for="(item,index) in dataList" :key="index">
			<view class="flex align-center justify-between">
				<view class="font-30 text-main font-weight-bold">{{item.title}}</view>
				<view class="font-sm" style="color: #FF7A45;">{{getServiceStatus(item)}}</view>
			</view>
			<up-line margin="30rpx 0"></up-line>
			<goodsItem :item="item"></goodsItem>
			<up-line margin="30rpx 0"></up-line>
			<view class="flex align-center justify-between mt-2">
				<view class="font-sm text-main">游戏ID</view>
				<view class="font-30 text-light6">{{item.game_id}}</view>
			</view>

			<view class="flex align-center justify-between mt-2">
				<view class="font-sm text-main">游戏区服</view>
				<view class="font-30 text-light6">{{item.game_qf}}</view>
			</view>
			<view class="flex align-center justify-between mt-2">
				<view class="font-sm text-main">订单备注</view>
				<view class="font-30 text-light6">{{item.remarks||'无'}}</view>
			</view>
			<view class="flex align-center justify-between mt-2">
				<view class="font-sm text-main">实付金额</view>
				<view class="font-30 text-lightRed">￥{{item.pay_price}}</view>
			</view>
			<up-line margin="30rpx 0"></up-line>


			<orderButton :item="item" orderType="refun" @refresh="refresh"></orderButton>



		</view>
	</z-paging>


</template>

<script setup>
	import {
		ref
	} from 'vue';
	import {
		orderService
	} from '../../../request/api';
	import commonNavbar from '../../../compontents/common-navbar.vue';

	const paging = ref(null)
	const dataList = ref([])

	const type = ref('0')
	const tabList = [{
		name: '售后中',
		id: '0'
	}, {
		name: '已结束',
		id: '1'
	}]
	const current = ref(0)

	const queryList = (pageNo, pageSize) => {

		orderService({
			status: type.value,
			page: pageNo,
			limit: pageSize
		}).then(res => {
			paging.value.complete(res.data.data);
		})

	}

	const getServiceStatus = (item) => {
		if (item.type == 2) {
			if (item.status == 0) return '退款处理中'
			if (item.status == 1 && item.refund_method == 'balance') return '退款已退至余额'
			if (item.status == 1) return '退款已完成'
			if (item.status == 2) return '退款未通过'
			return '退款已撤销'
		}
		if (item.status == 0) return '售后中'
		if (item.status == 1) return '审核成功'
		if (item.status == 2) return '审核失败'
		return '已撤销'
	}

	// const getStatus = (item) => {
	// 	let cell = tabList.find(v => v.id == item.status)
	// 	return cell.name
	// }

	const refresh = () => {
		paging.value.reload()
	}
	const changeTab = (e) => {
		current.value = e.index
		type.value = tabList[current.value].id
		paging.value.reload()
	}
</script>

<style>
	page {
		background: #F2F3F7;
	}
</style>
