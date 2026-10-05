<script setup>
import { Widget } from '@statamic/cms/ui';

defineProps({
    title: { type: String, default: 'SEO' },
    report: { type: Object, default: null },
    notFound: { type: Array, default: () => [] },
    url: { type: String, required: true },
});
</script>

<template>
    <Widget :title="title" icon="search-magnifying-glass" :href="url">
        <div class="grid gap-4 px-4 py-3 sm:grid-cols-2">
            <section>
                <h3 class="mb-1 text-xs font-medium uppercase text-gray-500">Latest report</h3>
                <a v-if="report" :href="report.url" class="block">
                    <span class="text-3xl font-semibold">{{ report.score }}</span><span class="text-gray-500">/100</span>
                    <span class="block text-xs text-gray-500">{{ report.pages }} pages · {{ report.created_at }}</span>
                </a>
                <p v-else class="text-sm text-gray-500">No report yet.</p>
            </section>

            <section>
                <h3 class="mb-1 text-xs font-medium uppercase text-gray-500">Recent 404s</h3>
                <ul v-if="notFound.length" class="space-y-1 text-sm">
                    <li v-for="row in notFound" :key="row.path" class="flex justify-between gap-2">
                        <span class="truncate font-mono text-xs">{{ row.path }}</span>
                        <span class="shrink-0 text-gray-500">{{ row.hits }}×</span>
                    </li>
                </ul>
                <p v-else class="text-sm text-gray-500">No missing pages recorded.</p>
            </section>
        </div>
    </Widget>
</template>
