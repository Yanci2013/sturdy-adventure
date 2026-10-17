<template>
	<view class="cs-page">
		<!-- 顶部状态栏占位 -->
		<view class="status-bar" :style="{height: statusBarHeight + 'px'}"></view>

		<!-- 顶部导航 -->
		<view class="nav-bar">
			<view class="nav-back" @click="goBack">
				<text class="close-icon">×</text>
			</view>
			<text class="nav-title">{{ navTitle }}</text>
			<view class="nav-right">
				<up-icon name="more-dot-fill" size="20" color="#fff"></up-icon>
			</view>
		</view>

		<!-- 内容区 -->
		<view class="cs-content">
			<!-- 接入池 / 客服会话 -->
			<view v-if="currentTab === 0" class="tab-pane">
				<view class="sub-header">
					<text class="sub-title">客服会话</text>
					<view class="sub-manage">
						<up-icon name="grid" size="18" color="#666"></up-icon>
						<text class="manage-text">管理</text>
					</view>
				</view>

				<!-- 池子选择卡片 -->
				<view class="pool-cards">
					<view :class="['pool-card', 'pool-in', currentPool === 0 ? 'active' : '']" @click="switchPool(0)">
						<view class="pool-head">
							<up-icon name="chat" size="20" color="#fff"></up-icon>
							<text class="pool-num">接入池 ({{ inPoolCount }})</text>
						</view>
						<text class="pool-sub">{{ currentPool === 0 ? '等待接入' : '继续回复' }}</text>
					</view>
					<view :class="['pool-card', 'pool-reply', currentPool === 1 ? 'active' : '']" @click="switchPool(1)">
						<view class="pool-head">
							<up-icon name="chat" size="20" color="#fff"></up-icon>
							<text class="pool-num">回复池 ({{ replyPoolCount }})</text>
						</view>
						<text class="pool-sub">{{ currentPool === 1 ? '等待回复' : '继续回复' }}</text>
					</view>
				</view>

				<!-- 会话列表 -->
				<scroll-view scroll-y class="session-scroll">
					<view v-if="filteredList.length" class="session-list">
						<view class="session-item" v-for="item in filteredList" :key="item.conversationId" @click="gotoChat(item)">
							<view class="avatar-wrap">
								<image v-if="item.avatar" class="avatar-img" :src="item.avatar" mode="aspectFill"></image>
								<view v-else class="avatar-text" :style="{background: avatarColor(item.name)}">{{ avatarChar(item.name) }}</view>
								<view v-if="item.unreadCount" class="badge">{{ item.unreadCount > 99 ? '99+' : item.unreadCount }}</view>
							</view>
							<view class="session-main">
								<view class="session-top">
									<text class="name">{{ item.name }}</text>
									<text class="time">{{ item.timeText }}</text>
								</view>
								<text class="last-text">{{ item.lastText || item.preview || '用户发来咨询，点击接入' }}</text>
							</view>
							<view class="session-action" @click.stop="onSessionAction(item)">
								<text class="action-btn">{{ currentPool === 0 ? '接入' : '回复' }}</text>
							</view>
						</view>
					</view>
					<view v-else class="empty-wrap">
						<view class="empty-icon">
							<up-icon name="chat" size="48" color="#ccc"></up-icon>
						</view>
						<text class="empty-text">{{ currentPool === 0 ? '暂无待接入咨询' : '暂无待回复会话' }}</text>
					</view>
				</scroll-view>
			</view>

			<!-- 订单 -->
			<view v-else-if="currentTab === 1" class="tab-pane">
				<view class="sub-header">
					<text class="sub-title">订单列表</text>
				</view>
				<view class="order-tabs">
					<scroll-view scroll-x class="order-tabs-scroll">
						<view class="order-tabs-inner">
							<view :class="['order-tab', orderTab === tab.key ? 'active' : '']" v-for="tab in orderTabs" :key="tab.key" @click="orderTab = tab.key">
								<text>{{ tab.label }}</text>
							</view>
						</view>
					</scroll-view>
					<view class="filter-tab">
						<up-icon name="list" size="14" color="#666"></up-icon>
						<text>筛选</text>
					</view>
				</view>
				<scroll-view scroll-y class="order-scroll">
					<view v-if="orderList.length" class="order-list">
						<view class="order-card" v-for="item in orderList" :key="item.id">
							<view class="order-card-head">
								<text class="order-no">订单号: {{ item.order_no }}</text>
								<text :class="['order-status', orderStatusClass(item.status)]">{{ orderStatusText(item.status) }}</text>
							</view>
							<view class="order-info-row">
								<text class="info-label">下单时间:</text>
								<text class="info-value">{{ item.createtime }}</text>
								<text class="order-amount">订单金额: ¥{{ item.amount }}</text>
							</view>
							<view class="order-info-row">
								<text class="info-label">老板名字:</text>
								<text class="info-value">{{ item.boss_name || '.' }}</text>
								<view class="copy-btn" @click="copyText(item.boss_name)">复制</view>
							</view>
							<view class="order-info-row">
								<text class="info-label">老板ID:</text>
								<text class="info-value">{{ item.boss_id || '1' }}</text>
								<view class="copy-btn" @click="copyText(item.boss_id)">复制</view>
							</view>
							<view class="order-info-row">
								<text class="info-label">接单成员:</text>
								<text class="info-value">{{ item.thug_name || '待接单' }}</text>
								<view class="copy-btn" @click="copyText(item.thug_name)">复制</view>
							</view>
							<view class="order-info-row">
								<text class="info-label">抢单时间:</text>
								<text class="info-value">{{ item.grab_time || '-' }}</text>
							</view>
							<view class="order-detail-box">
								<view class="detail-row"><text class="detail-label">商品:</text><text class="detail-value">{{ item.title }}</text></view>
								<view class="detail-row"><text class="detail-label">游戏区服:</text><text class="detail-value">{{ item.server || '-' }}</text></view>
								<view class="detail-row"><text class="detail-label">订单规格:</text><text class="detail-value">{{ item.spec || '-' }}</text></view>
								<view class="detail-row"><text class="detail-label">简介:</text><text class="detail-value">{{ item.synopsis || '-' }}</text></view>
							</view>
							<view class="order-card-foot">
								<view class="order-btn outline" @click="orderAbnormal(item)">订单异常</view>
								<view class="order-btn outline" @click="contactMember(item)">联系成员</view>
								<view class="order-btn outline" @click="contactBoss(item)">联系老板</view>
								<view class="order-btn primary" @click="viewOrderDetail(item)">查看详情</view>
							</view>
						</view>
					</view>
					<view v-else class="empty-wrap">
						<view class="empty-icon">
							<up-icon name="order" size="48" color="#ccc"></up-icon>
						</view>
						<text class="empty-text">暂无订单</text>
					</view>
				</scroll-view>
			</view>

			<!-- 结单审核 -->
				<view v-else-if="currentTab === 2" class="tab-pane">
					<view class="sub-header">
						<text class="sub-title">结单审核</text>
				</view>
				<!-- 统计卡片 -->
				<view class="review-summary">
					<view class="review-summary-left">
						<text class="review-summary-title">待审核结单</text>
						<text class="review-summary-num">{{ pendingReviewCount }}</text>
						<text class="review-summary-desc">打手完成后先待结算，由董事长或授权客服验收</text>
					</view>
					<view class="review-summary-right">
						<view class="pass-rate-circle">
							<text class="pass-rate-num">100%</text>
							<text class="pass-rate-label">审核通过率</text>
						</view>
					</view>
				</view>
				<!-- 审核 Tab -->
				<view class="review-tabs">
					<view :class="['review-tab', reviewTab === 'pending' ? 'active' : '']" @click="reviewTab = 'pending'">
						待审核 ({{ pendingReviewCount }})
					</view>
					<view :class="['review-tab', reviewTab === 'passed' ? 'active' : '']" @click="reviewTab = 'passed'">
						已通过 ({{ passedCount }})
					</view>
					<view :class="['review-tab', reviewTab === 'rejected' ? 'active' : '']" @click="reviewTab = 'rejected'">
						已退回 ({{ rejectedCount }})
					</view>
				</view>
				<scroll-view scroll-y class="review-scroll">
					<view v-if="filteredReviewList.length" class="order-list">
						<view class="order-card" v-for="item in filteredReviewList" :key="item.id">
							<view class="order-card-head">
								<text class="order-no">订单号: {{ item.order_no }}</text>
								<text class="order-status pending">待审核</text>
							</view>
							<view class="order-info-row">
								<text class="info-label">提交时间:</text>
								<text class="info-value">{{ item.createtime }}</text>
							</view>
							<view class="order-card-foot">
								<view class="order-btn outline" @click="reviewOrder(item, 'reject')">退回</view>
								<view class="order-btn primary" @click="reviewOrder(item, 'pass')">通过</view>
							</view>
						</view>
					</view>
					<view v-else class="empty-wrap review-empty">
						<view class="review-empty-illust">
							<view class="illust-bar bar1"></view>
							<view class="illust-bar bar2"></view>
							<view class="illust-bar bar3"></view>
							<view class="illust-plane">✈</view>
						</view>
						<text class="empty-text">— 暂无待审核结单 —</text>
						<text class="empty-subtext">打手提交完成凭证后会显示在这里</text>
						<view class="grab-btn" @click="goGrabOrder">去接单</view>
					</view>
				</scroll-view>
			</view>

			<!-- 我的 -->
			<view v-else-if="currentTab === 3" class="tab-pane mine-pane">
				<view class="sub-header">
					<text class="sub-title">我的</text>
				</view>
				<scroll-view scroll-y class="mine-scroll">
					<!-- 用户卡片 -->
					<view class="mine-card">
						<view class="mine-avatar">
							<image v-if="userInfo.avatar" class="mine-avatar-img" :src="userInfo.avatar" mode="aspectFill"></image>
							<text v-else>{{ avatarChar(userInfo.name) }}</text>
						</view>
						<view class="mine-user">
							<view class="mine-name-row">
								<text class="mine-name">{{ userInfo.name || '客服' }}</text>
								<text class="role-badge">客服</text>
							</view>
							<text class="mine-id">ID: {{ userInfo.id || '--' }}</text>
							<view class="mine-meta">
								<text class="meta-item">编辑</text>
								<text class="meta-item">当前分站</text>
								<text class="meta-link" @click="changeAvatar">修改头像</text>
							</view>
						</view>
						<view class="switch-identity-btn" @click="openIdentityPopup">切换身份</view>
					</view>

					<!-- 客服设置 -->
					<view class="mine-section">
						<text class="section-title">客服设置</text>
						<view class="mine-menu">
							<view class="menu-item" @click="changeAvatar">
								<view class="menu-left">
									<view class="menu-icon-wrap"><up-icon name="photo" size="20" color="#FF7A45"></up-icon></view>
									<text class="menu-text">客服头像</text>
								</view>
								<view class="menu-right">
									<text class="menu-value">修改</text>
									<up-icon name="arrow-right" size="14" color="#ccc"></up-icon>
								</view>
							</view>
							<view class="menu-item" @click="refreshProfile">
								<view class="menu-left">
									<view class="menu-icon-wrap"><up-icon name="reload" size="20" color="#FF7A45"></up-icon></view>
									<text class="menu-text">刷新客服资料</text>
								</view>
								<view class="menu-right">
									<text class="menu-value">立即刷新</text>
									<up-icon name="arrow-right" size="14" color="#ccc"></up-icon>
								</view>
							</view>
							<view class="menu-item" @click="openIdentityPopup">
								<view class="menu-left">
									<view class="menu-icon-wrap"><up-icon name="account" size="20" color="#FF7A45"></up-icon></view>
									<text class="menu-text">切换身份</text>
								</view>
								<view class="menu-right">
									<text class="menu-value">客服端</text>
									<up-icon name="arrow-right" size="14" color="#ccc"></up-icon>
								</view>
							</view>
						</view>
					</view>
				</scroll-view>
			</view>
		</view>

		<!-- 底部 tabbar -->
		<view class="cs-tabbar">
			<view
				v-for="(item, index) in tabbarList"
				:key="index"
				:class="['cs-tabbar-item', currentTab === index ? 'active' : '']"
				@click="switchTab(index)"
			>
				<view class="cs-tabbar-icon-wrap">
					<up-icon :name="item.icon" size="22" :color="currentTab === index ? '#fff' : '#999'"></up-icon>
				</view>
				<text class="cs-tabbar-text">{{ item.text }}</text>
			</view>
		</view>

		<!-- 切换身份弹窗 -->
		<view class="identity-popup-mask" v-if="showIdentityPopup" @click="closeIdentityPopup">
			<view class="identity-popup" @click.stop>
				<view class="identity-popup-header">
					<text class="identity-popup-title">切换身份</text>
					<view class="identity-popup-close" @click="closeIdentityPopup"><text class="close-icon">×</text></view>
				</view>
				<view class="identity-options">
					<view v-for="item in identityList" :key="item.key" :class="['identity-option', currentIdentity === item.key ? 'active' : '']" @click="selectIdentity(item)">
						<view class="identity-option-left">
							<view :class="['identity-radio', currentIdentity === item.key ? 'checked' : '']">
								<up-icon v-if="currentIdentity === item.key" name="checkmark" size="14" color="#fff"></up-icon>
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
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getConversationList } from '../../utils/im'
import { getUserInfo } from '../../request/api'

