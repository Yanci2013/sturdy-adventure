<template>
<view class="page">
  <view class="nav-bar">
    <view class="nav-back" @click="goBack">
      <up-icon name="arrow-left" size="20" color="#333"></up-icon>
    </view>
    <text class="nav-title">{{ targetName || '聊天' }}</text>
    <!-- 群聊菜单按钮（只有群聊时显示） -->
    <view v-if="state.conversationType === 'group'" class="nav-right nav-menu-btn" @click="showGroupMenu = true">
      <text class="menu-icon">⋮</text>
    </view>
    <view v-else class="nav-right"></view>
  </view>

  <view v-if="!ready" class="loading-wrap">
    <text class="loading-text">加载中...</text>
  </view>

  <!-- 群聊菜单弹窗 -->
  <view v-if="showGroupMenu" class="group-menu-mask" @click="showGroupMenu = false">
    <view class="group-menu" @click.stop>
      <view class="group-menu-title">群聊设置</view>
      <!-- 邀请用户 -->
      <view class="group-menu-item" @click="showInviteModal = true">
        <text class="group-menu-icon">+</text>
        <text class="group-menu-text">邀请用户</text>
      </view>
      <!-- 更换群头像（只有群主可见） -->
      <view v-if="isGroupOwner" class="group-menu-item" @click="handleUpdateAvatar">
        <text class="group-menu-icon">◎</text>
        <text class="group-menu-text">更换群头像</text>
      </view>
      <!-- 踢人（只有群主可见） -->
      <view v-if="isGroupOwner" class="group-menu-item" @click="showKickModal = true">
        <text class="group-menu-icon">×</text>
        <text class="group-menu-text">踢出成员</text>
      </view>
      <!-- 解散群聊（只有群主可见） -->
      <view v-if="isGroupOwner" class="group-menu-item group-menu-item-danger" @click="handleDissolve">
        <text class="group-menu-icon">⊗</text>
        <text class="group-menu-text">解散群聊</text>
      </view>
      <view class="group-menu-item" @click="showGroupMenu = false">
        <text class="group-menu-text">关闭</text>
      </view>
    </view>
  </view>

  <!-- 邀请用户弹窗 -->
  <view v-if="showInviteModal" class="modal-mask" @click="showInviteModal = false">
    <view class="modal-content" @click.stop>
      <view class="modal-title">邀请用户入群</view>
      <view class="modal-input-wrap">
        <text class="modal-label">用户ID：</text>
        <input class="modal-input" v-model="inviteUserId" placeholder="请输入要邀请的用户ID" type="number" />
      </view>
      <view class="modal-btn-wrap">
        <view class="modal-btn modal-btn-cancel" @click="showInviteModal = false">取消</view>
        <view class="modal-btn modal-btn-confirm" @click="handleInvite">确定</view>
      </view>
    </view>
  </view>

  <!-- 踢人弹窗 -->
  <view v-if="showKickModal" class="modal-mask" @click="showKickModal = false">
    <view class="modal-content" @click.stop>
      <view class="modal-title">踢出成员</view>
      <view class="modal-input-wrap">
        <text class="modal-label">用户ID：</text>
        <input class="modal-input" v-model="kickUserId" placeholder="请输入要踢出的用户ID" type="number" />
      </view>
      <view class="modal-btn-wrap">
        <view class="modal-btn modal-btn-cancel" @click="showKickModal = false">取消</view>
        <view class="modal-btn modal-btn-danger" @click="handleKick">踢出</view>
      </view>
    </view>
  </view>

  <view @tap="reset" class="message-list" v-show="ready">
    <uni-load-more :status="loadStatus" :contentText="{contentnomore: ' '}" />
    <view @tap.stop.prevent="reset(false)" class="message-item" v-for="item in messageList" :key="item.id || item.displayTime">
      <xbc-message-time v-if="item.displayTime" :time="item.displayTime" />
      <template v-else>
        <xbc-message v-if="['system','notice'].includes(item.msgType)" mode="custom">
          <xbc-message-system :message="item" />
        </xbc-message>
        <xbc-message v-else :message="item" />
      </template>
    </view>
    <view class="xbc-editor-placeholder" :style="{height: placeholderHeight}"></view>
  </view>

  <xbc-editor v-show="ready" @change="editorChange" ref="editorRef" />
