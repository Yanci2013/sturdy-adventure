import {
	imInit,
	imSendMessage,
	imGetMessageList,
	imGetConversationList,
	imMarkAsRead,
	imCreateGroup,
	imGetGroupInfo,
	imInviteGroupMember,
	imKickGroupMember,
	imDissolveGroup,
	imUpdateGroupAvatar,
	imCreateConversation as apiCreateConversation
} from '../request/api'

class XBCIM {
	constructor() {
		this.connected = false
		this.currentUser = null
		this.conversations = []
		this.messages = {}
		this.eventHandlers = {}
		this.pollingTimer = null
		this.lastPollTime = 0
		this.EVENT = {
			CONVERSATION_READ: 'conversation_read',
			MESSAGE_READ: 'message_read',
			MESSAGE_RECEIVED: 'message_received',
			CONNECT: 'connect',
			DISCONNECT: 'disconnect'
		}
	}

	date = {
		isToday: (timestamp) => {
			const today = new Date()
			const msgDate = new Date(timestamp * 1000)
			return today.toDateString() === msgDate.toDateString()
		},
		toTimeStr: (timestamp) => {
			if (!timestamp) return ''
			const date = new Date(timestamp * 1000)
			const year = date.getFullYear()
			const month = String(date.getMonth() + 1).padStart(2, '0')
			const day = String(date.getDate()).padStart(2, '0')
			const hours = String(date.getHours()).padStart(2, '0')
			const minutes = String(date.getMinutes()).padStart(2, '0')
			return `${year}-${month}-${day} ${hours}:${minutes}`
		}
	}

	async init(config) {
		this.config = config
	}

	async login(params) {
		try {
			const res = await imInit()
			if (res.code === 1) {
				this.currentUser = res.data.user
				this.connected = true
				this.startPolling()
				this.emit(XBCIM.EVENT.CONNECT, {})
			} else {
				this.connected = false
			}
			return this.connected
		} catch (e) {
			// XBCIM login error
			return false
		}
	}

	async destroy() {
		this.stopPolling()
		this.connected = false
		this.currentUser = null
	}

	async createConversation(targetUserId) {
		try {
			const userId = uni.getStorageSync('userId') || uni.getStorageSync('user_id') || ''
			const res = await apiCreateConversation({
				type: 'single',
				target_id: targetUserId,
				user_id: userId
			})
			if (res.code === 1) {
				return {
					conversationId: res.data.conversation_id,
					targetUser: res.data.target
				}
			}
			return null
		} catch (e) {
			console.error('createConversation error:', e)
			return null
		}
	}

	async getMessageList(params) {
		try {
			const userId = uni.getStorageSync('userId') || uni.getStorageSync('user_id') || ''
			const reqParams = {
				...params,
				user_id: userId
			}
			const res = await imGetMessageList(reqParams)
			if (res.code === 1) {
				const myUserId = String(uni.getStorageSync('userId') || '')
				// 统一消息字段格式
				const list = (res.data.list || []).map(msg => {
					const msgType = msg.msg_type || 'text'
					return {
						id: msg.id,
						conversationType: params.type || 'single', // 添加会话类型
						targetId: msg.target_id,
						senderId: String(msg.sender_id), // 确保是字符串
						senderName: msg.sender?.nickname || msg.sender?.name || '',
						senderAvatar: msg.sender?.avatar || '',
						receiverAvatar: '',
						sender: msg.sender,
						msgType: msgType,
						type: msgType, // xbc-message 需要这个字段
						content: msg.content,
						extra: msg.extra,
						isRead: String(msg.sender_id) === myUserId ? 1 : 0,
						createTime: msg.createtime
					}
				})
				return {
					list: list,
					nextMessageId: res.data.has_more ? list[0]?.id : null
				}
			}
			return { list: [] }
		} catch (e) {
			console.error('getMessageList error:', e)
			return { list: [] }
		}
	}

	async getConversationList() {
		try {
			const userId = uni.getStorageSync('userId') || uni.getStorageSync('user_id') || ''
			const res = await imGetConversationList({ user_id: userId })
			if (res.code === 1) {
				// 兼容后端返回 {list: []} 或直接数组两种格式
				const list = Array.isArray(res.data) ? res.data : (res.data?.list || [])
				// 统一会话字段格式（适配后端返回的 target 字段）
				return list.map(conv => {
					const profile = conv.target || {
						name: conv.type === 'group' ? '群聊' : '用户',
						avatar: '',
						userId: conv.target_id
					}
					return {
						conversationId: String(conv.target_id), // 使用 target_id 作为 conversationId
						conversationType: conv.type,
						targetId: conv.target_id,
						unreadCount: conv.unread_count || 0,
						lastMessage: conv.last_message,
						lastMessageTime: conv.last_message_time,
						profile: profile,
						userId: conv.type === 'group' ? profile.id || conv.target_id : conv.target_id
					}
				})
			}
			return []
		} catch (e) {
			console.error('getConversationList error:', e)
			return []
		}
	}

