<template>
	<view class="page">
		<!-- 顶部背景图 -->
		<image class="top-bg" src="/static/images/header_background_panda.png" mode="aspectFill"></image>

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
			<text class="navbar-title">订单列表</text>
			<view class="navbar-placeholder"></view>
		</view>

		<!-- 标签栏 -->
		<view class="scroll-tabs-row">
			<scroll-view scroll-x class="scroll-tabs-scroll" :show-scrollbar="false">
				<view class="scroll-tabs-inner">
					<view v-for="(tab, idx) in tabList" :key="idx"
						class="scroll-tab-item" :class="{ active: current === idx }"
						@click="changeTab(idx)">
						<text>{{ tab.name }}</text>
					</view>
				</view>
			</scroll-view>
		</view>

		<!-- 搜索栏 -->
		<view class="search-wrap">
			<uni-icons type="search" size="14" color="#999"></uni-icons>
			<input class="search-input" placeholder="搜索订单" placeholder-class="search-placeholder"
				v-model="orderNo" @confirm="doSearch" />
			<text class="search-btn" @click="doSearch">搜索</text>
		</view>

		<!-- 列表内容 -->
		<scroll-view scroll-y class="content" @scrolltolower="loadMore" @scroll="onScroll">
			<view class="content-inner">
				<view v-for="(item, index) in list" :key="item.id"
					class="order-card list-card-animate" :style="cardAnimStyle(index)"
					@click="toDetail(item)">
					<!-- 卡片头部时间+状态 -->
					<view class="card-header">
						<text class="card-time">{{ item.createtime }}</text>
						<text class="card-status" :class="getStatusClass(item)">{{ getStatus(item) }}</text>
					</view>

					<!-- 商品信息 -->
					<view class="product-row">
						<image class="product-thumb" :src="item.logo || item.image || '/static/images/icon.png'" mode="aspectFill"></image>
						<view class="product-info">
							<text class="product-name">{{ item.title }}</text>
							<view class="product-price">
								<view class="card-actual">
									<text class="price-symbol">¥</text>
									<text class="price-num">{{ item.price }}</text>
								</view>
								<text class="price-qty">x{{ item.number || 1 }}</text>
							</view>
							<text class="product-desc">{{ item.synopsis || '' }}</text>
						</view>
					</view>

					<!-- 订单配送信息 -->
					<view class="delivery-list">
						<view class="delivery">
							<text class="label">订单编号：</text>
							<text class="value">{{ item.order_no }}</text>
						</view>
						<view class="delivery">
							<text class="label">游戏ID：</text>
							<text class="value">{{ item.game_id || '-' }}</text>
						</view>
						<view class="delivery">
							<text class="label">游戏区服：</text>
							<text class="value">{{ item.game_qf || '-' }}</text>
						</view>
						<view class="delivery">
							<text class="label">订单备注：</text>
							<text class="value remark">{{ item.remarks || '无' }}</text>
						</view>
					</view>

					<!-- 实付款 -->
					<view class="pay-row">
						<text class="pay-label">实付款</text>
						<text class="pay-price">¥{{ item.pay_price }}</text>
					</view>

					<!-- 底部按钮 -->
					<view class="card-footer">
						<view class="btn-secondary" @click.stop="toDetail(item)">查看详情</view>
						<view class="btn-secondary" v-if="item.complaint==0 && userType=='用户'" @click.stop="toComplaint(item)">订单投诉</view>
						<view class="btn-secondary" @click.stop="purchaseAgain(item)">再次购买</view>
						<view class="btn-secondary" v-if="item.status===0" @click.stop="cancelOrder(item)">取消订单</view>
						<view class="btn-secondary btn-pay" v-if="item.status===0" @click.stop="payOrder(item)">立即支付</view>
						<view class="btn-secondary" v-if="item.status===4 && userType=='用户'" @click.stop="confirmFinish(item)">确认完成</view>
					</view>
				</view>
			</view>

			<!-- 加载更多 -->
			<view class="load-more-wrap" v-if="list.length > 0">
				<text class="load-more-text" v-if="loadStatus === 'loading'">加载中...</text>
				<text class="load-more-text" v-else-if="loadStatus === 'nomore'">没有更多了</text>
			</view>

			<!-- 空状态 -->
			<view style="margin-top:100rpx" v-if="!list.length && !loading">
				<uv-empty mode="data"></uv-empty>
			</view>

			<view style="margin-bottom:100rpx"></view>
		</scroll-view>

		<!-- 返回顶部 -->
		<view class="back-top" v-if="showBackTop" @click="backToTop">
			<uni-icons type="arrow-up" size="20" color="#333"></uni-icons>
		</view>

		<!-- 用户确认完成评价弹窗 -->
		<up-popup :show="usershow" mode="center" closeable @close="usershow=false" round="20rpx">
			<view class="popup-content">
				<view class="popup-title">服务评价</view>
				<view class="popup-rate">
					<up-rate v-model="rate" size="24"></up-rate>
				</view>
				<view class="popup-btns">
					<view class="popup-btn-cancel" @click="sureFinish('2')">不满意</view>
					<view class="popup-btn-confirm" @click="sureFinish('3')">确认完成</view>
				</view>
			</view>
		</up-popup>
	</view>
