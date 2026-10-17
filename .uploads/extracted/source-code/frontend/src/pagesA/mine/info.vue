<template>
	<view class="info-page">
		<!-- 顶部导航 -->
		<view class="navbar">
			<view class="navbar-capsule">
				<view class="navbar-btn" @click="navBack">
					<up-icon name="arrow-left" size="34rpx" color="#606266"></up-icon>
				</view>
				<view class="navbar-divider"></view>
				<view class="navbar-btn" @click="goHome">
					<up-icon name="home" size="36rpx" color="#606266"></up-icon>
				</view>
			</view>
			<text class="navbar-title">用户信息</text>
			<view class="navbar-placeholder"></view>
		</view>

		<!-- 顶部背景 -->
		<view class="top-bg">
			<u-image src="/static/images/header_background.png" mode="aspectFill" width="750rpx" height="500rpx"></u-image>
		</view>

		<!-- 头像区域 -->
		<view class="avatar-section">
			<view class="avatar-box" @click="chooseAvatar">
				<image class="avatar-img" :src="avatarfull || '/static/yun/default_avatar.png'" mode="aspectFill"></image>
				<view class="avatar-edit-icon">
					<up-icon name="edit-pen" color="#ffffff" size="26rpx"></up-icon>
				</view>
			</view>
			<text class="avatar-label">设置头像</text>
		</view>

		<!-- 表单卡片 -->
		<view class="form-card">
			<!-- 昵称 -->
			<view class="form-item">
				<text class="form-label">昵称</text>
				<input class="form-input" v-model="nickname" placeholder="输入昵称" placeholder-class="form-placeholder" />
			</view>

			<!-- 性别 -->
			<view class="form-item form-item-last">
				<text class="form-label">性别</text>
				<view class="gender-row">
					<view :class="['gender-btn', sex === '男' ? 'gender-active' : '']" @click="sex = '男'">男生</view>
					<view :class="['gender-btn', sex === '女' ? 'gender-active' : '']" @click="sex = '女'">女生</view>
				</view>
			</view>

			<!-- 打手扩展信息 -->
			<template v-if="type === '打手'">
				<view class="divider"></view>
				<view class="form-item">
					<text class="form-label">接单状态</text>
					<view class="switch-wrap">
						<up-switch activeColor="#F5A623" v-model="jsStatus"></up-switch>
					</view>
				</view>
				<view class="section-title">个人图片</view>
				<view class="section-block">
					<commonUpload :maxCount="9" :tempImageList="imageList" :tempfileList="fileList"
						uploadWidth="190rpx" uploadHeight="190rpx"
						@upload-success="handleUploadSuccess" @delete-image="handleDele"></commonUpload>
				</view>
				<view class="section-title">个人语音</view>
				<view class="section-block audio-upload-block">
					<view v-if="audio" class="audio-item">
						<view class="audio-info">
							<up-icon name="mic" size="32rpx" color="#666"></up-icon>
							<text class="audio-name">{{audioName || '音频文件'}}</text>
						</view>
						<view class="audio-actions">
							<view class="audio-btn audio-btn-replace" @click="chooseAudio">更换</view>
							<view class="audio-btn audio-btn-delete" @click="deleteAudio">删除</view>
						</view>
					</view>
					<view v-else class="audio-upload-btn" @click="chooseAudio">
						<up-icon name="plus" size="40rpx" color="#999"></up-icon>
						<text class="audio-upload-text">点击上传语音</text>
					</view>
				</view>
				<view class="section-title">个人简介</view>
				<view class="section-block">
					<up-textarea v-model="synopsis" border="none" placeholder="请输入简介"></up-textarea>
				</view>
			</template>
		</view>

		<!-- 保存按钮 -->
		<view class="save-btn-wrap">
			<view class="save-btn" @click="submint">保存修改</view>
		</view>
	</view>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { getUserInfo, userEdit, thugEdit, thugMsg } from '../../request/api';
import { base_url } from '../../request/env';
import router from '../../utils/router';

const thugId = ref('')
const type = uni.getStorageSync('type')
const nickname = ref('')
const avatar = ref('')
const avatarfull = ref('')
const mobile = ref('')
const synopsis = ref('')
const sex = ref('男')
const imageList = ref([])
const fileList = ref([])
const jsStatus = ref(false)
const audio = ref('')
const audiofull = ref('')
const audioName = ref('')

onMounted(() => {
	onLoadUserInfo()
})

const navBack = () => uni.navigateBack({ delta: 1 })
const goHome = () => router.reLaunch({ url: 'pages/index/index' })

