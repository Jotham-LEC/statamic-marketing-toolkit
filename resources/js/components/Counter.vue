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

    return { short: `under ${min}`, long: `over ${max}`, good: `${min}–${max}` }[state.value];
});
</script>

<template>
    <span
        :title="`${label}: ${length} characters, aim for ${limits[0]}–${limits[1]}`"
        :class="{
            'text-amber-700 dark:text-amber-400': state === 'short',
            'text-red-600 dark:text-red-400': state === 'long',
            'text-green-700 dark:text-green-400': state === 'good',
        }"
    >
        {{ label }} {{ length }} ({{ hint }})
    </span>
</template>