</template>

<script setup>
import { ref } from 'vue';
import { onLoad, onShow } from '@dcloudio/uni-app';
import { orderList, orderCancel as apiOrderCancel, orderPay, orderConfirm } from '../../../request/api';

const userType = uni.getStorageSync('type');
const list = ref([]);
const loading = ref(false);
const loadStatus = ref('loadmore');
const page = ref(0);
const pages = ref(0);
const current = ref(0);
const orderNo = ref('');
const showBackTop = ref(false);
const scrollTopVal = ref(0);
const usershow = ref(false);
const rate = ref(0);
const currentItem = ref(null);

const tabList = [
	{ name: '全部', id: '10' },
	{ name: '待付款', id: '0' },
	{ name: '待接单', id: '1' },
	{ name: '待开始', id: '2' },
	{ name: '服务中', id: '3' },
	{ name: '待结单', id: '4' },
	{ name: '已完成', id: '5' }
];

onLoad((option) => {
	if (option && option.type) {
		const idx = tabList.findIndex(t => t.id == option.type);
		if (idx > -1) current.value = idx;
	}
	if (option && option.status !== undefined) {
		const idx = tabList.findIndex(t => t.id == option.status);
		if (idx > -1) current.value = idx;
	}
	reloadList();

	// 静默初始化IM并注册用户
	if (uni.getStorageSync('token')) {
		import('../../../utils/im').then(({ ensureIMLogin }) => {
			ensureIMLogin().catch(() => {})
		}).catch(() => {})
	}
});

const goBack = () => {
	const p = getCurrentPages();
	if (p.length > 1) uni.navigateBack({ delta: 1 });
	else uni.reLaunch({ url: '/pages/mine/index' });
};
const goHome = () => uni.reLaunch({ url: '/pages/index/index' });

const onScroll = (e) => {
	scrollTopVal.value = e.detail.scrollTop;
	showBackTop.value = e.detail.scrollTop > 600;
};
const backToTop = () => {
	// scroll-view doesn't support programmatic scroll easily, use page scroll
};

const cardAnimStyle = (index) => ({
	animationDelay: Math.min(50 * index, 320) + 'ms'
});

const changeTab = (idx) => {
	current.value = idx;
	reloadList();
};

const doSearch = () => reloadList();

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
		const res = await orderList({
			status: tabList[current.value].id,
			page: page.value,
			limint: 10,
			order_no: orderNo.value
		});
		const data = res.data;
		if (data) {
			const newList = data.data || data.list || [];
			list.value = isReload ? newList : [...list.value, ...newList];
			pages.value = data.pages || data.last_page || Math.ceil((data.total || 0) / 10);
		}
	} catch (e) {
		console.error(e);
	} finally {
		loading.value = false;
		loadStatus.value = pages.value <= page.value ? 'nomore' : 'loadmore';
	}
};

const loadMore = () => {
	if (loadStatus.value !== 'loading' && loadStatus.value !== 'nomore') getList();
};

const getStatus = (item) => {
	if (item.status == 6) return '售后中';
	if (item.status == 7) return item.refund_method == 'balance' ? '退款已退至余额' : '退款已完成';
	if (item.status == 8) return '已取消';
	const cell = tabList.find(v => v.id == item.status);
	return cell ? cell.name : '未知';
};

const getStatusClass = (item) => {
	const s = getStatus(item);
	if (s === '待付款') return 'status-pending';
	if (s === '待接单' || s === '待结单' || s.indexOf('退款') > -1 || s === '已取消') return 'status-receive';
	if (s === '服务中' || s === '售后中') return 'status-serving';
	if (s === '已完成') return 'status-done';
	return '';
};

const toDetail = (item) => {
	uni.navigateTo({ url: '/pagesA/mine/order/detail?id=' + item.id });
};

const toComplaint = (item) => {
	uni.navigateTo({ url: '/pagesA/mine/order/tou?id=' + item.id + '&no=' + item.order_no + '&status=' + item.status });
};

const purchaseAgain = (item) => {
	if (!item.goods_id) {
		return uni.showToast({ title: '商品信息不存在', icon: 'none' });
	}
	uni.navigateTo({ url: '/pagesA/index/detail?id=' + item.goods_id });
};

