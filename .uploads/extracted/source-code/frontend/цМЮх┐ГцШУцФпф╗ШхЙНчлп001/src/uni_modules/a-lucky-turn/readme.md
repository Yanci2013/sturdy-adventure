# a-lucky-turn
幸运转盘

## 属性props
|参数					|说明												|类型		|默认值		|
|:-:					|:-:												|:-:		|:-:			|
|prizesList		|奖品列表										|Array	| []			|
|prizesIndex	|中奖index索引								|Number	|0				|
|color				|文字颜色										|String	|'#cb3011'|
|bgColor			|背景颜色										|String	|'#FFF'		|
|activeColor	|激活项的文字颜色							|String	|'#cb3011'|
|activeBrColor|激活项的边框颜色							|String	|'#cb3011'|
|activeBgColor|激活项的背景颜色							|String	|'#fde8cd'|
|activeBgColor|激活项的背景颜色							|String	|'#fde8cd'|
|fastSteps		|快速跳动的最少步数 步为一圈8	|String	|'16'			|
|slowSteps		|慢速跳动的最少步数 步为一圈8	|String	|'7'			|

## 事件event
|参数		|说明						|默认值										|
|:-:		|:-:						|:-:											|
|end		|转盘跳动结束触发	| prizesIndex中奖奖品index	|
|preview|点击奖品触发			|	当前点击的index	|

## 使用示例

