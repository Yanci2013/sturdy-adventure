<template>
	<view class="page">
		<!-- 顶部区域 (top-section) -->
		<view class="top-section">
			<image class="top-bg" src="/static/images/header_background_panda.png" mode="aspectFill"></image>
			<view class="top-content" :style="{paddingTop: statusBarHeight + 'px'}">
				<!-- 右上角操作：设置 + 切换身份 -->
				<view class="top-actions">
					<view class="top-action-btn" @click="goSetting">
						<up-icon name="setting" size="18" color="#fff"></up-icon>
					</view>
					<view class="top-action-btn" @click="switchIdentity">
						<up-icon name="reload" size="18" color="#fff"></up-icon>
					</view>
				</view>
				<!-- 头像 + 名字 + 等级 -->
				<view class="profile-row">
					<!-- 左侧头像 -->
					<view class="profile-left">
						<view class="avatar-wrap" @click="updateUserInfo">
							<image class="avatar" :src="dataInfo.avatar || '/static/icon/default_avatar.png'" mode="aspectFill"></image>
						</view>
					</view>
					<!-- 右侧信息 -->
					<view class="profile-right-info">
						<!-- 用户名 -->
						<view class="username-wrap" @click="updateUserInfo">
							<text class="username">{{ token ? (dataInfo.nickname || '设置昵称') : '立即登录' }}</text>
						</view>
						<!-- 用户ID -->
						<view class="uid-wrap">
							<text class="uid">ID: {{ token ? dataInfo.id : '未登陆' }}</text>
						</view>
						<!-- VIP等级 -->
						<view class="level-wrap" @click="goLevelPage">
							<image :src="'/static/ym/v' + levelNum + '.png'" mode="aspectFit" class="level-image"></image>
						</view>
					</view>
				</view>
		</view>
	</view>

		<!-- 余额 + 客服 双卡片 -->
		<!-- 未成年提示 -->
		<view class="warning-card">
			<view class="warning-tag">
				<up-icon name="warning" size="14" color="#fff"></up-icon>
				<text class="warning-tag-text">提示</text>
			</view>
			<text class="warning-main-text">根据国家法律规定，未成年人禁止下单！</text>
		</view>

		<view class="balance-card">
			<view class="balance-info">
				<text class="balance-label">账户余额</text>
				<text class="balance-amount">¥{{ userBalance }}</text>
			</view>
			<view class="balance-btn" @click="gotoBalance">充值</view>
		</view>

		<!-- 我的订单 section -->
		<view class="section orders-section">
			<view class="section-head">
				<text class="orders-title">我的订单</text>
				<view class="section-link-wrap" @click="toOrder('')">
					<text class="section-link">全部</text>
					<image class="section-link-arrow" src="/static/images/right.png" mode="aspectFit"></image>
				</view>
			</view>
			<view class="order-icons">
				<view class="order-item" v-for="(item, index) in orderStatusList" :key="index" @click="toOrder(item.status)">
					<text class="order-count">{{ getOrderCount(item) }}</text>
					<text class="order-label">{{ item.label }}</text>
				</view>
			</view>
		</view>

		<!-- 会员等级 + 联系客服 双卡片 -->
		<view class="dual-card-row">
			<view class="level-card" @click="goLevelPage">
				<view class="level-card-top">
					<text class="level-card-label">会员等级</text>
					<image class="level-card-arrow" src="/static/images/right.png" mode="aspectFit"></image>
				</view>
				<text class="level-card-num">{{ levelNum }}</text>
			</view>
			<view class="cs-card" @click="openCustomerService">
				<text class="cs-card-sub">实时在线</text>
				<text class="cs-card-main">联系客服</text>
			</view>
		</view>

		<!-- 更多功能 -->
		<view class="section more-section">
			<text class="section-title more-title">常用功能</text>
			<view class="more-grid">
				<view
					class="more-grid-item"
					v-for="(item, index) in moreList"
					:key="index"
					@click="handleMoreClick(item)"
					v-show="!item.show || item.show()"
				>
					<view class="more-grid-icon">
						<uv-icon :name="item.icon" size="28" color="#333"></uv-icon>
					</view>
					<text class="more-grid-label">{{ item.label }}</text>
				</view>
			</view>
		</view>

		<view class="footer-brand">
			<view class="brand-banner">
				<image class="brand-mascot" src="/static/eon.png" mode="aspectFit"></image>
				<view class="brand-info">
					<text class="brand-title">砚世易游v2.0</text>
					<text class="brand-subtitle">创世网络提供技术支持</text>
					<view class="build-same-btn" @click="goBuildSame">做俱乐部就找创世网络</view>
				</view>
			</view>
		</view>

		<!-- 底部占位 -->
		<view style="height: 200rpx;"></view>

		<!-- tabbar -->
		<commonTabbar :current="4"></commonTabbar>

		<!-- 客服列表弹窗 -->
		<uv-modal ref="customerServiceModal" title="客服列表" :showConfirmButton="true" confirmText="确认" @confirm="closeCustomerService">
			<view style="width:100%;">
				<scroll-view scroll-y style="max-height:60vh;">
					<view v-if="customerServiceList.length">
						<view
							v-for="(item, index) in customerServiceList"
							:key="item.id"
							class="cs-item"
							@click="goCustomerService(item)"
						>
							<image class="cs-avatar" :src="item.image || '/static/icon/default_avatar.png'" mode="aspectFill"></image>
							<view class="cs-info">
								<text class="cs-name">{{ item.name }}</text>
							</view>
							<view class="cs-arrow">
								<uv-icon name="arrow-right" size="16" color="#999"></uv-icon>
							</view>
						</view>
					</view>
					<uv-empty v-else mode="data" text="暂无客服，等待平台添加"></uv-empty>
				</scroll-view>
			</view>
		</uv-modal>

		<!-- 切换身份弹窗 -->
		<view class="identity-popup-mask" v-if="showIdentityPopup" @click="closeIdentityPopup">
			<view class="identity-popup" @click.stop>
				<view class="identity-popup-header">
					<text class="identity-popup-title">切换身份</text>
					<view class="identity-popup-close" @click="closeIdentityPopup">
						<text class="close-icon">×</text>
					</view>
				</view>
				<view class="identity-options">
					<view
						v-for="(item, index) in identityList"
						:key="item.key"
						:class="['identity-option', currentIdentity === item.key ? 'active' : '']"
						@click="selectIdentity(item)"
					>
						<view class="identity-option-left">
							<view :class="['identity-radio', currentIdentity === item.key ? 'checked' : '']">
								<image
									v-if="currentIdentity === item.key"
									class="identity-select-icon"
									src="/static/images/identity_select.png"
									mode="aspectFit"
								></image>
							</view>
							<view class="identity-text">
								<text class="identity-title">{{ item.title }}</text>
								<text class="identity-desc">{{ item.desc }}</text>
							</view>
						</view>
					</view>
				</view>
			</view>
		</view>
	</view>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { onShow } from '@dcloudio/uni-app';
