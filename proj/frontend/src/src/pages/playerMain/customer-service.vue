<template>
	<view class="cs-page">
		<!-- 顶部状态栏占位 -->
		<view class="status-bar" :style="{height: statusBarHeight + 'px'}"></view>

		<!-- 顶部导航 -->
		<view class="nav-bar">
			<view class="nav-back" @click="goBack">
				<up-icon name="arrow-left" size="20" color="#333"></up-icon>
			</view>
			<text class="nav-title">客服会话</text>
			<view class="nav-right">
				<up-icon name="more-dot" size="20" color="#333"></up-icon>
			</view>
		</view>

		<!-- 内容区：根据 currentTab 切换 -->
		<view class="cs-content">
			<!-- 接入池 / 客服会话 -->
			<view v-if="currentTab === 0" class="tab-pane">
				<view class="sub-header">
					<view class="sub-close" @click="goBack"><text class="close-icon">×</text></view>
					<text class="sub-title">客服会话</text>
					<view class="sub-manage">
						<up-icon name="grid" size="18" color="#666"></up-icon>
						<text class="manage-text">管理</text>
					</view>
				</view>

				<!-- 池子选择卡片 -->
				<view class="pool-cards">
					<view :class="['pool-card', 'pool-in', currentPool === 0 ? 'active' : '']" @click="switchPool(0)">
						<text class="pool-num">接入池 ({{ inPoolCount }})</text>
						<text class="pool-sub">等待接入</text>
					</view>
					<view :class="['pool-card', 'pool-reply', currentPool === 1 ? 'active' : '']" @click="switchPool(1)">
						<text class="pool-num">回复池 ({{ replyPoolCount }})</text>
						<text class="pool-sub">{{ currentPool === 1 ? '等待回复' : '继续回复' }}</text>
					</view>
				</view>

				<!-- 会话列表 -->
				<scroll-view scroll-y class="session-scroll">
					<view v-if="filteredList.length" class="session-list">
						<view class="session-item" v-for="item in filteredList" :key="item.conversationId" @click="gotoChat(item)">
							<view class="avatar-wrap">
								<view class="avatar-text" :style="{background: avatarColor(item.name)}">{{ avatarChar(item.name) }}</view>
								<view v-if="item.unreadCount" class="badge">{{ item.unreadCount > 99 ? '99+' : item.unreadCount }}</view>
							</view>
							<view class="session-main">
								<view class="session-top">
									<text class="name">{{ item.name }}</text>
									<text class="time">{{ item.timeText }}</text>
								</view>
								<text class="last-text">{{ item.lastText || item.preview || '暂无消息' }}</text>
							</view>
							<view class="session-action" @click.stop="onSessionAction(item)">
								<text class="action-btn">{{ currentPool === 0 ? '接入' : '回复' }}</text>
							</view>
						</view>
					</view>
					<view v-else class="empty-wrap">
						<uv-empty :mode="currentPool === 0 ? 'data' : 'message'" :text="currentPool === 0 ? '暂无待接入会话' : '回复池为空'"></uv-empty>
					</view>
				</scroll-view>
			</view>

			<!-- 管理打手 -->
			<view v-else-if="currentTab === 1" class="tab-pane">
				<view class="sub-header">
					<view class="sub-close" @click="goBack"><text class="close-icon">×</text></view>
					<text class="sub-title">管理打手</text>
					<view class="sub-manage">
						<up-icon name="grid" size="18" color="#666"></up-icon>
						<text class="manage-text">管理</text>
					</view>
				</view>
				<scroll-view scroll-y class="order-scroll">
					<view v-if="thugList.length" class="order-list">
						<view class="thug-card" v-for="item in thugList" :key="item.id">
							<view class="thug-avatar">{{ avatarChar(item.name) }}</view>
							<view class="thug-info">
								<text class="thug-name">{{ item.name || '未命名' }}</text>
								<text class="thug-meta">ID：{{ item.id }} · {{ item.sh_status === '1' ? '已入驻' : '未入驻' }} · {{ item.jd_status === '1' ? '接单中' : '离线' }}</text>
								<text class="thug-game">{{ item.game_names || '未设置游戏' }}</text>
							</view>
							<view class="thug-actions">
								<view class="order-btn outline" @click="contactThug(item)">联系</view>
								<view class="order-btn primary" @click="toggleThugStatus(item)">{{ item.jd_status === '1' ? '下线' : '上线' }}</view>
							</view>
						</view>
					</view>
					<view v-else class="empty-wrap">
						<uv-empty mode="data" text="暂无打手"></uv-empty>
					</view>
				</scroll-view>
			</view>

			<!-- 订单 -->
			<view v-else-if="currentTab === 2" class="tab-pane">
				<view class="sub-header">
					<view class="sub-close" @click="goBack"><text class="close-icon">×</text></view>
					<text class="sub-title">订单列表</text>
					<view class="sub-manage">
						<up-icon name="grid" size="18" color="#666"></up-icon>
						<text class="manage-text">管理</text>
					</view>
				</view>
				<view class="order-tabs">
					<view :class="['order-tab', orderTab === tab.key ? 'active' : '']" v-for="tab in orderTabs" :key="tab.key" @click="orderTab = tab.key">
						<text>{{ tab.label }}</text>
					</view>
					<view class="order-tab filter-tab">
						<up-icon name="list" size="14" color="#666"></up-icon>
						<text>筛选</text>
					</view>
				</view>
				<scroll-view scroll-y class="order-scroll">
					<view v-if="orderList.length" class="order-list">
						<view class="order-card" v-for="item in orderList" :key="item.id">
							<view class="order-card-head">
								<text class="order-no">单号：{{ item.order_no }}</text>
								<text :class="['order-status', orderStatusClass(item.status)]">{{ orderStatusText(item.status) }}</text>
							</view>
							<view class="order-card-body">
								<view class="order-icon" :style="{background: orderIconColor(item.title)}">{{ orderIconChar(item.title) }}</view>
								<view class="order-info">
									<text class="order-title">{{ item.title }}</text>
									<text class="order-desc">{{ item.subtitle }}</text>
									<text class="order-time">{{ item.createtime }}</text>
								</view>
								<text class="order-price">¥{{ item.amount }}</text>
							</view>
							<view class="order-card-foot">
								<view class="order-btn outline" @click="viewOrderDetail(item)">详情</view>
								<view class="order-btn primary" @click="contactUser(item)">联系客服</view>
							</view>
						</view>
					</view>
					<view v-else class="empty-wrap">
						<uv-empty mode="data" text="暂无订单"></uv-empty>
					</view>
				</scroll-view>
			</view>

			<!-- 截单审核 -->
			<view v-else-if="currentTab === 3" class="tab-pane">
				<view class="sub-header">
					<view class="sub-close" @click="goBack"><text class="close-icon">×</text></view>
					<text class="sub-title">截单审核</text>
					<view class="sub-manage">
						<up-icon name="grid" size="18" color="#666"></up-icon>
						<text class="manage-text">管理</text>
					</view>
				</view>
				<scroll-view scroll-y class="order-scroll">
					<view v-if="reviewList.length" class="order-list">
						<view class="order-card" v-for="item in reviewList" :key="item.id">
							<view class="order-card-head">
								<text class="order-no">单号：{{ item.order_no }}</text>
								<text class="order-status pending">待审核</text>
							</view>
							<view class="order-card-body">
								<view class="order-icon" :style="{background: orderIconColor(item.title)}">{{ orderIconChar(item.title) }}</view>
								<view class="order-info">
									<text class="order-title">{{ item.title }}</text>
									<text class="order-desc">{{ item.subtitle }}</text>
									<text class="order-time">提交：{{ item.createtime }}</text>
								</view>
								<text class="order-price">¥{{ item.amount }}</text>
							</view>
							<view class="order-card-foot">
								<view class="order-btn outline" @click="reviewOrder(item, 'reject')">退回</view>
								<view class="order-btn primary" @click="reviewOrder(item, 'pass')">通过</view>
							</view>
						</view>
					</view>
					<view v-else class="empty-wrap">
						<uv-empty mode="data" text="暂无待审核订单"></uv-empty>
					</view>
				</scroll-view>
			</view>

			<!-- 我的 -->
			<view v-else-if="currentTab === 4" class="tab-pane mine-pane">
				<view class="sub-header">
					<view class="sub-close" @click="goBack"><text class="close-icon">×</text></view>
					<text class="sub-title">我的</text>
					<view class="sub-manage">
						<up-icon name="grid" size="18" color="#666"></up-icon>
						<text class="manage-text">管理</text>
					</view>
				</view>
				<scroll-view scroll-y class="mine-scroll">
					<!-- 用户卡片 -->
					<view class="mine-card">
						<view class="mine-avatar">{{ avatarChar(userInfo.name) }}</view>
						<view class="mine-user">
							<text class="mine-name">{{ userInfo.name || '客服' }}</text>
							<text class="mine-id">ID：{{ userInfo.id || '--' }}</text>
						</view>
						<view class="mine-role">客服</view>
					</view>
					<!-- 统计 -->
					<view class="stat-row">
						<view class="stat-box">
							<text class="stat-num">{{ stats.todayOrders }}</text>
							<text class="stat-label">今日订单</text>
						</view>
						<view class="stat-box">
							<text class="stat-num">¥{{ stats.todayIncome }}</text>
							<text class="stat-label">今日收入</text>
						</view>
						<view class="stat-box">
							<text class="stat-num">{{ stats.pendingReview }}</text>
							<text class="stat-label">待审核</text>
						</view>
					</view>
					<!-- 菜单列表（无钱包） -->
					<view class="mine-menu">
						<view class="menu-item" v-for="item in mineMenu" :key="item.action" @click="onMenuClick(item)">
							<view class="menu-left">
								<up-icon :name="item.icon" size="20" color="#333"></up-icon>
								<text class="menu-text">{{ item.label }}</text>
							</view>
							<up-icon name="arrow-right" size="14" color="#ccc"></up-icon>
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
				<up-icon :name="item.icon" size="22" :color="currentTab === index ? '#1890ff' : '#999'"></up-icon>
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
	</view>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getConversationList } from '../../utils/im'
