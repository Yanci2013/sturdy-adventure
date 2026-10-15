<template>
	<view class="sound-waves">
		<view class="wave"></view>
		<view class="wave"></view>
		<view class="wave"></view>
		<view class="wave"></view>
		<view class="wave"></view>
		<view class="wave"></view>
		<view class="wave"></view>
	</view>
</template>

<script>
</script>

<style lang="scss" scoped>
.sound-waves {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
	height: 60px;
}

.wave {
	width: 8px;
	height: 30px;
	background: linear-gradient(to top, #ff5e62, #ff9966);
	border-radius: 4px;
	animation: wave-animation 1.5s infinite ease-in-out;
}

.wave:nth-child(1) {
	animation-delay: 0.1s;
}

.wave:nth-child(2) {
	animation-delay: 0.2s;
}

.wave:nth-child(3) {
	animation-delay: 0.3s;
}

.wave:nth-child(4) {
	animation-delay: 0.4s;
}

.wave:nth-child(5) {
	animation-delay: 0.5s;
}

.wave:nth-child(6) {
	animation-delay: 0.6s;
}

.wave:nth-child(7) {
	animation-delay: 0.7s;
}

@keyframes wave-animation {

	0%,
	100% {
		height: 20px;
	}

	50% {
		height: 60px;
	}
}
</style>