	async getConversationUnreadCount() {
		try {
			const list = await this.getConversationList()
			return list.reduce((total, item) => total + (item.unread_count || 0), 0)
		} catch (e) {
			return 0
		}
	}

	async setConversationRead(params) {
		try {
			const userId = uni.getStorageSync('userId') || uni.getStorageSync('user_id') || ''
			await imMarkAsRead({
				type: params.conversationType || 'single',
				target_id: params.conversationId,
				user_id: userId
			})
			this.emit(XBCIM.EVENT.CONVERSATION_READ, {})
		} catch (e) {
			console.error('setConversationRead error:', e)
		}
	}

	async setMessageRead(params) {
		this.emit(XBCIM.EVENT.MESSAGE_READ, { list: params.messageList || [] })
	}

	async createGroup(name, userIds) {
		try {
			const res = await imCreateGroup({
				name: name,
				user_ids: userIds.join(',')
			})
			if (res.code === 1) {
				return res.data
			}
			return null
		} catch (e) {
			console.error('createGroup error:', e)
			return null
		}
	}

	// 获取群信息
	async getGroupInfo(groupId) {
		try {
			const userId = uni.getStorageSync('userId') || uni.getStorageSync('user_id') || ''
			const res = await imGetGroupInfo({ 
				group_id: groupId,
				user_id: userId
			})
			if (res.code === 1) {
				return { code: 1, data: res.data }
			}
			return { code: 0, msg: res.msg || '获取群信息失败' }
		} catch (e) {
			console.error('getGroupInfo error:', e)
			return { code: 0, msg: '获取群信息失败：' + e.message }
		}
	}

	// 邀请用户入群
	async inviteGroupMember(groupId, targetUserId) {
		try {
			const userId = uni.getStorageSync('userId') || uni.getStorageSync('user_id') || ''
			const res = await imInviteGroupMember({
				group_id: groupId,
				user_id: targetUserId,
				inviter_id: userId
			})
			console.log('inviteGroupMember response:', res)
			// 兼容多种成功码：1 表示成功，0 也可能表示成功（某些后端用 0 表示成功）
			// 如果有 data 返回且不为空，也认为是成功
			if (res && (res.code === 1 || res.code === 0 || (res.data && Object.keys(res.data).length > 0))) {
				return { code: 1, msg: res.msg || '邀请成功', data: res.data }
			}
			return { code: 0, msg: res.msg || '邀请失败' }
		} catch (e) {
			console.error('inviteGroupMember error:', e)
			return { code: 0, msg: '邀请失败：' + e.message }
		}
	}

	// 踢出成员
	async kickGroupMember(groupId, userId) {
		try {
			const currentUserId = uni.getStorageSync('userId') || uni.getStorageSync('user_id') || ''
			const res = await imKickGroupMember({
				group_id: groupId,
				user_id: userId,
				operator_id: currentUserId
			})
			console.log('kickGroupMember response:', res)
			// 兼容多种成功码：1 表示成功，0 也可能表示成功
			if (res && (res.code === 1 || res.code === 0 || (res.data && Object.keys(res.data).length > 0))) {
				return { code: 1, msg: res.msg || '踢出成功', data: res.data }
			}
			return { code: 0, msg: res.msg || '踢出失败' }
		} catch (e) {
			console.error('kickGroupMember error:', e)
			return { code: 0, msg: '踢出失败：' + e.message }
		}
	}

	// 更新群头像
	async updateGroupAvatar(groupId, avatar) {
		try {
			const userId = uni.getStorageSync('userId') || uni.getStorageSync('user_id') || ''
			const res = await imUpdateGroupAvatar({ group_id: groupId, avatar: avatar, user_id: userId })
			if (res.code === 1) {
				return { code: 1, msg: res.msg || '头像更新成功', data: res.data }
			}
			return { code: 0, msg: res.msg || '更新失败' }
		} catch (e) {
			console.error('updateGroupAvatar error:', e)
			return { code: 0, msg: '更新失败：' + e.message }
		}
	}

	// 解散群聊
	async dissolveGroup(groupId) {
		try {
			const userId = uni.getStorageSync('userId') || uni.getStorageSync('user_id') || ''
			const res = await imDissolveGroup({ group_id: groupId, user_id: userId })
			if (res.code === 1) {
				return { code: 1, msg: res.msg || '群聊已解散', data: res.data }
			}
			return { code: 0, msg: res.msg || '解散失败' }
		} catch (e) {
			console.error('dissolveGroup error:', e)
			return { code: 0, msg: '解散失败：' + e.message }
		}
	}

