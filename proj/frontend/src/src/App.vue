<script>
	import { configure } from '@/request/api.js'
	import { syncPendingPayment } from '@/utils/pay.js'

	// 应用后台自定义 UI 配置（背景色/背景图/主题色/底部导航颜色）
	function applyUiConfig(data) {
		// 保存完整配置到 storage，供 tabbar 等组件读取
		try { uni.setStorageSync('ui_config', data || {}); } catch (e) {}
		// #ifdef H5
		const primary = data.theme_primary || '#FF7A45'
		const secondary = data.theme_secondary || '#FFB199'
		const bgColor = data.app_bg_color || '#FFFFFF'
		const bgImage = data.app_bg_image || ''
		const tabbarActive = data.tabbar_active_color || primary
		const tabbarInactive = data.tabbar_inactive_color || 'rgba(60,40,30,.55)'
		const badgeColor = data.badge_color || '#FF3B30'

		// 构建背景样式
		let bgStyle
		if (bgImage) {
			const imgUrl = bgImage.startsWith('http') ? bgImage : (bgImage.startsWith('/') ? bgImage : '/' + bgImage)
			bgStyle = `background: linear-gradient(180deg, ${primary}22 0%, ${bgColor} 30%) !important; background-image: url('${imgUrl}') !important; background-size: cover !important; background-position: center top !important; background-attachment: fixed !important; background-repeat: no-repeat !important;`
		} else {
			bgStyle = `background: linear-gradient(180deg, ${primary}22 0%, ${bgColor} 30%, ${bgColor} 100%) !important; background-attachment: fixed !important;`
		}

		// 移除旧的动态样式（如果存在）
		const oldStyle = document.getElementById('dynamic-ui-config')
		if (oldStyle) oldStyle.remove()

		const style = document.createElement('style')
		style.id = 'dynamic-ui-config'
		style.textContent = `
			:root {
				--theme-primary: ${primary} !important;
				--theme-secondary: ${secondary} !important;
				--bg-color: ${bgColor} !important;
				--tabbar-active: ${tabbarActive} !important;
				--tabbar-inactive: ${tabbarInactive} !important;
				--badge-color: ${badgeColor} !important;
			}
			page, uni-page-body, body {
				${bgStyle}
			}
			page .page, page .main-container, page .clf-page, page .customer-page {
				background: transparent !important;
			}
			html { --primary-color: ${primary} !important; }
			button:active, .u-button:active, .btn:active, .service-btn:active,
			.classify_fixed_button:active, .sort-btn:active, .action-btn:active,
			.build-same-btn:active, .cu-btn:active,
			uni-view[class*="btn"]:active, uni-view[class*="Btn"]:active {
				background: ${primary} !important;
				opacity: 0.85;
			}
			a, uni-a, .u-link, .uv-link, [class*="parse"] a, [class*="parse"] uni-a {
				color: ${primary} !important;
				text-decoration: none !important;
			}
			a:hover, .u-link:hover { color: ${secondary} !important; }
			uni-button[type=primary] { background-color: ${primary} !important; }
			uni-button[type=primary][plain] { color: ${primary} !important; border-color: ${primary} !important; }
			uni-button[loading][type=primary][plain] { color: ${primary} !important; }
			uni-button[loading][type=primary], .button-hover[type=primary] { background-color: ${secondary} !important; }
			.uni-checkbox-input svg, .uni-checkbox-input { color: ${primary} !important; border-color: ${primary} !important; }
			.uni-checkbox-input:not([disabled]):hover { border-color: ${primary} !important; }
			uni-slider .uni-slider-track { background-color: ${primary} !important; }
			.uni-modal__btn_primary { color: ${primary} !important; }
			.uni-system-open-launch-weapp__btn, .uni-loading__spinner { background-color: ${primary} !important; }
		`
		document.head.appendChild(style)
		// #endif
	}

	export default {
		onLaunch: function() {
			// 暖珊瑚橘到纯白渐变背景 - 运行时注入确保生效
			// #ifdef H5
			const style = document.createElement('style')
			style.textContent = `
				page, uni-page-body, body {
					background: linear-gradient(180deg, #FF7A45 0%, #FFFFFF 100%) !important;
					background-attachment: fixed !important;
				}
				page .page, page .main-container, page .clf-page, page .customer-page {
					background: transparent !important;
				}
				button:active, .u-button:active, .btn:active, .service-btn:active,
				.classify_fixed_button:active, .sort-btn:active, .action-btn:active,
				.build-same-btn:active, .cu-btn:active,
				uni-view[class*="btn"]:active, uni-view[class*="Btn"]:active {
					background: #FF7A45 !important;
					opacity: 0.85;
				}
				a, uni-a, .u-link, .uv-link, [class*="parse"] a, [class*="parse"] uni-a {
					color: #FF7A45 !important;
					text-decoration: none !important;
				}
				a:hover, .u-link:hover {
					color: #FF9D6E !important;
				}
				/* uni-app 内置组件主色覆盖 */
				html { --primary-color: #FF7A45 !important; }
				uni-button[type=primary] { background-color: #FF7A45 !important; }
				uni-button[type=primary][plain] { color: #FF7A45 !important; border-color: #FF7A45 !important; }
				uni-button[loading][type=primary][plain] { color: #FF7A45 !important; }
				uni-button[loading][type=primary], .button-hover[type=primary] { background-color: #E66A35 !important; }
				.uni-checkbox-input svg, .uni-checkbox-input { color: #FF7A45 !important; border-color: #FF7A45 !important; }
				.uni-checkbox-input:not([disabled]):hover { border-color: #FF7A45 !important; }
				uni-slider .uni-slider-track { background-color: #FF7A45 !important; }
				.uni-modal__btn_primary { color: #FF7A45 !important; }
				.uni-system-open-launch-weapp__btn, .uni-loading__spinner { background-color: #FF7A45 !important; }
			`
			document.head.appendChild(style)
			// #endif
			// 拉取后端配置，缓存俱乐部名称（后台可改，默认创世网络）
			configure().then(res => {
				if (res && res.code == 1 && res.data) {
					const name = res.data.club_name || res.data.name || res.data.title || '创世网络'
					uni.setStorageSync('club_name', name)
					// 应用后台自定义 UI 配置（背景色/背景图/主题色）
					applyUiConfig(res.data)
				} else {
					uni.setStorageSync('club_name', '创世网络')
				}
			}).catch(() => {
				uni.setStorageSync('club_name', '创世网络')
			})
		},
		onShow: function() {
			syncPendingPayment()
		},
		onHide: function() {
		}
	}
