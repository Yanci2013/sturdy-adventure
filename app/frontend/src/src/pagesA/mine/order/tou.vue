<template>
	<view class="page">
		<!-- 顶部背景图 -->
		<image class="top-bg" src="/static/images/header_background_panda.png" mode="aspectFill"></image>

		<!-- 导航栏 -->
		<view class="navbar">
			<view class="navbar-capsule">
				<view class="navbar-btn" @click="goBack">
					<up-icon name="arrow-left" size="34rpx" color="#606266"></up-icon>
				</view>
				<view class="navbar-divider"></view>
				<view class="navbar-btn" @click="goHome">
					<up-icon name="home" size="36rpx" color="#606266"></up-icon>
				</view>
			</view>
			<text class="navbar-title">订单投诉</text>
			<view class="navbar-placeholder"></view>
		</view>

		<!-- 内容区 -->
		<scroll-view scroll-y class="content">
			<view class="content-inner">
				<view class="cards-wrap">
					<!-- 商品信息卡片 -->
					<view class="card product-card" v-if="orderInfo.id">
						<text class="card-title">商品信息</text>
						<view class="product-row">
							<image class="product-thumb" :src="orderInfo.logo || '/static/images/icon.png'" mode="aspectFill"></image>
							<view class="product-info">
								<text class="product-name">{{ orderInfo.title }}</text>
								<view class="product-price">
									<view class="card-actual">
										<text class="price-symbol">¥</text>
										<text class="price-num">{{ orderInfo.price }}</text>
									</view>
									<text class="price-qty">x{{ orderInfo.number || 1 }}</text>
								</view>
								<text class="product-desc">{{ orderInfo.synopsis || '' }}</text>
							</view>
						</view>

						<!-- 订单信息 -->
						<text class="delivery-section-title">订单信息</text>
						<view class="delivery-list">
							<view class="delivery">
								<text class="label">订单编号</text>
								<text class="value">{{ orderInfo.order_no }}</text>
							</view>
							<view class="delivery">
								<text class="label">游戏ID</text>
								<text class="value">{{ orderInfo.game_id || '-' }}</text>
							</view>
							<view class="delivery">
								<text class="label">游戏区服</text>
								<text class="value">{{ orderInfo.game_qf || '-' }}</text>
							</view>
							<view class="delivery">
								<text class="label">下单备注</text>
								<text class="value remark">{{ orderInfo.remarks || '无' }}</text>
							</view>
							<view class="delivery">
								<text class="label">下单时间</text>
								<text class="value">{{ orderInfo.createtime || '-' }}</text>
							</view>
							<view class="delivery" v-if="orderInfo.paytime">
								<text class="label">付款时间</text>
								<text class="value">{{ orderInfo.paytime }}</text>
							</view>
							<view class="delivery" v-if="orderInfo.connecttime">
								<text class="label">接单时间</text>
								<text class="value">{{ orderInfo.connecttime }}</text>
							</view>
							<view class="delivery" v-if="orderInfo.completetime">
								<text class="label">完成时间</text>
								<text class="value">{{ orderInfo.completetime }}</text>
							</view>
						</view>

						<!-- 实付款 -->
						<view class="pay-section">
							<text class="pay-name">实付款</text>
							<text class="pay-price">￥{{ orderInfo.pay_price }}</text>
						</view>
					</view>

					<!-- 投诉表单卡片 -->
					<view class="card complaint-form-card">
						<text class="card-title">投诉信息填写</text>

						<!-- 投诉类型 -->
						<view class="complaint-form-section">
							<text class="complaint-form-label">投诉类型</text>
							<view class="complaint-type-tags">
								<view v-for="(item, index) in typeList" :key="index"
									class="complaint-type-tag" :class="{ active: current === index }"
									@click="changeType(item, index)">
									<text>{{ item.name }}</text>
								</view>
							</view>
						</view>

						<!-- 联系方式 -->
						<view class="complaint-form-section" v-if="current !== 1">
							<text class="complaint-form-label">联系方式</text>
							<input class="complaint-form-input" v-model="phone"
								placeholder="请输入联系手机号" placeholder-class="complaint-form-placeholder"
								type="number" maxlength="11" />
							<text class="complaint-form-tip">为了后续能够及时与您取得联系处理问题，联系手机需输入常用号码</text>
						</view>

						<!-- 投诉内容 -->
						<view class="complaint-form-section">
							<text class="complaint-form-label">投诉内容</text>
							<textarea class="complaint-form-textarea" v-model="content"
								placeholder="不低于3个字，请详细说明情况"
								placeholder-class="complaint-form-placeholder"
								maxlength="500"></textarea>
							<text class="complaint-form-counter">{{ (content || '').length }}/500</text>
							<text class="complaint-form-tip">为了快速解决问题，投诉内容需要说明清楚、详细以便为您解决问题</text>
						</view>

						<!-- 上传图片 -->
						<view class="complaint-form-section">
							<text class="complaint-form-label">上传图片</text>
							<view class="complaint-upload-wrap">
								<view class="complaint-upload-list">
									<view class="complaint-upload-item" v-for="(file, idx) in fileList" :key="idx">
										<image class="complaint-upload-preview" :src="file.url" mode="aspectFill"
											@click="previewImage(idx)"></image>
										<view class="complaint-upload-delete" @click.stop="deleteImage(idx)">×</view>
										<view class="complaint-upload-mask" v-if="file.status === 'uploading'">上传中</view>
										<view class="complaint-upload-mask fail" v-if="file.status === 'failed'">失败</view>
									</view>
									<view class="complaint-upload-add" v-if="fileList.length < 9" @click="chooseImage">
										<text class="complaint-upload-add-icon">+</text>
										<text class="complaint-upload-add-text">添加图片</text>
									</view>
								</view>
							</view>
							<text class="complaint-form-tip">为了快速解决您的问题，如有相关诉求的图片或证明请上传以便平台核实处理</text>
						</view>
					</view>
				</view>
				<view class="bottom-spacer"></view>
			</view>
		</scroll-view>

		<!-- 底部提交按钮 -->
		<view class="submit-wrap">
			<view class="btn-submit" :class="{ disabled: !canSubmit || submitting }" @click="submitForm">提交投诉</view>
		</view>
	</view>
