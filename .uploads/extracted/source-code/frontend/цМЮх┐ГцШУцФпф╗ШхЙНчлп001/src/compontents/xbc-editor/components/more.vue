<template>
	<view class="menu" :class="{show}">
		<view class="items">
			<view @tap="chooseImage" class="item">
				<text class="iconfont icon-album"></text>
				<text class="text">相册</text>
			</view>
			<view @tap="camera" class="item">
				<text class="iconfont icon-camera"></text>
				<text class="text">拍照</text>
			</view>
			<view @tap="chooseVideo" class="item">
				<text class="iconfont icon-play"></text>
				<text class="text">视频</text>
			</view>
			<view class="item placeholder"></view>
		</view>
	</view>
</template>

<script setup>
import { base_url } from '@/request/env'

const emit = defineEmits(['success'])
const props = defineProps({
	show: { type: Boolean, default: false }
})

// 上传文件到服务器
function uploadFile(filePath, type, meta = {}) {
	uni.showLoading({ title: '上传中...', mask: true })
	uni.uploadFile({
		url: base_url + '/common/upload',
		filePath,
		name: 'file',
		header: { token: uni.getStorageSync('token') || '' },
		success(r) {
			try {
				const d = JSON.parse(r.data)
				if (d.data?.fullurl) {
					emit('success', { type, data: { ...meta, url: d.data.fullurl } })
				} else {
					uni.showToast({ title: '上传失败', icon: 'none' })
				}
			} catch (e) {
				uni.showToast({ title: '上传失败', icon: 'none' })
			}
		},
		fail() { uni.showToast({ title: '上传失败', icon: 'none' }) },
		complete() { uni.hideLoading() }
	})
}

// 拍照
function camera() {
	uni.chooseImage({
		count: 1,
		sourceType: ['camera'],
		sizeType: ['compressed'],
		success(res) {
			uploadImage(res.tempFilePaths[0], res.tempFiles?.[0])
		}
	})
}

// 选择照片
function chooseImage() {
	uni.chooseImage({
		count: 9,
		sourceType: ['album'],
		sizeType: ['compressed'],
		success(res) {
			res.tempFilePaths.forEach((fp, index) => uploadImage(fp, res.tempFiles?.[index]))
		}
	})
}

function uploadImage(filePath, file = {}) {
	uni.getImageInfo({
		src: filePath,
		success(info) {
			uploadFile(filePath, 'image', {
				width: info.width || 0,
				height: info.height || 0,
				size: file.size || 0
			})
		},
		fail() {
			uploadFile(filePath, 'image', { size: file.size || 0 })
		}
	})
}

function chooseVideo() {
	uni.chooseVideo({
		sourceType: ['album', 'camera'],
		compressed: true,
		success(res) {
			uploadFile(res.tempFilePath, 'video', {
				coverUrl: '',
				duration: res.duration || 0,
				size: res.size || 0,
				width: res.width || 320,
				height: res.height || 176
			})
		}
	})
}
</script>

<style lang="scss" scoped>
@import '@/uni.scss';
.menu {
	opacity: 0;
	padding: 24rpx 0 0 30rpx;
	transition: opacity 0.2s ease;

	&.show { opacity: 1; }

	.items {
		display: flex;
		flex-direction: row;
		justify-content: space-around;

		.item {
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			margin: 0 40rpx 40rpx 0;
			background: #fff;
			border-radius: 10rpx;
			@include fullsize(110rpx);

			&.placeholder { background: transparent; }

			.iconfont { color: #555; font-size: 50rpx; }
			.text { color: #333; font-size: 24rpx; }
		}
	}
}
</style>
