<template>
	<view class="cs-center-page">
		<view class="status-bar" :style="{height: statusBarHeight + 'px'}"></view>
		<view class="nav-bar">
			<view class="nav-back" @click="goBack"><up-icon name="arrow-left" size="20" color="#333"></up-icon></view>
			<text class="nav-title">客服会话</text>
			<view class="nav-right"><up-icon name="more-dot" size="20" color="#333"></up-icon></view>
		</view>

		<view class="sub-header">
			<view class="sub-close" @click="goBack"><text class="close-icon">×</text></view>
			<text class="sub-title">客服中心</text>
			<view class="sub-manage">
				<up-icon name="grid" size="18" color="#666"></up-icon>
				<text class="manage-text">管理</text>
			</view>
		</view>

		<scroll-view scroll-y class="content">
			<!-- 官方客服卡片 -->
			<view class="cs-card">
				<view class="cs-avatar">客</view>
				<view class="cs-info">
					<text class="cs-name">官方客服</text>
					<text class="cs-online">在线时间: 09:00 - 23:00</text>
				</view>
				<view class="cs-btn" @click="contactOfficial">联系客服</view>
			</view>

			<!-- 常见问题 -->
			<view class="section">
				<text class="section-title">常见问题</text>
				<view class="faq-list">
					<view class="faq-item" v-for="(item, index) in faqList" :key="index" @click="toggleFaq(index)">
						<view class="faq-head">
							<text class="faq-text">{{ item.q }}</text>
							<text :class="['faq-arrow', item.open ? 'open' : '']">›</text>
						</view>
						<view class="faq-body" v-if="item.open">
							<text class="faq-answer">{{ item.a }}</text>
						</view>
					</view>
				</view>
			</view>

			<!-- 更多帮助 -->
			<view class="section">
				<text class="section-title">更多帮助</text>
				<view class="help-list">
					<view class="help-item" @click="goFeedback">
						<view class="help-left">
							<up-icon name="edit-pen" size="18" color="#1890ff"></up-icon>
							<text class="help-text">意见反馈</text>
						</view>
						<up-icon name="arrow-right" size="14" color="#ccc"></up-icon>
					</view>
					<view class="help-item" @click="goComplaint">
						<view class="help-left">
							<up-icon name="star" size="18" color="#ff9500"></up-icon>
							<text class="help-text">投诉建议</text>
						</view>
						<up-icon name="arrow-right" size="14" color="#ccc"></up-icon>
					</view>
				</view>
			</view>
		</scroll-view>
	</view>
</template>

<script setup>
import { ref } from 'vue'
import { customerServiceList as getCustomerServiceList } from '../../request/api'
import { openCustomerServiceChat } from '../../utils/customer-service'

const statusBarHeight = ref(44)
try {
	const sysInfo = uni.getSystemInfoSync()
	statusBarHeight.value = sysInfo.statusBarHeight || 44
} catch (e) {}

const faqList = ref([
	{ q: '如何接入客户咨询?', a: '进入客服端首页，在接入池中点击“接入”按钮即可开始接待用户。', open: false },
	{ q: '提现多久到账?', a: '提现申请提交后，平台审核通过后一般1-3个工作日内到账。', open: false },
	{ q: '订单审核需要多长时间?', a: '订单提交审核后，平台会在24小时内完成审核。', open: false },
	{ q: '如何切换身份?', a: '在“我的”页面点击“切换身份”，选择要切换的身份即可。', open: false }
])

const toggleFaq = (index) => {
	faqList.value[index].open = !faqList.value[index].open
}

const contactOfficial = async () => {
	try {
		uni.showLoading({ title: '加载客服...', mask: true })
		const res = await getCustomerServiceList()
		const list = res.data || []
		if (list.length) {
			openCustomerServiceChat(list[0])
		} else {
			uni.showToast({ title: '暂无客服', icon: 'none' })
		}
	} catch (e) {
		uni.showToast({ title: '加载失败', icon: 'none' })
	} finally {
		uni.hideLoading()
	}
}

const goFeedback = () => {
	uni.showToast({ title: '功能开发中', icon: 'none' })
}

const goComplaint = () => {
	uni.showToast({ title: '功能开发中', icon: 'none' })
}

const goBack = () => {
	uni.navigateBack()
}
</script>

<style scoped>
.cs-center-page { min-height: 100vh; background: #f5f7fa; display: flex; flex-direction: column; }
.status-bar { background: #fff; }
.nav-bar { height: 88rpx; padding: 0 24rpx; display: flex; align-items: center; justify-content: space-between; background: #fff; }
.nav-back { width: 56rpx; height: 56rpx; display: flex; align-items: center; justify-content: center; }
.nav-title { font-size: 32rpx; font-weight: 600; color: #111; }
.nav-right { width: 56rpx; height: 56rpx; display: flex; align-items: center; justify-content: center; }

.sub-header { display: flex; align-items: center; justify-content: space-between; padding: 20rpx 24rpx; background: linear-gradient(180deg, #E6F4FF 0%, #ffffff 100%); }
.sub-close { width: 56rpx; height: 56rpx; display: flex; align-items: center; justify-content: center; }
.close-icon { font-size: 40rpx; color: #333; line-height: 1; }
.sub-title { font-size: 30rpx; font-weight: 600; color: #111; }
.sub-manage { display: flex; align-items: center; gap: 6rpx; }
.manage-text { font-size: 24rpx; color: #666; }

.content { flex: 1; padding: 24rpx; }

.cs-card { background: #fff; border-radius: 16rpx; padding: 32rpx 24rpx; display: flex; align-items: center; gap: 20rpx; box-shadow: 0 2rpx 12rpx rgba(0,0,0,0.04); margin-bottom: 32rpx; }
.cs-avatar { width: 88rpx; height: 88rpx; border-radius: 50%; background: #1890ff; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 36rpx; font-weight: 600; flex-shrink: 0; }
.cs-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 8rpx; }
.cs-name { font-size: 30rpx; color: #111; font-weight: 600; }
.cs-online { font-size: 24rpx; color: #999; }
.cs-btn { padding: 14rpx 32rpx; background: #1890ff; color: #fff; font-size: 26rpx; border-radius: 28rpx; flex-shrink: 0; }

.section { margin-bottom: 32rpx; }
.section-title { display: block; font-size: 28rpx; font-weight: 600; color: #111; margin-bottom: 16rpx; }
.faq-list { background: #fff; border-radius: 16rpx; padding: 0 24rpx; }
.faq-item { padding: 24rpx 0; border-bottom: 1rpx solid #f5f5f5; }
.faq-item:last-child { border-bottom: none; }
.faq-head { display: flex; align-items: center; justify-content: space-between; }
.faq-text { font-size: 28rpx; color: #333; flex: 1; }
.faq-arrow { font-size: 32rpx; color: #ccc; transition: transform 0.2s; }
.faq-arrow.open { transform: rotate(90deg); }
.faq-body { margin-top: 16rpx; padding: 16rpx; background: #f5f7fa; border-radius: 8rpx; }
.faq-answer { font-size: 24rpx; color: #666; line-height: 1.6; }

.help-list { background: #fff; border-radius: 16rpx; padding: 0 24rpx; }
.help-item { display: flex; align-items: center; justify-content: space-between; padding: 28rpx 0; border-bottom: 1rpx solid #f5f5f5; }
.help-item:last-child { border-bottom: none; }
.help-left { display: flex; align-items: center; gap: 16rpx; }
.help-text { font-size: 28rpx; color: #333; }
</style>
