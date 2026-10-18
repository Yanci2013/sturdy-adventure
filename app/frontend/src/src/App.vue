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
					color: #2B6EFF !important;
					text-decoration: none !important;
				}
				a:hover, .u-link:hover {
					color: #5B8DEF !important;
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

			// #endif

			// ===== 店铺关店检测：拉取装修配置，若关店则全屏覆盖关店提示页 =====
			fetch('/api/frontend/decoration', { cache: 'no-store' })
				.then(r => r.json())
				.then(res => {
					const cfg = (res && res.data) || {}
					if (String(cfg.store_status) === '0') {
						const closeQq = cfg.close_qq || ''
						const closeImg = cfg.close_image || ''
						const closeEl = document.createElement('div')
						closeEl.id = '__store_closed__'
						closeEl.style.cssText = 'position:fixed;inset:0;z-index:9999999;background:#f5f5f5;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 24px;text-align:center;'
						closeEl.innerHTML = `
							${closeImg ? `<img src="${closeImg}" style="width:120px;height:120px;border-radius:16px;margin-bottom:24px;object-fit:cover">` : `<div style="width:120px;height:120px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;margin-bottom:24px;box-shadow:0 4px 20px rgba(0,0,0,0.06)"><svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><line x1="9" y1="22" x2="9" y2="12"/><line x1="15" y1="22" x2="15" y2="12"/></svg></div>`}
							<h2 style="font-size:22px;font-weight:700;color:#1a1a1a;margin:0 0 10px">店铺已关闭 / 打烊</h2>
							<p style="font-size:14px;color:#666;margin:0 0 8px;line-height:1.6">该店目前暂未营业，请稍后再来</p>
							${closeQq ? `<p style="font-size:13px;color:#999;margin:0">联系客服 QQ：<span style="color:#2B6EFF">${closeQq}</span></p>` : ''}
						`
						document.body.appendChild(closeEl)
					}
				})
				.catch(() => {})

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

	/* 全局链接颜色 - 蓝色 */
	a, uni-a, .u-link, .uv-link, [class*="parse"] a, [class*="parse"] uni-a {
		color: #2B6EFF !important;
		text-decoration: none;
	}
	a:hover, .u-link:hover {
		color: #5B8DEF !important;
	}
	/* up-parse / uv-parse 解析内容中的链接 */
	:deep(.up-parse) a, :deep(.u-parse) a, :deep(.uv-parse) a,
	.up-parse-content a, .parse-content a {
		color: #2B6EFF !important;
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
