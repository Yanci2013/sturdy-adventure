<template>
	<view class="page">
		<image class="top-bg" src="/static/images/header_background.png" mode="aspectFill"></image>
		<z-paging ref="paging" v-model="dataList" @query="queryList" auto-show-back-to-top>
			<template #top>
				<!-- 导航栏 -->
				<view class="navbar" :style="{paddingTop: statusBarHeight + 'px'}">
					<view class="navbar-capsule">
						<view class="navbar-btn" @click="goBack">
							<up-icon name="arrow-left" size="34rpx" color="#606266"></up-icon>
						</view>
						<view class="navbar-divider"></view>
						<view class="navbar-btn" @click="goHome">
							<up-icon name="home" size="36rpx" color="#606266"></up-icon>
						</view>
					</view>
					<text class="navbar-title">我的罚单</text>
					<view class="navbar-placeholder"></view>
				</view>
				<view class="tabs-wrap">
					<view :class="['tab-item', status===-1?'active':'']" @click="changeStatus(-1)">全部</view>
					<view :class="['tab-item', status===0?'active':'']" @click="changeStatus(0)">待缴纳</view>
					<view :class="['tab-item', status===1?'active':'']" @click="changeStatus(1)">已缴纳</view>
					<view :class="['tab-item', status===2?'active':'']" @click="changeStatus(2)">已撤销</view>
				</view>
			</template>
			<template #empty>
				<uv-empty mode="list" text="暂无罚单记录" style="margin-top:100rpx"></uv-empty>
			</template>
			<view class="content-inner">
				<view class="record-card card-glass" v-for="item in dataList" :key="item.id" @click="showDetail(item)">
					<view class="card-header">
						<text class="card-time">{{ item.fine_no }}</text>
						<text :class="['card-status', statusClass(item.status)]">{{ item.status_text }}</text>
					</view>
					<view class="card-title-row">
						<text class="card-title">{{ item.reason || '罚款' }}</text>
						<text class="card-amount">-{{ item.amount }}</text>
					</view>
					<view class="detail-list">
						<view class="detail-row">
							<text class="detail-label">罚款时间</text>
							<text class="detail-value">{{ item.fine_time_text }}</text>
						</view>
						<view class="detail-row" v-if="item.pay_time_text">
							<text class="detail-label">交款时间</text>
							<text class="detail-value">{{ item.pay_time_text }}</text>
						</view>
						<view class="detail-row">
							<text class="detail-label">处罚类型</text>
							<text class="detail-value">{{ item.punish_text }}</text>
						</view>
						<view class="detail-row" v-if="item.order_no">
							<text class="detail-label">关联订单</text>
							<text class="detail-value">{{ item.order_no }}</text>
						</view>
						<view class="detail-row">
							<text class="detail-label">罚款详情</text>
							<text class="detail-value link">点击查看</text>
						</view>
					</view>
				</view>
			</view>
		</z-paging>
		<!-- 底部缴纳按钮 -->
		<view class="footer-bar" v-if="unpaidTotal > 0">
			<view class="btn-submit" @click="goPayAll">交罚款 (待缴: ￥{{ unpaidTotal }})</view>
		</view>
		<!-- 详情弹窗 -->
		<up-popup :show="showPopup" mode="center" round="16" @close="showPopup=false" :overlay="true" :z-index="999">
			<view class="popup-content" v-if="currentItem">
				<view class="popup-title">罚款详情</view>
				<view class="popup-row"><text class="popup-label">罚单编号</text><text class="popup-val">{{ currentItem.fine_no }}</text></view>
				<view class="popup-row"><text class="popup-label">罚款金额</text><text class="popup-val amount">￥{{ currentItem.amount }}</text></view>
				<view class="popup-row"><text class="popup-label">罚款理由</text><text class="popup-val">{{ currentItem.reason }}</text></view>
				<view class="popup-row"><text class="popup-label">处罚类型</text><text class="popup-val">{{ currentItem.punish_text }}</text></view>
				<view class="popup-row"><text class="popup-label">罚款时间</text><text class="popup-val">{{ currentItem.fine_time_text }}</text></view>
				<view class="popup-row" v-if="currentItem.pay_time_text"><text class="popup-label">缴纳时间</text><text class="popup-val">{{ currentItem.pay_time_text }}</text></view>
				<view class="popup-row" v-if="currentItem.order_no"><text class="popup-label">关联订单</text><text class="popup-val">{{ currentItem.order_no }}</text></view>
				<view class="popup-row"><text class="popup-label">状态</text><text :class="['popup-val', statusClass(currentItem.status)]">{{ currentItem.status_text }}</text></view>
				<view class="popup-btn" v-if="currentItem.status===0" @click="openPay(currentItem)">立即缴纳</view>
				<view class="popup-btn-close" @click="showPopup=false">关闭</view>
			</view>
		</up-popup>
		<!-- 支付方式弹窗 -->
		<up-popup :show="showPayPopup" mode="center" round="16" @close="showPayPopup=false" :overlay="true" :z-index="1000">
			<view class="popup-content" v-if="payItem">
				<view class="popup-title">缴纳罚款</view>
				<view class="pay-amount-row">
					<text class="pay-amount-symbol">¥</text>
					<text class="pay-amount-num">{{ payItem.amount }}</text>
				</view>
				<view class="pay-fine-no">罚单号：{{ payItem.fine_no }}</view>
				<view class="pay-way-title">选择支付方式</view>
				<uv-radio-group v-model="payType" placement="column">
					<uv-radio class="pay-way-item" name="balance" :customStyle="{marginBottom:'8rpx'}">
						<view class="pay-type">
							<uv-icon name="red-packet" size="44rpx"></uv-icon>
							<text class="pay-type-title">余额支付</text>
						</view>
					</uv-radio>
					<uv-radio class="pay-way-item" name="yipay">
						<view class="pay-type">
							<uv-icon name="weixin-circle-fill" size="44rpx" color="#07c160"></uv-icon>
							<text class="pay-type-title">在线支付</text>
						</view>
					</uv-radio>
				</uv-radio-group>
				<view class="popup-btn" @click="payFine">确认支付</view>
				<view class="popup-btn-close" @click="showPayPopup=false">取消</view>
			</view>
		</up-popup>
	</view>