</view>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { onLoad, onUnload, onPageScroll } from '@dcloudio/uni-app'
import { ensureIMLogin, XBCIM, getConversationList } from '../../utils/im'
import { useEditor } from '../../hooks/useEditor'
// H5 分包中 easycom 不生效，需手动导入
import XbcEditor from '../../compontents/xbc-editor/xbc-editor.vue'
import XbcMessage from '../../compontents/xbc-message/xbc-message.vue'
import XbcMessageTime from '../../compontents/xbc-message-time/xbc-message-time.vue'
import XbcMessageSystem from '../../compontents/xbc-message-system/xbc-message-system.vue'

const targetUserId = ref('')
const targetName = ref('')
const ready = ref(false)
// 群聊菜单相关
const showGroupMenu = ref(false)
const showInviteModal = ref(false)
const showKickModal = ref(false)
const inviteUserId = ref('')
const kickUserId = ref('')
const isGroupOwner = ref(false) // 是否是群主
const state = reactive({
  conversationId: '',
  conversationType: 'single',
  targetId: '',
  nextMessageId: ''
})
const loadStatus = ref('more')
const editorRef = ref(null)
const messageList = ref([])
const placeholderHeight = ref('60px')
const { scrollToBottom } = useEditor()
const isAtBottom = ref(true) // 记录用户是否在页面底部

function editorChange(height) {
  placeholderHeight.value = height
}

// 监听页面滚动，判断用户是否在底部
onPageScroll((e) => {
  // 获取页面高度
  const query = uni.createSelectorQuery()
  query.select('.message-list').boundingClientRect()
  query.exec((res) => {
    if (res && res[0]) {
      // 判断是否在底部（距离底部100px以内算在底部）
      const scrollTop = e.scrollTop
      const scrollHeight = res[0].height
      const windowHeight = uni.getSystemInfoSync().windowHeight
      
      // 如果滚动位置 + 窗口高度 >= 内容高度 - 100px，则认为在底部
      isAtBottom.value = (scrollTop + windowHeight >= scrollHeight - 100)
    }
  })
})

function reset(isBottom = true) {
  if (typeof editorRef.value?.reset === 'function') {
    editorRef.value.reset(isBottom)
  }
}

async function getList() {
  try {
    const res = await XBCIM.getMessageList({
      type: state.conversationType,
      target_id: state.targetId,
      last_id: state.nextMessageId
    })
    const list = res?.list || []
    if (!list.length) {
      loadStatus.value = 'no-more'
      return
    }
    const myUserId = String(uni.getStorageSync('userId') || '')
    let newList = []
    list.forEach((item, index) => {
      try {
        const prev = index > 0 ? list[index - 1] : null
        if (!prev || (item.createTime && prev.createTime && item.createTime - prev.createTime >= 300)) {
          const tr = XBCIM.date.toTimeStr(item.createTime)
          if (tr) {
            const [dateStr, timeStr] = tr.split(' ')
            if (dateStr && timeStr) {
              const [year, month, day] = dateStr.split('-')
              const hm = timeStr.split(':')[0] + ':' + timeStr.split(':')[1]
              const datePrefix = XBCIM.date.isToday(item.createTime)
                ? ''
                : `${year}年${month}月${day}日  `
              newList.push({ displayTime: datePrefix + hm })
            }
          }
        }
      } catch (te) {
        console.warn('time format err', te)
      }
      const itemType = item.msgType || item.msg_type || item.type || 'text'
      item.type = itemType
      item.msgType = itemType
      item.conversationId = state.conversationId
      if (item.isRead === undefined || item.isRead === null) {
        if (String(item.senderId) === myUserId) {
          item.isRead = 1
        } else {
          item.isRead = 0
        }
      }
      newList.push(item)
    })
    if (state.nextMessageId) {
      messageList.value.unshift(...newList)
    } else {
      messageList.value = newList
    }
    state.nextMessageId = res?.nextMessageId || ''
    loadStatus.value = res?.nextMessageId ? 'more' : 'no-more'
  } catch (e) {
    console.error('getList err', e)
    loadStatus.value = 'no-more'
  }
}