import router from '../../utils/router';
import { getUserInfo, customerServiceList as getCustomerServiceList } from '../../request/api';
import { openCustomerServiceChat } from '../../utils/customer-service';
import { test } from '@/uni_modules/uview-plus';
import commonTabbar from '../../compontents/common-tabbar.vue';

const showIdentityPopup = ref(false)
const currentIdentity = ref(0)
const dataInfo = ref({})
const levelNum = ref(1)
const examine = ref('')
const token = ref(uni.getStorageSync('token'))
const statusBarHeight = ref(0)
const customerServiceModal = ref(null)
const customerServiceList = ref([])

const userBalance = computed(() => {
	const balance = dataInfo.value.balance
	const num = Number(balance || 0)
	return Number.isNaN(num) ? '0.00' : num.toFixed(2)
})

// 获取状态栏高度
try {
	const sysInfo = uni.getSystemInfoSync()
	statusBarHeight.value = sysInfo.statusBarHeight || 44
} catch (e) {
	statusBarHeight.value = 44
}

const orderStatusList = [
	{ status: '0', label: '待付款', keys: ['wait_pay', 'waitPay', 'unpaid', 'status_0', 'order_status_0'] },
	{ status: '1', label: '待接单', keys: ['wait_receive', 'waitReceive', 'pending_receive', 'status_1', 'order_status_1'] },
	{ status: '3', label: '服务中', keys: ['serving', 'service', 'status_3', 'order_status_3'] },
	{ status: '4', label: '待结单', keys: ['wait_finish', 'waitFinish', 'pending_finish', 'status_4', 'order_status_4'] },
	{ status: '5', label: '已完成', keys: ['completed', 'complete', 'finish', 'status_5', 'order_status_5'] }
]

const getOrderCount = (item) => {
	const orderCount = dataInfo.value.order_count || dataInfo.value.orderCount || dataInfo.value.order_status_count || {}
	for (const key of item.keys) {
		if (orderCount[key] !== undefined) return orderCount[key]
		if (dataInfo.value[key] !== undefined) return dataInfo.value[key]
	}
	if (orderCount[item.status] !== undefined) return orderCount[item.status]
	return 0
}

const identityList = [
	{ key: 0, title: '我是老板', desc: '我要找打手帮我玩', icon: '/static/images/woman.png' },
	{ key: 1, title: '我是大神', desc: '我就是打手,我来接单子', icon: '/static/images/man.png' },
	{ key: 2, title: '我是客服', desc: '进入客服端处理用户消息', icon: '/static/ym/kf.png' }
]

