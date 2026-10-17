<template>
	<view class="main-container">
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
			<text class="navbar-title">抢单大厅</text>
			<view class="navbar-msg" @click="goMessage">
				<up-icon name="chat" size="36rpx" color="#606266"></up-icon>
			</view>
		</view>

		<!-- 收益卡片 -->
		<view class="revenue-card">
			<view class="revenue-item">
				<text class="revenue-label">今日订单收益</text>
				<text class="revenue-value">¥{{ todayOrderIncome }}</text>
			</view>
			<view class="revenue-divider"></view>
			<view class="revenue-item">
				<text class="revenue-label">今日礼物收益</text>
				<text class="revenue-value">¥{{ todayGiftIncome }}</text>
			</view>
			<view class="revenue-divider"></view>
			<view class="revenue-item">
				<text class="revenue-label">我的余额</text>
				<text class="revenue-value">¥{{ myBalance }}</text>
			</view>
		</view>

	<z-paging ref="paging" v-model="dataList" @query="queryList" :auto="false" auto-show-back-to-top
		@scrollTopChange="scrollTopChange" :show-refresher-update-time="false">
		<!-- 搜索栏 -->
		<view class="search-wrap">
			<up-icon name="search" size="32rpx" color="#999"></up-icon>
			<input
				class="search-input"
				type="text"
				v-model="keyword"
				placeholder="商品名称"
				placeholder-class="search-placeholder"
				confirm-type="search"
				@confirm="doSearch"
			/>
		</view>

		<!-- 游戏分类切换 -->
		<view class="category-panel">
			<scroll-view class="game-category-scroll" scroll-x :show-scrollbar="false">
				<view class="game-category-list">
					<view
						v-for="(item, index) in gameList"
						:key="item.id || index"
						:class="['game-category-item', selectedCategoryId === item.id ? 'active' : '']"
						@click="selectCategory(item)"
					>
						<text class="game-category-name">{{ item.name }}</text>
						<view class="game-category-mark"></view>
					</view>
				</view>
			</scroll-view>

			<view class="zone-tabs-wrap">
				<view
					v-for="(item, index) in tabList"
					:key="index"
					:class="['zone-tab-item', current === index ? 'active' : '']"
					@click="changeTab(index)"
				>
					<text class="zone-tab-text">{{ item.name }}</text>
				</view>
			</view>
		</view>

		<view class="page">
			<!-- 订单列表 -->
			<view class="content-inner">
				<view class="order-card"
					v-for="(item, index) in dataList"
					:key="item.id || index">
					<view class="card-header">
						<text class="card-server">{{ item.game_qf || '未知区服' }}</text>
						<text class="card-fee">预计服务费：¥{{ item.received_price }}</text>
					</view>

					<!-- 商品信息 -->
					<view class="goods-row">
						<image class="goods-img" :src="item.logo" mode="aspectFill"></image>
						<view class="goods-info">
							<text class="goods-title">{{ item.title }}</text>
							<text class="goods-desc">{{ item.synopsis }}</text>
							<text class="goods-price">¥{{ item.price }} <text class="goods-qty">×{{ item.number }}</text></text>
						</view>
					</view>

					<view class="card-footer">
						<view class="btn-view" @click.stop="goDetail(item)">查看</view>
					</view>
				</view>

				<uv-empty v-if="!dataList.length" mode="data" text="暂无数据" style="margin-top:100rpx"></uv-empty>

				<view class="bottom-spacer"></view>
			</view>
		</view>
	</z-paging>
		<!-- 底部导航栏 -->
		<player-tabbar :current="0"></player-tabbar>
	</view>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { onShow, onLoad } from '@dcloudio/uni-app'
import { receivingOrders, thugMsg, category, getThugPermissions } from '../../request/api'
import router from '../../utils/router'

const paging = ref(null)
const tabList = [{ name: '全部' }, { name: '手机端' }, { name: '电脑端' }]
const current = ref(0)
const keyword = ref('')
const dataList = ref([])
const cateId = ref('')
const cateList = ref([])
const categoryName = ref('')
const gameList = ref([])
const selectedCategoryId = ref('')
const selectedCategoryName = ref('')
const todayOrderIncome = ref('0.00')
const todayGiftIncome = ref('0.00')
const myBalance = ref('0.00')

const goMessage = () => {
	uni.reLaunch({ url: '/pages/playerMain/message' })
}

onLoad((options) => {
	if (options.category_id) {
		cateId.value = options.category_id
		selectedCategoryId.value = options.category_id
	}
	if (options.category_name) {
		categoryName.value = options.category_name
		selectedCategoryName.value = options.category_name
	}
})

onShow(() => {
	const thugId = uni.getStorageSync('id')
	if (thugId) {
		thugMsg({ thug_id: thugId }).then(res => {
			if (res.data.sh_status == 2) {
				uni.showModal({
					title: '提示',
					content: '您已被禁止接单请联系客服',
					showCancel: false,
					confirmText: '确定',
					success: result => {
						if (result.confirm) {
							uni.setStorageSync('type', '用户')
							uni.removeStorageSync('id')
							router.reLaunch({ url: 'pages/index/index' })
						}
					}
				})
			}
		})
	}
})

