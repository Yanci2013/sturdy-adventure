<template>
	<view v-if="ready && !hidden" class="nav-root" :class="'nav-' + style">
		<!-- ① 经典贴底栏 -->
		<view v-if="style === 'classic_bar'" class="cb-bar">
			<view v-for="(it, i) in items" :key="i" class="cb-item" :class="{ on: current === i }" @click="go(i)">
				<image class="cb-ic" :src="icon(it, i)" mode="aspectFit"></image>
				<text class="cb-tx">{{ it.text }}</text>
			</view>
		</view>

		<!-- ② 悬浮胶囊 · 橙色高亮 -->
		<view v-else-if="style === 'float_ellipse'" class="fe-wrap">
			<view class="fe-pill">
				<view v-for="(it, i) in items" :key="i" class="fe-item" :class="{ on: current === i }" @click="go(i)">
					<image class="fe-ic" :src="icon(it, i)" mode="aspectFit"></image>
					<text class="fe-tx" :style="current === i ? { color: it.color || BRAND } : {}">{{ it.text }}</text>
				</view>
			</view>
		</view>

		<!-- ③ 悬浮胶囊 · 黄色描边 -->
		<view v-else-if="style === 'float_pill_yellow'" class="fy-wrap">
			<view class="fy-pill">
				<view v-for="(it, i) in items" :key="i" class="fy-item" @click="go(i)">
					<view class="fy-icbox" :class="{ on: current === i }">
						<image class="fy-ic" :src="icon(it, i)" mode="aspectFit"></image>
					</view>
					<text class="fy-tx" :class="{ on: current === i }">{{ it.text }}</text>
				</view>
			</view>
		</view>

		<!-- ④ 中间凸起按钮 -->
		<view v-else-if="style === 'center_raise'" class="cr-bar">
			<view v-for="(it, i) in items" :key="i" class="cr-item" :class="{ on: current === i, center: i === centerIndex }" @click="go(i)">
				<view v-if="i === centerIndex" class="cr-raise">
					<image class="cr-raise-ic" :src="icon(it, i)" mode="aspectFit"></image>
				</view>
				<template v-else>
					<image class="cr-ic" :src="icon(it, i)" mode="aspectFit"></image>
					<text class="cr-tx">{{ it.text }}</text>
				</template>
			</view>
		</view>

		<!-- ⑤ 手绘黄色 -->
		<view v-else class="hd-bar">
			<view v-for="(it, i) in items" :key="i" class="hd-item" @click="go(i)">
				<view class="hd-icbox" :class="{ on: current === i }">
					<image class="hd-ic" :src="icon(it, i)" mode="aspectFit"></image>
				</view>
				<text class="hd-tx" :class="{ on: current === i }">{{ it.text }}</text>
			</view>
		</view>
	</view>
