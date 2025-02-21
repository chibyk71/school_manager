<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    value: string | number,
    severity: string,
}>();

const rotation = computed(() => {
    const value = Number(props.value);
    return {
        right: value > 50 ? 180 : (value / 50) * 180,
        left: value > 50 ? ((value - 50) / 50) * 180 : 0,
    };
});
</script>

<template>
    <div class="circle-progress mb-3" :data-value="props.value">
        <span class="progress-left">
            <span class="`progress-bar border-${ props.severity }`" :style="{ transform: `rotate(${rotation.left}deg)` }"></span>
        </span>
        <span class="progress-right">
            <span :class="`progress-bar border-${ props.severity }`" :style="{ transform: `rotate(${rotation.right}deg)` }"></span>
        </span>
        <div class="progress-value text-sm/none text-color">{{ props.value }}%</div>
    </div>
</template>

<style lang="postcss">
.circle-progress {
    width: 38px;
    float: left;
    line-height: 38px;
    box-shadow: none;
    position: relative;
    height: 38px !important;
    background: none;
}

.circle-progress > span {
    width: 50%;
    height: 100%;
    position: absolute;
    top: 0px;
    z-index: 1;
    overflow: hidden;
}
.circle-progress .progress-left {
    left: 0px;
}
.circle-progress .progress-right {
    right: 0px;
}
.circle-progress .progress-left .progress-bar {
    left: 100%;
    border-top-right-radius: 80px;
    border-bottom-right-radius: 80px;
    transform-origin: left center;
    border-left: 0px;
}
.circle-progress .progress-bar {
    width: 100%;
    height: 100%;
    position: absolute;
    top: 0px;
    background: none;
    border-width: 4px;
    border-style: solid;
}
.circle-progress .progress-right .progress-bar {
    left: -100%;
    border-top-left-radius: 80px;
    border-bottom-left-radius: 80px;
    transform-origin: right center;
    border-right: 0px;
    animation: 1.8s linear 0s 1 normal forwards running loading-1;
}
.circle-progress .progress-value {
    position: absolute;
    top: 5%;
    left: 5%;
    width: 90%;
    height: 90%;
    font-size: 12px;
    color: rgb(81, 91, 115);
    line-height: 38px;
    text-align: center;
}
.circle-progress::after {
    content: "";
    width: 100%;
    height: 100%;
    position: absolute;
    top: 0px;
    left: 0px;
    border-radius: 50%;
    border-width: 4px;
    border-style: solid;
    border-color: rgb(233, 237, 244);
    border-image: initial;
}
</style>