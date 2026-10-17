<template>
	<view class="main-container">
	<z-paging ref="paging" v-model="dataList" @query="queryList" :auto="false" auto-show-back-to-top
		@scrollTopChange="scrollTopChange" :show-empty-view="false" :show-no-more="false" :show-refresher-update-time="false">
		<!-- 顶部区域 -->
		<view class="top-section">
			<view class="top-content" :style="{paddingTop: statusBarHeight + 'px'}">
				<!-- 头像 + 名字 + 等级 + ID -->
				<view class="profile-row">
					<view class="profile-left">
						<view class="avatar-wrap" @click="gotoPage({url:'pagesA/mine/info'})">
							<image class="avatar" :src="dataInfo.avatar || '/static/icon/default_avatar.png'" mode="aspectFill"></image>
							<image v-if="dataInfo.avatar_frame && dataInfo.avatar_frame.image" class="avatar-frame-img" :src="dataInfo.avatar_frame.image" mode="aspectFill"></image>
						</view>
						<view class="name-col">
							<view class="username-wrap">
								<text class="username">{{ dataInfo.name || '设置昵称' }}</text>
								<view class="club-badge" v-if="clubName">{{ clubName }}</view>
								<image class="vip-icon" :src="'/static/ym/v' + levelNum + '.png'" mode="aspectFit"></image>
							</view>
							<text class="uid">用户ID: {{ dataInfo.id || '未登陆' }}</text>
						</view>
					</view>
					<view class="profile-right">
						<!-- 开/关接单 -->
						<view :class="['work-status-btn', jsStatus ? 'work-status-on' : 'work-status-off']" @click="toggleWorkStatus">
							<up-icon :name="jsStatus ? 'pause-circle-fill' : 'play-circle-fill'" size="28" :color="jsStatus ? '#ffffff' : '#FF7A45'"></up-icon>
							<text class="work-status-text">{{ jsStatus ? '接单中' : '开启接单' }}</text>
						</view>
					</view>
				</view>
				<view class="uid-line"></view>
			</view>
		</view>
		
		<view class="page">
		<!-- 接单大厅 -->
		<view class="section servant-orders-section">
			<view class="section-head">
				<text class="section-title">接单大厅</text>
			</view>
			<view class="order-list">
				<view class="order-stat-item" @click="gotoPage({url:'pages/playerOrder/index', data:{status:'2'}})">
					<text class="stat-num">{{ servantData.pending || 0 }}</text>
					<text class="stat-txt">待开始</text>
				</view>
				<view class="order-stat-item" @click="gotoPage({url:'pages/playerOrder/index', data:{status:'3'}})">
					<text class="stat-num">{{ servantData.serving || 0 }}</text>
					<text class="stat-txt">服务中</text>
				</view>
				<view class="order-stat-item" @click="gotoPage({url:'pages/playerOrder/index', data:{status:'4'}})">
					<text class="stat-num">{{ servantData.waitFinish || 0 }}</text>
					<text class="stat-txt">待结单</text>
				</view>
				<view class="order-stat-item">
					<text class="stat-num">{{ servantData.newMsg || 0 }}</text>
					<text class="stat-txt">新消息</text>
				</view>
				<view class="order-stat-item" @click="gotoPage({url:'pages/playerOrder/index', data:{status:'5'}})">
					<text class="stat-num">{{ servantData.finished || 0 }}</text>
					<text class="stat-txt">已结单</text>
				</view>
			</view>
			<view class="order-line"></view>
			<view class="game-list">
				<view class="game-item" v-for="(game, index) in gameList" :key="index" @click="gotoPlayerHome(game)">
					<view class="game-left">
						<view class="game-icon-box"><image :src="game.cover || game.image" mode="aspectFill"></image></view>
						<text>{{ game.name }}</text>
					</view>
					<view class="game-right">
						<text class="game-pending">{{ game.pendingCount || 0 }}单</text>
						<image src="/static/images/right.png" mode="aspectFit"></image>
					</view>
				</view>
			</view>
		</view>
		<!-- 常用功能 -->
		<view class="section quick-section">
			<text class="section-title">常用功能</text>
			<view class="quick-grid">
				<view class="quick-item" @click="gotoPage({url:'pages/playerOrder/index'})">
					<view class="quick-icon-wrap order-icon"><uv-icon name="order" size="32" color="#FF7A45"></uv-icon></view>
					<text class="quick-label">我的订单</text>
				</view>
				<view class="quick-item" @click="gotoPage({url:'pages/playerMain/invited'})">
					<view class="quick-icon-wrap invite-icon"><uv-icon name="gift" size="32" color="#FF7A45"></uv-icon></view>
					<text class="quick-label">受邀订单</text>
				</view>
				<view class="quick-item" @click="gotoPage({url:'pages/playerMain/ti'})">
					<view class="quick-icon-wrap money-icon"><uv-icon name="rmb-circle" size="32" color="#FF7A45"></uv-icon></view>
					<text class="quick-label">佣金提现</text>
				</view>
				<view class="quick-item" @click="gotoPage({url:'pages/playerMain/fine'})">
					<view class="quick-icon-wrap grab-icon"><uv-icon name="info-circle" size="32" color="#FF7A45"></uv-icon></view>
					<text class="quick-label">罚款缴纳</text>
				</view>
				<view class="quick-item" @click="gotoPage({url:'pages/playerMain/center'})">
					<view class="quick-icon-wrap center-icon"><uv-icon name="account" size="32" color="#FF7A45"></uv-icon></view>
					<text class="quick-label">打手中心</text>
				</view>
			</view>
		</view>
		<!-- 更多功能 -->
		<view class="section more-section">
			<text class="section-title more-title">更多功能</text>
			<view class="more-grid">
				<view class="more-item" v-for="(item, index) in moreList" :key="index" @click="handleMoreClick(item)">
					<view class="more-icon-wrap"><uv-icon :name="item.icon" size="26" color="#606266"></uv-icon></view>
					<text class="more-label">{{ item.label }}</text>
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
		<view class="bottom-spacer"></view>
		</view>
	</z-paging>
		<!-- 客服弹窗 -->
	<uv-modal ref="customerServiceModal" title="客服列表" :showConfirmButton="true" confirmText="确认" @confirm="closeCustomerService">
		<view style="width:100%;"><scroll-view scroll-y style="max-height:60vh;">
		<view v-if="cslist.length">
			<view v-for="(item, index) in cslist" :key="item.id" class="cs-item" @click="goCustomerService(item)">
				<image class="cs-avatar" :src="item.image || '/static/icon/default_avatar.png'" mode="aspectFill"></image>
				<view class="cs-info"><text class="cs-name">{{ item.name }}</text></view>
				<view class="cs-arrow"><up-icon name="arrow-right" size="16" color="#999"></up-icon></view>
			</view>
		</view>
		<uv-empty v-else mode="data" text="暂无客服，等待平台添加"></uv-empty>
	</scroll-view></view>
	</uv-modal>
		<!-- 切换身份弹窗 -->
		<view class="identity-popup-mask" v-if="showIdentityPopup" @click="closeIdentityPopup">
			<view class="identity-popup" @click.stop>
				<view class="identity-popup-header">
					<text class="identity-popup-title">切换身份</text>
					<view class="identity-popup-close" @click="closeIdentityPopup"><text class="close-icon">×</text></view>
				</view>
				<view class="identity-options">
					<view v-for="(item, index) in identityList" :key="item.key"
						:class="['identity-option', currentIdentity === item.key ? 'active' : '']"
						@click="selectIdentity(item)">
						<view class="identity-option-left">
							<view :class="['identity-radio', currentIdentity === item.key ? 'checked' : '']">
								<image v-if="currentIdentity === item.key" class="identity-select-icon" src="/static/images/identity_select.png" mode="aspectFit"></image>
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
		<!-- 底部导航栏 -->
		<player-tabbar :current="1"></player-tabbar>
	</view>