const statusBarHeight = ref(44)
try {
	const sysInfo = uni.getSystemInfoSync()
	statusBarHeight.value = sysInfo.statusBarHeight || 44
} catch (e) {}

const currentTab = ref(0)
const currentPool = ref(0)
const orderTab = ref('all')
const reviewTab = ref('pending')
const conversationList = ref([])
const orderList = ref([])
const reviewList = ref([])
const userInfo = ref({})
const inPoolCount = ref(0)
const replyPoolCount = ref(0)
const showIdentityPopup = ref(false)
const currentIdentity = ref(2)
let timer = null

const tabbarList = [
	{ icon: 'chat', text: '接入池' },
	{ icon: 'order', text: '订单' },
	{ icon: 'checkmark-circle', text: '结单审核' },
	{ icon: 'account', text: '我的' }
]

const navTitle = computed(() => {
	const map = ['客服会话', '订单列表', '结单审核', '我的']
	return map[currentTab.value] || '客服会话'
})

const orderTabs = [
	{ key: 'all', label: '全部' },
	{ key: 'serving', label: '服务中' },
	{ key: 'pending', label: '审核中' },
	{ key: 'rejected', label: '审核失败' },
	{ key: 'completed', label: '已完成' }
]

const identityList = [
	{ key: 0, title: '我是老板', desc: '我要找打手帮我玩' },
	{ key: 1, title: '我是大神', desc: '我就是打手,我来接单子' },
	{ key: 2, title: '我是客服', desc: '进入客服端处理用户消息' }
]

