<script setup>
import { Head, Link } from '@statamic/cms/inertia';
import { PublishForm } from '@statamic/cms/ui';
import When from '../components/When.vue';

defineProps({
    title: { type: String, required: true },
    blueprint: { type: Object, required: true },
    values: { type: Object, required: true },
    meta: { type: Object, required: true },
    submitUrl: { type: String, required: true },
    submitMethod: { type: String, required: true },
    listingUrl: { type: String, required: true },
    stats: { type: Object, default: null },
});
</script>

<template>
    <Head :title="title" />

    <div class="max-w-page mx-auto">
        <Link :href="listingUrl" class="mb-2 inline-block text-sm text-gray-600 dark:text-gray-400">← Redirects</Link>

        <PublishForm
            :title="title"
            icon="moved"
            :blueprint="blueprint"
            :initial-values="values"
            :initial-meta="meta"
            :submit-url="submitUrl"
            :submit-method="submitMethod"
        />

        <p v-if="stats" class="mt-4 text-sm text-gray-600 dark:text-gray-400">
            Used {{ stats.hits }} {{ stats.hits === 1 ? 'time' : 'times' }}<template v-if="stats.last_hit_at">, last <When :value="stats.last_hit_at" /></template>.
            <template v-if="stats.automatic"> Added automatically when the content moved; saving here makes it a manual one.</template>
        </p>
    </div>
</template>
