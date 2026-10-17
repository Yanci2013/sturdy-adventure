<template>
	<view class="page">
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
			<text class="navbar-title">投诉陪玩</text>
			<view class="navbar-placeholder"></view>
		</view>

		<scroll-view scroll-y class="content">
			<view class="content-inner">
				<!-- 选择订单 -->
				<view class="form-section">
					<text class="form-label"><text class="required">*</text>选择订单</text>
					<view class="select-trigger" @click="openOrderPicker">
						<text class="select-placeholder" v-if="!selectedOrder">选择陪玩所服务的订单</text>
						<text class="select-value" v-else>{{ selectedOrderText }}</text>
						<text class="select-arrow">选择订单 ›</text>
					</view>
				</view>

				<!-- 投诉内容 -->
				<view class="form-section">
					<text class="form-label"><text class="required">*</text>投诉内容</text>
					<view class="textarea-box">
						<textarea class="form-textarea" v-model="content" maxlength="200"
							placeholder="请填写陪玩昵称以及相关问题"
							placeholder-class="placeholder" />
						<text class="form-counter">{{ (content || '').length }}/200</text>
					</view>
				</view>

				<!-- 照片 -->
				<view class="form-section">
					<text class="form-label">照片</text>
					<view class="upload-list">
						<view class="upload-item" v-for="(file, idx) in fileList" :key="idx">
							<image class="upload-preview" :src="file.url" mode="aspectFill" @click="previewImage(idx)"></image>
							<view class="upload-delete" @click.stop="deleteImage(idx)">×</view>
						</view>
						<view class="upload-add" v-if="fileList.length < 8" @click="chooseImage">
							<text class="upload-add-icon">+</text>
						</view>
					</view>
					<text class="upload-counter">{{ fileList.length }}/8</text>
				</view>

				<!-- 联系方式 -->
				<view class="form-section">
					<text class="form-label">联系方式</text>
					<input class="form-input" v-model="phone" type="number" maxlength="11"
						placeholder="手机号码，用于联系你" placeholder-class="placeholder" />
				</view>

				<view class="bottom-spacer"></view>
			</view>
		</scroll-view>

		<!-- 底部提交按钮 -->
		<view class="submit-wrap">
			<view class="btn-submit" :class="{ disabled: !canSubmit }" @click="submitForm">提交</view>
		</view>

		<!-- 订单选择弹窗 -->
		<view class="order-modal-mask" v-if="showOrderModal" @click="showOrderModal = false">
			<view class="order-modal" @click.stop>
				<view class="order-modal-header">
					<text class="order-modal-title">选择订单</text>
					<view class="order-modal-close" @click="showOrderModal = false">×</view>
				</view>
				<scroll-view scroll-x class="order-tabs">
					<view v-for="(tab, idx) in statusTabs" :key="idx"
						class="order-tab" :class="{ active: currentTab === idx }"
						@click="switchTab(idx)">
						<text>{{ tab.name }}</text>
					</view>
				</scroll-view>
				<scroll-view scroll-y class="order-list">
					<view v-if="orderLoading" class="order-empty">加载中...</view>
					<view v-else-if="filteredOrders.length === 0" class="order-empty">暂无订单</view>
					<view v-else v-for="order in filteredOrders" :key="order.id" class="order-item" @click="selectOrder(order)">
						<view class="order-item-info">
							<text class="order-item-title">{{ order.title || order.product_name || '订单 #' + order.id }}</text>
							<text class="order-item-no">单号：{{ order.order_no }}</text>
						</view>
						<text class="order-item-status">{{ order.status_text || '查看' }}</text>
					</view>
				</scroll-view>
			</view>
		</view>
	</view>
</template>

<script setup>
import { ref, computed } from 'vue';
import { test } from '../../uni_modules/uview-plus';
import { base_url } from '../../request/env';

const content = ref('');
const phone = ref('');
const fileList = ref([]);
const selectedOrder = ref(null);

const showOrderModal = ref(false);
const orderLoading = ref(false);
const orderList = ref([]);
const currentTab = ref(0);

const statusTabs = ref([
	{ name: '全部', value: '' },
	{ name: '待服务', value: '1' },
	{ name: '服务中', value: '2' },
	{ name: '已完成', value: '3' },
	{ name: '售后中', value: '4' },
	{ name: '已失效', value: '5' },
	{ name: '退款', value: '6' }
]);

const filteredOrders = computed(() => {
	const tab = statusTabs.value[currentTab.value]
	if (!tab || !tab.value) return orderList.value
	return orderList.value.filter(o => String(o.status) === tab.value)
});

const selectedOrderText = computed(() => {
	if (!selectedOrder.value) return ''
	return selectedOrder.value.title || selectedOrder.value.product_name || ('订单 #' + selectedOrder.value.id)
});

const canSubmit = computed(() => {
	return selectedOrder.value && content.value.trim().length >= 3
});

