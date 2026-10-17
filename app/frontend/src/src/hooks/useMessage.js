import { computed } from 'vue'

export function useMessage(props) {
	const isSenderThemselve = computed(() => {
		const userId = String(uni.getStorageSync('userId') || uni.getStorageSync('user_id') || '')
		const senderId = String(props?.message?.senderId || '')
		return userId !== '' && senderId !== '' && userId === senderId
	})

	// HTML 解码函数 - 使用简单的字符串替换
	function htmlDecode(str) {
		if (!str) return str
		return str.replace(/&quot;/g, '"')
		          .replace(/&amp;/g, '&')
		          .replace(/&lt;/g, '<')
		          .replace(/&gt;/g, '>')
		          .replace(/&nbsp;/g, ' ')
	}

	const messageContent = computed(() => {
		try {
			let content = props?.message?.content || props?.message?.extra || '{}'
			content = htmlDecode(content)
			let parsed = typeof content === 'string' ? JSON.parse(content) : content
			if (typeof parsed === 'string') parsed = JSON.parse(parsed)
			if (parsed && typeof parsed === 'object') {
				return parsed.data !== undefined ? parsed : { data: parsed }
			}
			return { data: parsed || '' }
		} catch (e) {
			return { data: props?.message?.content || '' }
		}
	})

	const userName = computed(() => {
		const cpages = getCurrentPages()
		const croute = cpages[cpages.length - 1]?.route
		return isSenderThemselve.value || croute.includes('pages/chat/single') || croute.includes('pagesA/im/chat') ? '' : props.message?.senderName
	})

	const userAvatar = computed(() => {
		return isSenderThemselve.value ? props?.message?.senderAvatar : props?.message?.receiverAvatar
	})

	const isRead = computed(() => {
		return props?.message?.isRead
	})

	return { isRead, isSenderThemselve, messageContent, userName, userAvatar }
}

export const messageProps = {
	message: { type: Object, default: () => ({}) },
	isReverse: { type: Boolean, default: true },
	showReadStatus: { type: Boolean, default: true },
}
