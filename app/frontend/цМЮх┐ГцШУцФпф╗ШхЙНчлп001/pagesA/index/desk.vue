<template>
  <view class="page">
    <scroll-view scroll-y class="content">
      <view class="content-inner">
        <view class="pay-amount-card card-glass">
          <view class="pay-amount-row">
            <text class="pay-amount-symbol">¥</text>
            <text class="pay-amount-num">{{price}}</text>
          </view>
          <view class="pay-countdown">
            <text class="pay-countdown-label">支付剩余时间</text>
            <view class="pay-countdown-time">
              <text class="pay-countdown-cell">{{hours}}</text>
              <text class="pay-countdown-dot">:</text>
              <text class="pay-countdown-cell">{{minutes}}</text>
              <text class="pay-countdown-dot">:</text>
              <text class="pay-countdown-cell">{{seconds}}</text>
            </view>
          </view>
        </view>
        <view class="pay-way-card card-glass">
          <view class="pay-way-title">支付方式</view>
          <uv-radio-group v-model="payType" placement="column">
            <uv-radio class="pay-way-item" name="balance" :customStyle="{marginBottom:'8rpx'}">
              <view class="pay-type">
                <uv-icon name="red-packet" size="44rpx" class="pay-type-icon"></uv-icon>
                <text class="pay-type-title">余额支付</text>
              </view>
            </uv-radio>
            <uv-radio class="pay-way-item" name="yipay">
              <view class="pay-type">
                <uv-icon name="weixin-circle-fill" size="44rpx" color="#07c160" class="pay-type-icon"></uv-icon>
                <text class="pay-type-title">在线支付</text>
              </view>
            </uv-radio>
          </uv-radio-group>
        </view>
        <view class="pay-agreement" @click="toggleAgreement">
          <view class="pay-agreement-checkbox">
            <uv-checkbox-group>
              <uv-checkbox :checked="agreed" @change="onAgreementChange" shape="circle" activeColor="#faad14"></uv-checkbox>
            </uv-checkbox-group>
          </view>
          <text class="pay-agreement-text">本平台服务仅限成年人，本人确认已满18岁</text>
        </view>
        <view class="bottom-spacer"></view>
      </view>
    </scroll-view>
    <view class="footer-bar">
      <view class="footer-left">
        <text class="footer-label">需支付</text>
        <text class="footer-symbol">¥</text>
        <text class="footer-total">{{price}}</text>
      </view>
      <view class="btn-settle" @click="pay">立即支付</view>
    </view>
  </view>
</template>

<script setup>
import { ref, onUnmounted } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { orderPay } from '../../request/api'
import payUtil from '../../utils/pay'

const price = ref('')
const id = ref('')
const award = ref('')
const payType = ref('yipay')
const agreed = ref(false)
const hours = ref('00')
const minutes = ref('14')
const seconds = ref('59')
let countdownTimer = null
let totalSeconds = 15 * 60

const toggleAgreement = () => { agreed.value = !agreed.value }
const onAgreementChange = (e) => { agreed.value = e }

const startCountdown = () => {
  countdownTimer = setInterval(() => {
    if (totalSeconds <= 0) {
      clearInterval(countdownTimer)
      uni.showToast({ title: '支付超时', icon: 'none' })
      return
    }
    totalSeconds--
    const h = Math.floor(totalSeconds / 3600)
    const m = Math.floor((totalSeconds % 3600) / 60)
    const s = totalSeconds % 60
    hours.value = h < 10 ? '0' + h : '' + h
    minutes.value = m < 10 ? '0' + m : '' + m
    seconds.value = s < 10 ? '0' + s : '' + s
  }, 1000)
}

onLoad((prop) => {
  price.value = prop.price
  id.value = prop.id
  award.value = prop.award || ''
  startCountdown()
})

onUnmounted(() => { if (countdownTimer) clearInterval(countdownTimer) })

const pay = async () => {
  if (!agreed.value) {
    uni.showToast({ title: '请先勾选协议', icon: 'none' })
    return
  }
  uni.showLoading({ title: '支付中...' })
  const res = await orderPay({ id: id.value, pay_type: payType.value })
  if (res.code != 1) { uni.hideLoading(); uni.showToast({ title: res.msg || '支付失败', icon: 'none' }); return }
  if (payType.value === 'balance') {
    uni.hideLoading()
    uni.showToast({ title: '支付成功', icon: 'success' })
    setTimeout(() => {
      uni.redirectTo({ url: '/pagesA/mine/order/detail?id=' + id.value + '&from=pay' })
    }, 1000)
  } else {
    uni.hideLoading()
    payUtil.redirectPay(res.data, (err) => {
      uni.showToast({ title: err || '无法打开易支付', icon: 'none' })
    })
  }
}
</script>

