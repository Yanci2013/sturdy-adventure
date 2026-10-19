<template>
	<view class="lt-page">
		<common-navbar title="传世武器抽奖"></common-navbar>

		<scroll-view scroll-y class="lt-scroll">
			<view class="lt-body">
				<!-- 活动封面 -->
				<view class="lt-cover" @click="onDraw(1)" :class="{ 'is-busy': drawing }">
					<image v-if="cfg.cover" class="lt-cover-img" :src="cfg.cover" mode="aspectFill"></image>
					<view v-else class="lt-cover-fallback">
						<text class="lt-cover-fallback-icon">⚔️</text>
					</view>
					<view class="lt-cover-mask">
						<text class="lt-cover-title">{{ cfg.title || '传世武器抽奖' }}</text>
						<text class="lt-cover-sub">{{ cfg.subtitle || '极品传世武器等你来抽' }}</text>
					</view>
				</view>

				<!-- 抽奖图片（后台自定义） -->
				<view class="lt-section" v-if="images.length">
					<view class="lt-section-head">
						<text class="lt-section-title">奖池展示</text>
					</view>
					<scroll-view scroll-x class="lt-images">
						<view class="lt-images-row">
							<view class="lt-image-item" v-for="(img, i) in images" :key="i" @click="previewImages(i)">
								<image class="lt-image" :src="img" mode="aspectFill"></image>
							</view>
						</view>
					</scroll-view>
				</view>

				<!-- 抽奖操作 -->
				<view class="lt-draw-area">
					<view class="lt-draw-btns">
						<view class="lt-draw-btn" :class="{ disabled: drawing || !enabled }" @click="onDraw(1)">
							<text class="lt-draw-btn-text">抽 1 次</text>
						</view>
						<view class="lt-draw-btn lt-draw-btn-strong" :class="{ disabled: drawing || !enabled }" @click="onDraw(10)">
							<text class="lt-draw-btn-text">抽 10 次</text>
						</view>
					</view>
					<text class="lt-draw-tip" v-if="!enabled">活动暂未开放，敬请期待</text>
				</view>

				<!-- 奖励与爆率 -->
				<view class="lt-section">
					<view class="lt-section-head">
						<text class="lt-section-title">奖励与爆率</text>
						<text class="lt-section-tip">爆率为中奖权重，数值越高越容易中</text>
					</view>
					<view class="lt-prize-list">
						<view class="lt-prize-item" v-for="p in prizes" :key="p.id" @click="openPrize(p)">
							<image class="lt-prize-icon" :src="p.icon || '/static/ym/1.png'" mode="aspectFill"></image>
							<view class="lt-prize-info">
								<text class="lt-prize-name">{{ p.name }}</text>
								<text class="lt-prize-stock">剩余库存：{{ p.stock }} · 点击查看外观</text>
							</view>
							<view class="lt-prize-rate">
								<text class="lt-prize-rate-num">{{ p.rate }}</text>
								<text class="lt-prize-rate-unit">爆率</text>
							</view>
						</view>
						<view class="lt-empty" v-if="!prizes.length">
							<text class="lt-empty-text">奖池暂未配置</text>
						</view>
					</view>
				</view>

				<!-- 规则 -->
				<view class="lt-section" v-if="cfg.rules">
					<view class="lt-section-head"><text class="lt-section-title">活动规则</text></view>
					<text class="lt-rules">{{ cfg.rules }}</text>
				</view>

				<view style="height: 60rpx;"></view>
			</view>
		</scroll-view>

		<!-- 奖品外观详情弹窗 -->
		<view class="lt-result-mask" v-if="prizeShow" @click="closePrize">
			<view class="lt-result" @click.stop>
				<text class="lt-prize-detail-title">奖品外观</text>
				<image class="lt-prize-detail-img" :src="currentPrize.icon || '/static/ym/1.png'" mode="aspectFit"></image>
				<text class="lt-prize-detail-name">{{ currentPrize.name }}</text>
				<view class="lt-prize-detail-meta">
					<text class="lt-prize-detail-rate">爆率 {{ currentPrize.rate }}</text>
					<text class="lt-prize-detail-stock">剩余库存 {{ currentPrize.stock }}</text>
				</view>
				<view class="lt-result-btn" @click="closePrize">知道了</view>
			</view>
		</view>

		<!-- 抽奖结果弹窗 -->
		<view class="lt-result-mask" v-if="resultShow" @click="closeResult">
			<view class="lt-result" @click.stop>
				<text class="lt-result-title">恭喜获得</text>
				<view class="lt-result-list">
					<view class="lt-result-item" v-for="(r, i) in results" :key="i">
						<image class="lt-result-icon" :src="r.icon || '/static/ym/1.png'" mode="aspectFit"></image>
						<text class="lt-result-name">{{ r.name }}</text>
					</view>
				</view>
				<view class="lt-result-btn" @click="closeResult">知道了</view>
			</view>
		</view>
	</view>
