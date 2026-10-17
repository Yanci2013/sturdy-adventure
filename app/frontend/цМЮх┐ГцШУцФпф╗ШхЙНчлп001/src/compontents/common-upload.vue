<template>
	<up-upload :fileList="fileList" @beforeRead="beforeUpload" @afterRead="afterRead" @delete="deletePic" multiple
		:maxCount="maxCount" :width="uploadWidth" :height="uploadHeight">
		<u-image :src="uploadImage" :width="uploadWidth" :height="uploadHeight"></u-image>
	</up-upload>

</template>

<script setup>
	import {
		onMounted,
		ref
	} from 'vue';
	import {
		base_url
	} from '../request/env';


	const props = defineProps({
		// 最大上传数量
		maxCount: {
			type: Number,
			default: 1
		},
		// 图片大小限制(MB)
		maxSize: {
			type: Number,
			default: 5
		},
		// 上传额外参数
		formData: {
			type: Object,
			default: () => ({})
		},
		// 上传图片
		uploadImage: {
			type: String,
			default: '/static/mine/upload.png'
		},
		//上传图片的宽度
		uploadWidth: {
			type: String,
			default: '194rpx'
		},
		//上传图片的高度
		uploadHeight: {
			type: String,
			default: '194rpx'
		},


		tempImageList: {
			type: Array,
			default: () => {
				return []
			}
		},
		tempfileList: {
			type: Array,
			default: () => {
				return []
			}
		}

	});

	onMounted(() => {
		console.log(props.tempfileList);
		fileList.value = props.tempfileList
		imageList.value = props.tempImageList


	})

	const fileList = ref([])
	const imageList = ref([])

	const emit = defineEmits(['upload-success', 'delete-image']);

	// 删除图片
	const deletePic = (event) => {
		fileList.value.splice(event.index, 1);
		imageList.value.splice(event.index, 1)
		emit('delete-image', imageList.value)
	}
	const removePic = (event) => {
		fileList.value.forEach((res, i) => {
			fileList.value.splice(i, 1);
			imageList.value.splice(i, 1)
		})
	}

	const beforeUpload = (event) => {
		return console.log(event)
	}

	// 新增图片
	const afterRead = async (event) => {
		// 当设置 mutiple 为 true 时, file 为数组格式，否则为对象格式
		let lists = [].concat(event.file);
		let fileListLen = fileList.value.length;
		lists.map((item) => {
			fileList.value.push({
				...item,
				status: 'uploading',
				message: '上传中',
			});
		});
		for (let i = 0; i < lists.length; i++) {
			const result = await uploadFilePromise(lists[i].url);
			let item = fileList.value[fileListLen];
			fileList.value.splice(fileListLen, 1, {
				...item,
				status: 'success',
				message: '',
				url: result,
			});
			fileListLen++;
		}
	}

	const uploadFilePromise = (url) => {

		return new Promise((resolve, reject) => {
			let a = uni.uploadFile({
				url: base_url + '/common/upload', // 仅为示例，非真实的接口地址
				filePath: url,
				name: 'file',
				formData: {
					user: 'test',
				},
				success: (res) => {
					let data = JSON.parse(res.data)
					setTimeout(() => {
						imageList.value.push(data.data.url)
						resolve(data.data.fullurl);
						emit('upload-success', imageList.value)
					}, 1000);
				},
			});
		})
	};
	defineExpose({
		removePic
	})
</script>

<style>
</style>