const moreList = computed(() => {
	const thug = examine.value
	const isThug = dataInfo.value.type == '2' && thug && !Array.isArray(thug)
	const isThugApproved = isThug && String(thug.sh_status || '') === '1'
	const isCs = isThug && String(thug.is_customer_service || '0') === '1'
	const list = [
		{ label: '会员等级', icon: 'level', action: 'level' },
		{ label: '打手入驻', icon: 'integral', action: 'ruzhu' },
		{ label: '客服入驻', icon: 'plus-circle', action: 'csruzhu' },
		{ label: '投诉陪玩', icon: 'warning', action: 'companion' }
	]
	if (isThugApproved) list.push({ label: '打手端', icon: 'account', action: 'player' })
	if (isCs) list.push({ label: '客服端', icon: 'phone', action: 'customer' })
	list.push(
		{ label: '联系客服', icon: 'phone', action: 'service' },
		{ label: '意见反馈', icon: 'edit-pen', action: 'feedback' }
	)
	if (!!token.value) list.push({ label: '退出登陆', icon: 'account', action: 'logout' })
	return list
})

onShow(() => {
	token.value = uni.getStorageSync('token')
	if (!test.isEmpty(token.value)) {
		getData()
	}
})

const getData = async () => {
	try {
		const userRes = await getUserInfo()
		if (userRes.data.type == '2') {
			// 仅记录打手id，不覆盖当前身份type
			uni.setStorageSync('id', userRes.data.thug.id)
		}
		// 老板页面始终保持用户身份
		uni.setStorageSync('type', '用户')
		if (!Array.isArray(userRes.data.thug)) {
			examine.value = userRes.data.thug
		}
		dataInfo.value = userRes.data
		const levelName = userRes.data.level_name || (userRes.data.user_current_level_info && userRes.data.user_current_level_info.name) || ''
		if (levelName) {
			const match = levelName.match(/\d+/)
			levelNum.value = match ? match[0] : 1
		}
	} catch (e) {
		console.log('获取用户信息失败', e)
	}
}

const updateUserInfo = () => {
	if (token.value) {
		router.navigateTo({ url: 'pagesA/mine/info' })
	} else {
		router.navigateTo({ url: 'pages/login/login' })
	}
}

const goLevelPage = () => {
	if (!token.value) return router.navigateTo({ url: 'pages/login/login' })
	const levelId = dataInfo.value.user_current_level_info && dataInfo.value.user_current_level_info.id
	uni.navigateTo({ url: '/pagesA/mine/level?identity=user' })
}

const toOrder = (status) => {
	if (test.isEmpty(token.value)) return router.navigateTo({ url: 'pages/login/login' })
	if (status === '') {
		router.navigateTo({ url: 'pagesA/mine/order/order' })
	} else {
		router.navigateTo({ url: 'pagesA/mine/order/order', data: { tabar: 'hidden', status } })
	}
}

const gotoBalance = () => {
	if (test.isEmpty(token.value)) return router.navigateTo({ url: 'pages/login/login' })
	uni.navigateTo({ url: '/pagesA/mine/balance' })
}

const openIdentityPopup = () => {
	showIdentityPopup.value = true
}

const closeIdentityPopup = () => {
	showIdentityPopup.value = false
}

const selectIdentity = (item) => {
	if (item.key === 0) {
		uni.setStorageSync('type', '用户')
		uni.removeStorageSync('id')
		uni.reLaunch({ url: '/pages/mine/index' })
		return
	}
	if (item.key === 1) {
		if (test.isEmpty(token.value)) return router.navigateTo({ url: 'pages/login/login' })
		// 打手必须先申请入驻且审核通过
		const thug = dataInfo.value.thug
		const isApproved = dataInfo.value.type == '2' && thug && !Array.isArray(thug) && String(thug.sh_status || '') === '1'
		if (isApproved) {
			uni.setStorageSync('type', '打手')
			uni.setStorageSync('id', thug.id)
			uni.reLaunch({ url: '/pages/playerMain/index' })
			return
		}
		uni.showToast({ title: '请先申请入驻', icon: 'none' })
		setTimeout(() => {
			router.navigateTo({ url: 'pagesA/mine/ruzhu' })
		}, 800)
		return
	}
	if (item.key === 2) {
		if (test.isEmpty(token.value)) return router.navigateTo({ url: 'pages/login/login' })
		// 客服必须先入驻打手且开通客服权限
		const thug = dataInfo.value.thug
		const isCs = dataInfo.value.type == '2' && thug && !Array.isArray(thug) && String(thug.is_customer_service || '0') === '1'
		if (isCs) {
			uni.setStorageSync('type', '客服')
			uni.setStorageSync('id', thug.id)
			uni.reLaunch({ url: '/pages/playerMain/customer-service' })
			return
		}
		uni.showToast({ title: '请先申请客服权限', icon: 'none' })
		return
	}
	currentIdentity.value = item.key
	closeIdentityPopup()
}

