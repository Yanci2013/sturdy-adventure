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
			<text class="navbar-title">入驻申请</text>
			<view class="navbar-placeholder"></view>
		</view>

		<scroll-view scroll-y class="content" :style="{height: scrollHeight + 'px'}">
			<view class="content-inner">
				<!-- 入驻公告说明 -->
				<view class="card card-glass" v-if="wxImage">
					<text class="card-title">{{ ruzhuTitle }}</text>
					<view class="notice-qr">
						<text class="notice-desc">{{ ruzhuDesc }}</text>
						<image :src="wxImage" mode="aspectFit" class="notice-qr-img"
							@longpress="saveImageToAlbum(wxImage)"></image>
					</view>
				</view>

				<!-- 审核状态提-->
				<view class="card card-glass" v-if="statusInfo.show">
					<view :class="['status-tip', statusInfo.cls]">
						<text class="status-tip-title" v-if="statusInfo.title">{{ statusInfo.title }}</text>
						<text class="status-tip-desc">{{ statusInfo.desc }}</text>
					</view>
				</view>

				<!-- 入驻信息填写 -->
				<view class="card card-glass">
					<text class="card-title">入驻信息填写</text>

					<!-- 姓名 -->
					<view class="form-row">
						<text class="form-label">姓名</text>
						<input class="form-input" v-model="form.name" placeholder="请填写姓名" placeholder-class="placeholder" />
					</view>

					<!-- 性别 -->
					<view class="form-row">
						<text class="form-label">性别</text>
						<view class="sex-options">
							<view :class="['sex-tag', form.sex === '男' ? 'active' : '']" @click="form.sex = '男'">
								<text>男</text>
							</view>
							<view :class="['sex-tag', form.sex === '女' ? 'active' : '']" @click="form.sex = '女'">
								<text>女</text>
							</view>
						</view>
					</view>

					<!-- 接单区服 -->
					<view class="form-row">
						<text class="form-label">接单区服</text>
						<text class="form-tip">至少选择一项</text>
						<view class="checkbox-wrap">
							<view
								v-for="(item, index) in serverOptions"
								:key="index"
								:class="['checkbox-tag', form.game_server.indexOf(item) > -1 ? 'active' : '']"
								@click="toggleGameServer(item)"
							>
								<text>{{ item }}</text>
							</view>
						</view>
					</view>

					<!-- 个人简介 -->
					<view class="form-row">
						<text class="form-label">个人简介</text>
						<view class="textarea-box">
							<textarea
								class="form-textarea"
								v-model="form.intro"
								maxlength="500"
								placeholder="不低于3个字"
								placeholder-class="placeholder"
							></textarea>
							<text class="form-counter-inner">{{ (form.intro || '').length }}/500</text>
						</view>
					</view>

					<!-- 其他材料 -->
					<view class="form-row">
						<text class="form-label">其他材料</text>
						<view class="upload-wrap">
							<view class="upload-list">
								<view class="upload-item" v-for="(file, idx) in fileList" :key="idx">
									<image class="upload-preview" :src="file.url" mode="aspectFill" @click="previewImage(idx)"></image>
									<view class="upload-delete" @click.stop="deletePic(idx)">×</view>
									<view class="upload-mask" v-if="file.status === 'uploading'">上传中</view>
									<view class="upload-mask fail" v-if="file.status === 'failed'">失败</view>
								</view>
								<view class="upload-add" v-if="fileList.length < 9" @click="chooseImage">
									<text class="upload-add-icon">+</text>
									<text class="upload-add-text">添加图片</text>
								</view>
							</view>
						</view>
					</view>

					<!-- 邀请码 -->
					<view class="form-row form-row-last">
						<text class="form-label">邀请码（选填）</text>
						<input class="form-input" v-model="form.superior_uid" placeholder="请输入邀请码" placeholder-class="placeholder" />
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
import { category, configure, thugAdd, getUserInfo } from '../../request/api';
import { base_url } from '../../request/env';
import router from '../../utils/router';
import { test } from '../../uni_modules/uview-plus';

const statusBarHeight = ref(44)
const scrollHeight = ref(600)
try {
	const sysInfo = uni.getSystemInfoSync()
	statusBarHeight.value = sysInfo.statusBarHeight || 44
	// scroll-view高度 = 窗口高度 - 底部按钮区域
	scrollHeight.value = sysInfo.windowHeight || 600
} catch (e) {}

const wxImage = ref('')
const ruzhuTitle = ref('入驻公告说明')
const ruzhuDesc = ref('进群考核过了再入驻')
const fileList = ref([])
const serverOptions = ref([])
const isSubmit = ref(false)
const applyStatus = ref(-1) // -1=未提�? 0=审核�? 1=已通过, 2=已拒�?
const rejectReason = ref('')