const filteredList = computed(() => {
	if (currentPool.value === 0) {
		return conversationList.value.filter(it => !it.accepted)
	}
	return conversationList.value.filter(it => it.accepted)
})

const pendingReviewCount = computed(() => reviewList.value.filter(it => it.status === 'pending').length)
const passedCount = computed(() => reviewList.value.filter(it => it.status === 'passed').length)
const rejectedCount = computed(() => reviewList.value.filter(it => it.status === 'rejected').length)

const filteredReviewList = computed(() => {
	return reviewList.value.filter(it => it.status === reviewTab.value)
})

onMounted(() => {
	loadAll()
	startPolling()
})

onShow(() => {
	loadAll()
})

onUnmounted(() => {
	stopPolling()
})

const loadAll = async () => {
	await Promise.all([loadConversationList(), loadOrders(), loadUserInfo()])
}

const loadUserInfo = async () => {
	try {
		const res = await getUserInfo()
		if (res.code === 1 && res.data) {
			const thug = res.data.thug
			userInfo.value = {
				id: thug && thug.id ? thug.id : (res.data.id || res.data.user_id || ''),
				name: (thug && thug.name) || res.data.nickname || res.data.username || '客服',
				avatar: res.data.avatar || ''
			}
		}
	} catch (e) {
		console.log('获取客服信息失败', e)
	}
}

