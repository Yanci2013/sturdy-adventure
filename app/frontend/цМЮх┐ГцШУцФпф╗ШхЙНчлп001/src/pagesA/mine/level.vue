<template>
	<view class="page">
		<!-- 顶部背景图 -->
		<image class="top-bg" src="/static/images/header_background.png" mode="aspectFill"></image>

		<!-- 胶囊导航栏 -->
		<view class="navbar">
			<view class="navbar-capsule">
				<view class="navbar-btn" @click="goBack">
					<up-icon name="arrow-left" size="34rpx" color="#606266"></up-icon>
				</view>
				<view class="navbar-divider"></view>
				<view class="navbar-btn" @click="goHome">
					<up-icon name="home" size="36rpx" color="#606266"></up-icon>
				</view>
			</view>
			<text class="navbar-title">会员等级</text>
			<view class="navbar-placeholder"></view>
		</view>

		<scroll-view scroll-y class="content">
			<view class="content-inner">
				<!-- 等级 Banner 轮播 -->
				<view class="card card-glass swiper-card">
					<swiper class="level-swiper" :current="current" :circular="false"
						:indicator-dots="true" :indicator-color="'rgba(0,0,0,0.15)'" :indicator-active-color="'#fac423'"
						:autoplay="false" :duration="500"
						previous-margin="50rpx" next-margin="50rpx"
						@change="onSwiperChange">
						<swiper-item v-for="(item, index) in levelList" :key="item.id">
							<view class="level-banner" :class="{ 'level-banner-active': index === current }">
								<view class="level-banner-content">
									<image :src="'/static/ym/v' + getLevelNum(item.name) + '.png'" mode="aspectFit" class="level-banner-image"></image>
								</view>
							</view>
						</swiper-item>
					</swiper>
				</view>

				<!-- 打手身份：价格卡片（立即开通） -->
				<view class="card card-glass price-card" v-if="currentLevel && currentLevel.price > 0 && isThug">
					<view class="price-row">
						<text class="price-label">¥<text class="price-num">{{ currentLevel.price }}</text>/{{ currentLevel.days || 30 }}天</text>
						<view class="btn-pay" @click="purchaseLevel">立即开通/续费</view>
					</view>
				</view>

				<!-- 用户身份：当前等级 + 升级进度 -->
				<view class="card card-glass info-card" v-if="currentLevel">
					<view class="info-row">
						<text class="info-label">我当前的会员等级</text>
						<text class="info-value">{{ userLevelName || '-' }}</text>
					</view>
					<template v-if="!isThug && nextLevelInfo">
						<view class="info-row" style="margin-top: 12rpx;">
							<text class="info-label" style="font-weight:700;color:#000;">升级至 {{ nextLevelInfo.name }}</text>
							<text class="info-progress">{{ upgradePercent }}%</text>
						</view>
						<view class="progress-bar-wrap">
							<view class="progress-bar-bg">
								<view class="progress-bar-fill" :style="{ width: upgradePercent + '%' }"></view>
							</view>
						</view>
						<view class="info-row" style="margin-top: 8rpx;">
							<text class="info-label" style="font-size:24rpx;color:#999;">当前成长值 {{ userScore }} / {{ cappingValue }}</text>
							<text class="info-link" @click="scrollToRights">查看下一级权益</text>
						</view>
					</template>
				</view>

				<!-- 权益介绍 -->
				<view class="card card-glass rights-card" v-if="currentLevel">
					<text class="rights-title">{{ currentLevel.name }} - 权益介绍</text>
					<view class="rights-grid" v-if="currentLevel.rights && currentLevel.rights.length">
						<view class="rights-item" v-for="(r, i) in currentLevel.rights" :key="i" @click="showRightsDetail(r)">
							<image class="rights-icon" :src="r.icon || '/static/yun/default_avatar.png'" mode="aspectFit"></image>
							<text class="rights-name">{{ r.title }}</text>
						</view>
					</view>
					<view class="rights-empty" v-else>
						<text class="rights-empty-text">暂无权益</text>
					</view>
				</view>

				<view class="bottom-spacer"></view>
			</view>
		</scroll-view>

		<!-- 权益详情弹窗 -->
		<view class="rights-popup-wrap" v-if="rightsPopupVisible" @click="rightsPopupVisible = false">
			<view class="rights-popup-mask" :class="{ 'popup-in': rightsPopupAnimated }"></view>
			<view class="rights-popup-card" :class="{ 'popup-in': rightsPopupAnimated }" @click.stop>
				<view class="rights-popup-head">
					<view class="rights-popup-pattern"></view>
					<view class="rights-popup-icon-wrap" v-if="rightsPopupIcon">
						<image :src="rightsPopupIcon" mode="aspectFill"></image>
					</view>
				</view>
				<view class="rights-popup-body">
					<text class="rights-popup-title">{{ rightsPopupTitle }}</text>
					<text class="rights-popup-desc">{{ rightsPopupDesc }}</text>
					<view class="rights-popup-btn" @click="rightsPopupVisible = false">知道了</view>
				</view>
			</view>
		</view>

		<!-- 申请升级弹窗 -->
		<up-popup :show="applyVisible" mode="center" round="20rpx" closeable @close="applyVisible = false">
			<view class="apply-popup">
				<text class="apply-popup-title">申请升级</text>
				<up-textarea v-model="applyIntro" count autoHeight placeholder="请输入申请说明" maxlength="200"></up-textarea>
				<view class="apply-btns">
					<view class="apply-btn-cancel" @click="applyVisible = false">取消</view>
					<view class="apply-btn-confirm" @click="submitApply">确定</view>
				</view>
			</view>
		</up-popup>
	</view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getLevelList, getUserInfo, levelOrder, levelApply, scoreLevel } from '../../request/api'
