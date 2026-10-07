<script setup>
import { Head } from '@statamic/cms/inertia';
import { Header, Listing } from '@statamic/cms/ui';
import When from '../components/When.vue';

defineProps({
    listingUrl: { type: String, required: true },
    actionUrl: { type: String, required: true },
    enabled: { type: Boolean, required: true },
    maxRows: { type: Number, required: true },
});
</script>

<template>
    <Head :title="__('marketing-toolkit::cp.not_found.title')" />

    <Header :title="__('marketing-toolkit::cp.not_found.title')" icon="alert-warning-exclamation-mark" />

    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        <template v-if="enabled">
            {{ __('marketing-toolkit::cp.not_found.intro', { max: maxRows }) }}
            {{ __('marketing-toolkit::cp.not_found.create') }}
        </template>
        <span v-else v-html="__('marketing-toolkit::cp.not_found.off', { setting: '<code>marketing-toolkit.not_found.enabled</code>' })" />
    </p>

    <Listing
        :url="listingUrl"
        :action-url="actionUrl"
        :action-context="{ type: '404s' }"
        sort-column="last_seen_at"
        sort-direction="desc"
        preferences-prefix="mt.404s"
        :allow-presets="false"
    >
        <template #cell-path="{ row }">
            <span class="font-mono text-sm">{{ row.path }}</span>
        </template>
        <template #cell-last_seen_at="{ row }">
            <When :value="row.last_seen_at" />
        </template>
        <template #cell-first_seen_at="{ row }">
            <When :value="row.first_seen_at" />
        </template>
        <template #cell-referrer="{ row }">
            <a v-if="row.referrer" :href="row.referrer" target="_blank" rel="noopener noreferrer" class="block max-w-xs truncate text-sm">{{ row.referrer }}</a>
            <span v-else class="text-gray-400">—</span>
        </template>
    </Listing>
</template>
