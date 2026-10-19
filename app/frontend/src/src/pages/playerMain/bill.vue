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
					<text class="navbar-title">系统缴费</text>
					<view class="navbar-placeholder"></view>
				</view>

				<!-- 主 Tab -->
				<view class="main-tabs">
					<view :class="['main-tab', tab==='mine'?'active':'']" @click="changeTab('mine')">我的缴费</view>
					<view :class="['main-tab', tab==='record'?'active':'']" @click="changeTab('record')">缴纳记录</view>
				</view>

				<!-- 子 Tab（仅我的缴费显示） -->
				<view class="sub-tabs" v-if="tab==='mine'">
					<view :class="['sub-tab', status===-1?'active':'']" @click="changeStatus(-1)">全部</view>
					<view :class="['sub-tab', status===0?'active':'']" @click="changeStatus(0)">待执行</view>
					<view :class="['sub-tab', status===2?'active':'']" @click="changeStatus(2)">已逾期</view>
				</view>
			</template>
			<template #empty>
				<uv-empty mode="list" text="暂无数据" style="margin-top:100rpx"></uv-empty>
			</template>
			<view class="content-inner">
				<view class="record-card card-glass" v-for="item in dataList" :key="item.id">
					<!-- 已逾期印章 -->
					<view class="stamp" v-if="tab==='mine' && item.status===2">
						<text class="stamp-main">已逾期</text>
						<text class="stamp-sub">缴费逾期</text>
					</view>
					<!-- 已缴纳标记 -->
					<view class="paid-tag" v-if="item.status===1">已缴纳</view>

					<view class="bill-no-row">
						<text class="bill-no-label">缴费单号：</text>
						<text class="bill-no">{{ item.bill_no }}</text>
					</view>
					<view class="detail-list">
						<view class="detail-row">
							<text class="detail-label">缴费项目：</text>
							<text class="detail-value">{{ item.project || '-' }}</text>
						</view>
						<view class="detail-row">
							<text class="detail-label">缴纳原因：</text>
							<text class="detail-value">{{ item.reason || '-' }}</text>
						</view>
						<view class="detail-row">
							<text class="detail-label">缴费备注：</text>
							<text class="detail-value">{{ item.remark || '-' }}</text>
						</view>
						<view class="detail-row" v-if="item.deadline">
							<text class="detail-label">缴费截止：</text>
							<text class="detail-value">{{ item.deadline }}</text>
						</view>
						<view class="detail-row">
							<text class="detail-label">{{ item.status===1 ? '缴纳时间：' : '创建时间：' }}</text>
							<text class="detail-value">{{ item.createtime }}</text>
						</view>
					</view>
					<view class="amount-row">
						<view class="amount-left">
							<text class="amount-label">缴费金额：</text>
							<text class="amount-num">{{ item.amount }}</text>
						</view>
						<view class="amount-btns" v-if="tab==='mine'">
							<view class="btn-dai" @click="openPay(item, 'for')">代缴</view>
							<view class="btn-bu" @click="openPay(item, 'self')">去补缴</view>
						</view>
					</view>
				</view>
			</view>
		</z-paging>

		<!-- 支付方式弹窗 -->
		<up-popup :show="showPayPopup" mode="center" round="16" @close="showPayPopup=false" :overlay="true" :z-index="1000">
			<view class="popup-content" v-if="payItem">
				<view class="popup-title">{{ payMode==='for' ? '代缴费用' : '缴纳费用' }}</view>
				<view class="pay-amount-row">
					<text class="pay-amount-symbol">¥</text>
					<text class="pay-amount-num">{{ payItem.amount }}</text>
				</view>
				<view class="pay-fine-no">缴费单号：{{ payItem.bill_no }}</view>
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
				<view class="popup-btn" @click="doPay">确认支付</view>
				<view class="popup-btn-close" @click="showPayPopup=false">取消</view>
			</view>
		</up-popup>
	</view>
