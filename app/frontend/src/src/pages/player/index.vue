<template>
	<view class="pk-page">
		<!-- 底部导航栏，当前选中第3个（打手专区） -->
		<commonTabbar :current="2" :placeholder="false"></commonTabbar>
		<!-- z-paging 滚动列表容器 -->
		<z-paging ref="paging" v-model="dataList" :default-page-size="8" @query="queryList" auto-show-back-to-top
			@scrollTopChange="scrollTopChange" :use-page-scroll="false" :refresher-enabled="true">
			<view class="pk-body">
				<!-- 顶部标题 + 搜索 -->
				<view class="pk-topbar">
					<view class="pk-title">打手专区</view>
					<view class="pk-search">
						<u-search placeholder="搜索打手昵称" v-model="keyword" :showAction="false" :focus="searchFocus"
							@search="search"></u-search>
					</view>
				</view>

				<!-- 一级分类 chips -->
				<scroll-view class="pk-cats" scroll-x v-if="categoryList.length">
					<view class="pk-cats-inner">
						<view v-for="(item, index) in categoryList" :key="index"
							:class="['pk-cat', categoryCurrent === index ? 'pk-cat-on' : '']"
							@click="changeCategory(index)">
							<view class="pk-cat-ico">
								<image :src="item.image" mode="aspectFill"></image>
							</view>
							<text class="pk-cat-txt">{{item.name}}</text>
						</view>
					</view>
				</scroll-view>

				<!-- 男陪 / 女陪 两张专区 banner -->
				<view class="pk-banners">
					<view :class="['pk-banner', 'pk-banner-m', type === '1' ? 'pk-banner-on' : '']"
						@click="pickBanner('1')">
						<view class="pk-banner-l">
							<text class="pk-banner-t">男陪专区</text>
							<text class="pk-banner-s">[ 专属男神 ]</text>
						</view>
						<view class="pk-banner-play">
							<view class="pk-banner-tri"></view>
							<view class="pk-banner-tri"></view>
						</view>
					</view>
					<view :class="['pk-banner', 'pk-banner-f', type === '0' ? 'pk-banner-on' : '']"
						@click="pickBanner('0')">
						<view class="pk-banner-l">
							<text class="pk-banner-t">女陪专区</text>
							<text class="pk-banner-s">[ 终极甜心 ]</text>
						</view>
						<view class="pk-banner-play">
							<view class="pk-banner-tri"></view>
							<view class="pk-banner-tri"></view>
						</view>
					</view>
				</view>

				<!-- 打手卡片列表 -->
				<view class="pk-list">
					<view v-for="(item, index) in dataList" :key="index" class="pk-card" @click="goToPage(item)">
						<!-- 左侧视频封面 -->
						<view class="pk-cover-wrap">
							<image class="pk-cover" :src="coverOf(item)" mode="aspectFill"></image>
							<view class="pk-play-btn" @click.stop="toggleAudio(index, $event)">
								<up-icon :name="playingIndex === index ? 'pause' : 'play-right-fill'" size="22rpx"
									color="#ffffff"></up-icon>
							</view>
							<view class="pk-wave">
								<view class="pk-wave-bar" :class="{'on': playingIndex === index}"
									:style="{animationDelay:(i*0.12)+'s'}" v-for="i in 4" :key="i"></view>
							</view>
							<text class="pk-dur" v-if="durOf(item)">{{durOf(item)}}</text>
						</view>
						<!-- 右侧信息 -->
						<view class="pk-info">
							<view class="pk-row1">
								<view class="pk-name-wrap">
									<text class="pk-name">{{item.name}}</text>
									<text class="pk-cow" v-if="item.type == 1 || item.is_new == 1">🐮</text>
									<image class="pk-sex" :src="getSex(item)" mode="aspectFit"></image>
								</view>
								<view class="pk-online">
									<view class="pk-dot" :class="item.jd_status == 0 ? 'off' : ''"></view>
									<text class="pk-online-txt" :style="getJDstatus(item).color">{{item.jd_status == 0 ? '离线' : '在线'}}</text>
								</view>
							</view>
							<text class="pk-desc">{{ (item.synopsis || item.intro || '').slice(0, 16) }}{{ (item.synopsis || item.intro || '').length > 16 ? '...' : '' }}</text>
							<view class="pk-row3">
								<view class="pk-tags">
									<template v-if="tagsOf(item).length">
										<text class="pk-tag" :class="'pk-tag-' + (ti % 2)" v-for="(tg, ti) in tagsOf(item)"
											:key="ti">{{tg}}</text>
									</template>
									<text class="pk-tag pk-tag-0" v-else>{{getplayerLevl(item).title}}</text>
								</view>
								<view class="pk-order" @click.stop="goToPage(item)">
									<up-icon name="heart-fill" color="#ffffff" size="20rpx"></up-icon>
									<text class="pk-order-txt">下单</text>
								</view>
							</view>
						</view>
					</view>
					<!-- 空状态 -->
					<view v-if="!dataList.length" class="pk-empty">
						<view class="pk-empty-txt">暂无合适的打手</view>
					</view>
					<!-- 底部安全区占位 -->
					<view class="pk-bottom-space"></view>
				</view>
			</view>

			<!-- 隐藏 z-paging 自带空状态 -->
			<template #empty>
				<view></view>
			</template>
		</z-paging>

		<!-- 悬浮搜索按钮 -->
		<view class="pk-fab" @click="focusSearch">
			<up-icon name="search" color="#ffffff" size="38rpx"></up-icon>
		</view>
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
	// 搜索框聚焦状态（悬浮搜索按钮点击时触发）
	const searchFocus = ref(false)
	// 导航栏背景色
	const bgColor = ref('transparent')
	// 一级分类数据
	const categoryList = ref([]);
	// 当前选中的一级分类索引
	const categoryCurrent = ref(0)
	// 当前选中的分类ID
	const currentCategoryId = ref(0)
	// 当前选中的打手类型：5=全部 1=男陪 0=女陪
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

	// 获取打手封面图
	const coverOf = (item) => {
		if (!item) return '/static/icon/default_avatar.png'
		return item.image || item.logo || item.cover || item.avatar || '/static/icon/default_avatar.png'
	}

	// 获取语音时长文案
	const durOf = (item) => {
		if (!item) return ''
		const d = item.audio_time || item.duration || item.voice_time
		return d ? (String(d).replace('s', '') + 's') : ''
	}

	// 获取打手标签
	const tagsOf = (item) => {
		if (!item) return []
		const raw = item.tags || item.label || item.labels || ''
		if (!raw) return []
		const arr = Array.isArray(raw) ? raw : String(raw).split(/[,，]/)
		return arr.map(t => String(t).trim()).filter(Boolean).slice(0, 3)
	}

	// 切换男陪/女陪专区
	const pickBanner = (v) => {
		// 再次点击已选专区则恢复全部
		type.value = (type.value === v) ? '5' : v
		paging.value && paging.value.reload()
	}

	// 悬浮搜索按钮：回到顶部并聚焦搜索框
	const focusSearch = () => {
		if (paging.value) paging.value.scrollToTop && paging.value.scrollToTop()
		searchFocus.value = false
		setTimeout(() => { searchFocus.value = true }, 50)
	}

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
	// 页面背景
	page {
		background: #ffffff;
	}

	.page-container {
		min-height: 100vh;
		background: linear-gradient(180deg, #FF7A45 0%, #ffffff 32%, #ffffff 100%);
	}

	// 顶部头部容器
	.top-header {
		padding: 20rpx 30rpx;
		background: transparent;
	}

	// 导航栏标题样式
	.navleft {
		font-family: PingFang SC, PingFang SC;
		font-weight: bold;
		font-size: 42rpx;
		color: #ffffff;
		text-align: center;
		margin-bottom: 20rpx;
	}

	// 搜索框玻璃质感
	.search-glass {
		background: rgba(255,255,255,0.75);
		backdrop-filter: blur(24rpx);
		-webkit-backdrop-filter: blur(24rpx);
		border: 1rpx solid rgba(255,255,255,0.6);
		border-radius: 16rpx;
		padding: 8rpx 16rpx;
		box-shadow: 0 2rpx 20rpx rgba(0,0,0,0.04), inset 0 1rpx rgba(255,255,255,0.8);
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

	// 标签容器居中
	.tabs-container {
		display: flex;
		justify-content: center;
		align-items: center;
		width: 100%;
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
		background: linear-gradient(270deg, #FF9D6E 0%, #FF7A45 100%);
		border-radius: 10rpx 10rpx 10rpx 10rpx;
		z-index: -1;
		bottom: 0rpx;
		left: 0;
	}

	// 选中标签的文字样式
	.sel {
		font-family: PingFang SC, PingFang SC;
		font-weight: bold;
		font-size: 38rpx;
		color: #ffffff;
	}

	// 未选中标签的文字样式
	.nor {
		font-family: PingFang SC, PingFang SC;
		font-weight: 500;
		font-size: 36rpx;
		color: #999999;
	}

	// 可接单状态的圆点样式
	.point {
		width: 8rpx;
		height: 8rpx;
		background: #1BD36C;
		border-radius: 4rpx;
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

	// 头像图片样式
	.img {
		width: 280rpx;
		height: 280rpx;
		border-radius: 20rpx 0 0 20rpx;
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

	// 打手卡片样式 - 平行四边形
	.player-card {
		background: #ffffff;
		position: relative;
		padding: 0rpx;
		transition: all 0.3s ease;
		overflow: visible;
		z-index: 1;
		box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.08);
		// 平行四边形效果：向左倾斜5度
		transform: skewX(-5deg);
		// 内部内容反向倾斜，保持文字正常
		& > * {
			transform: skewX(5deg);
		}
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
		background: #f2f3f5;
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
					linear-gradient(135deg, #FF7A45, #FFB38A, #FF7A45) border-box;
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
		color: #000000;
		text-align: center;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		padding: 6rpx 20rpx;
		line-height: 32rpx;
	}

	.category-item-active .category-name {
		background: linear-gradient(135deg, #FF7A45, #FF9D6E, #FF7A45);
		padding: 6rpx 20rpx;
		border-radius: 24rpx;
		color: #ffffff;
	}

	.category-tag {
		width: 8rpx;
		height: 8rpx;
		border-radius: 50%;
		background: #FF7A45;
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

	// 语音播放器小图标样式
	.voice-icon {
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.voice-icon-btn {
		width: 40rpx;
		height: 40rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		position: relative;
	}

	.voice-icon-btn.playing {
		animation: playing-bounce 0.6s ease-in-out infinite;
	}

	@keyframes playing-bounce {
		0%, 100% { transform: scale(1); }
		50% { transform: scale(1.1); }
	}

	.voice-icon-pulse {
		position: absolute;
		width: 100%;
		height: 100%;
		border-radius: 50%;
		background: rgba(255, 122, 69, 0.2);
		animation: voice-icon-pulse 1.5s infinite;
	}

	@keyframes voice-icon-pulse {
		0% {
			transform: scale(1);
			opacity: 0.7;
		}
		50% {
			transform: scale(1.3);
			opacity: 0;
		}
		100% {
			transform: scale(1);
			opacity: 0;
		}
	}

	/* 天蓝色科技风语音播放器样式 */
	.voice-player {
		position: relative;
		display: flex;
		align-items: center;
		justify-content: flex-start;
		flex-shrink: 0;
		padding: 8rpx 14rpx;
		border-radius: 40rpx;
		background: linear-gradient(135deg, #FF7A45 0%, #FF9D6E 100%);
		box-shadow: 0 3rpx 15rpx rgba(255, 122, 69, 0.4),
					0 0 0 1rpx rgba(255, 255, 255, 0.2) inset;
		transition: all 0.3s ease;
		overflow: visible;
		min-width: 100rpx;
	}

	.voice-player.playing {
		box-shadow: 0 4rpx 24rpx rgba(255, 122, 69, 0.7),
					0 0 30rpx rgba(255, 122, 69, 0.4),
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
					0 0 0 1.5rpx rgba(255, 122, 69, 0.3);
		transition: all 0.3s ease;
	}

	.voice-player.playing .voice-btn-inner {
		animation: btn-pulse 0.8s ease-in-out infinite;
	}

	@keyframes btn-pulse {
		0%, 100% { transform: scale(1); box-shadow: 0 1.5rpx 6rpx rgba(0, 0, 0, 0.15), 0 0 0 1.5rpx rgba(255, 122, 69, 0.3); }
		50% { transform: scale(1.05); box-shadow: 0 3rpx 9rpx rgba(0, 0, 0, 0.2), 0 0 0 3rpx rgba(255, 122, 69, 0.5); }
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
		color: #666666;
		line-height: 24rpx;
		display: inline-block;
		vertical-align: middle;
		margin-left: 8rpx;
	}

	/* ==================== 打手专区（新样式） ==================== */
	page {
		background: #f5f6f8;
	}

	.pk-page {
		min-height: 100vh;
		background: #f5f6f8;
	}

	.pk-body {
		padding: 0 24rpx;
	}

	// 顶部标题 + 搜索
	.pk-topbar {
		padding: 24rpx 0 8rpx;
	}

	.pk-title {
		font-size: 46rpx;
		font-weight: 800;
		color: #1f2937;
		letter-spacing: 2rpx;
		margin-bottom: 18rpx;
	}

	.pk-search ::v-deep .u-search {
		background: #ffffff;
		border-radius: 16rpx;
	}

	// 一级分类 chips
	.pk-cats {
		white-space: nowrap;
		width: 100%;
		margin-top: 8rpx;
	}

	.pk-cats-inner {
		display: inline-flex;
		align-items: flex-start;
		padding: 8rpx 0 16rpx;
	}

	.pk-cat {
		display: inline-flex;
		flex-direction: column;
		align-items: center;
		width: 150rpx;
		margin-right: 16rpx;
	}

	.pk-cat-ico {
		width: 132rpx;
		height: 132rpx;
		border-radius: 28rpx;
		overflow: hidden;
		background: #fdf3e8;
		display: flex;
		align-items: center;
		justify-content: center;
		transition: all .25s ease;
	}

	.pk-cat-ico image {
		width: 88rpx;
		height: 88rpx;
		border-radius: 50%;
	}

	.pk-cat-txt {
		font-size: 24rpx;
		color: #4b5563;
		margin-top: 10rpx;
		max-width: 140rpx;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.pk-cat-on .pk-cat-ico {
		background: linear-gradient(135deg, #8b7cf6 0%, #6d5efc 100%);
		box-shadow: 0 8rpx 20rpx rgba(109, 94, 252, .35);
	}

	.pk-cat-on .pk-cat-txt {
		color: #ffffff;
		font-weight: 700;
		background: linear-gradient(135deg, #8b7cf6 0%, #6d5efc 100%);
		padding: 2rpx 18rpx;
		border-radius: 20rpx;
	}

	// 男陪 / 女陪 banner
	.pk-banners {
		display: flex;
		justify-content: space-between;
		margin: 12rpx 0 20rpx;
	}

	.pk-banner {
		flex: 1;
		height: 168rpx;
		border-radius: 24rpx;
		position: relative;
		overflow: hidden;
		display: flex;
		align-items: center;
		padding: 0 20rpx;
		background: linear-gradient(135deg, #b9c0cc 0%, #8b95a5 100%);
		transition: all .25s ease;
	}

	.pk-banner + .pk-banner {
		margin-left: 18rpx;
	}

	.pk-banner-m.pk-banner-on {
		background: linear-gradient(135deg, #7c6cf6 0%, #4a6cf7 100%);
		box-shadow: 0 10rpx 24rpx rgba(90, 96, 250, .35);
	}

	.pk-banner-f.pk-banner-on {
		background: linear-gradient(135deg, #ff8bb8 0%, #ff4d94 100%);
		box-shadow: 0 10rpx 24rpx rgba(255, 77, 148, .35);
	}

	.pk-banner-l {
		display: flex;
		flex-direction: column;
		justify-content: center;
	}

	.pk-banner-t {
		font-size: 40rpx;
		font-weight: 800;
		color: #ffffff;
		letter-spacing: 2rpx;
		text-shadow: 0 2rpx 6rpx rgba(0, 0, 0, .12);
	}

	.pk-banner-s {
		font-size: 22rpx;
		color: rgba(255, 255, 255, .9);
		margin-top: 8rpx;
		letter-spacing: 2rpx;
	}

	.pk-banner-play {
		display: flex;
		align-items: center;
		margin-top: 14rpx;
	}

	.pk-banner-tri {
		width: 0;
		height: 0;
		border-top: 9rpx solid transparent;
		border-bottom: 9rpx solid transparent;
		border-left: 14rpx solid rgba(255, 255, 255, .85);
		margin-right: 8rpx;
	}

	// 打手卡片列表
	.pk-list {
		padding-top: 4rpx;
	}

	.pk-card {
		display: flex;
		align-items: stretch;
		background: #ffffff;
		border-radius: 24rpx;
		padding: 16rpx;
		margin-bottom: 18rpx;
		box-shadow: 0 4rpx 16rpx rgba(17, 24, 39, .05);
	}

	.pk-cover-wrap {
		position: relative;
		width: 210rpx;
		height: 210rpx;
		border-radius: 20rpx;
		overflow: hidden;
		flex-shrink: 0;
		background: #eef0f4;
	}

	.pk-cover {
		width: 100%;
		height: 100%;
	}

	.pk-play-btn {
		position: absolute;
		left: 12rpx;
		bottom: 12rpx;
		width: 54rpx;
		height: 54rpx;
		border-radius: 50%;
		background: rgba(0, 0, 0, .55);
		display: flex;
		align-items: center;
		justify-content: center;
		z-index: 2;
	}

	.pk-wave {
		position: absolute;
		left: 76rpx;
		bottom: 26rpx;
		display: flex;
		align-items: flex-end;
		height: 30rpx;
		gap: 6rpx;
		z-index: 2;
	}

	.pk-wave-bar {
		width: 6rpx;
		height: 18rpx;
		background: rgba(255, 255, 255, .9);
		border-radius: 4rpx;
	}

	.pk-wave-bar.on {
		animation: pk-wave-dance .7s ease-in-out infinite;
	}

	@keyframes pk-wave-dance {
		0%, 100% { transform: scaleY(.45); }
		50% { transform: scaleY(1); }
	}

	.pk-dur {
		position: absolute;
		left: 122rpx;
		bottom: 20rpx;
		color: #ffffff;
		font-size: 24rpx;
		font-weight: 600;
		text-shadow: 0 2rpx 6rpx rgba(0, 0, 0, .4);
		z-index: 2;
	}

	.pk-info {
		flex: 1;
		display: flex;
		flex-direction: column;
		justify-content: space-between;
		padding: 4rpx 0 4rpx 20rpx;
		overflow: hidden;
	}

	.pk-row1 {
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.pk-name-wrap {
		display: flex;
		align-items: center;
		flex: 1;
		overflow: hidden;
	}

	.pk-name {
		font-size: 34rpx;
		font-weight: 700;
		color: #1f2937;
		max-width: 300rpx;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.pk-cow {
		font-size: 28rpx;
		margin-left: 8rpx;
	}

	.pk-sex {
		width: 30rpx;
		height: 30rpx;
		margin-left: 8rpx;
		flex-shrink: 0;
	}

	.pk-online {
		display: flex;
		align-items: center;
		flex-shrink: 0;
		margin-left: 12rpx;
	}

	.pk-dot {
		width: 12rpx;
		height: 12rpx;
		border-radius: 50%;
		background: #1BD36C;
		margin-right: 8rpx;
	}

	.pk-dot.off {
		background: #BBBBBB;
	}

	.pk-online-txt {
		font-size: 24rpx;
		color: #1BD36C;
	}

	.pk-desc {
		font-size: 26rpx;
		color: #9ca3af;
		margin: 14rpx 0;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.pk-row3 {
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.pk-tags {
		display: flex;
		align-items: center;
		flex: 1;
		overflow: hidden;
	}

	.pk-tag {
		font-size: 22rpx;
		padding: 6rpx 16rpx;
		border-radius: 10rpx;
		margin-right: 10rpx;
		white-space: nowrap;
	}

	.pk-tag-0 {
		color: #ff5c8d;
		background: #fdeef4;
	}

	.pk-tag-1 {
		color: #3d7eff;
		background: #e9f1ff;
	}

	.pk-order {
		display: flex;
		align-items: center;
		justify-content: center;
		background: #1c1c1e;
		padding: 10rpx 24rpx;
		border-radius: 14rpx;
		flex-shrink: 0;
	}

	.pk-order-txt {
		font-size: 26rpx;
		color: #ffffff;
		margin-left: 8rpx;
		font-weight: 600;
	}

	// 空状态
	.pk-empty {
		display: flex;
		align-items: center;
		justify-content: center;
		padding: 160rpx 0;
	}

	.pk-empty-txt {
		font-size: 30rpx;
		color: #bbbbbb;
	}

	// 底部安全区占位
	.pk-bottom-space {
		height: calc(160rpx + constant(safe-area-inset-bottom));
		height: calc(160rpx + env(safe-area-inset-bottom));
		width: 100%;
	}

	// 悬浮搜索按钮
	.pk-fab {
		position: fixed;
		right: 40rpx;
		bottom: calc(200rpx + env(safe-area-inset-bottom));
		width: 100rpx;
		height: 100rpx;
		border-radius: 50%;
		background: linear-gradient(135deg, #ff9d6e 0%, #ff7a45 100%);
		display: flex;
		align-items: center;
		justify-content: center;
		box-shadow: 0 10rpx 26rpx rgba(255, 122, 69, .45);
		z-index: 99;
	}
</style>
