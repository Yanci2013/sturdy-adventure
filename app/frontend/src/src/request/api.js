import {
	http
} from '@/uni_modules/uview-plus'

/*统一封装api*/

//登录
export const accountLogin = (data) => http.post('user/login', data)
export const quickRegister = (data) => http.post('user/quickRegister', data)
export const mpLogin = (data) => http.post('user/mpLogin', data)
export const mobileLogin = (data) => http.post('user/mobilelogin', data)
//聚合登录
export const juheLogin = (data) => http.post('login/juhe', data)
export const getJuheConfig = () => http.get('login/juhe/config')
//发送短信验证码（阿里云短信）
export const smsSend = (data) => http.post('sms/send', data)
export const miniWxLogin = (data) => http.post('user/miniWxLogin', data)
export const bindMiniPhone = (data) => http.post('user/bindMiniPhone', data)
//轮播图
export const lunbo = (params) => http.get('index/lunboList', {
	params
})
//启用的图片导航（最多用于首页展示）
export const imageNavList = (params) => http.get('index/imageNavList', { params })
export const orderBroadcast = (params) => http.get('index/order_broadcast', { params })
//分类列表
export const category = (params) => http.get('goods/categoryList', {
	params
})
//打手专区分类列表
export const playerCategory = (params) => http.get('thug/categoryList', {
	params
})
//商品列表
export const goods = (params) => http.get('goods/goodsList', {
	params
})
//商品详情
export const goodsMsg = (params) => http.get('goods/goodsMsg', {
	params
})
//生成订单
export const orderAdd = (data) => http.post('goods/orderAdd', data)
//订单支付
export const orderPay = (data) => http.post('goods/orderPay', data)
//打手列表
export const thug = (params) => http.get('thug/thugList', {
	params
})
//用户详情
export const getUserInfo = (params) => http.get('user/getUserInfo', {
	params
})
//配置
export const configure = (params) => http.get('index/configure', {
	params
})
//传世武器抽奖 - 活动配置（封面/抽奖图片/奖励爆率）
export const lotteryConfig = (params) => http.get('lottery/config', {
	params
})
//传世武器抽奖 - 抽奖
export const lotteryDraw = (data) => http.post('lottery/draw', data)
//订单列表
export const orderList = (params) => http.get('goods/orderList', {
	params
})
//订单详情
export const orderMsg = (params) => http.get('goods/orderMsg', {
	params
})
//指定打手
export const thugAppoint = (params) => http.get('goods/thugAppoint', {
	params
})
//取消订单
export const orderCancel = (data) => http.post('goods/orderCancel', data)
//等级
export const level = (params) => http.get('index/levelList', {
	params
})
//修改个人信息
export const userEdit = (data) => http.post('user/userEdit', data)
//申请打手
export const thugAdd = (data) => http.post('thug/thugAdd', data)
//申请客服
export const csAdd = (data) => http.post('cs/csAdd', data)
//邀请码校验
export const inviteCheck = (params) => http.get('invite/check', { params })
//打赏订单
export const thugReward = (data) => http.post('thug/thugReward', data)
//打赏订单支付
export const thugPay = (data) => http.post('thug/thugPay', data)
//公告列表
export const noticeList = (params) => http.get('index/noticeList', {
	params
})
//公告详情
export const noticeMsg = (params) => http.get('index/noticeMsg', {
	params
})
//用户确认完成
export const orderConfirm = (data) => http.post('goods/orderConfirm', data)
//订单投诉
export const orderComplaint = (data) => http.post('goods/orderComplaint', data)
//订单投诉详情
export const complaintMsg = (params) => http.get('goods/complaintMsg', {
	params
})
//订单售后
export const orderService = (params) => http.get('goods/orderService', {
	params
})
//提交
export const subjectSave = (data) => http.post('index/subjectSave', data)

//撤销投诉
export const orderRevoke = (data) => http.post('goods/orderRevoke', data)
//获取下一个用户等级
export const scoreLevel = (params) => http.get('user/scoreLevel', {
	params
})
//会员等级列表（含权益、价格）
export const getLevelList = (params) => http.get('user/getLevelList', {
	params
})
//会员等级购买下单
export const levelOrder = (data) => http.post('user/levelOrder', data)
//会员等级申请
export const levelApply = (data) => http.post('user/levelApply', data)
//我的团队
export const userShare = (params) => http.get('user/userShare', {
	params
})
//奖品列表
export const jackpotList = (params) => http.get('goods/jackpotList', {
	params
})

//抽奖
export const orderSmoke = (data) => http.post('goods/orderSmoke', data)
//用户申请提现
export const userApply = (data) => http.post('user/userApply', data)

////////////////////////////////////打手端////////////////////////////////////////////
//获取打手有权限的区服列表
export const getThugPermissions = () => http.get('thug/getThugPermissions')
//接单大厅
export const receivingOrders = (params) => http.get('thug/receivingOrders', {
	params
})
//打手接单
export const orderConnect = (data) => http.post('thug/orderConnect', data)
//订单列表
export const thugorderList = (params) => http.get('thug/orderList', {
	params
})
//开始服务
export const orderStart = (data) => http.post('thug/orderStart', data)
//接单/流水排行
export const thugStream = (params) => http.get('thug/thugStream', {
	params
})
//打手信息
export const thugMsg = (params) => http.get('thug/thugMsg', {
	params
})
//打手接单完成
export const orderFinish = (data) => http.post('thug/orderFinish', data)
//打手申请提现
export const thugApply = (data) => http.post('thug/thugApply', data)
//打手取消订单
export const thugorderCancel = (data) => http.post('thug/orderCancel', data)
//保证金缴纳
export const thugBond = (data) => http.post('thug/bond', data)
//修改打手信息
export const thugEdit = (data) => http.post('thug/thugEdit', data)
//指定打手
export const thugthugAppoint = (params) => http.get('thug/thugAppoint', {
	params
})


