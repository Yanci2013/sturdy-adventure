<template>
	<view class="page">
		<!-- 顶部背景图 -->
		<image class="top-bg" src="/static/images/header_background_panda.png" mode="aspectFill"></image>

		<!-- 导航栏 -->
		<view class="navbar">
			<view class="navbar-capsule">
				<view class="navbar-btn" @click="goBack">
					<up-icon name="arrow-left" size="34rpx" color="#606266"></up-icon>
				</view>
				<view class="navbar-divider"></view>
				<view class="navbar-btn" @click="goHome">
					<up-icon name="home" size="36rpx" color="#606266"></up-icon>
				</view>
			</view>
			<text class="navbar-title">订单详情</text>
			<view class="navbar-placeholder"></view>
		</view>

		<!-- 内容区 -->
		<scroll-view scroll-y class="content">
			<view class="content-inner">
				<!-- 状态区域 -->
				<view class="status-section" @click="refunClick">
					<view class="status-img-wrap">
						<image class="status-img" :src="statusIcon" mode="aspectFit"></image>
					</view>
					<view class="status-text-wrap">
						<text class="status-text">{{ infoContentTitle() }}</text>
						<text class="status-tips">{{ infoContentSubtitle() }}</text>
					</view>
				</view>

				<view class="cards-wrap">
					<!-- 打手信息卡片 -->
					<view class="card" v-if="dataInfo.thug">
						<text class="card-title">打手信息</text>
						<view class="provider-row">
							<view class="provider-avatar">
								<image :src="dataInfo.thug.avatar || '/static/images/icon.png'" mode="aspectFill"></image>
							</view>
							<view class="provider-info">
								<view class="provider-name-row">
									<text class="provider-name">{{ dataInfo.thug.name }}</text>
								</view>
								<view class="provider-time-wrap">
									<text class="provider-time-line">接单时间: {{ dataInfo.connecttime || '无' }}</text>
									<text class="provider-time-line">完成时间: {{ dataInfo.completetime || '未完成' }}</text>
								</view>
							</view>
						</view>
						<!-- 对话按钮 -->
						<view class="chat-btn-row">
							<view class="chat-btn" @click="openChat">
								<up-icon name="chat" size="16" color="#333"></up-icon>
								<text class="chat-btn-text">对话聊天</text>
							</view>
						</view>

						<!-- 服务凭证 -->
						<view class="service-images" v-if="dataInfo.image && dataInfo.image.length">
							<image v-for="(url, idx) in dataInfo.image" :key="idx"
								class="service-img" :src="url" mode="aspectFill"
								@click="previewImage(dataInfo.image, idx)"></image>
						</view>

						<!-- 打手备注 -->
						<view v-if="dataInfo.thug_remarks && type=='打手'" class="announce-text">
							{{ dataInfo.thug_remarks }}
						</view>

						<!-- 评分 -->
						<view class="rating-row" v-if="dataInfo.status >= 5" :class="{ 'rating-row-clickable': !dataInfo.is_rated && type=='用户' }" @click="type=='用户' && openRatingPopup()">
							<text class="rating-label">服务评分</text>
							<view class="stars">
								<up-rate v-model="rateVal" :count="5" :size="25" :readonly="true" gutter="2"></up-rate>
							</view>
						</view>
					</view>

					<!-- 商品信息卡片 -->
					<view class="card product-card">
						<text class="card-title">商品信息</text>
						<view class="product-row">
							<image class="product-thumb" :src="dataInfo.logo || '/static/images/icon.png'" mode="aspectFill"></image>
							<view class="product-info">
								<text class="product-name">{{ dataInfo.title }}</text>
								<view class="product-price">
									<view class="card-actual">
										<text class="price-symbol">¥</text>
										<text class="price-num">{{ dataInfo.price }}</text>
									</view>
									<text class="price-qty">x{{ dataInfo.number || 1 }}</text>
								</view>
								<text class="product-desc">{{ dataInfo.synopsis || '' }}</text>
							</view>
						</view>

						<!-- 奖品信息 -->
						<view class="product-row product-row-extra" v-if="dataInfo.jackpot">
							<image class="product-thumb" :src="dataInfo.jackpot.image || '/static/images/icon.png'" mode="aspectFill"></image>
							<view class="product-info">
								<text class="product-name">{{ dataInfo.jackpot.name }}</text>
								<view class="product-price">
									<text class="price-tag">奖品</text>
									<text class="price-qty">x1</text>
								</view>
							</view>
						</view>

						<!-- 订单信息 -->
						<text class="delivery-section-title">订单信息</text>
						<view class="delivery-list">
							<view class="delivery">
								<text class="label">订单编号</text>
								<text class="value">{{ dataInfo.order_no }}</text>
							</view>
							<view class="delivery">
								<text class="label">游戏ID</text>
								<view class="value-copy-row">
									<text class="value">{{ type=='打手' && dataInfo.status == 1 ? '接单后查看' : (dataInfo.game_id || '-') }}</text>
									<text class="copy-btn" v-if="type!='打手' || dataInfo.status >= 2" @click="copyText(dataInfo.game_id)">复制</text>
								</view>
							</view>
							<view class="delivery">
								<text class="label">游戏昵称</text>
								<view class="value-copy-row">
									<text class="value">{{ type=='打手' && dataInfo.status == 1 ? '接单后查看' : (dataInfo.wz_id || '-') }}</text>
									<text class="copy-btn" v-if="type!='打手' || dataInfo.status >= 2" @click="copyText(dataInfo.wz_id)">复制</text>
								</view>
							</view>
							<view class="delivery">
								<text class="label">游戏区服</text>
								<text class="value">{{ dataInfo.game_qf || '-' }}</text>
							</view>
							<view class="delivery" v-if="dataInfo.helper && type=='打手'">
								<text class="label">协助打手</text>
								<text class="value">{{ dataInfo.helper }}</text>
							</view>
							<view class="delivery">
								<text class="label">下单备注</text>
								<text class="value remark">{{ dataInfo.remarks || '无' }}</text>
							</view>
							<view class="delivery">
								<text class="label">下单时间</text>
								<text class="value">{{ dataInfo.createtime }}</text>
							</view>
							<view class="delivery" v-if="dataInfo.paytime">
								<text class="label">付款时间</text>
								<text class="value">{{ dataInfo.paytime }}</text>
							</view>
							<view class="delivery" v-if="dataInfo.status == 7 && dataInfo.refund_method == 'balance'">
								<text class="label">退款方式</text>
								<text class="value">退至平台余额</text>
							</view>
							<view class="delivery" v-if="dataInfo.status == 7 && dataInfo.refund_time_text">
								<text class="label">退款到账</text>
								<text class="value">{{ dataInfo.refund_time_text }}</text>
							</view>
							<view class="delivery" v-if="dataInfo.connecttime">
								<text class="label">接单时间</text>
								<text class="value">{{ dataInfo.connecttime }}</text>
							</view>
							<view class="delivery" v-if="dataInfo.completetime">
								<text class="label">完成时间</text>
								<text class="value">{{ dataInfo.completetime }}</text>
							</view>
						</view>

						<!-- 实付款 -->
						<view class="pay-section">
							<text class="pay-name">实付款</text>
							<text class="pay-price">¥{{ dataInfo.pay_price }}</text>
						</view>
					</view>

					<!-- 下单说明卡片 -->
					<view class="card" v-if="dataInfo.place_order">
						<text class="card-title">下单说明</text>
						<text class="announce-text">{{ dataInfo.place_order }}</text>
					</view>

					<view class="bottom-spacer"></view>
				</view>
			</view>
		</scroll-view>

		<!-- 底部操作栏 -->
		<view class="footer-bar" v-if="dataInfo.id">
			<view class="footer-left">
				<text class="footer-label">合计:</text>
				<text class="footer-symbol">¥</text>
				<text class="footer-total">{{ dataInfo.pay_price }}</text>
			</view>
			<view class="footer-btns">
				<view class="btn-more" @click="showDrawer=true">更多操作</view>
				<!-- 用户：待抽奖 → 立即抽奖 -->
				<view class="btn-buy" v-if="isWaitingLottery && type=='用户'" @click="openLuckPopup">立即抽奖</view>
				<!-- 用户：待付款 → 立即付款 -->
				<view class="btn-buy" v-else-if="dataInfo.status == 0 && type=='用户'" @click="pay">立即付款</view>
				<!-- 用户：待结单 → 确认结单 -->
				<view class="btn-buy" v-else-if="dataInfo.status == 4 && type=='用户'" @click="finishClick">确认结单</view>
				<!-- 用户：已抽奖有奖品 → 再次购买 -->
				<view class="btn-buy" v-else-if="type=='用户' && dataInfo.award == '1' && dataInfo.Jackpot_id > 0 && dataInfo.status >= 1" @click="buyAgain">再次购买</view>
				<!-- 打手：待接单 → 接单 -->
				<view class="btn-buy" v-else-if="type=='打手' && (dataInfo.status == 1 || dataInfo.status == '1')" @click="acceptOrder">接单</view>
				<!-- 打手：待开始 → 开始服务 -->
				<view class="btn-buy" v-else-if="type=='打手' && dataInfo.status == 2" @click="startClick">开始服务</view>
				<!-- 打手：服务中 → 确认结单 -->
				<view class="btn-buy" v-else-if="type=='打手' && (dataInfo.status == 3 || dataInfo.status == '3')" @click="showUploadPopup">确认结单</view>
			</view>
		</view>

		<!-- 底部操作菜单 -->
		<view class="drawer-mask" :class="{ show: showDrawer }" @click="showDrawer=false"></view>
		<view class="drawer-panel" :class="{ show: showDrawer }">
			<view class="drawer-actions">
				<view class="drawer-item" v-for="(item, idx) in drawerActions" :key="idx" @click="onDrawerAction(item)">
					<view class="drawer-icon-wrap">
						<up-icon :name="item.icon" size="24" :color="item.color || '#333'"></up-icon>
					</view>
					<text class="drawer-label">{{ item.name }}</text>
				</view>
			</view>
			<view class="drawer-cancel" @click="showDrawer=false">取消</view>
		</view>

		<!-- 客服列表弹窗 -->
		<uv-modal ref="customerServiceModal" title="客服列表" :showConfirmButton="true" confirmText="确定" @confirm="closeCustomerService">
			<view style="width:100%;">
				<scroll-view scroll-y style="max-height:60vh;">
					<view v-if="cslist.length">
						<view v-for="(item, index) in cslist" :key="item.id" class="cs-item" @click="goCustomerService(item)">
							<image class="cs-avatar" :src="item.image || '/static/icon/default_avatar.png'" mode="aspectFill"></image>
							<view class="cs-info"><text class="cs-name">{{ item.name }}</text></view>
							<view class="cs-arrow"><up-icon name="arrow-right" size="16" color="#999"></up-icon></view>
						</view>
					</view>
					<uv-empty v-else mode="data" text="暂无客服，请等待平台添加"></uv-empty>
				</scroll-view>
			</view>
		</uv-modal>

		<!-- 评价弹窗 -->
		<up-popup :show="usershow" mode="bottom" closeable @close="usershow=false" round="24">
			<view class="rating-popup-wrap">
				<view class="rating-popup-title">服务评价</view>
				<view class="rating-popup-body">
					<view class="rating-popup-stars">
						<up-rate v-model="rate" :count="5" :size="36" gutter="8"></up-rate>
					</view>
					<text class="rating-popup-text" v-if="rate">{{ rate }}星 — {{ ratingText[rate - 1] }}</text>
					<text class="rating-popup-text placeholder" v-else>请选择评分</text>
					<view class="comment-input-wrap">
						<up-textarea v-model="commentContent" placeholder="分享您的服务体验..." border="none" maxlength="500" count customStyle="background: #f5f5f5; border-radius: 12rpx;"></up-textarea>
					</view>
					<view class="comment-images-wrap">
						<view class="comment-img-item" v-for="(img, idx) in commentImages" :key="idx">
							<image :src="img" mode="aspectFill" class="comment-img"></image>
							<view class="comment-img-del" @tap="removeCommentImage(idx)">×</view>
						</view>
						<view class="comment-img-add" @tap="chooseCommentImage" v-if="commentImages.length < 6">
							<up-icon name="camera" size="24" color="#999"></up-icon>
							<text style="font-size:20rpx;color:#999;margin-top:4rpx;">添加图片</text>
						</view>
					</view>
				</view>
				<view class="rating-popup-footer">
					<view class="rating-popup-btn cancel" @click="usershow=false">取消</view>
					<view class="rating-popup-btn submit" :class="{ disabled: !rate }" @click="sureFinish">确认提交</view>
				</view>
			</view>
		</up-popup>

		<!-- 上传凭证弹窗（打手确认结单） -->
		<up-popup :show="showUpload" mode="center" closeable @close="closeUpload" round="20rpx">
			<view class="upload-popup-wrap">
				<view class="upload-popup-title">上传凭证</view>
				<view class="upload-popup-label">凭证图片</view>
				<view class="upload-images">
					<view class="upload-img-item" v-for="(img, idx) in uploadImages" :key="idx">
						<image :src="img" mode="aspectFill" class="upload-img"></image>
						<view class="upload-img-del" @tap="removeImage(idx)">×</view>
					</view>
					<view class="upload-img-add" @tap="chooseImage" v-if="uploadImages.length < 6">
						<up-icon name="plus" size="24" color="#999"></up-icon>
					</view>
				</view>
				<view class="upload-popup-label">服务备注</view>
				<view class="upload-textarea-wrap">
					<up-textarea v-model="thugRemarks" placeholder="服务备注" border="none" customStyle="background: #f5f5f5;"></up-textarea>
				</view>
				<view class="upload-popup-btn" @tap="submitFinish">确认上传并结单</view>
			</view>
		</up-popup>

		<!-- 转盘抽奖弹窗 -->
		<uv-popup ref="popupLuck" :safeAreaInsetBottom="false" :closeOnClickOverlay="false" mode="center" round="300" bgColor="transparent">
			<view class="dialer-popup">
				<view class="dialer-container">
					<view class="dialer-wheel" :style="{transform: 'rotate(' + wheelAngle + 'deg)', transition: wheelTransition, background: wheelBackground}">
						<view class="dialer-label" v-for="(item, index) in prizesListDisplay" :key="index"
							:style="getLabelStyle(index)">
							<view class="dialer-label-inner" :style="getLabelInnerStyle(index)">
								<image v-if="item.image" class="dialer-label-img" :src="item.image" mode="aspectFill"></image>
								<text class="dialer-label-name">{{item.name}}</text>
							</view>
						</view>
					</view>
					<image class="dialer-pointer" :class="{heart: !isDrawing}" @click="drawClick" src="/static/images/turnable_btn.png" mode="widthFix"></image>
				</view>
				<view class="dialer-close" @click="popupLuck.close()">
					<text class="dialer-close-icon">×</text>
				</view>
			</view>
		</uv-popup>

		<!-- 中奖结果弹窗 -->
		<uv-popup :show="showResult" mode="center" round="20rpx" closeable @close="showResult=false">
			<view class="result-popup">
				<view class="result-title">恭喜中奖</view>
				<view class="result-body">
					<image class="result-img" mode="aspectFill" :src="luckyItem.image"></image>
					<text class="result-name">{{luckyItem.name}}</text>
				</view>
				<view class="result-btn" @click="showResult=false">确定</view>
			</view>
		</uv-popup>
	</view>