const openCustomerService = async () => {
	customerServiceModal.value.open()
	try {
		uni.showLoading({ mask: true, title: '加载中' })
		const res = await getCustomerServiceList()
		customerServiceList.value = res.data || []
	} catch (e) {
		console.log('获取客服列表失败', e)
		customerServiceList.value = []
	} finally {
		uni.hideLoading()
	}
}

const closeCustomerService = () => {
	customerServiceModal.value.close()
}

const goCustomerService = (item) => {
	openCustomerServiceChat(item)
}

const goBuildSame = () => {
	// 创世网络 - 做系统就找创世网络
	// #ifdef H5
	window.location.href = 'https://qm.qq.com/q/Cno2EcKMZG'
	// #endif
}

const goSetting = () => {
	updateUserInfo()
}

const switchIdentity = () => {
	openIdentityPopup()
}

const handleMoreClick = (item) => {
	if (item.action === 'level') {
		goLevelPage()
	} else if (item.action === 'ruzhu') {
		if (test.isEmpty(token.value)) return router.navigateTo({ url: 'pages/login/login' })
		const thug = examine.value
		if (thug && !Array.isArray(thug)) {
			if (String(thug.sh_status) === '0') {
				uni.showToast({ title: '已提交打手入驻申请，请等待后台审核', icon: 'none' })
				return
			}
			if (String(thug.sh_status) === '1') {
				uni.showToast({ title: '您已入驻成功，可前往打手端', icon: 'none' })
				return
			}
			// sh_status == 2 被拒绝，允许重新申请
		}
		router.navigateTo({ url: 'pagesA/mine/ruzhu' })
	} else if (item.action === 'csruzhu') {
		// 客服入驻：跳转客服入驻申请页
		if (test.isEmpty(token.value)) return router.navigateTo({ url: 'pages/login/login' })
		const thug = examine.value
		if (thug && !Array.isArray(thug) && String(thug.is_customer_service || '0') === '1' && String(thug.sh_status || '0') === '1') {
			uni.showToast({ title: '您已是平台客服，可前往客服端', icon: 'none' })
			return
		}
		router.navigateTo({ url: 'pagesA/mine/csruzhu' })
	} else if (item.action === 'companion') {
		// 投诉陪玩：跳转投诉表单页
		if (test.isEmpty(token.value)) return router.navigateTo({ url: 'pages/login/login' })
		router.navigateTo({ url: 'pagesA/mine/tousu' })
	} else if (item.action === 'player') {
		uni.setStorageSync('type', '打手')
		uni.reLaunch({ url: '/pages/playerMain/index' })
	} else if (item.action === 'customer') {
		uni.setStorageSync('type', '客服')
		uni.reLaunch({ url: '/pages/playerMain/customer-service' })
	} else if (item.action === 'agreement') {
		router.navigateTo({ url: 'pagesA/mine/agreement', data: { name: '隐私政策' } })
	} else if (item.action === 'about') {
		// 关于平台：跳转到平台介绍页
		router.navigateTo({ url: 'pagesA/mine/agreement', data: { name: '关于平台' } })
	} else if (item.action === 'feedback') {
		// 意见反馈：跳转反馈表单页，提交后接入联系客服
		router.navigateTo({ url: 'pagesA/mine/feedback' })
	} else if (item.action === 'logout') {
		uni.showModal({
			title: '提示',
			content: '确定退出登录吗？',
			showCancel: true,
			success: (res) => {
				if (res.confirm) {
					uni.removeStorageSync('token')
					uni.removeStorageSync('type')
					uni.removeStorageSync('id')
					router.navigateTo({ url: 'pages/login/login' })
				}
			}
		})
	} else if (item.action === 'switch') {
		openIdentityPopup()
	} else if (item.action === 'setting') {
		updateUserInfo()
	} else if (item.action === 'service') {
		openCustomerService()
	}
}
</script>

<style scoped>
.page {
	min-height: 100vh;
	background-image: url('/static/images/brand_bg.png');
	background-size: cover;
	background-position: center;
	background-repeat: no-repeat;
	background-attachment: fixed;
	overflow-x: hidden;
}

