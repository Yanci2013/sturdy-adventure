<template>
	<view class="gd-container">
		<!-- 顶部导航 -->
		<view class="gd-navbar">
			<view class="gd-navbar-left">
				<view class="gd-navbar-btn" @click="navBack">
					<up-icon name="arrow-left" size="32rpx" color="#fff"></up-icon>
				</view>
				<view class="gd-navbar-btn" @click="goHome">
					<up-icon name="home" size="32rpx" color="#fff"></up-icon>
				</view>
			</view>
		</view>

		<!-- Banner图 -->
		<view class="gd-banner">
			<up-swiper v-if="dataInfo.images && dataInfo.images.length > 1"
				:list="dataInfo.images" height="auto" radius="0" :autoplay="false"
				@click="handleSwiper" bgColor="transparent" imgMode="widthFix">
			</up-swiper>
			<image v-else-if="dataInfo.images && dataInfo.images.length === 1"
				class="gd-banner-img" :src="dataInfo.images[0]" mode="widthFix" @click="handleSwiper(0)"></image>
			<image v-else-if="dataInfo.logo" class="gd-banner-img" :src="dataInfo.logo" mode="widthFix"></image>
		</view>

		<!-- 主内容区 -->
		<view class="gd-main">
			<!-- 商品信息卡片 -->
			<view class="gd-area gd-detail-box gd-card-overlap">
				<view class="gd-title-box">
					<text>{{ dataInfo.title }}</text>
					<view class="gd-discount-tag" v-if="dataInfo.award == 1">赠送转盘</view>
				</view>
				<view class="gd-sales-text" v-if="dataInfo.sales">已售:{{ dataInfo.sales }}</view>
				<view class="gd-price-box">
					<view class="gd-golden">
						<image class="gd-price-icon" src="/static/yun/golden-diamond.png" mode=""></image>
						<text>{{ dataInfo.price }}</text>
					</view>
					<view class="gd-gray" v-if="dataInfo.line_price">
						<image class="gd-price-icon-sm" src="/static/yun/gray-diamond.png" mode=""></image>
						<text class="gd-line-price">{{ dataInfo.line_price }}</text>
					</view>
				</view>
				<view class="gd-introduce" v-if="dataInfo.synopsis">{{ dataInfo.synopsis }}</view>
			</view>

			<!-- 极速服务 -->
			<view class="gd-area gd-jsfh">
				<view class="gd-jsfh-item">
					<image src="/static/yun/guide.png" mode="aspectFit"></image>
					<text>极速接单</text>
				</view>
				<view class="gd-jsfh-item">
					<image src="/static/yun/guide.png" mode="aspectFit"></image>
					<text>极速响应</text>
				</view>
				<view class="gd-jsfh-item">
					<image src="/static/yun/guide.png" mode="aspectFit"></image>
					<text>服务至上</text>
				</view>
				<view class="gd-jsfh-item">
					<image src="/static/yun/guide.png" mode="aspectFit"></image>
					<text>官方严选</text>
				</view>
			</view>

			<!-- 奖池列表 -->
			<view class="gd-area" v-if="dataInfo.award == 1 && prizeList.length">
				<view class="gd-subtitle">
					<image src="/static/yun/good-detail.png" mode="aspectFit"></image>
					<text>奖池列表 · 下单赠1次抽取机会</text>
				</view>
				<view class="gd-prize-scroll-wrap">
					<view class="gd-prize-scroll-track" :class="{'gd-prize-scroll-track-short': prizeList.length <= 4}" :style="'width:' + prizeTrackWidth">
						<view class="gd-prize-scroll-item" v-for="(item, index) in prizesRepeated" :key="index">
							<view class="gd-prize-item">
								<view class="gd-prize-img-wrap">
									<image class="gd-prize-img" mode="aspectFill" :src="item.image"></image>
								</view>
								<text class="gd-prize-name">{{item.name}}</text>
								<text class="gd-prize-num">×1</text>
							</view>
						</view>
					</view>
				</view>
			</view>

			<!-- 商品详情 -->
			<view class="gd-area" v-if="dataInfo.content">
				<view class="gd-subtitle">
					<image src="/static/yun/good-detail.png" mode="aspectFit"></image>
					<text>商品详情</text>
				</view>
				<view class="gd-good-detail-box">
					<up-parse :content="dataInfo.content"></up-parse>
				</view>
			</view>

			<!-- 用户评价 -->
			<view class="gd-area" v-if="commentData.total > 0">
				<view class="gd-subtitle">
					<image src="/static/yun/user-comment.png" mode="aspectFit"></image>
					<text>用户评价 ({{commentData.total}})</text>
					<text class="gd-comment-avg" v-if="commentData.avg_rating">{{commentData.avg_rating}}分</text>
				</view>
				<view class="gd-comment-list">
					<view class="gd-comment-item" v-for="item in commentData.list" :key="item.id">
						<view class="gd-comment-header">
							<image class="gd-comment-avatar" :src="item.avatar || '/static/icon/default_avatar.png'" mode="aspectFill"></image>
							<view class="gd-comment-info">
								<text class="gd-comment-name">{{item.nickname}}</text>
								<view class="gd-comment-meta">
									<up-rate :modelValue="item.rating" :count="5" :size="12" :readonly="true" gutter="1" activeColor="#ffc300"></up-rate>
									<text class="gd-comment-time">{{item.time}}</text>
								</view>
							</view>
						</view>
						<text class="gd-comment-content" v-if="item.content">{{item.content}}</text>
						<view class="gd-comment-pics" v-if="item.images && item.images.length">
							<image v-for="(img, idx) in item.images" :key="idx" class="gd-comment-pic" :src="img" mode="aspectFill" @click="previewCommentImage(item.images, idx)"></image>
						</view>
					</view>
				</view>
				<view class="gd-comment-more" v-if="commentData.total > 3" @click="loadMoreComments">
					<text>查看更多评价</text>
					<up-icon name="arrow-right" size="12" color="#999"></up-icon>
				</view>
			</view>

			<view style="height:180rpx"></view>
		</view>

		<!-- 底部操作栏 -->
		<view class="gd-footer">
			<view class="gd-footer-inner">
				<view class="gd-footer-left">
					<view class="gd-footer-icon-btn" @click="toKefu">
						<up-icon name="kefu-ermai" size="36rpx" color="#090b08"></up-icon>
						<text>客服</text>
					</view>
				</view>
				<view class="gd-btn-wrap">
					<button class="gd-btn-order" @click="submit">立即下单</button>
				</view>
			</view>
		</view>

		<!-- 绑定手机号弹窗 -->
		<up-modal :show="show" title="绑定手机号" showCancelButton cancelText="稍后再说" confirmText="绑定"
			@cancel="show=false" @confirm="confirm">
			<up-input v-model="mobile" placeholder="请填写您的手机号码" inputAlign="center" border="none"
				:customStyle="customStyle"></up-input>
		</up-modal>

		<!-- 客服列表弹窗 -->
		<uv-modal ref="customerServiceModal" title="客服列表" :showConfirmButton="true" confirmText="确认" @confirm="closeCustomerService">
			<view style="width:100%;">
				<scroll-view scroll-y style="max-height:60vh;">
					<view v-if="cslist.length">
						<view v-for="(item, index) in cslist" :key="item.id" class="cs-item" @click="goCustomerService(item)">
							<image class="cs-avatar" :src="item.image || '/static/icon/default_avatar.png'" mode="aspectFill"></image>
							<view class="cs-info"><text class="cs-name">{{ item.name }}</text></view>
							<view class="cs-arrow"><up-icon name="arrow-right" size="16" color="#999"></up-icon></view>
						</view>
					</view>
					<uv-empty v-else mode="data" text="暂无客服，等待平台添加"></uv-empty>
				</scroll-view>
			</view>
		</uv-modal>

		<u-toast ref="uToastRef"></u-toast>
	</view>