const chooseAvatar = () => {
	uni.chooseImage({
		count: 1,
		success: (res) => {
			uni.showLoading({ title: '上传中..', mask: true });
			uni.uploadFile({
				url: base_url + "/common/upload",
				filePath: res.tempFilePaths[0],
				name: "file",
				header: { token: uni.getStorageSync('token') },
				success: res => {
					uni.hideLoading()
					const data = JSON.parse(res.data);
					avatarfull.value = data.data.fullurl;
					avatar.value = data.data.url;
				},
				fail: () => uni.hideLoading()
			});
		}
	})
}

const chooseAudio = () => {
	uni.chooseFile({
		count: 1,
		type: 'audio',
		success: (res) => {
			uni.showLoading({ title: '上传中..', mask: true });
			uni.uploadFile({
				url: base_url + "/common/upload",
				filePath: res.tempFilePaths[0],
				name: "file",
				header: { token: uni.getStorageSync('token') },
				success: res => {
					uni.hideLoading()
					const data = JSON.parse(res.data);
					audiofull.value = data.data.fullurl;
					audio.value = data.data.url;
					audioName.value = res.tempFiles[0].name || '音频';
					uni.showToast({ title: '上传成功', icon: 'none' });
				},
				fail: () => {
					uni.hideLoading()
					uni.showToast({ title: '上传失败', icon: 'none' });
				}
			});
		}
	})
}

const deleteAudio = () => {
	audio.value = ''
	audiofull.value = ''
	audioName.value = ''
}

const onLoadUserInfo = async () => {
	uni.showLoading({ title: '加载中..', mask: true })
	try {
		const res = type == '用户' ? await getUserInfo() : await thugMsg({ thug_id: uni.getStorageSync('id') })
		nickname.value = type == '用户' ? res.data.nickname : res.data.name
		avatarfull.value = res.data.avatar
		mobile.value = res.data.mobile
		sex.value = res.data.sex == 0 ? '女' : '男'
		if (type == '打手') {
			thugId.value = res.data.id
			synopsis.value = res.data.synopsis
			imageList.value = res.data.images_text
			jsStatus.value = res.data.jd_status == '1'
			res.data.images.map(v => {
				fileList.value.push({ url: v, type: 'image' })
			})
			audio.value = res.data.audio || ''
			audiofull.value = res.data.audio ? (res.data.audio_full || res.data.audio) : ''
			audioName.value = res.data.audio ? '音频文件' : ''
		}
	} catch (e) {
		console.log('加载用户信息失败', e)
	} finally {
		uni.hideLoading()
	}
}

const handleUploadSuccess = (res) => { imageList.value = res }
const handleDele = (res) => { imageList.value = res }

const submint = () => {
	if (type == '用户') {
		userEdit({
			nickname: nickname.value,
			avatar: avatar.value,
			mobile: mobile.value,
			sex: sex.value == '女' ? '0' : '1'
		}).then(res => {
			uni.showToast({ title: '修改成功', icon: 'none' });
			setTimeout(() => onLoadUserInfo(), 1500)
		})
	} else {
		thugEdit({
			name: nickname.value,
			avatar: avatar.value,
			mobile: mobile.value,
			sex: sex.value == '女' ? '0' : '1',
			synopsis: synopsis.value,
			images: imageList.value.join(','),
			thug_id: thugId.value,
			jd_status: jsStatus.value ? '1' : '0',
			audio: audio.value
		}).then(res => {
			uni.showToast({ title: '修改成功', icon: 'none' });
			setTimeout(() => {
				fileList.value = []
				onLoadUserInfo()
			}, 1500)
		})
	}
}
</script>