/* ===== 顶部区域 ===== */
.top-section {
	position: relative;
	width: 100%;
	overflow: hidden;
}
.top-bg {
	position: absolute;
	top: 0; left: 0;
	width: 100%; height: 100%;
	display: none;
}
.top-content {
	position: relative;
	z-index: 2;
	padding: 120rpx 24rpx 0;
}
.top-actions {
	position: absolute;
	top: 24rpx;
	right: 24rpx;
	display: flex;
	gap: 16rpx;
	z-index: 11;
}
.top-action-btn {
	width: 64rpx;
	height: 64rpx;
	border-radius: 50%;
	background: rgba(255,255,255,0.2);
	display: flex;
	align-items: center;
	justify-content: center;
}
.page-title-wrap {
	position: absolute;
	top: 30rpx;
	left: 0;
	right: 0;
	display: flex;
	justify-content: center;
	z-index: 10;
}
.page-title {
	font-size: 36rpx;
	font-weight: 700;
	color: #ffffff;
}

.profile-top-row {
	display: flex;
	flex-direction: row;
	justify-content: flex-end;
	align-items: center;
	gap: 16rpx;
	width: 100%;
}
.setting-btn-card {
	width: 72rpx;
	height: 72rpx;
	border-radius: 50%;
	background: rgba(255, 255, 255, 0.25);
	backdrop-filter: blur(10rpx);
	box-shadow: 0 4rpx 16rpx rgba(0,0,0,0.1);
	border: 1rpx solid rgba(255,255,255,0.3);
	display: flex;
	align-items: center;
	justify-content: center;
}