</template>

<script setup>
import { ref } from 'vue';
import { onShow } from '@dcloudio/uni-app';
import router from '../../utils/router';
import { thugMsg, thugEdit, category, customerServiceList as getCustomerServiceList, getUserInfo, getThugPermissions, receivingOrders, thugorderList } from '../../request/api';
import { openCustomerServiceChat } from '../../utils/customer-service';

const paging = ref(null)
const dataList = ref([])
const dataInfo = ref({})
const jsStatus = ref(false)
const availableMoney = ref(0)
const frozenMoney = ref(0)
const levelNum = ref(1)
const gameList = ref([])
const servantData = ref({ pending: 0, serving: 0, waitFinish: 0, newMsg: 0, finished: 0 })
const showIdentityPopup = ref(false)
const currentIdentity = ref(1)
const customerServiceModal = ref(null)
const cslist = ref([])
const statusBarHeight = ref(0)
const clubName = ref('')

try {
	const sysInfo = uni.getSystemInfoSync()
	statusBarHeight.value = sysInfo.statusBarHeight || 44
} catch (e) { statusBarHeight.value = 44 }

const identityList = [
	{ key: 0, title: '我是老板', desc: '我要找打手帮我玩' },
	{ key: 1, title: '我是大神', desc: '我就是打手,我来接单子' },
	{ key: 2, title: '我是客服', desc: '进入客服端处理用户消息' }
]
const moreList = [
	{ label: '打手等级', icon: 'level', action: 'level' },
	{ label: '打手排行', icon: 'star', action: 'ranking' },
	{ label: '打手客服', icon: 'kefu-ermai', action: 'kefu' },
	{ label: '客服端', icon: 'chat', action: 'customer-service' },
	{ label: '平台通知', icon: 'volume', action: 'notice' },
	{ label: '充保证金', icon: 'empty-permission', action: 'bond' },
	{ label: '切换身份', icon: 'reload', action: 'switch' },
	{ label: '设置', icon: 'setting', action: 'setting' }
]

