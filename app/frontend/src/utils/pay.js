import { syncYipayPayment, qiangdanSync } from '@/request/api.js'

let paymentSyncing = false

export function syncPendingPayment() {
  const pending = uni.getStorageSync('pending_yipay_order')
  if (!pending || !pending.out_trade_no || !pending.scene || !uni.getStorageSync('token')) return

  const now = Date.now()
  if (now - Number(pending.created_at || 0) > 24 * 60 * 60 * 1000) {
    uni.removeStorageSync('pending_yipay_order')
    return
  }
  if (paymentSyncing || now - Number(pending.last_check_at || 0) < 3000) return

  paymentSyncing = true
  pending.last_check_at = now
  uni.setStorageSync('pending_yipay_order', pending)

  // 根据场景选择同步接口
  const syncApi = pending.scene === 'qiangdan' ? qiangdanSync : syncYipayPayment
  return syncApi({
    out_trade_no: pending.out_trade_no,
    scene: pending.scene
  }).then(res => {
    if (res && res.code == 1 && res.data && res.data.paid) {
      uni.removeStorageSync('pending_yipay_order')
      uni.showToast({ title: '支付成功', icon: 'success' })
    }
    return res
  }).finally(() => {
    paymentSyncing = false
  })
}

/**
 * 打开易支付收银台。
 */
function redirectPay(params, failCallback) {
  const url = params && (params.url || params.pay_url);
  if (!url) {
    return false;
  }
  if (!/^https?:\/\//i.test(url)) {
    failCallback && failCallback('支付地址格式不正确');
    return true;
  }

  if (params.out_trade_no && params.scene) {
    uni.setStorageSync('pending_yipay_order', {
      out_trade_no: String(params.out_trade_no),
      scene: String(params.scene),
      created_at: Date.now(),
      last_check_at: 0
    });
  }

  // #ifdef H5
  window.location.assign(url);
  return true;
  // #endif

  // #ifdef APP-PLUS
  plus.runtime.openURL(url, () => {
    failCallback && failCallback('无法打开支付页面');
  });
  return true;
  // #endif

  // #ifdef MP
  uni.navigateTo({
    url: '/pages/pay/index?url=' + encodeURIComponent(url),
    fail: () => {
      uni.setClipboardData({
        data: url,
        success: () => uni.showToast({ title: '支付地址已复制，请在浏览器打开', icon: 'none' })
      });
    }
  });
  return true;
  // #endif

  failCallback && failCallback('当前客户端无法打开支付页面');
  return true;
}

// 导出支付方法
export default {
  redirectPay,
  syncPendingPayment
};