<style lang="scss" scoped>
.info-page {
	min-height: 100vh;
	background: #EDF1F7;
}
.navbar {
	position: relative;
	z-index: 10;
	display: flex;
	flex-direction: row;
	align-items: center;
	justify-content: space-between;
	padding: 24rpx 24rpx 20rpx;
	padding-top: calc(var(--status-bar-height, 0px) + 16rpx);
	background: #fff;
}
.navbar-capsule {
	display: flex;
	flex-direction: row;
	align-items: center;
	border: 1rpx solid #dadbde;
	border-radius: 100px;
	padding: 8rpx 16rpx;
	background: rgba(255,255,255,0.9);
}
.navbar-btn { padding: 4rpx 8rpx; }
.navbar-divider {
	width: 1rpx; height: 32rpx;
	background: #dadbde;
	margin: 0 8rpx;
}
.navbar-title { font-size: 34rpx; font-weight: 600; color: #333; }
.navbar-placeholder { width: 120rpx; }
.top-bg {
	position: absolute;
	top: 0; left: 0; right: 0;
	height: 500rpx;
	z-index: 0;
	overflow: hidden;
}
.avatar-section {
	position: relative;
	z-index: 1;
	display: flex;
	flex-direction: column;
	align-items: center;
	padding-top: 40rpx;
	padding-bottom: 30rpx;
}
.avatar-box {
	width: 160rpx;
	height: 160rpx;
	border-radius: 28rpx;
	background: rgba(255,255,255,0.7);
	display: flex;
	align-items: center;
	justify-content: center;
	position: relative;
	overflow: hidden;
}
.avatar-img {
	width: 140rpx;
	height: 140rpx;
	border-radius: 22rpx;
}
.avatar-edit-icon {
	position: absolute;
	right: 8rpx;
	bottom: 8rpx;
	width: 40rpx;
	height: 40rpx;
	border-radius: 50%;
	background: rgba(0,0,0,0.45);
	display: flex;
	align-items: center;
	justify-content: center;
}
.avatar-label {
	margin-top: 14rpx;
	font-size: 26rpx;
	color: #888;
}
.form-card {
	position: relative;
	z-index: 1;
	margin: 0 32rpx;
	background: #fff;
	border-radius: 24rpx;
	padding: 36rpx 32rpx 40rpx;
	box-shadow: 0 4rpx 24rpx rgba(0,0,0,0.04);
}
.form-item {
	margin-bottom: 32rpx;
}
.form-item-last {
	margin-bottom: 0;
}
.form-label {
	display: block;
	font-size: 28rpx;
	color: #333;
	font-weight: 500;
	margin-bottom: 16rpx;
}
.form-input {
	width: 100%;
	height: 84rpx;
	background: #F5F6F8;
	border-radius: 14rpx;
	padding: 0 24rpx;
	font-size: 28rpx;
	color: #333;
	box-sizing: border-box;
}
.form-placeholder {
	color: #C0C4CC;
}
.gender-row {
	display: flex;
	gap: 24rpx;
}
.gender-btn {
	flex: 1;
	height: 84rpx;
	line-height: 84rpx;
	text-align: center;
	background: #F5F6F8;
	border-radius: 14rpx;
	font-size: 28rpx;
	color: #666;
	position: relative;
	overflow: hidden;
}
.gender-active {
	background: #1a1a1a;
	color: #fff;
	font-weight: 500;
}
.gender-active::after {
	content: '';
	position: absolute;
	top: 0;
	left: -100%;
	width: 60%;
	height: 100%;
	background: linear-gradient(90deg, transparent, rgba(255,255,255,0.25), transparent);
	animation: sweep 1s ease-out;
}
@keyframes sweep {
	0% { left: -100%; }
	100% { left: 150%; }
}
.divider {
	height: 1rpx;
	background: #F0F0F0;
	margin: 24rpx 0;
}
.switch-wrap {
	margin-top: 8rpx;
}
.section-title {
	font-size: 28rpx;
	color: #333;
	font-weight: 500;
	margin-top: 24rpx;
	margin-bottom: 16rpx;
}
.section-block {
	margin-bottom: 8rpx;
}
.audio-upload-block {
	background: #F5F6F8;
	border-radius: 14rpx;
	padding: 24rpx;
}
.audio-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.audio-info {
	display: flex;
	align-items: center;
	flex: 1;
}
.audio-name {
	margin-left: 12rpx;
	font-size: 28rpx;
	color: #333;
}
.audio-actions {
	display: flex;
	gap: 16rpx;
}
.audio-btn {
	padding: 8rpx 20rpx;
	border-radius: 8rpx;
	font-size: 24rpx;
}
.audio-btn-replace {
	background: #F5A623;
	color: #fff;
}
.audio-btn-delete {
	background: #f5f5f5;
	color: #999;
	border: 1rpx solid #ddd;
}
.audio-upload-btn {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 40rpx 0;
}
.audio-upload-text {
	margin-top: 12rpx;
	font-size: 26rpx;
	color: #999;
}
.save-btn-wrap {
	position: fixed;
	left: 0; right: 0; bottom: 0;
	padding: 20rpx 48rpx calc(20rpx + env(safe-area-inset-bottom));
	z-index: 10;
}
.save-btn {
	width: 100%;
	height: 96rpx;
	line-height: 96rpx;
	text-align: center;
	background: linear-gradient(90deg, #FFC844, #F5A623);
	border-radius: 16rpx;
	font-size: 34rpx;
	font-weight: 500;
	color: #fff;
	letter-spacing: 4rpx;
}
</style>
��