</template>

<script setup>
	import { ref, computed } from 'vue';
	import { onLoad } from '@dcloudio/uni-app';
	import { goodsMsg, getUserInfo, userEdit, commentList } from '../../request/api';
	import router from '../../utils/router';
import { openCustomerServiceChat, resolveCustomerService } from '../../utils/customer-service';
	import { test } from '../../uni_modules/uview-plus';

	const id = ref('')
	const dataInfo = ref('')
	const thugId = ref('')
	const mobile = ref('')
	const customStyle = { height: '96rpx', background: '#F8FAFD' }
	const show = ref(false)
	const uToastRef = ref(null)
	const customerServiceModal = ref(null)
	const cslist = ref([])
	const prizeList = ref([])
	const commentData = ref({ total: 0, avg_rating: 0, list: [] })
	const commentPage = ref(1)

	const prizeCopyCount = computed(() => prizeList.value.length <= 2 ? 4 : 2)
	const prizesRepeated = computed(() => {
		const list = prizeList.value
		if (!list.length) return []
		return Array.from({ length: prizeCopyCount.value }, () => [...list]).flat()
	})
	const prizeTrackWidth = computed(() => {
		const len = prizeList.value.length
		return len ? (len * prizeCopyCount.value * 176) + 'rpx' : 'auto'
	})

	onLoad((prop) => {
		id.value = prop.id
		thugId.value = prop.thugId
		getData()
	})

	const getData = () => {
		goodsMsg({ id: id.value }).then(res => {
			dataInfo.value = res.data
			prizeList.value = res.data.prizeList || []
		})
		getComments()
	}

	const getComments = () => {
		commentList({ goods_id: id.value, page: commentPage.value, limit: 3 }).then(res => {
			if (commentPage.value === 1) {
				commentData.value = res.data
			} else {
				commentData.value.list = [...commentData.value.list, ...res.data.list]
				commentData.value.last_page = res.data.last_page
				commentData.value.current_page = res.data.current_page
			}
		}).catch(() => {})
	}

	const loadMoreComments = () => {
		if (commentData.value.current_page < commentData.value.last_page) {
			commentPage.value++
			getComments()
		}
	}

	const previewCommentImage = (urls, idx) => {
		uni.previewImage({ urls, current: urls[idx] || urls[0] })
	}

	const toKefu = async () => {
		let r = { direct: null, list: [] }
		try {
			uni.showLoading({ mask: true, title: '加载中' })
			r = await resolveCustomerService()
		} catch (e) {
			r = { direct: null, list: [] }
		} finally {
			uni.hideLoading()
		}
		// 有内置客服则直接进入内置客服会话
		if (r.direct) { openCustomerServiceChat(r.direct); return }
		cslist.value = r.list
		customerServiceModal.value.open()
	}

	const closeCustomerService = () => {
		customerServiceModal.value.close()
	}

	const goCustomerService = (item) => {
	openCustomerServiceChat(item)
	}

	const handleSwiper = (index) => {
		uni.previewImage({
			urls: dataInfo.value.images,
			current: index
		})
	}

	const confirm = () => {
		if (test.isEmpty(mobile.value)) {
			return uni.showToast({ title: '请输入手机号', icon: 'none' })
		}
		userEdit({ mobile: mobile.value }).then(res => {
			uni.showToast({ title: '绑定成功', icon: 'none' })
			show.value = false
			router.navigateTo({
				url: 'pagesA/index/order',
				data: { id: dataInfo.value.id, thugId: thugId.value }
			})
		})
	}

	const submit = async () => {
		if (test.isEmpty(uni.getStorageSync('token'))) {
			uni.setStorageSync('navtype', 'goodsDetail')
			uni.setStorageSync('goodsId', dataInfo.value.id)
			return router.navigateTo({ url: 'pages/login/login' })
		}
		const { number, gm, id } = dataInfo.value
		if (number > 0 && gm == 0) {
			return uToastRef.value.show({
				type: 'default',
				message: `该商品属于限购商品，每人限购${number}件，当前最多可购买0件`
			})
		}
		router.navigateTo({
			url: 'pagesA/index/order',
			data: { id: id, thugId: thugId.value, num: number > 0 ? gm : 0 }
		})
	}

	const navBack = () => {
		router.reLaunch({ url: 'pages/index/index' })
	}

	const goHome = () => {
		router.reLaunch({ url: 'pages/index/index' })
	}
