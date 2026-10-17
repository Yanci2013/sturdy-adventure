import {
	base_url
} from '@/request/env.js'

import {
	http
} from '@/uni_modules/uview-plus'

/*无需token的url请求*/
const unToken = ['']

let unauthorizedShowing = false

const handleUnauthorized = () => {
	uni.removeStorageSync('token')
	uni.removeStorageSync('userId')
	uni.removeStorageSync('user_id')
	if (unauthorizedShowing) return
	unauthorizedShowing = true
	uni.hideLoading()
	uni.showModal({
		title: '提示',
		content: '登录已过期，请重新登录',
		showCancel: false,
		confirmText: '去登陆',
		success: () => {
			unauthorizedShowing = false
			uni.reLaunch({
				url: '/pages/login/login'
			})
		},
		fail: () => {
			unauthorizedShowing = false
		}
	})
}

export const Request = (vm) => {
	// 初始化请求配置
	http.setConfig((config) => {
		/* config 为默认全局配置*/
		config.baseURL = base_url; /* 根域名 */
		return config
	})
}

// 请求拦截
http.interceptors.request.use((config) => { // 可使用async await 做异步操作
	// 初始化请求拦截器时，会执行此方法，此时data为undefined，赋予默认{}
	config.data = config.data || {}
	// 根据unToken，添加对应的请求头
	config.header.token = uni.getStorageSync('token') ? uni.getStorageSync('token') : ''
	return config
}, config => {
	return Promise.reject(config)
})


http.interceptors.response.use((response) => {
	/* 对响应成功做点什么 可使用async await 做异步操作*/
	const data = response.data
	const url = response.config?.url || ''
	
	if (response.data.code == 401) {
		handleUnauthorized()
		return Promise.resolve(response.data)
	}

	if (response.data.code != 1) {
		// 封禁状态不弹toast，由业务层处理跳转
		if (response.data.data && response.data.data.ban_status == 1) {
			return Promise.resolve(response.data)
		}
		// IM 接口不自动显示 toast，由业务层自己处理
		if (!url.includes('/im/') && response.data.msg !== '登录失败') {
			uni.showToast({
				title: response.data.msg,
				icon: 'none'
			})
		}
	}
	return Promise.resolve(response.data)
}, (response) => {
	if (response.data && response.data.code == 401) {
		handleUnauthorized()
	}
	return Promise.reject(response)
})