import router from '../../utils/router'
import payUtil from '../../utils/pay'

const levelList = ref([])
const current = ref(0)
const userInfo = ref({})
const loading = ref(false)
const rightsPopupVisible = ref(false)
const rightsPopupTitle = ref('')
const rightsPopupDesc = ref('')
const rightsPopupIcon = ref('')
const rightsPopupAnimated = ref(false)
const applyVisible = ref(false)
const applyIntro = ref('')
const nextLevelInfo = ref(null)
const userScore = ref(0)
const isThug = ref(false)
const identityParam = ref('')

const currentLevel = computed(() => levelList.value[current.value] || null)
const userLevelName = computed(() => {
	if (userInfo.value.level_name) return userInfo.value.level_name
	if (userInfo.value.user_current_level_info && userInfo.value.user_current_level_info.name) {
		return userInfo.value.user_current_level_info.name
	}
	return ''
})

// 从等级名称中提取数字
const getLevelNum = (name) => {
	if (!name) return 1
	const match = name.match(/\d+/)
	return match ? match[0] : 1
}

const upgradePercent = computed(() => {
	const next = nextLevelInfo.value
	if (!next || !next.score) return 0
	const cap = Number(next.score)
	if (cap <= 0) return 0
	return Math.min(100, Math.floor((userScore.value / cap) * 100))
})

const cappingValue = computed(() => {
	return Number((nextLevelInfo.value && nextLevelInfo.value.score) || 0)
})

onLoad(async (options) => {
	console.log('level page options:', options)
	if (options && options.identity) {
		identityParam.value = options.identity
	}
	await fetchData()
	if (options && options.index) {
		current.value = Number(options.index)
	}
})

