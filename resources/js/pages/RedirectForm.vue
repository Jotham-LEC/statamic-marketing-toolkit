<script setup>
import { Head, Link } from '@statamic/cms/inertia';
import { PublishForm } from '@statamic/cms/ui';
import { computed } from 'vue';
import { formatDate } from '../util.js';

const props = defineProps({
    title: { type: String, required: true },
    blueprint: { type: Object, required: true },
    values: { type: Object, required: true },
    meta: { type: Object, required: true },
    submitUrl: { type: String, required: true },
    submitMethod: { type: String, required: true },
    listingUrl: { type: String, required: true },
    stats: { type: Object, default: null },
});

const used = computed(() => {
    const { hits, last_hit_at } = props.stats ?? {};

    return last_hit_at
        ? __n('seo::cp.redirect_form.used_last', hits, { count: hits, when: formatDate(last_hit_at) })
        : __n('seo::cp.redirect_form.used', hits, { count: hits });
});
</script>

<template>
    <Head :title="title" />

    <div class="max-w-page mx-auto">
        <Link :href="listingUrl" class="mb-2 inline-block text-sm text-gray-600 dark:text-gray-400">{{ __('seo::cp.redirect_form.back') }}</Link>

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
            {{ used }}
            <template v-if="stats.automatic"> {{ __('seo::cp.redirect_form.automatic') }}</template>
        </p>
    </div>
</template>
