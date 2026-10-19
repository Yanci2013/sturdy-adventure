<template>
	<view class="cs-form-page">
		<view class="status-bar" :style="{height: statusBarHeight + 'px'}"></view>
		<view class="nav-bar">
			<view class="nav-back" @click="goBack"><up-icon name="arrow-left" size="20" color="#333"></up-icon></view>
			<text class="nav-title">添加分类</text>
			<view class="nav-right"></view>
		</view>
		<scroll-view scroll-y class="form-body">
			<view class="form-card">
				<view class="form-row">
					<text class="form-label">分类名称</text>
					<input class="form-input" v-model="form.name" placeholder="请输入分类名称" maxlength="20" />
				</view>
				<view class="form-row">
					<text class="form-label">排序</text>
					<input class="form-input" type="number" v-model="form.sort" placeholder="数字越小越靠前" />
				</view>
				<view class="form-row">
					<text class="form-label">分类图标URL</text>
					<input class="form-input" v-model="form.icon" placeholder="可选，图片链接" />
				</view>
			</view>
			<view class="submit-btn" @click="submit">添加分类</view>
		</scroll-view>
	</view>
</template>

<script setup>
import { ref } from 'vue'

const statusBarHeight = ref(44)
try { statusBarHeight.value = uni.getSystemInfoSync().statusBarHeight || 44 } catch (e) {}

const form = ref({ name: '', sort: '0', icon: '' })

const submit = async () => {
	if (!form.value.name.trim()) { uni.showToast({ title: '请填写分类名称', icon: 'none' }); return }
	uni.showLoading({ title: '提交中', mask: true })
	try {
		const token = uni.getStorageSync('token') || ''
		const r = await fetch('/api/cs/categoryAdd', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'token': token },
			body: JSON.stringify(form.value)
		}).then(r => r.json())
		uni.showToast({ title: r.msg || (r.code == 1 ? '添加成功' : '添加失败'), icon: 'none' })
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
.form-label { width: 180rpx; font-size: 28rpx; color: #333; flex-shrink: 0; }
.form-input { flex: 1; height: 64rpx; font-size: 28rpx; color: #111; }
.submit-btn { margin-top: 32rpx; height: 88rpx; line-height: 88rpx; text-align: center; background: #C05B34; color: #fff; border-radius: 12rpx; font-size: 30rpx; }
</style>