onShow(() => { 
	clubName.value = uni.getStorageSync('club_name') || '创世网络'
	loadData()
	loadGameList()
})

const queryList = (pageNo, pageSize) => {
	Promise.all([
		loadData(),
		loadGameList(),
		loadPendingOrders(),
		loadOrderCounts()
	]).then(() => {
		paging.value.complete([])
	}).catch(() => {
		paging.value.complete([])
	})
}

const loadData = async () => {
	try {
		const thugRes = await thugMsg({ thug_id: uni.getStorageSync('id') })
		const userRes = await getUserInfo()

		dataInfo.value = thugRes.data
		jsStatus.value = thugRes.data.jd_status == '1'
		frozenMoney.value = Number(thugRes.data.frozen_money || 0).toFixed(2)
		availableMoney.value = Number(thugRes.data.available_money || 0).toFixed(2)

		const levelName = (userRes.data.user_current_level_info && userRes.data.user_current_level_info.name) || userRes.data.level_name || ''
		const match = levelName.match(/\d+/)
		levelNum.value = match ? match[0] : 1

		loadOrderCounts()
	} catch (e) {
		console.log('加载数据失败', e)
	}
}

const loadOrderCounts = async () => {
	try {
		const pendingRes = await thugorderList({ status: '2', page: 1, limit: 1 })
		const pendingCount = pendingRes.data?.total || pendingRes.data?.data?.length || 0

		const servingRes = await thugorderList({ status: '3', page: 1, limit: 1 })
		const servingCount = servingRes.data?.total || servingRes.data?.data?.length || 0

		const waitFinishRes = await thugorderList({ status: '4', page: 1, limit: 1 })
		const waitFinishCount = waitFinishRes.data?.total || waitFinishRes.data?.data?.length || 0

		const finishedRes = await thugorderList({ status: '5', page: 1, limit: 1 })
		const finishedCount = finishedRes.data?.total || finishedRes.data?.data?.length || 0

		servantData.value = {
			pending: pendingCount,
			serving: servingCount,
			waitFinish: waitFinishCount,
			newMsg: 0,
			finished: finishedCount
		}
	} catch (e) {
		console.log('获取订单数量失败', e)
	}
}

