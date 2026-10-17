<template>
	<view class="clf-page">
		<commonTabbar :current="1" :placeholder="false"></commonTabbar>
		<view class="clf-box">
			<view class="clf-nav-bar">
				<text class="clf-nav-title">分类</text>
			</view>
			
			<!-- 一级分类 -->
			<view class="clf-category-section" v-if="categoryList.length">
				<scroll-view class="clf-category-scroll" scroll-x="true" :show-scrollbar="false">
					<view class="clf-category-grid">
						<view 
							v-for="(item, index) in categoryList" 
							:key="index" 
							:class="['clf-category-item', currentCategoryIndex === index ? 'clf-category-active' : '']"
							@click="switchMainCategory(index)">
							<view class="clf-category-image-wrap">
								<image class="clf-category-image" :src="item.image" mode="aspectFill"></image>
								<view class="clf-category-image-gloss"></view>
							</view>
							<view class="clf-category-name-wrap">
								<text class="clf-category-name">{{item.name}}</text>
							</view>
						</view>
					</view>
				</scroll-view>
			</view>
			
			<view class="clf-content">
				<view class="clf-sidebar">
					<view v-if="sidebarList.length === 0" class="skeleton-sidebar">
						<view v-for="i in 8" :key="i" class="skeleton-sidebar-item"></view>
					</view>
					<view 
						v-for="(cat, idx) in sidebarList" 
						:key="cat.id"
						:class="['clf-sidebar-item', activeCategory === idx ? 'clf-sidebar-active' : '']"
						@click="switchCategory(idx, cat)">
						<text class="clf-sidebar-text">{{ cat.name }}</text>
					</view>
				</view>
				
				<view class="clf-main">
					<view v-if="activeTab === 0">
						<view v-if="currentGoodsList.length === 0 && !sidebarList.length" class="skeleton-list">
							<view v-for="i in 5" :key="i" class="skeleton-card">
								<view class="skeleton-thumb"></view>
								<view class="skeleton-info">
									<view class="skeleton-title"></view>
									<view class="skeleton-sales"></view>
									<view class="skeleton-bottom">
										<view class="skeleton-price"></view>
										<view class="skeleton-btn"></view>
									</view>
								</view>
							</view>
						</view>
						<view class="clf-sort-bar">
							<view 
								v-for="item in sortOptions" 
								:key="item.key"
								:class="['clf-sort-item', sortType === item.key ? 'clf-sort-active' : '']"
								@click="setSortType(item.key)"
							>
								<text class="clf-sort-text">{{ item.label }}</text>
							</view>
						</view>
						<view class="clf-product-list">
							<view class="clf-product-card"
								v-for="(goods, idx) in currentGoodsList" :key="goods.id"
								@click="gotoDetail(goods)">
								<view class="clf-product-left">
									<image class="clf-product-thumb" :src="goods.logo" mode="aspectFill" lazy-load></image>
								</view>
								<view class="clf-product-right">
									<text class="clf-product-title">{{ goods.title }}</text>
									<text class="clf-product-sales">销量：{{ goods.sales || 0 }}</text>
									<view class="clf-product-bottom">
										<text class="clf-product-price">¥{{ goods.price || '0.00' }}</text>
										<button class="clf-product-btn" @click.stop="gotoDetail(goods)">立即下单</button>
									</view>
								</view>
							</view>
						</view>
					</view>
					
					<view v-if="activeTab === 1">
						<view v-if="goodsList.length === 0 && !goodsLoaded" class="skeleton-list">
							<view v-for="i in 5" :key="i" class="skeleton-card">
								<view class="skeleton-thumb"></view>
								<view class="skeleton-info">
									<view class="skeleton-title"></view>
									<view class="skeleton-sales"></view>
									<view class="skeleton-bottom">
										<view class="skeleton-price"></view>
										<view class="skeleton-btn"></view>
									</view>
								</view>
							</view>
						</view>
						<view class="rank-list">
							<view class="rank-item"
								v-for="(goods, idx) in goodsList" :key="goods.id"
								@click="gotoDetail(goods)">
								<view class="rank-num" :class="'rank-' + (idx + 1)">{{ idx + 1 }}</view>
								<image class="rank-thumb" :src="goods.logo" mode="aspectFill" lazy-load></image>
								<view class="rank-info">
									<text class="rank-title">{{ goods.title }}</text>
									<view class="rank-bottom">
										<text class="rank-sales">销量: {{ goods.sales || 0 }}</text>
										<text class="rank-price">¥{{ goods.price || '0.00' }}</text>
									</view>
								</view>
							</view>
						</view>
					</view>
				</view>
			</view>
		</view>
		<view style="height: 150rpx;"></view>
	</view>
