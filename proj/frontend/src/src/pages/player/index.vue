<template>
	<view class="page-container">
		<!-- 底部导航栏，当前选中第3个（打手专区） -->
		<commonTabbar :current="2" :placeholder="false"></commonTabbar>
		<!-- z-paging 滚动列表容器 -->
		<z-paging ref="paging" v-model="dataList" :default-page-size="8" @query="queryList" auto-show-back-to-top
			@scrollTopChange="scrollTopChange" :use-page-scroll="false" :refresher-enabled="true">
			<!-- 顶部头部：搜索框置于最上方 -->
			<view class="top-header">
				<view class="search-glass">
					<u-search placeholder="搜索打手昵称" v-model="keyword" :showAction="false"
						@search="search"></u-search>
				</view>
				<!-- 标题 -->
				<view class="nav-header">
					<view class="nav-bar"></view>
					<view class="navleft">打手专区</view>
				</view>
			</view>

			<!-- 内容区域 -->
			<view class="content-area px-3">
					<!-- 一级分类 -->
					<view class="category-section" v-if="categoryList.length">
						<view class="category-grid">
							<view 
								v-for="(item, index) in categoryList" 
								:key="index" 
								:class="['category-item', categoryCurrent === index ? 'category-item-active' : '']"
								@click="changeCategory(index)"
							>
								<view :class="['category-image-wrap', categoryCurrent === index ? 'category-image-wrap-active' : '']">
									<image class="category-image" :src="item.image" mode="aspectFill"></image>
								</view>
								<view class="category-name-wrap">
									<text class="category-name">{{item.name}}</text>
									<view class="category-tag" :class="{'tag-visible': categoryCurrent === index}"></view>
								</view>
							</view>
						</view>
					</view>
					<!-- 分类标签横向滚动列表 -->
					<view class="tabs-container">
						<view class="tabs-inner">
							<!-- 遍历标签列表 -->
							<view class="tab-item position-relative" v-for="(item, index) in tabs" :key="index">
								<!-- 标签文本，选中状态高亮 -->
								<view :class="current==index?'sel':'nor'" style="z-index: 1;" @click="changeTag(item,index)">{{item.name}}</view>
								<!-- 选中标签的下划线 -->
								<view v-if="current==index" class="position-absolute line"></view>
							</view>
						</view>
					</view>

					<!-- 打手卡片列表 -->
					<view v-if="dataList.length" v-for="(item,index) in dataList" :key="index"
						class="flex align-center player-card"
						@click="goToPage(item)">
						
						<!-- 左侧电竞渐变条 -->
						<view class="card-accent"></view>

						<!-- 打手头像 -->
						<view class="avatar-frame-wrapper ml-3 mr-4">
							<view class="avatar-frame-inner">
								<u-image :src="item.avatar" mode="aspectFill" width="120rpx" height="120rpx" radius="60rpx"
									:lazy-load="true"
									:error-src="'/static/icon/default_avatar.png'"
									:loading-src="'/static/icon/default_avatar.png'"
									class="avatar-main"></u-image>
								<!-- 头像框 -->
								<image v-if="item.avatar_frame && item.avatar_frame.image" 
									:src="item.avatar_frame.image" 
									mode="aspectFill"
									class="avatar-frame-img"></image>
							</view>
						</view>
						<!-- 卡片右侧内容 -->
						<view class="flex-1 py-1 px-2">
							<!-- 第一行：昵称+性别+可接单状态 -->
							<view class="flex align-center">
								<!-- 昵称+性别 -->
							<view style="display: flex; align-items: center; line-height: normal;">
							    <view class="player-name">{{item.name}}</view>
							    <!-- 性别图标 -->
							    <image 
							        :src="getSex(item)" 
							        class="sex-icon"
							    ></image>
							</view>
								<!-- 可接单状态 -->
								<view class="ml-auto flex align-center">
									<view :class="item.jd_status==0?'point1':'point'"></view>
									<view class="font-24 ml-1" :style="getJDstatus(item).color">{{getJDstatus(item).title}}</view>
								</view>
							</view>
							<!-- 第二行：打手等级标签+VIP图标+语音播放器 -->
							<view class="flex align-center flex-wrap justify-between">
								<view class="flex align-center">
									<view class="tag" :style="getplayerLevl(item).color">
										{{getplayerLevl(item).title}}
									</view>
									<image class="vip-icon" :src="getVipIcon(item)" mode="aspectFit"></image>
								</view>
								<!-- 语音播放器 -->
								<view v-if="item.audio" class="voice-player" :class="{'playing': playingIndex === index}" @click="toggleAudio(index, $event)">
									<view class="voice-btn-wrapper">
										<view class="voice-btn-inner">
											<up-icon :name="playingIndex === index ? 'pause-circle-fill' : 'play-circle-fill'" size="22rpx" color="#FF7A45"></up-icon>
										</view>
									</view>
									<view class="voice-bars">
										<view class="voice-bar-item" :class="{'active': playingIndex === index}" :style="{animationDelay: (i * 0.12) + 's'}" v-for="i in 5" :key="i"></view>
									</view>
								</view>
							</view>
							<!-- 第三行：打手简介 -->
							<view>
								<up-text :text="item.synopsis" color="#7a7a86" lines="1" size="24rpx"></up-text>
							</view>
							<!-- 第四行：评分+在线状态 -->
							<view class="mt-1 flex align-center justify-between">
								<!-- 评分 -->
								<view class="score-align-container">
									<image 
										src="/static/images/Star.png" 
										class="star-icon-fixed"
										mode="aspectFit"
									></image>
									<text class="score-text-fixed">{{item.grade || 0}}分</text>
								</view>
								<!-- 在线状态 -->
								<view class="flex align-center">
									<up-icon name="clock" :color="item.jd_status==0?'#BBBBBB':'#1BD36C'"></up-icon>
									<view class="font-24 ml-1" :style="getJDstatus(item).color">{{item.jd_status==0?'离线':'在线'}}</view>
								</view>
							</view>
						</view>
					</view>
					<!-- 自定义空状态（无数据时显示） -->
					<view v-if="!dataList.length" class="empty-container">
						<view class="empty-text">暂无合适的打手</view>
					</view>
					<!-- 底部安全区域占位 -->
					<view class="listpage"></view>
			</view>

			<!-- 底部tabbar占位，防止内容被遮挡 -->
			<view style="height: 150rpx;"></view>
			
			<!-- 隐藏 z-paging 自带的空状态 -->
			<template #empty>
				<view></view>
			</template>
		</z-paging>
	</view>
