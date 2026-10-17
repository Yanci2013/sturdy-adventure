<template>
	<view class="xbc-editor">
		<!--输入区-->
		<view class="top">
			<view @tap="triggerAudio" class="icon">
				<text v-show="oper != 'audio'" class="iconfont icon-audio"></text>
				<text v-show="oper == 'audio'" class="iconfont icon-keyboard"></text>
			</view>
			<view class="block">
				<textarea
					@focus="onFocus"
					@blur="isFocus=false"
					v-model="content"
					class="textarea"
					:show-confirm-bar="false"
					:disabled="oper == 'audio'"
					:cursor-spacing="24"
					:maxlength="10000"
					:auto-blur="false"
					auto-height
					fixed />
				<xbc-audio @success="audioRecordSuccess" v-if="oper == 'audio'" />
			</view>
			<view class="button">
				<view @tap="trigger('emoji')" class="icon">
					<text class="iconfont icon-emoji"></text>
				</view>
				<view @tap="trigger('more')" v-show="!content" class="icon">
					<text class="iconfont icon-plus-circle"></text>
				</view>
				<view @tap="sendTextMessage" v-show="content" class="send">发送</view>
			</view>
		</view>
		<!--表情-->
		<xbc-emoji @select="selectEmoji" v-show="oper == 'emoji'" :show="!isFocus" />
		<!--更多-->
		<xbc-more @success="uploadSuccess" v-show="oper == 'more'" :show="!isFocus" />
	</view>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useEditor } from '@/hooks/useEditor'
import XbcMore from './components/more.vue'
import XbcAudio from './components/audio/index.vue'
import XbcEmoji from './components/emoji/index.vue'
import { XBCIM } from '@/utils/im'

const emit = defineEmits(['change'])
const oper = ref('input')	// 当前操作：input-输入，audio-录音，emoji-表情，more-更多
const content = ref('')
const isFocus = ref(false)
const quoteMessage = ref(null)
const mentionMembers = ref([])
const placeholderHeight = ref('60px')
const { scrollToBottom, getFootHeight } = useEditor()

// 发送文本消息
async function sendTextMessage() {
  try {
    const { targetId, type } = uni.getStorageSync('currentConversation')
    // 文本消息需要包装成 {"data": "text"} 格式
    const formattedContent = JSON.stringify({ data: content.value })
	await sendMessage(type, targetId, 'text', formattedContent)
	content.value = ''
    quoteMessage.value = null
	mentionMembers.value = []
  } catch (error) {
    console.error(error)
  }
}

// 发送消息
async function sendMessage(type, targetId, msgType, content, extra = null) {
  try {
	const message = await XBCIM.sendMessage(type, targetId, msgType, content, extra)
    
    // 检查是否有错误
    if (message && message.error) {
        uni.showToast({
            title: message.msg || '发送失败',
            icon: 'none',
            duration: 3000
        })
        return
    }
    
	if (message) {
		// 获取当前用户ID（支持多种存储方式）
		const myUserId = String(uni.getStorageSync('userId') || uni.getStorageSync('user_id') || '')
		
		// 确保所有字段都正确
		message.type = msgType
		message.msgType = msgType
		message.conversationType = type // 确保有会话类型
		// 强制使用前端存储的用户ID作为senderId（因为后端可能返回错误的sender_id）
		message.senderId = myUserId
		message.isRead = 1 // 自己发送的消息默认已读
		// 立即触发消息接收事件，让聊天页面显示消息
		XBCIM.emit(XBCIM.EVENT.MESSAGE_RECEIVED, message)
		quoteMessage.value = null
		mentionMembers.value = []
		scrollToBottom()
	}
  } catch (error) {
	  // 发送消息异常
      uni.showToast({
          title: '发送异常：' + (error.message || '未知错误'),
          icon: 'none',
          duration: 3000
      })
  }
}

// 切换
function trigger(op) {
	setTimeout(() => {
		oper.value = op
		getFootHeight(placeholderHeight)
	}, 100)
}

