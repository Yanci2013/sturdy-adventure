<template>
	<view class="lt-page">
		<common-navbar :title="navbarTitle"></common-navbar>

		<!-- 奖池切换 Tab -->
		<view class="lt-tabs" v-if="pools.length > 1">
			<view
				class="lt-tab"
				:class="{ active: idx === i }"
				v-for="(p, i) in pools"
				:key="i"
				@click="switchPool(i)"
			>
				<text class="lt-tab-text">{{ p.name }}</text>
			</view>
		</view>

		<scroll-view scroll-y class="lt-scroll">
			<view class="lt-body">
				<!-- 活动标题 -->
				<view class="lt-hero" :class="'mode-' + pool.mode">
					<text class="lt-hero-title">{{ pool.title || '传世武器抽奖' }}</text>
					<view class="lt-hero-sub">
						<text class="lt-hero-sub-icon">!</text>
						<text class="lt-hero-sub-text">{{ pool.subtitle || '奖品在商品池随机抽取' }}</text>
					</view>
				</view>

				<!-- 转盘模式 -->
				<view class="lt-stage" v-if="pool.mode === 'wheel'">
					<view class="lt-wheel" :style="{ transform: 'rotate(' + rotation + 'deg)' }">
						<view class="lt-wheel-ring"></view>
						<view class="lt-seg" v-for="(p, i) in wheelPrizes" :key="i"
							:style="segStyle(i)">
							<view class="lt-seg-inner">
								<image class="lt-seg-icon" :src="p.icon || '/static/ym/1.png'" mode="aspectFill"></image>
								<text class="lt-seg-name">{{ p.name }}</text>
							</view>
						</view>
					</view>
					<view class="lt-pointer"></view>
					<view class="lt-wheel-center" @click="startDraw">
						<text class="lt-wheel-center-text">开业</text>
						<text class="lt-wheel-center-sub">GO</text>
					</view>
				</view>

				<!-- 盲盒模式 -->
				<view class="lt-stage lt-stage-box" v-else>
					<view class="lt-box" @click="startDraw">
						<image class="lt-box-img" :src="pool.cover || pool.images[0] || '/static/ym/1.png'" mode="aspectFit"></image>
					</view>
				</view>

				<!-- 抽奖按钮 -->
				<view class="lt-draw-area">
					<view class="lt-draw-btn" :class="{ disabled: drawing || !enabled }" @click="startDraw">
						<text class="lt-draw-btn-text">
							{{ pool.mode === 'wheel' ? '抽奖' : '开盲盒' }} ¥{{ poolPriceText }}
						</text>
					</view>
					<view class="lt-draw-btns" v-if="pool.mode === 'box'">
						<view class="lt-draw-ten" :class="{ disabled: drawing || !enabled }" @click="startDrawTen">
							<text class="lt-draw-ten-text">连开10次 ¥{{ (Number(pool.price || 0) * 10).toFixed(2) }}</text>
						</view>
					</view>
					<text class="lt-draw-tip" v-if="!enabled">活动暂未开放，敬请期待</text>
				</view>

				<!-- 奖池展示图片 -->
				<view class="lt-section" v-if="pool.images && pool.images.length">
					<view class="lt-section-head"><text class="lt-section-title">奖池展示</text></view>
					<scroll-view scroll-x class="lt-images">
						<view class="lt-images-row">
							<view class="lt-image-item" v-for="(img, i) in pool.images" :key="i" @click="previewImages(i)">
								<image class="lt-image" :src="img" mode="aspectFill"></image>
							</view>
						</view>
					</scroll-view>
				</view>

				<!-- 奖品池 -->
				<view class="lt-section">
					<view class="lt-section-head">
						<text class="lt-section-title">奖品池</text>
						<text class="lt-section-tip">点击奖品可查看外观</text>
					</view>
					<scroll-view scroll-x class="lt-prizes">
						<view class="lt-prizes-row">
							<view class="lt-prize-card" v-for="p in activePrizes" :key="p.id" @click="openPrize(p)">
								<image class="lt-prize-img" :src="p.icon || '/static/ym/1.png'" mode="aspectFill"></image>
								<text class="lt-prize-name">{{ p.name }}</text>
								<text class="lt-prize-price">¥{{ p.price || '0' }}</text>
							</view>
						</view>
					</scroll-view>
					<view class="lt-empty" v-if="!activePrizes.length"><text class="lt-empty-text">奖池暂未配置</text></view>
				</view>

				<view style="height: 120rpx;"></view>
			</view>
		</scroll-view>

		<!-- 右侧悬浮：规则说明 -->
		<view class="lt-side-btn lt-side-rules" @click="rulesShow = true">
			<text class="lt-side-text">规则说明</text>
		</view>

		<!-- 右下悬浮：游戏信息 -->
		<view class="lt-game-btn" @click="openGame">
			<up-icon name="game" size="40rpx" color="#fff"></up-icon>
			<text class="lt-game-btn-text">游戏信息</text>
		</view>

		<!-- 规则弹窗 -->
		<view class="lt-mask" v-if="rulesShow" @click="rulesShow = false">
			<view class="lt-dialog" @click.stop>
				<text class="lt-dialog-title">抽奖规则</text>
				<scroll-view scroll-y class="lt-dialog-scroll">
					<text class="lt-rules">{{ pool.rules || '暂无规则内容' }}</text>
				</scroll-view>
				<view class="lt-dialog-btn" @click="rulesShow = false">知道了</view>
			</view>
		</view>

		<!-- 游戏信息弹窗 -->
		<view class="lt-mask" v-if="gameShow" @click="gameShow = false">
			<view class="lt-dialog" @click.stop>
				<text class="lt-dialog-title">请先填写游戏信息</text>
				<view class="lt-form">
					<view class="lt-form-row">
						<text class="lt-form-label"><text class="req">*</text>游戏大区</text>
						<view class="lt-form-radio">
							<view class="lt-chip" :class="{ on: game.zone === '微区' }" @click="game.zone = '微区'">微区</view>
							<view class="lt-chip" :class="{ on: game.zone === 'Q区' }" @click="game.zone = 'Q区'">Q区</view>
						</view>
					</view>
					<view class="lt-form-row">
						<text class="lt-form-label"><text class="req">*</text>游戏昵称</text>
						<input class="lt-form-input" v-model="game.nickname" placeholder="请输入游戏昵称" />
					</view>
					<view class="lt-form-row">
						<text class="lt-form-label"><text class="req">*</text>游戏ID</text>
						<input class="lt-form-input" v-model="game.gameid" placeholder="请输入游戏ID" />
					</view>
					<view class="lt-form-row">
						<text class="lt-form-label">是否指定陪玩</text>
						<view class="lt-form-radio">
							<view class="lt-chip" :class="{ on: !game.assign }" @click="game.assign = false">不指定</view>
							<view class="lt-chip" :class="{ on: game.assign }" @click="game.assign = true">指定</view>
						</view>
					</view>
				</view>
				<view class="lt-dialog-btns">
					<view class="lt-dialog-btn ghost" @click="gameShow = false">返回</view>
					<view class="lt-dialog-btn" @click="confirmGame">确认</view>
				</view>
			</view>
		</view>

		<!-- 奖品外观弹窗 -->
		<view class="lt-mask" v-if="prizeShow" @click="prizeShow = false">
			<view class="lt-dialog" @click.stop>
				<text class="lt-dialog-title">奖品外观</text>
				<image class="lt-appearance-img" :src="currentPrize.icon || '/static/ym/1.png'" mode="aspectFit"></image>
				<text class="lt-appearance-name">{{ currentPrize.name }}</text>
				<view class="lt-appearance-meta">
					<text class="lt-appearance-rate">爆率 {{ currentPrize.rate }}</text>
					<text class="lt-appearance-price">服务价 ¥{{ currentPrize.price || '0' }}</text>
				</view>
				<view class="lt-dialog-btn" @click="prizeShow = false">知道了</view>
			</view>
		</view>

		<!-- 抽奖结果弹窗 -->
		<view class="lt-mask" v-if="resultShow" @click="resultShow = false">
			<view class="lt-dialog" @click.stop>
				<text class="lt-dialog-title">恭喜获得</text>
				<view class="lt-result-list">
					<view class="lt-result-item" v-for="(r, i) in results" :key="i">
						<image class="lt-result-icon" :src="r.icon || '/static/ym/1.png'" mode="aspectFit"></image>
						<text class="lt-result-name">{{ r.name }}</text>
					</view>
				</view>
				<view class="lt-dialog-btn" @click="resultShow = false">知道了</view>
			</view>
		</view>

		<!-- 统一支付弹窗 -->
		<pay-modal
			v-model:visible="payShow"
			:amount="payAmount"
			:balance="userBalance"
			@confirm="onPayConfirm"
		></pay-modal>
	</view>
