<template>
	<view class="page">
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
			<text class="navbar-title">意见反馈</text>
			<view class="navbar-placeholder"></view>
		</view>

		<view class="content">
			<view class="form-card">
				<view class="field">
					<up-icon name="phone" size="40rpx" color="#4A90D9"></up-icon>
					<input class="input" v-model="contact" type="text" placeholder="请输入您的联系方式" maxlength="50" />
				</view>
				<view class="field textarea-field">
					<up-icon name="chat" size="40rpx" color="#4A90D9"></up-icon>
					<textarea class="textarea" v-model="content" placeholder="请输入您的宝贵意见" maxlength="500" :auto-height="false" />
				</view>
				<view class="counter">{{ content.length }}/500</view>
			</view>

			<view class="hint">
				<text>提交后将自动接入联系客服，由客服人员跟进处理您的反馈</text>
			</view>

			<view class="submit-btn" :class="{ disabled: !canSubmit }" @click="handleSubmit">
				<text>提交反馈</text>
			</view>
		</view>

		<!-- 客服列表弹窗 -->
		<up-popup :show="showCsModal" mode="bottom" round="20" @close="showCsModal = false">
			<view class="cs-modal">
				<view class="cs-modal-title">联系客服</view>
				<view class="cs-list">
					<view v-if="csLoading" class="cs-empty">加载中...</view>
					<view v-else-if="csList.length === 0" class="cs-empty">暂无客服</view>
					<view v-else v-for="item in csList" :key="item.user_id" class="cs-item" @click="goCustomerService(item)">
						<image class="cs-avatar" :src="item.avatar" mode="aspectFill"></image>
						<view class="cs-info">
							<text class="cs-name">{{ item.name }}</text>
							<text class="cs-desc">{{ item.desc || '在线客服' }}</text>
						</view>
					</view>
				</view>
			</view>
		</up-popup>
	</view>
</template>

<script setup>
import { ref, computed } from 'vue';
import { customerServiceList } from '../../request/api';
import { openCustomerServiceChat } from '../../utils/customer-service';

const contact = ref('');
const content = ref('');

const canSubmit = computed(() => contact.value.trim() && content.value.trim());

// 客服列表
const showCsModal = ref(false);
const csList = ref([]);
const csLoading = ref(false);

const loadCsList = async () => {
	csLoading.value = true;
	try {
		const res = await getCustomerServiceList();
		csList.value = (res && res.data) ? res.data : [];
	} catch (e) {
		csList.value = [];
	} finally {
		csLoading.value = false;
	}
};

// 简单包装，便于改名
const getCustomerServiceList = () => customerServiceList();

const openCustomerService = () => {
	showCsModal.value = true;
	loadCsList();
};

const goCustomerService = (item) => {
	openCustomerServiceChat(item);
	showCsModal.value = false;
};

const handleSubmit = () => {
	if (!contact.value.trim()) {
		uni.showToast({ title: '请输入联系方式', icon: 'none' });
		return;
	}
	if (!content.value.trim()) {
		uni.showToast({ title: '请输入宝贵意见', icon: 'none' });
		return;
	}
	uni.showToast({ title: '提交成功', icon: 'success', duration: 1200 });
	// 提交后接入联系客服
	setTimeout(() => {
		openCustomerService();
	}, 1300);
};

const goBack = () => {
	const p = getCurrentPages();
	if (p.length > 1) uni.navigateBack({ delta: 1 });
	else uni.reLaunch({ url: '/pages/mine/index' });
};
const goHome = () => uni.reLaunch({ url: '/pages/index/index' });
</script>

<style scoped>
.page {
	min-height: 100vh;
	background: linear-gradient(180deg, #4A90D9 0%, #F5F7FA 100%);
	display: flex;
	flex-direction: column;
}
.navbar {
	position: relative;
	z-index: 10;
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 24rpx 24rpx 0;
	padding-top: calc(var(--status-bar-height, 0px) + 16rpx);
	background: transparent;
}
.navbar-capsule {
	display: flex;
	align-items: center;
	border: 1rpx solid rgba(255, 255, 255, 0.6);
	border-radius: 100px;
	padding: 8rpx 16rpx;
	backdrop-filter: blur(10px);
	-webkit-backdrop-filter: blur(10px);
	background: rgba(255,255,255,0.45);
}
.navbar-btn { padding: 4rpx 8rpx; }
.navbar-divider {
	width: 1rpx; height: 32rpx;
	background: rgba(255,255,255,0.6);
	margin: 0 8rpx;
}
.navbar-title { font-size: 34rpx; font-weight: 600; color: #ffffff; }
.navbar-placeholder { width: 120rpx; }
.content {
	flex: 1;
	padding: 32rpx 24rpx;
	box-sizing: border-box;
}
.form-card {
	background: #ffffff;
	border-radius: 20rpx;
	padding: 32rpx 24rpx;
	box-shadow: 0 4rpx 24rpx rgba(74, 144, 217, 0.10);
	position: relative;
}
.field {
	display: flex;
	align-items: center;
	padding: 24rpx 0;
	border-bottom: 1rpx solid #F0F2F5;
}
.field:last-of-type { border-bottom: none; }
.textarea-field {
	align-items: flex-start;
}
.input {
	flex: 1;
	margin-left: 16rpx;
	font-size: 28rpx;
	color: #333;
	height: 60rpx;
	line-height: 60rpx;
}
.textarea {
	flex: 1;
	margin-left: 16rpx;
	font-size: 28rpx;
	color: #333;
	height: 240rpx;
	line-height: 1.6;
}
.counter {
	position: absolute;
	right: 24rpx;
	bottom: 12rpx;
	font-size: 22rpx;
	color: #999;
}
.hint {
	margin-top: 24rpx;
	text-align: center;
}
.hint text {
	font-size: 24rpx;
	color: #999;
	line-height: 1.6;
}
.submit-btn {
	margin-top: 48rpx;
	height: 88rpx;
	border-radius: 44rpx;
	background: linear-gradient(90deg, #4A90D9 0%, #5BA0E8 100%);
	display: flex;
	align-items: center;
	justify-content: center;
	box-shadow: 0 8rpx 24rpx rgba(74, 144, 217, 0.30);
}
.submit-btn text {
	font-size: 30rpx;
	color: #ffffff;
	font-weight: 600;
}
.submit-btn.disabled {
	background: #C0C4CC;
	box-shadow: none;
}

/* 客服弹窗 */
.cs-modal {
	padding: 32rpx 24rpx;
}
.cs-modal-title {
	font-size: 32rpx;
	font-weight: 600;
	color: #333;
	text-align: center;
	margin-bottom: 24rpx;
}
.cs-list {
	max-height: 600rpx;
	overflow-y: auto;
}
.cs-empty {
	text-align: center;
	color: #999;
	font-size: 26rpx;
	padding: 40rpx 0;
}
.cs-item {
	display: flex;
	align-items: center;
	padding: 20rpx 16rpx;
	border-radius: 12rpx;
	background: #F5F7FA;
	margin-bottom: 16rpx;
}
.cs-avatar {
	width: 80rpx;
	height: 80rpx;
	border-radius: 50%;
	margin-right: 20rpx;
	background: #eee;
}
.cs-info {
	display: flex;
	flex-direction: column;
}
.cs-name {
	font-size: 28rpx;
	color: #333;
	font-weight: 600;
}
.cs-desc {
	font-size: 24rpx;
	color: #999;
	margin-top: 4rpx;
}
</style>