const form = reactive({
	name: '',
	sex: '',
	game_server: [],
	intro: '',
	superior_uid: ''
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
		return { show: true, cls: 'status-reject', title: '您的入驻申请未通过', desc: rejectReason.value || '' }
	} else if (applyStatus.value === 0) {
		return { show: true, cls: 'status-pending', title: '', desc: '您已提交入驻申请，请等待审核' }
	} else if (applyStatus.value === 1) {
		return { show: true, cls: 'status-success', title: '', desc: '您的入驻申请已通过审核' }
	}
	return { show: false, cls: '', title: '', desc: '' }
})

const submitDisabled = computed(() => {
	return isSubmit.value && applyStatus.value !== 2
})

const submitBtnText = computed(() => {
	if (isSubmit.value && applyStatus.value === 0) return '审核中'
	if (isSubmit.value && applyStatus.value === 1) return '已通过'
	return '提交'
})

onMounted(() => {
	if (!uni.getStorageSync('token')) {
		uni.setStorageSync('navtype', 'ruzhu')
		return router.navigateTo({ url: 'pages/login/login' })
	}
	getData()
	getConfig()
	getCategories()
})

onLoad((opts) => {
	if (opts && opts.invite_code) {
		form.superior_uid = opts.invite_code
	}
})

const getData = async () => {
	try {
		const res = await getUserInfo()
		if (res.data.thug && !Array.isArray(res.data.thug)) {
			const thug = res.data.thug
			if (thug.sh_status == 2) {
				isSubmit.value = true
				applyStatus.value = 2
				rejectReason.value = thug.remarks || ''
			} else if (thug.sh_status == 0) {
				isSubmit.value = true
				applyStatus.value = 0
			} else if (thug.sh_status == 1) {
				isSubmit.value = true
				applyStatus.value = 1
			}
		}
	} catch (e) {
		console.log('获取用户信息失败', e)
	}
}

const getConfig = () => {
	configure().then(res => {
		wxImage.value = res.data.ruzhu_image || res.data.qr_code
		ruzhuTitle.value = res.data.ruzhu_title || '入驻公告说明'
		ruzhuDesc.value = res.data.ruzhu_desc || '进群考核过了再入驻'
	})
}

const getCategories = () => {
	category().then(res => {
		const list = res.data || []
		const opts = []
		const qfList = ['手机端', '电脑端']
		list.forEach(cat => {
			qfList.forEach(qf => {
				opts.push(cat.name + '-' + qf)
			})
		})
		serverOptions.value = opts
	})
}

const toggleGameServer = (name) => {
	const idx = form.game_server.indexOf(name)
	if (idx > -1) {
		form.game_server.splice(idx, 1)
	} else {
		form.game_server.push(name)
	}
}

const chooseImage = () => {
	const remain = 9 - fileList.value.length
	if (remain <= 0) return
	uni.chooseImage({
		count: remain,
		sizeType: ['compressed'],
		sourceType: ['album', 'camera'],
		success: (res) => {
			(res.tempFilePaths || []).forEach(path => {
				fileList.value.push({ url: path, status: 'uploading', message: '待上传' })
			})
			doUpload()
		}
	})
}

const doUpload = async () => {
	for (let i = 0; i < fileList.value.length; i++) {
		if (fileList.value[i].status !== 'success') {
			fileList.value[i].status = 'uploading'
			try {
				const res = await new Promise((resolve, reject) => {
					uni.uploadFile({
						url: base_url + 'common/upload',
						filePath: fileList.value[i].url,
						name: 'file',
						header: { token: uni.getStorageSync('token') },
						success: (r) => resolve(JSON.parse(r.data)),
						fail: reject
					})
				})
				if (res.code == 1) {
					fileList.value[i].status = 'success'
					fileList.value[i].url = res.data.fullurl || res.data.url || res.data
				} else {
					fileList.value[i].status = 'failed'
				}
			} catch (e) {
				fileList.value[i].status = 'failed'
			}
		}
	}
}

const deletePic = (index) => {
	fileList.value.splice(index, 1)
}

const previewImage = (index) => {
	const urls = fileList.value.filter(f => f.status === 'success').map(f => f.url)
	if (urls.length) {
		uni.previewImage({ urls, current: fileList.value[index].url })
	}
}

const saveImageToAlbum = (url) => {
	uni.downloadFile({
		url,
		success: (res) => {
			if (res.statusCode === 200) {
				uni.saveImageToPhotosAlbum({
					filePath: res.tempFilePath,
					success: () => uni.showToast({ title: '保存成功', icon: 'success' }),
					fail: () => uni.showToast({ title: '保存失败', icon: 'none' })
				})
			}
		}
	})
}

const goBack = () => {
	const pages = getCurrentPages()
	if (pages.length > 1) {
		uni.navigateBack({ delta: 1 })
	} else {
		router.reLaunch({ url: 'pages/mine/index' })
	}
}

const goHome = () => {
	router.reLaunch({ url: 'pages/index/index' })
}

const submit = () => {
	if (submitDisabled.value) return

	if (!agreed.value) {
		showAgreeModal.value = true
		return
	}

	if (test.isEmpty(form.name)) {
		return uni.showToast({ title: '请填写姓名', icon: 'none' })
	}
	if (!form.sex) {
		return uni.showToast({ title: '请选择性别', icon: 'none' })
	}
	if (!form.game_server.length) {
		return uni.showToast({ title: '至少选择一项接单区服', icon: 'none' })
	}
	if (!form.intro || form.intro.length < 3) {
		return uni.showToast({ title: '个人简介不低于3个字', icon: 'none' })
	}

	const images = fileList.value.filter(f => f.status === 'success').map(f => f.url)

	thugAdd({
		name: form.name,
		sex: form.sex === '男' ? 1 : 0,
		receiving_orders: form.game_server.join(','),
		synopsis: form.intro,
		material: images.join(','),
		superior_uid: form.superior_uid || ''
	}).then(res => {
		uni.showToast({ title: res.msg, icon: 'none' })
		setTimeout(() => {
			uni.navigateBack({ delta: 1 })
		}, 1000)
	})
}
</script>

<style scoped>
.page {
	min-height: 100vh;
	background: #0a0a0a;
	display: flex;
	flex-direction: column;
	padding-bottom: calc(140rpx + env(safe-area-inset-bottom));
}

/* 导航栏 */
.navbar {
	position: relative;
	z-index: 10;
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 0 24rpx;
	padding-top: calc(var(--status-bar-height, 0px) + 16rpx);
	background: #0a0a0a;
}
.navbar-capsule {
	display: flex;
	align-items: center;
	border: 1rpx solid rgba(255,255,255,0.15);
	border-radius: 100px;
	padding: 8rpx 20rpx;
	backdrop-filter: blur(10px);
	-webkit-backdrop-filter: blur(10px);
	opacity: 0.95;
	background: rgba(255, 255, 255, 0.06);
}
.navbar-btn {
	padding: 4rpx 8rpx;
}
.navbar-divider {
	width: 1rpx;
	height: 32rpx;
	background: rgba(255,255,255,0.15);
	margin: 0 8rpx;
}
.navbar-title {
	font-size: 34rpx;
	font-weight: 600;
	color: #fff;
}
.navbar-placeholder {
	width: 120rpx;
}

/* 顶部背景 */
.top-bg {
	display: none;
}

/* 内容区 */
.content {
	position: relative;
	z-index: 1;
}
.content-inner {
	padding: 0 24rpx;
	padding-top: 20rpx;
}

/* 卡片 */
.card {
	width: 100%;
	box-sizing: border-box;
	margin-bottom: 24rpx;
}
.card-glass {
	background: rgba(255,255,255,0.06);
	backdrop-filter: blur(24rpx);
	-webkit-backdrop-filter: blur(24rpx);
	border: 1rpx solid rgba(255,255,255,0.1);
	border-radius: 24rpx;
	padding: 28rpx;
}
.card-title {
	display: block;
	font-size: 32rpx;
	font-weight: 700;
	color: #fff;
	margin-bottom: 24rpx;
	padding-bottom: 20rpx;
	border-bottom: 1rpx solid rgba(255,255,255,0.08);
}

/* 公告二维码 */
.notice-qr {
	display: flex;
	flex-direction: column;
	align-items: center;
}
.notice-desc {
	font-size: 26rpx;
	color: rgba(255,255,255,0.6);
	margin-bottom: 20rpx;
}
.notice-qr-img {
	width: 320rpx; height: 320rpx;
}