</template>



<script setup>
	// 导入Vue 3的组合式API
	import {
		ref,
		onMounted,
		onUnmounted
	} from 'vue';
	// 导入路由工具
	import router from '../../utils/router';
	// 导入打手列表API
	import {
		thug,
		category
	} from '../../request/api';
	
	// z-paging组件的引用
	const paging = ref(null)
	// 打手列表数据
	const dataList = ref([])
	// 搜索关键词
	const keyword = ref('')
	// 当前选中的标签索引
	const current = ref(0)
	// 导航栏背景色
	const bgColor = ref('transparent')
	// 一级分类数据
	const categoryList = ref([]);
	// 当前选中的一级分类索引
	const categoryCurrent = ref(0)
	// 当前选中的分类ID
	const currentCategoryId = ref(0)
	// 当前选中的打手类型
	const type = ref('5')
	// 加载中状态
	const isloading = ref(false)
	// 页面加载中状态
	const pageloading = ref(true)
	// 分页数据
	const pagedata = ref({
		page: 1,
		limit: 10,
		total: 0
	})
	// 分类标签数据
	const tabs = [{
		name: '推荐',
		type: '5'
	}, {
		name: '男生',
		type: '1'
	}, {
		name: '女生',
		type: '0'
	}]

	// 获取打手等级配置
	const getplayerLevl = (item) => {
		// 数据校验
		if (!item || item.type == null || item.type == undefined) {
			return {
				color: 'background:#999999',
				title: '普通打手'
			}
		}
		// 钻石打手
		if (item.type == 1) {
			return {
				color: 'background:linear-gradient(270deg,#FFB38A 0%,#FF7A45 100%)',
				title: '钻石打手'
			}
		}

		// 大师打手
		if (item.type == 2) {
			return {
				color: 'background:linear-gradient(270deg,#FFD4B8 0%,#FF9D6E 100%)',
				title: '大师打手'
			}
		}
		// 顶尖打手
		if (item.type == 3) {
			return {
				color: 'background:linear-gradient(270deg,#FFCC5B 0%,#FF8A40 100%)',
				title: '顶尖打手'
			}
		}
		// 普通打手
		return {
			color: 'background:#999999',
			title: '普通打手'
		}
	}

	// 获取VIP图标
	const getVipIcon = (item) => {
		let levelNum = 1
		let levelName = ''
		if (item.user_current_level_info && item.user_current_level_info.name) {
			levelName = item.user_current_level_info.name
		} else if (item.level_name) {
			levelName = item.level_name
		} else if (item.level) {
			levelName = String(item.level)
		}
		const match = levelName.match(/\d+/)
		levelNum = match ? match[0] : 1
		return `/static/ym/v${levelNum}.png`
	}

	// 获取接单状态配置
	const getJDstatus = (item) => {
		// 数据校验
		if (!item || item.jd_status == null || item.jd_status == undefined) {
			return {
				color: 'color:#999999',
				title: '状态未知'
			}
		}
		// 不可接单状态
		if (item.jd_status == 0) {
			return {
				color: 'color:#999999',
				title: '不可接单'
			}
		} else {
			// 可接单状态
			return {
				color: 'color:#1BD36C',
				title: '可接单'
			}
		}
	}
	
	// 获取性别图标
	const getSex = (item) => {
		// 数据校验
		if (!item || item.sex == null || item.sex == undefined) {
			return '/static/icon/default_avatar.png'
		}
		// 0表示女，其他表示男
		return item.sex == '0' ? '/static/images/woman.png' : '/static/images/man.png'
	}

	// 页面挂载时初始化
	onMounted(() => {
		reset()
		loadCategory()
		queryList()
		// 初始化音频上下文
		audioContext.value = uni.createInnerAudioContext()
	})

	// 加载一级分类
	const loadCategory = () => {
		category().then(res => {
			if (res.code == 1 && res.data) {
				categoryList.value = res.data
			}
		}).catch(() => {})
	}

	// 切换一级分类
	const changeCategory = (index) => {
		categoryCurrent.value = index
		currentCategoryId.value = categoryList.value[index]?.id || 0
		// 重新加载列表
		paging.value.reload()
	}
	
	// 重置分页数据
	const reset = () => {
		pagedata.value = {
			page: 1,
			limit: 10,
			total: 0
		}
		pageloading.value = true
		isloading.value = false
	}
	
	// 查询打手列表
	const queryList = (pageNo, pageSize) => {
		isloading.value = true
		// 调用API
		thug({
			type: type.value,
			name: keyword.value,
			category_id: currentCategoryId.value,
			page: pageNo,
			limit: pageSize
		}).then(res => {
			isloading.value = false
			// 接口调用成功
			if (res.code == 1) {
				// 通知z-paging数据加载完成
				paging.value.complete(res.data.data);
				pagedata.value.total = res.data.data.total
			} else {
				// 接口返回失败
				paging.value.complete([]);
				uni.showToast({
					title: res.msg || '获取数据失败',
					icon: 'none'
				})
			}
		}).catch(err => {
			// 网络请求失败
			isloading.value = false
			paging.value.complete([]);
			uni.showToast({
				title: '网络错误，请重试',
				icon: 'none'
			})
		})
	}

	// 切换分类标签
	const changeTag = (item, index) => {
		current.value = index
		type.value = tabs[current.value].type
		// 重新加载列表
		paging.value.reload()
	}
	
	// 滚动位置变化，控制导航栏背景
	const scrollTopChange = (e) => {
		// 滚动超过100rpx，导航栏变成白色
		if (e > 100) {
			bgColor.value = '#fff'
		} else {
			// 否则变成透明
			bgColor.value = 'transparent'
		}
	}
	
	// 跳转到打手详情页
	const goToPage = (item) => {
		if (!item || !item.id) {
			uni.showToast({
				title: '参数错误',
				icon: 'none'
			})
			return
		}
		router.navigateTo({
			url: 'pagesA/player/detail',
			data: {
				id: item.id
			}
		})
	}
	
	// 搜索打手
	const search = () => {
		paging.value.reload()
	}
	
	// 语音播放相关
	const audioContext = ref(null)
	const playingIndex = ref(-1)
	
	// 切换语音播放
	const toggleAudio = (index, event) => {
		// 阻止事件冒泡，避免跳转详情页
		event.stopPropagation()
		
		const item = dataList.value[index]
		if (!item || !item.audio) {
			return
		}
		
		// 如果点击的是当前正在播放的卡片，就暂停
		if (playingIndex.value === index) {
			if (audioContext.value) {
				audioContext.value.pause()
			}
			playingIndex.value = -1
			return
		}
		
		// 播放新的语音
		if (audioContext.value) {
			// 先停止之前的播放
			audioContext.value.stop()
			// 移除之前的监听（避免重复）
			audioContext.value.offEnded()
			
			audioContext.value.src = item.audio
			audioContext.value.play()
			playingIndex.value = index
			
			// 监听播放结束
			audioContext.value.onEnded(() => {
				playingIndex.value = -1
			})
		}
	}
	
	// 页面卸载时清理音频
	onUnmounted(() => {
		if (audioContext.value) {
			audioContext.value.stop()
			audioContext.value.destroy()
		}
	})
