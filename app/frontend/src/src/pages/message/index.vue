<template>
	<view class="msg-page">
		<commonTabbar :current="3" :placeholder="false"></commonTabbar>
		<view class="msg-box">
			<!-- 顶部入口卡片：系统通知 / 平台人工客服 -->
			<view class="entry-card">
				<view class="entry-item" @click="gotoNoticeList">
					<view class="entry-icon notice-icon">
						<uv-icon name="bell" size="24" color="#fff"></uv-icon>
					</view>
					<view class="entry-info">
						<text class="entry-name">系统通知</text>
						<text class="entry-desc">{{ noticeUnreadText }}</text>
					</view>
					<view class="entry-arrow">
						<text class="arrow-text">›</text>
					</view>
				</view>
				<view class="entry-divider"></view>
				<view class="entry-item" @click="contactCustomerService">
					<view class="entry-icon cs-icon">
						<uv-icon name="server-man" size="24" color="#fff"></uv-icon>
					</view>
					<view class="entry-info">
						<text class="entry-name">平台人工客服</text>
						<text class="entry-desc">有问题点我联系人工客服!</text>
					</view>
					<view class="entry-arrow">
						<text class="arrow-text">›</text>
					</view>
				</view>
			</view>

			<!-- 咨询状态切换 咨询中/全部 -->
			<view class="state-toggle">
				<view class="toggle-item" :class="{ active: consultState === 'consulting' }" @click="switchConsult('consulting')">
					<text>咨询中</text>
				</view>
				<view class="toggle-item" :class="{ active: consultState === 'all' }" @click="switchConsult('all')">
					<text>全部</text>
				</view>
			</view>

			<!-- 订单状态筛选 tab -->
			<scroll-view scroll-x class="filter-tabs" :show-scrollbar="false">
				<view class="filter-tab" v-for="(tab, idx) in filterTabs" :key="idx"
					:class="{ active: currentFilter === idx }" @click="switchFilter(idx)">
					<text>{{ tab.name }}</text>
				</view>
			</scroll-view>

			<!-- 订单聊天列表 -->
			<view class="chat-list-box">
				<view class="chat-item" v-for="(item, idx) in filteredChatList" :key="item.id"
					@click="gotoChatDetail(item)">
					<view class="chat-avatar-wrap">
						<image class="chat-avatar" :src="item.logo || '/static/yun/default_avatar.png'" mode="aspectFill"></image>
						<view class="chat-avatar-badge" :class="'badge-' + getChatRole(item).cls">
							<text class="chat-avatar-badge-text">{{ getChatRole(item).label }}</text>
						</view>
						<view class="chat-unread" v-if="!item.is_read">
							<text class="chat-unread-text">{{ item.unread_count || '●' }}</text>
						</view>
					</view>
					<view class="chat-info">
						<view class="chat-row-top">
							<text class="chat-name">{{ item.title || item.nickname || '订单通知' }}</text>
							<text class="chat-time">{{ item.time_text || '' }}</text>
						</view>
						<view class="chat-row-bottom">
							<text class="chat-content">{{ item.last_msg || item.status_text || '点击查看详情' }}</text>
							<view class="chat-tag" v-if="item.status_text">{{ item.status_text }}</view>
						</view>
					</view>
				</view>
				<!-- 空状态 -->
				<view class="chat-empty" v-if="!orderLoading && filteredChatList.length === 0">
					<view class="chat-empty-icon">
						<uv-icon name="chat" size="40" color="#ccc"></uv-icon>
					</view>
					<text class="chat-empty-text">暂无聊天对话</text>
				</view>
				<view class="chat-loading" v-if="orderLoading">
					<text>加载中...</text>
				</view>
			</view>
		</view>
		<view style="height: 150rpx;"></view>
	</view>
</template>