import { getUserInfo, customerServiceList as getCustomerServiceList, thug as getThugList, thugEdit } from '../../request/api'
import { openCustomerServiceChat } from '../../utils/customer-service'

const statusBarHeight = ref(44)
try {
	const sysInfo = uni.getSystemInfoSync()
	statusBarHeight.value = sysInfo.statusBarHeight || 44
} catch (e) {}

const currentTab = ref(0)
const currentPool = ref(0)
const orderTab = ref('all')
const conversationList = ref([])
const orderList = ref([])
const reviewList = ref([])
const userInfo = ref({})
const stats = ref({ todayOrders: 0, todayIncome: 0, pendingReview: 0 })
const thugList = ref([])
const inPoolCount = ref(0)
const replyPoolCount = ref(0)
const showIdentityPopup = ref(false)
const currentIdentity = ref(2)
let timer = null

const tabbarList = [
	{ icon: 'chat', text: '接入池' },
	{ icon: 'account', text: '打手' },
	{ icon: 'order', text: '订单' },
	{ icon: 'checkmark-circle', text: '截单审核' },
	{ icon: 'account-fill', text: '我的' }
]

const orderTabs = [
	{ key: 'all', label: '全部' },
	{ key: 'serving', label: '服务中' },
	{ key: 'pending', label: '待审核' },
	{ key: 'rejected', label: '审核退回' }
]