</script>

<style lang="scss">
	/* 注意要写在第一行，注意不能引入至uni.scss，同时给style标签加入lang="scss"属性 */
	@import "@/uni_modules/uview-plus/index.scss";
	/* 引入free样式库 */
	@import url("./common/free.css");
	/* 引入图标字体库 */
	@import url("./static/css/iconfont.css");
	/* 引入全局样式库 */

	/* 全局页面背景 - 暖珊瑚橘到纯白渐变 */
	page {
		background: linear-gradient(180deg, #FF7A45 0%, #FFFFFF 100%) !important;
		background-attachment: fixed !important;
	}
	uni-page-body, body {
		background: linear-gradient(180deg, #FF7A45 0%, #FFFFFF 100%) !important;
		background-attachment: fixed !important;
	}
	/* 让页面容器背景透明，露出全局渐变背景图 */
	page .page,
	page .main-container,
	page .clf-page,
	page .customer-page {
		background: transparent !important;
	}

	/* 全局按钮点击态 - 暖珊瑚橘 */
	button:active,
	uni-button:active,
	.u-button:active,
	.btn:active,
	.service-btn:active,
	.classify_fixed_button:active,
	.sort-btn:active,
	.action-btn:active,
	.build-same-btn:active,
	.cu-btn:active,
	view[class*="btn"]:active,
	view[class*="Btn"]:active {
		background: #FF7A45 !important;
		opacity: 0.85;
	}

	/* 全局链接颜色 - 暖珊瑚橘 */
	a, uni-a, .u-link, .uv-link, [class*="parse"] a, [class*="parse"] uni-a {
		color: #FF7A45 !important;
		text-decoration: none;
	}
	a:hover, .u-link:hover {
		color: #FF9D6E !important;
	}
	/* up-parse / uv-parse 解析内容中的链接 */
	:deep(.up-parse) a, :deep(.u-parse) a, :deep(.uv-parse) a,
	.up-parse-content a, .parse-content a {
		color: #FF7A45 !important;
	}

	/* uni-app 内置组件主色覆盖 - 暖珊瑚橘 */
	html { --primary-color: #FF7A45 !important; }
	uni-button[type=primary] { background-color: #FF7A45 !important; }
	uni-button[type=primary][plain] { color: #FF7A45 !important; border-color: #FF7A45 !important; }
	uni-button[loading][type=primary][plain] { color: #FF7A45 !important; }
	uni-button[loading][type=primary], .button-hover[type=primary] { background-color: #E66A35 !important; }
	.uni-checkbox-input svg, .uni-checkbox-input { color: #FF7A45 !important; border-color: #FF7A45 !important; }
	.uni-checkbox-input:not([disabled]):hover { border-color: #FF7A45 !important; }
	uni-slider .uni-slider-track { background-color: #FF7A45 !important; }
	.uni-modal__btn_primary { color: #FF7A45 !important; }
	.uni-system-open-launch-weapp__btn, .uni-loading__spinner { background-color: #FF7A45 !important; }
</style>
