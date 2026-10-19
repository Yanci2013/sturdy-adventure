<template>
	<view class="cs-form-page">
		<view class="status-bar" :style="{height: statusBarHeight + 'px'}"></view>
		<view class="nav-bar">
			<view class="nav-back" @click="goBack"><up-icon name="arrow-left" size="20" color="#333"></up-icon></view>
			<text class="nav-title">发布公告</text>
			<view class="nav-right"></view>
		</view>
		<scroll-view scroll-y class="form-body">
			<view class="form-card">
				<view class="form-row col">
					<text class="form-label">公告标题</text>
					<input class="form-input" v-model="form.title" placeholder="请输入公告标题" maxlength="50" />
				</view>
				<view class="form-row col">
					<text class="form-label">公告摘要</text>
					<input class="form-input" v-model="form.synopsis" placeholder="一句话摘要（可选）" maxlength="60" />
				</view>
				<view class="form-row col">
					<text class="form-label">公告内容</text>
					<textarea class="form-textarea" v-model="form.content" placeholder="请输入公告详细内容" maxlength="1000" />
				</view>
			</view>
			<view class="submit-btn" @click="submit">发布公告</view>
		</scroll-view>
	</view>
</template>

<script setup>
import { ref } from 'vue'

const statusBarHeight = ref(44)
try { statusBarHeight.value = uni.getSystemInfoSync().statusBarHeight || 44 } catch (e) {}

const form = ref({ title: '', synopsis: '', content: '' })

const submit = async () => {
	if (!form.value.title.trim()) { uni.showToast({ title: '请填写公告标题', icon: 'none' }); return }
	uni.showLoading({ title: '发布中', mask: true })
	try {
		const token = uni.getStorageSync('token') || ''
		const r = await fetch('/api/cs/noticeAdd', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'token': token },
			body: JSON.stringify(form.value)
		}).then(r => r.json())
		uni.showToast({ title: r.msg || (r.code == 1 ? '发布成功' : '发布失败'), icon: 'none' })
		if (r.code == 1) {
			setTimeout(() => uni.navigateBack(), 800)
		}
	} finally {
		uni.hideLoading()
	}
}

const goBack = () => uni.navigateBack()
</script>

<style scoped>
.cs-form-page { min-height: 100vh; background: #f5f7fa; display: flex; flex-direction: column; }
.status-bar { background: #fff; }
.nav-bar { height: 88rpx; padding: 0 24rpx; display: flex; align-items: center; justify-content: space-between; background: #fff; }
.nav-back { width: 56rpx; height: 56rpx; display: flex; align-items: center; justify-content: center; }
.nav-title { font-size: 32rpx; font-weight: 600; color: #111; }
.nav-right { width: 56rpx; }
.form-body { flex: 1; padding: 24rpx; }
.form-card { background: #fff; border-radius: 16rpx; padding: 8rpx 24rpx; }
.form-row { display: flex; align-items: center; padding: 24rpx 0; border-bottom: 1rpx solid #f5f5f5; }
.form-row:last-child { border-bottom: none; }
.form-row.col { flex-direction: column; align-items: stretch; }
.form-label { width: 180rpx; font-size: 28rpx; color: #333; flex-shrink: 0; }
.form-row.col .form-label { margin-bottom: 16rpx; }
.form-input { width: 100%; height: 64rpx; font-size: 28rpx; color: #111; }
.form-textarea { width: 100%; min-height: 240rpx; font-size: 28rpx; color: #111; }
.submit-btn { margin-top: 32rpx; height: 88rpx; line-height: 88rpx; text-align: center; background: #C05B34; color: #fff; border-radius: 12rpx; font-size: 30rpx; }
</style>