<script setup>
	import { ref, computed, onMounted } from 'vue';
	import { onShow } from '@dcloudio/uni-app';
	import { noticeList, orderList } from '../../request/api';
	import { openCustomerServiceChat, resolveCustomerService } from '../../utils/customer-service';
	import { getCsSessions, checkTimeoutSessions, markCsSessionRead } from '../../utils/cs-session';
	import router from '../../utils/router';

	const noticeData = ref([])
	const orderChatList = ref([])
	const csSessionList = ref([])
	const noticeLoading = ref(true)
	const orderLoading = ref(true)
	const consultState = ref('consulting')  // consulting | all
	const currentFilter = ref(0)  // 0=全部
	const filterTabs = ref([
		{ name: '全部', value: '' },
		{ name: '待接单', value: '0' },
		{ name: '服务中', value: '1' },
		{ name: '待结单', value: '2' },
		{ name: '已完成', value: '3' },
		{ name: '售后中', value: '4' }
	])

	const noticeUnreadText = computed(() => {
		if (noticeLoading.value) return '加载中...'
		if (noticeData.value.length === 0) return '暂无新通知'
		return `${noticeData.value[0].title || '点击查看'}`
	})

	const filteredChatList = computed(() => {
		const statusValue = filterTabs.value[currentFilter.value].value
		// 合并订单会话和客服会话
		let list = [...orderChatList.value]
		// 客服会话统一标记
		const csItems = csSessionList.value.map(s => ({
			id: 'cs_' + s.cs_id,
			cs_id: s.cs_id,
			is_cs: true,
			title: s.cs_name || '客服',
			nickname: s.cs_name || '客服',
			logo: s.cs_avatar || '/static/yun/default_avatar.png',
			last_msg: s.last_msg || '点击开始咨询',
			status: s.status === 'active' ? '1' : '3',
			status_text: s.status === 'active' ? '咨询中' : '已结束',
			time_text: formatTimeText(s.last_time),
			is_read: s.unread === 0,
			unread_count: s.unread || 0
		}))
		list = list.concat(csItems)
		// 按时间倒序
		list.sort((a, b) => (b.last_time || 0) - (a.last_time || 0))
		if (consultState.value === 'consulting') {
			// 咨询中：只显示状态为 0/1/2 的（待接单/服务中/待结单）以及活跃的客服会话
			list = list.filter(i => {
				if (i.is_cs) return i.status === '1' // 客服会话活跃才显示
				return ['0', '1', '2'].includes(String(i.status))
			})
		}
		if (statusValue) {
			list = list.filter(i => String(i.status) === statusValue)
		}
		return list
	})

	// 会话角色类型标识（打手/客服/订单/系统）
	const getChatRole = (item) => {
		if (item.is_cs) return { label: '客服', cls: 'cs' }
		if (item.is_player || item.is_thug || item.player_id || item.thug_id) return { label: '打手', cls: 'thug' }
		if (item.order_no) return { label: '订单', cls: 'order' }
		if (item.type) return { label: '系统', cls: 'sys' }
		return { label: '订单', cls: 'order' }
	}

	onMounted(() => {
		loadNotice()
		loadCsSessions()
		if (uni.getStorageSync('token')) {
			loadOrderChat()
		} else {
			orderLoading.value = false
		}
	})

	onShow(() => {
		// 检查超时会话（5分钟自动结束）
		checkTimeoutSessions()
		loadCsSessions()
		if (uni.getStorageSync('token')) {
			loadOrderChat()
		}
		loadNotice()
	})

	const loadNotice = () => {
		noticeLoading.value = true
		noticeList({ type: '1', page: 1, limit: 20 }).then(res => {
			const list = res.data.data || res.data || []
			const readIds = getNoticeReadIds()
			list.forEach(item => {
				item.is_read = readIds.includes(String(item.id))
			})
			noticeData.value = list
		}).catch(() => {
			noticeData.value = []
		}).finally(() => {
			noticeLoading.value = false
		})
	}

	const getNoticeReadIds = () => {
		try {
			const ids = uni.getStorageSync('notice_read_ids')
			return ids ? JSON.parse(ids) : []
		} catch (e) {
			return []
		}
	}

	const loadOrderChat = () => {
		orderLoading.value = true
		orderList({ page: 1, limit: 50 }).then(res => {
			const list = res.data.data || res.data || []
			const readIds = getOrderReadIds()
			list.forEach(item => {
				item.is_read = readIds.includes(String(item.id))
				item.time_text = formatTimeText(item.create_time || item.createtime)
				item.last_msg = item.last_msg || item.status_text || ''
			})
			orderChatList.value = list
		}).catch(() => {
			orderChatList.value = []
		}).finally(() => {
			orderLoading.value = false
		})
	}

	const formatTimeText = (t) => {
		if (!t) return ''
		// 兼容时间戳（数字）和字符串时间
		if (typeof t === 'number') {
			const d = new Date(t)
			return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
		}
		return String(t).substring(11, 16) || ''
	}

	const loadCsSessions = () => {
		csSessionList.value = getCsSessions()
	}

	const getOrderReadIds = () => {
		try {
			const ids = uni.getStorageSync('order_read_ids')
			return ids ? JSON.parse(ids) : []
		} catch (e) {
			return []
		}
	}

	const switchConsult = (state) => {
		consultState.value = state
	}

	const switchFilter = (idx) => {
		currentFilter.value = idx
	}

	const gotoNoticeList = () => {
		router.navigateTo({
			url: 'pagesA/mine/notice/index'
		})
	}

	const contactCustomerService = async () => {
		const token = uni.getStorageSync('token')
		if (!token) {
			router.navigateTo({ url: 'pages/login/login' })
			return
		}
		let r = { direct: null, list: [] }
		try {
			uni.showLoading({ title: '加载客服...', mask: true })
			r = await resolveCustomerService()
		} catch (e) {
			r = { direct: null, list: [] }
		} finally {
			uni.hideLoading()
		}
		// 有内置客服则直接进入内置客服会话
		if (r.direct) { openCustomerServiceChat(r.direct); return }
		const list = r.list || []
		if (list.length === 0) {
			uni.showToast({ title: '暂无客服在线', icon: 'none' })
			return
		}
		const csItem = list.find(i => i.type === 'im' && i.user_id) || list[0]
		if (csItem) {
			// 跳到聊天详情页
			router.navigateTo({
				url: 'pages/message/chat',
				data: {
					cs_id: csItem.id,
					cs_name: csItem.name,
					cs_avatar: csItem.avatar
				}
			})
		}
	}

	const gotoChatDetail = (item) => {
		// 客服会话
		if (item.is_cs) {
			markCsSessionRead(item.cs_id)
			loadCsSessions()
			router.navigateTo({
				url: 'pages/message/chat',
				data: {
					cs_id: item.cs_id,
					cs_name: item.title,
					cs_avatar: item.logo
				}
			})
			return
		}
		// 订单会话
		const ids = getOrderReadIds()
		const idStr = String(item.id)
		if (!ids.includes(idStr)) {
			ids.push(idStr)
			uni.setStorageSync('order_read_ids', JSON.stringify(ids))
		}
		item.is_read = true
		router.navigateTo({
			url: 'pages/message/chat',
			data: {
				order_id: item.id,
				order_no: item.order_no,
				title: item.title,
				logo: item.logo
			}
		})
	}