const cancelOrder = (item) => {
	uni.showModal({
		title: '提示', content: '是否要取消该订单',
		success: (res) => {
			if (res.confirm) {
				apiOrderCancel({ id: item.id }).then(() => reloadList());
			}
		}
	});
};

const payOrder = (item) => {
	uni.navigateTo({ url: '/pagesA/index/desk?id=' + item.id + '&price=' + item.pay_price + '&award=' });
};

const confirmFinish = (item) => {
	currentItem.value = item;
	usershow.value = true;
};

const sureFinish = (status) => {
	if (status === '3' && rate.value === 0) {
		return uni.showToast({ title: '请选择评分', icon: 'none' });
	}
	const grade = status === '2' ? '0' : rate.value;
	orderConfirm({ id: currentItem.value.id, grade: grade }).then(() => {
		usershow.value = false;
		rate.value = 0;
		reloadList();
	});
};
</script>

<style scoped>
/* ҳ�� */
.page {
	min-height: 100vh;
	background: #f5f7fa;
	display: flex;
	flex-direction: column;
	position: relative;
}
.top-bg {
	position: absolute;
	top: 0; left: 0;
	width: 100%;
	height: 400rpx;
	z-index: 0;
	pointer-events: none;
}

/* ������ */
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
	background: rgba(255,255,255,0.3);
}
.navbar-btn { padding: 4rpx 8rpx; }
.navbar-divider {
	width: 1rpx; height: 32rpx;
	background: #dadbde;
	margin: 0 8rpx;
}
.navbar-title {
	font-size: 34rpx;
	font-weight: 600;
	color: #333;
}
.navbar-placeholder { width: 120rpx; }

/* ������ǩ */
.scroll-tabs-row {
	position: relative;
	z-index: 10;
	width: 100%;
	padding: 32rpx 24rpx 20rpx;
	box-sizing: border-box;
}
.scroll-tabs-scroll {
	width: 100%;
	height: 80rpx;
	white-space: nowrap;
}
.scroll-tabs-inner {
	display: inline-flex;
	align-items: center;
	gap: 16rpx;
	padding-right: 16rpx;
}
.scroll-tab-item {
	flex-shrink: 0;
	padding: 12rpx 24rpx;
	border-radius: 32rpx;
	background: #fff;
	color: #999;
	font-size: 24rpx;
	font-weight: 400;
	line-height: normal;
}
.scroll-tab-item.active {
	background: #000;
	color: #fff;
	font-weight: 500;
}