const openOrderPicker = () => {
	showOrderModal.value = true
	if (orderList.value.length === 0) {
		loadOrders()
	}
};

const loadOrders = () => {
	orderLoading.value = true
	// 加载订单列表（从后端接口，mock 环境下空列表）
	setTimeout(() => {
		orderList.value = []
		orderLoading.value = false
	}, 400)
};

const switchTab = (idx) => {
	currentTab.value = idx
};

const selectOrder = (order) => {
	selectedOrder.value = order
	showOrderModal.value = false
};

const chooseImage = () => {
	const remain = 8 - fileList.value.length
	if (remain <= 0) return
	uni.chooseImage({
		count: remain,
		sizeType: ['compressed'],
		sourceType: ['album', 'camera'],
		success: (res) => {
			res.tempFilePaths.forEach((filePath) => {
				const idx = fileList.value.length
				fileList.value.push({ url: filePath, status: 'uploading' })
				uni.uploadFile({
					url: base_url + '/common/upload',
					filePath,
					name: 'file',
					header: { token: uni.getStorageSync('token') || '' },
					success: (uploadRes) => {
						try {
							const data = JSON.parse(uploadRes.data)
							if (data.data && data.data.url) {
								fileList.value[idx].url = data.data.fullurl
								fileList.value[idx].status = 'success'
							} else {
								fileList.value[idx].status = 'failed'
							}
						} catch (e) {
							fileList.value[idx].status = 'failed'
						}
					},
					fail: () => {
						fileList.value[idx].status = 'failed'
					}
				})
			})
		}
	})
};

const deleteImage = (idx) => {
	fileList.value.splice(idx, 1)
};

const previewImage = (idx) => {
	const urls = fileList.value.filter(f => f.status === 'success').map(f => f.url)
	if (urls.length) {
		uni.previewImage({ urls, current: fileList.value[idx]?.url || urls[0] })
	}
};

const submitForm = () => {
	if (!canSubmit.value) {
		if (!selectedOrder.value) {
			uni.showToast({ title: '请选择订单', icon: 'none' })
		} else if (content.value.trim().length < 3) {
			uni.showToast({ title: '投诉内容不少于3字', icon: 'none' })
		}
		return
	}
	if (phone.value && !test.mobile(phone.value)) {
		uni.showToast({ title: '请输入正确的手机号', icon: 'none' })
		return
	}
	uni.showLoading({ title: '提交中...', mask: true })
	// 投诉提交（mock 环境下模拟成功）
	setTimeout(() => {
		uni.hideLoading()
		uni.showToast({ title: '投诉提交成功', icon: 'success' })
		setTimeout(() => { uni.navigateBack({ delta: 1 }) }, 1200)
	}, 800)
};

const goBack = () => {
	const p = getCurrentPages()
	if (p.length > 1) uni.navigateBack({ delta: 1 })
	else uni.reLaunch({ url: '/pages/mine/index' })
};
const goHome = () => uni.reLaunch({ url: '/pages/index/index' });
</script>

<style scoped>
.page {
	min-height: 100vh;
	background: #f5f6f9;
	display: flex;
	flex-direction: column;
}

/* 导航栏 */
.navbar {
	position: relative;
	z-index: 10;
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 24rpx 24rpx 16rpx;
	padding-top: calc(var(--status-bar-height, 0px) + 16rpx);
	background: #fff;
	box-shadow: 0 2rpx 8rpx rgba(0,0,0,0.04);
}
.navbar-capsule {
	display: flex;
	align-items: center;
	border: 1rpx solid #dadbde;
	border-radius: 100px;
	padding: 8rpx 16rpx;
	background: rgba(255,255,255,0.5);
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
	min-height: 0;
	padding: 24rpx 0 200rpx;
	box-sizing: border-box;
}
.content-inner {
	padding: 0 24rpx;
	box-sizing: border-box;
}

/* 表单区块 */
.form-section {
	background: #fff;
	border-radius: 16rpx;
	padding: 28rpx 24rpx;
	margin-bottom: 16rpx;
}
.form-label {
	display: block;
	font-size: 28rpx;
	font-weight: 500;
	color: #333;
	margin-bottom: 20rpx;
}
.required {
	color: #f5222d;
	margin-right: 4rpx;
}

.select-trigger {
	display: flex;
	align-items: center;
	height: 80rpx;
	padding: 0 20rpx;
	background: #f7f8fa;
	border-radius: 12rpx;
}
.select-placeholder {
	flex: 1;
	font-size: 26rpx;
	color: #999;
}
.select-value {
	flex: 1;
	font-size: 26rpx;
	color: #333;
	overflow: hidden;
	white-space: nowrap;
	text-overflow: ellipsis;
}
.select-arrow {
	font-size: 24rpx;
	color: #FF7A45;
}