</script>

<style lang="scss">
	/* ================= 电竞风配色：主色 紫 + 橙 ================= */
	$purple: #6C4CE6;
	$purple-deep: #4B2FB0;
	$purple-soft: #8B6BFF;
	$orange: #FF7A45;
	$orange-soft: #FFA24D;

	// 页面背景
	page {
		background: #ffffff;
	}

	.page-container {
		min-height: 100vh;
		background: #ffffff;
	}

	// 顶部头部容器
	.top-header {
		padding: 24rpx 30rpx 10rpx;
	}

	// 搜索框（置于最上方）
	.search-glass {
		background: #f5f5f7;
		border-radius: 44rpx;
		padding: 8rpx 22rpx;
		width: 100%;
		box-sizing: border-box;
	}

	// 搜索框内部所有背景透明
	.search-glass ::v-deep * {
		background: transparent !important;
		background-color: transparent !important;
	}

	.search-glass ::v-deep .u-search {
		width: 100% !important;
	}

	// 标题行
	.nav-header {
		display: flex;
		align-items: center;
		margin-top: 22rpx;
		padding-left: 4rpx;
	}

	.nav-bar {
		width: 10rpx;
		height: 36rpx;
		border-radius: 6rpx;
		background: linear-gradient(180deg, $orange 0%, $orange-soft 100%);
		margin-right: 14rpx;
		box-shadow: 0 2rpx 8rpx rgba(255,122,69,0.5);
	}

	// 导航栏标题样式
	.navleft {
		font-weight: 800;
		font-size: 40rpx;
		color: #1a1a1a;
		letter-spacing: 2rpx;
	}

	// 标签容器居中
	.tabs-container {
		display: flex;
		justify-content: center;
		align-items: center;
		width: 100%;
		margin-top: 8rpx;
	}

	.tabs-inner {
		display: flex;
		align-items: center;
		justify-content: space-between;
		width: 100%;
		padding: 0 40rpx;
		box-sizing: border-box;
	}

	.tab-item {
		flex-shrink: 0;
	}

	// 选中标签的下划线样式
	.line {
		width: 56rpx;
		height: 10rpx;
		background: linear-gradient(90deg, $purple 0%, $orange 100%);
		border-radius: 10rpx;
		z-index: -1;
		bottom: 0rpx;
		left: 0;
	}

	// 选中标签的文字样式
	.sel {
		font-weight: 800;
		font-size: 38rpx;
		color: $purple-deep;
	}

	// 未选中标签的文字样式
	.nor {
		font-weight: 500;
		font-size: 36rpx;
		color: #A6A6B4;
	}

	// 可接单状态的圆点样式
	.point {
		width: 8rpx;
		height: 8rpx;
		background: #1BD36C;
		border-radius: 4rpx;
		box-shadow: 0 0 8rpx rgba(27,211,108,0.8);
	}

	// 不可接单状态的圆点样式
	.point1 {
		width: 8rpx;
		height: 8rpx;
		background: #BBBBBB;
		border-radius: 4rpx;
	}

	// 备用的状态点样式
	.enpoint {
		width: 8rpx;
		height: 8rpx;
		background: #BBBBBB;
		border-radius: 4rpx;
	}

	// 打手昵称
	.player-name {
		color: #1B1330;
		font-size: 26rpx;
		font-weight: 700;
		margin-right: 8rpx;
		transform: translateY(2rpx);
	}

	// 性别图标
	.sex-icon {
		width: 25rpx;
		height: 25rpx;
		transform: translateY(2rpx);
	}

	// 头像框容器样式
	.avatar-frame-container {
		position: relative;
		width: 130rpx;
		height: 130rpx;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	// 头像框样式
	.avatar-frame {
		position: absolute;
		top: 0;
		left: 0;
		width: 130rpx;
		height: 130rpx;
		z-index: 2;
		pointer-events: none;
	}

	// 带头像框的头像样式
	.avatar-with-frame {
		position: relative;
		z-index: 1;
		margin: 5rpx;
	}

	// 头像框容器样式（用于打手卡片）
	.avatar-frame-wrapper {
		position: relative;
		width: 120rpx;
		height: 120rpx;
		flex-shrink: 0;
	}
	
	// 头像框内部容器
	.avatar-frame-inner {
		position: relative;
		width: 120rpx;
		height: 120rpx;
	}
	
	// 头像样式（用于打手卡片）
	.avatar-frame-wrapper .avatar-main {
		position: absolute;
		top: 0;
		left: 0;
		width: 120rpx;
		height: 120rpx;
		z-index: 1;
	}
	
	// 头像框样式（用于打手卡片）- 比头像大10rpx
	.avatar-frame-wrapper .avatar-frame-img {
		position: absolute;
		top: -5rpx;
		left: -5rpx;
		width: 130rpx;
		height: 130rpx;
		z-index: 2;
		pointer-events: none;
	}

	// 底部安全区域占位样式
	.listpage {
		height: calc(125rpx + constant(safe-area-inset-bottom));
		height: calc(125rpx + env(safe-area-inset-bottom));
		width: 100%;
	}

	// 空状态容器样式
	.empty-container {
		display: flex;
		justify-content: center;
		align-items: center;
		padding-top: 0rpx;
		padding-bottom: 200rpx;
	}

	// 空状态文字样式
	.empty-text {
		color: #bbb;
		font-size: 36rpx;
	}

	// 打手卡片样式 —— 清爽圆角 + 轻微阴影，电竞质感
	.player-card {
		background: #ffffff;
		position: relative;
		border-radius: 26rpx;
		margin-top: 22rpx;
		padding: 22rpx 18rpx 22rpx 26rpx;
		overflow: hidden;
		box-shadow: 0 10rpx 26rpx rgba(75,47,176,0.10), 0 2rpx 6rpx rgba(0,0,0,0.03);
		transition: transform 0.2s ease, box-shadow 0.2s ease;
	}

	// 左侧电竞渐变条
	.card-accent {
		position: absolute;
		left: 0;
		top: 0;
		bottom: 0;
		width: 10rpx;
		border-radius: 26rpx 0 0 26rpx;
		background: linear-gradient(180deg, $purple 0%, $orange 100%);
	}

	.player-card:active {
		transform: scale(0.985);
		box-shadow: 0 4rpx 14rpx rgba(75,47,176,0.16);
	}

	// 打手等级标签样式
	.tag {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		padding: 6rpx 20rpx;
		border-radius: 20rpx;
		color: #fff;
		font-size: 20rpx;
		white-space: nowrap;
		line-height: 1;
		height: auto;
		margin-right: 12rpx;
	}

	// VIP图标样式
	.vip-icon {
		width: 85rpx;
		height: 85rpx;
		flex-shrink: 0;
	}

	// 一级分类
	.category-section {
		width: 100%;
		padding: 20rpx;
		box-sizing: border-box;
	}

	.category-grid {
		display: flex;
		flex-wrap: wrap;
		gap: 18rpx;
	}

	.category-item {
		display: flex;
		flex-direction: column;
		align-items: center;
		width: calc((100% - 54rpx) / 4);
		flex-shrink: 0;
	}

	.category-image-wrap {
		width: 112rpx;
		height: 112rpx;
		border-radius: 50%;
		overflow: hidden;
		background: #EFEBFD;
		display: flex;
		align-items: center;
		justify-content: center;
		border: 4rpx solid transparent;
		box-sizing: border-box;
	}

	.category-image-wrap .category-image {
		width: 85rpx;
		height: 85rpx;
		border-radius: 50%;
		border: 4rpx solid #ffffff;
		box-sizing: border-box;
	}

	.category-image-wrap-active {
		width: 112rpx;
		height: 112rpx;
		border-radius: 50%;
		overflow: hidden;
		display: flex;
		align-items: center;
		justify-content: center;
		border: 4rpx solid transparent;
		background: linear-gradient(#fff, #fff) padding-box,
					linear-gradient(135deg, $purple, $orange, $purple) border-box;
		box-shadow: 0 0 0 6rpx rgba(108,76,230,0.14);
		box-sizing: border-box;
	}

	.category-image-wrap-active .category-image {
		width: 85rpx;
		height: 85rpx;
		border-radius: 50%;
		border: 4rpx solid #ffffff;
		box-sizing: border-box;
	}

	.category-image {
		width: 100%;
		height: 100%;
	}

	.category-name-wrap {
		display: flex;
		flex-direction: column;
		align-items: center;
		width: 100%;
		margin-top: 12rpx;
		min-height: 60rpx;
		height: 60rpx;
		justify-content: center;
	}

	.category-name {
		font-size: 22rpx;
		color: #5B5B6B;
		text-align: center;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		padding: 6rpx 20rpx;
		line-height: 32rpx;
	}

	.category-item-active .category-name {
		background: linear-gradient(135deg, $purple, $orange, $purple);
		padding: 6rpx 20rpx;
		border-radius: 24rpx;
		color: #ffffff;
		font-weight: 600;
	}

	.category-tag {
		width: 8rpx;
		height: 8rpx;
		border-radius: 50%;
		background: $orange;
		margin-top: 4rpx;
		flex-shrink: 0;
		opacity: 0;
		visibility: hidden;
		transition: opacity 0.1s, visibility 0.1s;
		min-height: 12rpx;
	}

	.category-tag.tag-visible {
		opacity: 1;
		visibility: visible;
	}

	/* 电竞风语音播放器 */
	.voice-player {
		position: relative;
		display: flex;
		align-items: center;
		justify-content: flex-start;
		flex-shrink: 0;
		padding: 8rpx 14rpx;
		border-radius: 40rpx;
		background: linear-gradient(135deg, $purple 0%, $orange 100%);
		box-shadow: 0 4rpx 16rpx rgba(108,76,230,0.42),
					0 0 0 1rpx rgba(255, 255, 255, 0.2) inset;
		transition: all 0.3s ease;
		overflow: visible;
		min-width: 100rpx;
	}

	.voice-player.playing {
		box-shadow: 0 4rpx 24rpx rgba(108,76,230,0.65),
					0 0 30rpx rgba(255,122,69,0.4),
					0 0 0 1rpx rgba(255, 255, 255, 0.3) inset;
	}

	.voice-btn-wrapper {
		position: relative;
		flex-shrink: 0;
		margin-right: 10rpx;
	}

	.voice-btn-inner {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 36rpx;
		height: 36rpx;
		border-radius: 50%;
		background: #ffffff;
		box-shadow: 0 1.5rpx 6rpx rgba(0, 0, 0, 0.15),
					0 0 0 1.5rpx rgba(108,76,230,0.3);
		transition: all 0.3s ease;
	}

	.voice-player.playing .voice-btn-inner {
		animation: btn-pulse 0.8s ease-in-out infinite;
	}

	@keyframes btn-pulse {
		0%, 100% { transform: scale(1); box-shadow: 0 1.5rpx 6rpx rgba(0, 0, 0, 0.15), 0 0 0 1.5rpx rgba(108,76,230,0.3); }
		50% { transform: scale(1.05); box-shadow: 0 3rpx 9rpx rgba(0, 0, 0, 0.2), 0 0 0 3rpx rgba(108,76,230,0.5); }
	}

	.voice-bars {
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 5rpx;
		height: 30rpx;
	}

	.voice-bar-item {
		width: 6rpx;
		background: rgba(255, 255, 255, 0.9);
		border-radius: 3rpx;
		transition: all 0.2s ease;
	}

	.voice-bar-item:nth-child(1) { height: 15rpx; }
	.voice-bar-item:nth-child(2) { height: 21rpx; }
	.voice-bar-item:nth-child(3) { height: 30rpx; }
	.voice-bar-item:nth-child(4) { height: 21rpx; }
	.voice-bar-item:nth-child(5) { height: 15rpx; }

	.voice-bar-item.active {
		background: linear-gradient(to top, #ffffff 0%, rgba(255, 255, 255, 0.5) 100%);
		animation: bar-dance 0.7s ease-in-out infinite;
	}

	@keyframes bar-dance {
		0%, 100% { transform: scaleY(0.5); opacity: 0.7; }
		50% { transform: scaleY(1); opacity: 1; }
	}

	// 评分容器 - 精确对齐
	.score-align-container {
		display: flex;
		align-items: center;
		height: 32rpx;
	}

	// 星星图标 - 固定尺寸
	.star-icon-fixed {
		width: 24rpx;
		height: 24rpx;
		display: inline-block;
		vertical-align: middle;
		flex-shrink: 0;
	}

	// 评分文字
	.score-text-fixed {
		font-size: 24rpx;
		color: #7a7a86;
		line-height: 24rpx;
		display: inline-block;
		vertical-align: middle;
		margin-left: 8rpx;
	}
</style>
