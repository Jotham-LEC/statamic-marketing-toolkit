<script setup>
defineProps({ value: { type: Number, default: null } });
</script>

<template>
    <span
        v-if="value !== null"
        class="inline-flex min-w-10 justify-center rounded px-1.5 py-0.5 text-sm font-semibold tabular-nums"
        :class="value >= 90 ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : value >= 70 ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'"
    >{{ value }}</span>
    <span v-else class="text-sm text-gray-500">—</span>
</template>