</template>

<script setup>
import { computed, ref } from 'vue';
import { onLoad } from '@dcloudio/uni-app';
import { orderMsg, orderRevoke, orderPay, orderStart, orderConfirm, orderConnect, orderFinish, customerServiceList as getCustomerServiceList, jackpotList, orderSmoke } from '../../../request/api';
import { base_url } from '../../../request/env';
import router from '../../../utils/router';
import { openCustomerServiceChat } from '../../../utils/customer-service';
import { test } from '../../../uni_modules/uview-plus';
import payUtil from '../../../utils/pay.js';

const id = ref('');
const dataInfo = ref({});
const complaint = ref({});
const type = uni.getStorageSync('type');
const usershow = ref(false);
const rate = ref(0);
const rateVal = ref(0);
const ratingText = ['非常差', '差', '一般', '满意', '非常满意'];
const showDrawer = ref(false);
const customerServiceModal = ref(null);
const cslist = ref([]);
const showUpload = ref(false);
const uploadImages = ref([]);
const thugRemarks = ref('');
const commentContent = ref('');
const commentImages = ref([]);

// 抽奖相关
const popupLuck = ref(null);
const prizesListDisplay = ref([]);
const wonList = ref([]);
const wheelAngle = ref(0);
const wheelTransition = ref('none');
const showResult = ref(false);
const luckyItem = ref({});
const isDrawing = ref(false);

