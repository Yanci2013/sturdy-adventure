<template>
	<view class="page">
		<!-- 顶部导航 -->
		<view class="top-bg">
			<view class="navbar" :style="{paddingTop: statusBarHeight + 'px'}">
				<view class="navbar-left" @click="goBack">
					<up-icon name="arrow-left" size="36" color="#ffffff"></up-icon>
				</view>
				<text class="navbar-title">实名认证</text>
				<view class="navbar-right"></view>
			</view>
		</view>

		<view class="content">
			<!-- 功能未开启 -->
			<view v-if="!enabled" class="status-card closed">
				<up-icon name="lock" size="52" color="#bbb"></up-icon>
				<text class="closed-title">实名认证未开启</text>
				<text class="closed-desc">平台暂未开放实名认证，无需提交</text>
			</view>

			<template v-else>
				<!-- 认证状态 -->
				<view class="status-card" :class="'st-' + status">
					<up-icon :name="statusIcon" size="52" :color="statusColor"></up-icon>
					<text class="status-title">{{ statusTitle }}</text>
					<text class="status-desc">{{ statusDesc }}</text>
					<text v-if="status === 3 && reason" class="reason-text">驳回原因：{{ reason }}</text>
				</view>

				<!-- 已认证：展示信息 -->
				<view v-if="status === 2" class="form-card">
					<view class="info-row">
						<text class="info-label">真实姓名</text>
						<text class="info-value">{{ maskName(realName) }}</text>
					</view>
					<view class="info-row">
						<text class="info-label">身份证号</text>
						<text class="info-value">{{ maskIdCard(idCard) }}</text>
					</view>
				</view>

				<!-- 待审核 -->
				<view v-else-if="status === 1" class="form-card">
					<view class="info-row">
						<text class="info-label">真实姓名</text>
						<text class="info-value">{{ maskName(realName) }}</text>
					</view>
					<view class="info-row">
						<text class="info-label">身份证号</text>
						<text class="info-value">{{ maskIdCard(idCard) }}</text>
					</view>
					<view class="uploaded-imgs">
						<image v-if="frontUrl" class="uploaded-img" :src="frontUrl" mode="aspectFit"></image>
						<image v-if="backUrl" class="uploaded-img" :src="backUrl" mode="aspectFit"></image>
					</view>
				</view>

				<!-- 可提交（未提交 / 已驳回） -->
				<template v-else>
					<view class="form-card">
						<view class="field">
							<text class="field-label">真实姓名</text>
							<input class="field-input" v-model="realName" placeholder="请输入真实姓名" maxlength="20" />
						</view>
						<view class="field">
							<text class="field-label">身份证号</text>
							<input class="field-input" v-model="idCard" placeholder="请输入18位身份证号" maxlength="18" />
						</view>
						<text class="field-label upload-label">身份证照片</text>
						<view class="upload-row">
							<view class="upload-box" @click="chooseImg('front')">
								<image v-if="frontUrl" class="upload-img" :src="frontUrl" mode="aspectFill"></image>
								<template v-else>
									<up-icon name="camera-fill" size="40" color="#bbb"></up-icon>
									<text class="upload-tip">身份证正面</text>
								</template>
							</view>
							<view class="upload-box" @click="chooseImg('back')">
								<image v-if="backUrl" class="upload-img" :src="backUrl" mode="aspectFill"></image>
								<template v-else>
									<up-icon name="camera-fill" size="40" color="#bbb"></up-icon>
									<text class="upload-tip">身份证反面</text>
								</template>
							</view>
						</view>
						<text class="form-hint">请确保照片清晰、信息完整，仅用于身份核验</text>
					</view>
					<view class="submit-btn" @click="submit">提交认证</view>
				</template>
			</template>
		</view>
	</view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'

const statusBarHeight = ref(44)
try {
	const sysInfo = uni.getSystemInfoSync()
	statusBarHeight.value = sysInfo.statusBarHeight || 44
} catch (e) {}

const enabled = ref(true)
const status = ref(0) // 0未提交 1待审核 2已认证 3已驳回
const reason = ref('')
const realName = ref('')
const idCard = ref('')
const frontUrl = ref('')
const backUrl = ref('')

const statusTitle = computed(() => ({
	0: '未认证', 1: '审核中', 2: '已认证', 3: '认证被驳回'
}[status.value] || '未认证'))
const statusDesc = computed(() => ({
	0: '请填写信息并上传身份证照片完成认证',
	1: '资料已提交，请耐心等待平台审核',
	2: '您已完成实名认证',
	3: '请核对信息后重新提交'
}[status.value] || ''))
const statusIcon = computed(() => ({ 0: 'info-circle', 1: 'clock', 2: 'checkmark-circle', 3: 'close-circle' }[status.value] || 'info-circle'))
const statusColor = computed(() => ({ 0: '#ff9500', 1: '#ff9500', 2: '#3cc51f', 3: '#e64340' }[status.value] || '#ff9500'))

const maskName = (n) => {
	if (!n) return '-'
	return n.length <= 1 ? n : n[0] + '*'.repeat(n.length - 1)
}
const maskIdCard = (v) => {
	if (!v) return '-'
	return v.length <= 8 ? v : v.slice(0, 4) + '**********' + v.slice(-4)
}

