<template>
	<z-paging ref="paging" v-model="dataList" @query="queryList" auto-show-back-to-top>
		<template #top>
			<view class="top-header">
				<!-- 顶部背景图 -->
				<image class="top-section-bg" src="/static/images/header_background.png" mode="aspectFill"></image>
				<!-- 导航栏 -->
				<view class="navbar">
					<view class="navbar-capsule">
						<view class="navbar-btn" @tap="goBack">
							<up-icon name="arrow-left" size="34rpx" color="#606266"></up-icon>
						</view>
						<view class="navbar-divider"></view>
						<view class="navbar-btn" @tap="goHome">
							<up-icon name="home" size="36rpx" color="#606266"></up-icon>
						</view>
					</view>
					<text class="navbar-title">我的接单</text>
					<view class="navbar-placeholder"></view>
				</view>
				<!-- 状态 tabs -->
				<view class="scroll-tabs-row">
					<scroll-view class="scroll-tabs-scroll" scroll-x scroll-with-animation :show-scrollbar="false">
						<view class="scroll-tabs-inner">
							<view v-for="(tab, index) in tabList" :key="index"
								:class="['scroll-tab-item', current === index ? 'active' : '']"
								@tap="changeTab(index)">
								<text>{{ tab.name }}</text>
							</view>
						</view>
					</scroll-view>
				</view>
				<!-- 搜索栏 -->
				<view class="search-wrap">
					<image class="search-icon" src="/static/images/search_icon.png" mode="aspectFit"></image>
					<input class="search-input" type="text" placeholder="搜索订单"
						placeholder-class="search-placeholder" confirm-type="search"
						v-model="searchKey" @confirm="search" />
					<view class="search-btn" @tap="search">搜索</view>
				</view>
			</view>
		</template>

		<view class="content-inner">
			<view class="order-card list-card-animate"
				v-for="(item, index) in dataList" :key="index"
				:style="{ animationDelay: Math.min(50 * index, 320) + 'ms' }"
				@tap="gotoDetail(item)">
				<view class="card-header">
					<text class="card-server">{{ item.game_qf || item.title }}</text>
					<text class="card-time">{{ item.createtime_text || '' }}</text>
				</view>
				<view class="product-row">
					<image class="product-thumb" mode="aspectFill" :src="item.logo || '/static/images/icon.png'"></image>
					<view class="product-info">
						<text class="product-name">{{ item.title }}</text>
						<view class="product-price">
							<view class="card-actual">
								<text class="price-symbol">¥</text>
								<text class="price-num">{{ item.pay_price }}</text>
							</view>
							<text class="price-qty">x{{ item.goods_num || 1 }}</text>
						</view>
						<text class="product-desc">{{ item.synopsis || '' }}</text>
					</view>
				</view>
				<view class="delivery-list">
					<view class="delivery-item">
						<text class="delivery-label">订单编号</text>
						<text class="delivery-value">{{ item.order_no }}</text>
					</view>
					<view class="delivery-item">
						<text class="delivery-label">游戏ID</text>
						<text class="delivery-value">{{ item.game_id || '-' }}</text>
					</view>
					<view class="delivery-item">
						<text class="delivery-label">游戏昵称</text>
						<text class="delivery-value">{{ item.wz_id || '-' }}</text>
					</view>
					<view class="delivery-item">
						<text class="delivery-label">游戏区服</text>
						<text class="delivery-value">{{ item.game_qf || '-' }}</text>
					</view>
					<view class="delivery-item">
						<text class="delivery-label">订单备注</text>
						<text class="delivery-value">{{ item.remarks || '无' }}</text>
					</view>
					<view class="delivery-item">
						<text class="delivery-label">服务费</text>
						<text class="delivery-value fee-value">¥{{ item.received_price || '0' }}</text>
					</view>
				</view>
				<view v-if="item.complaint == 0 || item.complaint == 1 || item.complaint == 2"
					class="delivery-item" style="margin-top: 16rpx;">
					<text class="delivery-label">审核状态</text>
					<text class="delivery-value"
						:class="item.complaint == 1 ? 'status-success' : item.complaint == 2 ? 'status-fail' : ''">
						{{ item.complaint == 0 ? '审核中' : item.complaint == 1 ? '审核成功' : '审核失败' }}
					</text>
				</view>
				<view v-if="item.helper" class="delivery-item" style="margin-top: 16rpx;">
					<text class="delivery-label">协助打手</text>
					<text class="delivery-value">{{ item.helper }}</text>
				</view>
				<view class="pay-row">
					<text class="pay-label">实付款</text>
					<text class="pay-price">¥{{ item.pay_price }}</text>
				</view>
				<view class="card-footer">
					<view class="btn-secondary" @tap.stop="gotoDetail(item)">查看详情</view>
				</view>
			</view>
		</view>
		<view style="height: 160rpx;"></view>
	</z-paging>