const canLottery = computed(() => {
	return isWaitingLottery.value;
});

const goLottery = () => {
	openLuckPopup();
};

const wheelBackground = computed(() => {
	const count = prizesListDisplay.value.length;
	if (count === 0) return '#fff';
	const colors = ['#fff3e0', '#ffffff'];
	const stops = [];
	const angle = 360 / count;
	for (let i = 0; i < count; i++) {
		const start = angle * i;
		const end = angle * (i + 1);
		const color = colors[i % 2];
		stops.push(`${color} ${start}deg ${end}deg`);
	}
	return `conic-gradient(${stops.join(', ')})`;
});

const getLabelStyle = (index) => {
	const count = prizesListDisplay.value.length;
	if (count === 0) return {};
	const angle = 360 / count;
	const rotate = angle * index + angle / 2;
	return {
		position: 'absolute',
		left: '50%',
		top: '0',
		width: '0',
		height: '50%',
		transformOrigin: '0 100%',
		transform: `rotate(${rotate}deg)`
	};
};

const getLabelInnerStyle = () => {
	return {
		position: 'absolute',
		top: '40rpx',
		left: '50%',
		transform: 'translateX(-50%)',
		display: 'flex',
		flexDirection: 'column',
		alignItems: 'center',
		width: '120rpx'
	};
};

const openLuckPopup = async () => {
	uni.showLoading({ title: '加载中...', mask: true });
	try {
		const res = await jackpotList({ id: dataInfo.value.id });
		if (res.code == 1) {
			prizesListDisplay.value = res.data || [];
		} else {
			prizesListDisplay.value = [];
		}
	} catch (e) {
		prizesListDisplay.value = [];
	}
	uni.hideLoading();
	if (!prizesListDisplay.value.length) {
		return uni.showToast({ title: '暂无奖品', icon: 'none' });
	}
	wheelAngle.value = 0;
	wheelTransition.value = 'none';
	popupLuck.value.open();
};

