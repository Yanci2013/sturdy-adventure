<template>
	<z-paging ref="paging" v-model="dataList" @query="queryList">
		<template #top>
			<up-navbar placeholder autoBack title="分类"></up-navbar>
			<view class="bg-white p-2 flex align-center">
				<u-search placeholder="搜索商品标题" v-model="keyword" :showAction="false" @search="inputSearch"></u-search>
				<view class="ml-2">
					<u-image src="/static/images/classify.png" width="48rpx" height="48rpx" @click="show=true"></u-image>
				</view>
			</view>

			<view class="mt-2 pt-2 px-2 ">
				<up-scroll-list :indicator="false">
					<view class="mr-2" v-for="(item, index) in classifyAllList" :key="index">
						<view :class="current==index?'select':'normal'" @click="changeTag(item,index)">{{item.name}}</view>
					</view>
				</up-scroll-list>
			</view>
		</template>
		<up-grid col="2">
			<u-grid-item v-for="(item,index) in dataList">
				<commonItem :item="item"></commonItem>
			</u-grid-item>
		</up-grid>


		<up-picker ref="uPickerRef" :show="show" :columns="columns" @cancel="show=false" @confirm="onPickerConfirm"
			@change="onPickerChange" :defaultIndex="[0,0]"></up-picker>

	</z-paging>

</template>

<script setup>
	import {
		reactive,
		ref,
		nextTick
	} from 'vue';
	import {
		onLoad
	} from '@dcloudio/uni-app'
	import {
		category,
		goods
	} from '../../request/api';
	const paging = ref(null)
	// v-model绑定的这个变量不要在分页请求结束中自己赋值，直接使用即可
	const dataList = ref([])
	const arrAll = ref([])
	const classifyAllList = ref([])
	const classifyItem = ref({})
	const childrenList = ref([])
	const item = ref({})
	const pid = ref('')
	const id = ref('')
	const keyword = ref('')
	const current = ref(0)
	const columns = ref([])
	const columnData = ref([])
	const show = ref(false)
	const uPickerRef = ref(null)

	onLoad((prop) => {
		pid.value = prop.pid
		getClassify()
	})
	const getClassify = async () => {
		const res = await category()
		arrAll.value = res.data
		classifyAllList.value = [{
			id: '0',
			name: '全部'
		}].concat(res.data)
		columns.value = [
			arrAll.value.map(item => item.name),
			arrAll.value[0].children.map(item => item.name)
		]
		columnData.value = arrAll.value.map(item =>
			item.children.map(child => child.name)
		)
		current.value = arrAll.value.findIndex(v => v.id == pid.value) + 1
	}

	const changeTag = (item, index) => {
		current.value = index;
		pid.value = item.id;
		paging.value.reload();
	}
	const onPickerConfirm = (e) => {
		const [firstIndex, secondIndex] = e.indexs;
		const firstCategory = arrAll.value[firstIndex];
		const secondCategory = firstCategory.children[secondIndex];
		show.value = false;
		paging.value.reload();
	}

	const onPickerChange = (e) => {
		const {
			columnIndex,
			index,
		} = e;
		if (columnIndex === 0) {
			uPickerRef.value.setColumnValues(1, columnData.value[index]);
		}
	}

	const inputSearch = () => {
		paging.value.reload()
	}

	const queryList = (pageNo, pageSize) => {
		const queryParams = {
			page: pageNo,
			limit: pageSize,
			name: keyword.value,
			one_category: Number(pid.value),

		};
		if (id.value) {
			queryParams.two_category = Number(id.value);
		}
		goods(queryParams)
			.then(res => {
				// 验证响应结构
				if (res && res.data && Array.isArray(res.data.data)) {
					paging.value.complete(res.data.data);
				} else {

					paging.value.complete([]);
				}
			})
			.catch(err => {
				console.error('API请求失败:', err);
				paging.value.complete([]);
			})

	}
</script>

<style>
	.nor {}
</style>