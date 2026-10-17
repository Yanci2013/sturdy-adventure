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
			<text class="navbar-title">系统通知</text>
			<view class="navbar-placeholder"></view>
		</view>

		<!-- 内容区域 -->
		<scroll-view scroll-y class="content" @scrolltolower="loadMore" :scroll-top="scrollTop" @scroll="onScroll">
			<view class="content-inner">
				<!-- 标签切换 -->
				<view class="tabs">
					<view :class="['tab', currentTab === 'notice' ? 'tab-active' : '']" @click="switchTab('notice')">
						<text class="tab-text">系统通知</text>
					</view>
					<view :class="['tab', currentTab === 'guide' ? 'tab-active' : '']" @click="switchTab('guide')">
						<text class="tab-text">下单攻略</text>
					</view>
				</view>

				<!-- 下单攻略内容 -->
				<view class="guide-card" v-if="currentTab === 'guide'">
					<view class="guide-item" v-for="(item, idx) in guideList" :key="idx">
						<view class="guide-num">{{ idx + 1 }}</view>
						<view class="guide-content">
							<text class="guide-title">{{ item.title }}</text>
							<text class="guide-desc">{{ item.desc }}</text>
						</view>
					</view>
				</view>

				<!-- 搜索卡片 -->
				<view class="card card-glass search-card" v-if="currentTab === 'notice'">
					<view class="search-wrap-inner">
						<uni-icons type="search" size="16" color="#999"></uni-icons>
						<input class="search-input" placeholder="搜索关键词" placeholder-class="search-placeholder"
							v-model="keyword" @confirm="doSearch" />
						<text class="search-btn" @click="doSearch" v-if="keyword">搜索</text>
					</view>
				</view>

				<!-- 公告列表卡片 -->
				<view class="card card-glass list-card" v-if="currentTab === 'notice'">
					<view class="list" v-if="list.length > 0">
						<view class="item list-card-animate-left" v-for="(item, index) in list" :key="item.id"
							:style="cardAnimStyle(index)" @click="gotoDetail(item)">
							<view class="info">
								<view class="text">
									<view class="title">{{ item.title }}</view>
									<view class="describe">{{ stripHtml(item.content || item.describe || '') }}</view>
									<view class="other">
										<view class="left">
											<view class="time">{{ item.createtime }}</view>
										</view>
										<view class="right view">
											<image src="/static/mine/eye.png" mode="aspectFit"></image>
											<text>{{ item.views || 0 }}</text>
										</view>
									</view>
								</view>
								<view class="photo" v-if="item.image">
									<image :src="item.image" mode="aspectFill"></image>
								</view>
							</view>
							<view class="line"></view>
						</view>
					</view>

					<!-- 空状态 -->
					<view class="empty-state" v-if="list.length === 0 && !loading">
						<image src="/static/empty.png" mode="aspectFit" class="empty-img"></image>
						<text class="empty-text">暂无数据</text>
					</view>

					<!-- 加载更多 -->
					<view class="load-more" v-if="list.length > 0">
						<text class="load-more-text" v-if="loadStatus === 'loading'">加载中...</text>
						<text class="load-more-text" v-else-if="loadStatus === 'nomore'">没有更多了</text>
						<text class="load-more-text" v-else @click="loadMore">点击加载更多</text>
					</view>
				</view>

				<view class="bottom-spacer"></view>
			</view>
		</scroll-view>

		<!-- 返回顶部 -->
		<view class="back-top" v-if="showBackTop" @click="backToTop">
			<uni-icons type="arrow-up" size="20" color="#333"></uni-icons>
		</view>
	</view>
</template>

<script setup>
import { ref } from 'vue';
import { onLoad, onShow } from '@dcloudio/uni-app';
import { noticeList } from '../../../request/api';

const scrollTop = ref(0);
const oldScrollTop = ref(0);
const showBackTop = ref(false);
const keyword = ref('');
const list = ref([]);
const loading = ref(false);
const loadStatus = ref('loadmore');
const page = ref(0);
const pages = ref(0);
const limit = 10;
const currentTab = ref('guide');

const guideList = ref([
	{ title: '如何下单', desc: '在首页选择游戏和服务，填写需求后提交订单，支付成功后等待打手接单。' },
	{ title: '选择打手', desc: '下单后可在订单详情中查看接单打手信息，支持主动邀请指定打手接单。' },
	{ title: '订单进度', desc: '打手接单后可在「我的订单」中实时查看服务进度，完成后确认收货。' },
	{ title: '售后保障', desc: '如对服务不满意，可在订单详情中申请售后，平台客服将介入处理。' },
	{ title: '邀请好友', desc: '复制邀请链接分享给好友，好友注册后您将获得佣金奖励。' }
]);

const switchTab = (tab) => {
	currentTab.value = tab;
};

const stripHtml = (html) => {
	if (!html) return '';
	return html.replace(/<[^>]+>/g, '').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim();
};

onLoad(() => {
	reloadList();
});

const goBack = () => {
	const pages = getCurrentPages();
	if (pages.length > 1) {
		uni.navigateBack({ delta: 1 });
	} else {
		uni.reLaunch({ url: '/pages/mine/index' });
	}
};