const fetchData = async () => {
	loading.value = true
	uni.showLoading({ mask: true, title: '加载中' })
	try {
		// 先获取用户信息确定身份
		const userRes = await getUserInfo()
		if (userRes.code === 1) {
			userInfo.value = userRes.data || {}
			if (identityParam.value === 'thug') {
				isThug.value = true
			} else if (identityParam.value === 'user') {
				isThug.value = false
			} else {
				const storedType = uni.getStorageSync('type')
				isThug.value = storedType === '打手'
			}
		}
		// 根据身份获取对应等级列表
		const levelRes = await getLevelList({ identity: isThug.value ? 'thug' : 'user' })
		if (levelRes.code === 1) {
			levelList.value = levelRes.data || []
		}
		// 获取成长值和下一级信息
		if (!isThug.value) {
			try {
				const scoreRes = await scoreLevel()
				if (scoreRes.code === 1 && scoreRes.data) {
					// scoreLevel 返回 { q_level, h_level, score }
					// h_level 是下一级，score 是当前成长值
					userScore.value = Number(scoreRes.data.score || 0)
					if (scoreRes.data.h_level && scoreRes.data.h_level.name) {
						nextLevelInfo.value = scoreRes.data.h_level
					}
				}
			} catch (e) {
				console.log('获取等级信息失败', e)
			}
		}
	} catch (e) {
		console.log('获取会员数据失败', e)
	} finally {
		uni.hideLoading()
		loading.value = false
	}
}

const onSwiperChange = (e) => {
	current.value = e.detail.current
}

const goBack = () => {
	const pages = getCurrentPages()
	if (pages.length > 1) {
		uni.navigateBack({ delta: 1 })
	} else {
		uni.reLaunch({ url: '/pages/index/index' })
	}
}

const goHome = () => {
	uni.reLaunch({ url: '/pages/index/index' })
}

const purchaseLevel = () => {
	const item = currentLevel.value
	if (!item) return

	uni.showModal({
		title: '提示',
		content: `开通/续费「${item.name}」费用为 ¥${item.price} / ${item.days || 30}天`,
		success: async (res) => {
			if (!res.confirm) return
			uni.showLoading({ mask: true, title: '提交中' })
			try {
				const result = await levelOrder({ level_id: item.id })
				uni.hideLoading()
				if (result.code === 1 && result.data) {
					payUtil.redirectPay(result.data, (err) => {
						uni.showToast({ title: err || '无法打开易支付', icon: 'none' })
					})
				}
			} catch (e) {
				uni.hideLoading()
				console.log('下单失败', e)
			}
		}
	})
}

const showRightsDetail = (r) => {
	rightsPopupTitle.value = r.title || ''
	rightsPopupDesc.value = r.desc || ''
	rightsPopupIcon.value = r.icon || ''
	rightsPopupAnimated.value = false
	rightsPopupVisible.value = true
	setTimeout(() => {
		rightsPopupAnimated.value = true
	}, 50)
}

const scrollToRights = () => {
	const next = nextLevelInfo.value
	if (!next || !next.id) return
	const list = levelList.value || []
	for (let i = 0; i < list.length; i++) {
		// 宽松比较，兼容字符串和数字 id
		if (list[i].id == next.id) {
			current.value = i
			return
		}
	}
}

const submitApply = async () => {
	if (!applyIntro.value) {
		uni.showToast({ title: '请输入申请内容', icon: 'none' })
		return
	}
	try {
		const result = await levelApply({
			level_id: currentLevel.value.id,
			intro: applyIntro.value
		})
		if (result.code === 1) {
			uni.showToast({ title: '提交成功' })
			applyVisible.value = false
			applyIntro.value = ''
			await fetchData()
		}
	} catch (e) {
		console.log('申请失败', e)
	}
}
</script>

<style scoped>
.page {
	min-height: 100vh;
	background: #f5f7fa;
	display: flex;
	flex-direction: column;
	position: relative;
}

/* 顶部背景图 */
.top-bg {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 600rpx;
	z-index: 0;
	pointer-events: none;
}

/* 胶囊导航栏 */
.navbar {
	position: relative;
	z-index: 10;
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 0 24rpx;
	padding-top: calc(var(--status-bar-height, 0px) + 10rpx);
	height: calc(88rpx + var(--status-bar-height, 0px));
	background: #fff;
}
.navbar-capsule {
	display: flex;
	align-items: center;
	border: 1rpx solid #dadbde;
	border-radius: 100px;
	padding: 6rpx 16rpx;
	backdrop-filter: blur(10px);
	-webkit-backdrop-filter: blur(10px);
	background: rgba(255,255,255,.35);
}
.navbar-btn {
	width: 52rpx;
	height: 52rpx;
	display: flex;
	align-items: center;
	justify-content: center;
}
.navbar-divider {
	width: 1rpx;
	height: 32rpx;
	background: #dadbde;
	margin: 0 8rpx;
}
.navbar-title {
	font-size: 32rpx;
	font-weight: 700;
	color: #1a1a1a;
}
.navbar-placeholder {
	width: 140rpx;
}

