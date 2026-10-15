<template>
	<z-paging ref="paging" v-model="dataList" @query="queryList">
		<u-navbar autoBack placeholder>
			<template #center>
				<view class="" style="width: 500rpx;">
					<u-search placeholder="搜索商品标题" v-model="keyword" :showAction="false" style="width: 500rpx;"
						@search="inputSearch"></u-search>
				</view>

			</template>
		</u-navbar>
		<view class="mt-2">
			<up-grid col="2">
				<u-grid-item v-for="(item,index) in dataList" :key="index">
					<commonItem :item="item"></commonItem>
				</u-grid-item>
			</up-grid>
		</view>
	</z-paging>

</template>

<script setup>
	import {
		ref
	} from 'vue';
	import {
		test
	} from '../../uni_modules/uview-plus';
	import {
		goods
	} from '../../request/api';
	const paging = ref(null)
	const dataList = ref([])
	const keyword = ref('')

	const inputSearch = () => {
		paging.value.reload()
	}

	const queryList = (pageNo, pageSize) => {
		goods({
			page: pageNo,
			limit: pageSize,
			name: keyword.value,
			one_category: '0',
			two_category: '0'
		}).then(res => {
			paging.value.complete(res.data.data);
		})

	}
</script>

<style>
</style>