</template>

<script setup>
	import { ref, computed, onMounted } from 'vue'
	import { lotteryConfig, lotteryDraw } from '../../request/api'
	import commonNavbar from '../../compontents/common-navbar.vue'

	const cfg = ref({})
	const prizes = ref([])
	const drawing = ref(false)
	const results = ref([])
	const resultShow = ref(false)

	const enabled = computed(() => String(cfg.value.enabled) === '1')
	const images = computed(() => cfg.value.images || [])

	onMounted(() => {
		loadConfig()
	})

	const loadConfig = async () => {
		try {
			const res = await lotteryConfig()
			if (res.code == 1 && res.data) {
				cfg.value = res.data
				prizes.value = (res.data.prizes || []).filter(p => p.status === '1')
			}
		} catch (e) {
			console.error('抽奖配置加载失败', e)
		}
	}

	const onDraw = async (times) => {
		if (drawing.value) return
		if (!enabled.value) {
			uni.showToast({ title: '活动暂未开放', icon: 'none' })
			return
		}
		drawing.value = true
		try {
			const res = await lotteryDraw({ times })
			if (res.code == 1) {
				results.value = (res.data && res.data.results) || []
				resultShow.value = true
			}
		} catch (e) {
			uni.showToast({ title: '抽奖失败，请重试', icon: 'none' })
		} finally {
			drawing.value = false
		}
	}

	const closeResult = () => {
		resultShow.value = false
	}

	const prizeShow = ref(false)
	const currentPrize = ref({})
	const openPrize = (p) => {
		currentPrize.value = p || {}
		prizeShow.value = true
	}
	const closePrize = () => {
		prizeShow.value = false
	}
	const previewImages = (i) => {
		if (!images.value.length) return
		uni.previewImage({ urls: images.value, current: i })
	}
</script>