</template>

<script setup>
	import { ref, onMounted, computed } from 'vue';
	import { category, goods as goodsApi } from '../../request/api';
	import router from '../../utils/router';

	const activeTab = ref(0)
	const categoryList = ref([])
	const sidebarList = ref([])
	const activeCategory = ref(0)
	const currentCategoryId = ref('')
	const currentCategoryIndex = ref(0)
	const goodsMap = ref({})
	const goodsList = ref([])
	const isLoading = ref(false)
	const goodsLoaded = ref(false)
	const sortType = ref('default') // default, price_asc, price_desc, sales

	onMounted(() => { 
		loadAllData()
	})

	const loadAllData = async () => {
		isLoading.value = true
		try {
			await Promise.all([
				loadCategory(),
				loadGoods()
			])
		} finally {
			isLoading.value = false
		}
	}

	const loadCategory = async () => {
		try {
			const res = await category()
			categoryList.value = res.data
			// 只显示当前选中的一级分类下的二级分类
			if (categoryList.value.length > 0) {
				const firstCategory = categoryList.value[0]
				if (firstCategory.children && firstCategory.children.length) {
					sidebarList.value = firstCategory.children
					currentCategoryId.value = firstCategory.children[0].id
					await loadCategoryGoods(firstCategory.children[0].id)
				}
			}
		} catch (e) {
			console.error('分类数据加载失败:', e)
		}
	}

	const loadCategoryGoods = async (catId, sort = '') => {
		try {
			const res = await goodsApi({
				page: 1,
				limit: 30,
				two_category: catId,
				sort
			})
			const key = catId + '_' + sort
			goodsMap.value[key] = res.data.data || []
		} catch (e) {
			const key = catId + '_' + sort
			goodsMap.value[key] = []
		}
	}

	const sortOptions = [
		{ key: 'default', label: '默认' },
		{ key: 'price_asc', label: '价格最低' },
		{ key: 'price_desc', label: '价格最高' },
		{ key: 'sales', label: '销量' }
	]

	// sortType → 后端 sort 参数映射
	const sortParams = {
		default: '',
		price_asc: '4',
		price_desc: '3',
		sales: '1'
	}

	const setSortType = async (key) => {
		sortType.value = key
		const sortVal = sortParams[key] || ''
		const catId = currentCategoryId.value
		const cacheKey = catId + '_' + sortVal
		if (!goodsMap.value[cacheKey]) {
			await loadCategoryGoods(catId, sortVal)
		}
	}

	const currentGoodsList = computed(() => {
		const sortVal = sortParams[sortType.value] || ''
		const cacheKey = currentCategoryId.value + '_' + sortVal
		return goodsMap.value[cacheKey] || []
	})

	const switchMainCategory = (index) => {
		currentCategoryIndex.value = index
		const category = categoryList.value[index]
		if (category && category.children && category.children.length) {
			sidebarList.value = category.children
			const firstChild = category.children[0]
			currentCategoryId.value = firstChild.id
			activeCategory.value = 0
			const sortVal = sortParams[sortType.value] || ''
			const cacheKey = firstChild.id + '_' + sortVal
			if (!goodsMap.value[cacheKey]) {
				loadCategoryGoods(firstChild.id, sortVal)
			}
		}
	}

	const switchCategory = (idx, cat) => {
		activeCategory.value = idx
		currentCategoryId.value = cat.id
		const sortVal = sortParams[sortType.value] || ''
		const cacheKey = cat.id + '_' + sortVal
		if (!goodsMap.value[cacheKey]) {
			loadCategoryGoods(cat.id, sortVal)
		}
	}

	const loadGoods = async () => {
		if (goodsLoaded.value) return
		try {
			const res = await goodsApi({ page: 1, limit: 20, sort: '1' })
			goodsList.value = res.data.data || []
			goodsLoaded.value = true
		} catch (err) {
			console.error('排行榜数据加载失败:', err)
		}
	}

	const switchTab = (idx) => {
		activeTab.value = idx
		if (idx === 1 && !goodsLoaded.value) {
			loadGoods()
		}
	}

	const gotoDetail = (item) => {
		router.navigateTo({
			url: 'pagesA/index/detail',
			data: { id: item.id }
		})
	}
