import { createChat } from './im'
import { customerServiceList } from '../request/api'

export async function openCustomerServiceChat(item) {
	if (item && item.type === 'im' && item.user_id) {
		uni.showLoading({ title: '连接客服...', mask: true })
		try {
			const conv = await createChat(item.user_id)
			if (!conv) {
				uni.showToast({ title: '客服连接失败', icon: 'none' })
				return
			}
			uni.navigateTo({
				url: `/pagesA/im/chat?target_user_id=${item.user_id}&target_name=${encodeURIComponent(item.name || '客服')}&target_avatar=${encodeURIComponent(item.image || '')}&conversation_id=${conv.conversationId || ''}&conversation_type=single`
			})
		} finally {
			uni.hideLoading()
		}
		return
	}
	if (item && item.url) {
		// #ifdef H5
		window.location.href = item.url
		// #endif
		// #ifndef H5
		uni.navigateTo({ url: `/pagesA/mine/agreement?name=客服&url=${encodeURIComponent(item.url)}` })
		// #endif
	}
}

// 联系客服：优先取内置客服（cs_mode=built_in / type=im）
// 返回 { direct: 内置客服对象|null, list: 客服列表 }
export async function resolveCustomerService() {
	try {
		const res = await customerServiceList()
		const list = (res && res.data) || []
		const builtIn = list.find(i => (i.cs_mode === 'built_in') || (!i.cs_mode && i.type === 'im'))
		return { direct: builtIn || null, list }
	} catch (e) {
		return { direct: null, list: [] }
	}
}