function listenConversationRead() {
  const myUserId = String(uni.getStorageSync('userId') || '')
  messageList.value.forEach(i => {
    if (String(i.senderId) === myUserId) {
      i.isRead = 1
    }
  })
}

function listenMessageRead(res) {
  const ids = res.list ? res.list.map(i => i.id) : []
  const myUserId = String(uni.getStorageSync('userId') || '')
  messageList.value.forEach(i => {
    if (ids.includes(i.id) && String(i.senderId) === myUserId) {
      i.isRead = 1
    }
  })
}

function listenMessageReceived(msg) {
  if (!msg) return
  
  const myUserId = String(uni.getStorageSync('userId') || '')
  const isBelongToConversation = 
    (msg.conversationType === state.conversationType && String(msg.targetId) === String(state.targetId)) ||
    (state.conversationType === 'single' && 
     String(msg.senderId) === String(state.targetId) && 
     String(msg.targetId) === myUserId) ||
    (state.conversationType === 'single' && 
     String(msg.senderId) === myUserId && 
     String(msg.targetId) === String(state.targetId))
  
  if (!isBelongToConversation) return
  
  if (msg.isRead === undefined || msg.isRead === null) {
    msg.isRead = String(msg.senderId) === myUserId ? 1 : 0
  }
  const msgType = msg.msgType || msg.msg_type || msg.type || 'text'
  msg.type = msgType
  msg.msgType = msgType
  msg.conversationId = state.conversationId
  
  messageList.value.push(msg)
  XBCIM.setMessageRead({ messageList: [msg] })
  
  // 如果是自己发送的消息，或者用户在底部，才滚动到底部
  const isOwnMessage = String(msg.senderId) === myUserId
  if (isOwnMessage || isAtBottom.value) {
    scrollToBottom()
  }
}

function markAllAsReadIfNeeded() {
  try {
    XBCIM.setConversationRead({
      conversationId: state.targetId,
      conversationType: state.conversationType
    })
    const myUserId = String(uni.getStorageSync('userId') || '')
    messageList.value.forEach(i => {
      if (String(i.senderId) !== myUserId) {
        i.isRead = 1
      } else {
        if (!i.isRead) {
          i.isRead = 1
        }
      }
    })
  } catch (e) {
    console.warn('markAllAsReadIfNeeded err', e)
  }
}

onPageScroll(({ scrollTop }) => {
  if (loadStatus.value === 'no-more') return
  if (!scrollTop) getList()
  loadStatus.value = scrollTop ? 'more' : 'loading'
})

onLoad(async (options) => {
  targetUserId.value = options.target_user_id || ''
  targetName.value = decodeURIComponent(options.target_name || '聊天')
  const targetAvatar = decodeURIComponent(options.target_avatar || '')
  // 从 URL 参数获取会话类型
  const conversationType = options.conversation_type || 'single'

  if (!targetUserId.value) {
    uni.showToast({ title: '参数错误', icon: 'none' })
    ready.value = true
    return
  }

  // 确保 type 是有效的值（single 或 group）
  const validType = conversationType === 'group' ? 'group' : 'single'
  state.conversationType = validType
  state.targetId = targetUserId.value

  uni.showLoading({ title: '加载中...', mask: true })

  try {
    // 1. IM登录
    let imOk = false
    try {
      imOk = await ensureIMLogin()
    } catch (e) {
      console.error('ensureIMLogin err', e)
    }

    if (!imOk) {
      uni.showToast({ title: '连接失败，请重试', icon: 'none' })
      ready.value = true
      uni.hideLoading()
      return
    }

    // 2. 存储当前会话信息供编辑器发送消息使用
    uni.setStorageSync('currentConversation', {
      targetId: String(targetUserId.value),
      type: state.conversationType,
      conversationId: state.targetId,
      profile: {
        name: targetName.value,
        avatar: targetAvatar,
        userId: String(targetUserId.value)
      }
    })

    // 3. 先显示 UI，再拉取历史消息
    ready.value = true
    uni.hideLoading()

    // 标记已读
    try {
      XBCIM.setConversationRead({
        conversationId: state.targetId,
        conversationType: state.conversationType
      })
    } catch (e) {
      console.warn('setConversationRead err', e)
    }

    // 获取历史消息
    await getList()
    markAllAsReadIfNeeded()

    // 4. 监听事件
    XBCIM.on(XBCIM.EVENT.CONVERSATION_READ, listenConversationRead)
    XBCIM.on(XBCIM.EVENT.MESSAGE_READ, listenMessageRead)
    XBCIM.on(XBCIM.EVENT.MESSAGE_RECEIVED, listenMessageReceived)

    // 5. 启动轮询获取新消息
    startPolling()

    // 6. 检查是否是群主（群聊时）
    if (state.conversationType === 'group') {
      checkGroupOwner()
    }

    setTimeout(() => scrollToBottom(), 300)
  } catch (e) {
    console.error('onLoad err', e)
    ready.value = true
    uni.hideLoading()
  }
})