<style lang="scss" scoped>
.page {
  min-height: 100vh;
  background: #f5f6f9;
  display: flex;
  flex-direction: column;
  padding-bottom: calc(140rpx + env(safe-area-inset-bottom));
}
.content {
  flex: 1;
  min-height: 0;
  padding: 24rpx 0 32rpx;
  box-sizing: border-box;
}
.content-inner {
  padding: 0 24rpx;
  box-sizing: border-box;
}
.card-glass {
  background: rgba(255,255,255,.55);
  backdrop-filter: blur(24rpx);
  -webkit-backdrop-filter: blur(24rpx);
  border: 1rpx solid rgba(255,255,255,.7);
  border-radius: 24rpx;
  padding: 28rpx;
  box-shadow: 0 2rpx 20rpx rgba(0,0,0,.04), inset 0 1rpx rgba(255,255,255,.9);
}
.pay-amount-card {
  margin-bottom: 24rpx;
  padding: 40rpx 24rpx 32rpx;
  text-align: center;
}
.pay-amount-row {
  display: flex;
  align-items: baseline;
  justify-content: center;
  margin-bottom: 24rpx;
}
.pay-amount-symbol {
  font-size: 32rpx;
  color: #da4a44;
  font-weight: 600;
  margin-right: 4rpx;
}
.pay-amount-num {
  font-size: 56rpx;
  color: #da4a44;
  font-weight: 700;
  line-height: 1.2;
}
.pay-countdown {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: 12rpx;
}
.pay-countdown-label {
  font-size: 24rpx;
  color: #666;
}
.pay-countdown-time {
  display: flex;
  align-items: center;
}
.pay-countdown-cell {
  min-width: 44rpx;
  padding: 6rpx 8rpx;
  background: rgba(0,0,0,.06);
  border-radius: 8rpx;
  font-size: 24rpx;
  color: #333;
  font-weight: 500;
  text-align: center;
}
.pay-countdown-dot {
  margin: 0 6rpx;
  font-size: 24rpx;
  color: #333;
  font-weight: 600;
}
.pay-way-card {
  margin-bottom: 24rpx;
  padding: 24rpx;
}
.pay-way-title {
  font-size: 28rpx;
  color: #333;
  font-weight: 600;
  margin-bottom: 20rpx;
  padding-bottom: 16rpx;
  border-bottom: 1rpx solid rgba(0,0,0,.06);
}
.pay-way-item {
  margin-bottom: 8rpx;
}
.pay-type {
  display: flex;
  align-items: center;
  width: 100%;
  min-height: 96rpx;
  padding: 8rpx 0;
}
.pay-type-icon {
  width: 44rpx;
  height: 44rpx;
  margin-right: 20rpx;
}
.pay-type-title {
  font-size: 28rpx;
  color: #333;
}
.pay-agreement {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0;
  padding: 24rpx 0;
}
.pay-agreement-checkbox {
  flex-shrink: 0;
  line-height: 0;
}
.pay-agreement-text {
  font-size: 24rpx;
  color: #666;
  margin-left: 12rpx;
}
.bottom-spacer {
  height: 24rpx;
}
.footer-bar {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 10;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20rpx 24rpx;
  padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
  background: #fff;
  box-shadow: 0 -2rpx 12rpx rgba(0,0,0,.06);
}
.footer-left {
  display: flex;
  align-items: baseline;
  gap: 4rpx;
}
.footer-label {
  font-size: 24rpx;
  color: #666;
  margin-right: 4rpx;
}
.footer-symbol {
  font-size: 28rpx;
  color: #da4a44;
  font-weight: 600;
}
.footer-total {
  font-size: 36rpx;
  color: #da4a44;
  font-weight: 700;
}
.btn-settle {
  height: 80rpx;
  line-height: 80rpx;
  min-width: 240rpx;
  padding: 0 40rpx;
  font-size: 28rpx;
  color: #fff;
  background: linear-gradient(135deg, #ffc300, #faad14);
  border-radius: 16rpx;
  text-align: center;
}
</style>
