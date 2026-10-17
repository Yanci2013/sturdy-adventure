<template>
	<view class="create-group-page">
		<!-- 顶部导航栏 -->
		<view class="nav-bar">
			<view class="nav-back" @click="goBack">
				<text class="iconfont">&#xe600;</text>
			</view>
			<view class="nav-title">创建群聊</view>
			<view class="nav-right"></view>
		</view>

		<view class="content-box">
			<!-- 群名称 -->
			<view class="input-section">
				<view class="input-label">群名称</view>
				<view class="input-wrapper">
					<input class="text-input" v-model="groupName" placeholder="请输入群名称" placeholder-class="input-placeholder" />
				</view>
			</view>

			<!-- 成员ID列表 -->
			<view class="input-section">
				<view class="input-label">成员ID</view>
				<view class="input-wrapper">
					<input class="text-input" v-model="memberIds" placeholder="请输入成员ID，多个用逗号分隔" placeholder-class="input-placeholder" />
				</view>
				<view class="input-tip">例如：1,2,3</view>
			</view>

			<!-- 已选择的成员（示例） -->
			<view class="selected-section" v-if="selectedMembers.length > 0">
				<view class="selected-label">已选择 {{ selectedMembers.length }} 位成员</view>
				<view class="member-list">
					<view class="member-item" v-for="(item, idx) in selectedMembers" :key="idx">
						<image class="member-avatar" :src="item.avatar || '/static/yun/default_avatar.png'" mode="aspectFill"></image>
						<text class="member-name">{{ item.name }}</text>
					</view>
				</view>
			</view>

			<!-- 创建按钮 -->
			<view class="create-btn" :class="{ 'create-btn-disabled': !canCreate }" @click="createGroup">
				<text class="create-btn-text">{{ loading ? '创建中...' : '创建群聊' }}</text>
			</view>
		</view>
	</view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { XBCIM, ensureIMLogin } from '@/utils/im'
import router from '@/utils/router'

const groupName = ref('')
const memberIds = ref('')
const selectedMembers = ref([])
const loading = ref(false)

const canCreate = computed(() => {
	return groupName.value.trim().length > 0 && memberIds.value.trim().length > 0
})

const goBack = () => {
	uni.navigateBack()
}

const createGroup = async () => {
	if (!canCreate.value) return
	if (loading.value) return

	try {
		loading.value = true
		await ensureIMLogin()

		// 解析成员ID
		const ids = memberIds.value.split(',').map(id => id.trim()).filter(id => id.length > 0)

		if (ids.length < 1) {
			uni.showToast({ icon: 'none', title: '请至少添加一位成员' })
			return
		}

		const group = await XBCIM.createGroup(groupName.value.trim(), ids)

		if (group) {
			uni.showToast({ icon: 'success', title: '创建成功' })

			// 延迟跳转到聊天页面
			setTimeout(() => {
				uni.setStorageSync('currentConversation', {
					targetId: String(group.id),
					type: 'group',
					profile: {
						name: group.name,
						avatar: group.avatar || '/static/img/group_avatar.png',
						userId: String(group.id)
					}
				})
				uni.redirectTo({
					url: `/pagesA/im/chat?target_user_id=${group.id}&target_name=${encodeURIComponent(group.name)}&target_avatar=${encodeURIComponent(group.avatar || '/static/img/group_avatar.png')}`
				})
			}, 500)
		} else {
			uni.showToast({ icon: 'none', title: '创建失败，请重试' })
		}
	} catch (e) {
		console.error('创建群聊失败:', e)
		uni.showToast({ icon: 'none', title: '创建失败，请重试' })
	} finally {
		loading.value = false
	}
}
</script>

<style lang="scss" scoped>
.create-group-page {
	min-height: 100vh;
	background: linear-gradient(135deg, #FFF5EF 0%, #FFEDE0 100%);
}

.nav-bar {
	display: flex;
	flex-direction: row;
	justify-content: space-between;
	align-items: center;
	height: 88rpx;
	padding-top: 44rpx;
	padding-left: 24rpx;
	padding-right: 24rpx;
	background: rgba(255, 255, 255, 0.8);
	backdrop-filter: blur(24rpx);
}

.nav-back {
	width: 60rpx;
	height: 60rpx;
	display: flex;
	justify-content: center;
	align-items: center;
}

.nav-back .iconfont {
	font-size: 40rpx;
	color: #333;
}

.nav-title {
	font-size: 32rpx;
	font-weight: 600;
	color: #333;
}

.nav-right {
	width: 60rpx;
}

.content-box {
	padding: 32rpx;
}

.input-section {
	margin-bottom: 32rpx;
}

.input-label {
	font-size: 28rpx;
	color: #333;
	margin-bottom: 16rpx;
	font-weight: 500;
}

.input-wrapper {
	background: #fff;
	border-radius: 16rpx;
	padding: 24rpx;
	border: 1rpx solid rgba(0, 0, 0, 0.08);
}

.text-input {
	width: 100%;
	font-size: 28rpx;
	color: #333;
}

.input-placeholder {
	color: #999;
}

.input-tip {
	font-size: 22rpx;
	color: #999;
	margin-top: 8rpx;
	padding-left: 8rpx;
}

.selected-section {
	margin-bottom: 48rpx;
}

.selected-label {
	font-size: 28rpx;
	color: #333;
	margin-bottom: 16rpx;
	font-weight: 500;
}

.member-list {
	display: flex;
	flex-direction: row;
	flex-wrap: wrap;
	gap: 24rpx;
	background: #fff;
	border-radius: 16rpx;
	padding: 24rpx;
}

.member-item {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8rpx;
}

.member-avatar {
	width: 88rpx;
	height: 88rpx;
	border-radius: 24rpx;
}

.member-name {
	font-size: 22rpx;
	color: #666;
}

.create-btn {
	width: 100%;
	height: 96rpx;
	background: linear-gradient(135deg, #FFB38A 0%, #FFB38A 100%);
	border-radius: 48rpx;
	display: flex;
	justify-content: center;
	align-items: center;
	margin-top: 32rpx;
}

.create-btn-disabled {
	background: #ccc;
}

.create-btn-text {
	color: #fff;
	font-size: 32rpx;
	font-weight: 600;
}
</style>
