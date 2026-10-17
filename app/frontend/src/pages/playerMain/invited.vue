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
					<text class="navbar-title">受邀订单</text>
					<view class="navbar-placeholder"></view>
				</view>
				<!-- 提示 -->
				<view class="invite-tip">
					<uv-icon name="info-circle" size="28" color="#FF7A45"></uv-icon>
					<text class="tip-text">以下为平台邀请您接单的订单，点击"接受"即可开始服务</text>
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
					<text class="invite-badge">受邀</text>
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
						<text class="delivery-label">游戏区服</text>
						<text class="delivery-value">{{ item.game_qf || '-' }}</text>
					</view>
					<view class="delivery-item">
						<text class="delivery-label">服务费</text>
						<text class="delivery-value fee-value">¥{{ item.received_price || '0' }}</text>
					</view>
				</view>
				<view class="pay-row">
					<text class="pay-label">实付款</text>
					<text class="pay-price">¥{{ item.pay_price }}</text>
				</view>
				<view class="card-footer">
					<view class="btn-reject" @tap.stop="rejectOrder(item)">拒绝</view>
					<view class="btn-accept" @tap.stop="acceptOrder(item)">接受邀请</view>
				</view>
			</view>
			<uv-empty v-if="!loading && dataList.length === 0" mode="data" text="暂无受邀订单" style="margin-top:120rpx"></uv-empty>
		</view>
		<view style="height: 160rpx;"></view>
	</z-paging>
</template>

<script setup>
import { ref } from 'vue'
import { thugorderList, orderConnect } from '../../request/api'

const paging = ref(null)
const dataList = ref([])
const loading = ref(false)

const queryList = (pageNo, pageSize) => {
	loading.value = true
	thugorderList({
		status: '1',
		page: pageNo,
		limint: pageSize
	}).then(res => {
		const list = res.data?.data || []
		paging.value.complete(list)
	}).catch(() => {
		paging.value.complete([])
	}).finally(() => {
		loading.value = false
	})
}

const acceptOrder = (item) => {
	uni.showModal({
		title: '接受邀请',
		content: `确定接受订单「${item.title}」吗？`,
		success: (res) => {
			if (res.confirm) {
				orderConnect({ order_id: item.id, order_no: item.order_no }).then(r => {
					if (r.code == 1) {
						uni.showToast({ title: '已接受，开始服务', icon: 'success' })
						paging.value.reload()
					} else {
						uni.showToast({ title: r.msg || '接受失败', icon: 'none' })
					}
				}).catch(() => {
					uni.showToast({ title: '接受失败', icon: 'none' })
				})
			}
		}
	})
}

const rejectOrder = (item) => {
	uni.showModal({
		title: '拒绝邀请',
		content: `确定拒绝订单「${item.title}」吗？`,
		success: (res) => {
			if (res.confirm) {
				uni.showToast({ title: '已拒绝', icon: 'none' })
				paging.value.reload()
			}
		}
	})
}

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

.top-header { position: relative; overflow: hidden; }
.top-section-bg { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; }

.navbar { position: relative; z-index: 10; display: flex; align-items: center; justify-content: space-between; padding: 24rpx 24rpx 0; padding-top: calc(var(--status-bar-height, 0px) + 16rpx); background: #fff; }
.navbar-capsule { display: flex; align-items: center; border: 1rpx solid #dadbde; border-radius: 100px; padding: 8rpx 16rpx; backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); opacity: 0.95; background: rgba(255, 255, 255, 0.3); }
.navbar-btn { padding: 4rpx 8rpx; }
.navbar-divider { width: 1rpx; height: 32rpx; background: #dadbde; margin: 0 8rpx; }
.navbar-title { font-size: 34rpx; font-weight: 600; color: #333; }
.navbar-placeholder { width: 120rpx; }

.invite-tip { position: relative; z-index: 2; display: flex; align-items: center; gap: 12rpx; margin: 16rpx 24rpx 24rpx; padding: 16rpx 24rpx; background: rgba(255, 122, 69, 0.1); border-radius: 16rpx; }
.tip-text { font-size: 24rpx; color: #FF7A45; flex: 1; }

.content-inner { padding: 0 24rpx; }

.order-card { width: 100%; box-sizing: border-box; background: rgba(255,255,255,0.55); backdrop-filter: blur(24rpx); -webkit-backdrop-filter: blur(24rpx); border: 1rpx solid rgba(255,255,255,0.7); border-radius: 24rpx; margin-bottom: 28rpx; padding: 32rpx; box-shadow: 0 2rpx 20rpx rgba(0,0,0,0.04), inset 0 1rpx rgba(255,255,255,0.9); }
.card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24rpx; padding-bottom: 24rpx; border-bottom: 1rpx solid rgba(0,0,0,0.08); }
.card-server { font-size: 28rpx; font-weight: 600; color: #000; }
.invite-badge { font-size: 22rpx; color: #fff; background: linear-gradient(135deg, #FF7A45, #FF9D6E); padding: 6rpx 16rpx; border-radius: 20rpx; font-weight: 600; }

.product-row { display: flex; gap: 24rpx; margin-bottom: 24rpx; }
.product-thumb { width: 160rpx; height: 160rpx; border-radius: 12rpx; background: #f0f0f0; flex-shrink: 0; }
.product-info { flex: 1; min-width: 0; }
.product-name { display: -webkit-box; font-size: 30rpx; font-weight: 600; color: #000; overflow: hidden; text-overflow: ellipsis; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.product-price { display: flex; align-items: center; gap: 12rpx; margin: 12rpx 0; }
.card-actual { display: flex; align-items: baseline; }
.price-symbol { font-size: 24rpx; color: #FF7A45; font-weight: 600; }
.price-num { font-size: 36rpx; color: #FF7A45; font-weight: 700; }
.price-qty { font-size: 24rpx; color: #999; }
.product-desc { font-size: 24rpx; color: #999; }

.delivery-list { display: flex; flex-direction: column; gap: 12rpx; }
.delivery-item { display: flex; justify-content: space-between; align-items: center; }
.delivery-label { font-size: 24rpx; color: #999; }
.delivery-value { font-size: 24rpx; color: #333; }
.fee-value { color: #FF7A45; font-weight: 600; }

.pay-row { display: flex; justify-content: space-between; align-items: center; margin-top: 24rpx; padding-top: 24rpx; border-top: 1rpx solid rgba(0,0,0,0.08); }
.pay-label { font-size: 26rpx; color: #333; }
.pay-price { font-size: 32rpx; color: #FF7A45; font-weight: 700; }

.card-footer { display: flex; justify-content: flex-end; gap: 16rpx; margin-top: 24rpx; }
.btn-reject { padding: 12rpx 32rpx; border: 1rpx solid #ddd; border-radius: 32rpx; font-size: 26rpx; color: #666; }
.btn-accept { padding: 12rpx 32rpx; background: linear-gradient(135deg, #FF7A45, #FF9D6E); border-radius: 32rpx; font-size: 26rpx; color: #fff; font-weight: 600; }
</style>