onUnload(() => {
  XBCIM.off(XBCIM.EVENT.CONVERSATION_READ, listenConversationRead)
  XBCIM.off(XBCIM.EVENT.MESSAGE_READ, listenMessageRead)
  XBCIM.off(XBCIM.EVENT.MESSAGE_RECEIVED, listenMessageReceived)
  // 停止轮询
  stopPolling()
  markAllAsReadIfNeeded()
})

// 轮询获取新消息
let pollingTimer = null

const startPolling = () => {
  stopPolling()
  pollingTimer = setInterval(async () => {
    try {
      const params = {
        type: state.conversationType,
        target_id: state.targetId,
        last_id: 0,
        limit: 10
      }
      const res = await XBCIM.getMessageList(params)
      if (res.list && res.list.length > 0) {
        // 只添加新消息（不是自己发送的）
        const myUserId = String(uni.getStorageSync('userId') || uni.getStorageSync('user_id') || '')
        res.list.forEach(msg => {
          const existing = messageList.value.find(m => m.id === msg.id)
          if (!existing && String(msg.senderId) !== myUserId) {
            const msgType = msg.msgType || msg.msg_type || msg.type || 'text'
            msg.type = msgType
            msg.msgType = msgType
            msg.conversationId = state.conversationId
            messageList.value.push(msg)
          }
        })
        // 只有在用户在底部时，才滚动到底部
        if (res.list.length > 0 && isAtBottom.value) {
          scrollToBottom()
        }
      }
    } catch (e) {
      console.error('轮询获取消息失败:', e)
    }
  }, 1000) // 每1秒轮询一次
}

const stopPolling = () => {
  if (pollingTimer) {
    clearInterval(pollingTimer)
    pollingTimer = null
  }
}

// 检查是否是群主
async function checkGroupOwner() {
  if (state.conversationType !== 'group') return
  try {
    const res = await XBCIM.getGroupInfo(state.targetId)
    if (res && res.data) {
      const myUserId = String(uni.getStorageSync('userId') || uni.getStorageSync('user_id') || '')
      isGroupOwner.value = String(res.data.owner_id) === myUserId
    }
  } catch (e) {
    console.error('检查群主失败:', e)
  }
}

// 邀请用户入群
async function handleInvite() {
  if (!inviteUserId.value.trim()) {
    uni.showToast({ title: '请输入用户ID', icon: 'none' })
    return
  }
  try {
    const res = await XBCIM.inviteGroupMember(state.targetId, inviteUserId.value)
    if (res && res.code === 1) {
      uni.showToast({ title: res.msg || '邀请成功', icon: 'success', duration: 2000 })
      showInviteModal.value = false
      inviteUserId.value = ''
      // 刷新会话列表
      await getConversationList()
      uni.$emit('refresh_conversation_list')
    } else {
      uni.showToast({ title: res?.msg || '邀请失败', icon: 'none' })
    }
  } catch (e) {
    uni.showToast({ title: '邀请失败：' + (e.message || '未知错误'), icon: 'none' })
  }
}

