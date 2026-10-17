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

			// ===== 底部悬浮导航（后台配置动态渲染）=====
			const FN_ICONS = {
				home: '<path d="M3 12 12 3l9 9v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
				grid: '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
				user: '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
				chat: '<path d="M4 5h16v11H8l-4 4z"/>',
				play: '<polygon points="6 4 20 12 6 20"/>',
				gift: '<path d="M20 12v9H4v-9"/><path d="M2 7h20v5H2z"/><path d="M12 22V7"/><path d="M12 7S10 2 7 4s1 5 5 3zM12 7s2-5 5-3-1 5-5 3z"/>',
				plus: '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
			}
			function fnIcon(name) {
				const p = FN_ICONS[name] || FN_ICONS.home
				return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" style="width:24px;height:24px;display:block">' + p + '</svg>'
			}
			function fnSvg(name) {
				return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" style="width:24px;height:24px;display:block">' + (FN_ICONS[name] || FN_ICONS.home) + '</svg>'
			}
			function fnHideOnLogin() {
				const h = location.hash || ''
				return /login/i.test(h)
			}
			function initFloatingNav() {
				if (document.getElementById('__floating_nav__')) return
				const st = document.createElement('style')
				st.textContent = `
#__floating_nav__{position:fixed;left:0;right:0;bottom:calc(env(safe-area-inset-bottom,0px)+20px);z-index:99999;pointer-events:none;display:flex;justify-content:center;}
#__floating_nav__ *{box-sizing:border-box;}
/* 形态① 悬浮椭圆 */
.fn-ellipse{display:flex;align-items:center;gap:6px;padding:10px 16px;background:rgba(255,255,255,.92);backdrop-filter:blur(14px);border-radius:999px;box-shadow:0 12px 40px rgba(0,0,0,.18),0 2px 8px rgba(0,0,0,.08);pointer-events:auto;border:1px solid rgba(255,255,255,.6);}
.fn-ellipse .fn-item{display:flex;flex-direction:column;align-items:center;gap:3px;padding:6px 16px;border-radius:999px;cursor:pointer;transition:.2s;min-width:54px;}
.fn-ellipse .fn-item:hover{transform:translateY(-2px);}
.fn-ellipse .fn-label{font-size:11px;font-weight:600;}
/* 形态② 圆形主按钮 + 圆环弹出菜单 */
.fn-circle-wrap{position:relative;pointer-events:auto;}
.fn-circle-main{width:60px;height:60px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 10px 30px rgba(0,0,0,.3);transition:transform .3s,box-shadow .3s;border:3px solid rgba(255,255,255,.5);}
.fn-circle-main.open{transform:rotate(45deg);}
.fn-circle-ring{position:absolute;bottom:0;left:50%;transform:translateX(-50%);width:0;height:0;transition:.35s cubic-bezier(.34,1.56,.64,1);}
.fn-circle-ring.open{width:230px;height:230px;}
.fn-circle-ring .fn-item{position:absolute;width:52px;height:52px;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1px;background:#fff;box-shadow:0 6px 20px rgba(0,0,0,.2);cursor:pointer;opacity:0;transition:.3s;}
.fn-circle-ring.open .fn-item{opacity:1;}
.fn-circle-ring .fn-label{font-size:10px;font-weight:600;}
/* 形态③ 容器横向平行排布 */
.fn-row{display:flex;align-items:stretch;gap:10px;padding:10px;background:rgba(255,255,255,.92);backdrop-filter:blur(14px);border-radius:22px;box-shadow:0 12px 40px rgba(0,0,0,.18);pointer-events:auto;border:1px solid rgba(255,255,255,.6);}
.fn-row .fn-item{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;width:78px;padding:10px 6px;border-radius:16px;cursor:pointer;transition:.2s;}
.fn-row .fn-item:hover{transform:translateY(-3px);}
.fn-row .fn-label{font-size:11px;font-weight:600;}
.fn-row .fn-divider{width:1px;background:linear-gradient(180deg,transparent,rgba(0,0,0,.08),transparent);margin:6px 0;}`
				document.head.appendChild(st)

				const wrap = document.createElement('div')
				wrap.id = '__floating_nav__'
				document.body.appendChild(wrap)

				function navGo(link) {
					if (!link) return
					uni.reLaunch({ url: link.startsWith('/') ? link.slice(1) : link })
				}
				function render() {
					const items = (window.__floatingNav || [])
					const style = window.__navStyle || 'float_ellipse'
					if (!items.length || fnHideOnLogin()) { wrap.style.display = 'none'; return }
					wrap.style.display = 'flex'
					const color = (items[0] && items[0].color) || '#22c55e'
					let html = ''
					if (style === 'float_ellipse') {
						html = '<div class="fn-ellipse">' + items.map(it =>
							`<div class="fn-item" data-link="${it.link || ''}" style="color:${it.color || color}"><span>${fnIcon(it.icon)}</span><span class="fn-label">${it.text || ''}</span></div>`
						).join('') + '</div>'
					} else if (style === 'float_circle') {
						const n = items.length
						html = `<div class="fn-circle-wrap">
							<div class="fn-circle-ring" id="fnRing">` + items.map((it, i) => {
								const angle = -Math.PI + (Math.PI / (n - 1 || 1)) * i
								const r = 92
								const x = Math.cos(angle) * r
								const y = Math.sin(angle) * r - 10
								return `<div class="fn-item" data-link="${it.link || ''}" style="color:${it.color || color};left:calc(50% + ${x}px - 26px);bottom:${-y}px;transition-delay:${i * 40}ms"><span>${fnIcon(it.icon)}</span><span class="fn-label">${it.text || ''}</span></div>`
							}).join('') + `</div>
							<div class="fn-circle-main" id="fnMain" style="background:linear-gradient(135deg,${color},${color}cc)"><span style="color:#fff">${fnSvg('plus')}</span></div>
						</div>`
					} else {
						html = '<div class="fn-row">' + items.map((it, i) =>
							(i ? '<div class="fn-divider"></div>' : '') +
							`<div class="fn-item" data-link="${it.link || ''}" style="color:${it.color || color}"><span>${fnIcon(it.icon)}</span><span class="fn-label">${it.text || ''}</span></div>`
						).join('') + '</div>'
					}
					wrap.innerHTML = html
					wrap.querySelectorAll('.fn-item').forEach(el => {
						el.addEventListener('click', () => navGo(el.getAttribute('data-link')))
					})
					const main = document.getElementById('fnMain')
					const ring = document.getElementById('fnRing')
					if (main && ring) {
						main.addEventListener('click', (e) => {
							e.stopPropagation()
							main.classList.toggle('open')
							ring.classList.toggle('open')
						})
					}
				}
				// 轮询等待后台装修配置
				let tries = 0
				const timer = setInterval(() => {
					tries++
					if (window.__floatingNav) {
						clearInterval(timer)
						render()
						window.__onNavConfig = render
						window.addEventListener('hashchange', render)
					} else if (tries > 40) {
						clearInterval(timer)
					}
				}, 200)
			}
			initFloatingNav()
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
