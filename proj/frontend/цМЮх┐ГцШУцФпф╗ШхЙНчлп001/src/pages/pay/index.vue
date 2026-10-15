<template>
	<web-view v-if="payUrl" :src="payUrl" />
</template>

<script setup>
import { ref } from 'vue'
import { onLoad, onUnload } from '@dcloudio/uni-app'
import { syncPendingPayment } from '@/utils/pay.js'

const payUrl = ref('')

onLoad((options) => {
	let url = options.url || ''
	try {
		url = decodeURIComponent(url)
	} catch (e) {}
	if (!/^https?:\/\//i.test(url)) {
		uni.showToast({ title: '支付地址无效', icon: 'none' })
		return
	}
	payUrl.value = url
})

onUnload(() => {
	syncPendingPayment()
})
</script>
