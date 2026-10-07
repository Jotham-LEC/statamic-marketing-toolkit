<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    text: { type: String, default: '' },
    limits: { type: Array, required: true },
});

const length = computed(() => [...props.text].length);
const state = computed(() => {
    const [min, max] = props.limits;

    if (length.value === 0 || length.value < min) return 'short';
    if (length.value > max) return 'long';

    return 'good';
});
const hint = computed(() => {
    const [min, max] = props.limits;

    return { short: __('marketing-toolkit::cp.preview.under', { min }), long: __('marketing-toolkit::cp.preview.over', { max }), good: `${min}–${max}` }[state.value];
});
const tooltip = computed(() => __n('marketing-toolkit::cp.preview.count', length.value, { label: props.label, count: length.value, min: props.limits[0], max: props.limits[1] }));
</script>

<template>
    <span
        :title="tooltip"
        :class="{
            'text-amber-700 dark:text-amber-400': state === 'short',
            'text-(--theme-color-danger)': state === 'long',
            'text-(--theme-color-success)': state === 'good',
        }"
    >
        {{ label }} {{ length }} ({{ hint }})
    </span>
</template>