.textarea-box {
	position: relative;
}
.form-textarea {
	width: 100%;
	height: 200rpx;
	padding: 20rpx;
	background: #f7f8fa;
	border-radius: 12rpx;
	font-size: 26rpx;
	color: #333;
	box-sizing: border-box;
}
.placeholder { color: #999; }
.form-counter {
	position: absolute;
	right: 20rpx;
	bottom: 16rpx;
	font-size: 22rpx;
	color: #999;
}

.upload-list {
	display: flex;
	flex-wrap: wrap;
	gap: 16rpx;
	margin-bottom: 12rpx;
}
.upload-item {
	position: relative;
	width: 160rpx;
	height: 160rpx;
	border-radius: 12rpx;
	overflow: hidden;
}
.upload-preview {
	width: 100%;
	height: 100%;
	display: block;
}
.upload-delete {
	position: absolute;
	top: 4rpx;
	right: 4rpx;
	width: 36rpx;
	height: 36rpx;
	border-radius: 50%;
	background: rgba(0,0,0,0.5);
	color: #fff;
	font-size: 28rpx;
	line-height: 36rpx;
	text-align: center;
}
.upload-add {
	width: 160rpx;
	height: 160rpx;
	border: 2rpx dashed #d0d0d0;
	border-radius: 12rpx;
	background: #f7f8fa;
	display: flex;
	align-items: center;
	justify-content: center;
}
.upload-add-icon {
	font-size: 60rpx;
	color: #ccc;
	line-height: 1;
}
.upload-counter {
	display: block;
	font-size: 22rpx;
	color: #999;
}

.form-input {
	width: 100%;
	height: 80rpx;
	padding: 0 20rpx;
	background: #f7f8fa;
	border-radius: 12rpx;
	font-size: 26rpx;
	color: #333;
	box-sizing: border-box;
}

.bottom-spacer { height: 40rpx; }

/* 底部提交按钮 */
.submit-wrap {
	position: fixed;
	left: 24rpx;
	right: 24rpx;
	bottom: 0;
	padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
	z-index: 100;
}
.btn-submit {
	width: 100%;
	height: 88rpx;
	background: linear-gradient(135deg, #FF7A45 0%, #FFB38A 100%);
	border-radius: 44rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 30rpx;
	color: #fff;
	font-weight: 600;
	box-shadow: 0 8rpx 20rpx rgba(255, 122, 69, 0.30);
}
.btn-submit.disabled {
	background: #ccc;
	color: #fff;
	box-shadow: none;
}

/* 订单选择弹窗 */
.order-modal-mask {
	position: fixed;
	left: 0; top: 0; right: 0; bottom: 0;
	background: rgba(0,0,0,0.5);
	z-index: 1000;
	display: flex;
	align-items: flex-end;
}
.order-modal {
	width: 100%;
	max-height: 80vh;
	background: #fff;
	border-radius: 24rpx 24rpx 0 0;
	display: flex;
	flex-direction: column;
}
.order-modal-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 28rpx 24rpx 20rpx;
	border-bottom: 1rpx solid #f0f0f0;
}
.order-modal-title {
	font-size: 32rpx;
	font-weight: 600;
	color: #333;
}
.order-modal-close {
	width: 56rpx;
	height: 56rpx;
	font-size: 40rpx;
	color: #999;
	line-height: 56rpx;
	text-align: center;
}
.order-tabs {
	white-space: nowrap;
	padding: 20rpx 16rpx;
	border-bottom: 1rpx solid #f0f0f0;
}
.order-tab {
	display: inline-block;
	padding: 12rpx 28rpx;
	margin: 0 8rpx;
	font-size: 26rpx;
	color: #666;
	border-radius: 100px;
}
.order-tab.active {
	color: #FF7A45;
	background: rgba(255, 122, 69, 0.10);
	position: relative;
}
.order-tab.active::after {
	content: '';
	position: absolute;
	bottom: -8rpx;
	left: 50%;
	transform: translateX(-50%);
	width: 40rpx;
	height: 4rpx;
	background: #FF7A45;
	border-radius: 2rpx;
}
.order-list {
	flex: 1;
	max-height: 600rpx;
	padding: 16rpx 24rpx;
}
.order-empty {
	text-align: center;
	color: #999;
	font-size: 26rpx;
	padding: 60rpx 0;
}
.order-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 24rpx 16rpx;
	background: #f7f8fa;
	border-radius: 12rpx;
	margin-bottom: 12rpx;
}
.order-item-info {
	flex: 1;
	min-width: 0;
}
.order-item-title {
	display: block;
	font-size: 28rpx;
	color: #333;
	font-weight: 500;
	margin-bottom: 8rpx;
	overflow: hidden;
	white-space: nowrap;
	text-overflow: ellipsis;
}
.order-item-no {
	font-size: 22rpx;
	color: #999;
}
.order-item-status {
	font-size: 24rpx;
	color: #FF7A45;
	margin-left: 16rpx;
	flex-shrink: 0;
}
</style>