.profile-row {
	display: flex;
	align-items: center;
	justify-content: flex-start;
	gap: 24rpx;
	position: relative;
	padding: 20rpx 0;
	margin-top: 70rpx;
}
.profile-left {
	flex-shrink: 0;
}
.avatar-wrap {
	width: 140rpx; height: 140rpx;
	border-radius: 50%;
	overflow: hidden;
	background: #e8e4df;
	border: 4rpx solid #fff;
	box-shadow: 0 4rpx 16rpx rgba(0,0,0,0.15);
}
.avatar {
	width: 100%; height: 100%;
}
.profile-right-info {
	flex: 1;
	min-width: 0;
	display: flex;
	flex-direction: column;
	justify-content: center;
	align-items: flex-start;
	gap: 8rpx;
	padding-left: 0;
	margin-left: 0;
	width: 100%;
}
.username-wrap {
	overflow: hidden;
	width: 100%;
	text-align: left;
	display: flex;
	justify-content: flex-start;
	padding-left: 0;
	margin-left: 0;
}
.username {
	display: block;
	color: #000000;
	font-size: 36rpx;
	font-weight: 400;
	line-height: 1.3;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	text-align: left;
}
.uid-wrap {
	display: flex;
	align-items: center;
	text-align: left;
	justify-content: flex-start;
	width: 100%;
	padding-left: 0;
	margin-left: 0;
}
.uid {
	color: #999;
	font-size: 24rpx;
	font-weight: 500;
	text-align: left;
}
.level-wrap {
	display: flex;
	align-items: center;
	justify-content: flex-start;
	margin-top: 4rpx;
	text-align: left;
	width: 100%;
	padding-left: 0;
	margin-left: 0;
}
.level-image {
	width: 80rpx;
	height: 35rpx;
}
.level-cs-row {
	display: flex;
	align-items: center;
	gap: 16rpx;
	margin-top: 8rpx;
}
.cs-contact-btn {
	display: flex;
	align-items: center;
	gap: 6rpx;
	padding: 8rpx 20rpx;
	background: linear-gradient(135deg, #FF7A45 0%, #FF9D6E 100%);
	border-radius: 100rpx;
	box-shadow: 0 4rpx 12rpx rgba(255,122,69,0.3);
}
.cs-contact-btn text {
	color: #fff;
	font-size: 22rpx;
	font-weight: 600;
}
.switch-btn {
	flex-shrink: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 6rpx;
	padding: 12rpx 24rpx;
	background: rgba(255,255,255,0.4);
	backdrop-filter: blur(12rpx);
	-webkit-backdrop-filter: blur(12rpx);
	border: 2rpx solid rgba(255,255,255,0.6);
	border-radius: 50rpx;
	box-shadow: 0 2rpx 12rpx rgba(0,0,0,0.1);
}
.switch-icon {
	width: 28rpx; height: 28rpx;
}
.switch-text {
	font-size: 24rpx;
	color: #333;
	font-weight: 500;
}

/* ===== section 通用 ===== */
.section {
	background: rgba(255,255,255,0.45);
	backdrop-filter: blur(24rpx);
	-webkit-backdrop-filter: blur(24rpx);
	border: 1rpx solid rgba(255,255,255,0.6);
	margin: 0 24rpx 24rpx;
	border-radius: 24rpx;
	padding: 28rpx 24rpx;
	box-shadow: 0 2rpx 20rpx rgba(0,0,0,0.04), inset 0 1rpx rgba(255,255,255,0.8);
}

/* ===== 未成年提示 ===== */
.warning-card {
	margin: 0 auto 24rpx;
	width: 93%;
	display: flex;
	align-items: center;
	gap: 16rpx;
	padding: 12rpx 24rpx;
	background: #ffffff;
	border-radius: 16rpx;
	border: 1rpx solid #e8e8e8;
	box-shadow: 0 4rpx 20rpx rgba(0,0,0,0.08);
	box-sizing: border-box;
}
.warning-tag {
	display: flex;
	align-items: center;
	gap: 4rpx;
	padding: 4rpx 12rpx;
	background: #ff6b6b;
	border-radius: 6rpx;
	flex-shrink: 0;
}
.warning-tag-text {
	font-size: 20rpx;
	color: #ffffff;
	font-weight: 500;
}
.warning-main-text {
	font-size: 22rpx;
	color: #333;
	font-weight: 400;
	flex: 1;
}

.balance-card {
	margin: 0 auto 24rpx;
	width: 93%;
	min-height: 150rpx;
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 28rpx 32rpx;
	background: #ffffff;
	border-radius: 16rpx;
	border: 1rpx solid #f1f1f1;
	box-shadow: 0 8rpx 28rpx rgba(0,0,0,0.08);
	box-sizing: border-box;
}
.balance-info {
	display: flex;
	flex-direction: column;
	gap: 16rpx;
}
.balance-label {
	font-size: 26rpx;
	color: #999999;
	font-weight: 400;
}
.balance-amount {
	font-size: 44rpx;
	line-height: 1;
	color: #101010;
	font-weight: 700;
}
.balance-btn {
	min-width: 116rpx;
	height: 64rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 0 26rpx;
	background: linear-gradient(135deg, #FFB38A 0%, #FF7A45 100%);
	border-radius: 24rpx;
	font-size: 26rpx;
	color: #ffffff;
	font-weight: 700;
	box-sizing: border-box;
}

/* ===== 会员等级 + 联系客服 双卡片 ===== */
.dual-card-row {
	display: flex;
	gap: 20rpx;
	margin: 0 auto 24rpx;
	width: 93%;
}
.level-card {
	flex: 1;
	background: #1a1a1a;
	border-radius: 28rpx;
	padding: 32rpx 28rpx;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	min-height: 200rpx;
	box-shadow: 0 8rpx 24rpx rgba(0,0,0,0.15);
}
.level-card-top {
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.level-card-label {
	color: #fff;
	font-size: 28rpx;
	font-weight: 500;
}
.level-card-arrow {
	width: 24rpx;
	height: 24rpx;
	opacity: 0.6;
}
.level-card-num {
	color: #fff;
	font-size: 72rpx;
	font-weight: 800;
	line-height: 1;
	margin-top: 24rpx;
}
.cs-card {
	flex: 1;
	background: linear-gradient(135deg, #FFC107 0%, #FF9800 100%);
	border-radius: 28rpx;
	padding: 32rpx 28rpx;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	align-items: center;
	min-height: 200rpx;
	box-shadow: 0 8rpx 24rpx rgba(255,152,0,0.3);
}
.cs-card-sub {
	color: rgba(0,0,0,0.6);
	font-size: 26rpx;
	font-weight: 500;
	margin-top: 8rpx;
}
.cs-card-main {
	color: #3a2000;
	font-size: 48rpx;
	font-weight: 800;
	line-height: 1.1;
	margin-top: 20rpx;
}

/* ===== 我的订单 ===== */
.orders-section {
	margin: 0 auto 24rpx;
	width: 93%;
	display: flex;
	flex-direction: column;
	align-items: stretch;
	padding: 26rpx 28rpx 34rpx;
	border-radius: 16rpx;
	border: 1rpx solid #f1f1f1;
	background: #ffffff;
	box-shadow: 0 8rpx 28rpx rgba(0,0,0,0.08);
	box-sizing: border-box;
	overflow: visible;
}
.section-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 24rpx;
	width: 100%;
}
.orders-title {
	font-size: 28rpx;
	font-weight: 600;
	color: #333;
}
.section-link-wrap {
	display: flex;
	align-items: center;
	gap: 4rpx;
	flex-shrink: 0;
}
.section-link {
	color: #999999;
	font-size: 26rpx;
	font-weight: 400;
	white-space: nowrap;
}
.section-link-arrow {
	width: 24rpx; height: 24rpx;
	opacity: 0.45;
	flex-shrink: 0;
}
.order-icons {
	display: flex;
	justify-content: flex-start;
	flex-wrap: wrap;
	row-gap: 34rpx;
	width: 100%;
	padding-top: 6rpx;
}
.order-item {
	width: 33.333%;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 12rpx;
}
.order-count {
	font-size: 40rpx;
	line-height: 1;
	color: #333333;
	font-weight: 700;
}
.order-label {
	font-size: 24rpx;
	color: #666666;
	text-align: center;
	font-weight: 400;
}

/* ===== 余额 + 客服 ===== */
.cards-row {
	display: flex;
	gap: 20rpx;
	margin: 40rpx 24rpx 24rpx;
}
.card-diamond {
	flex: 1;
	height: 120rpx;
	background: #ffffff;
	backdrop-filter: blur(10rpx);
	border-radius: 32rpx;
	padding: 14rpx 30rpx;
	display: flex;
	align-items: center;
	justify-content: space-between;
	box-shadow: 0 4rpx 20rpx rgba(0,0,0,0.08);
	border: 1rpx solid #eee;
	position: relative;
	overflow: hidden;
}
.card-diamond-left {
	display: flex;
	flex-direction: column;
	gap: 12rpx;
}
.card-diamond-title-row {
	display: flex;
	align-items: center;
	gap: 8rpx;
}
.card-diamond-title {
	font-size: 20rpx;
	color: #4a5568;
	font-weight: 500;
}
.card-diamond-arrow {
	width: 24rpx; height: 24rpx;
	opacity: 0.7;
}
.card-diamond-num {
	font-size: 40rpx;
	font-weight: 700;
	color: #2d3748;
}
.card-service {
	flex: 1;
	height: 120rpx;
	background: #ffffff;
	backdrop-filter: blur(10rpx);
	border-radius: 32rpx;
	padding: 14rpx 30rpx;
	display: flex;
	align-items: center;
	justify-content: space-between;
	box-shadow: 0 4rpx 20rpx rgba(0,0,0,0.08);
	border: 1rpx solid #eee;
}
.card-service-left {
	display: flex;
	flex-direction: column;
	gap: 8rpx;
}
.card-service-online {
	font-size: 22rpx;
	color: #4a5568;
	font-weight: 500;
}
.card-service-cta {
	font-size: 32rpx;
	font-weight: 700;
	color: #2d3748;
}

/* ===== 更多功能 ===== */
.more-section {
	margin-bottom: 32rpx;
	padding: 24rpx;
	background: #ffffff;
	border-radius: 20rpx;
	border: 1rpx solid #e8e8e8;
	box-shadow: 0 4rpx 20rpx rgba(0,0,0,0.08);
	width: 93%;
	margin-left: auto;
	margin-right: auto;
	box-sizing: border-box;
}
.more-title {
	display: block;
	margin-bottom: 24rpx;
	font-size: 28rpx;
	font-weight: 600;
	color: #333;
}
.more-grid {
	display: flex;
	flex-wrap: wrap;
	gap: 24rpx;
}
.more-grid-item {
	width: calc(25% - 18rpx);
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 12rpx;
}
.more-grid-icon {
	width: 80rpx;
	height: 80rpx;
	display: flex;
	align-items: center;
	justify-content: center;
}
.more-grid-label {
	font-size: 24rpx;
	color: #333;
	font-weight: 500;
	text-align: center;
	white-space: nowrap;
}

/* ===== 底部品牌 ===== */
.footer-brand {
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 24rpx 24rpx 12rpx;
}
.brand-banner {
	display: flex;
	align-items: center;
	gap: 24rpx;
	width: 100%;
	max-width: 680rpx;
	padding: 24rpx 28rpx;
	background: linear-gradient(135deg, rgba(255, 237, 224, 0.95) 0%, rgba(255, 244, 235, 0.9) 100%);
	border-radius: 24rpx;
	box-shadow: 0 6rpx 24rpx rgba(255, 122, 69, 0.15), inset 0 2rpx 6rpx rgba(255,255,255,0.8);
	border: 1rpx solid rgba(255, 255, 255, 0.8);
	box-sizing: border-box;
}
.brand-mascot {
	width: 130rpx;
	height: 130rpx;
	flex-shrink: 0;
	border-radius: 20rpx;
}
.brand-info {
	flex: 1;
	display: flex;
	flex-direction: column;
	gap: 8rpx;
	min-width: 0;
}
.brand-title {
	font-size: 38rpx;
	font-weight: 800;
	background: linear-gradient(135deg, #FF7A45 0%, #FF9D6E 100%);
	-webkit-background-clip: text;
	-webkit-text-fill-color: transparent;
	background-clip: text;
	line-height: 1.2;
}
.brand-subtitle {
	font-size: 24rpx;
	color: #8a7368;
	font-weight: 500;
	line-height: 1.3;
}
.build-same-btn {
	margin-top: 6rpx;
	align-self: flex-start;
	padding: 10rpx 28rpx;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	background: linear-gradient(135deg, #FF7A45 0%, #FF9D6E 100%);
	border: 1rpx solid rgba(255,255,255,0.8);
	border-radius: 100rpx;
	box-shadow: 0 4rpx 14rpx rgba(255, 122, 69, 0.35);
	font-size: 24rpx;
	color: #ffffff;
	font-weight: 700;
	position: relative;
	overflow: hidden;
	box-sizing: border-box;
}
.build-same-btn::after {
	content: '';
	position: absolute;
	top: -55%;
	left: -80%;
	width: 48%;
	height: 210%;
	background: linear-gradient(100deg, transparent 0%, rgba(255,255,255,0.08) 25%, rgba(255,244,178,0.9) 48%, rgba(255,255,255,0.85) 55%, rgba(255,244,178,0.22) 70%, transparent 100%);
	transform: rotate(18deg);
	animation: gold-sparkle 2.8s ease-in-out infinite;
}
@keyframes gold-sparkle {
	0% { left: -80%; opacity: 0; }
	18% { opacity: 1; }
	48% { left: 118%; opacity: 1; }
	100% { left: 118%; opacity: 0; }
}

/* ===== 客服列表弹窗 ===== */
.cs-item {
	display: flex;
	align-items: center;
	padding: 20rpx 16rpx;
	border-bottom: 1rpx solid #f0f0f0;
}
.cs-item:last-child {
	border-bottom: none;
}
.cs-avatar {
	width: 80rpx;
	height: 80rpx;
	border-radius: 12rpx;
	flex-shrink: 0;
	margin-right: 20rpx;
	background: #f5f5f5;
}
.cs-info {
	flex: 1;
	min-width: 0;
}
.cs-name {
	font-size: 28rpx;
	color: #333;
	font-weight: 500;
}
.cs-arrow {
	flex-shrink: 0;
	margin-left: 12rpx;
}

/* ===== 切换身份弹窗 ===== */
.identity-popup-mask {
	position: fixed;
	top: 0; left: 0; right: 0; bottom: 0;
	background: rgba(0,0,0,0.5);
	z-index: 999;
	display: flex;
	align-items: center;
	justify-content: center;
	animation: identity-mask-fade 0.25s ease;
	padding: 0 32rpx;
	box-sizing: border-box;
}
@keyframes identity-mask-fade {
	0% { opacity: 0; }
	100% { opacity: 1; }
}
.identity-popup {
	width: 100%;
	max-width: 640rpx;
	background: #fff;
	border-radius: 36rpx;
	padding: 32rpx 24rpx 40rpx;
	animation: identity-popup-slide 0.3s ease;
	box-sizing: border-box;
}
@keyframes identity-popup-slide {
	0% { transform: scale(0.92); opacity: 0; }
	100% { transform: translateY(0); }
}
.identity-popup-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 40rpx;
}
.identity-popup-title {
	font-size: 36rpx;
	font-weight: 700;
	color: #090b08;
}
.identity-popup-close {
	width: 56rpx; height: 56rpx;
	display: flex;
	align-items: center;
	justify-content: center;
}
.close-icon {
	font-size: 48rpx;
	color: #999;
	line-height: 1;
}
.identity-options {
	display: flex;
	flex-direction: column;
	gap: 24rpx;
}
.identity-option {
	display: flex;
	align-items: center;
	justify-content: flex-start;
	padding: 28rpx 24rpx;
	background: #fff;
	border-radius: 32rpx;
	border: 2rpx solid rgba(0,0,0,0.06);
	transition: all 0.2s;
	position: relative;
	overflow: hidden;
}
.identity-option-bg {
	position: absolute;
	left: 0; top: 0;
	width: 100%; height: 100%;
	z-index: 0;
}
.identity-option.active {
	border-color: transparent;
	background: linear-gradient(135deg, #FF7A45, #FFB38A);
}
.identity-option-left {
	display: flex;
	align-items: center;
	gap: 20rpx;
	position: relative;
	z-index: 1;
}
.identity-radio {
	width: 44rpx; height: 44rpx;
	border-radius: 50%;
	border: 3rpx solid #ddd;
	box-sizing: border-box;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
}
.identity-radio.checked {
	border-color: #FF7A45;
	background: #FF7A45;
}
.identity-select-icon {
	width: 24rpx; height: 24rpx;
	/* 图标是白色勾 */
}
.identity-text {
	display: flex;
	flex-direction: column;
	gap: 6rpx;
}
.identity-title {
	font-size: 30rpx;
	font-weight: 700;
	color: #090b08;
}
.identity-desc {
	font-size: 24rpx;
	color: #666;
}

</style>
