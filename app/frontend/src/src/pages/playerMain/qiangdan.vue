<template>
	<view class="page">
		<!-- 顶部背景 -->
		<view class="top-bg">
			<view class="top-bg-glow"></view>
			<!-- 导航栏 -->
			<view class="navbar" :style="{paddingTop: statusBarHeight + 'px'}">
				<view class="navbar-left" @click="goBack">
					<up-icon name="arrow-left" size="36" color="#ffffff"></up-icon>
				</view>
				<text class="navbar-title">智能抢单</text>
				<view class="navbar-right" @click="gotoSubscribeLog">
					<text class="subscribe-link">订阅记录</text>
				</view>
			</view>

			<!-- 状态圆盘 -->
			<view class="status-circle-wrap">
				<view class="status-circle" :class="{ active: isRunning }" @click="toggleRun">
					<view class="status-ring"></view>
					<view class="status-inner">
						<view class="play-icon" v-if="!isRunning">
							<up-icon name="play-circle-fill" size="80" color="#ffffff"></up-icon>
						</view>
						<view class="pause-icon" v-else>
							<up-icon name="pause-circle-fill" size="80" color="#ffffff"></up-icon>
						</view>
						<text class="status-text">{{ statusText }}</text>
						<text class="status-sub">{{ statusSubText }}</text>
					</view>
				</view>
			</view>

			<!-- 到期时间 -->
			<view class="expire-bar">
				<text class="expire-label">到期时间：</text>
				<text class="expire-value">{{ expireTime || '--' }}</text>
			</view>

			<!-- 倒计时 -->
			<view class="countdown-bar">
				<view class="countdown-status">
					<up-icon name="play-circle" size="16" color="#3cc51f"></up-icon>
					<text class="countdown-status-text">{{ countdownStatus }}</text>
				</view>
				<view class="countdown-time">
					<text class="time-block">{{ countdown.h }}</text>
					<text class="time-colon">:</text>
					<text class="time-block">{{ countdown.m }}</text>
					<text class="time-colon">:</text>
					<text class="time-block">{{ countdown.s }}</text>
				</view>
			</view>
		</view>

		<!-- 标签页 -->
		<view class="tab-bar">
			<view
				v-for="(tab, index) in tabList"
				:key="index"
				:class="['tab-item', currentTab === index ? 'active' : '']"
				@click="switchTab(index)"
			>
				<up-icon :name="tab.icon" size="20" :color="currentTab === index ? '#3cc51f' : '#999'"></up-icon>
				<text class="tab-text">{{ tab.name }}</text>
			</view>
		</view>

		<!-- 内容区 -->
		<view class="content">
			<!-- 抢单配置 -->
			<view v-if="currentTab === 0" class="tab-content">
				<view class="config-card">
					<view class="config-header">
						<view class="config-title">
							<up-icon name="setting" size="18" color="#3cc51f"></up-icon>
							<text>抢单配置</text>
						</view>
						<view class="config-setting-btn" @click="openClubConfig">设置</view>
					</view>
					<view class="config-empty">当前俱乐部暂无游戏</view>
				</view>

				<view class="config-card">
					<view class="config-header">
						<view class="config-title">
							<up-icon name="star" size="18" color="#3cc51f"></up-icon>
							<text>抢单设置</text>
						</view>
					</view>
					<view class="setting-list">
						<view class="setting-item">
							<view class="setting-left">
								<up-icon name="info-circle" size="20" color="#666"></up-icon>
								<view class="setting-info">
									<text class="setting-name">有备注订单排除</text>
									<text class="setting-desc">开启后不抢带有备注的订单，减少纠纷风险</text>
								</view>
							</view>
							<switch :checked="excludeRemark" @change="excludeRemark = $event.detail.value" color="#3cc51f" />
						</view>
						<view class="setting-item">
							<view class="setting-left">
								<up-icon name="gift" size="20" color="#666"></up-icon>
								<view class="setting-info">
									<text class="setting-name">有增值服务排除</text>
									<text class="setting-desc">开启后不抢有增值服务的订单</text>
								</view>
							</view>
							<switch :checked="excludeValueAdded" @change="excludeValueAdded = $event.detail.value" color="#3cc51f" />
						</view>
						<view class="setting-item">
							<view class="setting-left">
								<up-icon name="search" size="20" color="#666"></up-icon>
								<view class="setting-info">
									<text class="setting-name">商品标题专抢词</text>
									<text class="setting-desc">仅抢取包含指定关键词的订单</text>
								</view>
							</view>
							<view class="setting-action" @click="setKeyword">设置</view>
						</view>
						<view class="setting-item">
							<view class="setting-left">
								<up-icon name="close-circle" size="20" color="#666"></up-icon>
								<view class="setting-info">
									<text class="setting-name">备注关键词排除</text>
									<text class="setting-desc">排除含指定备注的订单</text>
								</view>
							</view>
							<view class="setting-action" @click="setExcludeKeyword">设置</view>
						</view>
					</view>
				</view>
			</view>

			<!-- 权益充值 -->
			<view v-if="currentTab === 1" class="tab-content">
				<view class="recharge-card">
					<view class="recommend-tag">★ 官方推荐 超值特惠</view>
					<view class="recharge-title">1 天体验套餐</view>
					<text class="recharge-desc">1天畅享智能抢单</text>
					<view class="recharge-price">
						<text class="price-symbol">¥</text>
						<text class="price-num">{{ price }}</text>
					</view>
					<view class="feature-list">
						<view class="feature-item">
							<up-icon name="play-circle" size="18" color="#3cc51f"></up-icon>
							<view class="feature-info">
								<text class="feature-name">自动监听</text>
								<text class="feature-desc">接单池新订单</text>
							</view>
						</view>
						<view class="feature-item">
							<up-icon name="lightning" size="18" color="#3cc51f"></up-icon>
							<view class="feature-info">
								<text class="feature-name">毫秒级响应</text>
								<text class="feature-desc">极速抢单</text>
							</view>
						</view>
						<view class="feature-item">
							<up-icon name="shield" size="18" color="#3cc51f"></up-icon>
							<view class="feature-info">
								<text class="feature-name">白名单保护</text>
								<text class="feature-desc">风控安全保障</text>
							</view>
						</view>
						<view class="feature-item">
							<up-icon name="grid" size="18" color="#3cc51f"></up-icon>
							<view class="feature-info">
								<text class="feature-name">多维度筛选</text>
								<text class="feature-desc">游戏智能筛选</text>
							</view>
						</view>
						<view class="feature-item">
							<up-icon name="arrow-upward" size="18" color="#3cc51f"></up-icon>
							<view class="feature-info">
								<text class="feature-name">优先级加持</text>
								<text class="feature-desc">抢单记录追溯</text>
							</view>
						</view>
						<view class="feature-item">
							<up-icon name="file-text" size="18" color="#3cc51f"></up-icon>
							<view class="feature-info">
								<text class="feature-name">全程可追溯</text>
								<text class="feature-desc">日志记录可查</text>
							</view>
						</view>
					</view>
					<view class="recharge-btn" @click="recharge">立即开通</view>
				</view>
			</view>

			<!-- 抢单日志 -->
			<view v-if="currentTab === 2" class="tab-content">
				<view class="log-card">
					<view class="log-header">
						<view class="config-title">
							<up-icon name="file-text" size="18" color="#3cc51f"></up-icon>
							<text>今日统计</text>
						</view>
						<text class="log-tip">拉单间隔2s·抢单间隔1s·每次拉取前10条</text>
					</view>
					<view class="stat-row">
						<view class="stat-box">
							<text class="stat-num">{{ totalGrabbed }}</text>
							<text class="stat-label">累计成功抢到订单</text>
						</view>
						<view class="stat-box">
							<text class="stat-num">{{ todayGrabbed }}</text>
							<text class="stat-label">今日成功抢到</text>
						</view>
					</view>
				</view>

				<view class="log-card">
					<view class="config-title">
						<up-icon name="file-text" size="18" color="#3cc51f"></up-icon>
						<text>抢单日志</text>
					</view>
					<view class="log-tabs">
					<view :class="['log-tab', logTab === 0 ? 'active' : '']" @click="switchLogTab(0)">全部抢单日志</view>
					<view :class="['log-tab', logTab === 1 ? 'active' : '']" @click="switchLogTab(1)">今日抢单日志</view>
				</view>
				<view class="log-list" v-if="logList.length">
					<view class="log-item" v-for="(item, idx) in logList" :key="item.id || idx">
						<view class="log-item-main">
							<text class="log-item-name">{{ item.goods_name || '--' }}</text>
							<text class="log-item-no">单号：{{ item.order_no || '--' }}</text>
						</view>
						<view class="log-item-right">
							<text class="log-item-amount">¥{{ item.amount || '0' }}</text>
							<text class="log-item-status" :class="item.status === 'success' ? 'success' : (item.status === 'fail' ? 'fail' : 'pending')">{{ item.status === 'success' ? '成功' : (item.status === 'fail' ? '失败' : '处理中') }}</text>
						</view>
					</view>
				</view>
				<view class="log-empty" v-else>
					<image class="empty-img" src="/static/images/right.png" mode="aspectFit"></image>
					<text class="empty-text">暂无抢单日志</text>
				</view>
			</view>
			</view>
		</view>

		<!-- 俱乐部配置弹窗 -->
		<view class="popup-mask" v-if="showClubPopup" @click="closeClubPopup">
			<view class="popup-content" @click.stop>
				<view class="popup-header">
					<text class="popup-title">俱乐部配置</text>
					<view class="popup-close" @click="closeClubPopup">×</view>
				</view>
				<view class="popup-body">
					<view class="club-empty">暂无俱乐部</view>
				</view>
				<view class="popup-footer">
					<view class="popup-btn reset" @click="resetClubConfig">重置</view>
					<view class="popup-btn confirm" @click="saveClubConfig">保存设置</view>
				</view>
			</view>
		</view>
	</view>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import router from '../../utils/router'
