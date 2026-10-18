<script>
	import { configure } from '@/request/api.js'
	import { syncPendingPayment } from '@/utils/pay.js'
	export default {
		onLaunch: function() {
			// 暖珊瑚橘到纯白渐变背景 - 运行时注入确保生效
			// #ifdef H5
			const style = document.createElement('style')
			style.textContent = `
				page, uni-page-body, body {
					background: linear-gradient(180deg, #FF7A45 0%, #FFFFFF 100%) !important;
					background-attachment: fixed !important;
				}				page .page, page .main-container, page .clf-page, page .customer-page {
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
