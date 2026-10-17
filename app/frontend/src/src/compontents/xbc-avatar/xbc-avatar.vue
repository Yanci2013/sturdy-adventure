<template>
  <image @error="loadError" class="avatar pointer" :src="imgSrc" :style="style" lazy />
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import DefaultAvatar from '@/static/img/avatar.png'
import DefaultGroupAvatar from '@/static/img/group_avatar.png'

const props = defineProps({
  src: { type: String, default: DefaultAvatar },
  size: { type: String, default: '44px' },
  radius: { type: String, default: '4px' },
  group: { type: Boolean, default: false },
});
const style = computed(() => ({
  width: props.size,
  height: props.size,
  borderRadius: props.radius,
}));
const imgSrc = ref(props.src)

function loadError(e) {
  imgSrc.value = DefaultAvatar
}

watch(() => props.src, (newVal) => {
  imgSrc.value = newVal || (props.group ? DefaultGroupAvatar : DefaultAvatar)
}, { immediate: true })
</script>

<style lang="scss" scoped>

</style>