const drawClick = async () => {
	if (isDrawing.value) return;
	isDrawing.value = true;
	uni.showLoading({ title: '抽奖中...', mask: true });
	try {
		const res = await orderSmoke({ id: dataInfo.value.id });
		uni.hideLoading();
		if (res.code != 1) {
			isDrawing.value = false;
			uni.showToast({ title: res.msg || '抽奖失败', icon: 'none' });
			return;
		}
		const winId = res.data;
		const count = prizesListDisplay.value.length;
		let targetIndex = prizesListDisplay.value.findIndex(p => p.id == winId);
		if (targetIndex < 0) targetIndex = 0;
		const sectorAngle = 360 / count;
		const targetAngle = 360 - (targetIndex * sectorAngle + sectorAngle / 2);
		const spins = 5;
		const finalAngle = spins * 360 + targetAngle;
		wheelTransition.value = 'transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99)';
		wheelAngle.value = finalAngle;
		setTimeout(() => {
			isDrawing.value = false;
			luckyItem.value = prizesListDisplay.value[targetIndex] || {};
			showResult.value = true;
			dataInfo.value.Jackpot_id = winId;
			getDetail();
		}, 4200);
	} catch (e) {
		uni.hideLoading();
		isDrawing.value = false;
		uni.showToast({ title: '抽奖异常', icon: 'none' });
	}
};

const drawerActions = computed(() => {
	const actions = [];
	const s = dataInfo.value.status;
	// 联系客服 - 始终显示
	actions.push({ name: '联系客服', icon: 'kefu-ermai', color: '#83c0f1', onClick: openCustomerService });
	// 订单投诉
	if (type === '用户' && s >= 1 && (!complaint.value || complaint.value == 0)) {
		actions.push({ name: '订单投诉', icon: 'edit-pen', color: '#ffaa7f', onClick: touClick });
	}
	// 申请退款（服务未完成）
	if (type === '用户' && s >= 1 && s <= 4 && (!complaint.value || complaint.value == 0)) {
		actions.push({ name: '申请退款', icon: 'reload', color: '#e00129', onClick: touClick });
	}
	// 撤销投诉/取消退款
	if (complaint.value && complaint.value.status == 0) {
		actions.push({
			name: complaint.value.type == '2' ? '取消退款' : '撤销投诉',
			icon: 'reload', color: '#e00129', onClick: orderRevokeClick
		});
	}
	// 查看售后详情
	if (complaint.value && complaint.value.id) {
		actions.push({ name: '查看售后', icon: 'file-text', color: '#83c0f1', onClick: refunClick });
	}
	return actions;
});

const onDrawerAction = (item) => {
	showDrawer.value = false;
	if (item.onClick) item.onClick();
};

const statusIcon = computed(() => {
	return [5, 7].includes(Number(dataInfo.value.status)) ? '/static/icon/orderDetail/status-finish.png' : '/static/icon/orderDetail/status-wait.png';
});

const fromPay = ref(false);

onLoad((prop) => {
	id.value = prop.id;
	if (prop.from === 'pay') fromPay.value = true;
	getDetail();
});

const goBack = () => {
	const p = getCurrentPages();
	if (p.length > 1) uni.navigateBack({ delta: 1 });
	else uni.reLaunch({ url: '/pages/mine/index' });
};
const goHome = () => uni.reLaunch({ url: '/pages/index/index' });

const previewImage = (urls, idx) => {
	if (urls && urls.length) {
		uni.previewImage({ urls, current: urls[idx] || urls[0] });
	}
};

const openRatingPopup = () => {
	if (dataInfo.value.is_rated) {
		return uni.showToast({ title: '已经评论了', icon: 'none' });
	}
	rate.value = 0;
	commentContent.value = '';
	commentImages.value = [];
	usershow.value = true;
};

const orderRevokeClick = () => {
	orderRevoke({ id: dataInfo.value.complaint.id }).then(res => {
		uni.showToast({ title: res.msg, icon: 'none' });
		getDetail();
	});
};

const refunClick = () => {
	if (type == '打手') return;
	if (test.isEmpty(complaint.value)) return;
	router.navigateTo({ url: 'pagesA/mine/order/refunInfo', data: { id: dataInfo.value.complaint.id } });
};

const touClick = () => {
	router.navigateTo({ url: 'pagesA/mine/order/tou', data: { id: dataInfo.value.id, no: dataInfo.value.order_no } });
};

const finishClick = () => { usershow.value = true; };

const sureFinish = () => {
	if (rate.value === 0) {
		return uni.showToast({ title: '请先选择评分', icon: 'none' });
	}
	usershow.value = false;
	setTimeout(() => {
		uni.showModal({
			title: '提示',
			content: '您给本次服务' + rate.value + '分，是否确认提交？',
			showCancel: true,
			success: (res) => {
				if (res.confirm) {
					orderConfirm({
						id: dataInfo.value.id,
						grade: rate.value,
						comment_content: commentContent.value,
						comment_images: commentImages.value.join(',')
					}).then(() => {
						getDetail();
					});
				} else {
					usershow.value = true;
				}
			}
		});
	}, 300);
};

const removeCommentImage = (idx) => {
	commentImages.value.splice(idx, 1);
};

const chooseCommentImage = () => {
	uni.chooseImage({
		count: 6 - commentImages.value.length,
		sizeType: ['compressed'],
		sourceType: ['album', 'camera'],
		success: (res) => {
			res.tempFilePaths.forEach((filePath) => {
				uni.uploadFile({
					url: base_url + '/common/upload',
					filePath,
					name: 'file',
					header: { token: uni.getStorageSync('token') || '' },
					success: (uploadRes) => {
						try {
							const data = JSON.parse(uploadRes.data);
							if (data.data && data.data.url) {
								commentImages.value.push(data.data.fullurl);
							}
						} catch (e) {
							uni.showToast({ title: '上传失败', icon: 'none' });
						}
					},
					fail: () => {
						uni.showToast({ title: '上传失败', icon: 'none' });
					}
				});
			});
		}
	});
};

const acceptOrder = () => {
	uni.showModal({
		title: '提示',
		content: '是否确认接单？',
		showCancel: true,
		confirmText: '确定',
		cancelText: '取消',
		success: (res) => {
			if (res.confirm) {
				orderConnect({ id: dataInfo.value.id }).then(result => {
					if (result.code == 1) {
						uni.showToast({ title: '接单成功', icon: 'success' })
						setTimeout(() => { getDetail() }, 1500)
					}
				})
			}
		}
	})
};

