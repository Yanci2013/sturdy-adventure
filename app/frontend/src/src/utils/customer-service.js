import { createChat } from './im'
import { customerServiceList } from '../request/api'

export async function openCustomerServiceChat(item) {
	if (item && item.type === 'im' && item.user_id !== undefined && item.user_id !== null && item.user_id !== '') {
		// 最佳努力创建会话，失败也不阻塞进入聊天页（聊天页内部会自行 IM 登录与拉取消息）
		let convId = ''
		try {
			const conv = await createChat(item.user_id)
			if (conv) convId = conv.conversationId || ''
		} catch (e) {
			console.warn('创建客服会话失败，直接进入聊天页', e)
		}
		uni.navigateTo({
			url: `/pagesA/im/chat?target_user_id=${item.user_id}&target_name=${encodeURIComponent(item.name || '客服')}&target_avatar=${encodeURIComponent(item.avatar || '')}&conversation_id=${encodeURIComponent(convId)}&conversation_type=single`
		})
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
