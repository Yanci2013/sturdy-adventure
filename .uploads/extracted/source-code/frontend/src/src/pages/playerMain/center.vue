<template>
	<view class="page">
		<!-- 顶部区域 -->
		<view class="top-section">
			<image class="top-bg" src="/static/images/header_background_panda.png" mode="aspectFill"></image>
			<view class="top-content" :style="{paddingTop: statusBarHeight + 'px'}">
				<!-- 返回按钮 -->
				<view class="back-btn" @click="goBack">
					<up-icon name="arrow-left" size="32" color="#fff"></up-icon>
				</view>
				<text class="page-title">打手中心</text>
				<view class="header-spacer"></view>
			</view>
		</view>
		
		<!-- 我的资金 -->
		<view class="section my-bill">
			<text class="section-title">我的资金</text>
			<view class="bill-list">
				<view class="bill-row">
					<view class="bill-item"><text class="bill-num">{{ dataInfo.bond || 0 }}</text><text class="bill-label">保证金</text></view>
					<view class="bill-item" @click="gotoPage({url:'pages/playerMain/ti'})"><text class="bill-num">{{ availableMoney || 0 }}</text><text class="bill-label">可用佣金</text></view>
					<view class="bill-item"><text class="bill-num">{{ frozenMoney || 0 }}</text><text class="bill-label">冻结佣金</text></view>
				</view>
				<view class="bill-row">
					<view class="bill-item"><text class="bill-num">{{ dataInfo.total_settled || 0 }}</text><text class="bill-label">累计结算</text></view>
					<view class="bill-item"><text class="bill-num">{{ dataInfo.month_settled || 0 }}</text><text class="bill-label">本月结算</text></view>
					<view class="bill-item"><text class="bill-num">{{ dataInfo.last_month_settled || 0 }}</text><text class="bill-label">上月结算</text></view>
				</view>
				<view class="bill-row">
					<view class="bill-item"><text class="bill-num">{{ dataInfo.fine_paid_total || 0 }}</text><text class="bill-label">总已交罚款</text></view>
					<view class="bill-item" @click="gotoFine"><text class="bill-num warn-num">{{ dataInfo.fine_unpaid_total || 0 }}</text><text class="bill-label">待交罚款</text></view>
					<view class="bill-item"></view>
				</view>
			</view>
		</view>
		
		<!-- 打手等级信息 -->
		<view class="section level-info-section">
			<text class="section-title">打手等级信息</text>
			<view class="level-info-list">
				<view class="level-info-row"><text class="level-info-text">我的服务评分：{{ dataInfo.grade || 0 }}</text></view>
				<view class="level-info-row level-info-row-with-icon">
					<text class="level-info-text">打手类型：{{ getThugTypeText() }}</text>
					<image :src="'/static/ym/v' + levelNum + '.png'" mode="aspectFit" class="level-info-image"></image>
				</view>
				<view class="level-info-row level-info-row-with-icon">
					<text class="level-info-text">我的打手等级：</text>
					<image :src="'/static/ym/v' + levelNum + '.png'" mode="aspectFit" class="level-info-image"></image>
				</view>
				<view class="level-info-row"><text class="level-info-text">等级到期时间：{{ dataInfo.level_duration_text || '永久' }}</text></view>
			</view>
		</view>
		
		<!-- 平台公告通知 -->
		<view class="section notice-section" v-if="dataInfo.notice">
			<text class="section-title">平台公告通知</text>
			<view class="notice-content">
				<rich-text :nodes="dataInfo.notice"></rich-text>
			</view>
		</view>
		
		<view style="height: 120rpx;"></view>
	</view>
</template>

<script setup>
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import router from '../../utils/router'
import { thugMsg, getUserInfo } from '../../request/api'

const statusBarHeight = ref(44)
const dataInfo = ref({})
const availableMoney = ref(0)
const frozenMoney = ref(0)
const levelNum = ref(1)

try {
	const sysInfo = uni.getSystemInfoSync()
	statusBarHeight.value = sysInfo.statusBarHeight || 44
} catch (e) {}

onShow(() => {
	loadData()
})

const loadData = async () => {
	try {
		const thugRes = await thugMsg({ thug_id: uni.getStorageSync('id') })
		const userRes = await getUserInfo()
		
		dataInfo.value = thugRes.data
		frozenMoney.value = Number(thugRes.data.frozen_money || 0).toFixed(2)
		availableMoney.value = Number(thugRes.data.available_money || 0).toFixed(2)
		
		const levelName = (userRes.data.user_current_level_info && userRes.data.user_current_level_info.name) || userRes.data.level_name || ''
		const match = levelName.match(/\d+/)
		levelNum.value = match ? match[0] : 1
	} catch (e) {
		console.log('加载数据失败', e)
	}
}

const goBack = () => {
	uni.navigateBack()
}

const gotoPage = (item) => { 
	if (item.url) router.navigateTo({ url: item.url, data: item.data || {} }) 
}

const gotoFine = () => router.navigateTo({ url: 'pages/playerMain/fine' })

const getThugTypeText = () => {
	const typeMap = { 1: '普通打手', 2: '金牌打手', 3: '陪护打手' }
	return typeMap[dataInfo.value.type] || '未知'
}
</script>

<style scoped>
.page { min-height: 100vh; background: linear-gradient(180deg, #FFF5EF 0%, #FFB38A 50%, #ffeef8 100%); overflow-x: hidden; }
.top-section { position: relative; width: 100%; overflow: hidden; height: 280rpx; }
.top-bg { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: none; }
.top-content { position: relative; z-index: 2; padding: 20rpx 24rpx; display: flex; align-items: center; justify-content: space-between; }
.back-btn { width: 60rpx; height: 60rpx; display: flex; align-items: center; justify-content: center; }
.page-title { font-size: 36rpx; font-weight: 600; color: #090b08; }
.header-spacer { width: 60rpx; }
.section { background: rgba(255,255,255,0.45); backdrop-filter: blur(24rpx); -webkit-backdrop-filter: blur(24rpx); border: 1rpx solid rgba(255,255,255,0.6); margin: 24rpx; border-radius: 24rpx; padding: 32rpx; box-shadow: 0 2rpx 20rpx rgba(0,0,0,0.04), inset 0 1rpx rgba(255,255,255,0.8); }
.section-title { display: block; font-size: 28rpx; color: #090b08; font-weight: 600; margin-bottom: 20rpx; }
/* 我的资金 */
.bill-list { display: flex; flex-direction: column; gap: 30rpx; }
.bill-row { display: flex; }
.bill-item { width: 33.33%; display: flex; flex-direction: column; align-items: flex-start; gap: 12rpx; }
.bill-num { color: #090b08; font-size: 32rpx; font-weight: 700; }
.bill-label { font-size: 24rpx; color: #666; }
.warn-num { color: #fa5151; }
/* 打手等级信息 */
.level-info-list { margin-top: 16rpx; padding-left: 20rpx; }
.level-info-row { margin-bottom: 16rpx; }
.level-info-row-with-icon { display: flex; align-items: center; gap: 20rpx; }
.level-info-text { font-size: 26rpx; color: #333; }
.level-info-image { width: 100rpx; height: 50rpx; margin-left: 20rpx; }
/* 公告通知 */
.notice-section { }
.notice-content { margin-top: 20rpx; font-size: 26rpx; color: #333; line-height: 1.6; }
</style>