/* 内容区 */
.content {
	flex: 1;
	min-height: 0;
	position: relative;
	z-index: 1;
	padding: 0 0 32rpx;
	box-sizing: border-box;
}
.content-inner {
	padding: 24rpx;
	box-sizing: border-box;
}

/* 卡片通用 */
.card {
	width: 100%;
	box-sizing: border-box;
	margin-bottom: 24rpx;
}
.card-glass {
	background: rgba(255,255,255,.55);
	backdrop-filter: blur(24rpx);
	-webkit-backdrop-filter: blur(24rpx);
	border: 1rpx solid rgba(255,255,255,.7);
	border-radius: 24rpx;
	padding: 28rpx;
	box-shadow: 0 2rpx 20rpx rgba(0,0,0,.04), inset 0 1rpx rgba(255,255,255,.9);
}

/* 轮播卡片 */
.swiper-card { padding: 24rpx 16rpx 32rpx; }
.level-swiper {
	width: 100%;
	height: 360rpx;
	border-radius: 16rpx;
	overflow: hidden;
}
.level-banner {
	padding: 0 8rpx;
	position: relative;
	transition: transform 0.3s;
	transform: scale(0.92);
}
.level-banner-active { transform: scale(1); }
.level-banner-content {
	width: 100%;
	height: 320rpx;
	border-radius: 16rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	background: linear-gradient(135deg, rgba(168,216,255,0.3), rgba(196,164,255,0.3));
	backdrop-filter: blur(10rpx);
}
.level-banner-image {
	width: 80%;
	height: 80%;
}

/* 价格卡片 */
.price-card { padding: 28rpx 32rpx; }
.price-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 24rpx;
}
.price-label {
	font-size: 28rpx;
	color: #c16b2d;
	font-weight: 600;
}
.price-num {
	font-size: 40rpx;
	font-weight: 800;
}
.btn-pay {
	padding: 16rpx 40rpx;
	background: #000;
	color: #fff;
	font-size: 28rpx;
	font-weight: 600;
	border-radius: 32rpx;
}