</template>
<script setup>
	import { ref, computed, onMounted, onUnmounted } from 'vue'

	const props = defineProps({
		current: { type: Number, default: 0 },
		placeholder: { type: Boolean, default: true }
	})

	const BRAND = '#F25109'
	const GOLD = '#E6A700'
	const DARK = '#222222'
	const GREY = '#9AA0A6'
	const GREY2 = '#B3B3B3'

	const DEFAULT_ITEMS = [
		{ text: '首页', icon: 'home', url: '/pages/index/index' },
		{ text: '分类', icon: 'grid', url: '/pages/classify/index' },
		{ text: '挑选打手', icon: 'gamepad', url: '/pages/player/index' },
		{ text: '消息', icon: 'chat', url: '/pages/message/index' },
		{ text: '我的', icon: 'user', url: '/pages/mine/index' }
	]

	// 线性图标库（24x24，stroke 由颜色注入）
	const ICONS = {
		home: '<path d="M3.5 11.2 12 4.2l8.5 7"/><path d="M5.6 9.8v9.4h12.8V9.8"/><path d="M10 19.2v-4.4h4v4.4"/>',
		grid: '<rect x="3.6" y="3.6" width="7" height="7" rx="2.2"/><rect x="13.4" y="3.6" width="7" height="7" rx="2.2"/><rect x="3.6" y="13.4" width="7" height="7" rx="2.2"/><rect x="13.4" y="13.4" width="7" height="7" rx="2.2"/>',
		gamepad: '<path d="M8 8.5h8a4.8 4.8 0 0 1 4.6 5.9l-.6 2.6A2.2 2.2 0 0 1 17.8 18c-.9 0-1.4-.5-2-1l-1-1H9.2l-1 1c-.6.5-1.1 1-2 1a2.2 2.2 0 0 1-2.2-1.1l-.6-2.6A4.8 4.8 0 0 1 8 8.5z"/><path d="M7.5 11v3M6 12.5h3"/><circle cx="16" cy="11.6" r=".9"/><circle cx="18" cy="13.6" r=".9"/>',
		chat: '<path d="M4 5h16v11H9l-5 4z"/><path d="M8 10h8"/>',
		user: '<circle cx="12" cy="8" r="4"/><path d="M4.5 20.5v-1a6 6 0 0 1 6-6h3a6 6 0 0 1 6 6v1"/>',
		cart: '<circle cx="9.5" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2.5 3.5h2.6l2.4 11.4h11l2-7.4H6"/>',
		bag: '<rect x="4" y="8" width="16" height="12" rx="2.4"/><path d="M8.5 8V6.2a3.5 3.5 0 0 1 7 0V8"/>',
		heart: '<path d="M12 20s-7-4.4-9.3-8.3A4.9 4.9 0 0 1 12 6.2a4.9 4.9 0 0 1 9.3 5.5C19 15.6 12 20 12 20z"/>',
		headset: '<path d="M4.5 13.2v-1.2a7.5 7.5 0 0 1 15 0v1.2"/><rect x="2.8" y="13.2" width="4" height="6" rx="2"/><rect x="17.2" y="13.2" width="4" height="6" rx="2"/>',
		clipboard: '<rect x="5" y="4.5" width="14" height="16" rx="2.4"/><path d="M9 4.5a3 3 0 0 1 6 0"/><path d="M9 12.5l2 2 4-4.2"/>',
		spark: '<path d="M12 3.5l1.7 4.8 4.8 1.7-4.8 1.7L12 16.5l-1.7-4.8L5.5 10l4.8-1.7z"/>'
	}
	const ICON_ALIAS = {
		classify: 'grid', category: 'grid', message: 'chat', msg: 'chat',
		mine: 'user', my: 'user', player: 'gamepad', thug: 'gamepad',
		order: 'cart', point: 'cart', ta: 'heart', cs: 'headset', service: 'headset'
	}

	const items = ref([])
	const style = ref('float_ellipse')
	const ready = ref(false)
	const hidden = ref(false)
	const timers = []

	function svgUri(name, color) {
		const body = ICONS[name] || ICONS.home
		const svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="' +
			color + '" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' + body + '</svg>'
		return 'data:image/svg+xml,' + encodeURIComponent(svg)
	}
	function normIcon(n) {
		if (!n) return 'home'
		return ICONS[n] ? n : (ICON_ALIAS[n] || 'home')
	}
	function activeColor(it) {
		if (style.value === 'float_pill_yellow') return GOLD
		if (style.value === 'hand_drawn') return DARK
		if (style.value === 'classic_bar') return DARK
		return (it && it.color) || BRAND
	}
	function inactiveColor() {
		if (style.value === 'float_ellipse') return GREY2
		return GREY
	}
	function icon(it, i) {
		const active = props.current === i
		return svgUri(normIcon(it && it.icon), active ? activeColor(it) : inactiveColor())
	}
	const centerIndex = computed(() => Math.floor((items.value.length || 1) / 2))

	function readConfig() {
		const w = (typeof window !== 'undefined') ? window : {}
		const nav = w.__floatingNav || []
		style.value = w.__navStyle || 'float_ellipse'
		if (nav && nav.length) {
			items.value = nav.map((it, i) => ({
				text: (it && it.text) || (DEFAULT_ITEMS[i] && DEFAULT_ITEMS[i].text) || '',
				icon: (it && it.icon) || (DEFAULT_ITEMS[i] && DEFAULT_ITEMS[i].icon) || 'home',
				color: (it && it.color) || '',
				link: (it && it.link) || (DEFAULT_ITEMS[i] && DEFAULT_ITEMS[i].url) || ''
			}))
		} else {
			items.value = DEFAULT_ITEMS.map(it => ({ text: it.text, icon: it.icon, color: '', link: it.url }))
		}
		const h = (w.location && w.location.hash) || ''
		hidden.value = /\/login\//i.test(h)
		ready.value = true
	}

	function go(i) {
		const it = items.value[i]
		if (!it || !it.link) return
		const url = it.link.charAt(0) === '/' ? it.link.slice(1) : it.link
		uni.reLaunch({ url: '/' + url })
	}

	onMounted(() => {
		readConfig()
		if (typeof window !== 'undefined') {
			window.__onNavConfig = readConfig
			let tries = 0
			const t = setInterval(() => {
				tries++
				if (window.__floatingNav && window.__floatingNav.length) {
					readConfig()
					clearInterval(t)
				} else if (tries > 40) {
					clearInterval(t)
				}
			}, 200)
			timers.push(t)
		}
	})
	onUnmounted(() => {
		timers.forEach(t => clearInterval(t))
	})