onMounted(() => {
	loadGameList()
	loadData(1)
})

const loadGameList = async () => {
	try {
		const categoryRes = await category()
		const allCategories = categoryRes.data || []

		const permissionsRes = await getThugPermissions()
		const permissions = permissionsRes.data || []

		const allowedGameNames = []
		permissions.forEach(p => {
			const gameName = p.split('-')[0]
			if (gameName && !allowedGameNames.includes(gameName)) {
				allowedGameNames.push(gameName)
			}
		})

		let filteredCategories = []
		if (allowedGameNames.length > 0) {
			filteredCategories = allCategories.filter(cat => allowedGameNames.includes(cat.name))
		} else {
			filteredCategories = allCategories
		}

		// 在第一个位置添加“全部”选项
		gameList.value = [
			{ id: '', name: '全部' },
			...filteredCategories
		]
	} catch (e) {
		console.log('加载游戏列表失败', e)
	}
}

const queryList = (pageNo, pageSize) => {
	loadData(pageNo)
}

const loadData = async (pageNo) => {
	try {
		const type = tabList[current.value].name === '全部' ? '' : tabList[current.value].name
		const params = {
			game_qf: type,
			page: pageNo,
			limit: 10
		}
		// 只有选择了具体分类才传type和category_name
		if (selectedCategoryId.value && selectedCategoryId.value !== '') {
			params.type = selectedCategoryId.value
			params.category_name = selectedCategoryName.value
		}
		const res = await receivingOrders(params)
		const newList = res.data?.data || res.data || []
		if (pageNo === 1) {
			dataList.value = newList
		} else {
			dataList.value = [...dataList.value, ...newList]
		}
		paging.value.complete(newList)
	} catch (e) {
		console.log(e)
		paging.value.complete([])
	}
}

const changeTab = (index) => {
	current.value = index
	paging.value.reload()
}

const selectCategory = (item) => {
	selectedCategoryId.value = item.id
	selectedCategoryName.value = item.name
	paging.value.reload()
}

const doSearch = () => {
	paging.value.reload()
}

const goDetail = (item) => {
	router.navigateTo({
		url: 'pagesA/mine/order/detail',
		data: { id: item.id }
	})
}

const goBack = () => {
	const pages = getCurrentPages()
	if (pages.length > 1) {
		uni.navigateBack()
	} else {
		uni.reLaunch({ url: '/pages/playerMain/index' })
	}
}

const goHome = () => {
	uni.reLaunch({ url: '/pages/index/index' })
}

const scrollTopChange = (top) => {}
</script>