	async sendMessage(type, targetId, msgType, content, extra = null) {
		try {
			// 获取用户ID，兼容多种存储方式
			const userId = uni.getStorageSync('userId') || uni.getStorageSync('user_id') || ''
			const token = uni.getStorageSync('token') || ''
			
			console.log('sendMessage 用户信息 - userId:', userId, ', token:', token ? '有' : '无')
			
			const params = {
				type: type,
				target_id: targetId,
				msg_type: msgType,
				content: content,
				user_id: userId
			}
			
			// 只有当 extra 存在且不为空时才发送
			if (extra && Object.keys(extra).length > 0) {
				params.extra = JSON.stringify(extra)
			}
			
			console.log('sendMessage 发送参数:', JSON.stringify(params))
			
			const res = await imSendMessage(params)
			console.log('sendMessage 响应:', JSON.stringify(res))
			if (res && res.code === 1) {
				// 统一返回的消息字段
				const msg = res.data
				const myUserId = String(uni.getStorageSync('userId') || '')
				
				return {
					id: msg.id,
					conversationType: type, // 保存会话类型
					targetId: msg.target_id,
					senderId: String(msg.sender_id), // 确保是字符串
					senderName: msg.sender?.nickname || msg.sender?.name || '',
					senderAvatar: msg.sender?.avatar || '',
					receiverAvatar: '',
					sender: msg.sender,
					msgType: msg.msg_type,
					type: msg.msg_type, // xbc-message 需要这个字段
					content: msg.content,
					extra: msg.extra,
					isRead: String(msg.sender_id) === myUserId ? 1 : 0,
					createTime: msg.createtime
				}
			}
			console.log('sendMessage 失败:', res)
            // 直接返回完整的错误信息给调用者
			return {
                error: true,
                msg: res?.msg || '发送失败'
            }
		} catch (e) {
			console.error('sendMessage error:', e)
			return {
                error: true,
                msg: '请求异常：' + (e.message || '未知错误')
            }
		}
	}

	startPolling() {
		this.stopPolling()
		this.lastPollTime = Date.now()
		this.pollingTimer = setInterval(() => {
			this.pollMessages()
		}, 3000)
	}

	stopPolling() {
		if (this.pollingTimer) {
			clearInterval(this.pollingTimer)
			this.pollingTimer = null
		}
	}

	async pollMessages() {
		try {
			const list = await this.getConversationList()
			for (const conv of list) {
				if (conv.last_message_time && conv.last_message_time * 1000 > this.lastPollTime) {
					const msgList = await this.getMessageList({
						type: conv.type,
						target_id: conv.target_id,
						last_id: 0,
						limit: 1
					})
					if (msgList.list.length > 0) {
						const lastMsg = msgList.list[msgList.list.length - 1]
						if (lastMsg.sender_id !== this.currentUser?.id) {
							this.emit(XBCIM.EVENT.MESSAGE_RECEIVED, lastMsg)
						}
					}
				}
			}
			this.lastPollTime = Date.now()
		} catch (e) {
			console.error('pollMessages error:', e)
		}
	}

	on(event, handler) {
		if (!this.eventHandlers[event]) {
			this.eventHandlers[event] = []
		}
		this.eventHandlers[event].push(handler)
	}

	off(event, handler) {
		if (!this.eventHandlers[event]) return
		if (handler) {
			const index = this.eventHandlers[event].indexOf(handler)
			if (index > -1) {
				this.eventHandlers[event].splice(index, 1)
			}
		} else {
			delete this.eventHandlers[event]
		}
	}

	emit(event, data) {
		const handlers = this.eventHandlers[event]
		if (handlers) {
			handlers.forEach(handler => {
				try {
					handler(data)
				} catch (e) {
					console.error('Event handler error:', e)
				}
			})
		}
	}
}

const xbcimInstance = new XBCIM()

export { xbcimInstance as XBCIM }

let imLoggedIn = false
let loginPromise = null

export async function initIM() {
	if (loginPromise) return loginPromise
	loginPromise = _doInitIM()
	try {
		return await loginPromise
	} finally {
		loginPromise = null
	}
}

async function _doInitIM() {
	try {
		const token = uni.getStorageSync('token')
		if (!token) return false
		xbcimInstance.init({ appKey: '' })
		const userId = uni.getStorageSync('userId')
		const loggedIn = await xbcimInstance.login({ userId: userId, code: '' })
		imLoggedIn = loggedIn
		return loggedIn
	} catch (e) {
		console.error('IM初始化/登录失败:', e)
		imLoggedIn = false
		return false
	}
}

export async function ensureIMLogin() {
	if (imLoggedIn) return true
	return await initIM()
}

export async function createChat(targetUserId) {
	await ensureIMLogin()
	try {
		const conv = await xbcimInstance.createConversation(String(targetUserId))
		return conv
	} catch (e) {
		console.error('创建会话失败:', e)
		return null
	}
}

export async function getConversationList() {
	await ensureIMLogin()
	try {
		return await xbcimInstance.getConversationList()
	} catch (e) {
		console.error('获取会话列表失败:', e)
		return []
	}
}

export async function getUnreadCount() {
	await ensureIMLogin()
	try {
		return await xbcimInstance.getConversationUnreadCount()
	} catch (e) {
		return 0
	}
}

export function destroyIM() {
	try {
		xbcimInstance.destroy()
	} catch (e) {}
	imLoggedIn = false
	loginPromise = null
}