const loadPendingOrders = async () => {
	try {
		for (const game of gameList.value) {
			try {
				const res = await receivingOrders({ type: game.id, category_name: game.name })
				const listData = res.data?.data || res.data || []
				game.pendingCount = listData.length || 0
			} catch (e) {
				console.log(`获取分类 ${game.name} 的待接单数失败`, e)
				game.pendingCount = 0
			}
		}
	} catch (e) {
		console.log('加载待接单数失败', e)
	}
}

const loadGameList = async () => {
	try {
		const categoryRes = await category()
		const allCategories = categoryRes.data || []

		const permissionsRes = await getThugPermissions()
		const permissions = permissionsRes.data || []

		const allowedGameNames = []
		permissions.forEach(p => {
			const gameName = p.split('-')[0]
			if (gameName && !allowedGameNames.includes(gameName)) {
				allowedGameNames.push(gameName)
			}
		})

		if (allowedGameNames.length > 0) {
			gameList.value = allCategories.filter(cat => allowedGameNames.includes(cat.name))
		} else {
			gameList.value = allCategories
		}

		gameList.value.forEach(game => {
			game.pendingCount = 0
		})

		loadPendingOrders()
	} catch (e) {
		console.log('加载游戏列表失败', e)
		category().then(res => {
			gameList.value = res.data || []
			gameList.value.forEach(game => {
				game.pendingCount = 0
			})
		})
	}
}

const toggleWorkStatus = () => {
	const newStatus = !jsStatus.value
	jsStatus.value = newStatus
	thugEdit({ thug_id: uni.getStorageSync('id'), jd_status: newStatus ? '1' : '0' }).then(res => {
		if (res.code == 1) uni.showToast({ title: newStatus ? '上线成功' : '下线成功', icon: 'none' })
	})
}

const gotoPage = (item) => { if (item.url) router.navigateTo({ url: item.url, data: item.data || {} }) }
const gotoPlayerHome = (game) => { router.navigateTo({ url: 'pages/playerHome/index', data: { category_id: game.id, category_name: game.name } }) }
const openIdentityPopup = () => { showIdentityPopup.value = true }
const closeIdentityPopup = () => { showIdentityPopup.value = false }
const selectIdentity = (item) => {
	if (item.key === 0) {
		uni.setStorageSync('type', '用户')
		uni.removeStorageSync('id')
		uni.reLaunch({ url: '/pages/mine/index' })
		return
	}
	if (item.key === 1) {
		// 打手必须入驻审核通过
		if (String(dataInfo.value.sh_status || '') !== '1') {
			uni.showToast({ title: '请先申请入驻', icon: 'none' })
			return
		}
		uni.setStorageSync('type', '打手')
		uni.reLaunch({ url: '/pages/playerMain/index' })
		return
	}
	if (item.key === 2) {
		if (String(dataInfo.value.is_customer_service || '0') !== '1') {
			uni.showToast({ title: '请先申请客服权限', icon: 'none' })
			return
		}
		uni.setStorageSync('type', '客服')
		uni.reLaunch({ url: '/pages/playerMain/customer-service' })
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
		cslist.value = res.data || []
	} catch (e) { cslist.value = [] } finally { uni.hideLoading() }
}
const closeCustomerService = () => { customerServiceModal.value.close() }
const goCustomerService = (item) => {
	openCustomerServiceChat(item)
}
const goBuildSame = () => {
	// 创世网络 - 做系统就找创世网络
	// #ifdef H5
	window.location.href = 'https://qm.qq.com/q/Cno2EcKMZG'
	// #endif
}
const handleMoreClick = (item) => {
	if (item.action === 'center') router.navigateTo({ url: 'pages/playerMain/center' })
	else if (item.action === 'level') uni.navigateTo({ url: '/pagesA/mine/level?identity=thug' })
	else if (item.action === 'ranking') router.navigateTo({ url: 'pages/playRank/index' })
	else if (item.action === 'kefu') openCustomerService()
	else if (item.action === 'customer-service') {
		if (String(dataInfo.value.is_customer_service || '0') !== '1') {
			uni.showToast({ title: '暂未开通客服权限', icon: 'none' })
			return
		}
		router.navigateTo({ url: 'pages/playerMain/customer-service' })
	}
	else if (item.action === 'notice') uni.navigateTo({ url: 'pagesA/mine/notice/index' })
	else if (item.action === 'bond') router.navigateTo({ url: 'pages/playerMain/bound' })
	else if (item.action === 'myorder') router.navigateTo({ url: 'pages/playerOrder/index' })
	else if (item.action === 'switch') openIdentityPopup()
	else if (item.action === 'setting') gotoPage({url:'pagesA/mine/info'})
}

