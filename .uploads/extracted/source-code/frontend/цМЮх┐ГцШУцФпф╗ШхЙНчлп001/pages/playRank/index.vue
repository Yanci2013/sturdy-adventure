<template>
	<view class="page">
		<!-- 顶部背景图 -->
		<image class="top-bg" src="/static/images/header_background.png" mode="aspectFill"></image>

		<!-- 导航栏 -->
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
			<text class="navbar-title">排行榜</text>
			<view class="navbar-placeholder"></view>
		</view>

		<scroll-view scroll-y class="content">
			<view class="content-inner">
				<!-- 数据维度 tabs -->
				<view class="tabs-wrap">
					<scroll-view scroll-x :show-scrollbar="false" class="scroll-tabs-scroll">
						<view class="scroll-tabs-inner">
							<view v-for="(item, index) in tab" :key="index"
								:class="['scroll-tab-item', currentDataTabIndex === index ? 'active' : '']"
								@click="onDataTabChange(index)">
								<text>{{ item.name }}</text>
							</view>
						</view>
					</scroll-view>
				</view>

				<!-- 时间维度 tabs（综合评分时隐藏） -->
				<view class="time-tabs-wrap" v-if="params.data_type !== 2">
					<view v-for="(item, index) in tabs" :key="index"
						:class="['time-tab-item', currentTimeTabIndex === index ? 'active' : '']"
						@click="onTimeTabChange(index)">
						<text>{{ item.name }}</text>
					</view>
				</view>

				<!-- 领奖台（前三名） -->
				<view class="card card-glass podium-card">
					<view class="podium">
						<view v-for="rank in [2, 1, 3]" :key="rank"
							:class="['podium-item', 'podium-' + rank]">
							<view class="podium-rank-badge">{{ rank }}</view>
							<view class="podium-avatar-wrap">
								<image class="podium-avatar" :src="getRankingUserInfo(rank).avatar" mode="aspectFill"></image>
							</view>
							<text class="podium-name">{{ getRankingUserInfo(rank).nickname }}</text>
							<text class="podium-score">{{ getRankingUserInfo(rank).data }}</text>
							<view :class="['podium-bar', 'podium-bar-' + rank]"></view>
						</view>
					</view>
				</view>

				<!-- 排行列表（第4名起） -->
				<view class="card card-glass ranking-card">
					<view class="ranking-head">
						<text class="ranking-th">排名</text>
						<text class="ranking-th">昵称</text>
						<text class="ranking-th">{{ rankingDataName }}</text>
					</view>
					<view class="ranking-list" v-if="rankingList.length">
						<view class="ranking-item list-card-animate-left"
							v-for="(item, index) in rankingList" :key="index"
							:style="cardAnimStyle(index)">
							<text class="ranking-num">{{ index + 4 }}</text>
							<view class="ranking-user">
								<image class="user-avatar" :src="item.user_info?.avatar || item.avatar || '/static/icon/default_avatar.png'" mode="aspectFill"></image>
								<text class="user-nickname">{{ item.user_info?.nickname || item.name || '用户' }}</text>
							</view>
							<text class="ranking-score">{{ item.data || item.money || item.number || item.grade || 0 }}</text>
						</view>
					</view>
					<view class="ranking-empty" v-else>
						<uv-empty mode="data" text="暂无更多排名"></uv-empty>
					</view>
				</view>

				<view class="ranking-note">注：排行榜数据每隔 3 分钟刷新</view>
				<view class="bottom-spacer"></view>
			</view>
		</scroll-view>
	</view>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { thugStream } from '../../request/api'

const tab = [
	{ name: '接单流水', value: '' },
	{ name: '接单单量', value: 1 },
	{ name: '综合评分', value: 2 },
	{ name: '五星评分', value: 3 }
]
const tabs = [
	{ name: '日榜', value: '' },
	{ name: '周榜', value: 'YW' },
	{ name: '月榜', value: 'Ym' }
]

const list = ref([])
const currentDataTabIndex = ref(0)
const currentTimeTabIndex = ref(0)
const params = reactive({
	data_type: '',
	time_type: ''
})

const rankingDataName = computed(() => {
	const found = tab.find(t => String(t.value) === String(params.data_type))
	return found ? found.name : '数据'
})

const rankingList = computed(() => {
	return list.value.slice(3)
})

onLoad(() => {
	getData()
})

const cardAnimStyle = (index) => ({
	animationDelay: Math.min(50 * index, 320) + 'ms'
})

const onDataTabChange = (index) => {
	currentDataTabIndex.value = index
	params.data_type = tab[index].value
	getData()
}

const onTimeTabChange = (index) => {
	currentTimeTabIndex.value = index
	params.time_type = tabs[index].value
	getData()
}

