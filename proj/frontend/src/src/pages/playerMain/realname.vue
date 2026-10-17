<template>
	<view class="page">
		<!-- 导航栏 -->
		<view class="navbar">
			<view class="navbar-capsule" @click="goBack">
				<up-icon name="arrow-left" size="34rpx" color="#fff"></up-icon>
			</view>
			<text class="navbar-title">实名认证</text>
			<view class="navbar-placeholder"></view>
		</view>

		<view class="content">
			<!-- 状态提示 -->
			<view v-if="status === 1" class="status-tip status-pending">
				<up-icon name="info-circle" size="36" color="#FF9800"></up-icon>
				<text>实名认证审核中，请耐心等待</text>
			</view>
			<view v-else-if="status === 2" class="status-tip status-pass">
				<up-icon name="checkmark-circle" size="36" color="#4CAF50"></up-icon>
				<text>实名认证已通过</text>
			</view>
			<view v-else-if="status === 3" class="status-tip status-reject">
				<up-icon name="close-circle" size="36" color="#F44336"></up-icon>
				<text>实名认证未通过，请重新提交</text>
			</view>

			<!-- 表单 -->
			<view class="form-card" v-if="status !== 1 && status !== 2">
				<view class="form-group">
					<text class="form-label">真实姓名</text>
					<input class="form-input" v-model="realName" placeholder="请输入身份证上的姓名" placeholder-class="placeholder" />
				</view>
				<view class="form-group">
					<text class="form-label">身份证号</text>
					<input class="form-input" v-model="idCard" placeholder="请输入18位身份证号" placeholder-class="placeholder" maxlength="18" />
				</view>

				<view class="form-group">
					<text class="form-label">身份证人像面</text>
					<view class="upload-area" @click="uploadFront">
						<image v-if="frontImg" class="upload-img" :src="frontImg" mode="aspectFill"></image>
						<view v-else class="upload-placeholder">
							<up-icon name="camera" size="40" color="#ccc"></up-icon>
							<text class="upload-text">点击上传人像面</text>
						</view>
					</view>
				</view>

				<view class="form-group">
					<text class="form-label">身份证国徽面</text>
					<view class="upload-area" @click="uploadBack">
						<image v-if="backImg" class="upload-img" :src="backImg" mode="aspectFill"></image>
						<view v-else class="upload-placeholder">
							<up-icon name="camera" size="40" color="#ccc"></up-icon>
							<text class="upload-text">点击上传国徽面</text>
						</view>
					</view>
				</view>

				<view class="btn-area">
					<view :class="['btn-submit', submitting ? 'disabled' : '']" @click="submit">提交认证</view>
				</view>
			</view>

			<!-- 已通过展示 -->
			<view class="form-card" v-else-if="status === 2">
				<view class="info-row">
					<text class="info-label">真实姓名</text>
					<text class="info-value">{{ realName }}</text>
				</view>
				<view class="info-row">
					<text class="info-label">身份证号</text>
					<text class="info-value">{{ idCard }}</text>
				</view>
				<view class="info-row">
					<text class="info-label">人像面</text>
					<image class="info-img" :src="frontImg" mode="aspectFill" v-if="frontImg"></image>
				</view>
				<view class="info-row">
					<text class="info-label">国徽面</text>
					<image class="info-img" :src="backImg" mode="aspectFill" v-if="backImg"></image>
				</view>
			</view>
		</view>
	</view>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { thugRealname, thugRealnameInfo } from '../../request/api';

const realName = ref('');
const idCard = ref('');
const frontImg = ref('');
const backImg = ref('');
const status = ref(0); // 0未认证 1审核中 2已通过 3已拒绝
const submitting = ref(false);

onMounted(() => {
	loadInfo();
});

const loadInfo = async () => {
	try {
		const res = await thugRealnameInfo();
		if (res.code === 1 && res.data) {
			realName.value = res.data.real_name || '';
			idCard.value = res.data.id_card || '';
			frontImg.value = res.data.id_card_front || '';
			backImg.value = res.data.id_card_back || '';
			status.value = res.data.real_name_status || 0;
		}
	} catch (e) {}
};

const uploadFront = () => {
	uni.chooseImage({
		count: 1,
		success: (res) => {
			frontImg.value = res.tempFilePaths[0];
			// 上传到服务器
			uploadImage(res.tempFilePaths[0]).then(url => {
				if (url) frontImg.value = url;
			});
		}
	});
};

const uploadBack = () => {
	uni.chooseImage({
		count: 1,
		success: (res) => {
			backImg.value = res.tempFilePaths[0];
			uploadImage(res.tempFilePaths[0]).then(url => {
				if (url) backImg.value = url;
			});
		}
	});
};

