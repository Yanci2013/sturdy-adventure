<template>
	<view class="pm-mask" v-if="visible" @click="onClose">
		<view class="pm-sheet" @click.stop>
			<view class="pm-head">
				<text class="pm-title">{{ title || '选择支付方式' }}</text>
				<view class="pm-close" @click="onClose">
					<up-icon name="close" size="40rpx" color="#333"></up-icon>
				</view>
			</view>

			<view class="pm-amount" v-if="amountText">
				<text class="pm-amount-label">应付金额</text>
				<text class="pm-amount-num">¥{{ amountText }}</text>
			</view>

			<view class="pm-list">
				<view
					class="pm-item"
					:class="{ 'is-on': picked === m.key }"
					v-for="m in methodList"
					:key="m.key"
					@click="picked = m.key"
				>
					<view class="pm-item-icon" :style="{ background: m.color }">
						<text class="pm-item-icon-text">{{ m.emoji }}</text>
					</view>
					<view class="pm-item-info">
						<text class="pm-item-name">{{ m.name }}</text>
						<text class="pm-item-sub">{{ m.key === 'balance' ? ('当前余额：' + balanceText) : m.sub }}</text>
					</view>
					<view class="pm-radio" :class="{ on: picked === m.key }"></view>
				</view>
				<view class="pm-empty" v-if="!methodList.length">
					<text class="pm-empty-text">暂未配置可用支付方式</text>
				</view>
			</view>

			<view class="pm-btn" :class="{ disabled: !picked }" @click="onConfirm">
				<text class="pm-btn-text">确认支付</text>
			</view>
		</view>
	</view>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { payConfig } from '../request/api'

const props = defineProps({
	visible: { type: Boolean, default: false },
	title: { type: String, default: '选择支付方式' },
	amount: { type: [Number, String], default: 0 },
	balance: { type: [Number, String], default: 0 },
	methods: { type: Array, default: () => [] }
})

const emit = defineEmits(['update:visible', 'confirm', 'close'])

const ALL_METHODS = {
	balance: { key: 'balance', name: '余额支付', sub: '使用账户余额支付', emoji: '¥', color: '#1677ff' },
	epay_wxpay: { key: 'epay_wxpay', name: '微信支付', sub: '使用微信支付', emoji: '微', color: '#22c55e' },
	wechat: { key: 'epay_wxpay', name: '微信支付', sub: '使用微信支付', emoji: '微', color: '#22c55e' },
	wxpay: { key: 'epay_wxpay', name: '微信支付', sub: '使用微信支付', emoji: '微', color: '#22c55e' },
	native_wechat: { key: 'epay_wxpay', name: '微信支付', sub: '使用微信支付', emoji: '微', color: '#22c55e' },
	epay_alipay: { key: 'epay_alipay', name: '支付宝支付', sub: '使用支付宝支付', emoji: '支', color: '#1677ff' },
	alipay: { key: 'epay_alipay', name: '支付宝支付', sub: '使用支付宝支付', emoji: '支', color: '#1677ff' },
	epay_qqpay: { key: 'epay_qqpay', name: 'QQ钱包', sub: '使用QQ钱包支付', emoji: 'Q', color: '#12b7f5' },
	qqpay: { key: 'epay_qqpay', name: 'QQ钱包', sub: '使用QQ钱包支付', emoji: 'Q', color: '#12b7f5' },
	epay_bank: { key: 'epay_bank', name: '云闪付', sub: '使用云闪付支付', emoji: '闪', color: '#e11d48' },
	bank: { key: 'epay_bank', name: '云闪付', sub: '使用云闪付支付', emoji: '闪', color: '#e11d48' }
}

const remoteMethods = ref([])
const picked = ref('')
const loaded = ref(false)

const amountText = computed(() => {
	const n = Number(props.amount || 0)
	return n > 0 ? n.toFixed(2) : ''
})
const balanceText = computed(() => Number(props.balance || 0).toFixed(2))

const methodList = computed(() => {
	const keys = props.methods && props.methods.length ? props.methods : remoteMethods.value
	if (!keys || !keys.length) return []
	const seen = {}
	const list = []
	keys.forEach(k => {
		const m = ALL_METHODS[k]
		if (m && !seen[m.key]) {
			seen[m.key] = true
			list.push(m)
		}
	})
	return list
})

