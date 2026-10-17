<template>
	<view class="py-2 pl-2">
		<template v-if="item.status===0 && orderType=='order'">
			<view class="flex align-center justify-end">
				<view class="flex align-center justify-center cancle" @click.stop="gotoPage">订单详情</view>
				<view class="flex align-center justify-center cancle ml-4" @click.stop="cancelClick">取消订单</view>
				<view class="flex align-center justify-center pay ml-4" @click.stop="payClick">立即支付</view>
			</view>
		</template>
		<template v-if="item.status===0 && orderType=='refun'">
			<view class="flex align-center justify-end">
				<view class="flex align-center justify-center cancle" @click.stop="gotoPage">订单详情</view>
			</view>
		</template>

		<template v-if="item.status===1 && type =='用户'">
			<view class="flex align-center justify-end">
				<view class="flex align-center justify-center cancle" @click.stop="gotoPage">订单详情</view>
				<view v-if="item.complaint==0" class="flex align-center justify-center cancle ml-4" @click.stop="touClick">我要售后
				</view>
			</view>
		</template>
		<template v-if="item.status===1 && type =='打手'">
			<view class="flex align-center justify-end">
				<view class="flex align-center justify-center pay" @click.stop="connectClick">确认接单</view>
			</view>
		</template>

		<template v-if="item.status===2 && type =='用户'">
			<view class="flex align-center justify-end">
				<view class="flex align-center justify-center cancle" @click.stop="gotoPage">订单详情</view>
				<view v-if="item.complaint==0" class="flex align-center justify-center cancle ml-4" @click.stop="touClick">我要售后
				</view>
			</view>
		</template>
		<template v-if="item.status===2 && type =='打手'">
			<view class="flex align-center justify-end">
				<view class="flex align-center justify-center cancle" @click.stop="gotoPage">订单详情</view>
				<view class="flex align-center justify-center pay ml-4" @click.stop="cancelClick">取消接单</view>
				<view class="flex align-center justify-center pay ml-4" @click.stop="beginClick">开始服务</view>
			</view>
		</template>

		<template v-if="item.status===3 && type =='用户'">
			<view class="flex align-center justify-end">
				<view class="flex align-center justify-center cancle" @click.stop="gotoPage">订单详情</view>
				<view v-if="item.complaint==0" class="flex align-center justify-center cancle ml-4" @click.stop="touClick">我要售后
				</view>
			</view>
		</template>
		<template v-if="item.status===3 && type =='打手'">
			<view class="flex align-center justify-end">
				<view class="flex align-center justify-center cancle" @click.stop="gotoPage">订单详情</view>
				<view class="flex align-center justify-center pay ml-4" @click.stop="finishClick">确认结单</view>
			</view>
		</template>

		<template v-if="item.status===4 && type =='用户'">
			<view class="flex align-center justify-end">
				<view class="flex align-center justify-center cancle" @click.stop="gotoPage">订单详情</view>
				<view v-if="item.complaint==0" class="flex align-center justify-center cancle ml-4" @click.stop="touClick">我要售后
				</view>
				<view class="flex align-center justify-center pay ml-4" @click.stop="finishClick">确认结单</view>
			</view>
		</template>
		<template v-if="item.status===4 && type =='打手'">
			<view class="flex align-center justify-end">
				<view class="flex align-center justify-center cancle" @click.stop="gotoPage">订单详情</view>
			</view>
		</template>
		<template v-if="item.status===5">
			<view class="flex align-center justify-end">
				<view v-if="type =='用户'&&item.complaint==0" class="flex align-center justify-center cancle"
					@click.stop="touClick">我要售后</view>
				<view class="flex align-center justify-center cancle ml-4" @click.stop="gotoPage">订单详情</view>
			</view>
		</template>
		<template v-if="item.status===6">
			<view class="flex align-center justify-end">
				<view class="flex align-center justify-center cancle" @click.stop="gotoPage">订单详情</view>
			</view>
		</template>
		<template v-if="item.status===7">
			<view class="flex align-center justify-end">
				<view class="flex align-center justify-center cancle" @click.stop="gotoPage">订单详情</view>
			</view>
		</template>
		<template v-if="item.status===8">
			<view class="flex align-center justify-end">
				<view class="flex align-center justify-center cancle" @click.stop="gotoPage">订单详情</view>
			</view>
		</template>


		<!-- 打手确认结单 -->
		<up-popup :show="show" mode="center" closeable @close="closeimageList" round="20rpx">
			<view class="bg-white p-2 rounded-lg" style="width: 600rpx;">
				<view class="flex align-center justify-center text-main font font-weight-bold">上传凭证</view>
				<view class="mt-2 text-main font mx-2 font-weight-bold">凭证照片</view>
				<view class="mt-2 flex align-center justify-center">
					<commonUpload ref="uploadcom" :maxCount="6" @upload-success="handleUploadSuccess" @delete-image="handleDele">
					</commonUpload>
				</view>
			</view>
			<view class="mt-2 text-main font mx-2 font-weight-bold">订单备注</view>
			<view class="mt-2 mx-2 rounded-lg">
				<up-textarea v-model="thugRemarks" placeholder="订单备注" border="none" style="background: #f5f5f5;"></up-textarea>
			</view>
			<view class="my-2 flex align-center justify-center">
				<view class="flex align-center justify-center upload" @click="sureFinish('1')">确认上传并结单</view>
			</view>
		</up-popup>
		<!-- 用户确认结单评价 -->
		<up-popup :show="usershow" mode="center" closeable @close="usershow=false" round="20rpx">
			<view class="bg-white p-2 rounded-lg" style="width: 650rpx;">
				<view class="flex align-center justify-center text-main font font-weight-bold">打手评价</view>
				<view class="mt-5 flex align-center justify-center">
					<up-rate v-model="rate" size="24"></up-rate>
				</view>
				<view class="mt-5 flex align-center justify-center">
					<view class="flex align-center justify-center cancle" @click="sureFinish('2')">暂不评价</view>
					<view class="flex align-center justify-center pay ml-4" @click="sureFinish('3')">确认评价</view>
				</view>
			</view>
		</up-popup>
		<!-- 选择打手 -->
		<up-popup :show="playershow" mode="center" closeable @close="playershow=false" round="20rpx">
			<view class="rounded-lg bg-white p-2" style="height: 650rpx;width: 600rpx;">
				<view class="flex align-center justify-center font-30 text-main font-weight-bolder">选择打手</view>
				<scroll-view scroll-y="true" style="height: 500rpx;">
					<view class="mt-2 flex align-center" v-for="(item,index) in thugAppointList" :key="index"
						@click="changeTuag(item,index)">
						<u-image :src="item.check?'/static/images/select.png':'/static/images/normal.png' " width="48rpx"
							height="48rpx"></u-image>
						<view class="ml-2">
							<u-image :src="item.avatar" width="90rpx" height="90rpx" radius="20rpx"></u-image>
						</view>
						<view class="ml-2 text-main font-28">{{item.name}}</view>
					</view>
				</scroll-view>

				<commonButton title="确定" margin="30rpx 22rpx 10rpx" @click="choosePlayer"></commonButton>

			</view>
		</up-popup>

	</view>
