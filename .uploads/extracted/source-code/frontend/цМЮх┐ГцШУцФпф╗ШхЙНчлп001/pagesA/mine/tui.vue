<template>
	<view class="">
		<image :src="bg" mode="" class="img" @longpress="saveImageToAlbum"></image>
	</view>
	<!-- <view class="position-relative" @longpress="saveImageToAlbum">
		<image :src="shareImage" class="img"></image>
		<view class="position-absolute shareImage" id="qrcodeContainer">
			<image :src="qrcodeImagePath" mode="" style="width: 400rpx;height: 400rpx;"></image>
			<uv-qrcode ref="qrcode" :options="options" :value="val" @complete="onQrcodeComplete"></uv-qrcode>
		</view>
		<canvas id="shareCanvas" class="canvas" :style="{width: canvasWidth + 'px', height: canvasHeight + 'px'}"></canvas>
	</view> -->
</template>

<script setup>
	import {
		onMounted,
		ref
	} from 'vue';
	import {
		configure,
		getUserInfo
	} from '../../request/api';
	import {
		base_url,
		loginUrl
	} from '../../request/env';
	import QRCode from 'qrcode';

	const shareImage = ref('')
	const val = ref('')
	const bg = ref('')


	onMounted(async () => {
		uni.showLoading({
			title: '加载中...',
			mask: false
		});
		await getData()
	})

	const getData = async () => {
		const res = await configure()
		const userRes = await getUserInfo()
		shareImage.value = res.data.share_image
		val.value = loginUrl + '?pid=' + userRes.data.id
		generateQrCode()
	}

	// 生成二维码并合成图片
	const generateQrCode = async () => {
		try {
			// 1. 生成二维码图片
			const qrCodeUrl = await generateQrCodeImage(val.value);

			// 2. 合成背景图和二维码
			const mergedImage = await mergeImages(shareImage.value, qrCodeUrl);
			bg.value = mergedImage;
			uni.hideLoading()
		} catch (error) {
			console.error('图片合成失败', error);
			uni.showToast({
				title: '合成失败',
				icon: 'none'
			});
		}
	};

	const generateQrCodeImage = async (data) => {
		return new Promise((resolve, reject) => {
			// 这里使用canvas绘制二维码
			const canvas = document.createElement('canvas');
			const ctx = canvas.getContext('2d');

			// 设置二维码尺寸
			const size = 300;
			canvas.width = size;
			canvas.height = size;

			// 生成二维码
			QRCode.toCanvas(canvas, data, {
				errorCorrectionLevel: 'H',
				margin: 1
			}, (error) => {
				if (error) {
					reject(error);
					return;
				}

				// 将canvas转换为图片URL
				const qrCodeUrl = canvas.toDataURL('image/png');
				console.log(qrCodeUrl)
				resolve(qrCodeUrl);
			});
		});
	};

	const mergeImages = async (backgroundUrl, qrCodeUrl) => {
		return new Promise((resolve, reject) => {
			// 创建图片对象
			const backgroundImage = new Image();
			const qrCodeImage = new Image();

			// 等待两张图片都加载完成
			let loaded = 0;
			const total = 2;

			const handleLoad = () => {
				loaded++;
				if (loaded === total) {
					doMerge();
				}
			};

			backgroundImage.onload = handleLoad;
			backgroundImage.onerror = reject;
			backgroundImage.src = backgroundUrl;

			qrCodeImage.onload = handleLoad;
			qrCodeImage.onerror = reject;
			qrCodeImage.src = qrCodeUrl;

			// 执行图片合成
			const doMerge = () => {
				try {
					// 创建画布，尺寸与背景图一致
					const canvas = document.createElement('canvas');
					const ctx = canvas.getContext('2d');

					canvas.width = backgroundImage.width;
					canvas.height = backgroundImage.height;

					// 绘制背景图
					ctx.drawImage(backgroundImage, 0, 0, canvas.width, canvas.height);

					// 计算二维码放置位置（这里放在底部中央）
					const qrCodeSize = 800; // 二维码尺寸不超过背景图的40%
					const qrCodeX = 305;
					const qrCodeY = 1410; // 距离底部50像素

					// 绘制二维码
					ctx.drawImage(qrCodeImage, qrCodeX, qrCodeY, qrCodeSize, qrCodeSize);

					// 将画布转换为图片URL
					const mergedImageUrl = canvas.toDataURL('image/png');
					resolve(mergedImageUrl);
				} catch (error) {
					reject(error);
				}
			};
		});
	};


	const saveImageToAlbum = () => {

		uni.saveImageToPhotosAlbum({
			filePath: bg.value,
			success: () => {
				// uni.hideToast()
				uni.showToast({
					title: '保存成功',
					icon: 'success'
				})
			},
			fail: (err) => {
				// uni.hideToast()
			}
		})
	}
</script>

<style>
	.img {
		width: 100%;
		height: 100vh;
	}

	.shareImage {
		top: 710rpx;
		left: 175rpx;
	}

	.canvas {
		position: absolute;
		top: 0;
		left: 0;
		width: 100%;
		height: 100vh;
		opacity: 0;
		pointer-events: none;
	}
</style>