import payUtil from '../../utils/pay'

const statusBarHeight = ref(44)
try {
	const sysInfo = uni.getSystemInfoSync()
	statusBarHeight.value = sysInfo.statusBarHeight || 44
} catch (e) {}

const isRunning = ref(false)
const expireTime = ref('')
const expireTimestamp = ref(0)
const countdown = ref({ h: '00', m: '00', s: '00' })
const currentTab = ref(0)
const logTab = ref(0)
const excludeRemark = ref(false)
const excludeValueAdded = ref(false)
const totalGrabbed = ref(0)
const todayGrabbed = ref(0)
const showClubPopup = ref(false)
const isSubscribed = ref(false)
const isFree = ref(false)
const qiangdanEnabled = ref(true)
const price = ref(25)
const logList = ref([])

const tabList = [
	{ name: '抢单配置', icon: 'setting' },
	{ name: '权益充值', icon: 'rmb-circle' },
	{ name: '抢单日志', icon: 'file-text' }
]

let timer = null
let runSeconds = 0

const statusText = computed(() => {
	if (!qiangdanEnabled.value) return '未开放'
	if (isFree.value) return '免费版'
	if (isSubscribed.value) return (isRunning.value ? '抢单中' : '已开通')
	return '未购买'
})
const statusSubText = computed(() => {
	if (!qiangdanEnabled.value) return '功能暂未开放'
	if (isFree.value) return (isRunning.value ? '正在自动抢单' : '免费享抢单权益')
	if (isSubscribed.value) return (isRunning.value ? '正在自动抢单' : '点击开启抢单')
	return '请先开通权益'
})
const countdownStatus = computed(() => isRunning.value ? '抢单中' : (isSubscribed.value ? '待机中' : '未购买'))