const identityList = [
	{ key: 0, title: '我是老板', desc: '我要找打手帮我玩' },
	{ key: 1, title: '我是大神', desc: '我就是打手,我来接单子' },
	{ key: 2, title: '我是客服', desc: '进入客服端处理用户消息' }
]

// 我的菜单（无钱包）
const mineMenu = ref([
	{ label: '我的订单', icon: 'order', action: 'orders' },
	{ label: '客服中心', icon: 'chat', action: 'serviceCenter' },
	{ label: '设置', icon: 'setting', action: 'settings' },
	{ label: '切换身份', icon: 'reload', action: 'switch' }
])

const filteredList = computed(() => {
	// 接入池：未接入的会话；回复池：已接入需要回复的会话
	if (currentPool.value === 0) {
		return conversationList.value.filter(it => !it.accepted)
	}
	return conversationList.value.filter(it => it.accepted)
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
	await Promise.all([loadConversationList(), loadOrders(), loadUserInfo(), loadThugList()])
}

const loadUserInfo = async () => {
	try {
		const res = await getUserInfo()
		if (res.code === 1 && res.data) {
			const thug = res.data.thug
			userInfo.value = {
				id: thug && thug.id ? thug.id : (res.data.id || res.data.user_id || ''),
				name: (thug && thug.name) || res.data.nickname || res.data.username || '客服'
			}
			stats.value = {
				todayOrders: thug && thug.today_orders ? thug.today_orders : 0,
				todayIncome: thug && thug.today_income ? Number(thug.today_income).toFixed(2) : '0.00',
				pendingReview: thug && thug.pending_review ? thug.pending_review : 0
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
	// 复用订单列表接口（如未接入可留空展示）
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

const loadThugList = async () => {
	try {
		const res = await getThugList({ page: 1, limit: 50 })
		const data = res.data?.list || res.data?.data || res.data || []
		thugList.value = Array.isArray(data) ? data : []
	} catch (e) {
		thugList.value = []
	}
}

const contactThug = (item) => {
	uni.navigateTo({
		url: `/pagesA/im/chat?target_user_id=${item.id || item.user_id || ''}&target_name=${encodeURIComponent(item.name || '打手')}&target_avatar=${encodeURIComponent(item.avatar || '')}&conversation_id=&conversation_type=single`
	})
}

const toggleThugStatus = async (item) => {
	const newStatus = item.jd_status === '1' ? '0' : '1'
	try {
		await thugEdit({ thug_id: item.id, jd_status: newStatus })
		item.jd_status = newStatus
		uni.showToast({ title: newStatus === '1' ? '已上线' : '已下线', icon: 'none' })
	} catch (e) {
		uni.showToast({ title: '操作失败', icon: 'none' })
	}
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
		// 接入：进入聊天
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
	const map = { serving: '服务中', pending: '待审核', rejected: '审核退回', completed: '已完成' }
	return map[status] || '处理中'
}

const orderStatusClass = (status) => {
	const map = { serving: 'serving', pending: 'pending', rejected: 'rejected', completed: 'completed' }
	return map[status] || 'serving'
}

const orderIconChar = (title) => {
	if (!title) return '订'
	return String(title).charAt(0)
}

const orderIconColor = (title) => {
	const colors = ['#7C5CFF', '#5B8FF9', '#5AD8A6', '#F6BD16', '#E86452', '#6DC8EC']
	const idx = title ? String(title).charCodeAt(0) % colors.length : 0
	return colors[idx]
}

const viewOrderDetail = (item) => {
	uni.showToast({ title: '查看详情', icon: 'none' })
}

const contactUser = (item) => {
	// 联系下单用户：进入 IM 聊天
	if (item.userId) {
		uni.navigateTo({
			url: `/pagesA/im/chat?target_user_id=${item.userId}&target_name=${encodeURIComponent(item.name || '用户')}`
		})
	} else {
		uni.showToast({ title: '暂无用户信息', icon: 'none' })
	}
}

const reviewOrder = (item, action) => {
	uni.showToast({ title: action === 'pass' ? '已通过' : '已退回', icon: 'success' })
}

const onMenuClick = (item) => {
	if (item.action === 'orders') {
		currentTab.value = 1
	} else if (item.action === 'serviceCenter') {
		uni.navigateTo({ url: '/pages/playerMain/cs-center' })
	} else if (item.action === 'settings') {
		uni.navigateTo({ url: '/pages/playerMain/cs-settings' })
	} else if (item.action === 'switch') {
		openIdentityPopup()
	}
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
.cs-page { min-height: 100vh; background: #f5f7fa; display: flex; flex-direction: column; }
.status-bar { background: #fff; }
.nav-bar { height: 88rpx; padding: 0 24rpx; display: flex; align-items: center; justify-content: space-between; background: #fff; box-shadow: 0 1rpx 0 rgba(0,0,0,0.04); }
.nav-back { width: 56rpx; height: 56rpx; display: flex; align-items: center; justify-content: center; }
.nav-title { font-size: 32rpx; font-weight: 600; color: #111; }
.nav-right { width: 56rpx; height: 56rpx; display: flex; align-items: center; justify-content: center; }

.cs-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; padding-bottom: 100rpx; }
.tab-pane { flex: 1; display: flex; flex-direction: column; background: #f5f7fa; }

.sub-header { display: flex; align-items: center; justify-content: space-between; padding: 20rpx 24rpx; background: linear-gradient(180deg, #E6F4FF 0%, #ffffff 100%); }
.sub-close { width: 56rpx; height: 56rpx; display: flex; align-items: center; justify-content: center; }
.close-icon { font-size: 40rpx; color: #333; line-height: 1; }
.sub-title { font-size: 30rpx; font-weight: 600; color: #111; }
.sub-manage { display: flex; align-items: center; gap: 6rpx; }
.manage-text { font-size: 24rpx; color: #666; }

.pool-cards { display: flex; gap: 16rpx; padding: 24rpx; }
.pool-card { flex: 1; padding: 28rpx 24rpx; border-radius: 16rpx; display: flex; flex-direction: column; gap: 8rpx; }
.pool-in { background: #1a1a1a; color: #fff; }
.pool-reply { background: #1890ff; color: #fff; }
.pool-card.active { box-shadow: 0 4rpx 16rpx rgba(24, 144, 255, 0.35); transform: translateY(-2rpx); }
.pool-num { font-size: 30rpx; font-weight: 700; }
.pool-sub { font-size: 22rpx; opacity: 0.85; }

.session-scroll { flex: 1; }
.session-list { background: #fff; margin: 0 24rpx; border-radius: 16rpx; padding: 0 24rpx; }
.session-item { display: flex; align-items: center; gap: 20rpx; padding: 24rpx 0; border-bottom: 1rpx solid #f0f0f0; }
.session-item:last-child { border-bottom: none; }
.avatar-wrap { width: 80rpx; height: 80rpx; position: relative; flex-shrink: 0; }
.avatar-text { width: 80rpx; height: 80rpx; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 30rpx; font-weight: 600; }
.badge { position: absolute; right: -6rpx; top: -6rpx; min-width: 32rpx; height: 32rpx; padding: 0 8rpx; border-radius: 18rpx; background: #ff4d4f; color: #fff; font-size: 20rpx; display: flex; align-items: center; justify-content: center; }
.session-main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 8rpx; }
.session-top { display: flex; align-items: center; justify-content: space-between; gap: 16rpx; }
.name { font-size: 28rpx; color: #111; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.time { font-size: 22rpx; color: #999; flex-shrink: 0; }
.last-text { font-size: 24rpx; color: #999; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.session-action { flex-shrink: 0; }
.action-btn { padding: 10rpx 24rpx; background: #1890ff; color: #fff; font-size: 24rpx; border-radius: 24rpx; }
.empty-wrap { padding: 80rpx 0; }

.order-tabs { display: flex; align-items: center; gap: 8rpx; padding: 16rpx 24rpx; background: #fff; border-bottom: 1rpx solid #f0f0f0; overflow-x: auto; }
.order-tab { padding: 8rpx 16rpx; font-size: 26rpx; color: #666; white-space: nowrap; }
.order-tab.active { color: #ff4d4f; font-weight: 600; position: relative; }
.order-tab.active::after { content: ''; position: absolute; bottom: -8rpx; left: 50%; transform: translateX(-50%); width: 32rpx; height: 4rpx; background: #ff4d4f; border-radius: 2rpx; }
.filter-tab { display: flex; align-items: center; gap: 4rpx; margin-left: auto; }

.order-scroll { flex: 1; }
.order-list { padding: 24rpx; display: flex; flex-direction: column; gap: 24rpx; }
.thug-card { background: #fff; border-radius: 16rpx; padding: 24rpx; box-shadow: 0 2rpx 12rpx rgba(0,0,0,0.04); display: flex; align-items: center; gap: 20rpx; }
.thug-avatar { width: 88rpx; height: 88rpx; border-radius: 50%; background: linear-gradient(135deg, #FF7A45, #FFB38A); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 36rpx; font-weight: 600; flex-shrink: 0; }
.thug-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6rpx; }
.thug-name { font-size: 30rpx; color: #111; font-weight: 600; }
.thug-meta { font-size: 22rpx; color: #999; }
.thug-game { font-size: 22rpx; color: #1890ff; }
.thug-actions { display: flex; flex-direction: column; gap: 12rpx; flex-shrink: 0; }
.order-card { background: #fff; border-radius: 16rpx; padding: 24rpx; box-shadow: 0 2rpx 12rpx rgba(0,0,0,0.04); }
.order-card-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20rpx; }
.order-no { font-size: 24rpx; color: #999; }
.order-status { font-size: 24rpx; padding: 4rpx 16rpx; border-radius: 20rpx; }
.order-status.serving { color: #1890ff; background: #E6F4FF; }
.order-status.pending { color: #ff9500; background: #fff4e0; }
.order-status.rejected { color: #ff4d4f; background: #fde8e8; }
.order-status.completed { color: #3cc51f; background: #e8f8e4; }
.order-card-body { display: flex; align-items: center; gap: 20rpx; margin-bottom: 20rpx; }
.order-icon { width: 80rpx; height: 80rpx; border-radius: 16rpx; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 36rpx; font-weight: 600; flex-shrink: 0; }
.order-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6rpx; }
.order-title { font-size: 28rpx; color: #111; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.order-desc { font-size: 22rpx; color: #999; }
.order-time { font-size: 22rpx; color: #ccc; }
.order-price { font-size: 32rpx; color: #ff4d4f; font-weight: 700; flex-shrink: 0; }
.order-card-foot { display: flex; justify-content: flex-end; gap: 16rpx; }
.order-btn { padding: 12rpx 32rpx; font-size: 24rpx; border-radius: 28rpx; }
.order-btn.outline { border: 1rpx solid #ddd; color: #666; }
.order-btn.primary { background: #1890ff; color: #fff; }

.mine-scroll { flex: 1; }
.mine-card { margin: 24rpx; padding: 32rpx; background: linear-gradient(135deg, #1890ff 0%, #5B8FF9 100%); border-radius: 24rpx; display: flex; align-items: center; gap: 20rpx; box-shadow: 0 4rpx 16rpx rgba(24, 144, 255, 0.25); }
.mine-avatar { width: 96rpx; height: 96rpx; border-radius: 50%; background: rgba(255,255,255,0.3); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 36rpx; font-weight: 700; flex-shrink: 0; }
.mine-user { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6rpx; }
.mine-name { font-size: 30rpx; color: #fff; font-weight: 700; }
.mine-id { font-size: 22rpx; color: rgba(255,255,255,0.8); }
.mine-role { padding: 6rpx 20rpx; background: rgba(255,255,255,0.25); color: #fff; font-size: 22rpx; border-radius: 20rpx; }
.stat-row { display: flex; gap: 16rpx; margin: 0 24rpx 24rpx; }
.stat-box { flex: 1; background: #fff; border-radius: 16rpx; padding: 24rpx; text-align: center; }
.stat-num { display: block; font-size: 40rpx; font-weight: 700; color: #1890ff; }
.stat-label { font-size: 22rpx; color: #999; margin-top: 8rpx; display: block; }
.mine-menu { margin: 0 24rpx; background: #fff; border-radius: 16rpx; padding: 0 24rpx; }
.menu-item { display: flex; align-items: center; justify-content: space-between; padding: 28rpx 0; border-bottom: 1rpx solid #f5f5f5; }
.menu-item:last-child { border-bottom: none; }
.menu-left { display: flex; align-items: center; gap: 16rpx; }
.menu-text { font-size: 28rpx; color: #333; }

.cs-tabbar { position: fixed; bottom: 0; left: 0; right: 0; height: 100rpx; background: #fff; display: flex; align-items: center; justify-content: space-around; box-shadow: 0 -1rpx 0 rgba(0,0,0,0.06); padding-bottom: env(safe-area-inset-bottom); z-index: 99; }
.cs-tabbar-item { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6rpx; }
.cs-tabbar-text { font-size: 20rpx; color: #999; }
.cs-tabbar-item.active .cs-tabbar-text { color: #1890ff; font-weight: 600; }

.identity-popup-mask { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; display: flex; align-items: center; justify-content: center; padding: 0 32rpx; box-sizing: border-box; }
.identity-popup { width: 100%; max-width: 640rpx; background: #fff; border-radius: 36rpx; padding: 32rpx 24rpx 40rpx; box-sizing: border-box; }
.identity-popup-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 40rpx; }
.identity-popup-title { font-size: 36rpx; font-weight: 700; color: #090b08; }
.identity-popup-close { width: 56rpx; height: 56rpx; display: flex; align-items: center; justify-content: center; }
.identity-options { display: flex; flex-direction: column; gap: 24rpx; }
.identity-option { display: flex; align-items: center; padding: 28rpx 24rpx; background: #fff; border-radius: 32rpx; border: 2rpx solid rgba(0,0,0,0.06); position: relative; overflow: hidden; }
.identity-option.active { border-color: transparent; background: linear-gradient(135deg, #1890ff, #5B8FF9); }
.identity-option-left { display: flex; align-items: center; gap: 20rpx; position: relative; z-index: 1; }
.identity-radio { width: 44rpx; height: 44rpx; border-radius: 50%; border: 3rpx solid #ddd; box-sizing: border-box; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.identity-radio.checked { border-color: #1890ff; background: #1890ff; }
.identity-select-icon { width: 24rpx; height: 24rpx; }
.identity-text { display: flex; flex-direction: column; gap: 6rpx; }
.identity-title { font-size: 30rpx; font-weight: 700; color: #090b08; }
.identity-desc { font-size: 24rpx; color: #666; }
</style>