const loadMethods = async () => {
	if (props.methods && props.methods.length) return
	try {
		const res = await payConfig()
		if (res && res.code == 1 && res.data) {
			remoteMethods.value = res.data.pay_methods || []
		}
	} catch (e) {
		remoteMethods.value = ['balance', 'epay_wxpay']
	}
	loaded.value = true
}

watch(() => props.visible, (v) => {
	if (v) {
		loadMethods()
		if (!picked.value && methodList.value.length) picked.value = methodList.value[0].key
	}
})

const onClose = () => {
	emit('update:visible', false)
	emit('close')
}
const onConfirm = () => {
	if (!picked.value) {
		uni.showToast({ title: '请选择支付方式', icon: 'none' })
		return
	}
	emit('confirm', picked.value)
}
</script>

<style lang="scss" scoped>
.pm-mask {
	position: fixed; left: 0; top: 0; right: 0; bottom: 0;
	background: rgba(0, 0, 0, 0.5);
	z-index: 999;
	display: flex; flex-direction: column; justify-content: flex-end;
}
.pm-sheet {
	background: #fff;
	border-radius: 28rpx 28rpx 0 0;
	padding: 34rpx 28rpx calc(34rpx + env(safe-area-inset-bottom));
	max-height: 80vh;
	display: flex; flex-direction: column;
}
.pm-head {
	display: flex; align-items: center; justify-content: center;
	position: relative; margin-bottom: 20rpx;
}
.pm-title { font-size: 34rpx; font-weight: 700; color: #222; }
.pm-close {
	position: absolute; right: 0; top: -6rpx;
	padding: 6rpx;
}
.pm-amount {
	display: flex; align-items: baseline; justify-content: center; gap: 12rpx;
	margin-bottom: 24rpx;
}
.pm-amount-label { font-size: 24rpx; color: #999; }
.pm-amount-num { font-size: 48rpx; font-weight: 800; color: #FF6B35; }
.pm-list { display: flex; flex-direction: column; gap: 16rpx; overflow-y: auto; }
.pm-item {
	display: flex; flex-direction: row; align-items: center; gap: 20rpx;
	padding: 22rpx 24rpx; border-radius: 16rpx;
	background: #f7f8fa; border: 2rpx solid transparent;
}
.pm-item.is-on { background: #fff7f2; border-color: #FF9D6E; }
.pm-item-icon {
	width: 72rpx; height: 72rpx; border-radius: 16rpx;
	display: flex; align-items: center; justify-content: center;
	flex-shrink: 0;
}
.pm-item-icon-text { color: #fff; font-size: 32rpx; font-weight: 700; }
.pm-item-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6rpx; }
.pm-item-name { font-size: 30rpx; font-weight: 600; color: #222; }
.pm-item-sub { font-size: 22rpx; color: #999; }
.pm-radio {
	width: 40rpx; height: 40rpx; border-radius: 50%;
	border: 2rpx solid #d0d0d0; flex-shrink: 0;
	position: relative;
}
.pm-radio.on { border-color: #FF6B35; background: #FF6B35; }
.pm-radio.on::after {
	content: ''; position: absolute; left: 50%; top: 50%;
	width: 18rpx; height: 10rpx;
	border-left: 4rpx solid #fff; border-bottom: 4rpx solid #fff;
	transform: translate(-50%, -60%) rotate(-45deg);
}
.pm-empty { padding: 40rpx 0; text-align: center; }
.pm-empty-text { font-size: 26rpx; color: #999; }
.pm-btn {
	margin-top: 28rpx; height: 92rpx; border-radius: 46rpx;
	background: linear-gradient(135deg, #FFB038 0%, #FF8A00 100%);
	display: flex; align-items: center; justify-content: center;
	box-shadow: 0 8rpx 20rpx rgba(255, 138, 0, 0.3);
}
.pm-btn.disabled { opacity: 0.5; }
.pm-btn-text { font-size: 32rpx; font-weight: 700; color: #fff; }
</style>
