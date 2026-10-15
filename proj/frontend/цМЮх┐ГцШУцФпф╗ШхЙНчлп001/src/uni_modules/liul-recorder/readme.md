## 插件介绍

> 通过 navigator.mediaDevices.getUserMedia这个api调用麦克风，获取到到音频流数据。
> 
> 通过 MediaRecorder对音频流进行接收处理。
> 
> 生产环境必须在https环境下运行,防止部分机型不兼容,开发环境去掉了验证

##### 组件通过$refs调用

`startRecording() 开始录制(异步函数，验证不通过走reject)`

`stopRecording() 结束录制`

组件页面view中有使用演示被注释了，可以直接放开演示使用。

##### 事件（Events）

| 事件名称    | 说明         | 回调参数          |
|---------|------------|---------------|
| success | 停止录制后执行的方法 | data:DataType |

DataType类型

| 属性       | 说明        |
|----------|-----------|
| blob     | blob数据    |
| localUrl | 本地路径，可以播放 |
| duration | 录制时长      |
| size     | 文件大小(单位B) |