const getLastText = (msg) => {
	if (!msg) return ''
	const type = msg.msg_type || msg.type || 'text'
	if (type === 'text') {
		try {
			const content = typeof msg.content === 'string' ? JSON.parse(msg.content) : msg.content
			return content.data || ''
		} catch (e) {
			return ''
		}
	}
	if (type === 'image') return '[图片]'
	if (type === 'video') return '[视频]'
	if (type === 'audio' || type === 'voice') return '[语音]'
	if (type === 'file') return '[文件]'
	return '[消息]'
}

const getTimeText = (msg) => {
	if (!msg || !msg.createtime) return ''
	const d = new Date(msg.createtime * 1000)
	const now = new Date()
	const pad = n => String(n).padStart(2, '0')
	return d.toDateString() === now.toDateString() ? `${pad(d.getHours())}:${pad(d.getMinutes())}` : `${d.getMonth() + 1}/${d.getDate()}`
}

const loadConversationList = async () => {
	try {
		const list = await getConversationList()
		const mapped = (list || []).map(conv => ({
			conversationId: conv.conversationId,
			conversationType: conv.conversationType || 'single',
			targetId: conv.targetId || conv.profile?.userId || conv.userId || '',
			userId: conv.profile?.userId || conv.userId || conv.targetId || '',
			name: conv.profile?.name || conv.profile?.nickname || '用户',
			avatar: conv.avatar || conv.profile?.avatar || '',
			unreadCount: conv.unreadCount || 0,
			accepted: !!(conv.accepted || conv.profile?.accepted),
			lastText: getLastText(conv.lastMessage),
			preview: conv.lastMessage?.preview || '',
			timeText: getTimeText(conv.lastMessage)
		}))
		conversationList.value = mapped
		inPoolCount.value = mapped.filter(it => !it.accepted).length
		replyPoolCount.value = mapped.filter(it => it.accepted).length
	} catch (e) {
		conversationList.value = []
		inPoolCount.value = 0
		replyPoolCount.value = 0
	}
}

const loadOrders = async () => {
	orderList.value = []
	reviewList.value = []
}

const startPolling = () => {
	stopPolling()
	timer = setInterval(loadConversationList, 3000)
}

const stopPolling = () => {
	if (timer) {
		clearInterval(timer)
		timer = null
	}
}

const switchPool = (pool) => {
	currentPool.value = pool
}

const switchTab = (index) => {
	currentTab.value = index
}

const gotoChat = (item) => {
	const convType = item.conversationType || 'single'
	const targetId = convType === 'group' ? item.targetId : item.userId
	uni.navigateTo({
		url: `/pagesA/im/chat?target_user_id=${targetId}&target_name=${encodeURIComponent(item.name)}&target_avatar=${encodeURIComponent(item.avatar || '')}&conversation_id=${item.conversationId || ''}&conversation_type=${convType}`
	})
}

const onSessionAction = (item) => {
	if (currentPool.value === 0) {
		item.accepted = true
		gotoChat(item)
	} else {
		gotoChat(item)
	}
}

const avatarChar = (name) => {
	if (!name) return '客'
	return String(name).charAt(0)
}

const avatarColor = (name) => {
	const colors = ['#7C5CFF', '#5B8FF9', '#5AD8A6', '#F6BD16', '#E86452', '#6DC8EC', '#945FB9', '#FF9845']
	const idx = name ? String(name).charCodeAt(0) % colors.length : 0
	return colors[idx]
}

const orderStatusText = (status) => {
	const map = { serving: '服务中', pending: '审核中', rejected: '审核失败', completed: '已完成', cancelled: '已取消(已退款)' }
	return map[status] || '处理中'
}

const orderStatusClass = (status) => {
	const map = { serving: 'serving', pending: 'pending', rejected: 'rejected', completed: 'completed', cancelled: 'cancelled' }
	return map[status] || 'serving'
}

