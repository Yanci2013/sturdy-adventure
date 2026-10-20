<template>
	<view>
	<z-paging ref="paging" v-model="dataList" @query="queryList" :auto="false" auto-show-back-to-top
		@scrollTopChange="scrollTopChange">
		<commonTabbar :current="0" :placeholder="false"></commonTabbar>
		<view class="order-broadcast" :key="(currentOrderBroadcast.id || orderBroadcastIndex) + '-' + orderBroadcastPlayKey" v-if="pageMode === 'goods' && orderBroadcastList.length">
			<image class="order-broadcast__avatar" :src="currentOrderBroadcast.avatar" mode="aspectFill"></image>
			<view class="order-broadcast__text-wrap">
				<text class="order-broadcast__text">{{ currentOrderBroadcast.name }} 购买了 {{ currentOrderBroadcast.title }}</text>
			</view>
		</view>

		<view class="page">
			<view class="box">
				<image class="box-bg" src="/static/images/header_background.png" mode="widthFix"></image>
				<view class="header-content">
					<!-- 搜索栏 -->
					<view class="index-search" @click="toggleSearch">
						<image class="index-search__icon" src="/static/ym/ss.png" mode="aspectFit"></image>
						<text class="index-search__ph">搜索你喜欢的单子</text>
					</view>
					<!-- 俱乐部 Logo + 名称（后台可配） -->
					<view class="club-brand">
						<image class="club-brand__logo" :src="siteConfig.site_logo || '/static/eon.png'" mode="aspectFill"></image>
						<text class="club-brand__name">{{ siteConfig.club_name || '创世网络' }}</text>
					</view>
				</view>

				<!-- 搜索弹窗 -->
				<view class="search-popup-mask" v-if="showSearch" @click="closeSearch">
					<view class="search-popup" @click.stop>
						<view class="search-popup-header">
							<view class="search-popup-input-wrap">
								<image class="search-popup-icon" src="/static/yun/下载 (9).png" mode="aspectFit"></image>
								<input
									class="search-popup-input"
									v-model="goodsFilterForm.keyword"
									placeholder="搜索你喜欢的单子"
									placeholder-class="search-placeholder"
									confirm-type="search"
									@confirm="searchConfirm"
									focus
								/>
							</view>
							<text class="search-popup-cancel" @click="closeSearch">取消</text>
						</view>
					</view>
				</view>

				<!-- 公告弹窗 -->
				<view class="notice-popup-mask" v-if="showNotice" @click="closeNotice">
					<view class="notice-popup" @click.stop>
						<view class="notice-popup-header">
							<text class="anno-title">公告</text>
						</view>
						<view class="notice-popup-body">
							<text class="anno-label">{{ notice }}</text>
							<rich-text class="anno-content" :nodes="noticeContent" v-if="noticeContent"></rich-text>
							<image v-if="noticeImage" class="anno-cover" :src="noticeImage" mode="widthFix"></image>
						</view>
						<view class="notice-popup-close" @click="closeNotice">
							<text class="close-icon">×</text>
						</view>
					</view>
				</view>

				<block v-if="pageMode === 'goods'">

				<!-- 轮播图 -->
				<view class="swiper-view rlp20" v-if="lunboList.length > 1">
					<up-swiper
						:list="lunboList"
						height="340rpx"
						radius="20rpx"
						keyName="image"
						duration="700"
						:circular="true"
						bgColor="transparent"
						indicator
						indicatorActiveColor="#fff"
						indicatorInactiveColor="rgba(255,255,255,0.35)"
						@change="onSwiperChange"
						@click="previewBanner"
					>
					</up-swiper>
				</view>
				<view class="swiper-view rlp20" v-else-if="lunboList.length === 1">
					<image
						class="single-banner"
						:src="lunboList[0].image"
						mode="aspectFill"
						@click="previewBanner"
					></image>
				</view>

				<!-- 公告栏 -->
				<view class="notice-bar" v-if="noticeListData.length && !noticeBarClosed">
					<view class="notice-bar__main" @click="openNotice(noticeListData[0])">
						<svg class="notice-bar__icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M4 9.4h3.2l7.3-3.9v11.9l-7.3-3.9H4z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
							<path d="M17.6 8.6a5 5 0 0 1 0 6.8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
							<path d="M7.2 9.4v5.2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
						</svg>
						<text class="notice-bar__text">{{ noticeListData[0].barText || noticeListData[0].title }}</text>
					</view>
					<view class="notice-bar__close" @click.stop="closeNoticeBar">
						<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M7.5 7.5l9 9M16.5 7.5l-9 9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
						</svg>
					</view>
				</view>

				<!-- 活动 / 投诉入口卡片 -->
				<view class="entry-tiles rlp20" v-if="navCards.length">
					<view class="entry-tiles__row">
						<view
							v-for="(item, index) in navCards"
							:key="item.id || index"
							class="entry-tile"
							@click="openNavCard(item)"
						>
							<image v-if="item.image" class="entry-tile__img" :src="item.image" mode="aspectFill"></image>
							<template v-else>
								<view class="entry-tile__icon">
									<svg v-if="index === 0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M4 8.6h10.4v6.2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8.6z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
										<path d="M14.4 10.6h2.4a2 2 0 0 1 0 4h-2.4" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
										<path d="M8 5.6v1.2M12 5v1.8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
									</svg>
									<svg v-else-if="index === 1" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<rect x="5" y="4.5" width="14" height="16" rx="2.4" stroke="currentColor" stroke-width="1.7"/>
										<path d="M9 4.5a3 3 0 0 1 6 0" stroke="currentColor" stroke-width="1.7"/>
										<path d="M9 12.2l2 2 4-4.2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
									<svg v-else viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M5 15.6V8.2A1.2 1.2 0 0 1 6.2 7h11.6A1.2 1.2 0 0 1 19 8.2v7.4" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
										<path d="M4.6 15.4h14.8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
										<path d="M8.5 18.6h7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
										<path d="M12 6.4l1 2.2 2.4.3-1.8 1.6.5 2.4-2.1-1.2-2.1 1.2.5-2.4-1.8-1.6 2.4-.3z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/>
									</svg>
								</view>
								<text class="entry-tile__title">{{ item.cardTitle }}</text>
								<text class="entry-tile__sub">{{ item.cardSub }}</text>
							</template>
						</view>
					</view>
				</view>

		<!-- 快捷功能入口 -->
		<view class="quick-section">
			<view class="quick-grid">
				<view class="quick-item" @click="gotoQuick('process')">
					<view class="quick-icon">
						<svg class="quick-svg" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M7 11L4.5 13.5M7 11L9.5 8.5M7 11L4.5 8.5M7 11L9.5 13.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M17 11L14.5 13.5M17 11L19.5 8.5M17 11L14.5 8.5M17 11L19.5 13.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M9.5 8.5L12 6L14.5 8.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M3 17.5L4.5 19L7.5 16M21 17.5L19.5 19L16.5 16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M7.5 16L10 18.5L12 16.5L14 18.5L16.5 16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</view>
					<text class="quick-name">流程服务</text>
				</view>
				<view class="quick-item" @click="gotoQuick('wallet')">
					<view class="quick-icon">
						<svg class="quick-svg" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M3 7C3 5.89543 3.89543 5 5 5H17C18.1046 5 19 5.89543 19 7V8H5C3.89543 8 3 7.10457 3 7Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
							<rect x="3" y="8" width="18" height="11" rx="2" stroke="currentColor" stroke-width="1.8"/>
							<circle cx="16.5" cy="13.5" r="1.3" fill="currentColor"/>
							<path d="M3 12H19" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
						</svg>
					</view>
					<text class="quick-name">我的钱包</text>
				</view>
				<view class="quick-item" @click="gotoQuick('service')">
					<view class="quick-icon">
						<svg class="quick-svg" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M4 13V12C4 7.58172 7.58172 4 12 4C16.4183 4 20 7.58172 20 12V13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
							<path d="M4 13V15C4 16.1046 4.89543 17 6 17H7V13H4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
							<path d="M20 13V15C20 16.1046 19.1046 17 18 17H17V13H20Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
							<path d="M8 17V18C8 19.6569 9.34315 21 11 21H13C14.6569 21 16 19.6569 16 18V17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
							<path d="M12 4V2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
							<circle cx="12" cy="1.5" r="1" fill="currentColor"/>
						</svg>
					</view>
					<text class="quick-name">联系客服</text>
				</view>
				<view class="quick-item" @click="gotoQuick('recruit')">
					<view class="quick-icon">
						<svg class="quick-svg" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M7 8H17C19.2091 8 21 9.79086 21 12C21 14.2091 19.2091 16 17 16H7C4.79086 16 3 14.2091 3 12C3 9.79086 4.79086 8 7 8Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
							<path d="M7 11V13M6 12H8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
							<circle cx="16" cy="11" r="1" fill="currentColor"/>
							<circle cx="18" cy="13" r="1" fill="currentColor"/>
							<path d="M9 8L8 5H6L7 8M15 8L16 5H18L17 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</view>
					<text class="quick-name">招募陪玩</text>
				</view>
			</view>
		</view>

		<!-- 传世武器活动横幅（背景音乐上方），打造与抽奖互斥 -->
		<view v-if="craftMode === 'craft'" class="weapon-banner rlp20" @click="openCraft">
			<image v-if="craftBanner" :src="craftBanner" mode="aspectFill" class="weapon-banner__img"></image>
			<view v-else class="weapon-banner__content">
				<svg class="weapon-banner__art" viewBox="0 0 96 80" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M74 14l2.6 6 6.4.8-4.7 4.3 1.3 6.4-5.9-3.2-5.9 3.2 1.3-6.4-4.7-4.3 6.4-.8z" stroke="currentColor" stroke-width="1.1" stroke-linejoin="round"/>
					<circle cx="72" cy="52" r="15" stroke="currentColor" stroke-width="1.1"/>
					<path d="M72 40v24M60 52h24" stroke="currentColor" stroke-width="1.1" stroke-linecap="round"/>
				</svg>
				<view class="weapon-banner__text">
					<view class="weapon-banner__title">传世武器体验单</view>
					<view class="weapon-banner__sub">更多优惠活动等你来</view>
				</view>
				<view class="weapon-banner__go">
					<text class="weapon-banner__go-text">立即参与</text>
				</view>
			</view>
		</view>
		<view v-else class="weapon-banner rlp20" @click="openLottery">
			<image v-if="lotteryBanner" :src="lotteryBanner" mode="aspectFill" class="weapon-banner__img"></image>
			<view v-else class="weapon-banner__content">
				<svg class="weapon-banner__art" viewBox="0 0 96 80" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M74 14l2.6 6 6.4.8-4.7 4.3 1.3 6.4-5.9-3.2-5.9 3.2 1.3-6.4-4.7-4.3 6.4-.8z" stroke="currentColor" stroke-width="1.1" stroke-linejoin="round"/>
					<circle cx="72" cy="52" r="15" stroke="currentColor" stroke-width="1.1"/>
					<path d="M72 40v24M60 52h24" stroke="currentColor" stroke-width="1.1" stroke-linecap="round"/>
				</svg>
				<view class="weapon-banner__text">
					<view class="weapon-banner__title">抽传世武器</view>
					<view class="weapon-banner__sub">极品传世武器等你来抽</view>
				</view>
				<view class="weapon-banner__go">
					<text class="weapon-banner__go-text">立即参与</text>
				</view>
			</view>
		</view>

		<!-- 首页背景音乐（分类上方） -->
		<view class="bgm-bar" v-if="bgmEnabled && bgmTitle" @click="toggleBgm">
			<view class="bgm-icon">
				<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M9 18V6l10-2v12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
					<circle cx="6.5" cy="18" r="2.6" stroke="currentColor" stroke-width="1.8"/>
					<circle cx="16.5" cy="16" r="2.6" stroke="currentColor" stroke-width="1.8"/>
				</svg>
			</view>
			<view class="bgm-info">
				<text class="bgm-title">{{ bgmTitle }}</text>
				<text class="bgm-sub">{{ bgmPlaying ? '正在播放 · 点击暂停' : (userLevelNum < 2 ? '会员专属 · 点击开通会员' : bgmSub) }}</text>
			</view>
			<view :class="['bgm-btn', bgmPlaying ? 'bgm-btn-playing' : '']">
				<text class="bgm-btn-text">{{ bgmPlaying ? '暂停' : '播放' }}</text>
			</view>
		</view>

		<!-- 一级分类 -->
			<view class="category-section">
				<view class="category-grid">
					<view
						v-for="(item, index) in categoryList"
						:key="index"
						:class="['category-item', current === index ? 'category-item-active' : '']"
						@click="changeCategory(index)"
					>
						<view class="category-icon-wrap">
							<svg v-if="categoryIconType(item.name) === 'trophy'" class="category-icon-svg" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M7 4.5h10v4a5 5 0 0 1-10 0v-4z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
								<path d="M7 5.5H4.6a1 1 0 0 0-1 1.1c.2 1.7 1.5 2.9 3.4 2.9M17 5.5h2.4a1 1 0 0 1 1 1.1c-.2 1.7-1.5 2.9-3.4 2.9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
								<path d="M12 13.5v3.2M8.5 19.5h7M9.8 19.5l.5-2.8h3.4l.5 2.8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
							<svg v-else-if="categoryIconType(item.name) === 'smile'" class="category-icon-svg" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<circle cx="12" cy="12" r="8.2" stroke="currentColor" stroke-width="1.6"/>
								<path d="M8.6 14.2a4.2 4.2 0 0 0 6.8 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
								<path d="M9.2 9.6h.01M14.8 9.6h.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
							</svg>
							<svg v-else-if="categoryIconType(item.name) === 'book'" class="category-icon-svg" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M12 6.6C10.6 5.4 8.7 4.9 6.2 4.9c-1 0-1.8.1-2.4.3v12.6c.6-.2 1.4-.3 2.4-.3 2.5 0 4.4.6 5.8 1.8 1.4-1.2 3.3-1.8 5.8-1.8 1 0 1.8.1 2.4.3V5.2c-.6-.2-1.4-.3-2.4-.3-2.5 0-4.4.5-5.8 1.7z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
								<path d="M12 6.6v12.7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
							</svg>
							<image v-else class="category-icon" :src="item.image" mode="aspectFill"></image>
						</view>
						<view :class="['category-name', current === index ? 'category-name-active' : '']">
							<text class="category-name-text">{{item.name}}</text>
						</view>
						<view class="category-underline" :class="{ show: current === index }"></view>
					</view>
				</view>
			</view>

				<!-- 瀑布流区域 -->
				<view class="waterfall-area">
					<!-- 二级标签 + 筛选 -->
					<view class="goods_classify_box">
						<scroll-view class="scroll-view" scroll-x="true" :scroll-left="goodsScrollLeft"
							scroll-with-animation="true">
							<view class="goods_classify_list">
								<view v-for="(item, index) in childrenList" :key="index"
									:class="['classify_item', classCurrent===index ? 'select' : '']"
									@click="changeTag(item,index)">
									<view class="classify_text">{{item.name}}</view>
									<view class="classify_shadow"></view>
								</view>
								<view class="classify_item placeholder"></view>
							</view>
						</scroll-view>
						<view class="filter-dropdown-wrapper">
							<view class="classify_fixed_button" @click="toggleGoodsColumns">
								<text class="goods-columns-text">{{ goodsColumns }}列</text>
							</view>
						</view>
					</view>

					<!-- 商品列表 -->
					<view :class="['goods-list', 'goods-list--cols-' + goodsColumns]">
						<view
							v-for="(item,index) in dataList"
							:key="item.id || index"
							class="goods-item goods-item-anim"
							@click="gotoPage('pagesA/index/detail',item.id)"
						>
							<view class="goods-item__image">
								<image :src="item.logo" :mode="goodsColumns === 1 ? 'aspectFill' : 'widthFix'"></image>
							</view>
							<view class="goods-item__ft">
								<view class="goods-item__ft__title">
									<text class="value uv-line-1">{{item.title}}</text>
								</view>
								<view class="goods-item__ft__desc uv-line-2" v-if="item.synopsis">
									<text class="value">{{item.synopsis}}</text>
								</view>
								<view class="goods-item__ft__price">
									<text class="start">￥</text>
									<text class="value">{{item.price}}</text>
									<text class="list_price" v-if="item.line_price">￥{{item.line_price}}</text>
									<text class="sale" v-if="item.sales">已售{{item.sales}}</text>
								</view>
							</view>
							<view class="goods-item__cart" v-if="goodsColumns === 1">
								<svg class="goods-item__cart-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M6.2 6.5H21L18.9 14.2H8.1L6.2 6.5Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
									<path d="M3 4H5.4L6.2 6.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
									<path d="M9 19.5H9.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
									<path d="M17 19.5H17.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
								</svg>
							</view>
						</view>
					</view>
			</view>

				</block>

			<block v-else>
					<!-- 打手专区 -->
					<view class="player-section">
						<!-- 打手一级分类 -->
						<view class="player-category-section" v-if="playerCategoryList.length">
							<view class="player-category-grid">
								<view
									v-for="(item, index) in playerCategoryList"
									:key="index"
									:class="['player-category-item', playerCategoryCurrent === index ? 'player-category-item-active' : '']"
									@click="changePlayerCategory(index)"
								>
									<view :class="['player-category-icon-wrap', playerCategoryCurrent === index ? 'player-category-icon-wrap-active' : '']">
										<image class="player-category-icon" :src="item.image" mode="aspectFill"></image>
									</view>
									<text :class="['player-category-name', playerCategoryCurrent === index ? 'player-category-name-active' : '']">{{item.name}}</text>
								</view>
							</view>
						</view>

						<!-- 打手类型标签 -->
						<view class="player-tabs">
							<view
								v-for="(item, index) in playerTabs"
								:key="index"
								:class="['player-tab-item', playerCurrentTab === index ? 'player-tab-active' : '']"
								@click="changePlayerTab(index)"
							>{{item.name}}</view>
						</view>

						<!-- 打手列表 -->
						<view class="player-list">
							<view v-if="dataList.length" v-for="(item,index) in dataList" :key="index"
								class="player-card"
								@click="goToPlayerDetail(item)">
								<view class="player-card-top">
									<view class="avatar-frame-wrapper">
										<view class="avatar-frame-inner">
											<u-image :src="item.avatar" mode="aspectFill" width="100rpx" height="100rpx" radius="50rpx"
												:error-src="'/static/icon/default_avatar.png'"
												class="avatar-main"></u-image>
											<image v-if="item.avatar_frame && item.avatar_frame.image"
												:src="item.avatar_frame.image"
												mode="aspectFill"
												class="avatar-frame-img"></image>
										</view>
									</view>
									<view class="player-card-info">
										<view class="player-card-row">
											<text class="player-name">{{item.name}}</text>
											<image :src="getPlayerSex(item)" class="player-sex-icon"></image>
											<view class="player-status" :class="item.jd_status==0?'player-status-off':'player-status-on'">
												{{item.jd_status==0?'离线':'在线'}}
											</view>
										</view>
										<view class="player-level-line">
											<view class="player-level-tag" :style="getPlayerLevel(item).bg">
												{{getPlayerLevel(item).title}}
											</view>
											<image class="vip-icon" :src="getVipIcon(item)" mode="aspectFit"></image>
										</view>
										<view>
											<up-text :text="item.synopsis" color="#666" lines="1" size="22rpx"></up-text>
										</view>
										<view class="player-card-row" style="justify-content: space-between;">
											<view class="player-score">
												<image src="/static/images/Star.png" class="player-star-icon" mode="aspectFit"></image>
												<text class="player-score-text">{{item.grade || 0}}分</text>
											</view>
											<!-- 语音播放器 -->
											<view v-if="item.audio" class="player-voice-card" @click.stop="togglePlayerAudio(index, $event)">
												<view class="player-voice-player" :class="{'playing': playingIndex === index}">
													<view class="player-voice-btn-wrapper">
														<view class="player-voice-btn-inner">
															<up-icon :name="playingIndex === index ? 'pause-circle-fill' : 'play-circle-fill'" size="18rpx" color="#C05B34"></up-icon>
														</view>
													</view>
													<view class="player-voice-bars">
														<view class="player-voice-bar-item" :class="{'active': playingIndex === index}" :style="{animationDelay: (i * 0.12) + 's'}" v-for="i in 5" :key="i"></view>
													</view>
												</view>
											</view>
										</view>
									</view>
								</view>
							</view>
							<uv-empty v-if="!dataList.length" mode="data" text="暂无合适的打手"></uv-empty>
						</view>
					</view>
				</block>

			</view>
		</view>

		<view style="height: 150rpx;"></view>

	</z-paging>

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
	</view>
</template>

<script setup>
	import {
		onMounted,
		onUnmounted,
		ref,
		computed
	} from 'vue';
import router from '../../utils/router';
import { openCustomerServiceChat, resolveCustomerService } from '../../utils/customer-service';
	import {
		lunbo,
		imageNavList,
		category,
		playerCategory,
		goods,
		noticeList,
		noticeMsg,
		thug,
		configure,
		orderBroadcast,
		getUserInfo
	} from '../../request/api';

	import {
		onInit,
		onShow
	} from '@dcloudio/uni-app';
	import {
		test
	} from '../../uni_modules/uview-plus';
	import {
		base_url
	} from '../../request/env';

	const paging = ref(null)
	const dataList = ref([])
	const current = ref(0)
	const classCurrent = ref(0)
	const one_category = ref('')
	const two_category = ref('0')
	const lunboList = ref([])
	const categoryList = ref([]);
	const childrenList = ref([])
	const keyword = ref('')
	const bgColor = ref('transparent')
	const notice = ref('')
	const noticeId = ref('')
	const noticeContent = ref('')
	const noticeImage = ref('')
	const noticeListData = ref([])
	const showNotice = ref(false)
	// 公告条关闭状态（本次会话内）
	const noticeBarClosed = ref(false)
	const closeNoticeBar = () => { noticeBarClosed.value = true }
	// 一级分类图标类型（按名称匹配：奖杯/笑脸/书本），未命中则用后台图片
	const categoryIconType = (name) => {
		const n = String(name || '')
		if (/教学|课程|课堂|培训/.test(n)) return 'book'
		if (/上分|上星|排位|代练|竞技|技术|战神/.test(n)) return 'trophy'
		if (/娱乐|陪玩|聊天|交友/.test(n)) return 'smile'
		return ''
	}
	const imageNavs = ref([])
	// 活动/投诉入口默认卡片（后台无「图片导航」数据时兜底显示）
	const defaultNavCards = [
		{ id: 'd_activity', label: '消费福利活动', cardTitle: '消费福利活动', cardSub: '限时消费福利', action: 'activity' },
		{ id: 'd_tousu_cs', label: '订单投诉入口', cardTitle: '订单投诉入口', cardSub: '订单问题快速反馈', action: 'tousu' },
		{ id: 'd_tousu_boss', label: '升级反馈通道', cardTitle: '升级反馈通道', cardSub: '高阶问题升级反馈', action: 'tousu' }
	]
	// 优先使用后台配置的图片导航（需带图片）；无配置时使用默认三项
	const navCards = computed(() => {
		const list = (imageNavs.value || []).filter(i => i.image_url).slice(0, 3)
		if (list.length) {
			return list.map(i => ({
				id: i.id,
				image: i.image_url,
				link_url: i.link_url,
				title: i.name || i.title || '',
				label: i.name || i.title || ''
			}))
		}
		return defaultNavCards
	})

	// 首页背景音乐
	const bgmEnabled = ref(false)
	const bgmTitle = ref('')
	const bgmSub = ref('')
	const bgmUrl = ref('')
	const bgmPlaying = ref(false)
	let bgmAudio = null
	// 会员等级（V2 及以上为会员，可播放背景音乐）
	const userLevelNum = ref(0)
	const loadUserLevel = async () => {
		try {
			const token = uni.getStorageSync('token')
			if (!token) { userLevelNum.value = 0; return }
			const res = await getUserInfo()
			if (res && res.code === 1) {
				const d = res.data || {}
				const levelName = d.level_name || (d.user_current_level_info && d.user_current_level_info.name) || ''
				const m = String(levelName).match(/\d+/)
				userLevelNum.value = m ? Number(m[0]) : 0
			}
		} catch (e) { userLevelNum.value = 0 }
	}

	// 传世武器入口配置（打造与抽奖互斥，只显示一种）
	const craftMode = ref('lottery')
	const craftBanner = ref('')
	const craftJump = ref('')
	const lotteryBanner = ref('')
	const lotteryJump = ref('')

	const scrollLeft = ref(0)
	const goodsScrollLeft = ref(0)
	const maxTopClassifyScroll = ref(3)

	const sort = ref('')
	const customerServiceModal = ref(null)
	const cslist = ref([])
	const currentBannerIndex = ref(0)
	const goodsFilterForm = ref({
		keyword: '',
		sort: ''
	})
	const showFilterMenu = ref(false)
	const showSearch = ref(false)
	const goodsColumns = ref(3)
	// 页面模式：goods（商品首页）/ player（打手专区）
	const pageMode = ref('goods')
	// 打手专区相关
	const playerCategoryList = ref([])
	const playerCategoryCurrent = ref(0)
	const playerCurrentCategoryId = ref(0)
	const playerTabs = [
		{ name: '推荐', type: '5' },
		{ name: '男生', type: '1' },
		{ name: '女生', type: '0' }
	]
	const playerCurrentTab = ref(0)
	const playerType = ref('5')
	const siteConfig = ref({})
	const orderBroadcastIndex = ref(0)
	const orderBroadcastPlayKey = ref(0)
	const orderBroadcastTimer = ref(null)
	const orderBroadcastRefreshTimer = ref(null)
	const orderBroadcastDisabled = ref(false)
	const orderBroadcastList = ref([])
	// 语音播放相关
	const audioContext = ref(null)
	const playingIndex = ref(-1)

	const currentOrderBroadcast = computed(() => orderBroadcastList.value[orderBroadcastIndex.value] || {})

	const normalizeOrderBroadcastList = (res) => {
		const raw = Array.isArray(res?.data) ? res.data : (Array.isArray(res?.data?.data) ? res.data.data : [])
		return raw.filter(item => item && item.title)
	}

	const loadOrderBroadcast = () => {
		if (orderBroadcastDisabled.value) {
			return Promise.resolve()
		}
		return orderBroadcast({ limit: 50 }).then(res => {
			const list = normalizeOrderBroadcastList(res)
			orderBroadcastList.value = list
			orderBroadcastDisabled.value = false
			if (!list.length || orderBroadcastIndex.value >= list.length) {
				orderBroadcastIndex.value = 0
			}
		}).catch(err => {
			orderBroadcastList.value = []
			orderBroadcastIndex.value = 0
			const statusCode = err?.statusCode || err?.status || err?.response?.status
			if (statusCode === 404) {
				orderBroadcastDisabled.value = true
				if (orderBroadcastRefreshTimer.value) {
					clearInterval(orderBroadcastRefreshTimer.value)
					orderBroadcastRefreshTimer.value = null
				}
			}
		})
	}

	const leftList = computed(() => dataList.value.filter((_, idx) => idx % 2 === 0))
	const rightList = computed(() => dataList.value.filter((_, idx) => idx % 2 === 1))

	const reloadPaging = () => {
		if (paging.value && paging.value.reload) {
			paging.value.reload()
		}
	}

	const completePaging = (list = []) => {
		if (paging.value && paging.value.complete) {
			paging.value.complete(list)
		}
	}

	const switchMode = (mode) => {
		pageMode.value = mode
		reloadPaging()
	}

	// 传世武器活动横幅点击
	const openCraft = () => {
		if (craftJump.value) { uni.navigateTo({ url: craftJump.value, fail: () => uni.showToast({ title: '活动即将开放', icon: 'none' }) }); return }
		uni.showToast({ title: '活动即将开放', icon: 'none' })
	}
	const openLottery = () => {
		if (lotteryJump.value) { uni.navigateTo({ url: lotteryJump.value, fail: () => uni.showToast({ title: '活动即将开放', icon: 'none' }) }); return }
		uni.showToast({ title: '活动即将开放', icon: 'none' })
	}

	// 传世武器入口配置：拉取
	const loadCraft = () => {
		fetch('/api/frontend/decoration', { cache: 'no-store' })
			.then(r => r.json())
			.then(res => {
				const d = (res && res.data) || {}
				craftMode.value = d.craft_mode || 'lottery'
				craftBanner.value = d.craft_banner || ''
				craftJump.value = d.craft_jump || ''
				lotteryBanner.value = d.lottery_banner || ''
				lotteryJump.value = d.lottery_jump || ''
			})
			.catch(() => {})
	}

	// 首页背景音乐：拉取配置
	const loadBgm = () => {
		fetch('/api/frontend/home_bgm', { cache: 'no-store' })
			.then(r => r.json())
			.then(res => {
				const d = (res && res.data) || {}
				if (String(d.enabled) === '0') { bgmEnabled.value = false; return }
				bgmTitle.value = d.title || '背景音乐'
				bgmSub.value = d.sub || '首页背景音乐 · 点击播放'
				bgmUrl.value = d.url || ''
				bgmEnabled.value = true
			})
			.catch(() => {})
	}

	// 首页背景音乐：播放/暂停
	const toggleBgm = () => {
		if (typeof Audio === 'undefined') return
		// 会员校验：V2 及以上可播放背景音乐
		if (!bgmPlaying.value && userLevelNum.value < 2) {
			uni.showToast({ title: '开通会员后可播放背景音乐', icon: 'none' })
			setTimeout(() => {
				uni.navigateTo({ url: '/pagesA/mine/level?identity=user' })
			}, 600)
			return
		}
		if (!bgmUrl.value) {
			uni.showToast({ title: '请先在后台上传背景音乐', icon: 'none' })
			return
		}
		if (!bgmAudio) {
			bgmAudio = new Audio(bgmUrl.value)
			bgmAudio.loop = true
			bgmAudio.onended = () => { bgmPlaying.value = false }
			bgmAudio.onpause = () => { bgmPlaying.value = false }
			bgmAudio.onplay = () => { bgmPlaying.value = true }
		}
		if (bgmPlaying.value) {
			bgmAudio.pause()
			bgmPlaying.value = false
		} else {
			bgmAudio.play().then(() => { bgmPlaying.value = true })
				.catch(() => { uni.showToast({ title: '播放失败，请检查音乐地址', icon: 'none' }) })
		}
	}

	onMounted(() => {
		// 三个接口并行请求，互不依赖
		lunbo().then(res => {
			lunboList.value = res.data
		}).catch(() => {})

		loadBgm()
		loadCraft()
		loadUserLevel()
		configure().then(res => {
			if (res.data) {
				siteConfig.value = res.data
			}
		}).catch(() => {})

		imageNavList().then(res => {
			imageNavs.value = Array.isArray(res.data) ? res.data : []
		}).catch(() => {
			imageNavs.value = []
		})

		loadOrderBroadcast()

		noticeList({
			type: '1',
			page: 1,
			limit: 10
		}).then(res => {
			if (res.data && res.data.data && res.data.data.length) {
				noticeListData.value = res.data.data.map(item => ({
					...item,
					barText: item.synopsis || item.desc || item.description || item.summary || item.intro || item.title || ''
				}))
				const item = res.data.data[0]
				notice.value = item.title
				noticeId.value = item.id
				noticeImage.value = item.image || ''
				noticeMsg({ id: item.id }).then(detailRes => {
					if (detailRes.data) {
						noticeContent.value = detailRes.data.content || ''
						noticeListData.value = noticeListData.value.map(noticeItem => noticeItem.id === item.id ? {
							...noticeItem,
							barText: detailRes.data.synopsis || detailRes.data.desc || detailRes.data.description || detailRes.data.summary || detailRes.data.intro || noticeItem.barText || noticeItem.title || ''
						} : noticeItem)
					}
				})
				// 核心逻辑：检查是否是路由返回
				// 如果 index_navigated_away 标记存在，说明是从其他页面返回，不显示弹窗
				const isNavigatedBack = !!uni.getStorageSync('index_navigated_away')
				if (!isNavigatedBack) {
					showNotice.value = true
				}
				// 读取完后立即清除标记
				uni.removeStorageSync('index_navigated_away')
			}
		}).catch(() => {})

		category().then(res => {
			categoryList.value = (res.data || []).slice(0, 8)
			if (res.data && res.data.length) {
				childrenList.value = [{
					id: '0',
					name: '全部'
				}].concat(res.data[current.value].children || [])
				one_category.value = res.data[current.value].id
			}
			reloadPaging()
		}).catch(() => {
			reloadPaging()
		})

		playerCategory().then(res => {
			playerCategoryList.value = res.data || []
		}).catch(() => {})

		// 静默初始化IM并注册用户（不阻塞页面）
		if (uni.getStorageSync('token')) {
			import('../../utils/im').then(({ ensureIMLogin }) => {
				ensureIMLogin().catch(() => {})
			}).catch(() => {})
		}
		// 初始化音频上下文
		audioContext.value = uni.createInnerAudioContext()
		orderBroadcastTimer.value = setInterval(() => {
			if (orderBroadcastList.value.length > 1) {
				orderBroadcastIndex.value = (orderBroadcastIndex.value + 1) % orderBroadcastList.value.length
			}
			if (orderBroadcastList.value.length) {
				orderBroadcastPlayKey.value += 1
			}
		}, 8000)
		orderBroadcastRefreshTimer.value = setInterval(() => {
			loadOrderBroadcast()
		}, 30000)
	})

	onUnmounted(() => {
		// 页面卸载时设置标记，表示用户已导航离开首页
		uni.setStorageSync('index_navigated_away', true)
		// 清理音频
		if (audioContext.value) {
			audioContext.value.stop()
			audioContext.value.destroy()
		}
		if (orderBroadcastTimer.value) {
			clearInterval(orderBroadcastTimer.value)
			orderBroadcastTimer.value = null
		}
		if (orderBroadcastRefreshTimer.value) {
			clearInterval(orderBroadcastRefreshTimer.value)
			orderBroadcastRefreshTimer.value = null
		}
	})

	const uploadBlobDirectly = (blobUrl) => {
		return new Promise(async (resolve, reject) => {
			try {
				const response = await fetch(blobUrl);
				const blob = await response.blob();
				const formData = new FormData();
				formData.append('file', blob, 'file_' + Date.now());
				const uploadResponse = await fetch('https://yuniop.xitifk.cn/api/common/upload', {
					method: 'POST',
					body: formData,
					headers: {}
				});
				if (uploadResponse.ok) {
					resolve(await uploadResponse.json());
				} else {
					reject(new Error(`上传失败，状态码: ${uploadResponse.status}`));
				}
			} catch (error) {
				reject(new Error(`上传失败: ${error.message}`));
			}
		});
	}

	// 每次显示首页时刷新会员等级（从会员页升级后返回可立即生效）
	onShow(() => {
		loadUserLevel()
	})

	const changeCategory = (index) => {
		current.value = index
		classCurrent.value = 0
		childrenList.value = [{
			id: '0',
			name: '全部'
		}].concat(categoryList.value[current.value].children)
		one_category.value = categoryList.value[current.value].id
		two_category.value = '0'
		reloadPaging()
	}

	const changeTag = (item, index) => {
		classCurrent.value = index
		one_category.value = categoryList.value[current.value].id
		two_category.value = item.id
		reloadPaging()
	}

	const toggleFilter = () => {
		showFilterMenu.value = !showFilterMenu.value
	}

	const toggleSearch = () => {
		showSearch.value = true
	}

	const closeSearch = () => {
		showSearch.value = false
	}

	const closeFilter = () => {
		showFilterMenu.value = false
	}

	const toggleGoodsColumns = () => {
		goodsColumns.value = goodsColumns.value === 3 ? 1 : goodsColumns.value + 1
	}

	const selectSort = (val) => {
		goodsFilterForm.value.sort = val
	}

	const resetFilter = () => {
		goodsFilterForm.value = {
			keyword: '',
			sort: ''
		}
		confirmFilter()
	}

	const confirmFilter = () => {
		applyFilter()
		closeFilter()
	}

	const applyFilter = () => {
		keyword.value = goodsFilterForm.value.keyword
		sort.value = goodsFilterForm.value.sort
		reloadPaging()
	}

	const searchConfirm = () => {
		keyword.value = goodsFilterForm.value.keyword
		reloadPaging()
		closeSearch()
	}

	const searchClear = () => {
		goodsFilterForm.value.keyword = ''
		keyword.value = ''
		reloadPaging()
	}

	const gotoPage = (url, data) => {
		router.navigateTo({
			url: url,
			data: {
				id: data
			}
		})
	}

	const gotoQuick = (type) => {
		if (type === 'process') {
			// 流程服务
			router.navigateTo({ url: 'pagesA/mine/agreement', data: { name: '流程服务' } })
		} else if (type === 'wallet') {
			// 我的钱包
			const token = uni.getStorageSync('token')
			if (!token) {
				router.navigateTo({ url: 'pages/login/login' })
				return
			}
			router.navigateTo({ url: 'pagesA/mine/wallet' })
		} else if (type === 'service') {
			// 联系客服
			toSeivice()
		} else if (type === 'recruit') {
			// 招募陪玩 -> 打手入驻
			const token = uni.getStorageSync('token')
			if (!token) {
				router.navigateTo({ url: 'pages/login/login' })
				return
			}
			router.navigateTo({ url: 'pagesA/mine/ruzhu' })
		}
	}

	const onSwiperChange = (e) => {
		// up-swiper change 事件返回当前索引
		currentBannerIndex.value = e.current || 0
	}

	const previewBanner = () => {
		if (!lunboList.value.length) return
		const urls = lunboList.value.map(item => item.image)
		uni.previewImage({
			current: urls[currentBannerIndex.value] || urls[0],
			urls
		})
	}

	const closeNotice = () => {
		showNotice.value = false
	}

	const openNotice = (item) => {
		if (!item) return
		notice.value = item.title
		noticeId.value = item.id
		noticeImage.value = item.image || ''
		noticeContent.value = item.barText || item.title || ''
		noticeMsg({ id: item.id }).then(detailRes => {
			if (detailRes && detailRes.data) {
				noticeContent.value = detailRes.data.content || detailRes.data.synopsis || detailRes.data.desc || item.barText || item.title || ''
			}
		}).catch(() => {})
		showNotice.value = true
	}

	const queryList = (pageNo, pageSize) => {
		if (pageMode.value === 'goods') {
			goods({
				page: pageNo,
				limit: pageSize,
				name: keyword.value,
				one_category: one_category.value,
				two_category: two_category.value,
				sort: sort.value
			}).then(res => {
			completePaging(res.data.data);
			})
		} else {
			thug({
				type: playerType.value,
				name: keyword.value,
				category_id: playerCurrentCategoryId.value,
				page: pageNo,
				limit: pageSize
			}).then(res => {
			completePaging(res.code == 1 ? res.data.data : []);
			}).catch(() => {
			completePaging([]);
			})
		}
	}

	const scrollTopChange = (res) => {
		if (res > 100) {
			bgColor.value = '#fff'
		} else {
			bgColor.value = 'transparent'
		}
	}

	const toSeivice = async () => {
		let r = { direct: null, list: [] }
		try {
			uni.showLoading({ mask: true, title: '加载中' })
			r = await resolveCustomerService()
		} catch (e) {
			r = { direct: null, list: [] }
		} finally {
			uni.hideLoading()
		}
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

	const openImageNav = (item) => {
		const link = (item && item.link_url ? String(item.link_url) : '').trim()
		if (!link) return

		// 外链：直接打开；非H5走中转页
		if (/^https?:\/\//i.test(link)) {
			// #ifdef H5
			window.location.href = link
			// #endif
			// #ifndef H5
			uni.navigateTo({
				url: `/pagesA/mine/agreement?name=${encodeURIComponent(item.title || '链接')}&url=${encodeURIComponent(link)}`
			})
			// #endif
			return
		}

		// 内链：兼容写法：pagesA/xxx 或 /pagesA/xxx
		const path = link.startsWith('/') ? link : `/${link}`
		uni.navigateTo({ url: path })
	}

	// 活动/投诉入口卡片点击：后台配置走链接，默认卡片走内置跳转
	const openNavCard = (item) => {
		if (item.action === 'tousu') {
			const token = uni.getStorageSync('token')
			if (!token) { router.navigateTo({ url: 'pages/login/login' }); return }
			router.navigateTo({ url: 'pagesA/mine/tousu' })
			return
		}
		if (item.action === 'activity') {
			if (craftMode.value === 'craft') { openCraft() } else { openLottery() }
			return
		}
		openImageNav(item)
	}

	const changePlayerCategory = (index) => {
		playerCategoryCurrent.value = index
		playerCurrentCategoryId.value = playerCategoryList.value[index]?.id || 0
		reloadPaging()
	}

	const changePlayerTab = (index) => {
		playerCurrentTab.value = index
		playerType.value = playerTabs[index].type
		reloadPaging()
	}

	const getPlayerSex = (item) => {
		return item && item.sex == '0' ? '/static/images/woman.png' : '/static/images/man.png'
	}

	const getPlayerLevel = (item) => {
		if (!item || item.type == null || item.type == undefined) {
			return { bg: 'background:#999999', title: '普通打手' }
		}
		if (item.type == 1) return { bg: 'background:linear-gradient(270deg,#D08A6A 0%,#C05B34 100%)', title: '钻石打手' }
		if (item.type == 2) return { bg: 'background:linear-gradient(270deg,#CDAE9A 0%,#A64A28 100%)', title: '大师打手' }
		if (item.type == 3) return { bg: 'background:linear-gradient(270deg,#C8A46A 0%,#8A6A3A 100%)', title: '顶尖打手' }
		return { bg: 'background:#999999', title: '普通打手' }
	}

	const getVipIcon = (item) => {
		let levelName = ''
		if (item.user_current_level_info && item.user_current_level_info.name) {
			levelName = item.user_current_level_info.name
		} else if (item.level_name) {
			levelName = item.level_name
		} else if (item.level) {
			levelName = String(item.level)
		}
		const match = levelName.match(/\d+/)
		const levelNum = match ? match[0] : 1
		return `/static/ym/v${levelNum}.png`
	}

	const goToPlayerDetail = (item) => {
		if (!item || !item.id) return
		router.navigateTo({
			url: 'pagesA/player/detail',
			data: { id: item.id }
		})
	}

	// 切换语音播放
	const togglePlayerAudio = (index, event) => {
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
</script>

<style lang="scss">
	page {
		background: transparent;
	}

	/* 快捷功能入口（轻量线性图标） */
	.quick-section {
		margin-top: 24rpx;
		padding: 0 20rpx;
		background: transparent;
	}

	.quick-grid {
		display: flex;
		flex-direction: row !important;
		justify-content: space-between;
	}

	.quick-item {
		flex: 1 1 0;
		min-width: 0;
		display: flex;
		flex-direction: column !important;
		align-items: center;
		gap: 12rpx;
	}

	.quick-icon {
		width: 88rpx;
		height: 88rpx;
		border-radius: 20rpx;
		background: #FFFFFF;
		border: 2rpx solid #EDEFF2;
		box-shadow: 0 2rpx 8rpx rgba(16, 24, 40, 0.04);
		display: flex;
		justify-content: center;
		align-items: center;
		transition: background 0.2s ease, border-color 0.2s ease;
	}

	.quick-item:active .quick-icon {
		background: #F2F3F5;
		border-color: #E1E4E9;
	}

	.quick-svg {
		width: 46rpx;
		height: 46rpx;
		color: #3A4050;
	}

	.quick-name {
		font-size: 22rpx;
		color: #4B5563;
	}

	.order-broadcast {
		position: fixed;
		top: 50%;
		left: 22rpx;
		z-index: 999;
		width: auto;
		max-width: 650rpx;
		min-width: 0;
		height: 64rpx;
		padding: 6rpx 24rpx 6rpx 8rpx;
		box-sizing: border-box;
		border-radius: 999rpx;
		background: linear-gradient(90deg, rgba(43, 48, 59, 0.92) 0%, rgba(122, 68, 44, 0.9) 55%, rgba(192, 91, 52, 0.94) 100%);
		box-shadow: 0 12rpx 28rpx rgba(43, 48, 59, 0.26);
		transform: translate3d(0, -50%, 0);
		display: flex;
		align-items: center;
		pointer-events: none;
		overflow: hidden;
		animation: orderBroadcastFade 3.2s linear both;
		will-change: transform, opacity;
	}

	.order-broadcast__avatar {
		width: 52rpx;
		height: 52rpx;
		border-radius: 50%;
		flex-shrink: 0;
		border: 3rpx solid rgba(255, 255, 255, 0.82);
		background: #ffffff;
	}

	.order-broadcast__text-wrap {
		flex: 1;
		min-width: 0;
		margin-left: 14rpx;
		overflow: hidden;
	}

	.order-broadcast__text {
		display: block;
		font-size: 24rpx;
		line-height: 52rpx;
		font-weight: 700;
		color: #ffffff;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		text-shadow: 0 2rpx 6rpx rgba(86, 37, 13, 0.35);
	}

	@keyframes orderBroadcastFade {
		0% {
			opacity: 0;
			transform: translate3d(0, -50%, 0) scale(1);
		}
		10% {
			opacity: 1;
			transform: translate3d(0, -76%, 0) scale(1);
		}
		72% {
			opacity: 0.34;
			transform: translate3d(0, -236%, 0) scale(1);
		}
		100% {
			opacity: 0;
			transform: translate3d(0, -320%, 0) scale(1);
		}
	}

	.page {
		background: transparent;
		min-height: 100vh;
		width: 100%;
		overflow-x: hidden;
		box-sizing: border-box;
		position: relative;
	}

	.page::before {
		content: '';
		position: absolute;
		top: 0;
		left: 0;
		right: 0;
		height: 760rpx;
		background: linear-gradient(180deg, #F4F5F7 0%, #FFFFFF 100%);
		z-index: 0;
		pointer-events: none;
	}

	.box {
		min-height: 100%;
		width: 100%;
		margin: 0;
		color: #000;
		box-sizing: border-box;
		padding-bottom: 50rpx;
		position: relative;
		z-index: 1;
	}

	.box::before {
		display: none;
	}

	.box-bg {
		position: absolute;
		top: 0;
		left: 0;
		width: 100%;
		height: 600rpx;
		z-index: 0;
		pointer-events: none;
		display: none;
	}

	.rlp20 {
		padding: 0 20rpx;
	}

	/* 顶部：搜索栏 + 俱乐部Logo */
	.header-content {
		position: relative;
		z-index: 10;
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 20rpx;
		padding: 16rpx 24rpx 20rpx;
		/* #ifdef MP-WEIXIN */
		padding-top: calc(var(--status-bar-height, 20px) + 16rpx);
		/* #endif */
	}

	.index-search {
		flex: 1;
		min-width: 0;
		height: 76rpx;
		display: flex;
		align-items: center;
		gap: 14rpx;
		padding: 0 28rpx;
		background: #ffffff;
		border-radius: 999rpx;
		box-shadow: 0 4rpx 16rpx rgba(16, 24, 40, 0.06);
	}

	.index-search__icon {
		width: 34rpx;
		height: 34rpx;
		flex-shrink: 0;
		opacity: 0.55;
	}

	.index-search__ph {
		font-size: 26rpx;
		color: #b7b7b7;
	}

	.club-brand {
		display: flex;
		flex-direction: column;
		align-items: center;
		flex-shrink: 0;
		width: 108rpx;
	}

	.club-brand__logo {
		width: 64rpx;
		height: 64rpx;
		border-radius: 50%;
		background: #ffffff;
		box-shadow: 0 4rpx 12rpx rgba(16, 24, 40, 0.08);
	}

	.club-brand__name {
		margin-top: 6rpx;
		font-size: 20rpx;
		color: #333333;
		max-width: 108rpx;
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
		text-align: center;
	}

	/* 搜索弹窗 */
	.search-popup-mask {
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		background: rgba(0, 0, 0, 0.5);
		z-index: 9999;
	}

	.search-popup {
		background: #fff;
		padding: 24rpx;
		padding-top: calc(var(--status-bar-height, 20px) + 24rpx);
	}

	.search-popup-header {
		display: flex;
		align-items: center;
		gap: 20rpx;
	}

	.search-popup-input-wrap {
		flex: 1;
		height: 96rpx;
		padding: 10rpx 32rpx;
		border-radius: 48rpx;
		background: #f5f6f9;
		display: flex;
		align-items: center;
		box-sizing: border-box;
	}

	.search-popup-icon {
		width: 32rpx;
		height: 32rpx;
		margin-right: 12rpx;
		flex-shrink: 0;
	}

	.search-popup-input {
		flex: 1;
		height: 76rpx;
		line-height: normal;
		font-size: 26rpx;
		color: #000;
		background: transparent;
	}

	.search-placeholder {
		font-size: 26rpx;
		color: #999;
	}

	.search-popup-cancel {
		font-size: 28rpx;
		color: #666;
		flex-shrink: 0;
	}

	/* 轮播图 */
	.swiper-view {
		width: 100%;           /* 右边距：30rpx */        
		box-sizing: border-box;
		position: relative;
		background: transparent;
	}

	.single-banner {
		width: 100%;
		height: 340rpx;
		border-radius: 20rpx;
		display: block;
	}

	/* 公告栏：浅灰底 + 喇叭图标 + 关闭按钮 */
	.notice-bar {
		margin: 16rpx 20rpx 0 20rpx;
		width: calc(100% - 40rpx);
		min-height: 68rpx;
		background: #F2F3F5;
		border-radius: 24rpx;
		display: flex;
		align-items: center;
		padding: 0 8rpx 0 20rpx;
		box-sizing: border-box;
	}

	.notice-bar__main {
		flex: 1;
		min-width: 0;
		display: flex;
		align-items: center;
		gap: 12rpx;
		height: 68rpx;
	}

	.notice-bar__icon {
		width: 32rpx;
		height: 32rpx;
		flex-shrink: 0;
		color: #9AA0A6;
	}

	.notice-bar__text {
		flex: 1;
		min-width: 0;
		font-size: 24rpx;
		color: #6B7280;
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
	}

	.notice-bar__close {
		width: 56rpx;
		height: 56rpx;
		flex-shrink: 0;
		display: flex;
		align-items: center;
		justify-content: center;
		color: #B4B8BE;
	}

	.notice-bar__close svg {
		width: 26rpx;
		height: 26rpx;
	}

	/* 去掉swiper默认白色背景 */
	.swiper-view :deep(.u-swiper) {
		background: transparent !important;
	}

	.swiper-view :deep(.u-swiper__wrapper) {
		background: transparent !important;
	}

	.swiper-view :deep(.u-swiper-indicator) {
		background: transparent !important;
		bottom: 16rpx !important;
	}

	.swiper-view :deep(.u-swiper-indicator__dot) {
		width: 16rpx !important;
		height: 16rpx !important;
		border-radius: 50% !important;
		background-color: rgba(255, 255, 255, 0.4) !important;
		margin: 0 8rpx !important;
		border: 2rpx solid rgba(255, 255, 255, 0.6) !important;
	}

	.swiper-view :deep(.u-swiper-indicator__dot--active) {
		background-color: #ffffff !important;
		border-color: #ffffff !important;
	}

	/* 公告弹窗 */
	.notice-popup-mask {
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		background: rgba(0, 0, 0, 0.45);
		z-index: 9999;
		display: flex;
		align-items: center;
		justify-content: center;
		padding: 40rpx;
	}

	.notice-popup {
		width: 100%;
		max-width: 560rpx;
		background: 
			linear-gradient(160deg, 
				#fcfaff 0%, 
				#fdf2f8 15%,
				#f2f5ff 35%,
				#edf0fc 55%,
				#f7f0ff 75%,
				#fdf4f9 100%
			);
		border-radius: 32rpx;
		position: relative;
		max-height: 70vh;
		display: flex;
		flex-direction: column;
		padding-bottom: 28rpx;
		border: 2rpx solid rgba(255,255,255,0.65);
		box-shadow: 
			0 20rpx 50rpx rgba(180,140,200,0.18),
			0 8rpx 20rpx rgba(160,140,200,0.10),
			0 2rpx 6rpx rgba(0,0,0,0.04),
			inset 0 1rpx 0 rgba(255,255,255,0.8),
			inset 0 2rpx 6rpx rgba(255,255,255,0.35);
	}
	
	/* 果冻高光条 */
	.notice-popup::after {
		content: '';
		position: absolute;
		top: 8rpx;
		left: 50%;
		transform: translateX(-50%);
		width: 60%;
		height: 6rpx;
		background: linear-gradient(90deg, 
			transparent 0%, 
			rgba(255,255,255,0.7) 30%, 
			rgba(255,255,255,0.9) 50%, 
			rgba(255,255,255,0.7) 70%, 
			transparent 100%
		);
		border-radius: 6rpx;
		pointer-events: none;
		z-index: 2;
	}

	.notice-popup-header {
		text-align: center;
		padding: 24rpx;
	}

	.anno-title {
		font-size: 28rpx;
		font-weight: 600;
		color: #2B303B;
	}

	.notice-popup-body {
		max-height: 45vh;
		overflow-y: auto;
		padding: 24rpx;
	}

	.anno-cover {
		display: block;
		width: 100%;
		max-width: 100%;
		max-height: 200rpx;
		border-radius: 20rpx;
		margin-top: 16rpx;
	}

	.anno-label {
		font-size: 26rpx;
		font-weight: 600;
		color: #2B303B;
		line-height: 1.5;
		text-align: center;
		display: block;
	}

	.anno-content {
		font-size: 26rpx;
		color: #5B6472;
		line-height: 1.6;
		margin-top: 4rpx;
		word-break: break-all;
	}

	.notice-popup-close {
		position: absolute;
		bottom: -28rpx;
		left: 50%;
		transform: translateX(-50%);
		width: 56rpx;
		height: 56rpx;
		background: 
			linear-gradient(160deg, 
				rgba(255,255,255,0.75) 0%, 
				rgba(255,235,245,0.70) 30%,
				rgba(240,235,255,0.65) 60%,
				rgba(255,240,250,0.68) 100%
			);
		backdrop-filter: blur(20rpx);
		-webkit-backdrop-filter: blur(20rpx);
		border-radius: 56rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		border: 2rpx solid rgba(255,255,255,0.6);
		box-shadow: 
			0 8rpx 24rpx rgba(180,150,200,0.20),
			0 3rpx 8rpx rgba(160,140,180,0.12),
			inset 0 1rpx 0 rgba(255,255,255,0.7),
			inset 0 2rpx 4rpx rgba(255,255,255,0.3);
	}

	.close-icon {
		font-size: 36rpx;
		color: rgba(120,100,140,0.75);
		line-height: 1;
		font-weight: 500;
		text-shadow: 0 1rpx 1rpx rgba(255,255,255,0.5);
	}

	/* 三大功能入口卡片 */
	.entry-tiles {
		margin-top: 24rpx;
	}

	.entry-tiles__row {
		display: flex !important;
		gap: 18rpx;
	}

	.entry-tile {
		position: relative;
		flex: 1 1 0;
		min-width: 0;
		min-height: 176rpx;
		box-sizing: border-box;
		display: flex;
		flex-direction: column;
		align-items: flex-start;
		padding: 24rpx 22rpx;
		border-radius: 24rpx;
		background: linear-gradient(150deg, #F8D14E 0%, #EEB63A 100%);
		overflow: hidden;
		transition: transform 0.2s ease;
	}

	.entry-tile:active {
		transform: scale(0.97);
	}

	.entry-tile__img {
		position: absolute;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		display: block;
	}

	.entry-tile__icon {
		width: 48rpx;
		height: 48rpx;
		color: #FFFFFF;
		filter: drop-shadow(0 1rpx 2rpx rgba(180, 120, 10, 0.35));
	}

	.entry-tile__icon svg {
		width: 100%;
		height: 100%;
		display: block;
	}

	.entry-tile__title {
		margin-top: 20rpx;
		font-size: 26rpx;
		font-weight: 600;
		color: #5C3A10;
		line-height: 1.3;
	}

	.entry-tile__sub {
		margin-top: 6rpx;
		font-size: 20rpx;
		color: rgba(92, 58, 16, 0.7);
		line-height: 1.3;
	}

	/* 活动横幅（传世武器 / 抽奖） */
	.weapon-banner {
		margin-top: 24rpx;
	}
	.weapon-banner__img {
		width: 100%;
		height: 132rpx;
		border-radius: 24rpx;
		display: block;
	}
	.weapon-banner__content {
		display: flex;
		align-items: center;
		gap: 20rpx;
		min-height: 132rpx;
		box-sizing: border-box;
		padding: 20rpx 26rpx;
		border-radius: 24rpx;
		overflow: hidden;
		position: relative;
		background: linear-gradient(120deg, #2E333D 0%, #3B414D 100%);
	}
	.weapon-banner__art {
		width: 108rpx;
		height: 92rpx;
		flex: 0 0 108rpx;
		color: rgba(224, 138, 95, 0.85);
	}
	.weapon-banner__text {
		flex: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;
		gap: 6rpx;
		z-index: 2;
	}
	.weapon-banner__title {
		font-size: 30rpx;
		font-weight: 600;
		color: #FFFFFF;
		letter-spacing: 1rpx;
	}
	.weapon-banner__sub {
		font-size: 22rpx;
		color: rgba(255, 255, 255, 0.58);
	}
	.weapon-banner__go {
		flex: 0 0 auto;
		padding: 12rpx 28rpx;
		border-radius: 16rpx;
		background: #C05B34;
		display: flex;
		align-items: center;
		justify-content: center;
		z-index: 2;
	}
	.weapon-banner__go-text {
		font-size: 24rpx;
		font-weight: 600;
		color: #FFFFFF;
	}

	/* 首页背景音乐（轻量横条） */
	.bgm-bar {
		display: flex;
		align-items: center;
		gap: 16rpx;
		margin-top: 24rpx;
		min-height: 76rpx;
		box-sizing: border-box;
		padding: 14rpx 22rpx;
		background: #F6F7F9;
		border-radius: 16rpx;
	}
	.bgm-icon {
		width: 52rpx;
		height: 52rpx;
		border-radius: 14rpx;
		background: #EAECEF;
		color: #5B6472;
		display: flex;
		align-items: center;
		justify-content: center;
		flex: 0 0 52rpx;
	}
	.bgm-icon svg {
		width: 30rpx;
		height: 30rpx;
	}
	.bgm-info {
		flex: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;
		gap: 4rpx;
	}
	.bgm-title {
		font-size: 26rpx;
		font-weight: 500;
		color: #2B303B;
	}
	.bgm-sub {
		font-size: 20rpx;
		color: #8A8F98;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.bgm-btn {
		flex: 0 0 auto;
		padding: 8rpx 26rpx;
		border-radius: 16rpx;
		background: #FFFFFF;
		border: 2rpx solid #E3E5E9;
	}
	.bgm-btn-playing {
		background: rgba(192, 91, 52, 0.10);
		border-color: rgba(192, 91, 52, 0.28);
	}
	.bgm-btn-text {
		font-size: 22rpx;
		font-weight: 500;
		color: #4B5563;
	}
	.bgm-btn-playing .bgm-btn-text {
		color: #B0522E;
	}

	/* 一级分类 */
	.category-section {
		width: 100%;
		padding: 0rpx 14rpx;
		box-sizing: border-box;
		margin-top: 28rpx;
	}

	.category-grid {
		display: flex;
		flex-wrap: wrap;
		gap: 10rpx;
	}

	.category-item {
		display: flex;
		flex-direction: column;
		align-items: center;
		width: calc((100% - 40rpx) / 5);
		flex-shrink: 0;
		flex-grow: 0;
	}

	/* 图标容器 */
	.category-icon-wrap {
		position: relative;
		width: 78rpx;
		height: 78rpx;
		border-radius: 18rpx;
		overflow: hidden;
		background: transparent;
		display: flex;
		align-items: center;
		justify-content: center;
		transition: color 0.25s ease;
	}

	.category-icon-svg {
		width: 52rpx;
		height: 52rpx;
		color: #7A8291;
		transition: color 0.25s ease;
	}

	.category-item-active .category-icon-svg {
		color: #C05B34;
	}

	.category-icon {
		width: 100%;
		height: 100%;
		display: block;
	}

	/* 分类名：选中态仅文字变色 + 底部细线下划线 */
	.category-name {
		position: relative;
		margin-top: 8rpx;
		max-width: 100%;
		box-sizing: border-box;
	}

	.category-name-text {
		display: block;
		font-size: 22rpx;
		color: #3A4050;
		text-align: center;
		white-space: nowrap;
		line-height: 1.2;
		transition: color 0.25s ease;
	}

	.category-name-active .category-name-text {
		color: #C05B34;
		font-weight: 600;
	}

	.category-underline {
		margin-top: 6rpx;
		width: 36rpx;
		height: 4rpx;
		border-radius: 4rpx;
		background: transparent;
		transition: background 0.25s ease;
	}

	.category-underline.show {
		background: #C05B34;
	}

	/* 二级分类 + 筛选 */
	.goods_classify_box {
		width: 100%;
		margin-top: 10rpx;
		margin-left: 10rpx;
		position: relative;
	}

	.goods_classify_box .scroll-view {
		width: calc(100% - 80rpx);
		overflow: visible !important;
		padding: 15rpx 0;
		margin: -15rpx 0;
		white-space: nowrap;
	}

	.goods_classify_box .scroll-view::-webkit-scrollbar {
		display: none;
		width: 0;
		height: 0;
		color: transparent;
		background: transparent;
	}

	.goods_classify_box .goods_classify_list {
		display: flex;
		align-items: center;
		margin-left: 20rpx;
		margin-right: 20rpx;
		padding: 10rpx 0;
		flex-wrap: nowrap;
	}

	.goods_classify_box .classify_item {
		display: flex;
		flex-direction: column;
		height: auto;
		min-width: auto;
		width: auto;
		padding: 8rpx 22rpx;
		justify-content: center;
		align-items: center;
		color: #333333;
		margin-right: 16rpx;
		font-size: 24rpx;
		white-space: nowrap;
		transition: all 0.3s ease;
		position: relative;
		flex-shrink: 0;
		box-sizing: border-box;
		border-radius: 32rpx;
		background: #ffffff;
		box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.06);
		overflow: visible;
	}

	.goods_classify_box .classify_item::after {
		display: none;
	}

	.goods_classify_box .placeholder {
		background: rgba(0, 0, 0, 0);
		visibility: hidden;
		box-shadow: none;
	}

	.goods_classify_box .placeholder::after {
		display: none;
	}

	.goods_classify_box .select {
		color: #ffffff;
		font-weight: 500;
		background: linear-gradient(135deg, #C05B34 0%, #A64A28 100%);
		border: none;
		box-shadow: 0 4rpx 14rpx rgba(192, 91, 52, 0.3);
		transform: none;
		border-radius: 32rpx;
	}

	.goods_classify_box .select::after {
		display: none;
	}

	.goods_classify_box .classify_text {
		position: relative;
	}

	.goods_classify_box .classify_shadow {
		display: none;
	}

	.goods_classify_box .classify_fixed_button {
		position: absolute;
		right: -10rpx;
		top: 50%;
		transform: translateY(-50%);
		height: 62rpx;
		width: 62rpx;
		line-height: 62rpx;
		padding: 0;
		background: linear-gradient(135deg, #C05B34 0%, #A64A28 100%);
		border: 1rpx solid rgba(255, 255, 255, .8);
		box-shadow: 0 4rpx 14rpx rgba(192, 91, 52, 0.25);
		color: #ffffff;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		z-index: 10;
		text-align: center;
		white-space: nowrap;
	}

	.goods_classify_box .goods-columns-text {
		font-size: 20rpx;
		font-weight: 600;
		line-height: 1;
	}

	.goods_classify_box .filter-dropdown-wrapper {
		position: absolute;
		right: 20rpx;
		top: 50%;
		transform: translateY(-50%);
		z-index: 100;
	}

	.goods_classify_box .dropdown-menu {
		position: absolute;
		top: 60rpx;
		right: 0;
		width: 600rpx;
		background: #fff;
		border-radius: 12rpx;
		box-shadow: 0 8rpx 32rpx rgba(0, 0, 0, .15);
		z-index: 101;
		border: 1rpx solid #e8e8e8;
		animation: dropdownSlide .2s ease;
	}

	.goods_classify_box .dropdown-menu:before {
		content: "";
		position: absolute;
		top: -8rpx;
		right: 20rpx;
		width: 16rpx;
		height: 16rpx;
		background: #fff;
		transform: rotate(45deg);
		border-left: 1rpx solid #e8e8e8;
		border-top: 1rpx solid #e8e8e8;
	}

	@keyframes dropdownSlide {
		0% {
			opacity: 0;
			transform: translateY(-10rpx);
		}

		to {
			opacity: 1;
			transform: translateY(0);
		}
	}

	.goods_classify_box .dropdown-content {
		padding: 24rpx;
	}

	.goods_classify_box .dropdown-item {
		margin-bottom: 24rpx;
	}

	.goods_classify_box .dropdown-item .item-label {
		font-size: 24rpx;
		color: #666;
		margin-bottom: 12rpx;
		font-weight: 500;
	}

	.goods_classify_box .sort-buttons {
		display: flex;
		flex-wrap: wrap;
		gap: 12rpx;
	}

	.goods_classify_box .sort-buttons .sort-btn {
		flex: 1;
		min-width: 0;
		padding: 16rpx 12rpx;
		background: #f8f9fa;
		border-radius: 8rpx;
		font-size: 22rpx;
		color: #666;
		text-align: center;
		transition: all .3s ease;
		border: 1rpx solid transparent;
		white-space: nowrap;
	}

	.goods_classify_box .sort-buttons .sort-btn.sort-btn-active {
		background: #333;
		color: #fff;
		border-color: #333;
		font-weight: 500;
	}

	.goods_classify_box .dropdown-actions {
		display: flex;
		gap: 16rpx;
		margin-top: 16rpx;
		padding-top: 16rpx;
		border-top: 1rpx solid #f0f0f0;
	}

	.goods_classify_box .dropdown-actions .action-btn {
		flex: 1;
		padding: 16rpx;
		text-align: center;
		border-radius: 8rpx;
		font-size: 24rpx;
		font-weight: 500;
	}

	.goods_classify_box .dropdown-actions .action-btn.reset {
		background: #f8f9fa;
		color: #666;
		border: 1rpx solid #e8e8e8;
	}

	.goods_classify_box .dropdown-actions .action-btn.confirm {
		background: #333;
		color: #fff;
	}

	.goods_classify_box .dropdown-mask {
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		background: transparent;
		z-index: 99;
	}

	/* 商品列表 */
	.waterfall-area {
		padding: 0 10rpx;
		background: #ffffff;
		border-radius: 24rpx 24rpx 0 0;
		margin-top: 20rpx;
		padding-top: 10rpx;
	}

	.goods-list {
		display: flex;
		flex-wrap: wrap;
		gap: 10rpx;
		padding: 0 6rpx;
	}

	.goods-list .goods-item {
		position: relative;
		width: calc((100% - 20rpx) / 3);
		overflow: visible;
		margin-top: 10rpx;
		border-radius: 24rpx;
		flex-shrink: 0;
		background: #fff;
		box-shadow:
			0 4rpx 16rpx rgba(0, 0, 0, 0.08),
			0 1rpx 4rpx rgba(0, 0, 0, 0.04),
			inset 0 2rpx 4rpx rgba(255, 255, 255, 0.8),
			inset 0 -1rpx 3rpx rgba(0, 0, 0, 0.05);
		transition: all 0.3s ease;
	}

	.goods-list.goods-list--cols-1 .goods-item {
		width: 100%;
		height: 176rpx;
		display: flex;
		align-items: center;
		padding: 0 72rpx 0 0;
		box-sizing: border-box;
		border-radius: 18rpx;
		overflow: hidden;
	}

	.goods-list.goods-list--cols-1 .goods-item::after {
		display: none;
	}

	.goods-list.goods-list--cols-1 .goods-item__image {
		width: 176rpx;
		height: 176rpx;
		border-radius: 18rpx 0 0 18rpx;
		overflow: hidden;
		flex-shrink: 0;
		box-sizing: border-box;
	}

	.goods-list.goods-list--cols-1 .goods-item__image image {
		width: 100%;
		height: 100%;
		border-radius: 18rpx 0 0 18rpx;
	}

	.goods-list.goods-list--cols-1 .goods-item__ft {
		flex: 1;
		min-width: 0;
		height: 100%;
		padding: 16rpx 0 16rpx 24rpx;
		border-radius: 0;
		box-sizing: border-box;
		display: flex;
		flex-direction: column;
		justify-content: space-between;
	}

	.goods-list.goods-list--cols-1 .goods-item__ft__title {
		line-height: 34rpx;
	}

	.goods-list.goods-list--cols-1 .goods-item__ft__title .value {
		font-size: 30rpx;
		margin-left: 0;
	}

	.goods-list.goods-list--cols-1 .goods-item__ft__desc {
		margin-top: 6rpx;
		line-height: 28rpx;
	}

	.goods-list.goods-list--cols-1 .goods-item__ft__desc .value {
		font-size: 22rpx;
		color: #333333;
	}

	.goods-list.goods-list--cols-1 .goods-item__ft__price {
		margin-top: 0;
	}

	.goods-list.goods-list--cols-1 .goods-item__ft__price .start {
		font-size: 24rpx;
	}

	.goods-list.goods-list--cols-1 .goods-item__ft__price .value {
		font-size: 34rpx;
		color: var(--theme-price, #C05B34);
	}

	.goods-list.goods-list--cols-1 .goods-item__ft__price .list_price,
	.goods-list.goods-list--cols-1 .goods-item__ft__price .sale {
		display: none;
	}

	.goods-list.goods-list--cols-1 .goods-item__cart {
		position: absolute;
		right: 24rpx;
		bottom: 18rpx;
		width: 44rpx;
		height: 44rpx;
		border-radius: 50%;
		background: linear-gradient(135deg, #C05B34 0%, #A64A28 100%);
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.goods-list.goods-list--cols-1 .goods-item__cart-icon {
		width: 26rpx;
		height: 26rpx;
		color: #ffffff;
		display: block;
	}

	.goods-list.goods-list--cols-2 .goods-item {
		width: calc((100% - 10rpx) / 2);
	}

	.goods-list.goods-list--cols-3 .goods-item {
		width: calc((100% - 20rpx) / 3);
	}

	.goods-list .goods-item::after {
		content: '';
		position: absolute;
		top: 3rpx;
		left: 6rpx;
		right: 6rpx;
		height: 30%;
		border-radius: 18rpx;
		background: linear-gradient(180deg,
			rgba(255, 255, 255, 0.5) 0%,
			rgba(255, 255, 255, 0.1) 60%,
			rgba(255, 255, 255, 0) 100%
		);
		pointer-events: none;
		z-index: 2;
	}

	.goods-list .goods-item:active {
		transform: scale(0.98);
	}

	.goods-list .goods-item.goods-item-anim {
		opacity: 0;
		animation: goods-item-in .5s ease-out forwards;
	}

	@keyframes goods-item-in {
		0% {
			opacity: 0;
			transform: translateY(24rpx);
		}

		to {
			opacity: 1;
			transform: translateY(0);
		}
	}

	.goods-list .goods-item__image image {
		width: 100%;
		display: block;
		border-radius: 24rpx 24rpx 0 0;
	}

	.goods-list .goods-item__ft {
		padding: 16rpx;
		background: #fff;
		border-radius: 0 0 24rpx 24rpx;
	}

	.goods-list .goods-item__ft__title {
		line-height: 28rpx;
		display: flex;
	}

	.goods-list .goods-item__ft__title .value {
		margin-left: 6rpx;
		font-size: 24rpx;
		color: #000;
		font-weight: 600;
		font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
		align-items: center;
	}

	.goods-list .goods-item__ft__desc {
		margin-top: 3rpx;
		line-height: 1.4;
	}

	.goods-list .goods-item__ft__desc .value {
		font-size: 22rpx;
		color: #343536;
		font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
	}

	.goods-list .goods-item__ft__price {
		display: flex;
		align-items: baseline;
		flex-wrap: wrap;
		margin-top: 8rpx;
		overflow: hidden;
	}

	.goods-list .goods-item__ft__price .start {
		font-size: 22rpx;
		color: var(--theme-price, #C05B34);
		font-weight: 600;
		font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
		flex-shrink: 0;
	}

	.goods-list .goods-item__ft__price .value {
		font-size: 26rpx;
		color: var(--theme-price, #C05B34);
		font-weight: 600;
		font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
		flex-shrink: 0;
	}

	.goods-list .goods-item__ft__price .list_price {
		color: gray;
		text-decoration: line-through;
		font-size: 20rpx;
		margin-left: 8rpx;
		flex-shrink: 1;
		min-width: 0;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
	}

	.goods-list .goods-item__ft__price .sale {
		color: gray;
		font-size: 18rpx;
		white-space: nowrap;
		flex-shrink: 0;
		margin-left: auto;
		font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
	}

	/* 文本截断 */
	.uv-line-1 {
		display: -webkit-box !important;
		overflow: hidden;
		text-overflow: ellipsis;
		word-break: break-all;
		-webkit-line-clamp: 1;
		-webkit-box-orient: vertical !important;
	}

	.uv-line-2 {
		display: -webkit-box !important;
		overflow: hidden;
		text-overflow: ellipsis;
		word-break: break-all;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical !important;
	}

	/* 指示器 */
	.indicator {
		display: flex;
		flex-direction: row;
		justify-content: center;

		&__dot {
			height: 6px;
			width: 6px;
			border-radius: 100px;
			background-color: rgba(255, 255, 255, 0.35);
			margin: 0 5px;
			transition: background-color 0.3s;

			&--active {
				background-color: #ffffff;
			}
		}
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

	/* 模式切换标签 */
	.mode-tabs {
		display: flex;
		padding: 0 30rpx;
		margin-bottom: 16rpx;
	}

	.mode-tab {
		font-size: 28rpx;
		color: rgba(255,255,255,0.6);
		padding: 8rpx 0;
		margin-right: 40rpx;
		position: relative;
	}

	.mode-tab-active {
		color: #fff;
		font-weight: 600;
	}

	.mode-tab-active::after {
		content: '';
		position: absolute;
		bottom: 0;
		left: 0;
		right: 0;
		height: 4rpx;
		background: #fff;
		border-radius: 2rpx;
	}

	/* 打手专区 */
	.player-section {
		padding: 0 20rpx;
	}

	.player-category-section {
		margin-top: 10rpx;
	}

	.player-category-grid {
		display: flex;
		flex-wrap: wrap;
		gap: 16rpx;
	}

	.player-category-item {
		display: flex;
		flex-direction: column;
		align-items: center;
		width: calc((100% - 48rpx) / 4);
		padding: 16rpx 0;
	}

	.player-category-icon-wrap {
		position: relative;
		width: 100rpx;
		height: 100rpx;
		border-radius: 24rpx;
		overflow: hidden;
		background: linear-gradient(135deg, #f8f9fc 0%, #edf0f5 100%);
		box-shadow:
			0 3rpx 10rpx rgba(0, 0, 0, 0.08),
			inset 0 2rpx 4rpx rgba(255, 255, 255, 0.8),
			inset 0 -1rpx 3rpx rgba(0, 0, 0, 0.06);
	}

	.player-category-icon-wrap::after {
		content: '';
		position: absolute;
		top: 3rpx;
		left: 6rpx;
		right: 6rpx;
		height: 35%;
		border-radius: 24rpx;
		background: linear-gradient(180deg,
			rgba(255, 255, 255, 0.5) 0%,
			rgba(255, 255, 255, 0.1) 60%,
			rgba(255, 255, 255, 0) 100%
		);
		pointer-events: none;
		z-index: 2;
	}

	.player-category-icon-wrap-active {
		box-shadow:
			0 4rpx 16rpx rgba(192, 91, 52, 0.3),
			inset 0 2rpx 4rpx rgba(255, 255, 255, 0.6),
			inset 0 -2rpx 4rpx rgba(0, 0, 0, 0.08);
	}

	.player-category-icon {
		width: 100%;
		height: 100%;
	}

	.player-category-name {
		font-size: 22rpx;
		color: #ffffff;
		margin-top: 8rpx;
	}

	.player-category-name-active {
		font-weight: bold;
	}

	.player-tabs {
		display: flex;
		margin: 20rpx 0;
		gap: 20rpx;
	}

	.player-tab-item {
		position: relative;
		font-size: 26rpx;
		color: #666;
		padding: 10rpx 28rpx;
		background: linear-gradient(135deg, #f8f9fc 0%, #edf0f5 100%);
		border-radius: 32rpx;
		box-shadow:
			0 3rpx 10rpx rgba(0, 0, 0, 0.06),
			inset 0 2rpx 4rpx rgba(255, 255, 255, 0.8),
			inset 0 -1rpx 3rpx rgba(0, 0, 0, 0.06);
		transition: all 0.3s ease;
		overflow: visible;
	}

	.player-tab-item::after {
		content: '';
		position: absolute;
		top: 3rpx;
		left: 10rpx;
		right: 10rpx;
		height: 35%;
		border-radius: 32rpx;
		background: linear-gradient(180deg,
			rgba(255, 255, 255, 0.5) 0%,
			rgba(255, 255, 255, 0.1) 60%,
			rgba(255, 255, 255, 0) 100%
		);
		pointer-events: none;
		z-index: 0;
	}

	.player-tab-active {
		color: #fff;
		background: linear-gradient(180deg, #C05B34 0%, #A64A28 100%);
		box-shadow:
			0 4rpx 16rpx rgba(192, 91, 52, 0.35),
			0 0 0 1rpx rgba(255, 255, 255, 0.2) inset,
			inset 0 2rpx 6rpx rgba(255, 255, 255, 0.45),
			inset 0 -2rpx 6rpx rgba(0, 0, 0, 0.08);
	}

	.player-tab-active::after {
		content: '';
		position: absolute;
		top: 3rpx;
		left: 10rpx;
		right: 10rpx;
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

	.player-list {
		display: flex;
		flex-direction: column;
		gap: 16rpx;
	}

	.player-card {
		background: linear-gradient(135deg, #ffffff 0%, #fff5ef 100%);
		position: relative;
		padding: 0rpx;
		overflow: visible;
		box-shadow:
			0 6rpx 24rpx rgba(192, 91, 52, 0.10),
			0 2rpx 8rpx rgba(0, 0, 0, 0.06),
			inset 0 2rpx 6rpx rgba(255, 255, 255, 0.9),
			inset 0 -2rpx 6rpx rgba(0, 0, 0, 0.05),
			inset 0 1rpx 0 rgba(255, 255, 255, 1);
		border-radius: 15rpx 15rpx 15rpx 100rpx;
		transform: skewX(-5deg);
		transition: all 0.3s ease;
	}

	/* 果冻高光 */
	.player-card::after {
		content: '';
		position: absolute;
		top: 6rpx;
		left: 12rpx;
		right: 12rpx;
		height: 35%;
		border-radius: 15rpx 15rpx 15rpx 100rpx;
		background: linear-gradient(180deg,
			rgba(255, 255, 255, 0.5) 0%,
			rgba(255, 255, 255, 0.1) 60%,
			rgba(255, 255, 255, 0) 100%
		);
		pointer-events: none;
		z-index: 0;
	}

	.player-card:active {
		box-shadow:
			0 4rpx 16rpx rgba(0, 0, 0, 0.15),
			0 1rpx 4rpx rgba(0, 0, 0, 0.08),
			inset 0 3rpx 8rpx rgba(255, 255, 255, 0.8),
			inset 0 -2rpx 6rpx rgba(0, 0, 0, 0.08),
			inset 0 1rpx 0 rgba(255, 255, 255, 0.9);
		transform: skewX(-5deg) scale(0.98);
	}

	.player-card > * {
		transform: skewX(5deg);
	}

	.player-card-top {
		display: flex;
		align-items: center;
		padding: 24rpx;
	}

	.avatar-frame-wrapper {
		position: relative;
		width: 100rpx;
		height: 100rpx;
		flex-shrink: 0;
		margin-right: 20rpx;
	}

	.avatar-frame-inner {
		position: relative;
		width: 100rpx;
		height: 100rpx;
	}

	.avatar-frame-wrapper .avatar-main {
		position: absolute;
		top: 0;
		left: 0;
		width: 100rpx;
		height: 100rpx;
		z-index: 1;
	}

	.avatar-frame-wrapper .avatar-frame-img {
		position: absolute;
		top: -5rpx;
		left: -5rpx;
		width: 110rpx;
		height: 110rpx;
		z-index: 2;
		pointer-events: none;
	}

	.player-card-info {
		flex: 1;
		min-width: 0;
	}

	.player-card-row {
		display: flex;
		align-items: center;
	}

	.player-name {
		font-size: 28rpx;
		color: #000;
		font-weight: 500;
	}

	.player-sex-icon {
		width: 28rpx;
		height: 28rpx;
		margin-left: 8rpx;
	}

	.player-status {
		margin-left: auto;
		font-size: 22rpx;
		padding: 4rpx 14rpx;
		border-radius: 20rpx;
		position: relative;
		overflow: visible;
		transition: all 0.3s ease;
	}

	.player-status::after {
		content: '';
		position: absolute;
		top: 2rpx;
		left: 6rpx;
		right: 6rpx;
		height: 35%;
		border-radius: 20rpx;
		background: linear-gradient(180deg,
			rgba(255, 255, 255, 0.5) 0%,
			rgba(255, 255, 255, 0.1) 60%,
			rgba(255, 255, 255, 0) 100%
		);
		pointer-events: none;
		z-index: 0;
	}

	.player-status-on {
		color: #1BD36C;
		background: linear-gradient(135deg, #e6f9f0 0%, #d0f5e3 100%);
		box-shadow:
			0 2rpx 8rpx rgba(27, 211, 108, 0.18),
			inset 0 2rpx 4rpx rgba(255, 255, 255, 0.8),
			inset 0 -1rpx 3rpx rgba(0, 0, 0, 0.06);
	}

	.player-status-off {
		color: #999;
		background: linear-gradient(135deg, #f5f5f7 0%, #e8e8ec 100%);
		box-shadow:
			0 2rpx 8rpx rgba(0, 0, 0, 0.08),
			inset 0 2rpx 4rpx rgba(255, 255, 255, 0.7),
			inset 0 -1rpx 3rpx rgba(0, 0, 0, 0.05);
	}

	.player-level-tag {
		position: relative;
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
		overflow: visible;
		box-shadow:
			inset 0 2rpx 4rpx rgba(255, 255, 255, 0.4),
			inset 0 -1rpx 3rpx rgba(0, 0, 0, 0.12);
	}

	.player-level-tag::after {
		content: '';
		position: absolute;
		top: 2rpx;
		left: 8rpx;
		right: 8rpx;
		height: 35%;
		border-radius: 20rpx;
		background: linear-gradient(180deg,
			rgba(255, 255, 255, 0.45) 0%,
			rgba(255, 255, 255, 0.1) 60%,
			rgba(255, 255, 255, 0) 100%
		);
		pointer-events: none;
	}

	.player-level-line {
		display: flex;
		align-items: center;
		height: 34rpx;
		overflow: visible;
	}

	.vip-icon {
		width: 85rpx;
		height: 85rpx;
		flex-shrink: 0;
	}

	.player-score {
		display: flex;
		align-items: center;
	}

	.player-star-icon {
		width: 28rpx;
		height: 28rpx;
		margin-right: 6rpx;
	}

	.player-score-text {
		font-size: 24rpx;
		color: #C0873C;
		font-weight: 500;
	}

	.player-voice-card {
		display: flex;
		justify-content: flex-start;
	}

	.player-voice-player {
		position: relative;
		display: flex;
		align-items: center;
		justify-content: flex-start;
		flex-shrink: 0;
		padding: 6rpx 12rpx;
		border-radius: 40rpx;
		background: linear-gradient(135deg, #C05B34 0%, #A64A28 100%);
		box-shadow: 0 3rpx 15rpx rgba(192, 91, 52, 0.4),
			0 0 0 1rpx rgba(255, 255, 255, 0.2) inset;
		transition: all 0.3s ease;
		overflow: visible;
		min-width: 88rpx;
	}

	.player-voice-player.playing {
		box-shadow: 0 4rpx 24rpx rgba(192, 91, 52, 0.7),
			0 0 30rpx rgba(192, 91, 52, 0.4),
			0 0 0 1rpx rgba(255, 255, 255, 0.3) inset;
	}

	.player-voice-player:active {
		transform: scale(0.96);
		opacity: 0.9;
	}

	.player-voice-btn-wrapper {
		position: relative;
		flex-shrink: 0;
		margin-right: 8rpx;
	}

	.player-voice-btn-inner {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 30rpx;
		height: 30rpx;
		border-radius: 50%;
		background: #ffffff;
		box-shadow: 0 1.5rpx 6rpx rgba(0, 0, 0, 0.15),
			0 0 0 1.5rpx rgba(192, 91, 52, 0.3);
		transition: all 0.3s ease;
	}

	.player-voice-player.playing .player-voice-btn-inner {
		animation: player-voice-btn-pulse 0.8s ease-in-out infinite;
	}

	@keyframes player-voice-btn-pulse {
		0%, 100% { transform: scale(1); box-shadow: 0 1.5rpx 6rpx rgba(0, 0, 0, 0.15), 0 0 0 1.5rpx rgba(192, 91, 52, 0.3); }
		50% { transform: scale(1.05); box-shadow: 0 3rpx 9rpx rgba(0, 0, 0, 0.2), 0 0 0 3rpx rgba(192, 91, 52, 0.5); }
	}

	.player-voice-bars {
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 4rpx;
		height: 24rpx;
	}

	.player-voice-bar-item {
		width: 5rpx;
		background: rgba(255, 255, 255, 0.9);
		border-radius: 3rpx;
		transition: all 0.2s ease;
	}

	.player-voice-bar-item:nth-child(1) { height: 12rpx; }
	.player-voice-bar-item:nth-child(2) { height: 18rpx; }
	.player-voice-bar-item:nth-child(3) { height: 24rpx; }
	.player-voice-bar-item:nth-child(4) { height: 18rpx; }
	.player-voice-bar-item:nth-child(5) { height: 12rpx; }

	.player-voice-bar-item.active {
		background: linear-gradient(to top, #ffffff 0%, rgba(255, 255, 255, 0.5) 100%);
		animation: player-voice-bar-dance 0.7s ease-in-out infinite;
	}

	@keyframes player-voice-bar-dance {
		0%, 100% { transform: scaleY(0.5); opacity: 0.7; }
		50% { transform: scaleY(1); opacity: 1; }
	}
</style>