</template>

<script setup>
	import { ref, computed, onMounted, onUnmounted } from 'vue'
	import { lotteryConfig, lotteryDraw, payCreate, payQuery } from '../../request/api'
	import commonNavbar from '../../compontents/common-navbar.vue'
	import payModal from '../../compontents/pay-modal.vue'
	import payUtil from '../../utils/pay.js'

	const pools = ref([])
	const idx = ref(0)
	const enabled = ref(true)
	const drawing = ref(false)
	const rotation = ref(0)
	const results = ref([])
	const resultShow = ref(false)
	const rulesShow = ref(false)
	const prizeShow = ref(false)
	const currentPrize = ref({})
	const payShow = ref(false)
	const payAmount = ref(0)
	const userBalance = ref(0)
	const gameShow = ref(false)
	const game = ref({ zone: '微区', nickname: '', gameid: '', assign: false })
	const pendingDraw = ref(null)
	const drawTimes = ref(1)

	const navbarTitle = computed(() => (pool.value.mode === 'wheel' ? '转盘抽奖' : '盲盒抽奖'))
	const pool = computed(() => pools.value[idx.value] || { mode: 'wheel', images: [], prizes: [] })
	const poolPriceText = computed(() => Number(pool.value.price || 0).toFixed(2))
	const activePrizes = computed(() => (pool.value.prizes || []).filter(p => p.status === '1'))
	const wheelPrizes = computed(() => {
		const list = activePrizes.value
		return list.length ? list.slice(0, 12) : []
	})

	const segAngle = computed(() => 360 / Math.max(1, wheelPrizes.value.length))
	const segStyle = (i) => ({
		transform: 'rotate(' + (i * segAngle.value) + 'deg)',
		background: i % 2 === 0
			? 'conic-gradient(from -' + (segAngle.value / 2) + 'deg at 50% 100%, #ffe1cf 0deg, #ffe1cf ' + segAngle.value + 'deg, transparent ' + segAngle.value + 'deg)'
			: 'conic-gradient(from -' + (segAngle.value / 2) + 'deg at 50% 100%, #ffd0b3 0deg, #ffd0b3 ' + segAngle.value + 'deg, transparent ' + segAngle.value + 'deg)'
	})
	const segInnerStyle = (i) => ({
		transform: 'rotate(' + (segAngle.value / 2) + 'deg) translateY(-160rpx)'
	})

	onMounted(() => {
		loadConfig()
		loadGame()
		window.addEventListener('focus', onResume)
	})
	onUnmounted(() => {
		window.removeEventListener('focus', onResume)
	})

	const loadConfig = async () => {
		try {
			const res = await lotteryConfig()
			if (res.code == 1 && res.data) {
				pools.value = res.data.pools || []
				enabled.value = String(res.data.enabled) === '1'
			}
		} catch (e) {
			console.error('抽奖配置加载失败', e)
		}
	}

	const loadGame = () => {
		try {
			const saved = uni.getStorageSync('lottery_game_info')
			if (saved) game.value = Object.assign(game.value, saved)
		} catch (e) {}
	}

	const switchPool = (i) => {
		if (drawing.value) return
		idx.value = i
	}

	const previewImages = (i) => {
		if (!pool.value.images || !pool.value.images.length) return
		uni.previewImage({ urls: pool.value.images, current: i })
	}

	const openPrize = (p) => {
		currentPrize.value = p || {}
		prizeShow.value = true
	}

	const openGame = () => { gameShow.value = true }
	const confirmGame = () => {
		if (!game.value.nickname || !game.value.gameid) {
			uni.showToast({ title: '请填写完整游戏信息', icon: 'none' })
			return
		}
		uni.setStorageSync('lottery_game_info', game.value)
		gameShow.value = false
	}

	/* ===== 抽奖流程：先校验游戏信息 → 支付 → 抽奖 ===== */
	const startDraw = () => { beginDraw(1) }
	const startDrawTen = () => { beginDraw(10) }

	const beginDraw = (times) => {
		if (drawing.value) return
		if (!enabled.value) { uni.showToast({ title: '活动暂未开放', icon: 'none' }); return }
		if (!activePrizes.value.length) { uni.showToast({ title: '奖池暂未配置', icon: 'none' }); return }
		if (!game.value.nickname || !game.value.gameid) {
			gameShow.value = true
			return
		}
		drawTimes.value = times
		payAmount.value = Number(pool.value.price || 0) * times
		payShow.value = true
	}

	const onPayConfirm = async (payType) => {
		payShow.value = false
		drawing.value = true
		try {
			const res = await payCreate({
				scene: 'lottery',
				pool_id: pool.value.id,
				times: drawTimes.value,
				pay_type: payType
			})
			if (!res || res.code != 1) {
				uni.showToast({ title: (res && res.msg) || '下单失败', icon: 'none' })
				drawing.value = false
				return
			}
			if (res.data.paid) {
				await doDraw(drawTimes.value)
				drawing.value = false
				return
			}
			// 在线支付：记录待支付订单，跳转收银台
			pendingDraw.value = {
				out_trade_no: res.data.out_trade_no,
				times: drawTimes.value,
				pool_id: pool.value.id,
				created_at: Date.now()
			}
			uni.setStorageSync('pending_lottery_draw', pendingDraw.value)
			payUtil.redirectPay({ url: res.data.url, out_trade_no: res.data.out_trade_no }, (msg) => {
				uni.showToast({ title: msg, icon: 'none' })
			})
			drawing.value = false
		} catch (e) {
			drawing.value = false
			uni.showToast({ title: '支付发起失败，请重试', icon: 'none' })
		}
	}

	const onResume = () => {
		try {
			const p = uni.getStorageSync('pending_lottery_draw')
			if (p && p.out_trade_no) {
				pendingDraw.value = p
				verifyPending()
			}
		} catch (e) {}
	}

	const verifyPending = async () => {
		const p = pendingDraw.value
		if (!p) return
		try {
			const res = await payQuery({ out_trade_no: p.out_trade_no })
			if (res && res.code == 1 && res.data && res.data.paid) {
				uni.removeStorageSync('pending_lottery_draw')
				pendingDraw.value = null
				await doDraw(p.times)
			}
		} catch (e) {}
	}

	const doDraw = async (times) => {
		try {
			const res = await lotteryDraw({ pool_id: pool.value.id, times })
			if (res && res.code == 1 && res.data) {
				results.value = res.data.results || []
				spinTo(results.value)
				setTimeout(() => { resultShow.value = true }, 400)
			} else {
				uni.showToast({ title: (res && res.msg) || '抽奖失败', icon: 'none' })
			}
		} catch (e) {
			uni.showToast({ title: '抽奖失败，请重试', icon: 'none' })
		}
	}

	const spinTo = (list) => {
		if (pool.value.mode !== 'wheel' || !list || !list.length) return
		const n = wheelPrizes.value.length
		if (!n) return
		let i = wheelPrizes.value.findIndex(p => String(p.id) === String(list[0].id))
		if (i < 0) i = 0
		const center = i * segAngle.value + segAngle.value / 2
		const base = rotation.value - (rotation.value % 360)
		rotation.value = base + 360 * 5 + (360 - center)
	}

	defineExpose({})