</script>

<style lang="scss">
	.clf-page {
		min-height: 100vh;
		background: linear-gradient(180deg,
			#FF7A45 0%,
			#FF7A45 16%,
			#FF9D6E 33%,
			#FFB38A 50%,
			#FFD4B8 66%,
			#FFFFFF 83%,
			#FFFFFF 100%);
		overflow-x: hidden;
		touch-action: pan-y;
		width: 100%;
		box-sizing: border-box;
	}

	.clf-box {
		width: 100%;
		box-sizing: border-box;
		padding-bottom: 50rpx;
		padding-top: 0;
		position: relative;
		overflow-x: hidden; // 禁止横向滚动
	}

	.clf-nav-bar {
		height: 88rpx;
		padding: 30rpx 24rpx 16rpx;
		background: transparent;
		position: relative;
		z-index: 1;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.clf-nav-title {
		font-size: 36rpx;
		font-weight: 600;
		color: #ffffff;
		text-align: center;
	}

	.clf-tabs-wrap {
		display: flex;
		flex-direction: row;
		align-items: stretch;
		padding: 10rpx;
		margin: 0 24rpx;
		border-radius: 60rpx; // 胶囊式圆角
		background: rgba(255, 255, 255, 0.8); // 半透明白色
		backdrop-filter: blur(10rpx); // 玻璃质感模糊
		box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.1); // 轻微阴影
	}

	.clf-tab-item {
		flex: 1;
		display: flex;
		align-items: center;
		justify-content: center;
		padding: 20rpx 0;
		border-radius: 50rpx; // 胶囊式圆角
		background: transparent;
		margin: 0 4rpx;
	}

	.clf-tab-active {
		background: linear-gradient(135deg, #FFB38A 0%, #c183ff 100%) !important;
		box-shadow: 0 2rpx 10rpx rgba(142, 197, 252, 0.3); // 轻微阴影
	}

	.clf-tab-text {
		font-size: 34rpx; // 变大综合和排行榜文字
		color: #999;
		font-weight: 500;
	}

	.clf-tab-active .clf-tab-text {
		color: #fff !important;
		font-weight: 600;
	}

	.clf-content {
		display: flex;
		flex-direction: row;
		padding: 0;
		flex: 1;
		overflow-x: hidden;
	}
	
	.clf-sidebar {
		width: 156rpx;
		flex-shrink: 0;
		background: transparent;
		padding: 0 0 20rpx;
		overflow-y: auto;
		overflow-x: hidden;
	}
	
	.clf-sidebar-item {
		position: relative;
		padding: 14rpx 12rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		transition: all 0.3s ease;
		background: linear-gradient(135deg, #f8f9fc 0%, #edf0f5 100%);
		margin-bottom: 2rpx;
		border-radius: 14rpx;
		box-shadow:
			0 3rpx 10rpx rgba(0, 0, 0, 0.06),
			inset 0 2rpx 4rpx rgba(255, 255, 255, 0.7),
			inset 0 -1rpx 3rpx rgba(0, 0, 0, 0.05);
		overflow: visible;
	}

	.clf-sidebar-item::after {
		content: '';
		position: absolute;
		top: 4rpx;
		left: 6rpx;
		right: 6rpx;
		height: 35%;
		border-radius: 32rpx;
		background: linear-gradient(180deg,
			rgba(255, 255, 255, 0.45) 0%,
			rgba(255, 255, 255, 0.1) 60%,
			rgba(255, 255, 255, 0) 100%
		);
		pointer-events: none;
		z-index: 0;
	}

	.clf-sidebar-active {
		background: linear-gradient(135deg, #FF7A45, #FF9D6E, #FF7A45);
		padding: 14rpx 12rpx;
		box-shadow:
			0 4rpx 16rpx rgba(255, 122, 69, 0.3),
			0 0 0 1rpx rgba(255, 255, 255, 0.2) inset,
			inset 0 2rpx 6rpx rgba(255, 255, 255, 0.4),
			inset 0 -2rpx 6rpx rgba(0, 0, 0, 0.08);
	}

	.clf-sidebar-active::after {
		background: linear-gradient(180deg,
			rgba(255, 255, 255, 0.4) 0%,
			rgba(255, 255, 255, 0.1) 60%,
			rgba(255, 255, 255, 0) 100%
		);
	}

	.clf-sidebar-text {
		position: relative;
		z-index: 1;
		font-size: 25rpx;
		color: #666;
		font-weight: 500;
		text-align: center;
		line-height: 1;
	}

	.clf-sidebar-active .clf-sidebar-text {
		color: #ffffff;
		font-weight: 500;
	}
	
	.clf-main {
		flex: 1;
		padding: 0 24rpx 80rpx;
		overflow-y: auto;
		overflow-x: hidden;
		width: 100%;
		box-sizing: border-box;
	}

	.skeleton-sidebar {
		padding: 20rpx 0;
	}
	
	.skeleton-sidebar-item {
		height: 88rpx;
		background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
		background-size: 200% 100%;
		animation: skeleton-loading 1.5s ease-in-out infinite;
		margin-bottom: 4rpx;
		border-radius: 8rpx;
	}
	
	.skeleton-list {
		padding: 20rpx 0;
	}
	
	.skeleton-card {
		display: flex;
		padding: 20rpx 0;
		border-bottom: 1rpx solid #f0f0f0;
	}
	
	.skeleton-thumb {
		width: 160rpx;
		height: 160rpx;
		background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
		background-size: 200% 100%;
		animation: skeleton-loading 1.5s ease-in-out infinite;
		border-radius: 12rpx;
	}
	
	.skeleton-info {
		flex: 1;
		margin-left: 20rpx;
		display: flex;
		flex-direction: column;
		justify-content: space-between;
	}
	
	.skeleton-title {
		height: 40rpx;
		background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
		background-size: 200% 100%;
		animation: skeleton-loading 1.5s ease-in-out infinite;
		border-radius: 8rpx;
		width: 80%;
	}
	
	.skeleton-sales {
		height: 32rpx;
		background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
		background-size: 200% 100%;
		animation: skeleton-loading 1.5s ease-in-out infinite;
		border-radius: 8rpx;
		width: 60%;
	}
	
	.skeleton-bottom {
		display: flex;
		align-items: center;
		justify-content: space-between;
	}
	
	.skeleton-price {
		height: 40rpx;
		background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
		background-size: 200% 100%;
		animation: skeleton-loading 1.5s ease-in-out infinite;
		border-radius: 8rpx;
		width: 30%;
	}
	
	.skeleton-btn {
		height: 48rpx;
		background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
		background-size: 200% 100%;
		animation: skeleton-loading 1.5s ease-in-out infinite;
		border-radius: 24rpx;
		width: 140rpx;
	}
	
	@keyframes skeleton-loading {
		0% {
			background-position: 200% 0;
		}
		100% {
			background-position: -200% 0;
		}
	}
	
	.debug-info {
		padding: 10rpx 20rpx;
		background: rgba(0,0,0,0.1);
		color: #000;
		font-size: 24rpx;
		margin-bottom: 20rpx;
		border-radius: 8rpx;
	}
	
	.clf-product-list {
		padding-bottom: 24rpx;
		overflow-x: hidden; // 禁止横向滚动
		width: 100%;
		box-sizing: border-box;
	}
	
	.rank-list {
		padding-bottom: 24rpx;
		overflow-x: hidden; // 禁止横向滚动
		width: 100%;
		box-sizing: border-box;
	}
	
	.clf-product-card {
		position: relative;
		display: flex;
		flex-direction: row;
		align-items: center;
		gap: 16rpx;
		padding: 14rpx;
		margin-bottom: 16rpx;
		background: linear-gradient(160deg, 
			#fefeff 0%, 
			#faf5fc 25%,
			#f5f4ff 55%,
			#faf8fd 80%,
			#fefafe 100%
		);
		border-radius: 20rpx;
		border: 1.5rpx solid rgba(255,255,255,0.65);
		box-shadow:
			0 6rpx 20rpx rgba(160,140,200,0.10),
			0 2rpx 6rpx rgba(0,0,0,0.03),
			inset 0 1rpx 0 rgba(255,255,255,0.9),
			inset 0 2rpx 6rpx rgba(255,255,255,0.45);
		height: auto;
		box-sizing: border-box;
		width: 100%;
		overflow: hidden;
		transition: all 0.3s ease;
	}

	.clf-product-card::after {
		content: '';
		position: absolute;
		top: 4rpx;
		left: 50%;
		transform: translateX(-50%);
		width: 65%;
		height: 5rpx;
		border-radius: 5rpx;
		background: linear-gradient(90deg,
			transparent 0%,
			rgba(255,255,255,0.5) 35%,
			rgba(255,255,255,0.75) 50%,
			rgba(255,255,255,0.5) 65%,
			transparent 100%
		);
		pointer-events: none;
		z-index: 1;
	}

	.clf-product-card:active {
		transform: scale(0.98);
		box-shadow:
			0 3rpx 10rpx rgba(160,140,200,0.12),
			inset 0 2rpx 6rpx rgba(255, 255, 255, 0.7),
			inset 0 -2rpx 6rpx rgba(0, 0, 0, 0.06);
	}
	
	.clf-product-left {
		flex-shrink: 0;
	}
	
	.clf-product-thumb {
		width: 140rpx;
		height: 140rpx;
		border-radius: 16rpx;
		background: #f0f0f0;
		flex-shrink: 0;
		box-shadow: 
			inset 0 1rpx 0 rgba(255,255,255,0.6),
			0 2rpx 8rpx rgba(0,0,0,0.06);
	}
	
	.clf-product-right {
		flex: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;
		gap: 6rpx;
		height: 100%;
		justify-content: space-between;
	}
	
	.clf-product-title {
		font-size: 30rpx; // 放大标题字体
		font-weight: 400;
		color: #000;
		line-height: 1.4;
		overflow: hidden;
		text-overflow: ellipsis;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
		min-height: 84rpx; // 调整标题高度
	}
	
	.clf-product-sales {
		font-size: 26rpx; // 放大销量字体
		color: #999;
		flex-shrink: 0;
	}
	
	.clf-product-bottom {
		display: flex;
		flex-direction: row;
		align-items: center;
		justify-content: space-between;
		flex-shrink: 0;
	}
	
	.clf-product-price {
		font-size: 24rpx; // 价格保持小尺寸
		font-weight: 800;
		color: #ff4757;
		flex-shrink: 0;
		margin-right: 8rpx; // 调整间距
	}
	
	.clf-product-btn {
		position: relative;
		width: 120rpx;
		height: 50rpx;
		line-height: 50rpx;
		padding: 0;
		margin: 0;
		font-size: 22rpx;
		font-weight: 600;
		border: none;
		border-radius: 27rpx;
		text-align: center;
		background: linear-gradient(160deg, 
			rgba(255,122,69,0.85) 0%, 
			rgba(65,105,225,0.80) 40%,
			rgba(255,122,69,0.82) 100%
		);
		color: rgba(255,255,255,0.95);
		flex-shrink: 0;
		display: flex;
		align-items: center;
		justify-content: center;
		border: 2rpx solid rgba(255,255,255,0.5);
		box-shadow:
			0 6rpx 18rpx rgba(255,122,69,0.22),
			0 2rpx 6rpx rgba(0,0,0,0.04),
			inset 0 1rpx 0 rgba(255,255,255,0.55),
			inset 0 2rpx 8rpx rgba(255,255,255,0.25);
		transition: all 0.25s ease;
		overflow: hidden;
	}

	/* 果冻圆顶高光 */
	.clf-product-btn::after {
		content: '';
		position: absolute;
		top: 4rpx;
		left: 25%;
		width: 50%;
		height: 14rpx;
		border-radius: 50%;
		background: radial-gradient(ellipse,
			rgba(255,255,255,0.55) 0%,
			rgba(255,255,255,0.15) 60%,
			rgba(255,255,255,0) 100%
		);
		pointer-events: none;
	}

	.clf-product-btn:active {
		transform: scale(0.95);
		box-shadow:
			0 2rpx 8rpx rgba(255,122,69,0.18),
			inset 0 1rpx 0 rgba(255,255,255,0.3),
			inset 0 -2rpx 6rpx rgba(0,0,0,0.1);
	}
	
	.rank-item {
		position: relative;
		display: flex;
		flex-direction: row;
		align-items: center;
		gap: 20rpx;
		padding: 24rpx;
		margin-bottom: 24rpx;
		background: linear-gradient(135deg, #ffffff 0%, #f8faff 100%);
		border-radius: 16rpx;
		box-shadow:
			0 4rpx 16rpx rgba(0, 0, 0, 0.08),
			0 1rpx 4rpx rgba(0, 0, 0, 0.04),
			inset 0 2rpx 4rpx rgba(255, 255, 255, 0.8),
			inset 0 -1rpx 3rpx rgba(0, 0, 0, 0.05);
		overflow: hidden;
		transition: all 0.3s ease;
	}

	.rank-item::after {
		content: '';
		position: absolute;
		top: 4rpx;
		left: 8rpx;
		right: 8rpx;
		height: 30%;
		border-radius: 16rpx;
		background: linear-gradient(180deg,
			rgba(255, 255, 255, 0.5) 0%,
			rgba(255, 255, 255, 0.1) 60%,
			rgba(255, 255, 255, 0) 100%
		);
		pointer-events: none;
		z-index: 0;
	}

	.rank-item:active {
		transform: scale(0.98);
	}
	
	.rank-num {
		width: 56rpx;
		height: 56rpx;
		border-radius: 12rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 28rpx;
		font-weight: 800;
		color: #fff;
		background: #ddd;
		flex-shrink: 0;
	}
	
	.rank-1 {
		background: linear-gradient(135deg, #ffd700 0%, #ffaa00 100%);
	}
	
	.rank-2 {
		background: linear-gradient(135deg, #c0c0c0 0%, #a0a0a0 100%);
	}
	
	.rank-3 {
		background: linear-gradient(135deg, #cd7f32 0%, #a0522d 100%);
	}
	
	.rank-thumb {
		width: 120rpx;
		height: 120rpx;
		border-radius: 12rpx;
		background: #f0f0f0;
		flex-shrink: 0;
	}
	
	.rank-info {
		flex: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;
		gap: 12rpx;
	}
	
	.rank-title {
		font-size: 30rpx;
		font-weight: 700;
		color: #000;
		line-height: 1.4;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	
	.rank-bottom {
		display: flex;
		flex-direction: row;
		align-items: center;
		justify-content: space-between;
	}
	
	.rank-sales {
		font-size: 24rpx;
		color: #999;
	}
	
	.rank-price {
		font-size: 36rpx;
		font-weight: 800;
		color: #ff4757;
	}
	
	/* 一级分类样式 */
	.clf-category-section {
		width: 100%;
		padding: 8rpx 0;
	}

	.clf-category-scroll {
		width: 100%;
		white-space: nowrap;
	}

	.clf-category-grid {
		display: flex;
		flex-wrap: nowrap;
		align-items: center;
		justify-content: space-between;
		padding: 0 30rpx;
		gap: 0;
		min-width: 100%;
		box-sizing: border-box;
	}

	.clf-category-item {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;
		gap: 10rpx;
		padding: 6rpx 0 8rpx;
	}

	.clf-category-image-wrap {
		position: relative;
		width: 88rpx;
		height: 88rpx;
		border-radius: 50%;
		overflow: hidden;
		background: linear-gradient(145deg, rgba(255, 255, 255, 0.92), rgba(215, 233, 255, 0.88));
		box-shadow:
			0 8rpx 18rpx rgba(72, 129, 255, 0.16),
			inset 0 4rpx 10rpx rgba(255, 255, 255, 0.92),
			inset 0 -8rpx 14rpx rgba(101, 149, 255, 0.14);
		border: 2rpx solid rgba(255, 255, 255, 0.9);
	}


	.clf-category-image {
		width: 100%;
		height: 100%;
		border-radius: 50%;
		display: block;
	}

	.clf-category-image-gloss {
		position: absolute;
		top: 7rpx;
		left: 12rpx;
		width: 46rpx;
		height: 24rpx;
		border-radius: 50rpx;
		background: linear-gradient(180deg, rgba(255, 255, 255, 0.78), rgba(255, 255, 255, 0.08));
		filter: blur(1rpx);
		transform: rotate(-18deg);
		pointer-events: none;
	}

	.clf-category-name-wrap {
		position: relative;
		display: flex;
		align-items: center;
		justify-content: center;
		padding: 6rpx 14rpx;
		border-radius: 22rpx;
		background: linear-gradient(135deg, #f8f9fc 0%, #edf0f5 100%);
		box-shadow:
			0 2rpx 8rpx rgba(0, 0, 0, 0.06),
			inset 0 2rpx 4rpx rgba(255, 255, 255, 0.8),
			inset 0 -1rpx 3rpx rgba(0, 0, 0, 0.05);
		transition: all 0.3s ease;
		overflow: visible;
	}


	.clf-category-name-wrap::after {
		content: '';
		position: absolute;
		top: 2rpx;
		left: 6rpx;
		right: 6rpx;
		height: 32%;
		border-radius: 22rpx;
		background: linear-gradient(180deg,
			rgba(255, 255, 255, 0.5) 0%,
			rgba(255, 255, 255, 0.1) 60%,
			rgba(255, 255, 255, 0) 100%
		);
		pointer-events: none;
		z-index: 0;
	}

	.clf-category-name {
		font-size: 22rpx;
		color: #666;
		text-align: center;
		white-space: nowrap;
		position: relative;
		z-index: 1;
	}

	.clf-category-active .clf-category-name-wrap {
		background: linear-gradient(135deg, #FF7A45, #FF9D6E, #FF7A45);
		box-shadow:
			0 3rpx 12rpx rgba(255, 122, 69, 0.26),
			0 0 0 1rpx rgba(255, 255, 255, 0.2) inset,
			inset 0 2rpx 5rpx rgba(255, 255, 255, 0.35),
			inset 0 -2rpx 5rpx rgba(0, 0, 0, 0.08);
	}

	.clf-category-active .clf-category-name-wrap::after {
		background: linear-gradient(180deg,
			rgba(255, 255, 255, 0.4) 0%,
			rgba(255, 255, 255, 0.1) 60%,
			rgba(255, 255, 255, 0) 100%
		);
	}

	.clf-category-active .clf-category-name {
		color: #ffffff;
		font-weight: 500;
	}

	.clf-sort-bar {
		display: flex;
		align-items: center;
		padding: 16rpx 0 12rpx;
	}

	.clf-sort-item {
		position: relative;
		flex-shrink: 0;
		padding: 6rpx 16rpx;
		border-radius: 20rpx;
		background: #f5f5f5;
		margin-right: 12rpx;
		border: 1.5rpx solid rgba(255,255,255,0.5);
		box-shadow: 
			0 3rpx 8rpx rgba(0,0,0,0.06),
			inset 0 2rpx 4rpx rgba(255,255,255,0.8),
			inset 0 -1rpx 3rpx rgba(0,0,0,0.04);
		transition: all 0.2s ease;
		overflow: hidden;
	}

	/* 果冻高光 */
	.clf-sort-item::after {
		content: '';
		position: absolute;
		top: 2rpx;
		left: 3rpx;
		right: 3rpx;
		height: 40%;
		border-radius: 24rpx 24rpx 10rpx 10rpx;
		background: linear-gradient(180deg,
			rgba(255,255,255,0.5) 0%,
			rgba(255,255,255,0.08) 70%,
			rgba(255,255,255,0) 100%
		);
		pointer-events: none;
		z-index: 0;
	}

	.clf-sort-text {
		position: relative;
		z-index: 1;
		font-size: 22rpx;
		color: #888;
		white-space: nowrap;
	}

	.clf-sort-active {
		background: linear-gradient(135deg, #FF7A45, #FF9D6E);
		border-color: rgba(255,122,69,0.3);
		box-shadow: 
			0 4rpx 16rpx rgba(255,122,69,0.25),
			inset 0 2rpx 5rpx rgba(255,255,255,0.45),
			inset 0 -1rpx 4rpx rgba(0,0,0,0.08);
	}

	.clf-sort-active .clf-sort-text {
		color: #fff;
		font-weight: 500;
	}

</style>