const copyText = (text) => {
	if (!text) return
	uni.setClipboardData({ data: String(text) })
}

const viewOrderDetail = (item) => {
	uni.showToast({ title: '查看详情', icon: 'none' })
}

const orderAbnormal = (item) => {
	uni.showToast({ title: '订单异常', icon: 'none' })
}

const contactMember = (item) => {
	if (item.thug_id) {
		uni.navigateTo({
			url: `/pagesA/im/chat?target_user_id=${item.thug_id}&target_name=${encodeURIComponent(item.thug_name || '成员')}`
		})
	} else {
		uni.showToast({ title: '暂无成员信息', icon: 'none' })
	}
}

const contactBoss = (item) => {
	if (item.boss_id) {
		uni.navigateTo({
			url: `/pagesA/im/chat?target_user_id=${item.boss_id}&target_name=${encodeURIComponent(item.boss_name || '老板')}`
		})
	} else {
		uni.showToast({ title: '暂无老板信息', icon: 'none' })
	}
}

const reviewOrder = (item, action) => {
	uni.showToast({ title: action === 'pass' ? '已通过' : '已退回', icon: 'success' })
}

const goGrabOrder = () => {
	uni.showToast({ title: '去接单', icon: 'none' })
}

const changeAvatar = () => {
	uni.navigateTo({ url: '/pages/playerMain/cs-avatar' })
}

const refreshProfile = () => {
	loadUserInfo()
	uni.showToast({ title: '已刷新', icon: 'success' })
}

const goBack = () => {
	uni.navigateBack()
}

const openIdentityPopup = () => {
	showIdentityPopup.value = true
}

const closeIdentityPopup = () => {
	showIdentityPopup.value = false
}

const selectIdentity = async (item) => {
	if (item.key === 0) {
		uni.setStorageSync('type', '用户')
		uni.removeStorageSync('id')
		uni.reLaunch({ url: '/pages/mine/index' })
		return
	}
	if (item.key === 1) {
		try {
			const res = await getUserInfo()
			const thug = res.data.thug
			const isApproved = res.data.type == '2' && thug && !Array.isArray(thug) && String(thug.sh_status || '') === '1'
			if (!isApproved) {
				uni.showToast({ title: '请先申请入驻', icon: 'none' })
				return
			}
			uni.setStorageSync('type', '打手')
			uni.setStorageSync('id', thug.id)
			uni.reLaunch({ url: '/pages/playerMain/index' })
		} catch (e) {
			uni.showToast({ title: '请先申请入驻', icon: 'none' })
		}
		return
	}
	if (item.key === 2) {
		try {
			const res = await getUserInfo()
			const thug = res.data.thug
			const isCs = res.data.type == '2' && thug && !Array.isArray(thug) && String(thug.is_customer_service || '0') === '1'
			if (!isCs) {
				uni.showToast({ title: '请先申请客服权限', icon: 'none' })
				return
			}
			uni.setStorageSync('type', '客服')
			uni.setStorageSync('id', thug.id)
			uni.reLaunch({ url: '/pages/playerMain/customer-service' })
		} catch (e) {
			uni.showToast({ title: '请先申请客服权限', icon: 'none' })
		}
		return
	}
	currentIdentity.value = item.key
	closeIdentityPopup()
}
</script>