// 踢出成员
async function handleKick() {
  if (!kickUserId.value.trim()) {
    uni.showToast({ title: '请输入用户 ID', icon: 'none' })
    return
  }
  // 不能踢自己
  const myUserId = String(uni.getStorageSync('userId') || uni.getStorageSync('user_id') || '')
  if (String(kickUserId.value) === myUserId) {
    uni.showToast({ title: '不能踢出自己', icon: 'none' })
    return
  }
  try {
    const res = await XBCIM.kickGroupMember(state.targetId, kickUserId.value)
    if (res && res.code === 1) {
      uni.showToast({ title: res.msg || '踢出成功', icon: 'success', duration: 2000 })
      showKickModal.value = false
      kickUserId.value = ''
      // 刷新会话列表，让被踢出的人的群聊消失
      await getConversationList()
      // 如果是被踢出的人查看自己的会话列表，需要刷新页面
      // 这里通过触发一个事件来通知消息页面刷新
      uni.$emit('refresh_conversation_list')
    } else {
      uni.showToast({ title: res?.msg || '踢出失败', icon: 'none' })
    }
  } catch (e) {
    uni.showToast({ title: '踢出失败：' + (e.message || '未知错误'), icon: 'none' })
  }
}

// 更换群头像
async function handleUpdateAvatar() {
  // 先关闭群聊设置弹窗
  showGroupMenu.value = false
  
  // 选择图片
  uni.chooseImage({
    count: 1,
    sizeType: ['compressed'],
    sourceType: ['album', 'camera'],
    success: async (res) => {
      const tempFilePaths = res.tempFilePaths
      if (tempFilePaths.length > 0) {
        uni.showLoading({ title: '上传中...' })
        
        try {
          // 上传图片到服务器
          const uploadRes = await new Promise((resolve, reject) => {
            uni.uploadFile({
              url: uni.getStorageSync('baseUrl') + '/api/common/upload',
              filePath: tempFilePaths[0],
              name: 'file',
              header: {
                'token': uni.getStorageSync('token')
              },
              success: (uploadFileRes) => {
                resolve(JSON.parse(uploadFileRes.data))
              },
              fail: (err) => {
                reject(err)
              }
            })
          })
          
          uni.hideLoading()
          
          if (uploadRes && uploadRes.code === 1 && uploadRes.data && uploadRes.data.url) {
            // 更新群头像
            const result = await XBCIM.updateGroupAvatar(state.targetId, uploadRes.data.url)
            if (result && result.code === 1) {
              uni.showToast({ title: result.msg || '头像更新成功', icon: 'success', duration: 2000 })
            } else {
              uni.showToast({ title: result?.msg || '更新失败', icon: 'none' })
            }
          } else {
            uni.showToast({ title: uploadRes?.msg || '上传失败', icon: 'none' })
          }
        } catch (e) {
          uni.hideLoading()
          uni.showToast({ title: '上传失败：' + (e.message || '未知错误'), icon: 'none' })
        }
      }
    }
  })
}

// 解散群聊
async function handleDissolve() {
  uni.showModal({
    title: '确认解散',
    content: '解散群聊后，所有群成员的聊天记录将会消失，此操作不可恢复！',
    confirmColor: '#f44',
    success: async (res) => {
      if (res.confirm) {
        try {
          const result = await XBCIM.dissolveGroup(state.targetId)
          if (result && result.code === 1) {
            uni.showToast({ title: result.msg || '群聊已解散', icon: 'success', duration: 2000 })
            // 延迟返回，让用户看到提示
            setTimeout(() => {
              const pages = getCurrentPages()
              if (pages.length > 1) {
                uni.navigateBack()
              } else {
                uni.reLaunch({ url: '/pages/index/index' })
              }
            }, 1500)
          } else {
            uni.showToast({ title: result?.msg || '解散失败', icon: 'none' })
          }
        } catch (e) {
          uni.showToast({ title: '解散失败：' + (e.message || '未知错误'), icon: 'none' })
        }
      }
    }
  })
}

function goBack() {
  const pages = getCurrentPages()
  if (pages.length > 1) {
    uni.navigateBack()
  } else {
    uni.reLaunch({ url: '/pages/index/index' })
  }
}
</script>