onMounted(() => {
	loadInfo()
	loadLogs()
	startCountdown()
})

onShow(() => {
	loadInfo()
})

onUnmounted(() => {
	if (timer) clearInterval(timer)
})

const loadInfo = async () => {
	try {
		const thugId = uni.getStorageSync('id') || 1
		const token = uni.getStorageSync('token') || ''
		const resp = await fetch(`/api/qiangdan/info?thug_id=${thugId}`, {
			headers: { 'token': token }
		})
		const res = await resp.json()
		if (res.code === 1 && res.data) {
			const d = res.data
			qiangdanEnabled.value = !!d.enabled
			price.value = d.price || 25
			isSubscribed.value = !!d.is_subscribed
			isFree.value = !!d.is_free
			expireTime.value = d.expire_text || ''
			expireTimestamp.value = d.expire_time || 0
			isRunning.value = !!d.qiangdan_status
			excludeRemark.value = d.config?.exclude_remark || false
			excludeValueAdded.value = d.config?.exclude_value_added || false
		}
	} catch (e) {
		console.log('加载抢单信息失败', e)
	}
}

const startCountdown = () => {
	timer = setInterval(() => {
		if (!isRunning.value) {
			countdown.value = { h: '00', m: '00', s: '00' }
			return
		}
		runSeconds++
		const h = String(Math.floor(runSeconds / 3600)).padStart(2, '0')
		const m = String(Math.floor((runSeconds % 3600) / 60)).padStart(2, '0')
		const s = String(runSeconds % 60).padStart(2, '0')
		countdown.value = { h, m, s }
	}, 1000)
}