</script>

<style lang="scss">
	.gd-container {
		background-color: #f5f6f9;
		min-height: 100vh;
		padding-bottom: 0;
	}

	/* 导航栏 */
	.gd-navbar {
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		z-index: 99;
		display: flex;
		flex-direction: row !important;
		align-items: center;
		height: 88rpx;
		padding: 0 24rpx;
		padding-top: var(--status-bar-height, 40rpx);
		background: transparent;
	}

	.gd-navbar-left {
		display: flex;
		flex-direction: row !important;
		align-items: center;
		gap: 12rpx;
	}

	.gd-navbar-btn {
		width: 56rpx;
		height: 56rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		border-radius: 50%;
		background: rgba(0, 0, 0, .35);
	}

	/* Banner */
	.gd-banner {
		width: 100vw;
		position: relative;
		background: #eee;
	}

	.gd-banner-img {
		width: 100%;
		display: block;
	}

	/* 主内容 */
	.gd-main {
		position: relative;
		z-index: 2;
		padding: 20rpx;
		padding-bottom: 0;
		margin-top: -60rpx;
	}

	.gd-area {
		background: rgba(255, 255, 255, 0.7);
		backdrop-filter: blur(20rpx);
		-webkit-backdrop-filter: blur(20rpx);
		border-radius: 24rpx;
		padding: 30rpx;
		margin: 20rpx 0;
		border: 1rpx solid rgba(255, 255, 255, 0.5);
		box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.06);
	}

	.gd-card-overlap {
		box-shadow: 0 4rpx 24rpx rgba(0, 0, 0, .06);
	}

	/* 标题 */
	.gd-title-box {
		font-size: 32rpx;
		font-weight: 700;
		display: flex;
		flex-direction: row !important;
		align-items: center;
	}

	.gd-sales-text {
		font-size: 24rpx;
		color: #FFB38A;
		margin-top: 10rpx;
		padding-bottom: 16rpx;
		border-bottom: 1rpx solid rgba(0, 0, 0, .06);
	}

	/* 价格 */
	.gd-price-box {
		display: flex;
		flex-direction: row !important;
		align-items: center;
		justify-content: space-between;
		margin: 20rpx 0;
	}

	.gd-golden {
		display: flex;
		flex-direction: row !important;
		align-items: center;
		font-size: 36rpx;
		color: #ff5500;
		font-weight: 700;
	}

	.gd-price-icon {
		width: 36rpx !important;
		height: 36rpx !important;
		margin-right: 10rpx;
		filter: hue-rotate(200deg) saturate(1.5) brightness(1.2);
	}

	.gd-gray {
		display: flex;
		flex-direction: row !important;
		align-items: center;
		font-size: 28rpx;
		color: gold;
	}

	.gd-price-icon-sm {
		width: 28rpx !important;
		height: 28rpx !important;
		margin-right: 10rpx;
		filter: grayscale(100%) brightness(1.5);
	}

	.gd-line-price {
		color: #8f8f8f;
		text-decoration: line-through;
	}

	.gd-introduce {
		color: #8f8f8f;
		font-size: 24rpx;
	}

	/* 赠送转盘标签 */
	.gd-discount-tag {
		margin-left: 10rpx;
		padding: 0 18rpx;
		height: 44rpx;
		line-height: 44rpx;
		font-size: 24rpx;
		font-weight: 600;
		color: #fff;
		background: linear-gradient(135deg, #FF9D6E, #FF7A45);
		border-radius: 22rpx;
		flex-shrink: 0;
		box-shadow: 0 4rpx 12rpx rgba(168, 216, 255, 0.3);
	}

	/* 奖池滚动 */
	.gd-prize-scroll-wrap {
		width: 100%;
		margin-top: 20rpx;
		overflow: hidden;
		height: auto;
	}
	.gd-prize-scroll-track {
		display: flex;
		flex-shrink: 0;
		align-items: flex-start;
		animation: gd-prize-scroll 22s linear infinite;
		will-change: transform;
	}
	.gd-prize-scroll-track-short {
		animation-name: gd-prize-scroll-short;
	}
	@keyframes gd-prize-scroll {
		0% { transform: translate(0); }
		100% { transform: translate(-50%); }
	}
	@keyframes gd-prize-scroll-short {
		0% { transform: translate(0); }
		100% { transform: translate(-25%); }
	}
	.gd-prize-scroll-item {
		width: 176rpx;
		flex-shrink: 0;
		box-sizing: border-box;
		padding: 0 6rpx;
	}
	.gd-prize-item {
		display: flex;
		flex-direction: column;
		align-items: center;
		padding: 16rpx 12rpx 12rpx;
		background: linear-gradient(135deg, rgba(168,216,255,0.4), rgba(196,164,255,0.4));
		border-radius: 16rpx;
		border: 1rpx solid rgba(255,255,255,0.6);
		box-shadow: 0 4rpx 16rpx rgba(168,216,255,0.2);
		box-sizing: border-box;
	}
	.gd-prize-img-wrap {
		width: 116rpx;
		height: 116rpx;
		border-radius: 14rpx;
		overflow: hidden;
		background: linear-gradient(145deg, rgba(168,216,255,0.2), rgba(196,164,255,0.2));
		box-shadow: inset 0 1rpx 2rpx rgba(255,255,255,0.8), 0 2rpx 8rpx rgba(0,0,0,0.04);
	}
	.gd-prize-img {
		width: 100%;
		height: 100%;
		display: block;
	}
	.gd-prize-name {
		font-size: 24rpx;
		color: #4a5568;
		font-weight: 500;
		margin-top: 8rpx;
		text-align: center;
		overflow: hidden;
		text-overflow: ellipsis;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
		line-height: 1.35;
		min-height: 44rpx;
		max-width: 100%;
	}
	.gd-prize-num {
		font-size: 22rpx;
		color: #FFD4B8;
		font-weight: 600;
		margin-top: 4rpx;
		padding: 2rpx 10rpx;
		border-radius: 8rpx;
		background: rgba(196,164,255,0.15);
	}

	/* 极速服务 */
	.gd-jsfh {
		display: flex;
		flex-direction: row !important;
		align-items: center;
		justify-content: space-between;
	}

	.gd-jsfh-item {
		display: flex;
		flex-direction: row !important;
		align-items: center;
	}

	.gd-jsfh-item image {
		margin-right: 10rpx;
		width: 24rpx !important;
		height: 24rpx !important;
		filter: hue-rotate(200deg) saturate(1.2) brightness(1.1);
	}

	.gd-jsfh-item text {
		font-size: 22rpx;
		color: #64748b;
		font-weight: 500;
	}

	/* 小标题 */
	.gd-subtitle {
		display: flex;
		flex-direction: row !important;
		align-items: center;
		padding-bottom: 20rpx;
		border-bottom: 1rpx solid rgba(0, 0, 0, .08);
	}

	.gd-subtitle image {
		width: 36rpx !important;
		height: 40rpx !important;
		margin-right: 10rpx;
		filter: hue-rotate(200deg) saturate(2) brightness(1.2);
	}

	.gd-subtitle text {
		font-size: 28rpx;
		font-weight: 500;
		color: #4a5568;
	}

	.gd-good-detail-box {
		margin-top: 20rpx;
	}

	/* 底部操作栏 */
	.gd-footer {
		min-height: 100rpx;
		width: 100%;
		position: fixed;
		bottom: 0;
		left: 0;
		z-index: 9;
		box-sizing: border-box;
		padding: 10rpx 20rpx 0;
		padding-bottom: calc(8rpx + env(safe-area-inset-bottom));
		background: rgba(255, 255, 255, 0.85);
		backdrop-filter: blur(20rpx);
		-webkit-backdrop-filter: blur(20rpx);
		border-top: 1rpx solid rgba(255, 255, 255, 0.5);
	}

	.gd-footer-inner {
		display: flex;
		flex-direction: row !important;
		align-items: center;
		gap: 16rpx;
	}

	.gd-footer-left {
		display: flex;
		flex-direction: row !important;
		align-items: center;
		gap: 16rpx;
	}

	.gd-footer-icon-btn {
		display: flex;
		flex-direction: column !important;
		align-items: center;
		justify-content: center;
		gap: 2rpx;
	}

	.gd-footer-icon-btn text {
		font-size: 20rpx;
		color: #090b08;
	}

	.gd-btn-wrap {
		flex: 1;
	}

	.gd-btn-order {
		width: 100%;
		height: 72rpx;
		line-height: 72rpx;
		background: linear-gradient(135deg, #FF9D6E, #FF7A45);
		border-radius: 36rpx;
		font-size: 28rpx;
		color: #fff;
		font-weight: 600;
		border: none;
		text-align: center;
		box-shadow: 0 4rpx 16rpx rgba(168, 216, 255, 0.4);
	}

	.cs-item {
		display: flex;
		align-items: center;
		padding: 20rpx 16rpx;
		border-bottom: 1rpx solid #f0f0f0;
	}
	.cs-item:last-child { border-bottom: none; }
	.cs-avatar {
		width: 80rpx; height: 80rpx;
		border-radius: 12rpx;
		flex-shrink: 0;
		margin-right: 20rpx;
		background: #f5f5f5;
	}
	.cs-info { flex: 1; min-width: 0; }
	.cs-name { font-size: 28rpx; color: #333; font-weight: 500; }
	.cs-arrow { flex-shrink: 0; margin-left: 12rpx; }

	/* 评论区 */
	.gd-comment-avg {
		margin-left: auto;
		font-size: 24rpx;
		color: #FFD4B8;
		font-weight: 600;
	}
	.gd-comment-list {
		margin-top: 20rpx;
	}
	.gd-comment-item {
		padding: 20rpx 0;
		border-bottom: 1rpx solid rgba(0,0,0,0.06);
	}
	.gd-comment-item:last-child {
		border-bottom: none;
	}
	.gd-comment-header {
		display: flex;
		flex-direction: row !important;
		align-items: center;
		gap: 16rpx;
		margin-bottom: 12rpx;
	}
	.gd-comment-avatar {
		width: 64rpx;
		height: 64rpx;
		border-radius: 50%;
		flex-shrink: 0;
		background: #f0f0f0;
	}
	.gd-comment-info {
		flex: 1;
		min-width: 0;
	}
	.gd-comment-name {
		font-size: 26rpx;
		color: #333;
		font-weight: 500;
		display: block;
	}
	.gd-comment-meta {
		display: flex;
		flex-direction: row !important;
		align-items: center;
		gap: 12rpx;
		margin-top: 4rpx;
	}
	.gd-comment-time {
		font-size: 22rpx;
		color: #999;
	}
	.gd-comment-content {
		font-size: 28rpx;
		color: #090b08;
		line-height: 1.6;
		display: block;
		margin-bottom: 12rpx;
	}
	.gd-comment-pics {
		display: flex;
		flex-direction: row !important;
		flex-wrap: wrap;
		gap: 12rpx;
	}
	.gd-comment-pic {
		width: 160rpx;
		height: 160rpx;
		border-radius: 12rpx;
		background: #f0f0f0;
	}
	.gd-comment-more {
		display: flex;
		flex-direction: row !important;
		align-items: center;
		justify-content: center;
		gap: 8rpx;
		padding: 20rpx 0 0;
		font-size: 24rpx;
		color: #999;
	}
</style>
