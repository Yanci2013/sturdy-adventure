<template>
  <view class="box f-c wrap w-600">
    <view v-for="(item, index) in prizesList" :key="index">
      <view
        class="f-col-c m-10 br-40 wh-150"
        @tap.stop="onClick(index)"
        :style="index == 4 ? actionStyle : index === activeIndex ? activeStyle : Style"
      >
        <!-- 奖品图片 -->
        <image :src="item.image" mode="widthFix" class="wh-60" v-if="item.image"></image>
        <!-- 奖品文字 -->
        <view class="mt-5" v-if="index !== 4">
          <text class="fw-b fs-21">{{ item.name }}</text>
        </view>

        <!-- 中心的 抽奖 按钮 -->
        <view class="mt-5" v-if="index == 4 && item.name">
          <text class="fs-40 fw-b ls-10">{{ item.name }}</text>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
export default {
  props: {
    // 列表
    prizesList: {
      type: Array,
      default: []
    },
    // 中奖索引
    prizesIndex: {
      type: Number,
      default: 0
    },
    color: {
      type: String,
      default: '#cb3011'
    },
    bgColor: {
      type: String,
      default: '#FFF'
    },
    activeColor: {
      type: String,
      default: '#cb3011'
    },
    activeBrColor: {
      type: String,
      default: '#ffc28c'
    },
    activeBgColor: {
      type: String,
      default: '#fde8cd'
    },
    // 快速的步数 步为一圈8
    fastSteps: {
      type: String,
      default: '16'
    },
    // 慢速的步数 步为一圈8
    slowSteps: {
      type: String,
      default: '7'
    }
  },
  data() {
    return {
      activeIndex: '',
      totlaSteps: 0,
      FastNums: 0,
      SlowNums: 0,
      time: 200,
      LoopStatus: true,
      noClick: true
    };
  },
  computed: {
    actionStyle() {
      const style = {
        backgroundColor: this.color,
        border: `${this.activeBrColor} solid 3px`,
        color: this.bgColor
      };
      return style;
    },
    activeStyle() {
      const style = {
        backgroundColor: this.activeBgColor,
        border: `${this.activeBrColor} solid 3px`,
        color: this.activeColor
      };
      return style;
    },
    Style() {
      const style = {
        border: `${this.activeBrColor} solid 3px`,
        color: this.color,
        backgroundColor: this.bgColor
      };
      return style;
    }
  },
  methods: {
    onClick(index) {
      if(index == 4) {
        this.begin(index)
        return
      };
      
      this.$emit('preview', index );
    },
    // 中奖后的逻辑
    end(prizesIndex) {
      this.$emit('end', prizesIndex);
    },
    reset() {
      this.noClick = true;
      this.FastNums = 0;
      this.SlowNums = 0;
      this.totlaSteps = 0;
      this.time = 200;
      this.LoopStatus = false;
    },
    // 开始抽奖
    begin() {
      if (this.prizesIndex > 8 || this.prizesIndex < 0 || this.prizesIndex == 4) {
        uni.showToast({
          title: '奖品配置错误',
          icon: 'none'
        });
        return;
      }
      if (!this.noClick) {
        uni.showToast({
          title: '请勿频繁点击',
          icon: 'none'
        });
        return;
      }
      // 重置
      this.reset();
      this.noClick = false;
      this.LoopStatus = true;
      this.loop();
    },
    // 抽奖过程 控制
    loop() {
      let prizesIndex = this.prizesIndex;
      const fastSteps = parseInt(this.fastSteps);
      const slowSteps = parseInt(this.slowSteps);

      if (!this.activeIndex || this.activeIndex < 9) {
        // 转圈的顺序 从0开始 回到0为一圈
        const indexMap = { '': 0, 0: 1, 1: 2, 2: 5, 5: 8, 8: 7, 7: 6, 6: 3, 3: 0 };
        this.activeIndex = indexMap[this.activeIndex];

        this.totlaSteps++;

        if (this.totlaSteps <= fastSteps) {
          // 加速阶段  必走fastSteps次
          this.time -= 20;
        } else if (this.totlaSteps > fastSteps && this.totlaSteps <= fastSteps + slowSteps) {
          // 减速阶段  必走slowSteps次
          this.time += 40;
        } else if (this.activeIndex == prizesIndex) {
          // 最后一圈中奖
          this.noClick = true;
          this.LoopStatus = false;
          this.end(prizesIndex);
          return;
        }
        
        // 最小时间间隔
        if (this.time < 40) {
          this.time = 40;
        }
        
        setTimeout(() => {
          this.loop();
        }, this.time);
      }
    }
  }
};
</script>

<style scoped>
/* 抽奖 */
.f-c {
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: center;
}
.wrap {
  flex-wrap: wrap;
}
.w-600 {
  width: 600rpx;
}
.wh-150 {
  width: 150rpx;
  height: 150rpx;
}
.wh-60 {
  width: 60rpx;
  height: 60rpx;
}
.f-col-c {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.m-10 {
  margin: 10rpx;
}
.mt-5 {
  margin-top: 5rpx
}
.br-40 {
  border-radius: 40rpx;
}
.fs-40 {
  font-size: 40rpx;
}
.fs-21 {
  font-size: 21rpx;
}
.fw-b {
  font-weight: 700;
} 
.ls-10{
  letter-spacing: 10rpx
}
</style>