</template>

<script setup>
import { ref } from 'vue';
import { fineList, finePay } from '../../request/api';
import payUtil from '../../utils/pay';

const paging = ref(null)
const dataList = ref([])
const status = ref(-1)
const unpaidTotal = ref(0)
const showPopup = ref(false)
const currentItem = ref(null)
const showPayPopup = ref(false)
const payItem = ref(null)
const payType = ref('yipay')
const paying = ref(false)

const statusBarHeight = ref(20)
// #ifdef MP-WEIXIN
try {
	const sysInfo = uni.getSystemInfoSync()
	statusBarHeight.value = sysInfo.statusBarHeight || 44
} catch (e) { statusBarHeight.value = 44 }
// #endif

const goBack = () => {
	const p = getCurrentPages()
	if (p.length > 1) uni.navigateBack({ delta: 1 })
	else uni.reLaunch({ url: '/pages/index/index' })
}
const goHome = () => uni.reLaunch({ url: '/pages/index/index' })

const queryList = (pageNo, pageSize) => {
	fineList({ status: status.value, page: pageNo, limit: pageSize }).then(res => {
		unpaidTotal.value = res.data.unpaid_total || 0
		paging.value.complete(res.data.list?.data || [])
	}).catch(() => { paging.value.complete([]) })
}

const changeStatus = (s) => {
	status.value = s
	paging.value.reload()
}

const statusClass = (s) => {
	if (s === 0) return 'status-pending'
	if (s === 1) return 'status-done'
	return 'status-revoked'
}

const showDetail = (item) => {
	currentItem.value = item
	showPopup.value = true
}

const openPay = (item) => {
	payItem.value = item
	payType.value = 'yipay'
	showPopup.value = false
	showPayPopup.value = true
}

const payFine = () => {
	if (paying.value) return
	paying.value = true
	uni.showLoading({ title: '支付中...' })
	finePay({ id: payItem.value.id, pay_type: payType.value }).then(res => {
		if (res.code != 1) {
			uni.hideLoading()
			paying.value = false
			uni.showToast({ title: res.msg || '支付失败', icon: 'none' })
			return
		}
		if (payType.value === 'balance') {
			uni.hideLoading()
			paying.value = false
			uni.showToast({ title: '缴纳成功', icon: 'success' })
			showPayPopup.value = false
			paging.value.reload()
		} else {
			uni.hideLoading()
			paying.value = false
			showPayPopup.value = false
			payUtil.redirectPay(res.data, (err) => {
				paying.value = false
				uni.showToast({ title: err || '无法打开易支付', icon: 'none' })
			})
		}
	}).catch(() => {
		uni.hideLoading()
		paying.value = false
		uni.showToast({ title: '支付异常', icon: 'none' })
	})
}

const goPayAll = () => {
	if (status.value !== 0) {
		status.value = 0
		paging.value.reload()
	}
}
</script>

