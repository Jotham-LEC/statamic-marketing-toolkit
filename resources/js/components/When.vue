<script setup>
import { formatDate } from '../util.js';

defineProps({ value: { type: String, default: null } });
</script>

<template>
    <time v-if="value" :datetime="value" class="whitespace-nowrap text-sm">{{ formatDate(value) }}</time>
    <span v-else class="text-gray-500 dark:text-gray-400">—</span>
</template>
