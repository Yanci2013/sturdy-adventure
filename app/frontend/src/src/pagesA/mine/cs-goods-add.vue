<template>
	<view class="cs-form-page">
		<view class="status-bar" :style="{height: statusBarHeight + 'px'}"></view>
		<view class="nav-bar">
			<view class="nav-back" @click="goBack"><up-icon name="arrow-left" size="20" color="#333"></up-icon></view>
			<text class="nav-title">上传商品</text>
			<view class="nav-right"></view>
		</view>
		<scroll-view scroll-y class="form-body">
			<view class="form-card">
				<view class="form-row">
					<text class="form-label">商品名称</text>
					<input class="form-input" v-model="form.name" placeholder="请输入商品名称" maxlength="40" />
				</view>
				<view class="form-row">
					<text class="form-label">所属分类</text>
					<picker :range="categoryNames" @change="onCategoryChange">
						<view class="form-picker">{{ selectedCategoryName || '请选择分类' }}</view>
					</picker>
				</view>
				<view class="form-row">
					<text class="form-label">价格(元)</text>
					<input class="form-input" type="digit" v-model="form.price" placeholder="请输入价格" />
				</view>
				<view class="form-row">
					<text class="form-label">计价单位</text>
					<input class="form-input" v-model="form.unit" placeholder="如：次 / 局 / 小时" />
				</view>
				<view class="form-row">
					<text class="form-label">封面图URL</text>
					<input class="form-input" v-model="form.logo" placeholder="可选，图片链接" />
				</view>
				<view class="form-row col">
					<text class="form-label">商品简介</text>
					<textarea class="form-textarea" v-model="form.remark" placeholder="请输入商品简介" maxlength="200" />
				</view>
			</view>
			<view class="submit-btn" @click="submit">发布商品</view>
		</scroll-view>
	</view>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const statusBarHeight = ref(44)
try { statusBarHeight.value = uni.getSystemInfoSync().statusBarHeight || 44 } catch (e) {}

const form = ref({ name: '', category_id: 0, price: '', unit: '次', logo: '', remark: '' })
const categories = ref([])
const selectedCategoryName = ref('')

const categoryNames = computed(() => categories.value.map(c => c.name))

const onCategoryChange = (e) => {
	const idx = e.detail.value
	const c = categories.value[idx]
	if (c) {
		form.value.category_id = c.id
		selectedCategoryName.value = c.name
	}
}

const loadCategories = async () => {
	try {
		const r = await fetch('/api/cs/categoryList').then(r => r.json())
		if (r.code == 1) categories.value = r.data || []
	} catch (e) {}
}

const submit = async () => {
	if (!form.value.name.trim()) { uni.showToast({ title: '请填写商品名称', icon: 'none' }); return }
	if (!form.value.category_id) { uni.showToast({ title: '请选择分类', icon: 'none' }); return }
	const price = parseFloat(form.value.price)
	if (!price || price <= 0) { uni.showToast({ title: '请填写正确价格', icon: 'none' }); return }
	uni.showLoading({ title: '发布中', mask: true })
	try {
		const token = uni.getStorageSync('token') || ''
		const r = await fetch('/api/cs/goodsAdd', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'token': token },
			body: JSON.stringify({ ...form.value, price })
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

onMounted(loadCategories)
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
.form-input, .form-picker { flex: 1; font-size: 28rpx; color: #111; }
.form-input { height: 64rpx; }
.form-picker { color: #111; }
.form-textarea { width: 100%; min-height: 160rpx; font-size: 28rpx; color: #111; }
.submit-btn { margin-top: 32rpx; height: 88rpx; line-height: 88rpx; text-align: center; background: #C05B34; color: #fff; border-radius: 12rpx; font-size: 30rpx; }
</style>