const goHome = () => {
	uni.reLaunch({ url: '/pages/index/index' });
};

const onScroll = (e) => {
	oldScrollTop.value = e.detail.scrollTop;
	showBackTop.value = e.detail.scrollTop > 600;
};

const backToTop = () => {
	scrollTop.value = oldScrollTop.value;
	setTimeout(() => { scrollTop.value = 0; }, 10);
};

const cardAnimStyle = (index) => ({
	animationDelay: Math.min(50 * index, 320) + 'ms'
});

const doSearch = () => {
	reloadList();
};

const reloadList = () => {
	page.value = 0;
	pages.value = 0;
	list.value = [];
	loadStatus.value = 'loadmore';
	getList(true);
};

const getList = async (isReload = false) => {
	if (pages.value && pages.value < page.value + 1 && !isReload) {
		loadStatus.value = 'nomore';
		return;
	}
	page.value++;
	loading.value = true;
	loadStatus.value = 'loading';

	try {
		const res = await noticeList({
			type: uni.getStorageSync('type') == '用户' ? '1' : '2',
			page: page.value,
			limit: limit,
			keyword: keyword.value
		});
		const data = res.data;
		if (data) {
			const newList = data.data || data.list || [];
			list.value = isReload ? newList : [...list.value, ...newList];
			pages.value = data.pages || data.last_page || Math.ceil((data.total || 0) / limit);
		}
	} catch (e) {
		console.error('获取公告列表失败', e);
	} finally {
		loading.value = false;
		if (pages.value <= page.value) {
			loadStatus.value = 'nomore';
		} else {
			loadStatus.value = 'loadmore';
		}
	}
};

const loadMore = () => {
	if (loadStatus.value !== 'loading' && loadStatus.value !== 'nomore') {
		getList();
	}
};

const gotoDetail = (item) => {
	uni.navigateTo({
		url: '/pagesA/mine/notice/detail?id=' + item.id
	});
};
</script>

<style scoped>
/* 页面容器 */
.page {
	min-height: 100vh;
	background: #f5f7fa;
	display: flex;
	flex-direction: column;
	position: relative;
}

/* 顶部背景 */
.top-bg {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 400rpx;
	z-index: 0;
	pointer-events: none;
}

/* 导航栏 */
.navbar {
	position: relative;
	z-index: 10;
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 24rpx 24rpx 0;
	padding-top: calc(var(--status-bar-height, 0px) + 16rpx);
	background: #fff;
}
.navbar-capsule {
	display: flex;
	align-items: center;
	border: 1rpx solid #dadbde;
	border-radius: 100px;
	padding: 8rpx 16rpx;
	backdrop-filter: blur(10px);
	-webkit-backdrop-filter: blur(10px);
	opacity: 0.95;
	background: rgba(255, 255, 255, 0.3);
}
.navbar-btn {
	padding: 4rpx 8rpx;
}
.navbar-divider {
	width: 1rpx;
	height: 32rpx;
	background: #dadbde;
	margin: 0 8rpx;
}
.navbar-title {
	font-size: 34rpx;
	font-weight: 600;
	color: #333;
}
.navbar-placeholder {
	width: 120rpx;
}

/* 内容区 */
.content {
	flex: 1;
	min-height: 0;
	padding: 0 0 32rpx;
	box-sizing: border-box;
	position: relative;
	z-index: 1;
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
	background: rgba(255, 255, 255, 0.55);
	backdrop-filter: blur(24rpx);
	-webkit-backdrop-filter: blur(24rpx);
	border: 1rpx solid rgba(255, 255, 255, 0.7);
	border-radius: 24rpx;
	padding: 28rpx;
	box-shadow: 0 2rpx 20rpx rgba(0, 0, 0, 0.04), inset 0 1rpx rgba(255, 255, 255, 0.9);
}

/* 搜索卡片 */
.search-card {
	padding: 20rpx 24rpx;
}
.search-wrap-inner {
	display: flex;
	align-items: center;
	padding: 12rpx 24rpx;
	background: rgba(245, 247, 250, 0.6);
	border-radius: 48rpx;
	border: 1rpx solid rgba(0, 0, 0, 0.04);
}
.search-input {
	flex: 1;
	height: 48rpx;
	font-size: 26rpx;
	color: #000;
	background: transparent;
	margin-left: 12rpx;
}
.search-placeholder {
	color: #999;
}
.search-btn {
	flex-shrink: 0;
	font-size: 26rpx;
	color: #333;
	padding-left: 16rpx;
}

/* 列表卡片 */
.list-card {
	padding: 0 24rpx 24rpx;
	min-height: 200rpx;
}

/* 公告列表 */
.list {
	margin-top: 2rpx;
}
.list .item {
	padding: 40rpx 0 0;
}

/* 入场动画 */
@keyframes listItemSlideInLeft {
	0% {
		opacity: 0;
		transform: translateX(-80rpx);
	}
	100% {
		opacity: 1;
		transform: translateX(0);
	}
}
.list-card-animate-left {
	opacity: 0;
	animation: listItemSlideInLeft 0.5s ease-out forwards;
}

