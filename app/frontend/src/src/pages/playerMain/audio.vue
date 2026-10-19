<template>
	<view class="container">
		<common-navbar title="语音录制"></common-navbar>
		<button @click="startRecording" :disabled="isRecording">开始录音</button>
		<button @click="stopRecording" :disabled="!isRecording">停止录音</button>
		<button @click="uploadAudio">上传录音</button>
		<button @click="downloadAudio">下载录音</button>
		<button @click="playAudio">播放录音</button>
		<view v-if="errorMsg" class="error">{{ errorMsg }}</view>
		<view v-if="Msg" class="error">{{ Msg }}</view>

		<!-- <button @click="stopRecording" :disabled="!isRecording">停止录音</button>
		<audio v-if="audioUrl" :src="audioUrl" controls></audio>
		<view v-if="errorMsg" class="error">{{ errorMsg }}</view> -->
	</view>
</template>

<script setup>
	import {
		ref,
		onMounted
	} from 'vue';
	import wx from 'weixin-js-sdk';
	import {
		wechatInfo
	} from '../../request/api';
	import commonNavbar from '../../compontents/common-navbar.vue';
	import {
		test
	} from '../../uni_modules/uview-plus';

	const isRecording = ref(false);
	const audioUrl = ref('');
	const errorMsg = ref('');
	const Msg = ref('')
	const localId = ref(''); // 微信录音ID
	const serverId = ref('') //服务器端ID

	// 初始化微信SDK
	const initWxSdk = async () => {
		try {
			// 获取当前页面URL（去除#后的部分）
			var url = window.location.href.split('#')[0];

			// 从后端获取签名配置
			const res = await wechatInfo({
				url: url
			})

			const {
				appId,
				timestamp,
				nonceStr,
				signature
			} = res.data;

			wx.config({
				debug: false, // 生产环境关闭
				appId,
				timestamp,
				nonceStr,
				signature,
				jsApiList: ['startRecord', 'stopRecord', 'playVoice', 'uploadVoice', 'translateVoice', 'downloadVoice']
			});
			wx.ready(() => {
				Msg.value = '微信SDK初始化完成'
			});

			wx.error(err => {
				errorMsg.value = `SDK初始化失败: ${JSON.stringify(err)}`;
			});


		} catch (e) {
			errorMsg.value = `初始化失败: ${e.message}`;
		}
	};

	// 开始录音
	const startRecording = () => {
		wx.startRecord({
			success: () => {
				isRecording.value = true;
				errorMsg.value = '';
				Msg.value = '录音开始'
			},
			fail: (err) => {
				errorMsg.value = `录音开始失败: ${JSON.stringify(err)}`;
			}
		});
	};

	// 停止录音
	const stopRecording = () => {
		wx.stopRecord({
			success: (res) => {
				isRecording.value = false;
				Msg.value = '录音停止'
				localId.value = res.localId;
				// playAudio(); // 停止后自动播放
			},
			fail: (err) => {
				errorMsg.value = `录音停止失败: ${JSON.stringify(err)}`;
			}
		});
	};

	// 上传录音
	const uploadAudio = () => {
		wx.uploadVoice({
			localId: localId.value, // 需要上传的音频的本地ID，由stopRecord接口获得
			isShowProgressTips: 1, // 默认为1，显示进度提示
			success: (res) => {
				serverId.value = res.serverId; // 返回音频的服务器端ID

			}
		})
	}
	//下载录音
	const downloadAudio = () => {
		wx.downloadVoice({
			serverId: serverId.value, // 需要下载的音频的服务器端ID，由uploadVoice接口获得
			isShowProgressTips: 1, // 默认为1，显示进度提示
			success: (res) => {
				localId.value = res.localId; // 返回音频的本地ID
			}
		})
	}

	// 播放录音
	const playAudio = () => {
		wx.playVoice({
			localId: localId.value,
			success: () => {
				Msg.value = '播放录音'
				// 转换微信localId为可播放URL（实际需要上传到服务器）
				// audioUrl.value = `weixin://resourceid/${localId.value}`;
			},
			fail: (err) => {
				errorMsg.value = `播放录音失败: ${JSON.stringify(err)}`;
			}
		});
	};

	// 页面加载时初始化
	onMounted(() => {
		initWxSdk();
	});
</script>

<style>
	.container {
		padding: 20px;
		text-align: center;
	}

	button {
		margin: 10px;
		padding: 10px 20px;
	}

	.error {
		color: red;
		margin-top: 20px;
	}
</style>