const startClick = () => {
	uni.showModal({
		title: '提示', content: '是否开始服务该订单', showCancel: true,
		success: res => {
			if (res.confirm) {
				orderStart({ id: dataInfo.value.id }).then(() => getDetail());
			}
		}
	});
};

const pay = async () => {
	uni.showLoading({ title: '正在发起支付...', mask: true });
	try {
		const res = await orderPay({ id: dataInfo.value.id });
		uni.hideLoading();
		payUtil.redirectPay(res.data, (err) => {
			uni.showToast({ title: err || '无法打开易支付', icon: 'none' });
		});
	} catch (e) { uni.hideLoading(); }
};

const isWaitingLottery = computed(() => {
	return dataInfo.value.award == '1' && dataInfo.value.Jackpot_id == 0 && dataInfo.value.status >= 1 && dataInfo.value.status <= 5;
});

const infoContentTitle = () => {
	const s = dataInfo.value.status;
	if (s == 0) return '待付款';
	if (isWaitingLottery.value) return '待抽奖';
	if (s == 1) return '待接单';
	if (s == 2) return '待开始';
	if (s == 3) return '服务中';
	if (s == 4) return '待结单';
	if (s == 5) return type == '打手' ? '已完成评价' : test.isEmpty(complaint.value) ? '已完成评价' : '已完成评价';
	if (s == 6) return '售后中';
	if (s == 7) return dataInfo.value.refund_method == 'balance' ? '退款已退至余额' : '退款已完成';
	if (s == 8) return '已取消';
	return '';
};

const infoContentSubtitle = () => {
	const s = dataInfo.value.status;
	if (s == 0) return '订单需要付款后才能提供服务哦';
	if (isWaitingLottery.value) return '订单需要抽奖完成后才能接单哦\n(剩余抽奖次数: 1)';
	if (s == 1) return '耐心等，等待打手接单';
	if (s == 2) return type == '用户' ? '打手已接单，等待开始' : '等待开始';
	if (s == 3) return '正在服务中请等待';
	if (s == 4) return type == '用户' ? '服务已完成订单请确认，请点击确认结单' : '等待用户确认';
	if (s == 5) return '该订单已完成';
	if (s == 6) return '请耐心等待客服为您处理';
	if (s == 7) return dataInfo.value.refund_method == 'balance'
		? `¥${dataInfo.value.refund_amount || dataInfo.value.pay_price || '0.00'} 已退回您的平台余额`
		: '该订单退款已处理完成';
	if (s == 8) return '该订单已取消';
	return '';
};

const openCustomerService = async () => {
	customerServiceModal.value.open();
	try {
		uni.showLoading({ mask: true, title: '加载中' });
		const res = await getCustomerServiceList();
		cslist.value = res.data || [];
	} catch (e) {
		cslist.value = [];
	} finally {
		uni.hideLoading();
	}
};

const closeCustomerService = () => {
	customerServiceModal.value.close();
};

const goCustomerService = (item) => {
	openCustomerServiceChat(item)
};

const getDetail = () => {
	orderMsg({ id: id.value }).then(res => {
		console.log('orderDetail type:', type, 'status:', res.data.status, typeof res.data.status)
		dataInfo.value = res.data;
		complaint.value = res.data.complaint;
		rateVal.value = res.data.grade || 0;
		// 支付后首次进入自动弹出抽奖
		if (fromPay.value && res.data.award == '1' && res.data.Jackpot_id == 0) {
			fromPay.value = false;
			setTimeout(() => { openLuckPopup(); }, 500);
		}
	});
};

const openChat = () => {
	if (!dataInfo.value.thug) {
		return uni.showToast({ title: '暂无打手信息', icon: 'none' });
	}
	const thugInfo = dataInfo.value.thug;
	// 判断是否是自己跟自己：下单用户ID == 打手关联用户ID
	const orderUserId = String(dataInfo.value.user_id || '');
	const thugUserId = String(dataInfo.value.thug_user_id || thugInfo.user_id || '');
	if (orderUserId && thugUserId && orderUserId === thugUserId) {
		return uni.showToast({ title: '无法与自己对话', icon: 'none' });
	}
	if (type === '打手') {
		if (!orderUserId) {
			return uni.showToast({ title: '无法获取用户信息', icon: 'none' });
		}
		const targetNick = dataInfo.value.nickname || '用户';
		const targetAva = dataInfo.value.user_avatar || '';
		// 先设置 currentConversation
		uni.setStorageSync('currentConversation', {
			targetId: orderUserId,
			type: 'single',
			conversationId: orderUserId,
			profile: {
				name: targetNick,
				avatar: targetAva,
				userId: orderUserId
			}
		});
		uni.navigateTo({
			url: `/pagesA/im/chat?target_user_id=${orderUserId}&target_name=${encodeURIComponent(targetNick)}&target_avatar=${encodeURIComponent(targetAva)}`
		});
	} else {
		if (!thugUserId) {
			return uni.showToast({ title: '无法获取打手信息', icon: 'none' });
		}
		// 先设置 currentConversation
		uni.setStorageSync('currentConversation', {
			targetId: thugUserId,
			type: 'single',
			conversationId: thugUserId,
			profile: {
				name: thugInfo.name || '打手',
				avatar: thugInfo.avatar || '',
				userId: thugUserId
			}
		});
		uni.navigateTo({
			url: `/pagesA/im/chat?target_user_id=${thugUserId}&target_name=${encodeURIComponent(thugInfo.name || '打手')}&target_avatar=${encodeURIComponent(thugInfo.avatar || '')}`
		});
	}
};

const copyText = (text) => {
	if (!text) return uni.showToast({ title: '无内容可复制', icon: 'none' });
	uni.setClipboardData({
		data: String(text),
		success: () => uni.showToast({ title: '复制成功', icon: 'success' })
	});
};

const buyAgain = () => {
	uni.navigateTo({ url: '/pagesA/index/detail?id=' + dataInfo.value.goods_id });
};

