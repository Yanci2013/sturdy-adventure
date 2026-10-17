<template>
	<view class="">
		<view class="mt-3 mx-3 bg-white rounded-lg p-3">
			<view class="text-main font font-weight-bolder">投诉类型</view>
			<view class="mt-3 flex align-center">
				<view class="flex align-center justify-center mr-2" :class="current==index?'select':'normal'"
					v-for="(item,index) in typeList" :key="index">
					{{item}}
				</view>
			</view>
			<view class="mt-3 flex align-center justify-between">
				<view class="font-28 text-main">订单编号</view>
				<view class="font-28 text-light9">{{dataInfo.order_no}}</view>
			</view>
		</view>

		<view class="mt-3 mx-3 bg-white rounded-lg p-3">
			<view class="text-main font font-weight-bolder">具体情况说明</view>
			<view class="mt-3  font-28">{{dataInfo.content}}</view>
			<view class="mt-3 flex align-center justify-between">
				<view class="text-main font-md font-weight-bolder">图片证明</view>
				<view class="text-main font-24 text-light9">直观的截图是非常有利的举报证据</view>
			</view>

			<view class="mt-2">
				<up-album :urls="dataInfo.image" singleSize="70"></up-album>
			</view>

		</view>

		<view class="mt-3 mx-3 bg-white rounded-lg p-3">
			<view class="text-main font font-weight-bolder">联系方式</view>
			<view class="mt-3 rounded-lg">
				<up-input v-model="dataInfo.phone" readonly="" placeholder="请填写您的手机号码，以便我们给您反馈" inputAlign="center"
					border="none" :customStyle="customStyle"></up-input>
			</view>

		</view>

		<view class="mt-3 mx-3 bg-white p-3">
			<view class="flex align-center justify-between rounded-lg">
				<view class="text-main font font-weight-bolder">售后状态</view>
				<view class="font-24 text-light9">{{getTitle()}}</view>
			</view>
			<view v-if="dataInfo.status=='2'" class="mt-3 font-24 text-light9">
				拒绝原因: {{dataInfo.remarks}}
			</view>
		</view>

		<view v-if="isBalanceRefunded" class="refund-result mx-3 mt-3">
			<view class="refund-result__header">
				<view class="refund-result__icon">
					<up-icon name="checkmark" size="18" color="#FFFFFF"></up-icon>
				</view>
				<view>
					<view class="refund-result__title">退款已退至余额</view>
					<view class="refund-result__tip">款项已到账，可在平台余额中查看和使用</view>
				</view>
			</view>
			<view class="refund-result__amount">
				<text>退款金额</text>
				<text class="refund-result__price">¥{{dataInfo.refund_amount}}</text>
			</view>
			<view class="refund-result__row">
				<text>退款方式</text>
				<text>退至平台余额</text>
			</view>
			<view class="refund-result__row">
				<text>到账状态</text>
				<text class="refund-result__success">已到账</text>
			</view>
			<view v-if="dataInfo.refund_time_text" class="refund-result__row">
				<text>到账时间</text>
				<text>{{dataInfo.refund_time_text}}</text>
			</view>
		</view>


		<view class="" style="height: 200rpx;"></view>
		<view v-if="dataInfo.status=='0'" class="fixed-bottom bg-white pb-5">
			<commonButton :title="getTitle()" margin="30rpx 22rpx 10rpx"></commonButton>
		</view>

	</view>
</template>

<script setup>
	import {
		onLoad
	} from '@dcloudio/uni-app';
	import {
		ref,
		computed
	} from 'vue';
	import {
		complaintMsg
	} from '../../../request/api';
	const id = ref('')
	const dataInfo = ref({})
	const typeList = ['订单投诉', '请求退款', '投诉打手']
	const current = ref(0)
	const isBalanceRefunded = computed(() => dataInfo.value.refund_method === 'balance')

	const customStyle = {
		height: '96rpx',
		background: '#F8FAFD'
	}

	onLoad((prop) => {
		id.value = prop.id
		getDetail()
	})

	const getDetail = () => {
		complaintMsg({
			id: id.value
		}).then(res => {
			dataInfo.value = res.data
			current.value = Number(res.data.type) - 1
		})
	}
	const getTitle = () => {
		const status = dataInfo.value.status
		if (dataInfo.value.type == 2) {
			if (status == 0) return '退款处理中'
			if (status == 1 && isBalanceRefunded.value) return '退款已退至余额'
			if (status == 1) return '退款审核成功'
			if (status == 2) return '退款申请未通过'
			return '退款申请已撤销'
		}
		return status == 0 ? '售后中' : status == 1 ? '审核成功' : status == 2 ? '审核失败' : '已撤销'
	}
</script>

<style>
	.select {
		width: 194rpx;
		height: 72rpx;
		background: linear-gradient(180deg, #FF9D6E 0%, #FF7A45 100%);
		border-radius: 12rpx 12rpx 12rpx 12rpx;
		border: 2rpx solid #FF9D6E;
		font-family: PingFang SC, PingFang SC;
		font-weight: 500;
		font-size: 26rpx;
		color: #333333;
	}

	.normal {
		width: 194rpx;
		height: 72rpx;
		background: #FFFFFF;
		border-radius: 12rpx 12rpx 12rpx 12rpx;
		border: 2rpx solid #EFF0F2;
		font-family: PingFang SC, PingFang SC;
		font-weight: 500;
		font-size: 26rpx;
		color: #666666;
	}

	.refund-result {
		padding: 30rpx;
		background: linear-gradient(145deg, #f2fffb 0%, #ffffff 58%, #eef9ff 100%);
		border: 2rpx solid rgba(29, 190, 139, 0.22);
		border-radius: 16rpx;
		box-shadow: 0 12rpx 30rpx rgba(22, 160, 123, 0.08);
	}

	.refund-result__header {
		display: flex;
		align-items: center;
		padding-bottom: 26rpx;
		border-bottom: 2rpx solid rgba(32, 170, 132, 0.1);
	}

	.refund-result__icon {
		width: 58rpx;
		height: 58rpx;
		margin-right: 20rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		border-radius: 50%;
		background: linear-gradient(135deg, #21c99a 0%, #10a9b7 100%);
		box-shadow: 0 8rpx 18rpx rgba(21, 181, 153, 0.22);
	}

	.refund-result__title {
		font-size: 30rpx;
		font-weight: 600;
		color: #183b35;
	}

	.refund-result__tip {
		margin-top: 8rpx;
		font-size: 23rpx;
		color: #78908b;
	}

	.refund-result__amount,
	.refund-result__row {
		display: flex;
		align-items: center;
		justify-content: space-between;
		color: #7b8785;
		font-size: 26rpx;
	}

	.refund-result__amount {
		padding: 26rpx 0 18rpx;
	}

	.refund-result__row {
		padding-top: 16rpx;
	}

	.refund-result__row text:last-child {
		color: #354541;
	}

	.refund-result__price {
		font-size: 38rpx;
		font-weight: 700;
		color: #15a77f;
	}

	.refund-result__success {
		color: #15a77f !important;
		font-weight: 600;
	}
</style>