const toggleRun = async () => {
	if (!qiangdanEnabled.value) {
		uni.showToast({ title: '功能暂未开放', icon: 'none' })
		return
	}
	if (!isSubscribed.value && !isFree.value) {
		uni.showToast({ title: '请先开通权益', icon: 'none' })
		currentTab.value = 1
		return
	}
	try {
		const newStatus = isRunning.value ? 0 : 1
		const resp = await fetch('/api/qiangdan/toggle', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'token': uni.getStorageSync('token') || '' },
			body: JSON.stringify({ thug_id: uni.getStorageSync('id') || 1, status: newStatus })
		})
		const res = await resp.json()
		if (res.code === 1) {
			isRunning.value = !!newStatus
			if (!newStatus) runSeconds = 0
		}
	} catch (e) {
		uni.showToast({ title: '操作失败', icon: 'none' })
	}
}

const switchTab = (index) => {
	currentTab.value = index
	if (index === 2) loadLogs()
}

const switchLogTab = (tab) => {
	if (logTab.value === tab) return
	logTab.value = tab
	loadLogs()
}

const goBack = () => { uni.navigateBack() }

const gotoSubscribeLog = () => {
	uni.showToast({ title: '暂无订阅记录', icon: 'none' })
}

const openClubConfig = () => { showClubPopup.value = true }
const closeClubPopup = () => { showClubPopup.value = false }
const resetClubConfig = () => {
	excludeRemark.value = false
	excludeValueAdded.value = false
	uni.showToast({ title: '已重置', icon: 'none' })
}
const saveClubConfig = async () => {
	try {
		const resp = await fetch('/api/qiangdan/saveConfig', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'token': uni.getStorageSync('token') || '' },
			body: JSON.stringify({
				thug_id: uni.getStorageSync('id') || 1,
				exclude_remark: excludeRemark.value ? 1 : 0,
				exclude_value_added: excludeValueAdded.value ? 1 : 0,
			})
		})
		const res = await resp.json()
		if (res.code === 1) {
			showClubPopup.value = false
			uni.showToast({ title: '保存成功', icon: 'success' })
		}
	} catch (e) {
		uni.showToast({ title: '保存失败', icon: 'none' })
	}
}

const setKeyword = () => {
	uni.showToast({ title: '功能开发中', icon: 'none' })
}

const setExcludeKeyword = () => {
	uni.showToast({ title: '功能开发中', icon: 'none' })
}

const recharge = async () => {
	if (!qiangdanEnabled.value) {
		uni.showToast({ title: '功能暂未开放', icon: 'none' })
		return
	}
	uni.showModal({
		title: '开通抢单神器',
		content: `开通1天抢单神器，费用 ¥${price.value}`,
		success: async (res) => {
			if (!res.confirm) return
			uni.showLoading({ mask: true, title: '提交中' })
			try {
				const resp = await fetch('/api/qiangdan/purchase', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'token': uni.getStorageSync('token') || '' },
					body: JSON.stringify({ thug_id: uni.getStorageSync('id') || 1, days: 1 })
				})
				const result = await resp.json()
				uni.hideLoading()
				if (result.code === 1 && result.data) {
					// 免费打手直接开通
					if (result.data.expire_time) {
						uni.showToast({ title: '开通成功', icon: 'success' })
						loadInfo()
						return
					}
					payUtil.redirectPay(result.data, (err) => {
						uni.showToast({ title: err || '无法打开支付', icon: 'none' })
					})
				}
			} catch (e) {
				uni.hideLoading()
				uni.showToast({ title: '下单失败', icon: 'none' })
			}
		}
	})
}

const loadLogs = async () => {
	try {
		const type = logTab.value === 1 ? 'today' : 'all'
		const thugId = uni.getStorageSync('id') || 1
		const resp = await fetch(`/api/qiangdan/logs?thug_id=${thugId}&type=${type}`, {
			headers: { 'token': uni.getStorageSync('token') || '' }
		})
		const res = await resp.json()
		if (res.code === 1 && res.data) {
			logList.value = res.data.list || []
			totalGrabbed.value = res.data.total_grabbed || 0
			todayGrabbed.value = res.data.today_grabbed || 0
		}
	} catch (e) {
		console.log('加载日志失败', e)
	}
}
</script>

<style scoped>
.page { min-height: 100vh; background: #f5f7fa; }
.top-bg { position: relative; background: linear-gradient(180deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); padding-bottom: 40rpx; overflow: hidden; }
.top-bg-glow { position: absolute; top: -100rpx; left: 50%; transform: translateX(-50%); width: 600rpx; height: 600rpx; background: radial-gradient(circle, rgba(60, 197, 31, 0.3) 0%, transparent 70%); border-radius: 50%; }
.navbar { position: relative; z-index: 2; display: flex; align-items: center; justify-content: space-between; padding: 20rpx 24rpx; }
.navbar-left, .navbar-right { width: 120rpx; display: flex; align-items: center; }
.navbar-right { justify-content: flex-end; }
.navbar-title { font-size: 34rpx; font-weight: 600; color: #fff; }
.subscribe-link { font-size: 26rpx; color: rgba(255,255,255,0.8); }

.status-circle-wrap { display: flex; justify-content: center; padding: 40rpx 0; position: relative; z-index: 2; }
.status-circle { width: 320rpx; height: 320rpx; border-radius: 50%; background: linear-gradient(135deg, #2d5a27 0%, #1a3d15 100%); display: flex; align-items: center; justify-content: center; position: relative; box-shadow: 0 0 60rpx rgba(60, 197, 31, 0.3); }
.status-circle.active { background: linear-gradient(135deg, #3cc51f 0%, #2d9a18 100%); box-shadow: 0 0 80rpx rgba(60, 197, 31, 0.6); }
.status-ring { position: absolute; inset: -20rpx; border-radius: 50%; border: 4rpx dashed rgba(60, 197, 31, 0.5); animation: spin 8s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.status-inner { display: flex; flex-direction: column; align-items: center; gap: 10rpx; }
.play-icon, .pause-icon { margin-bottom: 10rpx; }
.status-text { font-size: 40rpx; font-weight: 700; color: #fff; }
.status-sub { font-size: 24rpx; color: rgba(255,255,255,0.7); }

.expire-bar { display: flex; justify-content: center; align-items: center; padding: 16rpx 0; position: relative; z-index: 2; }
.expire-label { font-size: 26rpx; color: rgba(255,255,255,0.6); }
.expire-value { font-size: 26rpx; color: #3cc51f; font-weight: 600; }

.countdown-bar { display: flex; justify-content: space-between; align-items: center; margin: 20rpx 60rpx 0; padding: 20rpx 32rpx; background: rgba(255,255,255,0.1); border-radius: 50rpx; position: relative; z-index: 2; }
.countdown-status { display: flex; align-items: center; gap: 8rpx; }
.countdown-status-text { font-size: 24rpx; color: #3cc51f; }
.countdown-time { display: flex; align-items: center; gap: 4rpx; }
.time-block { min-width: 56rpx; height: 56rpx; background: #1a1a1a; color: #3cc51f; font-size: 32rpx; font-weight: 700; border-radius: 10rpx; display: flex; align-items: center; justify-content: center; }
.time-colon { color: #3cc51f; font-size: 28rpx; font-weight: 700; }

.tab-bar { display: flex; background: #fff; margin: 24rpx; border-radius: 20rpx; padding: 8rpx; box-shadow: 0 2rpx 12rpx rgba(0,0,0,0.04); }
.tab-item { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6rpx; padding: 20rpx 0; border-radius: 16rpx; transition: all 0.3s; }
.tab-item.active { background: linear-gradient(135deg, #e8f8e4 0%, #d4f0cc 100%); }
.tab-text { font-size: 26rpx; color: #999; }
.tab-item.active .tab-text { color: #3cc51f; font-weight: 600; }

.content { padding: 0 24rpx 40rpx; }
.tab-content { display: flex; flex-direction: column; gap: 24rpx; }

.config-card, .log-card, .recharge-card { background: #fff; border-radius: 24rpx; padding: 32rpx; box-shadow: 0 2rpx 12rpx rgba(0,0,0,0.04); }
.config-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24rpx; }
.config-title { display: flex; align-items: center; gap: 10rpx; font-size: 28rpx; font-weight: 600; color: #333; }
.config-setting-btn { font-size: 24rpx; color: #3cc51f; padding: 8rpx 24rpx; border: 1rpx solid #3cc51f; border-radius: 30rpx; }
.config-empty { text-align: center; color: #999; font-size: 26rpx; padding: 40rpx 0; }

.setting-list { display: flex; flex-direction: column; }
.setting-item { display: flex; align-items: center; justify-content: space-between; padding: 28rpx 0; border-bottom: 1rpx solid #f5f5f5; }
.setting-item:last-child { border-bottom: none; }
.setting-left { display: flex; align-items: center; gap: 20rpx; flex: 1; }
.setting-info { display: flex; flex-direction: column; gap: 6rpx; }
.setting-name { font-size: 28rpx; color: #333; font-weight: 500; }
.setting-desc { font-size: 22rpx; color: #999; }
.setting-action { font-size: 24rpx; color: #3cc51f; padding: 8rpx 24rpx; border: 1rpx solid #3cc51f; border-radius: 30rpx; }

.recommend-tag { display: inline-block; padding: 8rpx 20rpx; background: linear-gradient(90deg, #ff9500, #ff5e3a); color: #fff; font-size: 22rpx; border-radius: 30rpx; margin-bottom: 20rpx; }
.recharge-title { font-size: 40rpx; font-weight: 700; color: #333; }
.recharge-desc { font-size: 26rpx; color: #999; margin-top: 8rpx; }
.recharge-price { display: flex; align-items: baseline; margin: 30rpx 0; }
.price-symbol { font-size: 36rpx; color: #3cc51f; font-weight: 700; }
.price-num { font-size: 80rpx; color: #3cc51f; font-weight: 800; line-height: 1; }
.feature-list { display: flex; flex-wrap: wrap; gap: 24rpx; margin-bottom: 30rpx; }
.feature-item { width: 50%; display: flex; align-items: center; gap: 12rpx; }
.feature-info { display: flex; flex-direction: column; }
.feature-name { font-size: 26rpx; color: #333; font-weight: 500; }
.feature-desc { font-size: 20rpx; color: #999; }
.recharge-btn { width: 100%; height: 88rpx; background: linear-gradient(135deg, #1a1a1a 0%, #333 100%); color: #3cc51f; font-size: 32rpx; font-weight: 700; border-radius: 44rpx; display: flex; align-items: center; justify-content: center; }

.log-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24rpx; }
.log-tip { font-size: 20rpx; color: #999; }
.stat-row { display: flex; gap: 24rpx; }
.stat-box { flex: 1; background: #f8f9fa; border-radius: 16rpx; padding: 30rpx; text-align: center; }
.stat-num { display: block; font-size: 48rpx; font-weight: 700; color: #3cc51f; }
.stat-label { font-size: 22rpx; color: #999; margin-top: 8rpx; }
.log-tabs { display: flex; gap: 16rpx; margin: 24rpx 0; }
.log-tab { padding: 12rpx 32rpx; background: #f5f5f5; border-radius: 30rpx; font-size: 24rpx; color: #666; }
.log-tab.active { background: #e8f8e4; color: #3cc51f; font-weight: 600; }
.log-list { display: flex; flex-direction: column; }
.log-item { display: flex; align-items: center; justify-content: space-between; padding: 24rpx 0; border-bottom: 1rpx solid #f5f5f5; }
.log-item:last-child { border-bottom: none; }
.log-item-main { display: flex; flex-direction: column; gap: 8rpx; flex: 1; }
.log-item-name { font-size: 28rpx; color: #333; font-weight: 500; }
.log-item-no { font-size: 22rpx; color: #999; }
.log-item-right { display: flex; flex-direction: column; align-items: flex-end; gap: 8rpx; }
.log-item-amount { font-size: 30rpx; color: #3cc51f; font-weight: 700; }
.log-item-status { font-size: 22rpx; padding: 4rpx 16rpx; border-radius: 20rpx; }
.log-item-status.success { color: #3cc51f; background: #e8f8e4; }
.log-item-status.fail { color: #e64340; background: #fde8e8; }
.log-item-status.pending { color: #ff9500; background: #fff4e0; }
.log-empty { display: flex; flex-direction: column; align-items: center; padding: 60rpx 0; }
.empty-img { width: 120rpx; height: 120rpx; opacity: 0.3; margin-bottom: 20rpx; }
.empty-text { font-size: 26rpx; color: #999; }

.popup-mask { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; display: flex; align-items: flex-end; }
.popup-content { width: 100%; background: #fff; border-radius: 32rpx 32rpx 0 0; padding: 32rpx; animation: slideUp 0.3s ease; }
@keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
.popup-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 32rpx; }
.popup-title { font-size: 34rpx; font-weight: 700; color: #333; }
.popup-close { font-size: 48rpx; color: #999; line-height: 1; }
.popup-body { min-height: 300rpx; }
.club-empty { text-align: center; color: #999; font-size: 28rpx; padding: 80rpx 0; }
.popup-footer { display: flex; gap: 24rpx; margin-top: 32rpx; }
.popup-btn { flex: 1; height: 88rpx; border-radius: 44rpx; display: flex; align-items: center; justify-content: center; font-size: 30rpx; font-weight: 600; }
.popup-btn.reset { background: #f5f5f5; color: #666; }
.popup-btn.confirm { background: linear-gradient(135deg, #1a1a1a 0%, #333 100%); color: #3cc51f; }
</style>
