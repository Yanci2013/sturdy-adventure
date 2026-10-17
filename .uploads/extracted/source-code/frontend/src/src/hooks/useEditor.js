import { ref, nextTick, onMounted } from 'vue'

export function useEditor() {
	const scrollTop = ref(10000)

	async function scrollToBottom() {
		const cpages = getCurrentPages()
		const croute = cpages[cpages.length - 1]?.route
		if (!(croute.includes('pages/chat/single') || croute.includes('pages/chat/group') || croute.includes('pagesA/im/chat'))) {
			return
		}
		await nextTick()
		await new Promise(resolve => setTimeout(resolve, 300))
		await uni.pageScrollTo({ scrollTop: scrollTop.value, duration: 50 })
		scrollTop.value += 1000
	}

	async function getFootHeight(placeholderHeight, isBottom = true) {
		await nextTick()
		await new Promise(resolve => setTimeout(resolve, 20))
		const query = uni.createSelectorQuery()
		const rect = await new Promise((resolve) => {
			query.select('.xbc-editor').boundingClientRect(resolve).exec()
		})
		placeholderHeight.value = `${rect?.height}px`
		isBottom && await scrollToBottom()
	}

	function listenKeyboard(res) {
		if (res.height > 0) {
			uni.setStorageSync('uni_keyboard_height', res.height)
			// #ifndef H5
			uni.offKeyboardHeightChange(listenKeyboard)
			// #endif
		}
	}

	onMounted(() => {
		scrollToBottom()
		// #ifndef H5
		uni.onKeyboardHeightChange(listenKeyboard)
		// #endif
	})

	return { scrollToBottom, getFootHeight }
}