</script>

<style lang="scss">
	.msg-page {
		min-height: 100vh;
		background: linear-gradient(180deg, #FF7A45 0%, #FFFFFF 35%);
	}

	.msg-box {
		padding: 20rpx;
		padding-top: 80rpx;
	}

	/* 顶部入口卡片 */
	.entry-card {
		background: #FFFFFF;
		border-radius: 24rpx;
		padding: 8rpx 24rpx;
		box-shadow: 0 4rpx 20rpx rgba(255, 122, 69, 0.12);
	}

	.entry-item {
		display: flex;
		flex-direction: row !important;
		align-items: center;
		padding: 24rpx 0;
		gap: 20rpx;
	}

	.entry-divider {
		height: 1rpx;
		background: #f0f0f0;
		margin: 0;
	}

	.entry-icon {
		width: 80rpx;
		height: 80rpx;
		border-radius: 50%;
		display: flex;
		justify-content: center;
		align-items: center;
		flex-shrink: 0;
	}

	.notice-icon {
		background: linear-gradient(135deg, #FF7A45 0%, #FFA07A 100%);
	}

	.cs-icon {
		background: linear-gradient(135deg, #FF7A45 0%, #FFA07A 100%);
	}

	.entry-icon .iconfont {
		font-size: 40rpx;
		color: #fff;
	}

	.entry-info {
		flex: 1;
		display: flex;
		flex-direction: column !important;
		gap: 6rpx;
		min-width: 0;
	}

	.entry-name {
		font-size: 30rpx;
		font-weight: 600;
		color: #333;
	}

	.entry-desc {
		font-size: 22rpx;
		color: #999;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.entry-arrow {
		flex-shrink: 0;
		margin-left: 12rpx;
	}

	.arrow-text {
		font-size: 36rpx;
		color: #ccc;
		line-height: 1;
	}

	/* 咨询状态切换 */
	.state-toggle {
		display: flex;
		flex-direction: row !important;
		justify-content: center;
		margin: 32rpx 0 20rpx;
		gap: 0;
		background: #FFFFFF;
		border-radius: 999rpx;
		padding: 4rpx;
		width: 320rpx;
		margin-left: auto;
		margin-right: auto;
		box-shadow: 0 2rpx 10rpx rgba(0, 0, 0, 0.06);
	}

	.toggle-item {
		flex: 1;
		text-align: center;
		padding: 12rpx 0;
		font-size: 26rpx;
		color: #999;
		border-radius: 999rpx;
		transition: all 0.2s;
	}

	.toggle-item.active {
		background: #FF7A45;
		color: #fff;
		font-weight: 600;
	}

	/* 订单状态筛选 tab */
	.filter-tabs {
		white-space: nowrap;
		padding: 12rpx 0;
		margin-bottom: 16rpx;
	}

	.filter-tab {
		display: inline-block;
		padding: 12rpx 28rpx;
		margin-right: 8rpx;
		font-size: 26rpx;
		color: #999;
		position: relative;
	}

	.filter-tab.active {
		color: #FF7A45;
		font-weight: 700;
	}

	.filter-tab.active::after {
		content: '';
		position: absolute;
		left: 50%;
		transform: translateX(-50%);
		bottom: 4rpx;
		width: 32rpx;
		height: 4rpx;
		background: #FF7A45;
		border-radius: 2rpx;
	}

	/* 订单聊天列表 */
	.chat-list-box {
		background: transparent;
		padding: 0;
	}

	.chat-item {
		display: flex;
		flex-direction: row !important;
		align-items: center;
		padding: 24rpx 24rpx;
		background: #FFFFFF;
		border-radius: 20rpx;
		margin-bottom: 16rpx;
		gap: 20rpx;
		box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.05);
	}

	.chat-avatar-wrap {
		position: relative;
		flex-shrink: 0;
	}

	.chat-avatar {
		width: 88rpx;
		height: 88rpx;
		border-radius: 24rpx;
	}

	/* 会话角色类型徽章 */
	.chat-avatar-badge {
		position: absolute;
		right: -6rpx;
		bottom: -6rpx;
		min-width: 36rpx;
		height: 36rpx;
		padding: 0 8rpx;
		border-radius: 36rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		border: 2rpx solid #fff;
		box-sizing: border-box;
		box-shadow: 0 2rpx 6rpx rgba(0, 0, 0, 0.15);
	}

	.chat-avatar-badge-text {
		font-size: 18rpx;
		color: #fff;
		font-weight: 600;
		line-height: 1;
	}

	.badge-thug { background: #FF9F1C; }
	.badge-cs { background: #2FBF71; }
	.badge-order { background: #3B82F6; }
	.badge-sys { background: #9AA5B1; }

	.chat-unread {
		position: absolute;
		top: -8rpx;
		right: -8rpx;
		min-width: 28rpx;
		height: 28rpx;
		padding: 0 6rpx;
		background: #ff4d4f;
		border-radius: 28rpx;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.chat-unread-text {
		font-size: 18rpx;
		color: #fff;
		font-weight: 600;
	}

	.chat-info {
		flex: 1;
		display: flex;
		flex-direction: column !important;
		gap: 8rpx;
		min-width: 0;
	}

	.chat-row-top {
		display: flex;
		flex-direction: row !important;
		justify-content: space-between;
		align-items: center;
	}

	.chat-row-bottom {
		display: flex;
		flex-direction: row !important;
		justify-content: space-between;
		align-items: center;
	}

	.chat-name {
		font-size: 28rpx;
		font-weight: 600;
		color: #333;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		flex: 1;
	}

	.chat-time {
		font-size: 20rpx;
		color: #bbb;
		flex-shrink: 0;
		margin-left: 12rpx;
	}

	.chat-content {
		font-size: 22rpx;
		color: #999;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		flex: 1;
	}

	.chat-tag {
		font-size: 18rpx;
		color: #FF7A45;
		background: rgba(255, 122, 69, 0.12);
		padding: 4rpx 12rpx;
		border-radius: 8rpx;
		flex-shrink: 0;
		margin-left: 12rpx;
	}

	/* 空状态 */
	.chat-empty {
		width: 100%;
		padding: 120rpx 0 80rpx;
		display: flex;
		flex-direction: column !important;
		align-items: center;
		gap: 24rpx;
	}

	.chat-empty-icon {
		width: 120rpx;
		height: 120rpx;
		border-radius: 24rpx;
		background: #f5f5f5;
		display: flex;
		justify-content: center;
		align-items: center;
	}

	.chat-empty-icon .iconfont {
		font-size: 56rpx;
		color: #ccc;
	}

	.chat-empty-text {
		font-size: 26rpx;
		color: #bbb;
	}

	.chat-loading {
		text-align: center;
		padding: 40rpx 0;
		color: #999;
		font-size: 24rpx;
	}
</style>