<style scoped>
.page { min-height: 100vh; background: #f5f6f9; display: flex; flex-direction: column; padding-bottom: calc(120rpx + env(safe-area-inset-bottom)); }
.top-bg { position: fixed; top: 0; left: 0; right: 0; width: 100%; height: 480rpx; z-index: 0; }
.navbar { position: relative; z-index: 20; display: flex; align-items: center; justify-content: space-between; padding: 0 24rpx; padding-bottom: 16rpx; }
.navbar-capsule { display: flex; align-items: center; border: 1rpx solid #dadbde; border-radius: 100px; padding: 8rpx 20rpx; background: rgba(255,255,255,0.3); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); }
.navbar-btn { padding: 4rpx 8rpx; }
.navbar-divider { width: 1rpx; height: 32rpx; background: #dadbde; margin: 0 8rpx; }
.navbar-title { font-size: 34rpx; font-weight: 600; color: #333; }
.navbar-placeholder { width: 120rpx; }
.tabs-wrap { display: flex; padding: 16rpx 24rpx; gap: 16rpx; background: rgba(255,255,255,0.6); backdrop-filter: blur(12rpx); -webkit-backdrop-filter: blur(12rpx); border-radius: 16rpx; margin: 0 24rpx; margin-top: 20rpx; position: relative; z-index: 1; }
.tab-item { flex: 1; text-align: center; padding: 14rpx 0; font-size: 26rpx; color: #666; border-radius: 12rpx; background: rgba(0,0,0,0.04); }
.tab-item.active { background: #000; color: #fff; font-weight: 500; }
.content { flex: 1; padding: 0 0 32rpx; box-sizing: border-box; }
.content-inner { padding: 20rpx 24rpx 0; }
.record-card { width: 100%; box-sizing: border-box; margin-bottom: 28rpx; padding: 32rpx; }
.card-glass { background: rgba(255,255,255,0.55); backdrop-filter: blur(24rpx); -webkit-backdrop-filter: blur(24rpx); border: 1rpx solid rgba(255,255,255,0.7); border-radius: 24rpx; box-shadow: 0 2rpx 20rpx rgba(0,0,0,0.04), inset 0 1rpx rgba(255,255,255,0.9); }
.card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20rpx; padding-bottom: 20rpx; border-bottom: 1rpx solid rgba(0,0,0,0.08); }
.card-time { font-size: 24rpx; color: #666; }
.card-status { font-size: 24rpx; padding: 4rpx 16rpx; border-radius: 8rpx; }
.status-pending { background: rgba(0,0,0,0.06); color: #666; }
.status-done { background: rgba(82,196,26,0.15); color: #52c41a; }
.status-revoked { background: rgba(0,0,0,0.04); color: #999; }
.card-title-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20rpx; gap: 24rpx; }
.card-title { flex: 1; min-width: 0; font-size: 30rpx; font-weight: 500; color: #16181f; }
.card-amount { flex-shrink: 0; font-size: 36rpx; font-weight: 700; color: #313131; }
.detail-list { display: flex; flex-direction: column; gap: 12rpx; }
.detail-row { display: flex; align-items: center; justify-content: space-between; gap: 24rpx; font-size: 26rpx; }
.detail-label { flex-shrink: 0; color: #999; }
.detail-value { flex: 1; min-width: 0; color: #333; text-align: right; word-break: break-all; }
.detail-value.link { color: #faad14; }
.bottom-spacer { height: 40rpx; }
.footer-bar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 10; padding: 20rpx 24rpx; padding-bottom: calc(20rpx + env(safe-area-inset-bottom)); background: #fff; box-shadow: 0 -2rpx 12rpx rgba(0,0,0,0.06); }
.btn-submit { width: 100%; height: 88rpx; line-height: 88rpx; text-align: center; font-size: 30rpx; color: #fff; background: linear-gradient(135deg, #ffc300, #faad14); border-radius: 16rpx; font-weight: 600; }
/* 弹窗 */
.popup-content { width: 600rpx; padding: 40rpx 32rpx; background: #fff; border-radius: 24rpx; }
.popup-title { font-size: 32rpx; font-weight: 600; color: #16181f; text-align: center; margin-bottom: 32rpx; }
.popup-row { display: flex; justify-content: space-between; align-items: flex-start; padding: 12rpx 0; font-size: 26rpx; gap: 20rpx; }
.popup-label { color: #999; flex-shrink: 0; }
.popup-val { color: #333; flex: 1; text-align: right; word-break: break-all; }
.popup-val.amount { color: #313131; font-weight: 700; font-size: 30rpx; }
.popup-btn { margin-top: 32rpx; height: 80rpx; line-height: 80rpx; text-align: center; background: linear-gradient(135deg, #ffc300, #faad14); color: #fff; border-radius: 16rpx; font-size: 28rpx; font-weight: 600; }
.popup-btn-close { margin-top: 16rpx; height: 72rpx; line-height: 72rpx; text-align: center; color: #999; font-size: 26rpx; }
/* 支付弹窗 */
.pay-amount-row { display: flex; align-items: baseline; justify-content: center; margin-bottom: 12rpx; }
.pay-amount-symbol { font-size: 32rpx; color: #da4a44; font-weight: 600; margin-right: 4rpx; }
.pay-amount-num { font-size: 56rpx; color: #da4a44; font-weight: 700; line-height: 1.2; }
.pay-fine-no { text-align: center; font-size: 24rpx; color: #999; margin-bottom: 28rpx; }
.pay-way-title { font-size: 28rpx; color: #333; font-weight: 600; margin-bottom: 20rpx; padding-bottom: 16rpx; border-bottom: 1rpx solid rgba(0,0,0,0.06); }
.pay-way-item { margin-bottom: 8rpx; }
.pay-type { display: flex; align-items: center; width: 100%; min-height: 88rpx; padding: 8rpx 0; }
.pay-type-title { font-size: 28rpx; color: #333; margin-left: 20rpx; }
</style>
