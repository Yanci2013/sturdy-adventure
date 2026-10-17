<template>
	<view class="page">
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
				<!-- 头部卡片 -->
				<view class="card card-glass ranking-header">
					<view class="header-left">
						<image class="header-icon" src="/static/yun/下载 (10).png" mode="aspectFit"></image>
						<view class="header-text">
							<text class="header-title">会员排行榜</text>
							<text class="header-date">此数据根据结单统计 仅展示前30名</text>
						</view>
					</view>
				</view>

				<!-- Tab 切换 -->
				<view class="scroll-tabs-row">
					<view class="scroll-tabs-inner">
						<view
							class="scroll-tab-item"
							:class="{ active: currentTab === index }"
							v-for="(tab, index) in tabs"
							:key="index"
							@click="switchTab(index)"
						>
							<text>{{ tab.name }}</text>
						</view>
					</view>
				</view>

				<!-- 排行列表 -->
				<view class="card card-glass ranking-card">
					<view class="ranking-list" v-if="list.length">
						<view
							class="ranking-item list-card-animate-left"
							:class="{
								'top-three': index < 3,
								'top-one': index === 0,
								'top-two': index === 1,
								'top-three-item': index === 2
							}"
							:style="{ animationDelay: Math.min(index * 50, 320) + 'ms' }"
							v-for="(item, index) in list"
							:key="index"
						>
							<view class="ranking-number">
								<view v-if="index < 3" class="rank-medal-wrap" :class="'rank-medal-' + (index + 1)">
									<image class="rank-medal" :src="'/static/rank/' + (index + 1) + '.png'" mode="aspectFit"></image>
								</view>
								<text v-else class="rank-num">{{ index + 1 }}</text>
							</view>
							<view class="ranking-user">
								<image class="user-avatar" :src="item.avatar || '/static/yun/default_avatar.png'" mode="aspectFill"></image>
								<text class="user-nickname">{{ item.name || '用户' }}</text>
							</view>
							<text class="ranking-score">{{ item.money }}</text>
						</view>
					</view>
					<view class="ranking-empty" v-else>
						<text class="ranking-empty-text">无人上榜</text>
					</view>
				</view>

				<view class="ranking-note">注：排行榜数据每隔 3 分钟刷新</view>
				<view class="bottom-spacer"></view>
			</view>
		</scroll-view>
	</view>
</template>

<script setup>
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { userRanking } from '../../request/api'

const tabs = [
	{ name: '今日消费' },
	{ name: '本周消费' },
	{ name: '本月消费' }
]
const currentTab = ref(0)
const list = ref([])

onLoad(() => {
	getData()
})

const switchTab = (index) => {
	currentTab.value = index
	getData()
}