</template>

<script setup>
import { ref } from 'vue';
import { onLoad } from '@dcloudio/uni-app';
import { thugorderList } from '../../request/api';

const type = ref('10')
const tabList = [
	{ name: '全部', id: '10' },
	{ name: '待开始', id: '2' },
	{ name: '服务中', id: '3' },
	{ name: '待结单', id: '4' },
	{ name: '已结单', id: '5' },
	{ name: '有退款', id: '7' }
]
const paging = ref(null)
const dataList = ref([])
const current = ref(0)
const searchKey = ref('')

onLoad((options) => {
	if (options && options.status) {
		const idx = tabList.findIndex(t => t.id === options.status)
		if (idx > -1) {
			current.value = idx
			type.value = options.status
		}
	}
})

const queryList = (pageNo, pageSize) => {
	thugorderList({
		status: type.value,
		page: pageNo,
		limint: pageSize,
		order_no: searchKey.value
	}).then(res => {
		paging.value.complete(res.data.data);
	})
}
const changeTab = (index) => {
	current.value = index
	type.value = tabList[index].id
	paging.value.reload()
}
const refresh = () => { paging.value.reload() }
const search = () => { paging.value.reload() }
const goBack = () => { uni.navigateBack() }
const goHome = () => { uni.reLaunch({ url: '/pages/index/index' }) }
const gotoDetail = (item) => {
	uni.navigateTo({ url: '/pagesA/mine/order/detail?id=' + item.id })
}
</script>

<style scoped>
@keyframes orderCardIn {
	0% { opacity: 0; transform: translateY(24rpx); }
	100% { opacity: 1; transform: translateY(0); }
}
.list-card-animate { opacity: 0; animation: orderCardIn 0.5s ease-out forwards; }

/* 顶部背景 */
.top-header { position: relative; overflow: hidden; }
.top-section-bg { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; }