/* 等级信息 */
.info-card { padding: 28rpx 32rpx; }
.info-row {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 12rpx 0;
	font-size: 28rpx;
}
.info-label { color: #666; }
.info-value { color: #000; font-weight: 500; }
.info-progress {
	font-size: 28rpx;
	font-weight: 700;
	color: #fac423;
}
.info-link {
	font-size: 24rpx;
	color: #fac423;
	font-weight: 600;
}

/* 进度条 */
.progress-bar-wrap {
	padding: 8rpx 0;
}
.progress-bar-bg {
	width: 100%;
	height: 12rpx;
	background: #f0f0f0;
	border-radius: 6rpx;
	overflow: hidden;
}
.progress-bar-fill {
	height: 100%;
	background: linear-gradient(90deg, #fac423, #e8a820);
	border-radius: 6rpx;
	transition: width 0.3s;
}

/* 权益 */
.rights-card { padding: 32rpx; }
.rights-title {
	display: block;
	font-size: 32rpx;
	font-weight: 700;
	color: #000;
	margin-bottom: 28rpx;
	text-align: center;
}
.rights-grid {
	display: flex;
	flex-wrap: wrap;
	gap: 24rpx 0;
}
.rights-item {
	width: 33.33%;
	box-sizing: border-box;
	display: flex;
	flex-direction: column;
	align-items: center;
	padding: 8rpx 0;
}
.rights-icon {
	width: 88rpx;
	height: 88rpx;
	border-radius: 50%;
	margin-bottom: 12rpx;
	background: rgba(0,0,0,.06);
}
.rights-name {
	font-size: 26rpx;
	font-weight: 500;
	color: #333;
	text-align: center;
}
.rights-empty {
	padding: 48rpx 0;
	text-align: center;
}
.rights-empty-text {
	font-size: 26rpx;
	color: #999;
}

/* 权益详情弹窗 */
.rights-popup-wrap {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	z-index: 1000;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 0 48rpx;
	box-sizing: border-box;
}
.rights-popup-mask {
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(0,0,0,.5);
	opacity: 0;
	transition: opacity .25s ease;
}
.rights-popup-mask.popup-in {
	opacity: 1;
}
.rights-popup-card {
	position: relative;
	width: 100%;
	max-width: 600rpx;
	background: #fff;
	border-radius: 32rpx;
	overflow: hidden;
	box-shadow: 0 16rpx 48rpx rgba(0,0,0,.15);
	transform: scale(.92);
	opacity: 0;
	transition: transform .3s ease, opacity .25s ease;
}
.rights-popup-card.popup-in {
	transform: scale(1);
	opacity: 1;
}
.rights-popup-head {
	position: relative;
	height: 120rpx;
	display: flex;
	align-items: flex-end;
	justify-content: center;
}
.rights-popup-pattern {
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	height: 140rpx;
	background: linear-gradient(180deg, #fff9f0, #fff5e6, #fff);
	border-radius: 32rpx 32rpx 0 0;
}
.rights-popup-pattern::after {
	content: "";
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	height: 100%;
	background-image: radial-gradient(circle at 20% 30%, rgba(255,193,7,.08) 0%, transparent 50%),
		radial-gradient(circle at 80% 20%, rgba(255,152,0,.06) 0%, transparent 45%);
	border-radius: 32rpx 32rpx 0 0;
	pointer-events: none;
}
.rights-popup-icon-wrap {
	position: relative;
	z-index: 1;
	width: 100rpx;
	height: 100rpx;
	border-radius: 50%;
	background: linear-gradient(145deg, #fff, #fef8ed);
	box-shadow: 0 8rpx 24rpx rgba(255,152,0,.15), 0 0 0 4rpx rgba(255,255,255,.9);
	margin-bottom: -44rpx;
	padding: 8rpx;
	box-sizing: border-box;
}
.rights-popup-icon-wrap image {
	width: 100%;
	height: 100%;
	border-radius: 50%;
}
.rights-popup-body {
	padding: 48rpx 40rpx 40rpx;
}
.rights-popup-title {
	display: block;
	font-size: 36rpx;
	font-weight: 700;
	color: #000;
	text-align: center;
	margin-bottom: 20rpx;
}
.rights-popup-desc {
	display: block;
	font-size: 28rpx;
	color: #333;
	line-height: 1.6;
	text-align: center;
	margin-bottom: 40rpx;
	white-space: pre-wrap;
	word-break: break-all;
}
.rights-popup-btn {
	width: 100%;
	height: 88rpx;
	line-height: 88rpx;
	text-align: center;
	font-size: 30rpx;
	font-weight: 600;
	color: #fff;
	background: linear-gradient(135deg, #ffb347, #f57b15);
	border-radius: 44rpx;
}

/* 申请弹窗 */
.apply-popup {
	padding: 40rpx 32rpx;
	min-width: 560rpx;
}
.apply-popup-title {
	display: block;
	font-size: 32rpx;
	font-weight: 700;
	color: #1a1a1a;
	text-align: center;
	margin-bottom: 28rpx;
}
.apply-btns {
	display: flex;
	gap: 20rpx;
	margin-top: 28rpx;
}
.apply-btn-cancel, .apply-btn-confirm {
	flex: 1;
	height: 80rpx;
	line-height: 80rpx;
	text-align: center;
	border-radius: 16rpx;
	font-size: 28rpx;
	font-weight: 600;
}
.apply-btn-cancel {
	background: #f0f0f0;
	color: #666;
}
.apply-btn-confirm {
	background: #000;
	color: #fff;
}

.bottom-spacer { height: 48rpx; }
</style>