</script>
<style scoped>
	/* ========== 通用 ========== */
	.nav-root {
		display: block;
	}
	.nav-root image {
		display: block;
	}

	/* ========== ① 经典贴底栏 ========== */
	.nav-classic_bar .cb-bar {
		position: fixed;
		left: 0;
		right: 0;
		bottom: 0;
		z-index: 9999;
		display: flex;
		align-items: center;
		background: #FFFFFF;
		border-top: 1rpx solid #EDEDED;
		box-shadow: 0 -2rpx 12rpx rgba(0, 0, 0, 0.03);
		padding-bottom: env(safe-area-inset-bottom);
	}
	.cb-item {
		flex: 1;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		gap: 6rpx;
		padding: 14rpx 0 12rpx;
	}
	.cb-ic {
		width: 46rpx;
		height: 46rpx;
	}
	.cb-tx {
		font-size: 22rpx;
		line-height: 1;
		color: #9AA0A6;
	}
	.cb-item.on .cb-tx {
		color: #222222;
		font-weight: 600;
	}

	/* ========== ② 悬浮胶囊 · 橙色高亮 ========== */
	.nav-float_ellipse .fe-wrap {
		position: fixed;
		left: 0;
		right: 0;
		bottom: calc(env(safe-area-inset-bottom, 0px) + 16rpx);
		z-index: 9999;
		display: flex;
		justify-content: center;
		padding: 0 24rpx;
	}
	.fe-pill {
		display: flex;
		align-items: center;
		justify-content: space-around;
		width: 100%;
		max-width: 690rpx;
		height: 112rpx;
		background: #FFFFFF;
		border-radius: 999rpx;
		box-shadow: 0 10rpx 36rpx rgba(0, 0, 0, 0.14);
	}
	.fe-item {
		flex: 1;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		gap: 4rpx;
		border-radius: 999rpx;
		padding: 8rpx 0;
	}
	.fe-ic {
		width: 46rpx;
		height: 46rpx;
	}
	.fe-tx {
		font-size: 20rpx;
		line-height: 1;
		color: #B3B3B3;
	}
	.fe-item.on .fe-tx {
		font-weight: 700;
	}

	/* ========== ③ 悬浮胶囊 · 黄色描边 ========== */
	.nav-float_pill_yellow .fy-wrap {
		position: fixed;
		left: 0;
		right: 0;
		bottom: calc(env(safe-area-inset-bottom, 0px) + 16rpx);
		z-index: 9999;
		display: flex;
		justify-content: center;
		padding: 0 24rpx;
	}
	.fy-pill {
		display: flex;
		align-items: center;
		justify-content: space-around;
		width: 100%;
		max-width: 690rpx;
		height: 124rpx;
		background: #FFFFFF;
		border-radius: 999rpx;
		box-shadow: 0 10rpx 36rpx rgba(0, 0, 0, 0.12);
	}
	.fy-item {
		flex: 1;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		gap: 4rpx;
	}
	.fy-icbox {
		width: 60rpx;
		height: 60rpx;
		border-radius: 20rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		background: transparent;
		transition: background 0.2s ease;
	}
	.fy-icbox.on {
		background: #FFF3C4;
	}
	.fy-ic {
		width: 46rpx;
		height: 46rpx;
	}
	.fy-tx {
		font-size: 20rpx;
		line-height: 1;
		color: #9AA0A6;
	}
	.fy-tx.on {
		color: #E6A700;
		font-weight: 700;
	}

	/* ========== ④ 中间凸起按钮 ========== */
	.nav-center_raise .cr-bar {
		position: fixed;
		left: 0;
		right: 0;
		bottom: 0;
		z-index: 9999;
		display: flex;
		align-items: flex-end;
		background: #FFFFFF;
		border-top-left-radius: 32rpx;
		border-top-right-radius: 32rpx;
		box-shadow: 0 -8rpx 32rpx rgba(0, 0, 0, 0.08);
		padding: 0 16rpx env(safe-area-inset-bottom);
	}
	.cr-item {
		flex: 1;
		position: relative;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: flex-end;
		gap: 6rpx;
		height: 120rpx;
		padding-bottom: 18rpx;
	}
	.cr-ic {
		width: 48rpx;
		height: 48rpx;
	}
	.cr-tx {
		font-size: 20rpx;
		line-height: 1;
		color: #9AA0A6;
	}
	.cr-item.on .cr-tx {
		color: #222222;
		font-weight: 600;
	}
	.cr-raise {
		position: absolute;
		top: -52rpx;
		width: 116rpx;
		height: 116rpx;
		border-radius: 50%;
		background: #FFFFFF;
		border: 8rpx solid #FFFFFF;
		box-shadow: 0 -8rpx 28rpx rgba(0, 0, 0, 0.16);
		display: flex;
		align-items: center;
		justify-content: center;
	}
	.cr-raise-ic {
		width: 56rpx;
		height: 56rpx;
	}

	/* ========== ⑤ 手绘黄色 ========== */
	.nav-hand_drawn .hd-bar {
		position: fixed;
		left: 0;
		right: 0;
		bottom: 0;
		z-index: 9999;
		display: flex;
		align-items: center;
		background: #FFFFFF;
		border-top: 1rpx solid rgba(0, 0, 0, 0.04);
		padding: 10rpx 12rpx env(safe-area-inset-bottom);
	}
	.hd-item {
		flex: 1;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		gap: 6rpx;
	}
	.hd-icbox {
		width: 60rpx;
		height: 60rpx;
		border-radius: 20rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		background: transparent;
		transition: background 0.2s ease;
	}
	.hd-icbox.on {
		background: #FFE15C;
	}
	.hd-ic {
		width: 46rpx;
		height: 46rpx;
	}
	.hd-tx {
		font-size: 22rpx;
		line-height: 1;
		color: #9AA0A6;
	}
	.hd-tx.on {
		color: #222222;
		font-weight: 600;
	}
</style>
