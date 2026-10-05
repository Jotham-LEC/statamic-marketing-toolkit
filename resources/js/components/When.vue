<script setup>
import { computed } from 'vue';

const props = defineProps({ value: { type: String, default: null } });

const label = computed(() => {
    if (!props.value) return '—';

    return new Date(props.value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
});
</script>

<template>
    <time v-if="value" :datetime="value" class="whitespace-nowrap text-sm">{{ label }}</time>
    <span v-else class="text-gray-400">—</span>
</template>
