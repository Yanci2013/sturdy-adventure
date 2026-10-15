<template>
	<view class="page">
		<!-- 顶部背景 -->
		<image class="top-bg" src="/static/images/header_background.png" mode="aspectFill"></image>

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
			<text class="navbar-title">客服入驻</text>
			<view class="navbar-placeholder"></view>
		</view>

		<scroll-view scroll-y class="content" :style="{height: scrollHeight + 'px'}">
			<view class="content-inner">
				<!-- 入驻说明 -->
				<view class="card card-glass">
					<text class="card-title">客服入驻说明</text>
					<view class="notice-box">
						<text class="notice-text">申请成为砚世易游v2.0平台客服人员，负责处理用户咨询、订单售后、纠纷仲裁等工作。平台审核通过后即可登录客服端处理工单。</text>
						<text class="notice-tip">请如实填写以下信息，平台将在 1-3 个工作日内审核。</text>
					</view>
				</view>

				<!-- 审核状态提示 -->
				<view class="card card-glass" v-if="statusInfo.show">
					<view :class="['status-tip', statusInfo.cls]">
						<text class="status-tip-title" v-if="statusInfo.title">{{ statusInfo.title }}</text>
						<text class="status-tip-desc">{{ statusInfo.desc }}</text>
					</view>
				</view>

				<!-- 入驻信息填写 -->
				<view class="card card-glass">
					<text class="card-title">入驻信息填写</text>

					<!-- 真实姓名 -->
					<view class="form-row">
						<text class="form-label">真实姓名</text>
						<input class="form-input" v-model="form.name" placeholder="请填写真实姓名" placeholder-class="placeholder" />
					</view>

					<!-- 联系方式 -->
					<view class="form-row">
						<text class="form-label">联系方式</text>
						<input class="form-input" v-model="form.contact" type="number" maxlength="11" placeholder="请填写手机号码" placeholder-class="placeholder" />
					</view>

					<!-- 微信号 -->
					<view class="form-row">
						<text class="form-label">微信号</text>
						<input class="form-input" v-model="form.wechat" placeholder="请填写微信号" placeholder-class="placeholder" />
					</view>

					<!-- 服务经验 -->
					<view class="form-row">
						<text class="form-label">服务经验</text>
						<view class="sex-options">
							<view :class="['sex-tag', form.experience === '不限' ? 'active' : '']" @click="form.experience = '不限'">
								<text>不限</text>
							</view>
							<view :class="['sex-tag', form.experience === '半年以上' ? 'active' : '']" @click="form.experience = '半年以上'">
								<text>半年以上</text>
							</view>
							<view :class="['sex-tag', form.experience === '1年以上' ? 'active' : '']" @click="form.experience = '1年以上'">
								<text>1年以上</text>
							</view>
							<view :class="['sex-tag', form.experience === '3年以上' ? 'active' : '']" @click="form.experience = '3年以上'">
								<text>3年以上</text>
							</view>
						</view>
					</view>

					<!-- 在线时段 -->
					<view class="form-row">
						<text class="form-label">在线时段</text>
						<input class="form-input" v-model="form.online_time" placeholder="如：每天 9:00-22:00" placeholder-class="placeholder" />
					</view>

					<!-- 自我介绍 -->
					<view class="form-row">
						<text class="form-label">自我介绍</text>
						<view class="textarea-box">
							<textarea
								class="form-textarea"
								v-model="form.intro"
								maxlength="500"
								placeholder="请填写自我介绍，不低于10个字"
								placeholder-class="placeholder"
							></textarea>
							<text class="form-counter-inner">{{ (form.intro || '').length }}/500</text>
						</view>
					</view>

					<!-- 其他材料（选填） -->
					<view class="form-row form-row-last">
						<text class="form-label">其他材料（选填）</text>
						<view class="upload-wrap">
							<view class="upload-list">
								<view class="upload-item" v-for="(file, idx) in fileList" :key="idx">
									<image class="upload-preview" :src="file.url" mode="aspectFill" @click="previewImage(idx)"></image>
									<view class="upload-delete" @click.stop="deletePic(idx)">×</view>
								</view>
								<view class="upload-add" v-if="fileList.length < 9" @click="chooseImage">
									<text class="upload-add-icon">+</text>
									<text class="upload-add-text">添加图片</text>
								</view>
							</view>
						</view>
					</view>

					<!-- 入驻协议 -->
					<view class="agreement-row">
						<view class="agreement-box" @click="toggleAgree">
							<view :class="['agree-checkbox', agreed ? 'checked' : '']">
								<text v-if="agreed" class="agree-check-icon">✓</text>
							</view>
							<text class="agree-text">我已阅读并同意</text>
							<text class="agree-link" @click.stop="gotoAgreement">《入驻协议》</text>
						</view>
					</view>
				</view>

				<view class="bottom-spacer"></view>
			</view>
		</scroll-view>

		<!-- 底部提交按钮 -->
		<view class="footer-bar">
			<view :class="['btn-submit', submitDisabled ? 'disabled' : '']" @click="submit">{{ submitBtnText }}</view>
		</view>

		<!-- 入驻协议提醒弹窗 -->
		<view class="agreement-modal-mask" v-if="showAgreeModal" @click="showAgreeModal = false">
			<view class="agreement-modal" @click.stop>
				<text class="agreement-modal-title">入驻协议提醒</text>
				<text class="agreement-modal-desc">为了保障你的权益，请仔细阅读并同意《入驻协议》后继续入驻。</text>
				<view class="agreement-modal-btns">
					<view class="agree-btn-cancel" @click="showAgreeModal = false">不同意</view>
					<view class="agree-btn-confirm" @click="confirmAgree">同意</view>
				</view>
			</view>
		</view>
	</view>
</template>

<script setup>
import { onMounted, ref, reactive, computed } from 'vue';
import { onLoad } from '@dcloudio/uni-app';
import { getUserInfo } from '../../request/api';
import router from '../../utils/router';
import { test } from '../../uni_modules/uview-plus';
import { base_url } from '../../request/env';

const statusBarHeight = ref(44)
const scrollHeight = ref(600)
try {
	const sysInfo = uni.getSystemInfoSync()
	statusBarHeight.value = sysInfo.statusBarHeight || 44
	scrollHeight.value = sysInfo.windowHeight || 600
} catch (e) {}

const fileList = ref([])
const isSubmit = ref(false)
const applyStatus = ref(-1) // -1=未提交 0=审核中 1=已通过 2=已拒绝
const rejectReason = ref('')
const inviteCode = ref('')

const form = reactive({
	name: '',
	contact: '',
	wechat: '',
	experience: '',
	online_time: '',
	intro: ''
})

const agreed = ref(false)
const showAgreeModal = ref(false)

const toggleAgree = () => {
	agreed.value = !agreed.value
}

const confirmAgree = () => {
	agreed.value = true
	showAgreeModal.value = false
}

const gotoAgreement = () => {
	uni.navigateTo({ url: '/pagesA/mine/agreement?name=入驻协议' })
}

const statusInfo = computed(() => {
	if (applyStatus.value === 2) {
		return { show: true, cls: 'status-reject', title: '您的客服入驻申请未通过', desc: rejectReason.value || '' }
	} else if (applyStatus.value === 0) {
		return { show: true, cls: 'status-pending', title: '', desc: '您已提交客服入驻申请，请等待审核' }
	} else if (applyStatus.value === 1) {
		return { show: true, cls: 'status-success', title: '', desc: '您的客服入驻申请已通过，可前往客服端' }
	}
	return { show: false, cls: '', title: '', desc: '' }
})

const submitDisabled = computed(() => {
	return isSubmit.value && applyStatus.value !== 2
})

const submitBtnText = computed(() => {
	if (isSubmit.value && applyStatus.value === 0) return '审核中'
	if (isSubmit.value && applyStatus.value === 1) return '已通过'
	return '提交申请'
})

onMounted(() => {
	if (!uni.getStorageSync('token')) {
		uni.setStorageSync('navtype', 'csruzhu')
		return router.navigateTo({ url: 'pages/login/login' })
	}
	getData()
})

onLoad((opts) => {
	if (opts && opts.invite_code) {
		inviteCode.value = opts.invite_code
	}
})

const getData = async () => {
	try {
		const res = await getUserInfo()
		if (res && res.data && res.data.thug && !Array.isArray(res.data.thug)) {
			// 若打手已是客服且通过审核，展示已通过状态
			const isCs = String(res.data.thug.is_customer_service || '0') === '1'
			if (isCs && String(res.data.thug.sh_status || '0') === '1') {
				isSubmit.value = true
				applyStatus.value = 1
			}
		}
	} catch (e) {}
}

// 选择图片
const chooseImage = () => {
	uni.chooseImage({
		count: 9 - fileList.value.length,
		success: (res) => {
			res.tempFilePaths.forEach(path => {
				fileList.value.push({ url: path, status: 'done' })
			})
		}
	})
}

const deletePic = (idx) => {
	fileList.value.splice(idx, 1)
}

const previewImage = (idx) => {
	uni.previewImage({
		urls: fileList.value.map(f => f.url),
		current: fileList.value[idx].url
	})
}

const submit = async () => {
	if (submitDisabled.value) return

	if (!agreed.value) {
		showAgreeModal.value = true
		return
	}

	if (!form.name.trim()) return uni.showToast({ title: '请填写真实姓名', icon: 'none' })
	if (!form.contact.trim() || form.contact.trim().length !== 11) return uni.showToast({ title: '请填写正确的手机号', icon: 'none' })
	if (!form.experience) return uni.showToast({ title: '请选择服务经验', icon: 'none' })
	if (!form.online_time.trim()) return uni.showToast({ title: '请填写在线时段', icon: 'none' })
	if (form.intro.trim().length < 10) return uni.showToast({ title: '自我介绍不少于10字', icon: 'none' })

	uni.showLoading({ title: '提交中...', mask: true })
	try {
		// 当前无独立客服入驻接口，先模拟提交成功
		await new Promise(r => setTimeout(r, 800))
		isSubmit.value = true
		applyStatus.value = 0
		uni.showToast({ title: '提交成功，等待审核', icon: 'success' })
	} catch (e) {
		uni.showToast({ title: '提交失败', icon: 'none' })
	} finally {
		uni.hideLoading()
	}
}

const goBack = () => {
	const p = getCurrentPages()
	if (p.length > 1) uni.navigateBack({ delta: 1 })
	else uni.reLaunch({ url: '/pages/mine/index' })
}
const goHome = () => uni.reLaunch({ url: '/pages/index/index' })
</script>

<style scoped>
.page {
	min-height: 100vh;
	background: #0a0a0a;
	display: flex;
	flex-direction: column;
	position: relative;
}
.top-bg {
	display: none;
}
.navbar {
	position: relative;
	z-index: 10;
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 24rpx 24rpx 0;
	padding-top: calc(var(--status-bar-height, 0px) + 16rpx);
	background: #0a0a0a;
}
.navbar-capsule {
	display: flex;
	align-items: center;
	border: 1rpx solid rgba(255,255,255,0.15);
	border-radius: 100px;
	padding: 8rpx 16rpx;
	backdrop-filter: blur(10px);
	-webkit-backdrop-filter: blur(10px);
	opacity: 0.95;
	background: rgba(255,255,255,0.06);
}
.navbar-btn { padding: 4rpx 8rpx; }
.navbar-divider {
	width: 1rpx; height: 32rpx;
	background: rgba(255,255,255,0.15);
	margin: 0 8rpx;
}
.navbar-title { font-size: 34rpx; font-weight: 600; color: #fff; }
.navbar-placeholder { width: 120rpx; }
.content {
	flex: 1;
	box-sizing: border-box;
}
.content-inner {
	padding: 28rpx 24rpx;
}
.card {
	background: rgba(255,255,255,0.06);
	border: 1rpx solid rgba(255,255,255,0.1);
	border-radius: 24rpx;
	padding: 32rpx;
	margin-bottom: 24rpx;
}
.card-title {
	display: block;
	font-size: 30rpx;
	font-weight: 700;
	color: #fff;
	margin-bottom: 20rpx;
}
.notice-box {
	display: flex;
	flex-direction: column;
}
.notice-text {
	font-size: 26rpx;
	color: rgba(255,255,255,0.6);
	line-height: 1.7;
}
.notice-tip {
	font-size: 24rpx;
	color: #8b5cf6;
	margin-top: 12rpx;
}
.status-tip {
	border-radius: 12rpx;
	padding: 24rpx;
}
.status-tip-title {
	font-size: 28rpx;
	font-weight: 600;
	color: #fff;
	display: block;
	margin-bottom: 8rpx;
}
.status-tip-desc {
	font-size: 26rpx;
	color: rgba(255,255,255,0.6);
	line-height: 1.6;
}
.status-pending { background: rgba(255,122,69,0.15); }
.status-success { background: rgba(82,196,26,0.12); }
.status-success .status-tip-desc { color: #52C41A; }
.status-reject { background: rgba(255,77,79,0.12); }
.status-reject .status-tip-title { color: #FF4D4F; }
.status-reject .status-tip-desc { color: #FF4D4F; }

.form-row {
	padding: 20rpx 0;
	border-bottom: 1rpx solid rgba(255,255,255,0.08);
}
.form-row-last { border-bottom: none; }
.form-label {
	display: block;
	font-size: 26rpx;
	color: rgba(255,255,255,0.7);
	margin-bottom: 16rpx;
}
.form-tip {
	display: block;
	font-size: 22rpx;
	color: rgba(255,255,255,0.4);
	margin-bottom: 12rpx;
	margin-top: -8rpx;
}
.form-input {
	width: 100%;
	height: 72rpx;
	background: rgba(255,255,255,0.08);
	border-radius: 12rpx;
	padding: 0 20rpx;
	box-sizing: border-box;
	font-size: 28rpx;
	color: #fff;
}
.placeholder { color: rgba(255,255,255,0.3); }
.sex-options {
	display: flex;
	flex-wrap: wrap;
	gap: 16rpx;
}
.sex-tag {
	padding: 12rpx 32rpx;
	border-radius: 100px;
	background: rgba(255,255,255,0.08);
	font-size: 26rpx;
	color: rgba(255,255,255,0.6);
}
.sex-tag.active {
	background: linear-gradient(135deg, #6366f1, #8b5cf6);
	color: #fff;
}
.textarea-box {
	position: relative;
}
.form-textarea {
	width: 100%;
	height: 200rpx;
	background: rgba(255,255,255,0.08);
	border-radius: 12rpx;
	padding: 20rpx;
	box-sizing: border-box;
	font-size: 28rpx;
	color: #fff;
}
.form-counter-inner {
	position: absolute;
	right: 20rpx;
	bottom: 16rpx;
	font-size: 22rpx;
	color: rgba(255,255,255,0.4);
}
.upload-wrap { margin-top: 8rpx; }
.upload-list {
	display: flex;
	flex-wrap: wrap;
	gap: 16rpx;
}
.upload-item {
	position: relative;
	width: 160rpx;
	height: 160rpx;
}
.upload-preview {
	width: 100%;
	height: 100%;
	border-radius: 12rpx;
}
.upload-delete {
	position: absolute;
	top: -8rpx;
	right: -8rpx;
	width: 36rpx;
	height: 36rpx;
	border-radius: 50%;
	background: #FF4D4F;
	color: #fff;
	font-size: 24rpx;
	display: flex;
	align-items: center;
	justify-content: center;
}
.upload-add {
	width: 160rpx;
	height: 160rpx;
	background: rgba(255,255,255,0.08);
	border-radius: 12rpx;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
}
.upload-add-icon {
	font-size: 60rpx;
	color: rgba(255,255,255,0.3);
	line-height: 1;
}
.upload-add-text {
	font-size: 22rpx;
	color: rgba(255,255,255,0.4);
	margin-top: 8rpx;
}
.bottom-spacer { height: 160rpx; }
.footer-bar {
	position: fixed;
	bottom: 0;
	left: 0;
	right: 0;
	padding: 20rpx 24rpx calc(env(safe-area-inset-bottom, 0px) + 20rpx);
	background: #0a0a0a;
	border-top: 1rpx solid rgba(255,255,255,0.08);
	z-index: 100;
}
.btn-submit {
	height: 88rpx;
	border-radius: 44rpx;
	background: linear-gradient(135deg, #6366f1, #8b5cf6);
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 30rpx;
	color: #fff;
	font-weight: 600;
}
.btn-submit.disabled {
	background: rgba(255,255,255,0.1);
	color: rgba(255,255,255,0.4);
}

/* 入驻协议 */
.agreement-row {
	margin-top: 28rpx;
	padding-top: 24rpx;
	border-top: 1rpx solid rgba(255,255,255,0.08);
}
.agreement-box {
	display: flex;
	align-items: center;
	gap: 10rpx;
}
.agree-checkbox {
	width: 32rpx;
	height: 32rpx;
	border: 2rpx solid rgba(255,255,255,0.3);
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	box-sizing: border-box;
	flex-shrink: 0;
}
.agree-checkbox.checked {
	background: linear-gradient(135deg, #6366f1, #8b5cf6);
	border-color: #8b5cf6;
}
.agree-check-icon {
	font-size: 20rpx;
	color: #fff;
	line-height: 1;
}
.agree-text {
	font-size: 24rpx;
	color: rgba(255,255,255,0.5);
}
.agree-link {
	font-size: 24rpx;
	color: #8b5cf6;
}

/* 入驻协议提醒弹窗 */
.agreement-modal-mask {
	position: fixed;
	top: 0; left: 0; right: 0; bottom: 0;
	background: rgba(0,0,0,0.6);
	z-index: 9999;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 0 60rpx;
	box-sizing: border-box;
}
.agreement-modal {
	width: 100%;
	max-width: 560rpx;
	background: #fff;
	border-radius: 24rpx;
	padding: 48rpx 40rpx 32rpx;
	box-sizing: border-box;
}
.agreement-modal-title {
	display: block;
	font-size: 32rpx;
	font-weight: 700;
	color: #000;
	text-align: center;
	margin-bottom: 24rpx;
}
.agreement-modal-desc {
	display: block;
	font-size: 26rpx;
	color: #666;
	line-height: 1.6;
	text-align: center;
	margin-bottom: 36rpx;
}
.agreement-modal-btns {
	display: flex;
	gap: 20rpx;
}
.agree-btn-cancel {
	flex: 1;
	height: 72rpx;
	line-height: 72rpx;
	text-align: center;
	font-size: 28rpx;
	color: #333;
	background: #f5f5f5;
	border-radius: 36rpx;
}
.agree-btn-confirm {
	flex: 1;
	height: 72rpx;
	line-height: 72rpx;
	text-align: center;
	font-size: 28rpx;
	color: #fff;
	font-weight: 600;
	background: linear-gradient(135deg, #6366f1, #8b5cf6);
	border-radius: 36rpx;
}
</style>