```vue
<template>
  <view class="min-h-100vh lg-1">
    <view class="p-60 fw-b c-white fs-86 ta-c ls-20">幸运转盘</view>

    <view class="f-c">
      <view class="f-c wh-660 br-70 lg-t-b-_ffa15e-_ff7838 shadow3">
        <view class="f-c wh-590 br-60 shadow1">
          <view class="f-c wh-540 br-52 bg-white">
            <view class="f-c wh-560 br-52 shadow2 bg-_fdf5ec">
              
              <!-- 9宫格转盘内容 -->
              <a-lucky-turn ref="luckyTurntable" :prizesList="prizesList" :prizesIndex="prizesIndex" @end="end" @preview="preview"></a-lucky-turn>
              
            </view>
          </view>
        </view>
      </view>
    </view>

    <!-- 增加自己的背景图 由ui提供的背景图 -->
    <!-- <view class="f-c">
      <gb-choujiang ref="luckyTurntable" :prizesList="prizesList" :prizesIndex="prizesIndex" @end="end"></gb-choujiang>
    </view> -->

    <view class="f-c pt-60" style="">
      <view @click="luckyTurntable" class="btn br-full fw-b">2次抽奖机会</view>
    </view>
  </view>
</template>

<script>
export default {
  data() {
    return {
      // 中奖的索引，即prizesList数组的索引
      prizesIndex: 1, // 注意：prizesIndex 为0-8 且不能等于4，4是立即抽奖
      prizesList: [
        {
          image: 'https://gd-hbimg.huaban.com/32d95e566bbbf22e0bbdfd32f363e011bcd1cca012ffc-WD4ZDs_fw658webp',
          name: '0个金币'
        },
        {
          image: 'https://gd-hbimg.huaban.com/32d95e566bbbf22e0bbdfd32f363e011bcd1cca012ffc-WD4ZDs_fw658webp',
          name: '1个金币'
        },
        {
          image: 'https://gd-hbimg.huaban.com/32d95e566bbbf22e0bbdfd32f363e011bcd1cca012ffc-WD4ZDs_fw658webp',
          name: '2个金币'
        },
        {
          image: 'https://gd-hbimg.huaban.com/32d95e566bbbf22e0bbdfd32f363e011bcd1cca012ffc-WD4ZDs_fw658webp',
          name: '3个金币'
        },
        {
          // 存在图片就会展示  支持：仅图片 仅文字 图片和文字
          // image: 'https://gd-hbimg.huaban.com/32d95e566bbbf22e0bbdfd32f363e011bcd1cca012ffc-WD4ZDs_fw658webp',
          name: '抽奖'
        },
        {
          image: 'https://gd-hbimg.huaban.com/32d95e566bbbf22e0bbdfd32f363e011bcd1cca012ffc-WD4ZDs_fw658webp',
          name: '5个金币'
        },
        {
          image: 'https://gd-hbimg.huaban.com/32d95e566bbbf22e0bbdfd32f363e011bcd1cca012ffc-WD4ZDs_fw658webp',
          name: '6个金币'
        },
        {
          image: 'https://gd-hbimg.huaban.com/32d95e566bbbf22e0bbdfd32f363e011bcd1cca012ffc-WD4ZDs_fw658webp',
          name: '7个金币'
        },
        {
          image: 'https://gd-hbimg.huaban.com/32d95e566bbbf22e0bbdfd32f363e011bcd1cca012ffc-WD4ZDs_fw658webp',
          name: '8个金币'
        }
      ]
    };
  },
  methods: {
    end(prizesIndex) {
      uni.showToast({
        title: '恭喜获得：' + this.prizesList[prizesIndex].name,
        icon: 'none'
      });

      // 设置下一次中奖奖品
      this.prizesIndex = this.generateRandomNumber();
    },
    // 查看当前点击的奖品
    preview(index) {
      const prize = this.prizesList[index]
      
      // 预览图片 示例
      uni.previewImage({
        urls: [prize.image],
        longPressActions: {
          success: (data) => {
            console.log('选中了第' + (data.tapIndex + 1) + '个按钮,第' + (data.index + 1) + '张图片');
          },
          fail: (err)  => {
            console.log(err.errMsg);
          }
        }
      });
    },
    generateRandomNumber() {
      let num = Math.floor(Math.random() * 10); // 生成0-9的随机数
      if ([4, 9].includes(num)) {
        // 排除4,9
        num = this.generateRandomNumber();
      }
      return num;
    },
    luckyTurntable(item) {
      this.$refs.luckyTurntable.begin();
    }
  }
};
</script>

<style scoped>
/* atomcss 生成 */
/* atomcss 文档 https://chenmeizhou.gitee.io/atom-css-doc/ */
.min-h-100vh {
  min-height: 100vh;
}
.f-c {
  display: flex;
  flex-direction: row;
  justify-content: center;
  align-items: center;
}
.lg-t-b-_ffa15e-_ff7838 {
  background-image: linear-gradient(to bottom, #ffa15e, #ff7838);
}
.br-52 {
  border-radius: 52rpx;
}
.br-60 {
  border-radius: 60rpx;
}
.br-70 {
  border-radius: 70rpx;
}
.br-full {
  border-radius: 9999rpx;
}
.c-white {
  color: white;
}
.bg-white {
  background-color: white;
}
.bg-_fdf5ec {
  background-color: #fdf5ec;
}
.wh-540 {
  width: 564rpx;
  height: 564rpx;
}
.wh-560 {
  width: 560rpx;
  height: 560rpx;
}
.wh-590 {
  width: 590rpx;
  height: 590rpx;
}
.wh-660 {
  width: 660rpx;
  height: 660rpx;
}
.ta-c {
  text-align: center;
}
.ls-20 {
  letter-spacing: 20rpx;
}
.fs-86 {
  font-size: 86rpx;
}
.p-60 {
  padding: 60rpx;
}
.pt-60 {
  padding-top: 60rpx;
}
.fw-b {
  font-weight: 700;
}


.lg-1 {
  background-image: linear-gradient(to bottom, #ff2300, #ffaf69, #fff6d5);
}
.shadow1 {
  box-shadow: rgb(203 48 17 / 70%) 0px 0px 4px 3px inset, rgb(255 99 67) 0px 1px 0px 1px inset;
}
.shadow2 {
  box-shadow: inset 0px 0px 5rpx 5rpx #ffbd7c;
}
.shadow3 {
  box-shadow: inset 0px 0px 4rpx 4rpx #ffe8cb;
}
.btn {
  font-size: 46rpx;
  box-shadow: rgb(253 109 41 / 77%) 0px 12px 20px -5px;
  border: #fff9be solid 2px;
  background-image: linear-gradient(to right, #ffcd4e, #ffcc5c);
  padding: 30rpx 100rpx;
  color: #cb3011;
}
</style>

```