</template>

<script setup>
import { ref } from 'vue';
import { billList, billRecordList, billPay, billPayFor } from '../../request/api';
import payUtil from '../../utils/pay';

const paging = ref(null)
const dataList = ref([])
const tab = ref('mine')   // mine=我的缴费 record=缴纳记录
const status = ref(-1)    // -1全部 0待执行 2已逾期
const showPayPopup = ref(false)
const payItem = ref(null)
const payMode = ref('self')
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
	const req = tab.value === 'mine'
		? billList({ status: status.value, page: pageNo, limit: pageSize })
		: billRecordList({ page: pageNo, limit: pageSize })
	req.then(res => {
		paging.value.complete((res.data && res.data.list && res.data.list.data) || [])
	}).catch(() => { paging.value.complete([]) })
}

const changeTab = (t) => {
	tab.value = t
	paging.value.reload()
}

const changeStatus = (s) => {
	status.value = s
	paging.value.reload()
}

const openPay = (item, mode) => {
	payItem.value = item
	payMode.value = mode
	payType.value = 'yipay'
	showPayPopup.value = true
}

const doPay = () => {
	if (paying.value) return
	paying.value = true
	uni.showLoading({ title: '支付中...' })
	const req = payMode.value === 'for'
		? billPayFor({ id: payItem.value.id, pay_type: payType.value })
		: billPay({ id: payItem.value.id, pay_type: payType.value })
	req.then(res => {
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
</script>

<style scoped>
.page { min-height: 100vh; background: #f5f6f9; display: flex; flex-direction: column; }
.top-bg { position: fixed; top: 0; left: 0; right: 0; width: 100%; height: 480rpx; z-index: 0; }
.navbar { position: relative; z-index: 20; display: flex; align-items: center; justify-content: space-between; padding: 0 24rpx; padding-bottom: 16rpx; }
.navbar-capsule { display: flex; align-items: center; border: 1rpx solid #dadbde; border-radius: 100px; padding: 8rpx 20rpx; background: rgba(255,255,255,0.3); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); }
.navbar-btn { padding: 4rpx 8rpx; }
.navbar-divider { width: 1rpx; height: 32rpx; background: #dadbde; margin: 0 8rpx; }
.navbar-title { font-size: 34rpx; font-weight: 600; color: #333; }
.navbar-placeholder { width: 120rpx; }
/* 主 Tab */
.main-tabs { display: flex; margin: 20rpx 24rpx 0; background: rgba(255,255,255,0.6); border-radius: 16rpx; padding: 8rpx; gap: 8rpx; position: relative; z-index: 1; }
.main-tab { flex: 1; text-align: center; padding: 20rpx 0; font-size: 28rpx; color: #333; border-radius: 12rpx; }
.main-tab.active { background: #1c1c1e; color: #fff; font-weight: 600; }
/* 子 Tab */
.sub-tabs { display: flex; margin: 24rpx 24rpx 0; position: relative; z-index: 1; }
.sub-tab { flex: 1; text-align: center; font-size: 28rpx; color: #666; padding-bottom: 14rpx; position: relative; }
.sub-tab.active { color: #111; font-weight: 700; }
.sub-tab.active::after { content: ''; position: absolute; left: 50%; transform: translateX(-50%); bottom: 0; width: 56rpx; height: 6rpx; border-radius: 6rpx; background: linear-gradient(90deg, #7c6bff, #4d7cff); }
.content-inner { padding: 24rpx 24rpx 40rpx; }
.record-card { position: relative; width: 100%; box-sizing: border-box; margin-bottom: 28rpx; padding: 32rpx; overflow: hidden; }
.card-glass { background: rgba(255,255,255,0.85); backdrop-filter: blur(24rpx); -webkit-backdrop-filter: blur(24rpx); border: 1rpx solid rgba(255,255,255,0.7); border-radius: 24rpx; box-shadow: 0 2rpx 20rpx rgba(0,0,0,0.04); }
.bill-no-row { display: flex; align-items: center; margin-bottom: 24rpx; }
.bill-no-label { font-size: 26rpx; color: #666; }
.bill-no { font-size: 26rpx; color: #666; }
.detail-list { display: flex; flex-direction: column; gap: 14rpx; }
.detail-row { display: flex; font-size: 26rpx; }
.detail-label { flex-shrink: 0; color: #999; }
.detail-value { flex: 1; min-width: 0; color: #333; word-break: break-all; }
.amount-row { display: flex; align-items: center; justify-content: space-between; margin-top: 30rpx; padding-top: 24rpx; border-top: 1rpx solid rgba(0,0,0,0.06); }
.amount-left { display: flex; align-items: baseline; }
.amount-label { font-size: 26rpx; color: #999; }
.amount-num { font-size: 40rpx; font-weight: 700; color: #111; margin-left: 8rpx; }
.amount-btns { display: flex; gap: 20rpx; }
.btn-dai { min-width: 132rpx; height: 64rpx; line-height: 64rpx; text-align: center; font-size: 26rpx; color: #fff; border-radius: 999rpx; background: linear-gradient(135deg, #7c6bff, #5b53ff); padding: 0 28rpx; }
.btn-bu { min-width: 132rpx; height: 64rpx; line-height: 64rpx; text-align: center; font-size: 26rpx; color: #fff; border-radius: 999rpx; background: linear-gradient(135deg, #4d7cff, #3a63ff); padding: 0 28rpx; }
/* 已逾期印章 */
.stamp { position: absolute; right: 40rpx; top: 130rpx; width: 132rpx; height: 132rpx; border: 4rpx solid rgba(214,74,68,0.7); border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; transform: rotate(-16deg); opacity: 0.85; }
.stamp-main { font-size: 30rpx; font-weight: 700; color: rgba(214,74,68,0.9); letter-spacing: 2rpx; }
.stamp-sub { font-size: 18rpx; color: rgba(214,74,68,0.7); margin-top: 6rpx; }
.paid-tag { position: absolute; right: 32rpx; top: 32rpx; font-size: 22rpx; color: #52c41a; background: rgba(82,196,26,0.12); padding: 6rpx 18rpx; border-radius: 8rpx; }
/* 弹窗 */
.popup-content { width: 600rpx; padding: 40rpx 32rpx; background: #fff; border-radius: 24rpx; }
.popup-title { font-size: 32rpx; font-weight: 600; color: #16181f; text-align: center; margin-bottom: 32rpx; }
.popup-btn { margin-top: 32rpx; height: 80rpx; line-height: 80rpx; text-align: center; background: linear-gradient(135deg, #7c6bff, #5b53ff); color: #fff; border-radius: 16rpx; font-size: 28rpx; font-weight: 600; }
.popup-btn-close { margin-top: 16rpx; height: 72rpx; line-height: 72rpx; text-align: center; color: #999; font-size: 26rpx; }
.pay-amount-row { display: flex; align-items: baseline; justify-content: center; margin-bottom: 12rpx; }
.pay-amount-symbol { font-size: 32rpx; color: #da4a44; font-weight: 600; margin-right: 4rpx; }
.pay-amount-num { font-size: 56rpx; color: #da4a44; font-weight: 700; line-height: 1.2; }
.pay-fine-no { text-align: center; font-size: 24rpx; color: #999; margin-bottom: 28rpx; }
.pay-way-title { font-size: 28rpx; color: #333; font-weight: 600; margin-bottom: 20rpx; padding-bottom: 16rpx; border-bottom: 1rpx solid rgba(0,0,0,0.06); }
.pay-way-item { margin-bottom: 8rpx; }
.pay-type { display: flex; align-items: center; width: 100%; min-height: 88rpx; padding: 8rpx 0; }
.pay-type-title { font-size: 28rpx; color: #333; margin-left: 20rpx; }
</style>