<style lang="scss" scoped>
	.lt-page {
		min-height: 100vh;
		background: #f5f6fa;
		display: flex;
		flex-direction: column;
	}
	.lt-scroll { flex: 1; }
	.lt-body { padding: 20rpx 24rpx 0; }

	.lt-cover {
		position: relative;
		width: 100%;
		height: 300rpx;
		border-radius: 20rpx;
		overflow: hidden;
		background: linear-gradient(135deg, #FF7A45 0%, #FF9D6E 100%);
		box-shadow: 0 8rpx 24rpx rgba(255, 122, 69, 0.25);
	}
	.lt-cover-img { width: 100%; height: 100%; }
	.lt-cover-fallback {
		width: 100%; height: 100%;
		display: flex; align-items: center; justify-content: center;
	}
	.lt-cover-fallback-icon { font-size: 100rpx; }
	.lt-cover-mask {
		position: absolute; left: 0; right: 0; bottom: 0;
		padding: 30rpx 24rpx 20rpx;
		background: linear-gradient(180deg, rgba(0,0,0,0) 0%, rgba(0,0,0,0.5) 100%);
		display: flex; flex-direction: column; gap: 6rpx;
	}
	.lt-cover-title { font-size: 40rpx; font-weight: 800; color: #fff; }
	.lt-cover-sub { font-size: 24rpx; color: rgba(255,255,255,0.9); }

	.lt-section {
		background: #fff;
		border-radius: 20rpx;
		padding: 24rpx;
		margin-top: 20rpx;
		box-shadow: 0 4rpx 16rpx rgba(0,0,0,0.04);
	}
	.lt-section-head {
		display: flex; align-items: baseline; justify-content: space-between;
		margin-bottom: 16rpx;
	}
	.lt-section-title { font-size: 30rpx; font-weight: 700; color: #222; }
	.lt-section-tip { font-size: 22rpx; color: #999; }

	.lt-images { width: 100%; white-space: nowrap; }
	.lt-images-row { display: flex; flex-direction: row; gap: 16rpx; }
	.lt-image-item {
		width: 180rpx; height: 180rpx; flex-shrink: 0;
		border-radius: 16rpx; overflow: hidden;
		background: #f0f0f0;
	}
	.lt-image { width: 100%; height: 100%; }

	.lt-draw-area { margin-top: 24rpx; }
	.lt-draw-btns { display: flex; flex-direction: row; gap: 20rpx; }
	.lt-draw-btn {
		flex: 1; height: 92rpx; border-radius: 46rpx;
		display: flex; align-items: center; justify-content: center;
		background: linear-gradient(135deg, #FFB38A 0%, #FF9D6E 100%);
		box-shadow: 0 6rpx 18rpx rgba(255, 157, 110, 0.3);
	}
	.lt-draw-btn-strong {
		background: linear-gradient(135deg, #FF7A45 0%, #FF5A1F 100%);
		box-shadow: 0 6rpx 18rpx rgba(255, 122, 69, 0.35);
	}
	.lt-draw-btn-text { font-size: 30rpx; font-weight: 700; color: #fff; }
	.lt-draw-btn.disabled { opacity: 0.5; }
	.lt-draw-tip { display: block; text-align: center; font-size: 24rpx; color: #999; margin-top: 12rpx; }

	.lt-prize-list { display: flex; flex-direction: column; gap: 12rpx; }
	.lt-prize-item {
		display: flex; flex-direction: row; align-items: center; gap: 16rpx;
		padding: 16rpx; border-radius: 14rpx;
		background: #fafbfc;
	}
	.lt-prize-icon {
		width: 88rpx; height: 88rpx; border-radius: 12rpx;
		background: #eee; flex-shrink: 0;
	}
	.lt-prize-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6rpx; }
	.lt-prize-name { font-size: 28rpx; color: #222; font-weight: 600; }
	.lt-prize-stock { font-size: 22rpx; color: #999; }
	.lt-prize-rate { display: flex; flex-direction: column; align-items: flex-end; }
	.lt-prize-rate-num { font-size: 32rpx; font-weight: 800; color: #FF7A45; }
	.lt-prize-rate-unit { font-size: 20rpx; color: #999; }

	.lt-empty { padding: 40rpx 0; text-align: center; }
	.lt-empty-text { font-size: 26rpx; color: #999; }

	.lt-rules { font-size: 24rpx; color: #666; line-height: 1.7; }

	.lt-result-mask {
		position: fixed; left: 0; top: 0; right: 0; bottom: 0;
		background: rgba(0,0,0,0.5);
		display: flex; align-items: center; justify-content: center;
		z-index: 999;
	}
	.lt-result {
		width: 600rpx; max-width: 86vw;
		background: #fff; border-radius: 24rpx;
		padding: 36rpx 28rpx 28rpx;
		display: flex; flex-direction: column; align-items: center;
	}
	.lt-result-title { font-size: 34rpx; font-weight: 800; color: #FF7A45; margin-bottom: 20rpx; }
	.lt-result-list {
		display: flex; flex-direction: row; flex-wrap: wrap; gap: 16rpx;
		justify-content: center; max-height: 50vh; overflow-y: auto;
	}
	.lt-result-item {
		width: 140rpx; display: flex; flex-direction: column; align-items: center; gap: 8rpx;
	}
	.lt-result-icon { width: 100rpx; height: 100rpx; border-radius: 12rpx; background: #f5f5f5; }
	.lt-result-name { font-size: 22rpx; color: #333; text-align: center; }
	.lt-result-btn {
		margin-top: 30rpx; width: 100%; height: 80rpx; border-radius: 40rpx;
		background: linear-gradient(135deg, #FF7A45 0%, #FF9D6E 100%);
		display: flex; align-items: center; justify-content: center;
		color: #fff; font-size: 28rpx; font-weight: 600;
	}

	.lt-prize-detail-title { font-size: 32rpx; font-weight: 800; color: #222; margin-bottom: 20rpx; }
	.lt-prize-detail-img {
		width: 420rpx; height: 420rpx; border-radius: 20rpx;
		background: #f7f7f9; margin-bottom: 20rpx;
	}
	.lt-prize-detail-name { font-size: 32rpx; font-weight: 700; color: #222; }
	.lt-prize-detail-meta { display: flex; flex-direction: row; gap: 28rpx; margin-top: 14rpx; }
	.lt-prize-detail-rate { font-size: 26rpx; font-weight: 700; color: #FF7A45; }
	.lt-prize-detail-stock { font-size: 26rpx; color: #999; }
</style>