<style scoped>
.cs-page { min-height: 100vh; background: linear-gradient(180deg, #FF7A45 0%, #FF8E5A 15%, #FFE0C8 35%, #FFF5EC 55%, #FFFFFF 100%); display: flex; flex-direction: column; }
.status-bar { background: transparent; }
.nav-bar { height: 88rpx; padding: 0 24rpx; display: flex; align-items: center; justify-content: space-between; background: transparent; }
.nav-back { width: 56rpx; height: 56rpx; display: flex; align-items: center; justify-content: center; }
.close-icon { font-size: 44rpx; color: #fff; line-height: 1; }
.nav-title { font-size: 34rpx; font-weight: 700; color: #fff; letter-spacing: 2rpx; }
.nav-right { width: 56rpx; height: 56rpx; display: flex; align-items: center; justify-content: center; }

.cs-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; padding-bottom: 180rpx; }
.tab-pane { flex: 1; display: flex; flex-direction: column; background: transparent; }

.sub-header { display: flex; align-items: center; justify-content: space-between; padding: 28rpx 32rpx; background: transparent; }
.sub-title { font-size: 38rpx; font-weight: 800; color: #1a1a1a; }
.sub-manage { display: flex; align-items: center; gap: 6rpx; padding: 8rpx 16rpx; background: #FFF1E8; border-radius: 24rpx; }
.manage-text { font-size: 24rpx; color: #FF7A45; font-weight: 600; }

/* ===== 接入池 ===== */
.pool-cards { display: flex; gap: 16rpx; padding: 8rpx 24rpx 24rpx; background: linear-gradient(180deg, #FFFFFF 0%, #FFFFFF 100%); }
.pool-card { flex: 1; padding: 28rpx 24rpx; border-radius: 24rpx; display: flex; flex-direction: column; gap: 12rpx; transition: all 0.2s; }
.pool-in { background: linear-gradient(135deg, #FF7A45 0%, #FF9F7A 100%); color: #fff; box-shadow: 0 8rpx 24rpx rgba(255, 122, 69, 0.3); }
.pool-reply { background: linear-gradient(135deg, #FFC53B 0%, #FFD966 100%); color: #fff; box-shadow: 0 8rpx 24rpx rgba(255, 197, 59, 0.35); }
.pool-card.active { transform: translateY(-4rpx); box-shadow: 0 12rpx 32rpx rgba(255, 122, 69, 0.4); }
.pool-head { display: flex; align-items: center; gap: 8rpx; }
.pool-num { font-size: 30rpx; font-weight: 700; }
.pool-sub { font-size: 22rpx; opacity: 0.85; padding-left: 48rpx; }

.session-scroll { flex: 1; }
.session-list { background: #fff; margin: 0 24rpx; border-radius: 24rpx; padding: 0 24rpx; box-shadow: 0 2rpx 12rpx rgba(0,0,0,0.04); }
.session-item { display: flex; align-items: center; gap: 20rpx; padding: 28rpx 0; border-bottom: 1rpx solid #f5f5f5; }
.session-item:last-child { border-bottom: none; }
.avatar-wrap { width: 88rpx; height: 88rpx; position: relative; flex-shrink: 0; }
.avatar-img { width: 88rpx; height: 88rpx; border-radius: 50%; }
.avatar-text { width: 88rpx; height: 88rpx; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 32rpx; font-weight: 600; }
.badge { position: absolute; right: -6rpx; top: -6rpx; min-width: 32rpx; height: 32rpx; padding: 0 8rpx; border-radius: 18rpx; background: #ff4d4f; color: #fff; font-size: 20rpx; display: flex; align-items: center; justify-content: center; box-shadow: 0 2rpx 6rpx rgba(255,77,79,0.4); }
.session-main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 8rpx; }
.session-top { display: flex; align-items: center; justify-content: space-between; gap: 16rpx; }
.name { font-size: 30rpx; color: #1a1a1a; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.time { font-size: 22rpx; color: #999; flex-shrink: 0; }
.last-text { font-size: 24rpx; color: #999; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.session-action { flex-shrink: 0; }
.action-btn { padding: 14rpx 36rpx; background: linear-gradient(135deg, #FF7A45 0%, #FF9F7A 100%); color: #fff; font-size: 26rpx; border-radius: 32rpx; font-weight: 600; box-shadow: 0 4rpx 12rpx rgba(255, 122, 69, 0.3); }

.empty-wrap { padding: 100rpx 0; display: flex; flex-direction: column; align-items: center; gap: 20rpx; }
.empty-icon { width: 140rpx; height: 140rpx; background: linear-gradient(135deg, #FFF1E8 0%, #FFE0C8 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.empty-text { font-size: 28rpx; color: #999; }

/* ===== 订单 ===== */
.order-tabs { display: flex; align-items: center; gap: 8rpx; padding: 0 24rpx; background: #fff; border-bottom: 1rpx solid #f5f5f5; }
.order-tabs-scroll { flex: 1; white-space: nowrap; }
.order-tabs-inner { display: inline-flex; gap: 12rpx; padding: 20rpx 0; }
.order-tab { padding: 10rpx 24rpx; font-size: 26rpx; color: #666; white-space: nowrap; border-radius: 24rpx; transition: all 0.2s; }
.order-tab.active { color: #fff; font-weight: 600; background: linear-gradient(135deg, #FF7A45 0%, #FF9F7A 100%); box-shadow: 0 4rpx 10rpx rgba(255, 122, 69, 0.3); }
.filter-tab { display: flex; align-items: center; gap: 4rpx; padding: 16rpx; font-size: 24rpx; color: #666; border-left: 1rpx solid #f0f0f0; padding-left: 20rpx; }

.order-scroll { flex: 1; }
.order-list { padding: 24rpx; display: flex; flex-direction: column; gap: 24rpx; }
.order-card { background: #fff; border-radius: 24rpx; padding: 28rpx; box-shadow: 0 4rpx 20rpx rgba(0,0,0,0.05); }
.order-card-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24rpx; padding-bottom: 20rpx; border-bottom: 2rpx solid #f5f5f5; }
.order-no { font-size: 24rpx; color: #999; }
.order-status { font-size: 24rpx; padding: 6rpx 18rpx; border-radius: 20rpx; font-weight: 600; }
.order-status.serving { color: #FF7A45; background: #FFF1E8; }
.order-status.pending { color: #ff9500; background: #fff4e0; }
.order-status.rejected { color: #ff4d4f; background: #fde8e8; }
.order-status.completed { color: #3cc51f; background: #e8f8e4; }
.order-status.cancelled { color: #999; background: #f5f5f5; }

.order-info-row { display: flex; align-items: center; gap: 8rpx; margin-bottom: 14rpx; font-size: 24rpx; }
.info-label { color: #999; flex-shrink: 0; }
.info-value { color: #333; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.order-amount { color: #FF7A45; font-weight: 700; flex-shrink: 0; font-size: 26rpx; }
.copy-btn { padding: 6rpx 18rpx; border: 1rpx solid #FF7A45; color: #FF7A45; border-radius: 12rpx; font-size: 20rpx; flex-shrink: 0; background: #FFF1E8; }

.order-detail-box { background: #FFF8F3; border-radius: 16rpx; padding: 20rpx 24rpx; margin: 20rpx 0; border-left: 4rpx solid #FF7A45; }
.detail-row { display: flex; margin-bottom: 10rpx; font-size: 24rpx; }
.detail-row:last-child { margin-bottom: 0; }
.detail-label { color: #999; width: 140rpx; flex-shrink: 0; }
.detail-value { color: #333; flex: 1; }

.order-card-foot { display: flex; justify-content: flex-end; gap: 12rpx; margin-top: 20rpx; }
.order-btn { padding: 14rpx 30rpx; font-size: 24rpx; border-radius: 32rpx; font-weight: 600; }
.order-btn.outline { border: 1rpx solid #ddd; color: #666; background: #fff; }
.order-btn.primary { background: linear-gradient(135deg, #FF7A45 0%, #FF9F7A 100%); color: #fff; box-shadow: 0 4rpx 12rpx rgba(255, 122, 69, 0.3); border: none; }

/* ===== 结单审核 ===== */
.review-summary { margin: 16rpx 24rpx; padding: 32rpx; background: linear-gradient(135deg, #FF7A45 0%, #FF9F7A 100%); border-radius: 24rpx; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 8rpx 24rpx rgba(255, 122, 69, 0.3); }
.review-summary-left { flex: 1; }
.review-summary-title { font-size: 26rpx; color: rgba(255,255,255,0.85); }
.review-summary-num { display: block; font-size: 64rpx; font-weight: 800; color: #fff; margin: 8rpx 0; }
.review-summary-desc { font-size: 22rpx; color: rgba(255,255,255,0.7); line-height: 1.5; }
.review-summary-right { flex-shrink: 0; margin-left: 24rpx; }
.pass-rate-circle { width: 130rpx; height: 130rpx; border-radius: 50%; background: rgba(255,255,255,0.18); display: flex; flex-direction: column; align-items: center; justify-content: center; backdrop-filter: blur(10rpx); }
.pass-rate-num { font-size: 34rpx; font-weight: 700; color: #fff; }
.pass-rate-label { font-size: 18rpx; color: rgba(255,255,255,0.8); }

.review-tabs { display: flex; padding: 0 24rpx; background: #fff; border-bottom: 1rpx solid #f0f0f0; }
.review-tab { flex: 1; text-align: center; padding: 24rpx 0; font-size: 26rpx; color: #666; position: relative; }
.review-tab.active { color: #FF7A45; font-weight: 700; }
.review-tab.active::after { content: ''; position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); width: 48rpx; height: 6rpx; background: linear-gradient(90deg, #FF7A45, #FF9F7A); border-radius: 3rpx; }

.review-scroll { flex: 1; }
.review-empty { gap: 12rpx; }
.review-empty-illust { position: relative; width: 200rpx; height: 140rpx; margin-bottom: 16rpx; }
.illust-bar { position: absolute; bottom: 0; width: 36rpx; background: linear-gradient(180deg, #FF9F7A, #FF7A45); border-radius: 8rpx 8rpx 0 0; }
.bar1 { left: 20rpx; height: 60rpx; }
.bar2 { left: 70rpx; height: 90rpx; }
.bar3 { left: 120rpx; height: 120rpx; }
.illust-plane { position: absolute; top: 10rpx; right: 20rpx; font-size: 40rpx; color: #FF7A45; }
.empty-subtext { font-size: 22rpx; color: #bbb; }
.grab-btn { margin-top: 24rpx; padding: 18rpx 72rpx; background: linear-gradient(135deg, #FF7A45 0%, #FF9F7A 100%); color: #fff; font-size: 28rpx; border-radius: 36rpx; font-weight: 600; box-shadow: 0 6rpx 16rpx rgba(255, 122, 69, 0.35); }

/* ===== 我的 ===== */
.mine-scroll { flex: 1; }
.mine-card { margin: 24rpx; padding: 32rpx; background: linear-gradient(135deg, #FF7A45 0%, #FF9F7A 100%); border-radius: 28rpx; display: flex; align-items: center; gap: 20rpx; box-shadow: 0 8rpx 24rpx rgba(255, 122, 69, 0.3); }
.mine-avatar { width: 100rpx; height: 100rpx; border-radius: 50%; background: rgba(255,255,255,0.25); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 36rpx; font-weight: 700; flex-shrink: 0; overflow: hidden; border: 3rpx solid rgba(255,255,255,0.4); }
.mine-avatar-img { width: 100%; height: 100%; }
.mine-user { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 8rpx; }
.mine-name-row { display: flex; align-items: center; gap: 12rpx; }
.mine-name { font-size: 32rpx; color: #fff; font-weight: 700; }
.role-badge { padding: 4rpx 14rpx; background: rgba(255,255,255,0.25); color: #fff; font-size: 20rpx; border-radius: 12rpx; backdrop-filter: blur(10rpx); }
.mine-id { font-size: 22rpx; color: rgba(255,255,255,0.85); }
.mine-meta { display: flex; align-items: center; gap: 12rpx; margin-top: 6rpx; }
.meta-item { font-size: 20rpx; color: rgba(255,255,255,0.9); padding: 4rpx 14rpx; background: rgba(255,255,255,0.18); border-radius: 12rpx; }
.meta-link { font-size: 22rpx; color: #fff; text-decoration: underline; }
.switch-identity-btn { padding: 14rpx 26rpx; background: #1a1a1a; color: #fff; font-size: 24rpx; border-radius: 28rpx; flex-shrink: 0; font-weight: 600; }

.mine-section { margin: 0 24rpx 24rpx; }
.section-title { display: block; font-size: 30rpx; font-weight: 700; color: #1a1a1a; margin-bottom: 16rpx; padding-left: 8rpx; }
.mine-menu { background: #fff; border-radius: 24rpx; padding: 0 28rpx; box-shadow: 0 4rpx 20rpx rgba(0,0,0,0.04); }
.menu-item { display: flex; align-items: center; justify-content: space-between; padding: 32rpx 0; border-bottom: 1rpx solid #f5f5f5; }
.menu-item:last-child { border-bottom: none; }
.menu-left { display: flex; align-items: center; gap: 20rpx; }
.menu-icon-wrap { width: 56rpx; height: 56rpx; background: #FFF1E8; border-radius: 14rpx; display: flex; align-items: center; justify-content: center; }
.menu-text { font-size: 28rpx; color: #333; font-weight: 500; }
.menu-right { display: flex; align-items: center; gap: 8rpx; }
.menu-value { font-size: 24rpx; color: #999; }

/* ===== 底部 tabbar（整体椭圆形包裹）===== */
.cs-tabbar { position: fixed; bottom: calc(env(safe-area-inset-bottom) + 16rpx); left: 24rpx; right: 24rpx; height: 112rpx; background: #fff; border-radius: 56rpx; display: flex; align-items: center; justify-content: space-around; box-shadow: 0 8rpx 32rpx rgba(0,0,0,0.12); padding: 0 16rpx; z-index: 99; border: 1rpx solid rgba(0,0,0,0.04); }
.cs-tabbar-item { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4rpx; padding: 10rpx 0; position: relative; border-radius: 48rpx; transition: all 0.25s ease; }
.cs-tabbar-item.active { background: linear-gradient(135deg, #FF7A45 0%, #FF9F7A 100%); box-shadow: 0 6rpx 18rpx rgba(255, 122, 69, 0.4); }
.cs-tabbar-icon-wrap { display: flex; align-items: center; justify-content: center; transition: all 0.25s ease; }
.cs-tabbar-item.active .cs-tabbar-icon-wrap { }
.cs-tabbar-text { font-size: 20rpx; color: #999; transition: all 0.25s; }
.cs-tabbar-item.active .cs-tabbar-text { color: #fff; font-weight: 700; }

/* ===== 切换身份弹窗 ===== */
.identity-popup-mask { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; display: flex; align-items: center; justify-content: center; padding: 0 32rpx; box-sizing: border-box; }
.identity-popup { width: 100%; max-width: 640rpx; background: #fff; border-radius: 36rpx; padding: 36rpx 24rpx 40rpx; box-sizing: border-box; }
.identity-popup-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 40rpx; }
.identity-popup-title { font-size: 36rpx; font-weight: 700; color: #1a1a1a; }
.identity-popup-close { width: 56rpx; height: 56rpx; display: flex; align-items: center; justify-content: center; }
.identity-options { display: flex; flex-direction: column; gap: 24rpx; }
.identity-option { display: flex; align-items: center; padding: 28rpx 24rpx; background: #fff; border-radius: 32rpx; border: 2rpx solid rgba(0,0,0,0.06); position: relative; overflow: hidden; }
.identity-option.active { border-color: transparent; background: linear-gradient(135deg, #FF7A45 0%, #FF9F7A 100%); box-shadow: 0 8rpx 20rpx rgba(255, 122, 69, 0.3); }
.identity-option-left { display: flex; align-items: center; gap: 20rpx; position: relative; z-index: 1; }
.identity-radio { width: 44rpx; height: 44rpx; border-radius: 50%; border: 3rpx solid #ddd; box-sizing: border-box; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.identity-radio.checked { border-color: #FF7A45; background: #FF7A45; }
.identity-text { display: flex; flex-direction: column; gap: 6rpx; }
.identity-title { font-size: 30rpx; font-weight: 700; color: #1a1a1a; }
.identity-desc { font-size: 24rpx; color: #666; }
.identity-option.active .identity-title { color: #fff; }
.identity-option.active .identity-desc { color: rgba(255,255,255,0.85); }
</style>