</template>

<script setup>
import { ref, computed } from 'vue';
import { onLoad } from '@dcloudio/uni-app';
import { orderComplaint, orderMsg } from '../../../request/api';
import { base_url } from '../../../request/env';
import { test } from '../../../uni_modules/uview-plus';

const typeList = ref([
	{ id: '1', name: '订单投诉' },
	{ id: '2', name: '请求退款' },
	{ id: '3', name: '投诉打手' }
]);
const current = ref(0);
const type = ref('1');
const phone = ref('');
const content = ref('');
const qurma = ref({});
const fileList = ref([]);
const submitting = ref(false);
const orderInfo = ref({});

const canSubmit = computed(() => {
	// 投诉内容不低于3个字
	if (!content.value || content.value.length < 3) return false;
	// 非请求退款类型需要填写有效手机号
	if (current.value !== 1) {
		if (!phone.value || !test.mobile(phone.value)) return false;
	}
	// 至少上传1张成功的图片
	const successImages = fileList.value.filter(f => f.status === 'success');
	if (successImages.length === 0) return false;
	return true;
});

onLoad((prop) => {
	qurma.value = prop;
	if (prop.id) {
		getOrderDetail(prop.id);
	}
	if (prop.status == '1') {
		typeList.value = [
			{ id: '1', name: '订单投诉' },
			{ id: '2', name: '请求退款' }
		];
	}
	if (prop.status == '5') {
		typeList.value = [
			{ id: '1', name: '订单投诉' },
			{ id: '3', name: '投诉打手' }
		];
	}
});

