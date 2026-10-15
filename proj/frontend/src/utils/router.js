import {
	route
} from "../uni_modules/uview-plus";

// 封装全局路由跳转方法
const router = {
	// 跳转到指定页面，保留当前页面
	navigateTo(options) {
		route({
			type: 'navigateTo',
			url: options.url,
			params: options.data
		})
	},

	// 关闭当前页面，跳转到指定页面
	redirectTo(options) {
		route({
			type: 'redirect',
			url: options.url,
			params: options.data
		})
	},

	// 关闭所有页面，打开到应用内的某个页面
	reLaunch(options) {
		route({
			type: 'reLaunch',
			url: options.url,
			params: options.data
		})
	},

	// 跳转到 tabBar 页面，并关闭其他所有非 tabBar 页面
	switchTab(options) {
		route({
			type: 'switchTab',
			url: options.url
		})
	},

	// 关闭当前页面，返回上一页面或多级页面
	navigateBack(options) {
		route({
			type: 'navigateBack',
			delta: options.delta || 1
		})
	}
};

export default router;