<script>
/**
 * web,h5端在线录制功能组件
 * 通过ref调用
 */
export default {
  name: "WebMediaDevices",
  data() {
    return {
      isRecording: false,
      audioUrl: null,
      mediaRecorder: null,
      audioChunks: [],
      startTime: 0,
      recordingTime: 0,
    }
  },
  methods: {
    /**
     * 开始录制
     * @returns {Promise<Awaited<boolean>>}
     */
    async startRecording() {
      //需要https环境才能使用，vue2本地测试可以在 manifest.json 中点击源码展示，找到h5 ，添加："devServer" : { "https" : true}
      if (origin.indexOf('https') === -1 && process.env.NODE_ENV === 'production') { //大多数现代浏览器（包括 Safari）要求在安全上下文中使用 getUserMedia
        uni.showToast({
          title: '请在 https 环境中使用。',
          icon: 'none',
        })
        return Promise.reject(false)
      }
      if (!navigator.mediaDevices || !window.MediaRecorder) {
        uni.showToast({
          title: '当前浏览器不支持',
          icon: 'none',
        })
        return Promise.reject(false)
      }
      try {
        const stream = await navigator.mediaDevices.getUserMedia({
          audio: true
        });
        this.mediaRecorder = new MediaRecorder(stream);

        this.mediaRecorder.onstart = () => {
          this.startTime = new Date().getTime();
          console.log('开始录制');
        };

        this.mediaRecorder.ondataavailable = event => {
          this.audioChunks.push(event.data);
        };

        this.mediaRecorder.onstop = () => {
          const audioBlob = new Blob(this.audioChunks, {
            type: 'audio/mpeg'
          });
          this.audioUrl = URL.createObjectURL(audioBlob);
          this.audioChunks = [];
          const endTime = new Date().getTime();
          const timeDifference = endTime - this.startTime;
          this.recordingTime = Math.floor(timeDifference / 1000);
          let params = {
            blob: audioBlob,
            localUrl: this.audioUrl,
            duration: this.recordingTime,
            size: audioBlob.size, // b
          }
          // console.log(params)
          this.$emit('success', params);
        };

        this.mediaRecorder.start();
        this.isRecording = true;
        return Promise.resolve(true)
      } catch (err) {
        console.error(err);
        if (err.name === 'NotFoundError') {
          uni.showToast({
            title: '浏览器无法获取音频设备，请检查麦克风是否可用',
            icon: 'none',
          })
        } else if (err.name === 'NotAllowedError') {
          uni.showToast({
            title: '浏览器未检测到音频设备，请在浏览器设置中打开访问权限，打开后才可正常使用',
            icon: 'none',
          })
        } else {
          uni.showToast({
            title: '未知，请检查设备是否正常。' + err.name + ':' + err.message,
            icon: 'none',
          })
        }
        return Promise.reject(false)
      }
    },
    /**
     * 结束录制
     */
    stopRecording() {
      if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
        this.mediaRecorder.stop();
        console.log('结束录制')
        this.isRecording = false;
      }
    },
  }
}
</script>

<template>
  <view>
    <!--    <button @click="startRecording" :disabled="isRecording">开始录音</button>-->
    <!--    <button @click="stopRecording" :disabled="!isRecording">停止录音</button>-->
    <!--    <audio v-if="audioUrl" :src="audioUrl" controls></audio>-->
  </view>
</template>

<style scoped lang="scss">

</style>