<style lang="scss" scoped>
.page {
  min-height: 100vh;
  background: linear-gradient(180deg,
    #FF7A45 0%,
    #FF7A45 16%,
    #FF9D6E 33%,
    #FFB38A 50%,
    #FFD4B8 66%,
    #FFFFFF 83%,
    #FFFFFF 100%);
  display: flex;
  flex-direction: column;
}
.nav-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 88rpx;
  padding: 0 24rpx;
  padding-top: var(--status-bar-height, 0px);
  background: #FFFFFF;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 100;
}
.nav-back { padding: 16rpx; }
.nav-title { font-size: 32rpx; font-weight: 600; color: #111; }
.nav-right { width: 56rpx; display: flex; align-items: center; justify-content: center; }
.nav-menu-btn {
  padding: 12rpx;
}
.menu-icon {
  font-size: 40rpx;
  color: #666;
  font-weight: bold;
}
/* 群聊菜单 */
.group-menu-mask {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.5);
  z-index: 1000;
  display: flex;
  align-items: flex-end;
  justify-content: center;
}
.group-menu {
  width: 100%;
  background: #fff;
  border-radius: 32rpx 32rpx 0 0;
  padding: 32rpx;
  padding-bottom: calc(32rpx + env(safe-area-inset-bottom));
}
.group-menu-title {
  text-align: center;
  font-size: 28rpx;
  color: #999;
  padding-bottom: 24rpx;
  border-bottom: 1rpx solid #eee;
  margin-bottom: 16rpx;
}
.group-menu-item {
  display: flex;
  align-items: center;
  height: 88rpx;
  padding: 0 24rpx;
  border-radius: 16rpx;
  margin-bottom: 12rpx;
  &:active { background: #f5f5f5; }
}
.group-menu-icon {
  width: 56rpx;
  height: 56rpx;
  background: #e8e8e8;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32rpx;
  color: #666;
  margin-right: 20rpx;
}
.group-menu-text {
  font-size: 32rpx;
  color: #333;
}
.group-menu-item-danger {
  background: rgba(244, 67, 54, 0.1);
  .group-menu-icon, .group-menu-text {
    color: #f44;
  }
}
/* 通用弹窗 */
.modal-mask {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.5);
  z-index: 1001;
  display: flex;
  align-items: center;
  justify-content: center;
}
.modal-content {
  width: 600rpx;
  background: #fff;
  border-radius: 24rpx;
  padding: 40rpx;
}
.modal-title {
  text-align: center;
  font-size: 36rpx;
  font-weight: 600;
  color: #333;
  margin-bottom: 40rpx;
}
.modal-input-wrap {
  margin-bottom: 40rpx;
}
.modal-label {
  font-size: 28rpx;
  color: #666;
  margin-bottom: 12rpx;
  display: block;
}
.modal-input {
  width: 100%;
  height: 88rpx;
  border: 2rpx solid #ddd;
  border-radius: 12rpx;
  padding: 0 24rpx;
  font-size: 32rpx;
  box-sizing: border-box;
}
.modal-btn-wrap {
  display: flex;
  gap: 24rpx;
}
.modal-btn {
  flex: 1;
  height: 88rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 44rpx;
  font-size: 32rpx;
  font-weight: 500;
}
.modal-btn-cancel {
  background: #f5f5f5;
  color: #666;
}
.modal-btn-confirm {
  background: linear-gradient(135deg, #FFB38A 0%, #FFB38A 100%);
  color: #fff;
}
.modal-btn-danger {
  background: #f44;
  color: #fff;
}
.message-list {
  flex: 1;
  min-height: 80vh;
  background-color: #f5f5f5;
  padding-top: calc(88rpx + var(--status-bar-height, 0px));
  padding-bottom: env(safe-area-inset-bottom);
  .message-item { margin-bottom: 20rpx; }
}
.xbc-editor-placeholder {
  height: 120rpx;
  transition: height 0.3s ease;
  background-color: #f5f5f5;
  padding-bottom: env(safe-area-inset-bottom);
}
.loading-wrap {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
}
.loading-text { font-size: 28rpx; color: #999; }
</style>