/* ������ */
.search-wrap {
	position: relative;
	z-index: 10;
	display: flex;
	align-items: center;
	padding: 12rpx 24rpx;
	margin: 0 24rpx 20rpx;
	background: rgba(255,255,255,0.45);
	backdrop-filter: blur(24rpx);
	-webkit-backdrop-filter: blur(24rpx);
	border: 1rpx solid rgba(255,255,255,0.6);
	box-shadow: 0 2rpx 20rpx rgba(0,0,0,0.04), inset 0 1rpx rgba(255,255,255,0.8);
	border-radius: 48rpx;
}
.search-input {
	flex: 1;
	height: 48rpx;
	font-size: 26rpx;
	color: #000;
	background: transparent;
	margin-left: 12rpx;
}
.search-placeholder { color: #999; }
.search-btn {
	flex-shrink: 0;
	font-size: 26rpx;
	color: #333;
	padding-left: 16rpx;
}

/* ������ */
.content {
	flex: 1;
	min-height: 0;
	padding: 0 0 32rpx;
	box-sizing: border-box;
	position: relative;
	z-index: 1;
}
.content-inner {
	padding: 0 24rpx;
}

/* ������Ƭ */
.order-card {
	width: 100%;
	box-sizing: border-box;
	background: rgba(255,255,255,0.55);
	backdrop-filter: blur(24rpx);
	-webkit-backdrop-filter: blur(24rpx);
	border: 1rpx solid rgba(255,255,255,0.7);
	border-radius: 24rpx;
	margin-bottom: 28rpx;
	padding: 32rpx;
	box-shadow: 0 2rpx 20rpx rgba(0,0,0,0.04), inset 0 1rpx rgba(255,255,255,0.9);
}

/* ��Ƭ�볡���� */
@keyframes orderCardIn {
	0% { opacity: 0; transform: translateY(24rpx); }
	100% { opacity: 1; transform: translateY(0); }
}
.list-card-animate {
	opacity: 0;
	animation: orderCardIn 0.5s ease-out forwards;
}

/* ��Ƭͷ�� */
.card-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 24rpx;
	padding-bottom: 24rpx;
	border-bottom: 1rpx solid rgba(0,0,0,0.08);
}
.card-time { font-size: 24rpx; color: #666; }
.card-status {
	font-size: 24rpx;
	padding: 4rpx 16rpx;
	border-radius: 8rpx;
}
.card-status.status-pending {
	background: transparent;
	color: #ff4d4f;
	padding: 0;
}
.card-status.status-receive {
	background: rgba(250,173,20,0.2);
	color: #faad14;
}
.card-status.status-serving {
	background: rgba(82,196,26,0.15);
	color: #52c41a;
}

/* ��Ʒ��Ϣ�� */
.product-row {
	display: flex;
	gap: 24rpx;
	margin-bottom: 24rpx;
}
.product-thumb {
	width: 160rpx;
	height: 160rpx;
	border-radius: 12rpx;
	background: #f0f0f0;
	flex-shrink: 0;
}
.product-info {
	flex: 1;
	min-width: 0;
}
.product-name {
	display: -webkit-box;
	font-size: 30rpx;
	font-weight: 600;
	color: #000;
	overflow: hidden;
	text-overflow: ellipsis;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
}
.product-price {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 16rpx 0 12rpx;
	margin-bottom: 12rpx;
	border-bottom: 1rpx solid rgba(0,0,0,0.08);
}
.card-actual {
	display: flex;
	align-items: center;
}
.price-symbol {
	font-size: 24rpx;
	color: #da4a44;
	font-weight: 600;
}
.price-num {
	font-size: 32rpx;
	color: #da4a44;
	font-weight: 600;
}
.price-qty {
	font-size: 24rpx;
	color: #999;
	margin-left: 8rpx;
}
.product-desc {
	font-size: 24rpx;
	color: #8f8f8f;
	line-height: 1.5;
	display: -webkit-box;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
}

/* ���������б� */
.delivery-list {
	display: flex;
	flex-direction: column;
	gap: 16rpx;
	border-top: 1rpx solid rgba(0,0,0,0.08);
	padding: 16rpx 0;
	margin-bottom: 12rpx;
}
.delivery {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 24rpx;
	font-size: 26rpx;
}
.delivery .label {
	flex-shrink: 0;
	color: #999;
}
.delivery .value {
	flex: 1;
	min-width: 0;
	color: #333;
	text-align: right;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.delivery .value.remark {
	white-space: normal;
	word-break: break-all;
}

/* ʵ���� */
.pay-row {
	display: flex;
	align-items: flex-end;
	justify-content: space-between;
	margin-bottom: 16rpx;
}
.pay-label {
	font-size: 28rpx;
	color: #343434;
}
.pay-price {
	font-size: 28rpx;
	font-weight: 600;
	color: #da4a44;
}

/* �ײ���ť */
.card-footer {
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: nowrap;
	gap: 12rpx;
	padding-top: 16rpx;
	border-top: 1rpx solid rgba(0,0,0,0.08);
}
.btn-secondary {
	flex: 1 1 0%;
	min-width: 0;
	height: 54rpx;
	line-height: 54rpx;
	text-align: center;
	font-size: 20rpx;
	color: #333;
	background: #f0f0f0;
	border-radius: 16rpx;
	padding: 0 12rpx;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.btn-pay {
	background: linear-gradient(135deg, #ffc300, #faad14);
	color: #fff;
	font-weight: 500;
}

/* ���ظ��� */
.load-more-wrap {
	text-align: center;
	padding: 30rpx 0;
}
.load-more-text {
	font-size: 26rpx;
	color: #999;
}

/* ��״̬ */
.empty-wrap {
	display: flex;
	flex-direction: column;
	align-items: center;
	padding: 100rpx 0;
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

/* �������� */
.back-top {
	position: fixed;
	right: 30rpx;
	bottom: 200rpx;
	width: 80rpx;
	height: 80rpx;
	border-radius: 50%;
	background: rgba(255,255,255,0.9);
	backdrop-filter: blur(10px);
	-webkit-backdrop-filter: blur(10px);
	box-shadow: 0 4rpx 20rpx rgba(0,0,0,0.1);
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 99;
}

/* ���� */
.popup-content {
	background: #fff;
	padding: 40rpx;
	border-radius: 20rpx;
	width: 600rpx;
}
.popup-title {
	text-align: center;
	font-size: 32rpx;
	font-weight: 600;
	color: #333;
}
.popup-rate {
	display: flex;
	justify-content: center;
	margin: 40rpx 0;
}
.popup-btns {
	display: flex;
	gap: 24rpx;
	justify-content: center;
}
.popup-btn-cancel {
	width: 200rpx;
	height: 72rpx;
	line-height: 72rpx;
	text-align: center;
	border: 1rpx solid #ccc;
	border-radius: 36rpx;
	font-size: 28rpx;
	color: #333;
}
.popup-btn-confirm {
	width: 200rpx;
	height: 72rpx;
	line-height: 72rpx;
	text-align: center;
	background: #000;
	color: #fff;
	border-radius: 36rpx;
	font-size: 28rpx;
}
</style>
��
