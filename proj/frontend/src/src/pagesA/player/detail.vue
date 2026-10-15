<template>
	<view class="">
		<view class="detail-navbar">
			<view class="detail-navbar-back" @click="goBack">
				<up-icon name="arrow-left" size="40rpx" color="#333"></up-icon>
			</view>
		</view>
		<view class="" style="z-index: 0; position: relative;">
			<up-swiper :list="dataInfo.images" height="600rpx" :autoplay="false">
			</up-swiper>
		</view>
		<view class="info">
			<view class="info-inner">
			<!-- 头像、姓名、评分居中一行 -->
			<view class="flex align-center justify-center" style="flex-direction: column;">
				<view class="info_top">
					<view class="avatar-frame-wrapper">
						<view class="avatar-frame-inner">
							<up-avatar :src="dataInfo.avatar" size="160rpx" class="avatar-main"></up-avatar>
							<!-- 头像框 -->
							<image v-if="dataInfo.avatar_frame && dataInfo.avatar_frame.image" 
								:src="dataInfo.avatar_frame.image" 
								mode="aspectFill"
								class="avatar-frame-img"></image>
						</view>
					</view>
				</view>
				<view class="flex align-center" style="margin-top: 16rpx;">
					<view class="font-lg font-weight-bolder mr-2" style="color: #000000; line-height: 1; display: flex; align-items: center;">{{dataInfo.name}}</view>
					<view class="id-card">
						<image :src="dataInfo.sex == '0' ? '/static/images/woman.png' : '/static/images/man.png'" class="id-sex-icon"></image>
						<text class="id-text">ID {{dataInfo.id}}</text>
					</view>
					<view class="flex align-center ml-3" style="line-height: 1;">
						<image src="/static/images/Star.png" style="width: 28rpx; height: 28rpx; display: block;"></image>
						<view class="font-24 text-light9 ml-2" style="line-height: 1;">{{dataInfo.grade}}分</view>
					</view>
				</view>
				<view class="font-sm text-light6 mt-1" style="text-align: center;">{{dataInfo.synopsis || ''}}</view>
			</view>

			<!-- 接单大厅权限卡片 -->
			<view v-if="dataInfo.game_category_list && dataInfo.game_category_list.length" class="halls-card">
				<view v-for="(cat, index) in dataInfo.game_category_list" :key="index" class="hall-item">
					<image class="hall-icon" :src="cat.image" mode="aspectFit"></image>
					<text class="hall-name">{{cat.name}}</text>
				</view>
			</view>

			<!-- 介绍和礼物墙切换Tab -->
			<view class="switch-tabs" style="margin-top: 10rpx;">
				<view :class="['switch-tab', mainTabIndex === 0 ? 'active' : '']" @click="mainTabIndex = 0">
					<text>Ta的介绍</text>
				</view>
				<view :class="['switch-tab', mainTabIndex === 1 ? 'active' : '']" @click="switchToGiftTab">
					<text>Ta的礼物墙</text>
				</view>
			</view>

			<!-- 介绍内容 -->
			<view v-if="mainTabIndex === 0" style="margin-top: 10rpx;">
				<view class="flex align-center font text-main">账号等级</view>
				<view class="flex align-center" style="margin-top: 10rpx;">
					<view class="tag" :style="getplayerLevl().color">
						{{getplayerLevl().title}}
					</view>
					<image class="vip-icon ml-3" :src="getVipIcon()" mode="aspectFit"></image>
				</view>

				<view class="flex align-center mt-1 font text-main">Ta的语音</view>
				<view v-if="dataInfo.audio" class="voice-card" style="margin-top: 6rpx;">
					<view class="voice-player" :class="{'playing': isPlaying}" @click="toggleAudio">
						<view class="voice-btn-wrapper">
							<view class="voice-btn-inner">
								<up-icon :name="isPlaying ? 'pause-circle-fill' : 'play-circle-fill'" size="22rpx" color="#FF7A45"></up-icon>
							</view>
						</view>
						<view class="voice-bars">
							<view class="voice-bar-item" :class="{'active': isPlaying}" :style="{animationDelay: (i * 0.12) + 's'}" v-for="i in 5" :key="i"></view>
						</view>
					</view>
				</view>
				<view v-else class="mt-1 font-sm text-light6">对方还没上传自己的声音</view>

				<view class="flex align-center mt-1 font text-main">Ta的相册</view>
				<view class="mt-1">
					<up-album v-if="dataInfo.images" :urls="dataInfo.images" radius="20rpx" rowCount="4" multipleSize="80"></up-album>
				</view>
			</view>

			<!-- 礼物墙内容 -->
			<view v-if="mainTabIndex === 1" class="gift-wall-content">
				<view class="gift-wall-header">
					<text class="gift-wall-title">礼物墙</text>
					<text class="gift-wall-count">共 {{giftTotalCount}} 件礼物</text>
				</view>
				<view v-if="giftWallList.length" class="gift-wall-grid">
					<view v-for="(gift, index) in giftWallList" :key="index" class="gift-wall-item">
						<view class="gift-wall-icon-wrap">
							<image class="gift-wall-icon" :src="gift.gift_icon" mode="aspectFit"></image>
							<view v-if="gift.count > 1" class="gift-wall-badge">x{{gift.count}}</view>
						</view>
						<view class="gift-wall-name">{{gift.gift_name}}</view>
					</view>
				</view>
				<uv-empty v-else mode="data" text="暂无礼物"></uv-empty>

				<!-- 礼物冠名 -->
				<view v-if="giftWallList.length" class="gift-sponsor-section">
					<view class="gift-sponsor-title">礼物冠名</view>
					<view class="gift-sponsor-list">
						<view v-for="(gift, index) in giftWallList" :key="index" class="gift-sponsor-item">
							<image class="gift-sponsor-avatar" :src="gift.first_sender_avatar || '/static/images/default.png'" mode="aspectFill"></image>
							<view class="gift-sponsor-info">
								<text class="gift-sponsor-name">{{gift.first_sender_name || '匿名'}}</text>
								<text class="gift-sponsor-gift">赠送了 {{gift.gift_name}} x{{gift.count}}</text>
							</view>
							<image class="gift-sponsor-icon" :src="gift.gift_icon" mode="aspectFit"></image>
						</view>
					</view>
				</view>
			</view>
			</view>
		</view>



		<view class="" style="height: 150rpx;"></view>

		<view class="fixed-bottom p-3 flex align-center bg-white">
			<view class="u-flex u-flex-middle ju" @click="juClick">举报</view>
			<view class="u-flex u-flex-middle da" @click="show=true">打赏</view>
			<view class="u-flex u-flex-middle gift-box" @click="showGiftBox">
				<text class="gift-box-btn-text">礼物</text>
			</view>
			<view class="u-flex u-flex-middle dian" @click="xiadanClick">点单</view>
		</view>

		<up-popup :show="show" closeable @close="show=false" round="20rpx">
			<view class="flex align-center justify-center flex-column py-4">
				<up-avatar :src="dataInfo.avatar" size="120rpx"></up-avatar>
				<view class="font-lg font-weight-bolder text-main mt-2">{{dataInfo.name}}</view>
				<view class="mt-4 font-sm text-light6">谢谢你的支持和打赏！</view>
				<view class="mt-4 flex align-center">
					<view class="mr-2 u-flex u-flex-middle" :class="current==index?'select':'normal'" v-for="(item,index) in list"
						:key="index" @click="changePrice(item,index)">
						{{item}}
					</view>
				</view>
				<view class="mt-4 px-3" style="">
					<up-input v-model="zmoney" placeholder="自定义金额" border="none" inputAlign="center" shape="circle"
						customStyle="background: #F8FAFD;width: 632rpx;height:72rpx" @focus="focus"></up-input>
				</view>

				<view class="mt-4 px-3">
					<view class="pay-method">
						<view class="pay-method-title">选择支付方式</view>
						<view class="pay-method-list">
							<view class="pay-method-item" :class="payMethod==='yipay'?'active':''" @click="payMethod='yipay'">
								<view class="pay-method-icon">
									<up-icon name="weixin-fill" color="#07C160" size="40rpx"></up-icon>
								</view>
								<view class="pay-method-info">
									<view class="pay-method-name">在线支付</view>
									<view class="pay-method-desc">推荐</view>
								</view>
								<view class="pay-method-check">
									<up-icon v-if="payMethod==='yipay'" name="checkmark-circle-fill" color="#07C160" size="40rpx"></up-icon>
									<up-icon v-else name="circle" color="#999999" size="40rpx"></up-icon>
								</view>
							</view>
							<view class="pay-method-item" :class="payMethod==='balance'?'active':''" @click="payMethod='balance'">
								<view class="pay-method-icon">
									<up-icon name="red-packet-fill" color="#FF6034" size="40rpx"></up-icon>
								</view>
								<view class="pay-method-info">
									<view class="pay-method-name">余额支付</view>
									<view class="pay-method-desc">当前余额：{{ balance }}元</view>
								</view>
								<view class="pay-method-check">
									<up-icon v-if="payMethod==='balance'" name="checkmark-circle-fill" color="#07C160" size="40rpx"></up-icon>
									<up-icon v-else name="circle" color="#999999" size="40rpx"></up-icon>
								</view>
							</view>
						</view>
					</view>
				</view>

				<view class="mt-4 pay u-flex u-flex-middle" @click="dashang">
					确认打赏
				</view>

			</view>
		</up-popup>

		<up-popup :show="dianShow" closeable @close="dianShow=false" round="20rpx">

			<view class="p-3 bg-white" style="height: 1200rpx">
				<view class="flex align-center justify-center">
					<view class="text-main font font-weight-bold">选择商品</view>
				</view>

				<view class="mt-2 flex align-center">
					<view class="bg-white p-2 flex align-center">
						<view class="" style="width: 600rpx;">
							<u-search placeholder="搜索商品标题" v-model="keyword" :showAction="false" @search="inputSearch"></u-search>
						</view>

						<view class="ml-2">
							<u-image src="/static/images/classify.png" width="48rpx" height="48rpx" @click="pickshow=true"></u-image>
						</view>
					</view>

				</view>

				<view class="" v-if="dataList.length">
					<scroll-view scroll-y="true" style="height: 1000rpx;">

						<view class="">
							<up-grid col="2">
								<u-grid-item v-for="(item,index) in dataList" :key="index">
									<commonItem :item="item" :thugId="thugId"></commonItem>
								</u-grid-item>
							</up-grid>
						</view>
					</scroll-view>

				</view>
				<template v-else>
					<up-empty></up-empty>
				</template>

				<up-picker ref="uPickerRef" :show="pickshow" :columns="columns" @cancel="pickshow=false"
					@confirm="onPickerConfirm" @change="onPickerChange" :defaultIndex="[0,0]"></up-picker>

			</view>

		</up-popup>

		<!-- 打手详细介绍弹窗 -->
		<up-popup :show="showIntroPopup" closeable @close="showIntroPopup=false" round="20rpx">
			<view class="p-4 bg-white intro-popup">
				<view class="flex align-center justify-center mb-4">
					<view class="text-main font font-weight-bold font-lg">Ta的介绍</view>
				</view>
				<view class="intro-content">
					<text class="font-base text-dark">{{dataInfo.synopsis || '暂无介绍'}}</text>
				</view>
				<view v-if="dataInfo.game_content" class="mt-4">
					<view class="font-sm text-light6 mb-2">详细说明：</view>
					<text class="font-sm text-light9">{{dataInfo.game_content}}</text>
				</view>
			</view>
		</up-popup>

		<!-- 礼物箱弹窗 -->
		<up-popup :show="showGiftPopup" closeable @close="closeGiftPopup" round="20rpx">
			<view class="gift-popup">
				<view class="gift-popup-header">
					<view class="flex align-center justify-center">
						<view class="text-main font font-weight-bold font-lg">礼物箱</view>
					</view>
					<view class="gift-tabs mt-3">
						<view :class="['gift-tab', giftTabIndex === 0 ? 'active' : '']" @click="giftTabIndex = 0">赠送礼物</view>
						<view :class="['gift-tab', giftTabIndex === 1 ? 'active' : '']" @click="switchGiftTab(1)">礼物记录</view>
					</view>
				</view>

				<!-- 赠送礼物tab -->
				<view v-if="giftTabIndex === 0" class="gift-content">
					<scroll-view scroll-y="true" style="max-height: 600rpx;">
						<view class="gift-list">
							<view
								v-for="(gift, index) in giftList"
								:key="gift.id"
								:class="['gift-item', selectedGift && selectedGift.id === gift.id ? 'selected' : '']"
								@click="selectGift(gift)"
							>
								<image class="gift-icon" :src="gift.icon" mode="aspectFit"></image>
								<view class="gift-name">{{gift.name}}</view>
								<view class="gift-price">￥{{gift.price}}</view>
							</view>
						</view>
						<uv-empty v-if="!giftList.length" mode="data" text="暂无可赠送的礼物"></uv-empty>
					</scroll-view>

					<!-- 礼物数量选择 -->
					<view v-if="selectedGift" class="gift-count-section">
						<view class="gift-count-label">选择数量</view>
						<view class="gift-count-controls">
							<view class="count-btn" @click="decreaseCount">-</view>
							<input class="count-input" type="number" v-model="giftCount" @blur="validateCount" />
							<view class="count-btn" @click="increaseCount">+</view>
						</view>
						<view class="gift-total">合计: ￥{{(selectedGift.price * giftCount).toFixed(2)}}</view>
					</view>

					<!-- 支付方式 -->
					<view v-if="selectedGift" class="gift-pay-section">
						<view class="pay-method-title">选择支付方式</view>
						<view class="pay-method-list">
							<view class="pay-method-item" :class="giftPayMethod==='yipay'?'active':''" @click="giftPayMethod='yipay'">
								<view class="pay-method-icon">
									<up-icon name="weixin-fill" color="#07C160" size="40rpx"></up-icon>
								</view>
								<view class="pay-method-info">
									<view class="pay-method-name">在线支付</view>
								</view>
								<view class="pay-method-check">
									<up-icon v-if="giftPayMethod==='yipay'" name="checkmark-circle-fill" color="#07C160" size="40rpx"></up-icon>
									<up-icon v-else name="circle" color="#999999" size="40rpx"></up-icon>
								</view>
							</view>
							<view class="pay-method-item" :class="giftPayMethod==='balance'?'active':''" @click="giftPayMethod='balance'">
								<view class="pay-method-icon">
									<up-icon name="red-packet-fill" color="#FF6034" size="40rpx"></up-icon>
								</view>
								<view class="pay-method-info">
									<view class="pay-method-name">余额支付</view>
									<view class="pay-method-desc">当前余额：{{ balance }}元</view>
								</view>
								<view class="pay-method-check">
									<up-icon v-if="giftPayMethod==='balance'" name="checkmark-circle-fill" color="#07C160" size="40rpx"></up-icon>
									<up-icon v-else name="circle" color="#999999" size="40rpx"></up-icon>
								</view>
							</view>
						</view>
					</view>

					<!-- 赠送按钮 -->
					<view v-if="selectedGift" class="gift-submit-btn" @click="submitGift">
						<view class="submit-btn-text">赠送礼物</view>
					</view>
				</view>

				<!-- 礼物记录tab -->
				<view v-if="giftTabIndex === 1" class="gift-content">
					<scroll-view scroll-y="true" style="max-height: 700rpx;">
						<view class="gift-record-list">
							<view v-for="(record, index) in giftRecordList" :key="index" class="gift-record-item">
								<image class="record-gift-icon" :src="record.gift_icon" mode="aspectFit"></image>
								<view class="record-info">
									<view class="record-title">{{record.gift_name || '礼物'}}</view>
									<view class="record-desc">
										<text>x{{record.num}}</text>
										<text class="ml-2">共 ￥{{record.total_price}}</text>
									</view>
									<view class="record-time">{{formatRecordTime(record.createtime)}}</view>
								</view>
							</view>
						</view>
						<uv-empty v-if="!giftRecordList.length" mode="data" text="暂无礼物记录"></uv-empty>
					</scroll-view>
				</view>
			</view>
		</up-popup>

		<!-- 礼物动画播报 -->
		<view v-if="showGiftAnimation" class="gift-animation-container" @click="closeGiftAnimation">
			<view class="gift-animation-content">
				<view class="gift-animation-sender">
					<up-avatar :src="userInfo.avatar || '/static/icon/default_avatar.png'" size="60rpx"></up-avatar>
					<text class="sender-name">{{userInfo.nickname || '用户'}}</text>
				</view>
				<view class="gift-animation-main">
					<image class="animation-gift-icon" :src="currentAnimationGift.icon" mode="aspectFit"></image>
					<view class="animation-text">送出了</view>
					<view class="animation-gift-name">{{currentAnimationGift.name}}</view>
					<view class="animation-count">x{{giftAnimationCount}}</view>
				</view>
			</view>
		</view>






	</view>


</template>

<script setup>
	import {
		ref
	} from 'vue';
	import {
		onLoad
	} from '@dcloudio/uni-app';
	import {
		thugMsg,
		thugReward,
		goods,
		category,
		getUserInfo,
		getThugGifts,
		getThugGiftStats,
		sendGift,
		getThugGiftRecords
	} from '../../request/api';
	import payUtil from '../../utils/pay';
	import {
		test
	} from '../../uni_modules/uview-plus';
	import {
		round
	} from '../../uni_modules/uview-plus/libs/function/digit';
	import router from '../../utils/router';

	const show = ref(false)
	const dianShow = ref(false)
	const pickshow = ref(false)
	const list = ['￥5', '￥10', '￥15', '￥20']
	const current = ref(0)
	const id = ref('')
	const dataInfo = ref({})
	const money = ref('5')
	const zmoney = ref('')
	const keyword = ref('')
	const paging = ref(null)
	const dataList = ref([])
	const columns = ref([])
	const columnData = ref([])
	const uPickerRef = ref(null)
	const arrAll = ref([])
	const classifyItem = ref({})
	const childrenList = ref([])
	const pid = ref('')
	const tid = ref('')
	const thugId = ref('')
	const payMethod = ref('yipay')
	const balance = ref('0.00')
	const audioContext = ref(null)
	const isPlaying = ref(false)
	const audioUrl = ref('')
	const audioProgress = ref(0)
	const currentTime = ref(0)
	const totalDuration = ref(0)

	// 礼物相关变量
	const mainTabIndex = ref(0)
	const giftWallList = ref([])
	const giftTotalCount = ref(0)
	const showIntroPopup = ref(false)
	const showGiftPopup = ref(false)
	const showGiftAnimation = ref(false)
	const giftTabIndex = ref(0)
	const giftList = ref([])
	const giftRecordList = ref([])
	const selectedGift = ref(null)
	const giftCount = ref(1)
	const giftPayMethod = ref('balance')
	const userInfo = ref({})
	const currentAnimationGift = ref({})
	const giftAnimationCount = ref(1)

	// 格式化记录时间
	const formatRecordTime = (timestamp) => {
		if (!timestamp) return ''
		const date = new Date(timestamp * 1000)
		const month = (date.getMonth() + 1).toString().padStart(2, '0')
		const day = date.getDate().toString().padStart(2, '0')
		const hour = date.getHours().toString().padStart(2, '0')
		const minute = date.getMinutes().toString().padStart(2, '0')
		return `${month}-${day} ${hour}:${minute}`
	}

	// 切换到礼物墙Tab
	const switchToGiftTab = () => {
		mainTabIndex.value = 1
		loadGiftWall()
	}

	// 加载礼物墙数据
	const loadGiftWall = async () => {
		try {
			const res = await getThugGiftStats({ thug_id: id.value })
			if (res.code === 1 && res.data) {
				giftWallList.value = res.data.gift_wall || []
				giftTotalCount.value = res.data.total_gifts || 0
			}
		} catch (e) {
			console.error('获取礼物墙失败:', e)
		}
	}

	// 显示礼物箱
	const showGiftBox = () => {
		if (test.isEmpty(uni.getStorageSync('token'))) {
			return router.navigateTo({ url: 'pages/login/login' })
		}
		showGiftPopup.value = true
		giftTabIndex.value = 0
		loadGiftList()
		// 同时加载用户信息，确保礼物动画能正确显示
		loadUserInfo()
		// 如果用户信息为空，从本地存储获取基本信息
		if (!userInfo.value.avatar && !userInfo.value.nickname) {
			const localUserInfo = uni.getStorageSync('userInfo')
			if (localUserInfo) {
				userInfo.value = localUserInfo
			}
		}
	}

	// 关闭礼物弹窗
	const closeGiftPopup = () => {
		showGiftPopup.value = false
		selectedGift.value = null
		giftCount.value = 1
	}

	// 加载礼物列表
	const loadGiftList = async () => {
		try {
			const res = await getThugGifts()
			if (res.code === 1 && res.data) {
				giftList.value = res.data
			}
		} catch (e) {
			console.error('获取礼物列表失败:', e)
		}
	}

	// 加载用户信息
	const loadUserInfo = async () => {
		try {
			const res = await getUserInfo()
			if (res.code === 1) {
				userInfo.value = res.data || {}
			}
		} catch (e) {
			console.error('获取用户信息失败:', e)
		}
	}

	// 切换礼物tab
	const switchGiftTab = async (index) => {
		giftTabIndex.value = index
		if (index === 1) {
			loadGiftRecords()
		}
	}

	// 加载礼物记录
	const loadGiftRecords = async () => {
		try {
			const res = await getThugGiftRecords({ thug_id: id.value })
			if (res.code === 1 && res.data) {
				giftRecordList.value = res.data.list || []
			}
		} catch (e) {
			console.error('获取礼物记录失败:', e)
		}
	}

	// 选择礼物
	const selectGift = (gift) => {
		selectedGift.value = gift
		giftCount.value = 1
	}

	// 减少数量
	const decreaseCount = () => {
		if (giftCount.value > 1) {
			giftCount.value--
		}
	}

	// 增加数量
	const increaseCount = () => {
		giftCount.value++
	}

	// 验证数量
	const validateCount = () => {
		if (giftCount.value < 1 || !giftCount.value) {
			giftCount.value = 1
		}
	}

	// 提交礼物
	const submitGift = async () => {
		if (!selectedGift.value) {
			uni.showToast({ title: '请选择礼物', icon: 'none' })
			return
		}
		const totalPrice = selectedGift.value.price * giftCount.value
		if (giftPayMethod.value === 'balance') {
			const amount = parseFloat(balance.value)
			if (amount < totalPrice) {
				uni.showToast({ title: '余额不足，请选择在线支付', icon: 'none' })
				return
			}
		}
		try {
			if (giftPayMethod.value === 'balance') {
				const giftParams = {
					thug_id: id.value,
					gift_id: selectedGift.value.id,
					num: giftCount.value,
					pay_type: 'balance'
				}
				const res = await sendGift(giftParams)
				if (res.code === 1) {
					const sentGift = {
						...selectedGift.value,
						icon: res.data.gift_icon || selectedGift.value.icon
					}
					const sentCount = giftCount.value
					showGiftPopup.value = false
					selectedGift.value = null
					giftCount.value = 1
					currentAnimationGift.value = sentGift
					giftAnimationCount.value = sentCount
					showGiftAnimation.value = true
					setTimeout(() => { showGiftAnimation.value = false }, 3000)
					await loadGiftWall()
					await loadBalance()
				} else {
					uni.showToast({ title: res.msg || '赠送失败', icon: 'none' })
				}
			} else {
				// 礼物直接创建易支付订单，支付回调会自动完成赠送。
				uni.showLoading({ title: '发起支付...' })
				const res = await sendGift({
					thug_id: id.value,
					gift_id: selectedGift.value.id,
					num: giftCount.value,
					pay_type: 'yipay'
				})
				uni.hideLoading()
				if (res.code != 1) {
					return uni.showToast({ title: res.msg || '支付失败', icon: 'none', duration: 3000 })
				}
				showGiftPopup.value = false
				payUtil.redirectPay(res.data, (err) => {
					uni.showToast({ title: err || '支付失败', icon: 'none', duration: 3000 })
				})
			}
		} catch (e) {
			console.error('赠送礼物失败详细错误:', e)
			const msg = e?.errMsg || e?.message || e?.data?.msg || '网络错误，请重试'
			uni.showToast({ title: msg, icon: 'none', duration: 3000 })
		}
	}

	// 关闭礼物动画
	const closeGiftAnimation = () => {
		showGiftAnimation.value = false
	}

	const formatTime = (seconds) => {
		if (!seconds || isNaN(seconds)) return '0:00'
		const mins = Math.floor(seconds / 60)
		const secs = Math.floor(seconds % 60)
		return `${mins}:${secs.toString().padStart(2, '0')}`
	}

	onLoad((prop) => {
		id.value = prop.id
		getDetail()
		getClassify()
		queryList(1, 50)
		audioContext.value = uni.createInnerAudioContext()
		audioContext.value.onEnded(() => {
			isPlaying.value = false
			audioProgress.value = 0
			currentTime.value = 0
		})
		audioContext.value.onTimeUpdate(() => {
			if (audioContext.value.duration) {
				currentTime.value = audioContext.value.currentTime
				totalDuration.value = audioContext.value.duration
				audioProgress.value = (audioContext.value.currentTime / audioContext.value.duration) * 100
			}
		})
	})
	const getDetail = async () => {
		try {
			const res = await thugMsg({
				thug_id: id.value
			})
			dataInfo.value = res.data || {}
			thugId.value = res.data.id
			audioUrl.value = res.data.audio || ''
			if (audioUrl.value) {
				audioContext.value.src = audioUrl.value
				audioContext.value.onCanplay(() => {
					totalDuration.value = audioContext.value.duration
				})
			}
			await loadBalance()
		} catch (e) {
			console.error('获取打手详情失败:', e)
		}
	}

	const toggleAudio = () => {
		if (!audioUrl.value) {
			uni.showToast({ title: '音频地址无效', icon: 'none' })
			return
		}
		if (isPlaying.value) {
			audioContext.value.pause()
			isPlaying.value = false
		} else {
			audioContext.value.src = audioUrl.value
			audioContext.value.play()
			isPlaying.value = true
		}
	}

	const loadBalance = async () => {
		try {
			const userRes = await getUserInfo()
			if (userRes.code === 1) {
				balance.value = userRes.data.balance || '0.00'
			}
		} catch (e) {
			console.error('获取用户余额失败:', e)
		}
	}

	const getplayerLevl = () => {
		if (dataInfo.value.type == 1) {
			return {
				color: 'background: linear-gradient( 270deg, #FFB38A 0%, #FF7A45 100%);',
				title: '钻石打手'
			}
		}

		if (dataInfo.value.type == 2) {
			return {
				color: 'background: linear-gradient( 270deg, #FFD4B8 0%, #FF9D6E 100%);',
				title: '大师打手'
			}
		}
		if (dataInfo.value.type == 3) {
			return {
				color: 'background: linear-gradient( 270deg, #FFCC5B 0%, #FF8A40 100%);',
				title: '顶尖打手'
			}
		}
	}

	const getVipIcon = () => {
		let levelNum = 1
		// 从dataInfo中获取等级信息
		const levelName = (dataInfo.value.user_current_level_info && dataInfo.value.user_current_level_info.name) || dataInfo.value.level_name || ''
		const match = levelName.match(/\d+/)
		levelNum = match ? match[0] : 1
		return `/static/ym/v${levelNum}.png`
	}

	const changePrice = (item, index) => {
		current.value = index
		money.value = item
	}

	const dashang = async () => {
		if (test.isEmpty(uni.getStorageSync('token'))) {
			return router.navigateTo({
				url: 'pages/login/login'
			})
		}
		const finalMoney = current.value > -1 ? money.value.replace('￥', '') : zmoney.value
		if (!finalMoney || parseFloat(finalMoney) <= 0) {
			return uni.showToast({
				title: '请选择或输入打赏金额',
				icon: 'none'
			})
		}
		if (payMethod.value === 'balance') {
			const amount = parseFloat(balance.value)
			if (amount < parseFloat(finalMoney)) {
				return uni.showToast({
					title: '余额不足，请选择在线支付',
					icon: 'none'
				})
			}
		}
		try {
			if (payMethod.value === 'balance') {
				const res = await thugReward({
					thug_id: dataInfo.value.id,
					money: finalMoney,
					pay_type: 'balance'
				})
				if (res.code === 1) {
					uni.showToast({ title: '打赏成功', icon: 'success' })
					show.value = false
					await loadBalance()
				} else {
					uni.showToast({ title: res.msg || '打赏失败', icon: 'none', duration: 3000 })
				}
			} else {
				// 打赏直接创建易支付订单，支付回调会自动给打手入账。
				uni.showLoading({ title: '发起支付...' })
				const res = await thugReward({
					thug_id: dataInfo.value.id,
					money: finalMoney,
					pay_type: 'yipay'
				})
				uni.hideLoading()
				if (res.code != 1) {
					return uni.showToast({ title: res.msg || '支付失败', icon: 'none', duration: 3000 })
				}
				show.value = false
				payUtil.redirectPay(res.data, (err) => {
					uni.showToast({ title: err || '支付失败', icon: 'none', duration: 3000 })
				})
			}
		} catch (e) {
			console.error('打赏失败详细错误:', e)
			const msg = e?.errMsg || e?.message || e?.data?.msg || '网络错误，请重试'
			uni.showToast({
				title: msg,
				icon: 'none',
				duration: 3000
			})
		}
	}

	const inputSearch = () => {
		queryList(1, 50)
	}

	const getClassify = async () => {
		const res = await category()
		arrAll.value = res.data
		columns.value = [
			arrAll.value.map(item => item.name),
			arrAll.value[0].children.map(item => item.name)
		]
		columnData.value = arrAll.value.map(item =>
			item.children.map(child => child.name)
		)
	}

	const onPickerConfirm = (e) => {
		const [firstIndex, secondIndex] = e.indexs;
		const firstCategory = arrAll.value[firstIndex];
		const secondCategory = firstCategory.children[secondIndex];
		pid.value = firstCategory.id
		tid.value = secondCategory.id
		pickshow.value = false;
		queryList(1, 50)
	}

	const onPickerChange = (e) => {
		const {
			columnIndex,
			index,
		} = e;
		if (columnIndex === 0) {
			uPickerRef.value.setColumnValues(1, columnData.value[index]);
		}
	}

	const queryList = (pageNo, pageSize) => {
		const queryParams = {
			page: pageNo,
			limit: pageSize,
			name: keyword.value,
			one_category: Number(pid.value),

		};
		if (tid.value) {
			queryParams.two_category = Number(tid.value);
		}
		goods(queryParams)
			.then(res => {
				// 验证响应结构
				if (res && res.data && Array.isArray(res.data.data)) {
					dataList.value = res.data.data
					// paging.value.complete(res.data.data);
				} else {

					// paging.value.complete([]);
				}
			})
			.catch(err => {
				console.error('API请求失败:', err);
				// paging.value.complete([]);
			})

	}
	const juClick = () => {
		uni.showToast({
			title: '请截图后联系平台客服，提交举报信息',
			icon: 'none'
		});
	}
	const focus = () => {
		current.value = -1
	}

	const xiadanClick = () => {

		if (test.isEmpty(uni.getStorageSync('token'))) {
			return router.navigateTo({
				url: 'pages/login/login'
			})
		}
		if (dataInfo.value.jd_status == 0) {
			return uni.showToast({
				title: '当前打手暂无法为您服务',
				icon: 'none'
			});
		}
		dianShow.value = true
	}

	const goBack = () => {
		uni.navigateBack({
			delta: 1,
			fail: () => {
				router.switchTab({
					url: '/pages/player/index'
				})
			}
		})
	}
</script>

<style lang="scss">
	.info {
		padding: 30rpx;
		background: #FFFFFF;
		border-radius: 24rpx 24rpx 0rpx 0rpx;
		margin-top: -30rpx;
		z-index: 10;
		position: relative;

		&_top {
			display: flex;
			align-items: flex-end;

		}
	}

	.info-inner {
		margin-top: -120rpx;
		display: block;
	}

	.ju {
		width: 160rpx;
		height: 88rpx;
		background: linear-gradient(90deg, #FF9D6E 0%, #FF7A45 100%);
		border-radius: 50rpx 50rpx 50rpx 50rpx;
		font-family: PingFang SC, PingFang SC;
		font-weight: 500;
		font-size: 30rpx;
		color: #FFFFFF;
	}

	.da {
		width: 160rpx;
		height: 88rpx;
		background: linear-gradient(90deg, #FFCC5B 0%, #FF8A40 100%);
		border-radius: 50rpx 50rpx 50rpx 50rpx;
		font-family: PingFang SC, PingFang SC;
		font-weight: 500;
		font-size: 30rpx;
		color: #FFFFFF;
		margin-left: 24rpx;
	}

	.dian {
		width: 322rpx;
		height: 88rpx;
		background: linear-gradient(90deg, #FF9D6E 0%, #FF7A45 100%);
		border-radius: 50rpx 50rpx 50rpx 50rpx;
		font-family: PingFang SC, PingFang SC;
		font-weight: 500;
		font-size: 30rpx;
		color: #FFFFFF;
		margin-left: 24rpx;
	}

	.normal {
		width: 140rpx;
		height: 72rpx;
		background: #F8FAFD;
		border-radius: 60rpx 60rpx 60rpx 60rpx;
		font-family: PingFang SC, PingFang SC;
		font-weight: 500;
		font-size: 28rpx;
		color: #222222;
	}

	.select {
		width: 140rpx;
		height: 72rpx;
		background: linear-gradient(180deg, #FF9D6E 0%, #FF7A45 100%);
		border-radius: 60rpx 60rpx 60rpx 60rpx;
		font-family: PingFang SC, PingFang SC;
		font-weight: 500;
		font-size: 28rpx;
		color: #FFFFFF;
	}

	.pay {
		width: 690rpx;
		height: 96rpx;
		background: linear-gradient(270deg, #FF7A45 0%, #FF9D6E 100%);
		border-radius: 60rpx 60rpx 60rpx 60rpx;
		font-family: PingFang SC, PingFang SC;
		font-weight: 500;
		font-size: 32rpx;
		color: #FFFFFF;
	}

	.pay-method {
		background: #F8FAFD;
		border-radius: 20rpx;
		padding: 24rpx;
	}

	.pay-method-title {
		font-size: 26rpx;
		color: #999;
		margin-bottom: 20rpx;
	}

	.pay-method-list {
		display: flex;
		flex-direction: column;
		gap: 16rpx;
	}

	.pay-method-item {
		display: flex;
		align-items: center;
		padding: 20rpx 24rpx;
		background: #fff;
		border-radius: 16rpx;
		border: 2rpx solid transparent;
		transition: all 0.2s ease;
	}

	.pay-method-item.active {
		border-color: #07C160;
		background: #f0f9f4;
	}

	.pay-method-icon {
		width: 64rpx;
		height: 64rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		background: #f5f5f5;
		border-radius: 50%;
		margin-right: 20rpx;
	}

	.pay-method-info {
		flex: 1;
	}

	.pay-method-name {
		font-size: 28rpx;
		color: #333;
		font-weight: 500;
	}

	.pay-method-desc {
		font-size: 22rpx;
		color: #999;
		margin-top: 4rpx;
	}

	.pay-method-check {
		flex-shrink: 0;
	}

	/* ID卡片样式 - 黑色半透明胶囊 */
	.id-card {
		display: flex;
		align-items: center;
		justify-content: center;
		background: rgba(0, 0, 0, 0.5);
		border-radius: 50rpx;
		padding: 0 10rpx;
		gap: 4rpx;
		height: 28rpx;
		box-sizing: border-box;
	}

	.id-sex-icon {
		width: 14rpx;
		height: 14rpx;
		display: block;
	}

	.id-text {
		font-size: 16rpx;
		color: #fff;
		font-weight: 400;
		line-height: 1;
	}

	/* 接单大厅权限卡片 */
	.halls-card {
		display: flex;
		flex-wrap: wrap;
		gap: 12rpx;
		margin-top: 10rpx;
		justify-content: center;
	}

	.hall-item {
		display: inline-flex;
		align-items: center;
		background: rgba(0, 0, 0, 0.5);
		border-radius: 20rpx;
		padding: 6rpx 20rpx;
		gap: 6rpx;
		white-space: nowrap;
	}

	.hall-icon {
		width: 28rpx;
		height: 28rpx;
		border-radius: 6rpx;
	}

	.hall-name {
		font-size: 20rpx;
		color: #fff;
	}

	.detail-navbar {
		position: fixed;
		top: var(--status-bar-height);
		left: 0;
		z-index: 100;
		padding: 20rpx 16rpx;
	}

	.detail-navbar-back {
		height: 64rpx;
		padding: 0 24rpx;
		background: rgba(255, 255, 255, 0.95);
		border-radius: 32rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.12);
	}

	.audio-container {
		background: #f8f8f8;
		border-radius: 16rpx;
		padding: 20rpx;
	}

	/* 语音播放器样式 - 微信胶囊气泡 */
	.voice-card {
		display: flex;
		justify-content: flex-start;
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

	.voice-player:active {
		transform: scale(0.96);
		opacity: 0.9;
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

	.vip-icon {
		width: 85rpx;
		height: 85rpx;
		flex-shrink: 0;
	}

	/* 头像框样式 */
	.avatar-frame-wrapper {
		position: relative;
		width: 160rpx;
		height: 160rpx;
		flex-shrink: 0;
	}

	.avatar-frame-inner {
		position: relative;
		width: 160rpx;
		height: 160rpx;
	}

	.avatar-frame-wrapper .avatar-main {
		position: absolute;
		top: 0;
		left: 0;
		width: 160rpx;
		height: 160rpx;
		z-index: 1;
	}

	.avatar-frame-wrapper .avatar-frame-img {
		position: absolute;
		top: -5rpx;
		left: -5rpx;
		width: 170rpx;
		height: 170rpx;
		z-index: 2;
	}

	/* 打手等级标签样式 */
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

	/* 介绍和礼物墙切换Tab样式 - 炫酷玻璃拟态 */
	.switch-tabs {
		display: flex;
		gap: 40rpx;
		justify-content: center;
		padding: 10rpx 0;
	}

	.switch-tab {
		display: flex;
		flex-direction: column;
		align-items: center;
		font-size: 26rpx;
		color: #999;
		transition: all 0.3s ease;
		position: relative;
		padding-bottom: 8rpx;
		font-weight: 500;
	}

	.switch-tab.active {
		color: #333;
		font-weight: 600;
	}

	.switch-tab.active::after {
		content: '';
		position: absolute;
		bottom: 0;
		left: 50%;
		transform: translateX(-50%);
		width: 40rpx;
		height: 6rpx;
		background: linear-gradient(90deg, #FF6B6B, #FFD93D, #6BCB77, #4D96FF, #9B59B6);
		border-radius: 3rpx;
	}

	/* 礼物墙内容样式 - 炫酷卡片 */
	.gift-wall-content {
		padding: 24rpx 0;
	}

	.gift-wall-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 0 10rpx 24rpx;
	}

	.gift-wall-title {
		font-size: 28rpx;
		font-weight: 600;
		color: #333;
	}

	.gift-wall-count {
		font-size: 22rpx;
		color: #999;
	}

	.gift-wall-grid {
		display: grid;
		grid-template-columns: repeat(3, 1fr);
		gap: 20rpx;
	}

	.gift-wall-item {
		display: flex;
		flex-direction: column;
		align-items: center;
		padding: 20rpx 12rpx;
		background: #f8f8f8;
		border-radius: 20rpx;
	}

	.gift-wall-icon-wrap {
		position: relative;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.gift-wall-icon {
		width: 80rpx;
		height: 80rpx;
	}

	.gift-wall-badge {
		position: absolute;
		top: -8rpx;
		right: -16rpx;
		background: #FF6034;
		color: #fff;
		font-size: 18rpx;
		padding: 2rpx 8rpx;
		border-radius: 20rpx;
		line-height: 1.4;
		font-weight: 600;
	}

	.gift-wall-name {
		font-size: 24rpx;
		color: #333;
		margin-top: 12rpx;
		font-weight: 500;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		max-width: 100%;
	}

	/* 礼物冠名 */
	.gift-sponsor-section {
		margin-top: 32rpx;
		padding-top: 24rpx;
		border-top: 2rpx solid #f0f0f0;
	}

	.gift-sponsor-title {
		font-size: 28rpx;
		font-weight: 600;
		color: #333;
		margin-bottom: 20rpx;
	}

	.gift-sponsor-list {
		display: flex;
		flex-direction: column;
		gap: 16rpx;
	}

	.gift-sponsor-item {
		display: flex;
		align-items: center;
		padding: 16rpx 20rpx;
		background: #f8f8f8;
		border-radius: 16rpx;
		gap: 16rpx;
	}

	.gift-sponsor-avatar {
		width: 48rpx;
		height: 48rpx;
		border-radius: 50%;
		flex-shrink: 0;
	}

	.gift-sponsor-info {
		flex: 1;
		display: flex;
		flex-direction: column;
		gap: 4rpx;
	}

	.gift-sponsor-name {
		font-size: 24rpx;
		color: #333;
		font-weight: 500;
	}

	.gift-sponsor-gift {
		font-size: 20rpx;
		color: #999;
	}

	.gift-sponsor-icon {
		width: 48rpx;
		height: 48rpx;
		flex-shrink: 0;
	}

	.gift-box-btn {
		display: flex;
		align-items: center;
		gap: 8rpx;
		background: linear-gradient(135deg, #FF6034 0%, #FF8A40 100%);
		padding: 16rpx 24rpx;
		border-radius: 30rpx;
		flex-shrink: 0;
	}

	.gift-box-text {
		font-size: 24rpx;
		color: #fff;
		font-weight: 500;
	}

	/* 介绍弹窗样式 */
	.intro-popup {
		width: 600rpx;
		max-height: 600rpx;
	}

	.intro-content {
		line-height: 1.8;
		padding: 20rpx;
		background: #f8f8f8;
		border-radius: 16rpx;
	}

	/* 礼物箱弹窗样式 */
	.gift-popup {
		width: 690rpx;
		padding: 30rpx;
		background: #fff;
	}

	.gift-tabs {
		display: flex;
		justify-content: center;
		gap: 60rpx;
		border-bottom: 2rpx solid #eee;
		padding-bottom: 20rpx;
	}

	.gift-tab {
		font-size: 28rpx;
		color: #999;
		padding-bottom: 10rpx;
		position: relative;
	}

	.gift-tab.active {
		color: #FF7A45;
		font-weight: bold;
	}

	.gift-tab.active::after {
		content: '';
		position: absolute;
		bottom: -22rpx;
		left: 50%;
		transform: translateX(-50%);
		width: 60rpx;
		height: 4rpx;
		background: linear-gradient(90deg, #FF9D6E 0%, #FF7A45 100%);
		border-radius: 2rpx;
	}

	.gift-content {
		margin-top: 30rpx;
	}

	.gift-list {
		display: flex;
		flex-wrap: wrap;
		gap: 20rpx;
	}

	.gift-item {
		width: 150rpx;
		display: flex;
		flex-direction: column;
		align-items: center;
		padding: 20rpx;
		border-radius: 16rpx;
		background: #f8f8f8;
		transition: all 0.3s ease;
	}

	.gift-item.selected {
		background: linear-gradient(135deg, #FFF5EF 0%, #FFD4B8 100%);
		border: 2rpx solid #FF7A45;
	}

	.gift-icon {
		width: 80rpx;
		height: 80rpx;
	}

	.gift-name {
		font-size: 24rpx;
		color: #333;
		margin-top: 10rpx;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		max-width: 100%;
	}

	.gift-price {
		font-size: 22rpx;
		color: #FF6034;
		margin-top: 6rpx;
	}

	/* 礼物数量选择样式 */
	.gift-count-section {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-top: 30rpx;
		padding: 20rpx;
		background: #f8f8f8;
		border-radius: 16rpx;
	}

	.gift-count-label {
		font-size: 26rpx;
		color: #333;
	}

	.gift-count-controls {
		display: flex;
		align-items: center;
		gap: 20rpx;
	}

	.count-btn {
		width: 56rpx;
		height: 56rpx;
		background: linear-gradient(135deg, #FF9D6E 0%, #FF7A45 100%);
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 32rpx;
		color: #fff;
		font-weight: bold;
	}

	.count-input {
		width: 100rpx;
		height: 56rpx;
		background: #fff;
		border-radius: 8rpx;
		text-align: center;
		font-size: 28rpx;
	}

	.gift-total {
		font-size: 28rpx;
		color: #FF6034;
		font-weight: bold;
	}

	/* 礼物支付方式样式 */
	.gift-pay-section {
		margin-top: 30rpx;
	}

	.gift-submit-btn {
		margin-top: 30rpx;
		background: linear-gradient(270deg, #FF7A45 0%, #FF9D6E 100%);
		border-radius: 60rpx;
		height: 96rpx;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.submit-btn-text {
		font-size: 32rpx;
		color: #fff;
		font-weight: 500;
	}

	/* 礼物记录样式 */
	.gift-record-list {
		display: flex;
		flex-direction: column;
		gap: 20rpx;
	}

	.gift-record-item {
		display: flex;
		align-items: center;
		padding: 20rpx;
		background: #f8f8f8;
		border-radius: 16rpx;
	}

	.record-gift-icon {
		width: 80rpx;
		height: 80rpx;
		border-radius: 12rpx;
		flex-shrink: 0;
	}

	.record-info {
		flex: 1;
		margin-left: 20rpx;
	}

	.record-title {
		font-size: 28rpx;
		color: #333;
		font-weight: 500;
	}

	.record-desc {
		font-size: 24rpx;
		color: #666;
		margin-top: 8rpx;
	}

	.record-time {
		font-size: 22rpx;
		color: #999;
		margin-top: 6rpx;
	}

	/* 礼物动画播报样式 */
	.gift-animation-container {
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		z-index: 9999;
		display: flex;
		align-items: center;
		justify-content: center;
		background: rgba(0, 0, 0, 0.3);
	}

	.gift-animation-content {
		display: flex;
		flex-direction: column;
		align-items: center;
		animation: giftPopIn 0.5s ease-out;
	}

	.gift-animation-sender {
		display: flex;
		align-items: center;
		gap: 16rpx;
		background: rgba(255, 255, 255, 0.95);
		padding: 20rpx 32rpx;
		border-radius: 50rpx;
		box-shadow: 0 8rpx 32rpx rgba(0, 0, 0, 0.15);
	}

	.sender-name {
		font-size: 28rpx;
		color: #333;
		font-weight: 500;
	}

	.gift-animation-main {
		display: flex;
		align-items: center;
		gap: 16rpx;
		background: linear-gradient(135deg, #FF6034 0%, #FF8A40 100%);
		padding: 24rpx 40rpx;
		border-radius: 50rpx;
		margin-top: 20rpx;
		box-shadow: 0 8rpx 32rpx rgba(255, 96, 52, 0.4);
		animation: giftFloat 2s ease-in-out infinite;
	}

	.animation-gift-icon {
		width: 80rpx;
		height: 80rpx;
		animation: giftBounce 0.6s ease-in-out infinite alternate;
	}

	.animation-text {
		font-size: 26rpx;
		color: rgba(255, 255, 255, 0.9);
	}

	.animation-gift-name {
		font-size: 30rpx;
		color: #fff;
		font-weight: bold;
	}

	.animation-count {
		font-size: 32rpx;
		color: #FFE066;
		font-weight: bold;
	}

	@keyframes giftPopIn {
		0% {
			transform: scale(0.5);
			opacity: 0;
		}
		100% {
			transform: scale(1);
			opacity: 1;
		}
	}

	@keyframes giftFloat {
		0%, 100% {
			transform: translateY(0);
		}
		50% {
			transform: translateY(-10rpx);
		}
	}

	@keyframes giftBounce {
		0% {
			transform: scale(1);
		}
		100% {
			transform: scale(1.2);
		}
	}

	/* 简介点击样式 */
	.synopsis-text {
		line-height: 1.6;
	}

	.intro-click-hint {
		display: block;
		margin-top: 8rpx;
		font-size: 24rpx;
	}

	/* 底部礼物按钮样式 */
	.gift-box {
		width: 140rpx;
		height: 88rpx;
		background: linear-gradient(135deg, #FF6034 0%, #FF8A40 100%);
		border-radius: 50rpx 50rpx 50rpx 50rpx;
		font-family: PingFang SC, PingFang SC;
		font-weight: 500;
		font-size: 28rpx;
		color: #FFFFFF;
		margin-left: 24rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 6rpx;
	}

	.gift-box-btn-text {
		font-size: 28rpx;
		color: #FFFFFF;
	}
</style>