</template>

<script setup>
	import {
		ref
	} from 'vue';
	import {
		orderCancel,
		orderPay,
		orderConnect,
		orderStart,
		orderFinish,
		orderConfirm,
		thugorderCancel,
		thugthugAppoint,
		configure,
		thugRealnameInfo
	} from '../request/api';
	import router from '../utils/router';
	import {
		base_url
	} from '../request/env';
	import {
		test
	} from '../uni_modules/uview-plus';


	const imageList = ref([])
	const show = ref(false)
	const usershow = ref(false)
	const playershow = ref(false)
	const rate = ref(0)
	const thugAppointList = ref([])
	const selectList = ref([])
	const thugRemarks = ref('')
	const uploadcom = ref(null)

	const props = defineProps({
		item: Object,
		orderType: {
			type: String,
			default: 'order'
		}
	})

	const type = uni.getStorageSync('type')
	const emit = defineEmits(['refresh'])


	const changeTuag = (item, index) => {
		let selctIndex = selectList.value.findIndex(v => v == item.id)
		if (selctIndex > -1) {
			item.check = false
			selectList.value.splice(selctIndex, 1)
			return
		}
		item.check = true
		selectList.value.push(item.id)
	}

	const choosePlayer = () => {
		if (test.isEmpty(selectList.value)) {
			return uni.showToast({
				title: '请选择打手',
				icon: 'none'
			});
		}
		playershow.value = false
		orderStart({
			id: props.item.id,
			helper: selectList.value.join(',')
		}).then(res => {
			emit('refresh')
		})

	}
	const payClick = () => {
		router.navigateTo({
			url: 'pagesA/index/desk',
			data: { id: props.item.id, price: props.item.pay_price, award: '' }
		})
	}

	const cancelClick = () => {

		uni.showModal({
			title: '提示',
			content: '是否要取消该订单',
			showCancel: true,
			success: res => {
				if (res.confirm) {

					if (type == '用户') {
						orderCancel({
							id: props.item.id
						}).then(result => {
							emit('refresh')
						})
					} else {
						thugorderCancel({
							id: props.item.id
						}).then(result => {
							emit('refresh')
						})
					}


				}
			}

		});

	}

	const connectClick = async () => {
		// 打手实名认证校验
		if (type === '打手') {
			try {
				const cfgRes = await configure();
				const required = cfgRes.data && cfgRes.data.thug_realname_required === 1;
				if (required) {
					const rnRes = await thugRealnameInfo();
					const status = rnRes.data && rnRes.data.real_name_status;
					if (status !== 2) {
						const tip = status === 1 ? '实名认证审核中，请耐心等待' : status === 3 ? '实名认证未通过，请重新提交' : '请先完成实名认证';
						uni.showModal({
							title: '提示',
							content: tip,
							confirmText: '去认证',
							success: (r) => {
								if (r.confirm) {
									uni.navigateTo({ url: '/pages/playerMain/realname' });
								}
							}
						});
						return;
					}
				}
			} catch (e) {}
		}
		orderConnect({
			id: props.item.id
		}).then(res => {
			if (res.code == 1) {
				uni.showToast({
					title: '接单成功',
					icon: 'none'
				});
				setTimeout(() => {
					emit('refresh')
				}, 1500)
			} else {
				uni.showToast({
					title: res.msg,
					icon: 'none'
				});
			}


		})
	}

	const touClick = () => {
		router.navigateTo({
			url: 'pagesA/mine/order/tou',
			data: {
				id: props.item.id,
				no: props.item.order_no,
				status: props.item.status
			}
		})
	}

	const handleUploadSuccess = (res) => {
		imageList.value = res
	}

	const handleDele = (res) => {
		// imageList.value = res
	}

	const beginClick = () => {
		uni.showModal({
			title: '提示',
			content: '是否和其他打手一起完成该订单',
			showCancel: true,
			cancelText: '不需要',
			confirmText: '选择打手',
			success: res => {
				if (res.cancel) {
					orderStart({
						id: props.item.id,
						helper: ''
					}).then(res => {
						emit('refresh')
					})
				}
				if (res.confirm) {
					thugthugAppoint().then(res => {
						thugAppointList.value = res.data.data
					})
					playershow.value = true
				}
			}

		});
	}
	const closeimageList = () => {
		imageList.value = []
		uploadcom.value.removePic()
		show.value = false
	}
	const finishClick = () => {
		if (type == '用户') {
			usershow.value = true
		} else {
			imageList.value = []
			show.value = true
		}
	}

	const sureFinish = (status) => {
		if (status == 1) {
			if (test.isEmpty(imageList.value)) {
				return uni.showToast({
					title: '请上	传完成凭证',
					icon: 'none'
				});
			}

			orderFinish({
				id: props.item.id,
				image: imageList.value.join(','),
				thug_remarks: thugRemarks.value
			}).then(res => {
				if (res.code == 1) {
					uni.showToast({
						title: res.msg,
						icon: "none"
					})
					uploadcom.value.removePic()
					setTimeout(() => {
						show.value = false
					}, 10)

					emit('refresh')
				} else {
					uni.showToast({
						title: res.msg,
						icon: "none"
					})
				}

			})
		} else {
			if (status == '3' && rate.value == 0) {
				return uni.showToast({
					title: '请选择评分',
					icon: 'none'
				});
			}
			const grade = status == '2' ? '0' : rate.value
			orderConfirm({
				id: props.item.id,
				grade: grade
			}).then(res => {
				emit('refresh')
			})
		}



	}


	const gotoPage = () => {

		router.navigateTo({
			url: 'pagesA/mine/order/detail',
			data: {
				id: props.item.id
			}
		})
	}