// ---- 打手上传凭证结单 ----
const showUploadPopup = () => {
	uploadImages.value = [];
	thugRemarks.value = '';
	showUpload.value = true;
};
const closeUpload = () => {
	showUpload.value = false;
};
const removeImage = (idx) => {
	uploadImages.value.splice(idx, 1);
};
const chooseImage = () => {
	uni.chooseImage({
		count: 6 - uploadImages.value.length,
		sizeType: ['compressed'],
		sourceType: ['album', 'camera'],
		success: (res) => {
			res.tempFilePaths.forEach((filePath) => {
				uni.uploadFile({
					url: base_url + '/common/upload',
					filePath,
					name: 'file',
					header: { token: uni.getStorageSync('token') || '' },
					success: (uploadRes) => {
						try {
							const data = JSON.parse(uploadRes.data);
							if (data.data && data.data.url) {
								uploadImages.value.push(data.data.fullurl);
							}
						} catch (e) {
							uni.showToast({ title: '上传失败', icon: 'none' });
						}
					},
					fail: () => {
						uni.showToast({ title: '上传失败', icon: 'none' });
					}
				});
			});
		}
	});
};
const submitFinish = () => {
	if (!uploadImages.value.length) {
		return uni.showToast({ title: '请上传服务凭证', icon: 'none' });
	}
	orderFinish({
		id: dataInfo.value.id,
		image: uploadImages.value.join(','),
		thug_remarks: thugRemarks.value
	}).then(res => {
		if (res.code == 1) {
			uni.showToast({ title: res.msg || '提交成功', icon: 'none' });
			showUpload.value = false;
			getDetail();
		} else {
			uni.showToast({ title: res.msg || '提交失败', icon: 'none' });
		}
	});
};
</script>

<style scoped>
/* 页面 */
.page {
	min-height: 100vh;
	background: #f5f6f9;
	display: flex;
	flex-direction: column;
	padding-bottom: calc(140rpx + env(safe-area-inset-bottom));
}
.top-bg {
	position: absolute;
	top: 0; left: 0;
	width: 100%;
	height: 400rpx;
	z-index: 0;
	pointer-events: none;
}