const getRankingUserInfo = (rank) => {
	const item = list.value[rank - 1]
	if (item) {
		return {
			nickname: item.user_info?.nickname || item.name || '用户',
			avatar: item.user_info?.avatar || item.avatar || '/static/icon/default_avatar.png',
			data: item.data || item.money || item.number || item.grade || 0
		}
	}
	return {
		nickname: 'TOP' + rank,
		avatar: '/static/icon/default_avatar.png',
		data: '无人上榜'
	}
}

const getData = async () => {
	uni.showLoading({ mask: true, title: '加载中' })
	try {
		const reqParams = {
			...params,
			data_type: params.data_type === '' ? 0 : params.data_type
		}
		// 后端接口用 type 和 rank_type
		const res = await thugStream({
			type: currentTimeTabIndex.value + 1,
			rank_type: reqParams.data_type === 0 ? 1 : reqParams.data_type
		})
		if (res.code === 1 || res.code === '1') {
			list.value = res.data || []
		} else {
			list.value = []
			uni.showModal({ content: res.msg || '加载失败' })
		}
	} catch (e) {
		list.value = []
		console.error(e)
	} finally {
		uni.hideLoading()
	}
}

const goBack = () => {
	const pages = getCurrentPages()
	if (pages.length > 1) {
		uni.navigateBack({ delta: 1 })
	} else {
		uni.reLaunch({ url: '/pages/playerMain/index' })
	}
}

const goHome = () => {
	uni.reLaunch({ url: '/pages/index/index' })
}
</script>

<style scoped>
@keyframes listItemSlideInLeft {
	0% { opacity: 0; transform: translateX(-80rpx); }
	100% { opacity: 1; transform: translateX(0); }
}
.list-card-animate-left { opacity: 0; animation: listItemSlideInLeft .5s ease-out forwards; }

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
	top: 0; left: 0;
	width: 100%; height: 600rpx;
	z-index: 0;
	pointer-events: none;
}

