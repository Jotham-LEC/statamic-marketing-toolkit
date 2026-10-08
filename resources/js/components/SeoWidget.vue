<script setup>
import { Widget } from '@statamic/cms/ui';
import Gauge from './Gauge.vue';
import RecentNotFound from './RecentNotFound.vue';
import When from './When.vue';

defineProps({
    title: { type: String, required: true },
    report: { type: Object, default: null },
    notFound: { type: Array, default: () => [] },
    url: { type: String, required: true },
    notFoundUrl: { type: String, required: true },
});
</script>

<template>
    <Widget :title="title" icon="search-magnifying-glass" :href="url">
        <div class="grid gap-4 px-4 py-3 sm:grid-cols-2">
            <section>
                <h3 class="mb-1 text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('marketing-toolkit::cp.widget.latest_report') }}</h3>
                <a v-if="report" :href="report.url" class="block">
                    <Gauge :value="report.score" size="sm" />
                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __n('marketing-toolkit::cp.widget.pages', report.pages, { count: report.pages }) }} · <When :value="report.created_at" /></span>
                </a>
                <p v-else class="text-sm text-gray-500 dark:text-gray-400">{{ __('marketing-toolkit::cp.widget.no_report') }}</p>
            </section>

            <section>
                <h3 class="mb-1 text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                    <a :href="notFoundUrl">{{ __('marketing-toolkit::cp.widget.recent_404s') }}</a>
                </h3>
                <RecentNotFound v-if="notFound.length" :rows="notFound" />
                <p v-else class="text-sm text-gray-500 dark:text-gray-400">{{ __('marketing-toolkit::cp.widget.none') }}</p>
            </section>
        </div>
    </Widget>
</template>