</script>

<style lang="scss" scoped>
	.lt-page {
		min-height: 100vh;
		background: #f5f6fa;
		display: flex; flex-direction: column;
	}
	.lt-scroll { flex: 1; }
	.lt-body { padding: 0 24rpx; }

	.lt-tabs {
		display: flex; flex-direction: row; gap: 12rpx;
		padding: 16rpx 24rpx 0;
	}
	.lt-tab {
		padding: 12rpx 28rpx; border-radius: 12rpx 12rpx 0 0;
		background: #eceef1;
	}
	.lt-tab.active { background: #fff; }
	.lt-tab-text { font-size: 26rpx; color: #555; font-weight: 600; }
	.lt-tab.active .lt-tab-text { color: #FF6B35; }

	.lt-hero { padding: 30rpx 12rpx 10rpx; text-align: center; }
	.lt-hero-title {
		font-size: 56rpx; font-weight: 900; letter-spacing: 2rpx;
		color: #FF6B35;
	}
	.lt-hero.mode-box .lt-hero-title { color: #f0b90b; }
	.lt-hero-sub { display: flex; align-items: center; justify-content: center; gap: 8rpx; margin-top: 10rpx; }
	.lt-hero-sub-icon {
		width: 30rpx; height: 30rpx; line-height: 30rpx; text-align: center;
		border-radius: 50%; background: #cfd4da; color: #fff; font-size: 20rpx; font-weight: 700;
	}
	.lt-hero-sub-text { font-size: 24rpx; color: #555; }

	.lt-stage { position: relative; display: flex; align-items: center; justify-content: center; padding: 30rpx 0 10rpx; }
	.lt-wheel {
		width: 640rpx; height: 640rpx; border-radius: 50%;
		background: #fff7f2;
		position: relative;
		transition: transform 3.4s cubic-bezier(.2,.7,.2,1);
	}
	.lt-wheel-ring {
		position: absolute; inset: 0; border-radius: 50%;
		border: 16rpx solid #ffd9c2;
		box-shadow: 0 0 30rpx rgba(255,107,53,.35) inset;
	}
	.lt-seg { position: absolute; left: 0; top: 0; width: 640rpx; height: 320rpx; transform-origin: 50% 100%; }
	.lt-seg-inner {
		position: absolute; left: 50%; top: 50%;
		width: 150rpx; margin-left: -75rpx;
		display: flex; flex-direction: column; align-items: center; gap: 6rpx;
	}
	.lt-seg-icon { width: 96rpx; height: 96rpx; border-radius: 12rpx; background: #fff; }
	.lt-seg-name { font-size: 22rpx; color: #5b3a26; font-weight: 600; }
	.lt-pointer {
		position: absolute; top: 12rpx; left: 50%; margin-left: -20rpx;
		width: 0; height: 0;
		border-left: 20rpx solid transparent;
		border-right: 20rpx solid transparent;
		border-top: 40rpx solid #FF6B35;
		z-index: 3;
	}
	.lt-wheel-center {
		position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%);
		width: 140rpx; height: 140rpx; border-radius: 50%;
		background: radial-gradient(circle, #ffe3d2 0%, #ffb38a 60%, #ff8a5c 100%);
		box-shadow: 0 6rpx 20rpx rgba(255,107,53,.4);
		display: flex; flex-direction: column; align-items: center; justify-content: center;
		z-index: 2;
	}
	.lt-wheel-center-text { font-size: 30rpx; font-weight: 800; color: #fff; }
	.lt-wheel-center-sub { font-size: 22rpx; color: #fff; }

	.lt-stage-box { padding: 40rpx 0 10rpx; }
	.lt-box {
		width: 560rpx; height: 560rpx; border-radius: 28rpx;
		background: radial-gradient(circle at 50% 45%, #fff 0%, #eef1f5 70%);
		display: flex; align-items: center; justify-content: center;
	}
	.lt-box-img { width: 80%; height: 80%; }

	.lt-draw-area { margin-top: 20rpx; display: flex; flex-direction: column; align-items: center; gap: 14rpx; }
	.lt-draw-btn {
		min-width: 420rpx; height: 96rpx; border-radius: 48rpx;
		padding: 0 60rpx;
		display: flex; align-items: center; justify-content: center;
		background: linear-gradient(135deg, #FFB038 0%, #FF8A00 100%);
		box-shadow: 0 8rpx 22rpx rgba(255,138,0,.35);
	}
	.lt-draw-btn.disabled { opacity: .5; }
	.lt-draw-btn-text { font-size: 34rpx; font-weight: 800; color: #fff; }
	.lt-draw-ten {
		padding: 14rpx 40rpx; border-radius: 40rpx;
		background: #fff; border: 2rpx solid #ffd9b0;
	}
	.lt-draw-ten.disabled { opacity: .5; }
	.lt-draw-ten-text { font-size: 26rpx; color: #FF8A00; font-weight: 600; }
	.lt-draw-tip { font-size: 24rpx; color: #999; }

	.lt-section {
		background: #fff; border-radius: 20rpx; padding: 24rpx; margin-top: 20rpx;
		box-shadow: 0 4rpx 16rpx rgba(0,0,0,.04);
	}
	.lt-section-head { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 16rpx; }
	.lt-section-title { font-size: 30rpx; font-weight: 700; color: #222; }
	.lt-section-tip { font-size: 22rpx; color: #999; }

	.lt-images { width: 100%; white-space: nowrap; }
	.lt-images-row { display: flex; flex-direction: row; gap: 16rpx; }
	.lt-image-item { width: 180rpx; height: 180rpx; flex-shrink: 0; border-radius: 16rpx; overflow: hidden; background: #f0f0f0; }
	.lt-image { width: 100%; height: 100%; }

	.lt-prizes { width: 100%; white-space: nowrap; }
	.lt-prizes-row { display: flex; flex-direction: row; gap: 16rpx; }
	.lt-prize-card {
		width: 200rpx; flex-shrink: 0; display: flex; flex-direction: column; gap: 8rpx;
	}
	.lt-prize-img { width: 200rpx; height: 200rpx; border-radius: 14rpx; background: #f4f5f7; }
	.lt-prize-name { font-size: 24rpx; color: #333; font-weight: 600; }
	.lt-prize-price { font-size: 24rpx; color: #FF6B35; font-weight: 700; }
	.lt-empty { padding: 30rpx 0; text-align: center; }
	.lt-empty-text { font-size: 26rpx; color: #999; }

	.lt-side-btn {
		position: fixed; right: 0; top: 40%;
		background: rgba(0,0,0,.45); color: #fff;
		padding: 16rpx 10rpx; border-radius: 30rpx 0 0 30rpx;
		z-index: 20;
	}
	.lt-side-text { writing-mode: vertical-rl; font-size: 24rpx; letter-spacing: 2rpx; }

	.lt-game-btn {
		position: fixed; right: 24rpx; bottom: 200rpx;
		width: 130rpx; height: 130rpx; border-radius: 50%;
		background: linear-gradient(135deg, #FFB038 0%, #FF8A00 100%);
		display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4rpx;
		box-shadow: 0 8rpx 22rpx rgba(255,138,0,.4);
		z-index: 20;
	}
	.lt-game-btn-text { font-size: 20rpx; color: #fff; }

	.lt-mask {
		position: fixed; inset: 0; background: rgba(0,0,0,.5);
		display: flex; align-items: center; justify-content: center; z-index: 999;
	}
	.lt-dialog {
		width: 600rpx; max-width: 88vw; background: #fff; border-radius: 24rpx;
		padding: 34rpx 28rpx 28rpx;
		display: flex; flex-direction: column; align-items: center;
	}
	.lt-dialog-title { font-size: 34rpx; font-weight: 800; color: #222; margin-bottom: 22rpx; }
	.lt-dialog-scroll { width: 100%; max-height: 50vh; }
	.lt-rules { font-size: 26rpx; color: #555; line-height: 1.8; }
	.lt-dialog-btn {
		margin-top: 26rpx; width: 100%; height: 84rpx; border-radius: 42rpx;
		background: linear-gradient(135deg, #FFB038 0%, #FF8A00 100%);
		display: flex; align-items: center; justify-content: center;
		color: #fff; font-size: 30rpx; font-weight: 700;
	}
	.lt-dialog-btns { display: flex; flex-direction: row; gap: 20rpx; width: 100%; margin-top: 26rpx; }
	.lt-dialog-btns .lt-dialog-btn { margin-top: 0; }
	.lt-dialog-btn.ghost { background: #f0f1f3; color: #555; }

	.lt-form { width: 100%; display: flex; flex-direction: column; }
	.lt-form-row {
		display: flex; align-items: center; justify-content: space-between;
		padding: 22rpx 0; border-bottom: 1rpx solid #f0f0f0;
	}
	.lt-form-label { font-size: 28rpx; color: #333; }
	.req { color: #ff4d4f; margin-right: 4rpx; }
	.lt-form-radio { display: flex; flex-direction: row; gap: 16rpx; }
	.lt-chip {
		padding: 10rpx 28rpx; border-radius: 40rpx; font-size: 24rpx;
		border: 2rpx solid #ddd; color: #666; background: #fff;
	}
	.lt-chip.on { border-color: #FF8A00; color: #FF8A00; background: #fff7f2; }
	.lt-form-input { text-align: right; font-size: 28rpx; color: #333; flex: 1; }

	.lt-result-list {
		display: flex; flex-direction: row; flex-wrap: wrap; gap: 16rpx; justify-content: center;
		max-height: 50vh; overflow-y: auto; width: 100%;
	}
	.lt-result-item { width: 150rpx; display: flex; flex-direction: column; align-items: center; gap: 8rpx; }
	.lt-result-icon { width: 110rpx; height: 110rpx; border-radius: 12rpx; background: #f5f5f5; }
	.lt-result-name { font-size: 22rpx; color: #333; text-align: center; }

	.lt-appearance-img { width: 420rpx; height: 420rpx; border-radius: 20rpx; background: #f7f7f9; margin-bottom: 18rpx; }
	.lt-appearance-name { font-size: 32rpx; font-weight: 700; color: #222; }
	.lt-appearance-meta { display: flex; flex-direction: row; gap: 28rpx; margin-top: 12rpx; }
	.lt-appearance-rate { font-size: 26rpx; font-weight: 700; color: #FF6B35; }
	.lt-appearance-price { font-size: 26rpx; color: #999; }
</style>