/* 导航栏 */
.navbar {
	position: relative;
	z-index: 10;
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 24rpx 24rpx 0;
	padding-top: calc(var(--status-bar-height, 0px) + 16rpx);
	background: #fff;
}
.navbar-capsule {
	display: flex;
	align-items: center;
	border: 1rpx solid #dadbde;
	border-radius: 100px;
	padding: 8rpx 16rpx;
	backdrop-filter: blur(10px);
	-webkit-backdrop-filter: blur(10px);
	opacity: 0.95;
	background: rgba(255,255,255,0.3);
}
.navbar-btn { padding: 4rpx 8rpx; }
.navbar-divider {
	width: 1rpx; height: 32rpx;
	background: #dadbde;
	margin: 0 8rpx;
}
.navbar-title { font-size: 34rpx; font-weight: 600; color: #333; }
.navbar-placeholder { width: 120rpx; }

/* 内容区 */
.content { flex: 1; position: relative; z-index: 1; }
.content-inner { padding: 32rpx 24rpx 0; box-sizing: border-box; }

/* 状态区域 */
.status-section {
	padding: 24rpx 0 40rpx 20rpx;
	margin-bottom: 0;
	position: relative;
	min-height: 160rpx;
	overflow: visible;
}
.status-img-wrap {
	position: absolute;
	top: 50%;
	right: 0;
	transform: translateY(-50%);
	width: 340rpx;
	height: 220rpx;
	z-index: 0;
	pointer-events: none;
}
.status-img { width: 340rpx; height: 220rpx; display: block; }
.status-text-wrap {
	position: relative;
	z-index: 1;
	display: flex;
	flex-direction: column;
	gap: 12rpx;
	max-width: calc(100% - 360rpx);
}
.status-text { font-size: 40rpx; font-weight: 700; color: #000; }
.status-tips { font-size: 24rpx; color: #666; line-height: 1.5; }

/* 卡片区域 */
.cards-wrap { position: relative; z-index: 2; }
.card {
	width: 100%;
	box-sizing: border-box;
	background: rgba(255,255,255,0.55);
	backdrop-filter: blur(24rpx);
	-webkit-backdrop-filter: blur(24rpx);
	border: 1rpx solid rgba(255,255,255,0.7);
	border-radius: 24rpx;
	margin: 0 0 24rpx;
	padding: 32rpx;
	box-shadow: 0 2rpx 20rpx rgba(0,0,0,0.04), inset 0 1rpx rgba(255,255,255,0.9);
}
.card-title {
	display: block;
	font-size: 32rpx;
	font-weight: 700;
	color: #000;
	margin-bottom: 28rpx;
	padding-bottom: 24rpx;
	border-bottom: 1rpx solid rgba(0,0,0,0.08);
}

/* 打手信息 */
.provider-row { display: flex; gap: 20rpx; margin-bottom: 24rpx; padding-bottom: 24rpx; border-bottom: 1rpx solid rgba(0,0,0,0.08); }
.provider-avatar { width: 128rpx; height: 128rpx; border-radius: 50%; background: #f0f0f0; flex-shrink: 0; overflow: hidden; }
.provider-avatar image { width: 100%; height: 100%; }
.provider-info { flex: 1; min-width: 0; }
.provider-name-row { display: flex; align-items: center; gap: 12rpx; margin-bottom: 8rpx; }
.provider-name { font-size: 28rpx; font-weight: 600; color: #090b08; }
.provider-time-wrap { display: flex; flex-direction: column; gap: 4rpx; margin-bottom: 16rpx; }
.provider-time-line { font-size: 22rpx; color: #666; display: block; }

/* 凭证图片 */
.service-images { display: flex; flex-wrap: wrap; gap: 16rpx; margin-top: 12rpx; margin-bottom: 20rpx; }
.service-img { width: 160rpx; height: 160rpx; border-radius: 12rpx; background: #f0f0f0; flex-shrink: 0; }

/* 评分 */
.rating-row { display: flex; align-items: center; justify-content: space-between; gap: 16rpx; margin-bottom: 16rpx; }
.rating-label { font-size: 26rpx; color: #666; }
.rating-row-clickable { cursor: pointer; }
.stars { display: flex; gap: 8rpx; }
.announce-text { font-size: 24rpx; color: #090b08; line-height: 1.5; display: block; margin-bottom: 12rpx; }

/* 对话聊天按钮 */
.chat-btn-row { padding-top: 20rpx; }
.chat-btn { display: flex; align-items: center; justify-content: center; gap: 8rpx; height: 64rpx; background: #f0f0f0; border-radius: 16rpx; }
.chat-btn-text { font-size: 26rpx; color: #333; }

/* 商品信息 */
.product-row { display: flex; gap: 24rpx; margin-bottom: 24rpx; }
.product-row-extra { margin-top: 8rpx; padding-top: 20rpx; border-top: 1rpx solid rgba(0,0,0,0.06); }
.product-thumb { width: 160rpx; height: 160rpx; border-radius: 12rpx; background: #f0f0f0; flex-shrink: 0; }
.product-info { flex: 1; min-width: 0; }
.product-name { display: -webkit-box; font-size: 30rpx; font-weight: 600; color: #000; overflow: hidden; text-overflow: ellipsis; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.product-price { display: flex; align-items: center; justify-content: space-between; padding: 16rpx 0 12rpx; margin-bottom: 12rpx; border-bottom: 1rpx solid rgba(0,0,0,0.08); }
.card-actual { display: flex; align-items: center; }
.price-symbol { font-size: 24rpx; color: #da4a44; font-weight: 600; }
.price-num { font-size: 32rpx; color: #da4a44; font-weight: 600; }
.price-qty { font-size: 24rpx; color: #999; margin-left: 8rpx; }
.price-tag { font-size: 24rpx; color: #666; margin-right: 8rpx; }
.product-desc { font-size: 24rpx; color: #8f8f8f; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

/* 订单信息列表 */
.delivery-section-title { display: block; font-size: 32rpx; font-weight: 700; color: #000; margin: 24rpx 0 20rpx; padding-bottom: 24rpx; border-bottom: 1rpx solid rgba(0,0,0,0.08); }
.delivery-list { display: flex; flex-direction: column; gap: 16rpx; }
.delivery { display: flex; align-items: flex-start; justify-content: space-between; gap: 24rpx; font-size: 26rpx; }
.delivery .label { flex-shrink: 0; width: 160rpx; color: #999; }
.delivery .value { flex: 1; min-width: 0; color: #333; text-align: right; word-break: break-all; }
.delivery .value.remark { white-space: normal; }
.value-copy-row { flex: 1; min-width: 0; display: flex; align-items: center; justify-content: flex-end; gap: 12rpx; }
.value-copy-row .value { word-break: break-all; }
.copy-btn { font-size: 22rpx; color: #faad14; padding: 4rpx 16rpx; border: 1rpx solid #faad14; border-radius: 8rpx; flex-shrink: 0; }

/* 实付款 */
.pay-section { display: flex; align-items: flex-end; justify-content: space-between; margin-top: 20rpx; margin-bottom: 16rpx; }
.pay-name { font-size: 28rpx; color: #343434; }
.pay-price { font-size: 28rpx; font-weight: 600; color: #da4a44; }

/* 底部间距 */
.bottom-spacer { height: 40rpx; }

/* 底部操作栏 */
.footer-bar {
	position: fixed;
	left: 0; right: 0; bottom: 0;
	z-index: 100;
	display: flex;
	flex-direction: row;
	align-items: center;
	justify-content: space-between;
	padding: 20rpx 24rpx;
	padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
	background: #fff;
	box-shadow: 0 -2rpx 12rpx rgba(0,0,0,0.06);
}
.footer-left {
	display: flex;
	flex-direction: row;
	align-items: center;
	gap: 4rpx;
}
.footer-label { font-size: 28rpx; color: #333; }
.footer-symbol { font-size: 24rpx; color: #da4a44; font-weight: 600; }
.footer-total { font-size: 36rpx; color: #da4a44; font-weight: 700; }
.footer-btns {
	display: flex;
	flex-direction: row;
	align-items: center;
	gap: 16rpx;
}
.btn-more {
	height: 72rpx; line-height: 72rpx;
	padding: 0 32rpx;
	font-size: 26rpx; color: #333;
	background: #f0f0f0;
	border-radius: 16rpx;
	text-align: center;
}
.btn-buy {
	height: 72rpx; line-height: 72rpx;
	padding: 0 40rpx;
	font-size: 26rpx; color: #fff;
	background: linear-gradient(135deg, #ffc300, #faad14);
	border-radius: 16rpx;
	text-align: center;
}
.btn-lottery {
	background: linear-gradient(135deg, #ff6b6b, #ee5a24);
}

/* 评价弹窗 */
.rating-popup-wrap {
	padding: 40rpx 32rpx calc(40rpx + env(safe-area-inset-bottom));
}
.rating-popup-title {
	font-size: 32rpx; font-weight: 700; color: #000;
	text-align: center; margin-bottom: 40rpx;
}
.rating-popup-body {
	display: flex; flex-direction: column; align-items: center; gap: 20rpx;
	padding: 32rpx 0;
}
.rating-popup-stars { display: flex; align-items: center; }
.rating-popup-text { font-size: 26rpx; color: #333; }
.rating-popup-text.placeholder { color: #999; }
.rating-popup-footer {
	display: flex; gap: 24rpx; margin-top: 32rpx;
}
.rating-popup-btn {
	flex: 1; height: 80rpx; line-height: 80rpx;
	text-align: center; font-size: 28rpx;
	border-radius: 16rpx;
}
.rating-popup-btn.cancel { background: #f0f0f0; color: #333; }
.rating-popup-btn.submit { background: linear-gradient(135deg, #ffc300, #faad14); color: #fff; }
.rating-popup-btn.disabled { opacity: 0.5; }

/* 底部抽屉菜单 */
.drawer-mask {
	position: fixed; top: 0; left: 0; right: 0; bottom: 0;
	background: rgba(0,0,0,0.4); z-index: 200;
	opacity: 0; pointer-events: none;
	transition: opacity 0.3s;
}
.drawer-mask.show { opacity: 1; pointer-events: auto; }
.drawer-panel {
	position: fixed; left: 0; right: 0; bottom: 0;
	z-index: 201; background: #fff;
	border-radius: 24rpx 24rpx 0 0;
	padding: 32rpx 24rpx calc(32rpx + env(safe-area-inset-bottom));
	transform: translateY(100%);
	transition: transform 0.3s;
}
.drawer-panel.show { transform: translateY(0); }
.drawer-actions {
	display: flex; flex-wrap: wrap; gap: 32rpx;
	padding: 16rpx 0 32rpx;
}
.drawer-item {
	display: flex; flex-direction: column; align-items: center; gap: 12rpx;
	width: calc(25% - 24rpx);
}
.drawer-icon-wrap {
	width: 96rpx; height: 96rpx;
	display: flex; align-items: center; justify-content: center;
	background: #f5f5f5; border-radius: 24rpx;
}
.drawer-label { font-size: 24rpx; color: #333; }
.drawer-cancel {
	height: 88rpx; line-height: 88rpx;
	text-align: center; font-size: 28rpx; color: #999;
	border-top: 1rpx solid rgba(0,0,0,0.08);
}

/* 客服列表 */
.cs-item {
	display: flex; align-items: center; gap: 20rpx;
	padding: 20rpx 0;
	border-bottom: 1rpx solid rgba(0,0,0,0.06);
}
.cs-avatar {
	width: 80rpx; height: 80rpx;
	border-radius: 50%; flex-shrink: 0;
	background: #f0f0f0;
}
.cs-info { flex: 1; min-width: 0; }
.cs-name { font-size: 28rpx; color: #333; }
.cs-arrow { flex-shrink: 0; }

/* 上传凭证弹窗 */
.upload-popup-wrap {
	width: 600rpx; padding: 40rpx 32rpx;
	background: #fff; border-radius: 24rpx;
}
.upload-popup-title {
	font-size: 32rpx; font-weight: 700; color: #000;
	text-align: center; margin-bottom: 32rpx;
}
.upload-popup-label {
	font-size: 26rpx; color: #333;
	margin-bottom: 16rpx; margin-top: 16rpx;
}
.upload-images {
	display: flex; flex-wrap: wrap; gap: 16rpx;
}
.upload-img-item {
	position: relative;
	width: 160rpx; height: 160rpx;
}
.upload-img {
	width: 100%; height: 100%;
	border-radius: 12rpx; background: #f0f0f0;
}
.upload-img-del {
	position: absolute; top: -12rpx; right: -12rpx;
	width: 40rpx; height: 40rpx;
	background: rgba(0,0,0,0.5); color: #fff;
	border-radius: 50%; font-size: 28rpx;
	display: flex; align-items: center; justify-content: center;
}
.upload-img-add {
	width: 160rpx; height: 160rpx;
	display: flex; align-items: center; justify-content: center;
	background: #f5f5f5; border-radius: 12rpx;
	border: 2rpx dashed #ddd;
}
.upload-textarea-wrap { margin-bottom: 24rpx; }
.upload-popup-btn {
	height: 80rpx; line-height: 80rpx;
	text-align: center; font-size: 28rpx; color: #fff;
	background: linear-gradient(135deg, #ffc300, #faad14);
	border-radius: 16rpx; margin-top: 16rpx;
}

/* 转盘抽奖 */
.dialer-popup {
	display: flex; flex-direction: column; align-items: center;
}
.dialer-container {
	position: relative;
	width: 560rpx; height: 560rpx;
}
.dialer-wheel {
	width: 100%; height: 100%;
	border-radius: 50%;
	border: 8rpx solid #ffc300;
	box-sizing: border-box;
	position: relative;
	overflow: hidden;
}
.dialer-label {
	pointer-events: none;
}
.dialer-label-inner {
	display: flex; flex-direction: column; align-items: center;
}
.dialer-label-img {
	width: 80rpx; height: 80rpx;
	border-radius: 12rpx; margin-bottom: 8rpx;
}
.dialer-label-name {
	font-size: 20rpx; color: #333;
	text-align: center; max-width: 100rpx;
	overflow: hidden; text-overflow: ellipsis;
	white-space: nowrap;
}
.dialer-pointer {
	position: absolute;
	top: 50%; left: 50%;
	transform: translate(-50%, -50%);
	width: 140rpx; z-index: 10;
	cursor: pointer;
}
@keyframes heartBeat {
	0%, 100% { transform: translate(-50%, -50%) scale(1); }
	50% { transform: translate(-50%, -50%) scale(1.1); }
}
.dialer-pointer.heart {
	animation: heartBeat 1.2s ease-in-out infinite;
}
.dialer-close {
	margin-top: 32rpx;
	width: 64rpx; height: 64rpx;
	display: flex; align-items: center; justify-content: center;
	border: 2rpx solid rgba(255,255,255,0.6);
	border-radius: 50%;
}
.dialer-close-icon {
	font-size: 36rpx; color: #fff;
}

/* 中奖结果弹窗 */
.result-popup {
	width: 500rpx; padding: 48rpx 32rpx;
	text-align: center;
}
.result-title {
	font-size: 36rpx; font-weight: 700; color: #000;
	margin-bottom: 32rpx;
}
.result-body {
	display: flex; flex-direction: column; align-items: center; gap: 16rpx;
	margin-bottom: 32rpx;
}
.result-img {
	width: 200rpx; height: 200rpx;
	border-radius: 20rpx; background: #f0f0f0;
}
.result-name {
	font-size: 28rpx; color: #333; font-weight: 500;
}
.result-btn {
	height: 80rpx; line-height: 80rpx;
	text-align: center; font-size: 28rpx; color: #fff;
	background: linear-gradient(135deg, #ffc300, #faad14);
	border-radius: 16rpx;
}

/* 评论输入 */
.comment-input-wrap {
	margin-top: 24rpx;
	width: 100%;
}
.comment-images-wrap {
	display: flex;
	flex-wrap: wrap;
	gap: 16rpx;
	margin-top: 20rpx;
}
.comment-img-item {
	position: relative;
	width: 140rpx;
	height: 140rpx;
}
.comment-img {
	width: 100%;
	height: 100%;
	border-radius: 12rpx;
}
.comment-img-del {
	position: absolute;
	top: -10rpx;
	right: -10rpx;
	width: 36rpx;
	height: 36rpx;
	line-height: 36rpx;
	text-align: center;
	background: rgba(0,0,0,0.6);
	color: #fff;
	border-radius: 50%;
	font-size: 24rpx;
}
.comment-img-add {
	width: 140rpx;
	height: 140rpx;
	border: 2rpx dashed #ddd;
	border-radius: 12rpx;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
}
</style>
