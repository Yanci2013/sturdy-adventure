import App from './App';
import uvUI from '@/uni_modules/uv-ui-tools'

// 初始化 uni.$u（uview-plus 路由模块依赖）
if (!uni.$u) {
	uni.$u = {}
}

import {
	Request
} from '@/request/request'
// 引入全局组件
import commonButton from "@/compontents/common-button.vue"
import commonItem from "@/compontents/commom-item.vue"
import commonPrice from "@/compontents/common-price.vue"
import orderButton from "@/compontents/common-order-button.vue"
import goodsItem from "@/compontents/common-goods-item.vue"
import commonTabbar from "@/compontents/common-tabbar.vue"
import commonUpload from "@/compontents/common-upload.vue"


import {
	createSSRApp
} from 'vue'
export function createApp() {
	const app = createSSRApp(App)
	app.use(uvUI);
	app.component('commonButton', commonButton);
	app.component('commonItem', commonItem);
	app.component('commonPrice', commonPrice);
	app.component('orderButton', orderButton);
	app.component('goodsItem', goodsItem);
	app.component('commonTabbar', commonTabbar);
	app.component('commonUpload', commonUpload);
	Request(app)
	return {
		app
	}
}