.list .item .info {
	display: flex;
	flex-direction: row;
	justify-content: space-between;
	padding-bottom: 4rpx;
}
.list .item .info .text {
	flex-grow: 1;
	flex-shrink: 1;
	display: flex;
	flex-direction: column;
	margin-right: 40rpx;
}
.list .item .info .text .title {
	font-size: 32rpx;
	font-weight: 600;
	line-height: 1.5;
	display: -webkit-box;
	text-overflow: ellipsis;
	word-break: break-all;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
	color: #333;
	margin-bottom: 8rpx;
}
.list .item .info .text .describe {
	font-size: 24rpx;
	line-height: 1.6;
	display: -webkit-box;
	text-overflow: ellipsis;
	word-break: break-all;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
	margin-bottom: 12rpx;
	color: #999;
}
.list .item .info .text .other {
	flex-grow: 1;
	flex-shrink: 1;
	display: flex;
	align-items: center;
	font-size: 28rpx;
	color: #999;
	line-height: normal;
}
.list .item .info .text .other .left {
	display: flex;
	flex-grow: 1;
	flex-shrink: 1;
}
.list .item .info .text .other .left .time {
	font-size: 24rpx;
	color: #999;
}
.list .item .info .text .other .right {
	display: flex;
	flex-direction: row;
	justify-content: center;
	align-items: center;
	flex-grow: 0;
	flex-shrink: 0;
	margin-right: 5rpx;
}
.list .item .info .text .other .right image {
	width: 40rpx;
	height: 40rpx;
	margin-right: 5rpx;
}
.list .item .info .text .other .right text {
	font-size: 24rpx;
	color: #999;
}
.list .item .info .photo image {
	height: 170rpx;
	width: 222rpx;
	border-radius: 10rpx;
}
.list .item .line {
	display: block;
	width: 100%;
	height: 1rpx;
	margin-top: 22rpx;
	background: rgba(0, 0, 0, 0.06);
}

/* 空状态 */
.empty-state {
	display: flex;
	flex-direction: column;
	align-items: center;
	padding: 80rpx 0;
}
.empty-img {
	width: 200rpx;
	height: 200rpx;
	margin-bottom: 20rpx;
}
.empty-text {
	font-size: 28rpx;
	color: #999;
}

/* 加载更多 */
.load-more {
	text-align: center;
	padding: 30rpx 0;
}
.load-more-text {
	font-size: 26rpx;
	color: #999;
}

/* 底部间距 */
.bottom-spacer {
	height: 48rpx;
}

/* 回到顶部 */
.back-top {
	position: fixed;
	right: 30rpx;
	bottom: 200rpx;
	width: 80rpx;
	height: 80rpx;
	border-radius: 50%;
	background: rgba(255, 255, 255, 0.9);
	backdrop-filter: blur(10px);
	-webkit-backdrop-filter: blur(10px);
	box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.1);
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 99;
}

/* 标签切换 */
.tabs {
	display: flex;
	gap: 0;
	margin-bottom: 24rpx;
	background: #fff;
	border-radius: 16rpx;
	padding: 6rpx;
	box-shadow: 0 2rpx 12rpx rgba(0,0,0,0.04);
}
.tab {
	flex: 1;
	height: 72rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 12rpx;
	position: relative;
	overflow: hidden;
}
.tab-text {
	font-size: 28rpx;
	color: #666;
	font-weight: 500;
	position: relative;
	z-index: 2;
}
.tab-active {
	background: linear-gradient(135deg, #ff6b35, #ff8c42);
}
.tab-active .tab-text {
	color: #fff;
	font-weight: 700;
}
.tab-active::before {
	content: '';
	position: absolute;
	right: -20rpx;
	top: 50%;
	transform: translateY(-50%);
	width: 60rpx;
	height: 120rpx;
	background: linear-gradient(135deg, #ff6b35, #ff8c42);
	border-radius: 50%;
	z-index: 1;
}

/* 下单攻略卡片 */
.guide-card {
	background: rgba(255,255,255,0.6);
	backdrop-filter: blur(24rpx);
	-webkit-backdrop-filter: blur(24rpx);
	border: 1rpx solid rgba(255,255,255,0.7);
	border-radius: 24rpx;
	padding: 28rpx;
	margin-bottom: 24rpx;
}
.guide-item {
	display: flex;
	align-items: flex-start;
	padding: 24rpx 0;
	border-bottom: 1rpx solid rgba(0,0,0,0.06);
}
.guide-item:last-child {
	border-bottom: none;
}
.guide-num {
	width: 48rpx;
	height: 48rpx;
	border-radius: 50%;
	background: linear-gradient(135deg, #ff6b35, #ff8c42);
	color: #fff;
	font-size: 26rpx;
	font-weight: 700;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
	margin-right: 20rpx;
}
.guide-content {
	flex: 1;
}
.guide-title {
	display: block;
	font-size: 30rpx;
	font-weight: 600;
	color: #333;
	margin-bottom: 8rpx;
}
.guide-desc {
	display: block;
	font-size: 26rpx;
	color: #999;
	line-height: 1.6;
}
</style>