const scrollTopChange = (top) => {}
</script>

<style scoped>
.main-container { min-height: 100vh; background-image: url('/static/images/brand_bg.png'); background-size: cover; background-position: center; background-repeat: no-repeat; background-attachment: fixed; }
.top-section { position: relative; width: 100%; overflow: hidden; background: transparent; }
.top-content { position: relative; z-index: 2; padding: 20rpx 24rpx 0; }
.page { background: transparent; }
.profile-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 24rpx; }
.profile-left { display: flex; align-items: center; gap: 16rpx; flex: 1; min-width: 0; }
.avatar-wrap { width: 100rpx; height: 100rpx; border-radius: 50%; background: #e8e4df; flex-shrink: 0; border: 4rpx solid #fff; box-shadow: 0 4rpx 16rpx rgba(0,0,0,0.15); position: relative; }
.avatar { width: 100%; height: 100%; border-radius: 50%; }
.avatar-frame-img { position: absolute; top: -10rpx; left: -10rpx; width: calc(100% + 20rpx); height: calc(100% + 20rpx); z-index: 20; pointer-events: none; }
.name-col { display: flex; flex-direction: column; align-items: flex-start; gap: 8rpx; flex: 1; min-width: 0; }
.username-wrap { flex: 0 1 auto; min-width: 0; max-width: calc(100% - 90rpx); overflow: hidden; display: flex; align-items: center; gap: 8rpx; }
.username { display: block; color: #090b08; font-size: 32rpx; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.club-badge { display: inline-block; padding: 4rpx 16rpx; background: linear-gradient(135deg, #FF7A45 0%, #FF9D6E 100%); color: #fff; font-size: 20rpx; font-weight: 600; border-radius: 20rpx; flex-shrink: 0; margin-left: 8rpx; }
.vip-icon { width: 68rpx; height: 48rpx; flex-shrink: 0; }
.uid { color: #7b7b7a; font-size: 20rpx; }
.uid-line { width: 670rpx; height: 1rpx; background: rgba(0,0,0,0.08); margin: 16rpx auto 24rpx; }
.profile-right { display: flex; flex-direction: column; align-items: flex-end; gap: 12rpx; flex-shrink: 0; }
.work-status-btn { display: flex; align-items: center; justify-content: center; gap: 6rpx; padding: 2rpx 12rpx; box-sizing: border-box; background: rgba(255,255,255,0.92); border-radius: 36rpx; box-shadow: 0 2rpx 12rpx rgba(0,0,0,0.06); position: relative; overflow: hidden; transition: all 0.4s ease; }
.work-status-btn::before { content: ''; position: absolute; inset: 0; opacity: 0; transition: opacity 0.4s ease; }
.work-status-on { background: linear-gradient(135deg, #FF7A45 0%, #FF9D6E 100%); box-shadow: 0 4rpx 24rpx rgba(255, 122, 69, 0.45), 0 0 40rpx rgba(255, 122, 69, 0.25); animation: pulseGlow 2s ease-in-out infinite; }
.work-status-on .work-status-text { color: #fff; }
.work-status-off { background: linear-gradient(135deg, rgba(255, 122, 69, 0.08) 0%, rgba(255, 122, 69, 0.12) 100%); border: 2rpx solid rgba(255, 122, 69, 0.35); box-shadow: 0 2rpx 16rpx rgba(255, 122, 69, 0.15), inset 0 1rpx 0 rgba(255,255,255,0.6); }
.work-status-off .work-status-text { color: #FF7A45; }
.work-status-text { font-size: 20rpx; font-weight: 600; position: relative; z-index: 1; }
@keyframes pulseGlow { 0%, 100% { box-shadow: 0 4rpx 24rpx rgba(255, 122, 69, 0.45), 0 0 40rpx rgba(255, 122, 69, 0.25); } 50% { box-shadow: 0 6rpx 36rpx rgba(255, 122, 69, 0.65), 0 0 60rpx rgba(255, 122, 69, 0.4); } }
.section { background: #fff; margin: 0 24rpx 24rpx; border-radius: 24rpx; padding: 28rpx 24rpx; box-shadow: 0 2rpx 20rpx rgba(0,0,0,0.04); }
.section-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16rpx; }
.section-title { display: block; font-size: 28rpx; color: #090b08; font-weight: 600; }
.servant-orders-section { margin: 0 auto 24rpx; width: calc(100% - 48rpx); display: flex; flex-direction: column; padding: 32rpx; border-radius: 24rpx; background: #fff; box-shadow: 0 2rpx 20rpx rgba(0,0,0,0.04); box-sizing: border-box; overflow: visible; }
.order-list { display: flex; align-items: center; justify-content: space-between; }
.order-stat-item { display: flex; flex-direction: column; align-items: center; padding-right: 10rpx; }
.stat-num { font-size: 40rpx; color: #090b08; font-weight: 700; }
.stat-txt { font-size: 22rpx; color: #666; }
.order-line { margin-top: 28rpx; width: 100%; border-bottom: 1rpx solid rgba(0,0,0,0.08); }
.game-list .game-item { padding: 28rpx 0; border-bottom: 1rpx solid rgba(0,0,0,0.08); display: flex; align-items: center; justify-content: space-between; }
.game-list .game-item:last-child { padding-bottom: 0; border-bottom: none; }
.game-left { display: flex; align-items: center; }
.game-icon-box { width: 64rpx; height: 64rpx; border-radius: 20rpx; overflow: hidden; margin-right: 20rpx; }
.game-icon-box image { width: 100%; height: 100%; }
.game-left text { font-size: 24rpx; color: #000; }
.game-right { display: flex; align-items: center; }
.game-pending { font-size: 22rpx; color: #FF7A45; font-weight: 600; margin-right: 10rpx; }
.game-right image { width: 32rpx; height: 32rpx; }
.more-section { margin-bottom: 32rpx; padding: 32rpx; padding-right: 0; }
.more-title { display: block; margin-bottom: 24rpx; font-size: 24rpx; color: #090b08; }
.more-grid { display: flex; flex-wrap: wrap; }
.more-item { width: 25%; display: flex; flex-direction: column; align-items: center; gap: 12rpx; box-sizing: border-box; padding: 20rpx 0; }
.more-icon-wrap { width: 36rpx; height: 36rpx; display: flex; align-items: center; justify-content: center; }
.more-label { font-size: 24rpx; color: #666; text-align: center; }
/* 常用功能 */
.quick-section { padding: 32rpx; }
.quick-grid { display: flex; flex-wrap: wrap; gap: 16rpx 0; margin-top: 16rpx; }
.quick-item { width: 25%; display: flex; flex-direction: column; align-items: center; gap: 14rpx; box-sizing: border-box; padding: 16rpx 0; }
.quick-icon-wrap { width: 88rpx; height: 88rpx; border-radius: 24rpx; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #FFF5EF 0%, #FFE8D6 100%); box-shadow: 0 4rpx 16rpx rgba(255, 122, 69, 0.12); }
.quick-label { font-size: 24rpx; color: #090b08; font-weight: 500; text-align: center; }
.footer-brand { display: flex; align-items: center; justify-content: center; padding: 24rpx 24rpx 12rpx; }
.brand-banner { display: flex; align-items: center; gap: 24rpx; width: 100%; max-width: 680rpx; padding: 24rpx 28rpx; background: linear-gradient(135deg, rgba(255, 237, 224, 0.95) 0%, rgba(255, 244, 235, 0.9) 100%); border-radius: 24rpx; box-shadow: 0 6rpx 24rpx rgba(255, 122, 69, 0.15), inset 0 2rpx 6rpx rgba(255,255,255,0.8); border: 1rpx solid rgba(255, 255, 255, 0.8); box-sizing: border-box; }
.brand-mascot { width: 130rpx; height: 130rpx; flex-shrink: 0; border-radius: 20rpx; }
.brand-info { flex: 1; display: flex; flex-direction: column; gap: 8rpx; min-width: 0; }
.brand-title { font-size: 38rpx; font-weight: 800; background: linear-gradient(135deg, #FF7A45 0%, #FF9D6E 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; line-height: 1.2; }
.brand-subtitle { font-size: 24rpx; color: #8a7368; font-weight: 500; line-height: 1.3; }
.build-same-btn { margin-top: 6rpx; align-self: flex-start; padding: 10rpx 28rpx; display: inline-flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #FF7A45 0%, #FF9D6E 100%); border: 1rpx solid rgba(255,255,255,0.8); border-radius: 100rpx; box-shadow: 0 4rpx 14rpx rgba(255, 122, 69, 0.35); font-size: 24rpx; color: #ffffff; font-weight: 700; position: relative; overflow: hidden; box-sizing: border-box; }
.build-same-btn::after { content: ''; position: absolute; top: -55%; left: -80%; width: 48%; height: 210%; background: linear-gradient(100deg, transparent 0%, rgba(255,255,255,0.08) 25%, rgba(255,244,178,0.9) 48%, rgba(255,255,255,0.85) 55%, rgba(255,244,178,0.22) 70%, transparent 100%); transform: rotate(18deg); animation: gold-sparkle 2.8s ease-in-out infinite; }
@keyframes gold-sparkle { 0% { left: -80%; opacity: 0; } 18% { opacity: 1; } 48% { left: 118%; opacity: 1; } 100% { left: 118%; opacity: 0; } }
.bottom-spacer { height: 160rpx; }
.cs-item { display: flex; align-items: center; padding: 20rpx 16rpx; border-bottom: 1rpx solid #f0f0f0; }
.cs-item:last-child { border-bottom: none; }
.cs-avatar { width: 80rpx; height: 80rpx; border-radius: 12rpx; flex-shrink: 0; margin-right: 20rpx; background: #f5f5f5; }
.cs-info { flex: 1; min-width: 0; }
.cs-name { font-size: 28rpx; color: #333; font-weight: 500; }
.cs-arrow { flex-shrink: 0; margin-left: 12rpx; }
.identity-popup-mask { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; display: flex; align-items: center; justify-content: center; padding: 0 32rpx; box-sizing: border-box; }
.identity-popup { width: 100%; max-width: 640rpx; background: #fff; border-radius: 36rpx; padding: 32rpx 24rpx 40rpx; box-sizing: border-box; animation: identity-popup-slide 0.3s ease; }
@keyframes identity-popup-slide { 0% { transform: scale(0.92); opacity: 0; } 100% { transform: translateY(0); } }
.identity-popup-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 40rpx; }
.identity-popup-title { font-size: 36rpx; font-weight: 700; color: #090b08; }
.identity-popup-close { width: 56rpx; height: 56rpx; display: flex; align-items: center; justify-content: center; }
.close-icon { font-size: 48rpx; color: #999; line-height: 1; }
.identity-options { display: flex; flex-direction: column; gap: 24rpx; }
.identity-option { display: flex; align-items: center; padding: 28rpx 24rpx; background: #fff; border-radius: 32rpx; border: 2rpx solid rgba(0,0,0,0.06); position: relative; overflow: hidden; }
.identity-option-bg { position: absolute; left: 0; top: 0; width: 100%; height: 100%; z-index: 0; }
.identity-option.active { border-color: transparent; background: linear-gradient(135deg, #FF7A45, #FFB38A); }
.identity-option-left { display: flex; align-items: center; gap: 20rpx; position: relative; z-index: 1; }
.identity-radio { width: 44rpx; height: 44rpx; border-radius: 50%; border: 3rpx solid #ddd; box-sizing: border-box; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.identity-radio.checked { border-color: #FF7A45; background: #FF7A45; }
.identity-select-icon { width: 24rpx; height: 24rpx; }
.identity-text { display: flex; flex-direction: column; gap: 6rpx; }
.identity-title { font-size: 30rpx; font-weight: 700; color: #090b08; }
.identity-desc { font-size: 24rpx; color: #666; }
</style>
