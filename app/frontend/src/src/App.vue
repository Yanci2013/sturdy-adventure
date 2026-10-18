<script>
	import { configure } from '@/request/api.js'
	import { syncPendingPayment } from '@/utils/pay.js'
	export default {
		onLaunch: function() {
			// 页面背景由后台装修配置驱动（初始纯白），此处不再写死渐变
			// #ifdef H5
			const style = document.createElement('style')
			style.textContent = `
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

			// 退出面板：默认完全隐藏，点击左侧悬浮入口才展开；退出后自动收起
			if (!document.getElementById('__exit_panel__')) {
				const exitStyle = document.createElement('style')
				exitStyle.textContent = `
					#__exit_trigger__{position:fixed;left:0;top:50%;transform:translateY(-50%);z-index:999998;width:20px;height:52px;border-radius:0 13px 13px 0;background:rgba(17,17,17,0.26);display:flex;align-items:center;justify-content:center;color:#fff;font-size:15px;line-height:1;cursor:pointer;user-select:none;-webkit-tap-highlight-color:transparent;transition:background .2s,opacity .2s;}
					#__exit_trigger__:active{background:rgba(17,17,17,0.5);}
					#__exit_panel__{position:fixed;left:0;top:0;bottom:0;width:150px;z-index:999997;background:rgba(17,17,17,0.92);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);color:#fff;display:flex;flex-direction:column;justify-content:center;gap:14px;padding:0 18px;transform:translateX(-100%);transition:transform .26s ease;box-shadow:4px 0 24px rgba(0,0,0,0.25);}
					#__exit_panel__.show{transform:translateX(0);}
					#__exit_panel__ .ep-btn{height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:600;cursor:pointer;}
					#__exit_panel__ .ep-exit{background:#ef4444;color:#fff;}
					#__exit_panel__ .ep-close{background:rgba(255,255,255,0.14);color:#fff;}`
				document.head.appendChild(exitStyle)

				const trigger = document.createElement('div')
				trigger.id = '__exit_trigger__'
				trigger.textContent = '‹'
				const panel = document.createElement('div')
				panel.id = '__exit_panel__'
				panel.innerHTML = '<div class="ep-btn ep-exit">退出</div><div class="ep-btn ep-close">收起</div>'
				document.body.appendChild(trigger)
				document.body.appendChild(panel)

				function openExitPanel() { panel.classList.add('show') }
				function closeExitPanel() { panel.classList.remove('show') }
				function doExit() {
					closeExitPanel()
					try {
						const pages = (typeof getCurrentPages === 'function') ? getCurrentPages() : []
						if (pages && pages.length > 1) {
							uni.navigateBack({ delta: 1 })
							return
						}
					} catch (e) {}
					try {
						if (window.history.length > 1) { window.history.back() } else { window.close() }
					} catch (e) {}
				}
				trigger.onclick = openExitPanel
				panel.querySelector('.ep-close').onclick = closeExitPanel
				panel.querySelector('.ep-exit').onclick = doExit
				document.addEventListener('click', function(e) {
					if (panel.classList.contains('show') && !panel.contains(e.target) && e.target !== trigger) closeExitPanel()
				}, true)
			}

			// ===== 截图水印：始终以极低透明度铺满全屏，保证任何截图都带「砚世易游Pro由砚辞提供技术支持」；检测到截图操作时临时加深 =====
			if (!document.getElementById('__screenshot_watermark__')) {
				const wmStyle = document.createElement('style')
				wmStyle.textContent = `
#__screenshot_watermark__{position:fixed;inset:0;z-index:999999;pointer-events:none;overflow:hidden;transition:opacity .25s ease;opacity:.04;}
#__screenshot_watermark__.active{opacity:.55;}
#__screenshot_watermark__ .wm-grid{position:absolute;inset:-50%;transform:rotate(-18deg);display:flex;flex-wrap:wrap;gap:60px 90px;align-content:center;justify-content:center;}
#__screenshot_watermark__ .wm-grid span{color:#111;font-size:14px;font-weight:700;white-space:nowrap;letter-spacing:1px;user-select:none;-webkit-user-select:none;}
#__screenshot_watermark__.active .wm-grid span{color:#000;text-shadow:0 0 3px rgba(255,255,255,.7);}`
				document.head.appendChild(wmStyle)

				const wm = document.createElement('div')
				wm.id = '__screenshot_watermark__'
				const grid = document.createElement('div')
				grid.className = 'wm-grid'
				const text = '砚世易游Pro由砚辞提供技术支持'
				for (let i = 0; i < 160; i++) {
					const s = document.createElement('span')
					s.textContent = text
					grid.appendChild(s)
				}
				wm.appendChild(grid)
				document.body.appendChild(wm)

				let flashTimer = null
				function flash() {
					wm.classList.add('active')
					if (flashTimer) clearTimeout(flashTimer)
					flashTimer = setTimeout(() => wm.classList.remove('active'), 2000)
				}
				function isEditable(t) {
					return t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable)
				}
				window.addEventListener('keydown', (e) => {
					const k = (e.key || '').toLowerCase()
					if (k === 'printscreen' || k === 'print' ||
						(e.ctrlKey && k === 's') ||
						(e.ctrlKey && e.shiftKey && k === 's') ||
						(e.ctrlKey && k === 'p') ||
						(e.metaKey && (k === '3' || k === '4' || k === '5'))) {
						flash()
					}
				})
				window.addEventListener('blur', () => flash())
				document.addEventListener('visibilitychange', () => { if (document.hidden) flash() })
				// 右键菜单 / 选中文本：仅在非输入框拦截，避免影响表单输入
				window.addEventListener('contextmenu', (e) => { if (!isEditable(e.target)) e.preventDefault() })
				document.addEventListener('selectstart', (e) => { if (!isEditable(e.target)) e.preventDefault() })
			}

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

	/* 页面背景由后台装修配置驱动（初始纯白），不再写死渐变 */
	/* 让页面容器背景透明，露出全局背景 */
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