const goBack = () => {
	const p = getCurrentPages();
	if (p.length > 1) uni.navigateBack({ delta: 1 });
	else uni.reLaunch({ url: '/pages/mine/index' });
};
const goHome = () => uni.reLaunch({ url: '/pages/index/index' });

const getOrderDetail = (id) => {
	orderMsg({ id }).then(res => {
		if (res.data) {
			orderInfo.value = res.data;
		}
	});
};

const changeType = (item, index) => {
	current.value = index;
	type.value = item.id;
};

const chooseImage = () => {
	const remain = 9 - fileList.value.length;
	if (remain <= 0) return;
	uni.chooseImage({
		count: remain,
		sizeType: ['compressed'],
		sourceType: ['album', 'camera'],
		success: (res) => {
			res.tempFilePaths.forEach((filePath) => {
				const idx = fileList.value.length;
				fileList.value.push({ url: filePath, status: 'uploading' });
				uni.uploadFile({
					url: base_url + '/common/upload',
					filePath,
					name: 'file',
					header: { token: uni.getStorageSync('token') || '' },
					success: (uploadRes) => {
						try {
							const data = JSON.parse(uploadRes.data);
							if (data.data && data.data.url) {
								fileList.value[idx].url = data.data.fullurl;
								fileList.value[idx].status = 'success';
							} else {
								fileList.value[idx].status = 'failed';
							}
						} catch (e) {
							fileList.value[idx].status = 'failed';
						}
					},
					fail: () => {
						fileList.value[idx].status = 'failed';
					}
				});
			});
		}
	});
};

const deleteImage = (idx) => {
	fileList.value.splice(idx, 1);
};

const previewImage = (idx) => {
	const urls = fileList.value.filter(f => f.status === 'success').map(f => f.url);
	if (urls.length) {
		uni.previewImage({ urls, current: fileList.value[idx]?.url || urls[0] });
	}
};

const submitForm = () => {
	if (!canSubmit.value || submitting.value) return;
	if (!content.value || content.value.length < 3) {
		return uni.showToast({ title: '投诉内容不低于3个字', icon: 'none' });
	}
	if (current.value !== 1) {
		if (test.isEmpty(phone.value)) {
			return uni.showToast({ title: '请输入手机号', icon: 'none' });
		}
		if (!test.mobile(phone.value)) {
			return uni.showToast({ title: '请输入正确的手机号', icon: 'none' });
		}
	}
	const images = fileList.value.filter(f => f.status === 'success').map(f => f.url);
	submitting.value = true;
	orderComplaint({
		id: qurma.value.id,
		type: type.value,
		phone: phone.value,
		content: content.value,
		images: images.join(',')
	}).then(res => {
		if (res.code == 1) {
			uni.showToast({ title: res.msg, icon: 'none' });
			setTimeout(() => { uni.navigateBack({ delta: 1 }); }, 1000);
		}
	}).finally(() => {
		submitting.value = false;
	});
};
</script>

<style scoped>
/* 页面 */
.page {
	min-height: 100vh;
	background: #f5f6f9;
	display: flex;
	flex-direction: column;
}
.top-bg {
	position: absolute;
	top: 0; left: 0;
	width: 100%;
	height: 400rpx;
	z-index: 0;
	pointer-events: none;
}