/* 导航栏 */
.navbar { position: relative; z-index: 10; display: flex; align-items: center; justify-content: space-between; padding: 24rpx 24rpx 0; padding-top: calc(var(--status-bar-height, 0px) + 16rpx); background: #fff; }
.navbar-capsule { display: flex; align-items: center; border: 1rpx solid #dadbde; border-radius: 100px; padding: 8rpx 16rpx; backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); opacity: 0.95; background: rgba(255, 255, 255, 0.3); }
.navbar-btn { padding: 4rpx 8rpx; }
.navbar-divider { width: 1rpx; height: 32rpx; background: #dadbde; margin: 0 8rpx; }
.navbar-title { font-size: 34rpx; font-weight: 600; color: #333; }
.navbar-placeholder { width: 120rpx; }

/* 状态 tabs */
.scroll-tabs-row { position: relative; z-index: 2; width: 100%; padding: 12rpx 24rpx 16rpx; box-sizing: border-box; }
.scroll-tabs-scroll { width: 100%; height: 80rpx; white-space: nowrap; }
.scroll-tabs-inner { display: inline-flex; align-items: center; gap: 16rpx; }
.scroll-tab-item { flex-shrink: 0; padding: 12rpx 24rpx; border-radius: 32rpx; background: #fff; color: #999; font-size: 24rpx; font-weight: 400; line-height: normal; }
.scroll-tab-item.active { background: #000; color: #fff; font-weight: 500; }

/* 搜索栏 */
.search-wrap { position: relative; z-index: 2; display: flex; align-items: center; padding: 12rpx 24rpx; margin: 0 24rpx 20rpx; background: rgba(255,255,255,0.45); backdrop-filter: blur(24rpx); -webkit-backdrop-filter: blur(24rpx); border: 1rpx solid rgba(255,255,255,0.6); box-shadow: 0 2rpx 20rpx rgba(0,0,0,0.04), inset 0 1rpx rgba(255,255,255,0.8); border-radius: 48rpx; }
.search-icon { width: 32rpx; height: 32rpx; margin-right: 12rpx; flex-shrink: 0; }
.search-input { flex: 1; height: 48rpx; font-size: 26rpx; color: #000; background: transparent; }
.search-placeholder { color: #999; }
.search-btn { flex-shrink: 0; font-size: 26rpx; color: #333; padding-left: 16rpx; }

/* 内容区 */
.content-inner { padding: 0 24rpx; }

/* 订单卡片 */
.order-card { width: 100%; box-sizing: border-box; background: rgba(255,255,255,0.55); backdrop-filter: blur(24rpx); -webkit-backdrop-filter: blur(24rpx); border: 1rpx solid rgba(255,255,255,0.7); border-radius: 24rpx; margin-bottom: 28rpx; padding: 32rpx; box-shadow: 0 2rpx 20rpx rgba(0,0,0,0.04), inset 0 1rpx rgba(255,255,255,0.9); }
.card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24rpx; padding-bottom: 24rpx; border-bottom: 1rpx solid rgba(0,0,0,0.08); }
.card-server { font-size: 28rpx; font-weight: 600; color: #000; }
.card-time { font-size: 24rpx; color: #666; }

/* 商品信息 */
.product-row { display: flex; gap: 24rpx; margin-bottom: 24rpx; }
.product-thumb { width: 160rpx; height: 160rpx; border-radius: 12rpx; background: #f0f0f0; flex-shrink: 0; }
.product-info { flex: 1; min-width: 0; }
.product-name { display: -webkit-box; font-size: 30rpx; font-weight: 600; color: #000; overflow: hidden; text-overflow: ellipsis; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.product-price { display: flex; align-items: center; justify-content: space-between; padding: 16rpx 0 12rpx; margin-bottom: 12rpx; border-bottom: 1rpx solid rgba(0,0,0,0.08); }
.card-actual { display: flex; align-items: center; }
.price-symbol { font-size: 24rpx; color: #da4a44; font-weight: 600; }
.price-num { font-size: 32rpx; color: #da4a44; font-weight: 600; }
.price-qty { font-size: 24rpx; color: #999; margin-left: 8rpx; }
.product-desc { font-size: 24rpx; color: #8f8f8f; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

/* 订单信息列表 */
.delivery-list { display: flex; flex-direction: column; gap: 16rpx; padding-top: 20rpx; border-top: 1rpx solid rgba(0,0,0,0.06); }
.delivery-item { display: flex; align-items: flex-start; justify-content: space-between; gap: 24rpx; font-size: 26rpx; }
.delivery-label { flex-shrink: 0; width: 160rpx; color: #999; }
.delivery-value { flex: 1; min-width: 0; color: #333; text-align: right; word-break: break-all; }
.fee-value { color: #da4a44; font-weight: 600; }
.status-success { color: #FF9D00; }
.status-fail { color: #da4a44; }

/* 实付款 */
.pay-row { display: flex; align-items: flex-end; justify-content: space-between; margin-top: 20rpx; margin-bottom: 16rpx; }
.pay-label { font-size: 28rpx; color: #343434; }
.pay-price { font-size: 28rpx; font-weight: 600; color: #da4a44; }

/* 底部按钮 */
.card-footer { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 12rpx; padding-top: 16rpx; border-top: 1rpx solid rgba(0,0,0,0.08); }
.btn-secondary { min-width: 140rpx; height: 54rpx; line-height: 54rpx; text-align: center; font-size: 24rpx; color: #333; background: #f0f0f0; border-radius: 16rpx; padding: 0 20rpx; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>