/* 审核状态 */
.status-tip {
	padding: 20rpx 0;
}
.status-tip-title {
	display: block;
	font-size: 28rpx;
	font-weight: 500;
	color: #ff6b6b;
	margin-bottom: 8rpx;
}
.status-tip-desc {
	display: block;
	font-size: 26rpx;
	color: rgba(255,255,255,0.6);
}
.status-reject .status-tip-desc { color: rgba(255,255,255,0.4); }
.status-pending .status-tip-desc { color: #FF7A45; }
.status-success .status-tip-desc { color: #52c41a; }

/* 表单行 */
.form-row {
	margin-bottom: 28rpx;
	padding-bottom: 28rpx;
	border-bottom: 1rpx solid rgba(255,255,255,0.08);
}
.form-row-last {
	margin-bottom: 0;
	padding-bottom: 0;
	border-bottom: none;
}
.form-label {
	display: block;
	font-size: 28rpx;
	color: rgba(255,255,255,0.8);
	font-weight: 500;
	margin-bottom: 16rpx;
}
.form-tip {
	display: block;
	font-size: 24rpx;
	color: rgba(255,255,255,0.4);
	margin-bottom: 12rpx;
}
.form-input {
	width: 100%;
	height: 80rpx;
	padding: 0 24rpx;
	background: rgba(255,255,255,0.08);
	border-radius: 12rpx;
	font-size: 28rpx;
	color: #fff;
	box-sizing: border-box;
}
.placeholder { color: rgba(255,255,255,0.3); }

/* 性别选项 */
.sex-options {
	display: flex;
	gap: 16rpx;
	margin-top: 12rpx;
}
.sex-tag {
	padding: 16rpx 40rpx;
	background: rgba(255,255,255,0.08);
	border-radius: 12rpx;
	font-size: 26rpx;
	color: rgba(255,255,255,0.6);
}
.sex-tag.active {
	background: linear-gradient(135deg, #6366f1, #8b5cf6);
	color: #fff;
}

/* 区服标签 */
.checkbox-wrap {
	display: flex;
	flex-wrap: wrap;
	gap: 16rpx;
	margin-top: 12rpx;
}
.checkbox-tag {
	padding: 16rpx 24rpx;
	background: rgba(255,255,255,0.08);
	border-radius: 12rpx;
	font-size: 26rpx;
	color: rgba(255,255,255,0.6);
}
.checkbox-tag.active {
	background: linear-gradient(135deg, #6366f1, #8b5cf6);
	color: #fff;
}

/* 文本域 */
.textarea-box {
	position: relative;
	margin-top: 8rpx;
	background: rgba(255,255,255,0.08);
	border-radius: 16rpx;
	overflow: hidden;
}
.form-textarea {
	width: 100%;
	min-height: 160rpx;
	padding: 20rpx 24rpx 56rpx;
	background: transparent;
	border-radius: 16rpx;
	font-size: 28rpx;
	color: #fff;
	box-sizing: border-box;
}
.form-counter-inner {
	position: absolute;
	right: 24rpx;
	bottom: 16rpx;
	font-size: 24rpx;
	color: rgba(255,255,255,0.4);
	pointer-events: none;
}

/* 图片上传 */
.upload-wrap {
	margin-top: 12rpx;
}
.upload-list {
	display: flex;
	flex-wrap: wrap;
	gap: 20rpx;
}
.upload-item {
	width: 160rpx; height: 160rpx;
	border-radius: 16rpx;
	overflow: hidden;
	position: relative;
	background: rgba(255,255,255,0.08);
}
.upload-preview {
	width: 100%; height: 100%;
	display: block;
}
.upload-delete {
	position: absolute;
	top: 4rpx; right: 4rpx;
	width: 44rpx; height: 44rpx;
	border-radius: 50%;
	background: rgba(0,0,0,0.5);
	color: #fff;
	font-size: 36rpx;
	line-height: 44rpx;
	text-align: center;
}
.upload-mask {
	position: absolute;
	left: 0; right: 0; bottom: 0;
	height: 48rpx;
	line-height: 48rpx;
	text-align: center;
	font-size: 22rpx;
	color: #fff;
	background: rgba(0,0,0,0.5);
}
.upload-mask.fail {
	background: rgba(220,53,69,0.8);
}
.upload-add {
	width: 160rpx; height: 160rpx;
	border-radius: 16rpx;
	border: 2rpx dashed rgba(255,255,255,0.2);
	background: rgba(255,255,255,0.04);
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 8rpx;
}
.upload-add-icon {
	font-size: 48rpx;
	color: rgba(255,255,255,0.4);
	line-height: 1;
}
.upload-add-text {
	font-size: 24rpx;
	color: rgba(255,255,255,0.4);
}

.bottom-spacer {
	height: 40rpx;
}

/* 底部按钮 */
.footer-bar {
	position: fixed;
	left: 0; right: 0; bottom: 0;
	z-index: 10;
	padding: 20rpx 24rpx;
	padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
	background: #0a0a0a;
	border-top: 1rpx solid rgba(255,255,255,0.08);
}
.btn-submit {
	width: 100%;
	height: 88rpx;
	line-height: 88rpx;
	text-align: center;
	font-size: 30rpx;
	color: #fff;
	background: linear-gradient(135deg, #6366f1, #8b5cf6);
	border-radius: 16rpx;
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