// 上传成功
async function uploadSuccess({ type, data }) {
	try {
		const { targetId, type: conversationType } = uni.getStorageSync('currentConversation')
		let msgType = 'text'
		let extra = null
		
		switch (type){
			case 'file':
				msgType = 'file'
				extra = { name: data.name, size: data.size, url: data.url }
				break
			case 'image':
				msgType = 'image'
				extra = {
					url: data.url,
					thumbUrl: data.thumbUrl || data.url,
					width: data.width || 0,
					height: data.height || 0,
					size: data.size || 0
				}
				break
			case 'video':
				msgType = 'video'
				extra = {
					coverUrl: data.coverUrl || '',
					duration: data.duration || 0,
					size: data.size || 0,
					width: data.width || 320,
					height: data.height || 176,
					url: data.url
				}
				break
			case 'location':
				msgType = 'text'
				extra = {
					name: data.name,
					address: data.address,
					latitude: data.latitude,
					longitude: data.longitude
				}
				break
			case 'card':
				msgType = 'text'
				extra = data
				break
		}
		
		await sendMessage(conversationType, targetId, msgType, JSON.stringify({ data: extra }), extra)
		reset()
	} catch (e) {
		console.error(e)
	}
}

// 音频录制成功
async function audioRecordSuccess({ duration, url }) {
	try {
		const { targetId, type } = uni.getStorageSync('currentConversation')
		await sendMessage(type, targetId, 'voice', url, { duration, url })
	} catch (e) {
		console.error(e)
	}
}

// 切换语音
function triggerAudio() {
	oper.value = oper.value == 'audio' ? 'input' : 'audio'
	getFootHeight(placeholderHeight)
}

// 选择表情
function selectEmoji(emoji) {
	content.value += emoji
}

// 就聚焦
function onFocus() {
	isFocus.value = true
	// #ifdef H5
	oper.value = 'input'
	// #endif
}

// 重置
function reset(isBottom = true) {
	if(oper.value != 'audio'){
		oper.value = 'input'
		getFootHeight(placeholderHeight, isBottom)
	}
}

// #ifndef H5
uni.onKeyboardHeightChange(({ height }) => {
	if(!height) reset()
})
// #endif

watch(placeholderHeight, height => {
	emit('change', height)
})

defineExpose({
	reset
})
</script>

<style lang="scss" scoped>
@import '@/uni.scss';
.xbc-editor {
	width: 100%;
	background-color: #f5f5f5;
	border-top: 2rpx solid #eee;
	transition: height 0.3s ease;
	position: fixed;
	bottom: 0;
	left: 0;
	z-index: 999;
	
	::-webkit-scrollbar {
		display: none;
	}
	
	.top {
		display: flex;
		flex-direction: row;
		align-items: center;
		padding: 16rpx 10rpx;
		min-height: 100rpx;
		box-sizing: border-box;
		gap: 6rpx;
		
		.button {
			display: flex;
			flex-direction: row;
			align-items: center;
			justify-content: flex-end;
			flex-shrink: 0;
			gap: 6rpx;
		}
		
		.send {
			color: #fff;
			width: 100rpx;
			height: 64rpx;
			font-size: 28rpx;
			border-radius: 10rpx;
			background-color: #2BA245;
			flex-shrink: 0;
			display: flex;
			align-items: center;
			justify-content: center;
		}
		
		.block {
			flex: 1;
			margin: 0 6rpx;
			padding: 0 20rpx;
			overflow: hidden;
			min-height: 68rpx;
			border-radius: 10rpx;
			background-color: #fff;
			position: relative;
			display: flex;
			align-items: center;
		}
		
		.textarea {
			width: 100%;
			color: #333;
			font-size: 30rpx;
			max-height: 160px;
			padding-top: 12rpx;
			padding-bottom: 12rpx;
			background-color: #fff;
		}
		
		.icon {
			@include fullsize(72rpx);
			display: flex;
			align-items: center;
			justify-content: center;
			flex-shrink: 0;
			
			.iconfont {
				font-size: 52rpx;
				
				&.icon-emoji {
					font-size: 50rpx;
				}
			}
		}
	}
}

</style>