const getData = async () => {
	uni.showLoading({ mask: true, title: '加载中' })
	try {
		const res = await userRanking({ type: currentTab.value + 1 })
		if (res.code === 1) {
			list.value = res.data || []
		} else {
			list.value = []
		}
	} catch (e) {
		list.value = []
	} finally {
		uni.hideLoading()
	}
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
</script>

<style scoped>
@keyframes listItemSlideInLeft {
	0% { opacity: 0; transform: translateX(-80rpx); }
	100% { opacity: 1; transform: translateX(0); }
}
.list-card-animate-left { opacity: 0; animation: listItemSlideInLeft .5s ease-out forwards; }

.page { min-height: 100vh; background: #f5f7fa; display: flex; flex-direction: column; position: relative; }
.top-bg { position: absolute; top: 0; left: 0; width: 100%; height: 600rpx; z-index: 0; pointer-events: none; }

.navbar {
	position: relative; z-index: 10; display: flex; align-items: center; justify-content: space-between;
	padding: 0 24rpx; padding-top: calc(var(--status-bar-height, 0px) + 10rpx);
	height: calc(88rpx + var(--status-bar-height, 0px));
	background: #fff;
}
.navbar-capsule {
	display: flex; align-items: center; border: 1rpx solid #dadbde; border-radius: 100px;
	padding: 6rpx 16rpx; backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); background: rgba(255,255,255,.35);
}
.navbar-btn { width: 52rpx; height: 52rpx; display: flex; align-items: center; justify-content: center; }
.navbar-divider { width: 1rpx; height: 32rpx; background: #dadbde; margin: 0 8rpx; }
.navbar-title { font-size: 32rpx; font-weight: 700; color: #1a1a1a; }
.navbar-placeholder { width: 140rpx; }

.content { flex: 1; min-height: 0; position: relative; z-index: 1; padding: 0 0 32rpx; box-sizing: border-box; }
.content-inner { padding: 24rpx; box-sizing: border-box; }

.card { width: 100%; box-sizing: border-box; margin-bottom: 24rpx; }
.card-glass {
	background: rgba(255,255,255,.55); backdrop-filter: blur(24rpx); -webkit-backdrop-filter: blur(24rpx);
	border: 1rpx solid rgba(255,255,255,.7); border-radius: 24rpx; padding: 28rpx;
	box-shadow: 0 2rpx 20rpx rgba(0,0,0,.04), inset 0 1rpx rgba(255,255,255,.9);
}

.ranking-header { padding: 28rpx 32rpx; }
.header-left { display: flex; align-items: center; gap: 20rpx; }
.header-icon { width: 80rpx; height: 80rpx; flex-shrink: 0; }
.header-text { display: flex; flex-direction: column; gap: 8rpx; }
.header-title { font-size: 36rpx; font-weight: 700; color: #000; }
.header-date { font-size: 24rpx; color: #666; }

.scroll-tabs-row { width: 100%; padding: 0 0 20rpx; box-sizing: border-box; }
.scroll-tabs-inner { display: inline-flex; align-items: center; gap: 16rpx; }
.scroll-tab-item {
	flex-shrink: 0; padding: 12rpx 24rpx; border-radius: 32rpx;
	background: #fff; color: #999; font-size: 24rpx; font-weight: 400; transition: all .2s;
}
.scroll-tab-item.active { background: #000; color: #fff; font-weight: 500; }

.ranking-card { padding: 0; overflow: hidden; }
.ranking-list { display: flex; flex-direction: column; }
.ranking-item {
	display: flex; align-items: center; min-height: 120rpx;
	padding: 24rpx 32rpx; border-bottom: 1rpx solid rgba(0,0,0,.06);
}
.ranking-item:last-child { border-bottom: none; }
.ranking-item.top-three { border-left: 6rpx solid transparent; }
.ranking-item.top-one {
	background: linear-gradient(90deg, rgba(232,185,35,.08) 0%, rgba(232,185,35,.02) 50%, transparent 100%);
	border-left-color: #c9981a;
}
.ranking-item.top-two {
	background: linear-gradient(90deg, rgba(168,168,168,.07) 0%, rgba(168,168,168,.02) 50%, transparent 100%);
	border-left-color: #8a8a8a;
}
.ranking-item.top-three-item {
	background: linear-gradient(90deg, rgba(199,124,74,.07) 0%, rgba(199,124,74,.02) 50%, transparent 100%);
	border-left-color: #a65c32;
}

.ranking-number { width: 80rpx; flex-shrink: 0; display: flex; justify-content: center; align-items: center; }
.rank-medal-wrap {
	width: 56rpx; height: 56rpx; border-radius: 50%;
	display: flex; align-items: center; justify-content: center; padding: 4rpx;
}
.rank-medal-1 { background: linear-gradient(145deg, rgba(232,185,35,.2), rgba(201,152,26,.1)); }
.rank-medal-2 { background: linear-gradient(145deg, rgba(168,168,168,.2), rgba(138,138,138,.1)); }
.rank-medal-3 { background: linear-gradient(145deg, rgba(199,124,74,.2), rgba(166,92,50,.1)); }
.rank-medal { width: 48rpx; height: 48rpx; }
.rank-num { font-size: 32rpx; font-weight: 700; color: #666; }

.ranking-user { flex: 1; min-width: 0; display: flex; align-items: center; gap: 20rpx; padding: 0 16rpx; }
.user-avatar { width: 72rpx; height: 72rpx; border-radius: 50%; flex-shrink: 0; background: rgba(0,0,0,.06); }
.user-nickname {
	flex: 1; min-width: 0; font-size: 28rpx; font-weight: 600; color: #000;
	overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.ranking-score { font-size: 30rpx; font-weight: 700; color: #e28935; flex-shrink: 0; min-width: 100rpx; text-align: right; }

.ranking-empty { padding: 64rpx 0; text-align: center; }
.ranking-empty-text { font-size: 28rpx; color: #999; }
.ranking-note { text-align: center; font-size: 22rpx; color: #999; padding: 24rpx 0; }
.bottom-spacer { height: 48rpx; }
</style>