/* 导航栏 */
.navbar {
	position: relative; z-index: 10;
	display: flex; align-items: center; justify-content: space-between;
	padding: 0 24rpx;
	padding-top: calc(var(--status-bar-height, 0px) + 10rpx);
	height: calc(88rpx + var(--status-bar-height, 0px));
	background: #fff;
}
.navbar-capsule {
	display: flex; align-items: center;
	border: 1rpx solid #dadbde; border-radius: 100px;
	padding: 6rpx 16rpx;
	backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
	background: rgba(255,255,255,.35);
}
.navbar-btn { width: 52rpx; height: 52rpx; display: flex; align-items: center; justify-content: center; }
.navbar-divider { width: 1rpx; height: 32rpx; background: #dadbde; margin: 0 8rpx; }
.navbar-title { font-size: 32rpx; font-weight: 700; color: #1a1a1a; }
.navbar-placeholder { width: 140rpx; }

/* 内容区 */
.content { flex: 1; min-height: 0; padding: 0 0 32rpx; box-sizing: border-box; }
.content-inner { padding: 24rpx; box-sizing: border-box; }

/* 卡片 */
.card { width: 100%; box-sizing: border-box; margin-bottom: 24rpx; }
.card-glass {
	background: rgba(255,255,255,.55);
	backdrop-filter: blur(24rpx); -webkit-backdrop-filter: blur(24rpx);
	border: 1rpx solid rgba(255,255,255,.7); border-radius: 24rpx; padding: 28rpx;
	box-shadow: 0 2rpx 20rpx rgba(0,0,0,.04), inset 0 1rpx rgba(255,255,255,.9);
}

/* 数据维度 tabs */
.tabs-wrap { margin-bottom: 24rpx; }
.scroll-tabs-scroll { width: 100%; white-space: nowrap; }
.scroll-tabs-inner { display: inline-flex; align-items: center; gap: 16rpx; }
.scroll-tab-item {
	flex-shrink: 0; padding: 12rpx 24rpx; border-radius: 32rpx;
	background: #fff; color: #999; font-size: 24rpx; font-weight: 400;
}
.scroll-tab-item.active { background: #000; color: #fff; font-weight: 500; }

/* 时间维度 tabs */
.time-tabs-wrap {
	display: flex; flex-wrap: wrap; gap: 20rpx; margin-bottom: 24rpx;
}
.time-tab-item {
	flex: 1; min-width: 0; box-sizing: border-box;
	padding: 20rpx 24rpx;
	background: rgba(0,0,0,.06); border-radius: 12rpx;
	text-align: center; font-size: 26rpx; color: #333;
}
.time-tab-item.active { background: #000; color: #fff; }

/* 领奖台 */
.podium-card { padding: 36rpx 20rpx 32rpx; }
.podium {
	display: flex; justify-content: space-between; align-items: flex-end; gap: 12rpx;
}
.podium-item {
	flex: 1; display: flex; flex-direction: column; align-items: center; min-height: 360rpx;
}
.podium-item.podium-2 { order: 0; }
.podium-item.podium-1 { order: 1; min-height: 380rpx; }
.podium-item.podium-3 { order: 2; }

.podium-rank-badge {
	width: 44rpx; height: 44rpx; border-radius: 50%;
	display: flex; align-items: center; justify-content: center;
	font-size: 24rpx; font-weight: 800; color: #fff;
	margin-bottom: 12rpx; flex-shrink: 0;
}
.podium-1 .podium-rank-badge { background: linear-gradient(145deg, #e8b923, #c9981a); box-shadow: 0 4rpx 12rpx rgba(201,152,26,.35); }
.podium-2 .podium-rank-badge { background: linear-gradient(145deg, #a8a8a8, #8a8a8a); box-shadow: 0 4rpx 12rpx rgba(0,0,0,.15); }
.podium-3 .podium-rank-badge { background: linear-gradient(145deg, #c77c4a, #a65c32); box-shadow: 0 4rpx 12rpx rgba(166,92,50,.3); }

.podium-avatar-wrap {
	width: 112rpx; height: 112rpx; border-radius: 50%; padding: 4rpx; flex-shrink: 0;
}
.podium-1 .podium-avatar-wrap { background: linear-gradient(145deg, #e8b923, #c9981a); box-shadow: 0 6rpx 16rpx rgba(201,152,26,.25); }
.podium-2 .podium-avatar-wrap { background: linear-gradient(145deg, #b0b0b0, #909090); box-shadow: 0 6rpx 16rpx rgba(0,0,0,.12); }
.podium-3 .podium-avatar-wrap { background: linear-gradient(145deg, #c77c4a, #a65c32); box-shadow: 0 6rpx 16rpx rgba(166,92,50,.22); }

.podium-avatar {
	width: 100%; height: 100%; border-radius: 50%;
	background: rgba(0,0,0,.06); display: block;
}
.podium-name {
	margin-top: 14rpx; font-size: 24rpx; font-weight: 600; color: #000;
	text-align: center; overflow: hidden; text-overflow: ellipsis;
	display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
	line-height: 1.35; padding: 0 4rpx;
}
.podium-score { margin-top: 6rpx; font-size: 22rpx; color: #e28935; font-weight: 700; }

.podium-bar {
	margin-top: 14rpx; width: 80%; border-radius: 8rpx 8rpx 0 0; flex-shrink: 0;
}
.podium-bar-1 { height: 72rpx; background: linear-gradient(180deg, rgba(232,185,35,.35), rgba(232,185,35,.12)); }
.podium-bar-2 { height: 56rpx; background: linear-gradient(180deg, rgba(168,168,168,.35), rgba(168,168,168,.12)); }
.podium-bar-3 { height: 44rpx; background: linear-gradient(180deg, rgba(199,124,74,.35), rgba(199,124,74,.12)); }

/* 排行列表 */
.ranking-card { padding: 0; overflow: hidden; }
.ranking-head {
	display: flex; align-items: center; height: 88rpx; padding: 0 32rpx;
	font-size: 26rpx; font-weight: 600; color: #555;
	background: rgba(0,0,0,.04);
}
.ranking-th { flex: 1; text-align: center; }
.ranking-th:nth-child(1) { max-width: 100rpx; text-align: center; }
.ranking-th:nth-child(2) { flex: 1; text-align: left; padding-left: 24rpx; }
.ranking-th:nth-child(3) { max-width: 140rpx; text-align: right; }

.ranking-list { display: flex; flex-direction: column; }
.ranking-item {
	display: flex; align-items: center; min-height: 112rpx;
	padding: 20rpx 32rpx; border-bottom: 1rpx solid rgba(0,0,0,.06);
}
.ranking-item:last-child { border-bottom: none; }

.ranking-num {
	width: 100rpx; flex-shrink: 0;
	font-size: 28rpx; font-weight: 600; color: #666; text-align: center;
}
.ranking-user {
	flex: 1; min-width: 0; display: flex; align-items: center; gap: 20rpx; padding: 0 16rpx;
}
.user-avatar {
	width: 72rpx; height: 72rpx; border-radius: 50%; flex-shrink: 0;
	background: rgba(0,0,0,.06);
}
.user-nickname {
	flex: 1; min-width: 0; font-size: 28rpx; font-weight: 600; color: #000;
	overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.ranking-score {
	width: 120rpx; flex-shrink: 0;
	font-size: 30rpx; font-weight: 700; color: #e28935; text-align: right;
}

.ranking-empty { padding: 64rpx 0; }
.ranking-note { text-align: center; font-size: 22rpx; color: #999; padding: 24rpx 0; }
.bottom-spacer { height: 48rpx; }
</style>
