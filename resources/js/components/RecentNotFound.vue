<script setup>
/** Lists the paths most recently missed, each with its hits, on the overview and the dashboard widget. */
defineProps({ rows: { type: Array, required: true } });
</script>

<template>
    <ul class="space-y-1 text-sm">
        <li v-for="(row, index) in rows" :key="`${index}:${row.path}`" class="flex justify-between gap-4">
            <span class="truncate font-mono text-xs">{{ row.path }}</span>
            <span class="shrink-0 tabular-nums text-gray-500 dark:text-gray-400">{{ row.hits }}×</span>
        </li>
    </ul>
</template>