/* 导航栏 */
.navbar {
	position: relative;
	z-index: 10;
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 24rpx 24rpx 0;
	padding-top: calc(var(--status-bar-height, 0px) + 16rpx);
	background: #fff;
}
.navbar-capsule {
	display: flex;
	align-items: center;
	border: 1rpx solid #dadbde;
	border-radius: 100px;
	padding: 8rpx 16rpx;
	backdrop-filter: blur(10px);
	-webkit-backdrop-filter: blur(10px);
	opacity: 0.95;
	background: rgba(255,255,255,0.3);
}
.navbar-btn { padding: 4rpx 8rpx; }
.navbar-divider {
	width: 1rpx; height: 32rpx;
	background: #dadbde;
	margin: 0 8rpx;
}
.navbar-title { font-size: 34rpx; font-weight: 600; color: #333; }
.navbar-placeholder { width: 120rpx; }

/* 内容区 */
.content {
	flex: 1;
	padding: 28rpx 0 180rpx;
	box-sizing: border-box;
}
.content-inner {
	padding: 0 24rpx;
	box-sizing: border-box;
}
.cards-wrap {
	padding-top: 0;
}

/* 卡片 */
.card {
	width: 100%;
	box-sizing: border-box;
	background: rgba(255,255,255,0.95);
	backdrop-filter: blur(24rpx);
	-webkit-backdrop-filter: blur(24rpx);
	border: 1rpx solid rgba(255,255,255,0.8);
	border-radius: 24rpx;
	margin-bottom: 24rpx;
	padding: 32rpx;
	box-shadow: 0 2rpx 20rpx rgba(0,0,0,0.04);
}
.card-title {
	display: block;
	font-size: 32rpx;
	font-weight: 700;
	color: #000;
	margin-bottom: 28rpx;
	padding-bottom: 24rpx;
	border-bottom: 1rpx solid rgba(0,0,0,0.08);
}

/* 表单区块 */
.complaint-form-section {
	margin-bottom: 28rpx;
	padding-bottom: 28rpx;
	border-bottom: 1rpx solid rgba(0,0,0,0.08);
}
.complaint-form-section:last-of-type {
	margin-bottom: 0;
	padding-bottom: 0;
	border-bottom: none;
}
.complaint-form-label {
	display: block;
	font-size: 28rpx;
	color: #333;
	font-weight: 500;
	margin-bottom: 16rpx;
}

/* 投诉类型标签 */
.complaint-type-tags {
	display: flex;
	flex-wrap: wrap;
	gap: 20rpx;
}
.complaint-type-tag {
	min-width: calc((100% - 40rpx) / 3);
	box-sizing: border-box;
	padding: 20rpx 24rpx;
	background: rgba(0,0,0,0.06);
	border-radius: 12rpx;
	text-align: center;
	font-size: 26rpx;
	color: #333;
}
.complaint-type-tag.active {
	background: #000;
	color: #fff;
}

/* 输入框 */
.complaint-form-input {
	width: 100%;
	height: 80rpx;
	padding: 0 24rpx;
	background: rgba(0,0,0,0.05);
	border-radius: 16rpx;
	font-size: 28rpx;
	color: #333;
	box-sizing: border-box;
}

/* 文本域 */
.complaint-form-textarea {
	width: 100%;
	min-height: 200rpx;
	padding: 20rpx 24rpx;
	background: rgba(0,0,0,0.05);
	border-radius: 16rpx;
	font-size: 28rpx;
	color: #333;
	box-sizing: border-box;
}
.complaint-form-placeholder { color: #999; }
.complaint-form-counter {
	display: block;
	text-align: right;
	font-size: 24rpx;
	color: #999;
	margin-top: 8rpx;
}
.complaint-form-tip {
	display: block;
	font-size: 24rpx;
	color: #999;
	margin-top: 12rpx;
	line-height: 1.5;
}

/* 图片上传 */
.complaint-upload-wrap {
	margin-top: 12rpx;
}
.complaint-upload-list {
	display: flex;
	flex-wrap: wrap;
	gap: 20rpx;
}
.complaint-upload-item {
	width: 160rpx;
	height: 160rpx;
	border-radius: 16rpx;
	overflow: hidden;
	position: relative;
	background: rgba(0,0,0,0.05);
}
.complaint-upload-preview {
	width: 100%;
	height: 100%;
	display: block;
}
.complaint-upload-delete {
	position: absolute;
	top: 4rpx;
	right: 4rpx;
	width: 44rpx;
	height: 44rpx;
	border-radius: 50%;
	background: rgba(0,0,0,0.5);
	color: #fff;
	font-size: 36rpx;
	line-height: 44rpx;
	text-align: center;
	padding: 0;
}
.complaint-upload-mask {
	position: absolute;
	left: 0; right: 0; bottom: 0;
	height: 48rpx;
	line-height: 48rpx;
	text-align: center;
	font-size: 22rpx;
	color: #fff;
	background: rgba(0,0,0,0.5);
}
.complaint-upload-mask.fail {
	background: rgba(220,53,69,0.8);
}
.complaint-upload-add {
	width: 160rpx;
	height: 160rpx;
	border-radius: 16rpx;
	border: 2rpx dashed rgba(0,0,0,0.2);
	background: rgba(0,0,0,0.03);
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 8rpx;
}
.complaint-upload-add-icon {
	font-size: 48rpx;
	color: #999;
	line-height: 1;
}
.complaint-upload-add-text {
	font-size: 24rpx;
	color: #999;
}

/* 商品信息 */
.product-card { padding: 32rpx; }
.product-row {
	display: flex;
	gap: 24rpx;
	margin-bottom: 24rpx;
}
.product-thumb {
	width: 160rpx;
	height: 160rpx;
	border-radius: 12rpx;
	background: #f0f0f0;
	flex-shrink: 0;
}
.product-info { flex: 1; min-width: 0; }
.product-name {
	display: -webkit-box;
	font-size: 30rpx;
	font-weight: 600;
	color: #000;
	overflow: hidden;
	text-overflow: ellipsis;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
}
.product-price {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 16rpx 0 12rpx;
	margin-bottom: 12rpx;
	border-bottom: 1rpx solid rgba(0,0,0,0.08);
}
.card-actual { display: flex; align-items: center; }
.price-symbol { font-size: 24rpx; color: #da4a44; font-weight: 600; }
.price-num { font-size: 32rpx; color: #da4a44; font-weight: 600; }
.price-qty { font-size: 24rpx; color: #999; margin-left: 8rpx; }
.product-desc {
	font-size: 24rpx;
	color: #8f8f8f;
	line-height: 1.5;
	display: -webkit-box;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
}

/* 订单信息 */
.delivery-section-title {
	display: block;
	font-size: 32rpx;
	font-weight: 700;
	color: #000;
	margin: 24rpx 0 20rpx;
	padding-bottom: 24rpx;
	border-bottom: 1rpx solid rgba(0,0,0,0.08);
}
.delivery-list {
	display: flex;
	flex-direction: column;
	gap: 16rpx;
}
.delivery {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 24rpx;
	font-size: 26rpx;
}
.delivery .label {
	flex-shrink: 0;
	color: #999;
}
.delivery .value {
	flex: 1;
	min-width: 0;
	color: #333;
	text-align: right;
	word-break: break-all;
}
.delivery .value.remark {
	white-space: normal;
	word-break: break-all;
	text-align: right;
}

/* 实付款 */
.pay-section {
	display: flex;
	align-items: flex-end;
	justify-content: space-between;
	margin-top: 20rpx;
	margin-bottom: 16rpx;
}
.pay-name {
	font-size: 28rpx;
	color: #343434;
}
.pay-price {
	font-size: 28rpx;
	font-weight: 600;
	color: #da4a44;
}

/* 底部间距 */
.bottom-spacer {
	height: 40rpx;
}

/* 提交按钮 */
.submit-wrap {
	position: fixed;
	left: 24rpx;
	right: 24rpx;
	bottom: 0;
	padding-bottom: calc(32rpx + env(safe-area-inset-bottom));
	z-index: 100;
}
.btn-submit {
	width: 100%;
	height: 80rpx;
	background: linear-gradient(135deg, #ffc300, #faad14);
	border-radius: 16rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 28rpx;
	color: #fff;
	font-weight: 500;
}
.btn-submit.disabled {
	background: #ccc;
	color: #999;
}
</style>
��