</script>

<style>
	.cancle {
		width: 156rpx;
		height: 56rpx;
		border-radius: 202rpx 202rpx 202rpx 202rpx;
		opacity: 1;
		border: 2rpx solid #CCCCCC;
		font-size: 24rpx;
		font-family: Source Han Sans CN-Regular, Source Han Sans CN;
		font-weight: 400;
		color: #333333;
	}

	.pay {
		width: 156rpx;
		height: 56rpx;
		background: linear-gradient(270deg, #FF7A45 0%, #FF9D6E 100%);
		border-radius: 202rpx 202rpx 202rpx 202rpx;
		opacity: 1;
		font-size: 24rpx;
		font-family: Source Han Sans CN-Regular, Source Han Sans CN;
		font-weight: 400;
		color: #FFFFFF;
	}

	.after {
		width: 220rpx;
		height: 56rpx;
		border-radius: 37rpx 37rpx 37rpx 37rpx;
		opacity: 1;
		border: 2rpx solid #04BF79;
		font-size: 30rpx;
		font-family: PingFang SC-Regular, PingFang SC;
		font-weight: 400;
		color: #04BF79;
	}

	.upload {
		width: 500rpx;
		height: 56rpx;
		background: linear-gradient(270deg, #FF7A45 0%, #FF9D6E 100%);
		border-radius: 202rpx 202rpx 202rpx 202rpx;
		opacity: 1;
		font-size: 24rpx;
		font-family: Source Han Sans CN-Regular, Source Han Sans CN;
		font-weight: 400;
		color: #FFFFFF;
	}
</style>
