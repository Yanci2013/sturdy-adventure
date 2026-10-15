/**
 * 客服会话存储管理
 *  - 持久化客服会话到 localStorage
 *  - 支持 5 分钟无活动自动结束
 *  - 咨询中 / 全部 筛选
 */

const STORAGE_KEY = 'cs_sessions'
const TIMEOUT_MS = 5 * 60 * 1000 // 5 分钟

/**
 * 获取所有客服会话
 */
export function getCsSessions() {
	try {
		const raw = uni.getStorageSync(STORAGE_KEY)
		return raw ? JSON.parse(raw) : []
	} catch (e) {
		return []
	}
}

/**
 * 保存所有客服会话
 */
function saveSessions(list) {
	try {
		uni.setStorageSync(STORAGE_KEY, JSON.stringify(list))
	} catch (e) {}
}

/**
 * 获取单个客服会话
 */
export function getCsSession(csId) {
	const list = getCsSessions()
	return list.find(s => String(s.cs_id) === String(csId)) || null
}

/**
 * 创建或更新客服会话（进入聊天时调用）
 */
export function touchCsSession({ cs_id, cs_name, cs_avatar }) {
	const list = getCsSessions()
	const now = Date.now()
	let session = list.find(s => String(s.cs_id) === String(cs_id))
	if (session) {
		session.cs_name = cs_name || session.cs_name
		session.cs_avatar = cs_avatar || session.cs_avatar
		session.last_active = now
		// 如果已结束，重新激活
		if (session.status === 'ended') {
			session.status = 'active'
			session.ended_at = null
		}
	} else {
		session = {
			id: 'cs_' + cs_id,
			cs_id: Number(cs_id),
			cs_name: cs_name || '客服',
			cs_avatar: cs_avatar || '',
			last_msg: '',
			last_time: now,
			last_active: now,
			status: 'active',
			ended_at: null,
			unread: 0
		}
		list.push(session)
	}
	saveSessions(list)
	return session
}

/**
 * 更新会话最后一条消息（发送/接收消息时调用）
 */
export function updateCsSessionLastMsg(csId, msg) {
	const list = getCsSessions()
	const idx = list.findIndex(s => String(s.cs_id) === String(csId))
	if (idx === -1) return
	const now = Date.now()
	list[idx].last_msg = msg
	list[idx].last_time = now
	list[idx].last_active = now
	if (list[idx].status === 'ended') {
		list[idx].status = 'active'
		list[idx].ended_at = null
	}
	saveSessions(list)
}

/**
 * 结束客服会话（5分钟超时或主动结束）
 */
export function endCsSession(csId) {
	const list = getCsSessions()
	const idx = list.findIndex(s => String(s.cs_id) === String(csId))
	if (idx === -1) return null
	const now = Date.now()
	list[idx].status = 'ended'
	list[idx].ended_at = now
	list[idx].last_msg = '会话已结束'
	list[idx].last_time = now
	saveSessions(list)
	return list[idx]
}

/**
 * 检查并结束超时会话（5分钟无活动）
 * 返回被结束的会话列表
 */
export function checkTimeoutSessions() {
	const list = getCsSessions()
	const now = Date.now()
	const ended = []
	list.forEach(s => {
		if (s.status === 'active' && now - s.last_active >= TIMEOUT_MS) {
			s.status = 'ended'
			s.ended_at = now
			s.last_msg = '会话已结束'
			s.last_time = now
			ended.push(s)
		}
	})
	if (ended.length) {
		saveSessions(list)
	}
	return ended
}

/**
 * 标记会话已读
 */
export function markCsSessionRead(csId) {
	const list = getCsSessions()
	const idx = list.findIndex(s => String(s.cs_id) === String(csId))
	if (idx === -1) return
	list[idx].unread = 0
	saveSessions(list)
}

/**
 * 删除会话
 */
export function removeCsSession(csId) {
	const list = getCsSessions().filter(s => String(s.cs_id) !== String(csId))
	saveSessions(list)
}

/**
 * 超时时间（毫秒）
 */
export const CS_TIMEOUT_MS = TIMEOUT_MS
