<script setup>
import { Badge } from '@statamic/cms/ui';

defineProps({ value: { type: Number, default: null } });
</script>

<template>
    <Badge v-if="value !== null" :text="value" :color="value >= 90 ? 'green' : value >= 70 ? 'amber' : 'red'" class="tabular-nums" />
    <span v-else class="text-sm text-gray-500">—</span>
</template>