const uploadImage = (filePath) => {
	return new Promise((resolve) => {
		uni.uploadFile({
			url: '/api/config/uploadBg',
			filePath: filePath,
			name: 'file',
			success: (res) => {
				try {
					const data = JSON.parse(res.data);
					if (data.code === 1 && data.data && data.data.url) {
						resolve(data.data.url);
					} else {
						resolve('');
					}
				} catch (e) {
					resolve('');
				}
			},
			fail: () => resolve('')
		});
	});
};

const submit = async () => {
	if (submitting.value) return;
	if (!realName.value.trim()) {
		return uni.showToast({ title: '请输入真实姓名', icon: 'none' });
	}
	if (!/^\d{17}[\dXx]$/.test(idCard.value.trim())) {
		return uni.showToast({ title: '请输入正确的身份证号', icon: 'none' });
	}
	if (!frontImg.value) {
		return uni.showToast({ title: '请上传身份证人像面', icon: 'none' });
	}
	if (!backImg.value) {
		return uni.showToast({ title: '请上传身份证国徽面', icon: 'none' });
	}
	submitting.value = true;
	uni.showLoading({ title: '提交中...', mask: true });
	try {
		const res = await thugRealname({
			real_name: realName.value.trim(),
			id_card: idCard.value.trim(),
			id_card_front: frontImg.value,
			id_card_back: backImg.value
		});
		if (res.code === 1) {
			uni.showToast({ title: res.msg || '提交成功', icon: 'success' });
			status.value = 1;
		} else {
			uni.showToast({ title: res.msg || '提交失败', icon: 'none' });
		}
	} catch (e) {
		uni.showToast({ title: '网络异常，请重试', icon: 'none' });
	} finally {
		uni.hideLoading();
		submitting.value = false;
	}
};

const goBack = () => {
	const pages = getCurrentPages();
	if (pages.length > 1) uni.navigateBack({ delta: 1 });
	else uni.reLaunch({ url: '/pages/playerMain/index' });
};
</script>

<style scoped>
page { background: #f5f5f7 !important; }
.page { min-height: 100vh; background: #f5f5f7; }
.navbar {
	display: flex; align-items: center; justify-content: space-between;
	padding: 0 24rpx;
	padding-top: calc(var(--status-bar-height, 0px) + 16rpx);
	padding-bottom: 16rpx;
	background: #fff;
}
.navbar-capsule { display: flex; align-items: center; }
.navbar-title { font-size: 34rpx; font-weight: 600; color: #000; }
.navbar-placeholder { width: 80rpx; }
.content { padding: 24rpx; }
.status-tip {
	display: flex; align-items: center; gap: 12rpx;
	padding: 24rpx; border-radius: 16rpx; margin-bottom: 24rpx;
	font-size: 28rpx; font-weight: 500;
}
.status-pending { background: #FFF8E1; color: #FF9800; }
.status-pass { background: #E8F5E9; color: #4CAF50; }
.status-reject { background: #FFEBEE; color: #F44336; }
.form-card {
	background: #fff; border-radius: 20rpx; padding: 32rpx 28rpx;
	box-shadow: 0 2rpx 12rpx rgba(0,0,0,0.04);
}
.form-group { margin-bottom: 32rpx; }
.form-label { display: block; font-size: 28rpx; color: #333; font-weight: 500; margin-bottom: 16rpx; }
.form-input {
	width: 100%; height: 88rpx; padding: 0 24rpx;
	background: #f5f5f7; border-radius: 12rpx; font-size: 28rpx; color: #333;
	box-sizing: border-box;
}
.placeholder { color: #bbb; }
.upload-area {
	width: 100%; height: 360rpx; background: #f5f5f7; border-radius: 12rpx;
	overflow: hidden; display: flex; align-items: center; justify-content: center;
}
.upload-placeholder { display: flex; flex-direction: column; align-items: center; gap: 12rpx; }
.upload-text { font-size: 26rpx; color: #bbb; }
.upload-img { width: 100%; height: 100%; }
.btn-area { margin-top: 16rpx; }
.btn-submit {
	width: 100%; height: 92rpx; line-height: 92rpx; text-align: center;
	font-size: 32rpx; color: #fff; font-weight: 600;
	background: linear-gradient(135deg, #FF7A45, #FF9D6E); border-radius: 46rpx;
}
.btn-submit.disabled { opacity: 0.6; }
.info-row {
	display: flex; align-items: center; justify-content: space-between;
	padding: 20rpx 0; border-bottom: 1rpx solid #f0f0f0;
}
.info-row:last-child { border-bottom: none; }
.info-label { font-size: 28rpx; color: #999; }
.info-value { font-size: 28rpx; color: #333; font-weight: 500; }
.info-img { width: 200rpx; height: 130rpx; border-radius: 8rpx; }
</style>