<style scoped>
.main-container { min-height: 100vh; background: linear-gradient(180deg, #FF7A45 0%, #ffffff 32%, #ffffff 100%); }
.page { background: transparent; }
.navbar { position: relative; z-index: 20; display: flex; align-items: center; justify-content: space-between; padding: 0 24rpx; padding-top: calc(var(--status-bar-height, 0px) + 16rpx); background: transparent; }
.navbar-capsule { display: flex; align-items: center; border: 1rpx solid #dadbde; border-radius: 100rpx; padding: 8rpx 20rpx; backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); background: rgba(255,255,255,0.3); }
.navbar-btn { padding: 4rpx 8rpx; }
.navbar-divider { width: 1rpx; height: 32rpx; background: #dadbde; margin: 0 8rpx; }
.navbar-title { font-size: 34rpx; font-weight: 600; color: #333; }
.navbar-msg { width: 64rpx; height: 64rpx; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: rgba(255,255,255,0.3); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); }
.navbar-placeholder { width: 120rpx; }
.revenue-card { margin: 24rpx 24rpx 0; padding: 28rpx 24rpx; border-radius: 24rpx; background: linear-gradient(135deg, #8b5cf6, #6366f1, #3b82f6); display: flex; align-items: center; justify-content: space-around; box-shadow: 0 8rpx 24rpx rgba(99, 102, 241, 0.3); }
.revenue-item { flex: 1; display: flex; flex-direction: column; align-items: center; }
.revenue-label { font-size: 22rpx; color: rgba(255,255,255,0.8); margin-bottom: 10rpx; }
.revenue-value { font-size: 34rpx; font-weight: 700; color: #fff; }
.revenue-divider { width: 1rpx; height: 56rpx; background: rgba(255,255,255,0.3); }
.search-wrap { position: relative; z-index: 10; display: flex; align-items: center; padding: 12rpx 24rpx; margin: 24rpx 24rpx 0; background: rgba(255,255,255,0.45); backdrop-filter: blur(24rpx); -webkit-backdrop-filter: blur(24rpx); border: 1rpx solid rgba(255,255,255,0.6); box-shadow: 0 2rpx 20rpx rgba(0,0,0,0.04), inset 0 1rpx rgba(255,255,255,0.8); border-radius: 48rpx; }
.search-input { flex: 1; height: 48rpx; font-size: 26rpx; color: #000; background: transparent; margin-left: 12rpx; }
.search-placeholder { color: #999; }
.category-panel { position: relative; z-index: 10; margin: 24rpx 0 0; padding: 0 20rpx 18rpx; background: transparent; }
.game-category-scroll { width: 100%; white-space: nowrap; }
.game-category-list { display: inline-flex; align-items: flex-start; gap: 42rpx; padding: 0 0 18rpx; }
.game-category-item { position: relative; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; min-width: 112rpx; height: 52rpx; flex-shrink: 0; }
.game-category-name { font-size: 30rpx; line-height: 36rpx; color: #9aa2aa; font-weight: 400; white-space: nowrap; }
.game-category-item.active .game-category-name { color: #101010; font-weight: 700; }
.game-category-mark { position: absolute; left: 50%; bottom: 0; width: 38rpx; height: 16rpx; transform: translateX(-50%); opacity: 0; }
.game-category-mark::before { content: ''; position: absolute; left: 0; top: 0; width: 32rpx; height: 12rpx; border-bottom: 5rpx solid #FF7A45; border-radius: 0 0 50% 50%; transform: rotate(12deg); }
.game-category-mark::after { content: ''; position: absolute; right: 0; top: 4rpx; width: 10rpx; height: 10rpx; border: 4rpx solid #FFB38A; border-left-color: transparent; border-top-color: transparent; border-radius: 50%; transform: rotate(-18deg); }
.game-category-item.active .game-category-mark { opacity: 1; }
.zone-tabs-wrap { position: relative; z-index: 10; display: flex; align-items: center; gap: 16rpx; padding: 0 8rpx; }
.zone-tab-item { display: flex; align-items: center; justify-content: center; min-width: 82rpx; height: 46rpx; padding: 0 18rpx; border-radius: 6rpx; background: rgba(255, 255, 255, 0.28); box-sizing: border-box; transform: skewX(-12deg); }
.zone-tab-text { font-size: 24rpx; color: #9aa2aa; line-height: 46rpx; white-space: nowrap; transform: skewX(12deg); }
.zone-tab-item.active { background: linear-gradient(135deg, #FF7A45, #FFB38A, #FF7A45); }
.zone-tab-item.active .zone-tab-text { color: #ffffff; }
.content-inner { padding: 0 24rpx; }
.order-card { width: 100%; box-sizing: border-box; margin-bottom: 20rpx; padding: 20rpx; background: rgba(255,255,255,0.55); backdrop-filter: blur(24rpx); -webkit-backdrop-filter: blur(24rpx); border: 1rpx solid rgba(255,255,255,0.7); border-radius: 20rpx; box-shadow: 0 2rpx 20rpx rgba(0,0,0,0.04), inset 0 1rpx rgba(255,255,255,0.9); }
.card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12rpx; padding-bottom: 12rpx; border-bottom: 1rpx solid rgba(0,0,0,0.06); }
.card-server { font-size: 28rpx; font-weight: 600; color: #000; flex: 1; min-width: 0; }
.card-fee { font-size: 24rpx; color: #FF9D00; flex-shrink: 0; margin-left: 16rpx; }
.goods-row { display: flex; align-items: flex-start; gap: 20rpx; padding: 16rpx 0; border-bottom: 1rpx solid rgba(0,0,0,0.04); }
.goods-row:last-of-type { border-bottom: none; }
.goods-img { width: 120rpx; height: 120rpx; border-radius: 16rpx; flex-shrink: 0; background: rgba(0,0,0,0.04); }
.goods-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 8rpx; }
.goods-title { font-size: 28rpx; font-weight: 500; color: #333; }
.goods-desc { font-size: 24rpx; color: #999; }
.goods-price { font-size: 26rpx; color: #e25c3b; font-weight: 600; }
.goods-qty { font-size: 24rpx; color: #999; font-weight: 400; }
.detail-rows { padding: 8rpx 0; }
.detail-row { display: flex; justify-content: space-between; align-items: center; padding: 12rpx 0; }
.detail-label { font-size: 26rpx; color: #333; }
.detail-value { font-size: 26rpx; color: #666; }
.detail-value.price { color: #e25c3b; font-weight: 600; }
.detail-value.hidden-value { color: #ccc; font-style: italic; }
.card-footer { display: flex; justify-content: flex-end; padding-top: 16rpx; margin-top: 8rpx; border-top: 1rpx solid rgba(0,0,0,0.08); }
.btn-view { width: 146rpx; height: 64rpx; line-height: 64rpx; text-align: center; font-size: 26rpx; color: #fff; background: linear-gradient(135deg, #ffc300, #faad14); border-radius: 16rpx; white-space: nowrap; }
.bottom-spacer { height: 80rpx; }
</style>