/////////////////////////////////common///////////////////////////////////////////////////////

//记录
export const moneyList = (params) => http.get('goods/moneyList', {
	params
})
//提现记录
export const withdrawalList = (params) => http.get('goods/withdrawalList', {
	params
})
export const thugRewardList = (params) => http.get('thug/thugRewardList', {
	params
})
//用户消费排行榜
export const userRanking = (params) => http.get('user/userRanking', {
	params
})

//余额明细
export const balanceLog = (params) => http.get('user/balanceLog', {
	params
})

//余额充值
export const balanceRecharge = (data) => http.post('user/balanceRecharge', data)

// 主动向易支付平台同步待支付订单
export const syncYipayPayment = (data) => http.post('payment/sync', data)

//获取微信信息
export const wechatInfo = (data) => http.post('common/wechat', data)

//客服列表
export const customerServiceList = (params) => http.get('index/customerServiceList', {
	params
})

//商品评论列表
export const commentList = (params) => http.get('goods/commentList', {
	params
})


////////////////////////////////////头像框功能////////////////////////////////////////////
//获取打手头像框列表
export const getThugFrames = (params) => http.get('avatarframe/getThugFrames', {
	params
})

//获取打手当前使用的头像框
export const getThugCurrentFrame = (params) => http.get('avatarframe/getThugCurrentFrame', {
	params
})


////////////////////////////////////罚款系统////////////////////////////////////////////
//发罚单
export const fineCreate = (data) => http.post('fine/fineCreate', data)
//我的罚单列表
export const fineList = (params) => http.get('fine/fineList', { params })
//缴纳罚款
export const finePay = (data) => http.post('fine/finePay', data)
//罚款检查
export const fineCheck = (params) => http.get('fine/fineCheck', { params })
//撤销罚单
export const fineRevoke = (data) => http.post('fine/fineRevoke', data)

////////////////////////////////////IM聊天////////////////////////////////////////////
//获取IM授权码
export const imGetToken = (params) => http.get('im/getToken', { params })
//创建/获取会话信息
export const imCreateConversation = (data) => http.post('im/createConversation', data)
//IM初始化
export const imInit = () => http.get('im/init')
//发送消息
export const imSendMessage = (data) => http.post('im/sendMessage', data)
//获取消息列表
export const imGetMessageList = (params) => http.get('im/getMessageList', { params })
//获取会话列表
export const imGetConversationList = (params) => http.get('im/getConversationList', { params })
//标记已读
export const imMarkAsRead = (data) => http.post('im/markAsRead', data)
//创建群组
export const imCreateGroup = (data) => http.post('im/createGroup', data)
//获取群信息
export const imGetGroupInfo = (data) => http.post('im/getGroupInfo', data)
//邀请用户入群
export const imInviteGroupMember = (data) => http.post('im/inviteGroupMember', data)
//踢出群成员
export const imKickGroupMember = (data) => http.post('im/kickGroupMember', data)
//解散群聊
export const imDissolveGroup = (data) => http.post('im/dissolveGroup', data)
//更新群头像
export const imUpdateGroupAvatar = (data) => http.post('im/updateGroupAvatar', data)

////////////////////////////////////礼物墙////////////////////////////////////////////
//获取礼物墙列表
export const getThugGifts = (params) => http.get('giftwall/getThugGifts', { params })
export const getThugGiftStats = (params) => http.get('giftwall/getThugGiftStats', { params })
//赠送礼物
export const sendGift = (data) => http.post('giftwall/sendGift', data)
//获取打手收到的礼物记录
export const getThugGiftRecords = (params) => http.get('giftwall/getThugGiftRecords', { params })

////////////////////////////////////智能抢单神器////////////////////////////////////////////
// 使用 fetch 直接调用（兼容 H5 环境）
const qdFetch = async (url, options = {}) => {
	const token = uni.getStorageSync('token') || ''
	const headers = { 'Content-Type': 'application/json' }
	if (token) headers['token'] = token
	const res = await fetch(url, { ...options, headers: { ...headers, ...(options.headers || {}) } })
	return res.json()
}
const buildQuery = (params) => {
	const qs = Object.entries(params || {}).map(([k, v]) => `${k}=${encodeURIComponent(v)}`).join('&')
	return qs ? '?' + qs : ''
}
//获取抢单配置和订阅状态
export const qiangdanInfo = (params) => qdFetch('/api/qiangdan/info' + buildQuery(params))
//购买抢单神器
export const qiangdanPurchase = (data) => qdFetch('/api/qiangdan/purchase', { method: 'POST', body: JSON.stringify(data) })
//同步支付结果
export const qiangdanSync = (data) => qdFetch('/api/qiangdan/sync', { method: 'POST', body: JSON.stringify(data) })
//开启/关闭抢单
export const qiangdanToggle = (data) => qdFetch('/api/qiangdan/toggle', { method: 'POST', body: JSON.stringify(data) })
//保存抢单配置
export const qiangdanSaveConfig = (data) => qdFetch('/api/qiangdan/saveConfig', { method: 'POST', body: JSON.stringify(data) })
//获取抢单日志
export const qiangdanLogs = (params) => qdFetch('/api/qiangdan/logs' + buildQuery(params))
