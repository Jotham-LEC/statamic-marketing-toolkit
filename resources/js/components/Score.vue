<script setup>
import { Badge } from '@statamic/cms/ui';
import { band } from '../shared/score.js';

const COLORS = { good: 'green', fair: 'amber', poor: 'red' };

defineProps({ value: { type: Number, default: null } });
</script>

<template>
    <Badge v-if="value !== null" :text="value" :color="COLORS[band(value)]" class="tabular-nums" />
    <span v-else class="text-sm text-gray-500 dark:text-gray-400">—</span>
</template>