const loadInfo = async () => {
	try {
		const resp = await fetch('/api/realname/info', { headers: { 'token': uni.getStorageSync('token') || '' } })
		const res = await resp.json()
		if (res.code === 1 && res.data) {
			const d = res.data
			enabled.value = !!d.enabled
			status.value = Number(d.status || 0)
			reason.value = d.reason || ''
			realName.value = d.real_name || ''
			idCard.value = d.id_card || ''
			frontUrl.value = d.front || ''
			backUrl.value = d.back || ''
		}
	} catch (e) {
		console.log('加载实名认证信息失败', e)
	}
}

const chooseImg = (side) => {
	uni.chooseImage({
		count: 1,
		success: (r) => {
			const path = r.tempFilePaths[0]
			uni.showLoading({ title: '上传中', mask: true })
			uni.uploadFile({
				url: '/api/upload/image',
				filePath: path,
				name: 'file',
				header: { 'token': uni.getStorageSync('token') || '' },
				success: (res) => {
					uni.hideLoading()
					let d = {}
					try { d = JSON.parse(res.data) } catch (e) {}
					if (d.code === 1 && d.data) {
						if (side === 'front') frontUrl.value = d.data.url
						else backUrl.value = d.data.url
					} else {
						uni.showToast({ title: d.msg || '上传失败', icon: 'none' })
					}
				},
				fail: () => { uni.hideLoading(); uni.showToast({ title: '上传失败', icon: 'none' }) }
			})
		}
	})
}

const submit = async () => {
	if (!realName.value.trim()) return uni.showToast({ title: '请填写真实姓名', icon: 'none' })
	if (!/^\d{17}[\dXx]$/.test(idCard.value.trim())) return uni.showToast({ title: '请填写正确的18位身份证号', icon: 'none' })
	if (!frontUrl.value || !backUrl.value) return uni.showToast({ title: '请上传身份证正反面照片', icon: 'none' })
	uni.showLoading({ title: '提交中', mask: true })
	try {
		const resp = await fetch('/api/realname/submit', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'token': uni.getStorageSync('token') || '' },
			body: JSON.stringify({ real_name: realName.value.trim(), id_card: idCard.value.trim(), front: frontUrl.value, back: backUrl.value })
		})
		const res = await resp.json()
		uni.hideLoading()
		if (res.code === 1) {
			uni.showToast({ title: '提交成功，请等待审核', icon: 'success' })
			loadInfo()
		} else {
			uni.showToast({ title: res.msg || '提交失败', icon: 'none' })
		}
	} catch (e) {
		uni.hideLoading()
		uni.showToast({ title: '提交失败', icon: 'none' })
	}
}

const goBack = () => { uni.navigateBack() }

onShow(() => { loadInfo() })
</script>

<style scoped>
.page { min-height: 100vh; background: #f5f7fa; }
.top-bg { position: relative; background: linear-gradient(180deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); padding-bottom: 30rpx; }
.navbar { position: relative; z-index: 2; display: flex; align-items: center; justify-content: space-between; padding: 20rpx 24rpx; }
.navbar-left, .navbar-right { width: 120rpx; display: flex; align-items: center; }
.navbar-right { justify-content: flex-end; }
.navbar-title { font-size: 34rpx; font-weight: 600; color: #fff; }

.content { padding: 24rpx; }

.status-card { display: flex; flex-direction: column; align-items: center; gap: 12rpx; background: #fff; border-radius: 24rpx; padding: 48rpx 32rpx; box-shadow: 0 2rpx 12rpx rgba(0,0,0,0.04); margin-bottom: 24rpx; }
.status-title { font-size: 34rpx; font-weight: 700; color: #333; }
.status-desc { font-size: 24rpx; color: #999; text-align: center; }
.reason-text { font-size: 24rpx; color: #e64340; margin-top: 6rpx; }
.status-card .closed-title { font-size: 34rpx; font-weight: 700; color: #666; }
.status-card .closed-desc { font-size: 24rpx; color: #999; }

.form-card { background: #fff; border-radius: 24rpx; padding: 24rpx 32rpx; box-shadow: 0 2rpx 12rpx rgba(0,0,0,0.04); }

.field { padding: 24rpx 0; border-bottom: 1rpx solid #f5f5f5; }
.field-label { font-size: 26rpx; color: #666; font-weight: 500; }
.field-input { margin-top: 16rpx; font-size: 30rpx; color: #333; height: 60rpx; }

.info-row { display: flex; justify-content: space-between; align-items: center; padding: 24rpx 0; border-bottom: 1rpx solid #f5f5f5; }
.info-label { font-size: 26rpx; color: #999; }
.info-value { font-size: 28rpx; color: #333; font-weight: 500; }

.upload-label { display: block; margin: 28rpx 0 16rpx; }
.upload-row { display: flex; gap: 24rpx; }
.upload-box { flex: 1; height: 220rpx; border-radius: 16rpx; background: #f8f9fa; border: 2rpx dashed #ddd; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12rpx; overflow: hidden; }
.upload-img { width: 100%; height: 100%; }
.upload-tip { font-size: 24rpx; color: #999; }
.form-hint { display: block; font-size: 22rpx; color: #bbb; margin-top: 20rpx; }
.uploaded-imgs { display: flex; gap: 16rpx; margin-top: 20rpx; }
.uploaded-img { flex: 1; height: 180rpx; border-radius: 12rpx; background: #f5f5f5; }

.submit-btn { margin-top: 40rpx; height: 92rpx; border-radius: 46rpx; background: linear-gradient(135deg, #1a1a2e 0%, #0f3460 100%); color: #fff; font-size: 32rpx; font-weight: 700; display: flex; align-items: center; justify-content: center; }
</style>
