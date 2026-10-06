<script setup>
import { Head, Link, router } from '@statamic/cms/inertia';
import { Badge, Button, Card, Header, Listing } from '@statamic/cms/ui';
import { computed, ref } from 'vue';
import ReportProgress from '../components/ReportProgress.vue';

const props = defineProps({
    report: { type: Object, required: true },
    counts: { type: Object, required: true },
    rules: { type: Array, required: true },
    listingUrl: { type: String, required: true },
    listUrl: { type: String, required: true },
});

const rule = ref(null);
const url = computed(() => (rule.value ? `${props.listingUrl}?rule=${rule.value}` : props.listingUrl));
const ruleLabel = computed(() => props.rules.find((item) => item.handle === rule.value)?.label);
// As When shows it, but as text, so a translation can put it anywhere in the sentence.
const finished = computed(() =>
    props.report.finished_at ? new Date(props.report.finished_at).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '—',
);
</script>

<template>
    <Head :title="__('seo::reports.cp.report', { id: report.id })" />

    <Link :href="listUrl" class="mb-2 inline-block text-sm text-gray-600 dark:text-gray-400">{{ __('seo::reports.cp.back') }}</Link>
    <Header :title="__('seo::reports.cp.report', { id: report.id })" icon="charts-donut-graph" />

    <Card v-if="report.status === 'running'" class="mb-6 p-4">
        <ReportProgress :report="report" @done="router.reload()" />
    </Card>

    <template v-else>
        <Card class="mb-6 p-4 text-sm">
            <p>
                {{ __n('seo::reports.cp.summary', counts.checked + counts.noindex + counts.errors, { count: counts.checked + counts.noindex + counts.errors, finished }) }}
                <strong v-if="counts.with_issues" class="text-(--theme-color-danger)">{{ __n('seo::reports.cp.with_issues', counts.with_issues, { count: counts.with_issues }) }}</strong>
                <template v-else>{{ __('seo::reports.cp.all_good') }}</template>
                <template v-if="counts.noindex"> {{ __n('seo::reports.cp.noindex', counts.noindex, { count: counts.noindex }) }}</template>
                <template v-if="counts.errors"> {{ __n('seo::reports.cp.not_rendered', counts.errors, { count: counts.errors }) }}</template>
            </p>
        </Card>

        <Card class="mb-6 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="text-left text-gray-600 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ __('seo::reports.cp.check') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('seo::reports.cp.failing') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('seo::reports.cp.warnings') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in rules"
                        :key="item.handle"
                        class="cursor-pointer border-t border-gray-200 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                        :class="{ 'bg-blue-50 dark:bg-blue-950': rule === item.handle }"
                        @click="rule = rule === item.handle ? null : item.handle"
                    >
                        <td class="px-4 py-2">{{ item.label }}</td>
                        <td class="px-4 py-2 tabular-nums" :class="{ 'font-semibold text-(--theme-color-danger)': item.fail }">{{ item.fail }}</td>
                        <td class="px-4 py-2 tabular-nums" :class="{ 'text-amber-700': item.warn }">{{ item.warn }}</td>
                    </tr>
                </tbody>
            </table>
        </Card>

        <p v-if="counts.with_issues" class="mb-2 text-sm text-gray-600 dark:text-gray-400">
            <template v-if="rule">
                {{ __('seo::reports.cp.flagged_by', { check: ruleLabel }) }} <button class="underline" @click="rule = null">{{ __('seo::reports.cp.show_all') }}</button>
            </template>
            <template v-else>{{ __('seo::reports.cp.all_pages') }}</template>
        </p>

        <Listing v-if="counts.with_issues" :key="url" :url="url" sort-column="title" sort-direction="asc" :allow-presets="false" :allow-bulk-actions="false" preferences-prefix="seo.report">
            <template #cell-title="{ row }">
                <div class="flex items-center gap-2">
                    <a :href="row.url" target="_blank" rel="noopener" class="font-medium">{{ row.title }}</a>
                    <Badge v-if="row.noindex" size="sm" :text="__('seo::reports.cp.hidden')" />
                    <Button v-if="row.edit_url" :href="row.edit_url" :text="__('seo::reports.cp.fix')" size="sm" />
                </div>
                <div class="font-mono text-xs text-gray-500">{{ row.path }}</div>
                <ul v-if="row.issues.length" class="mt-1 space-y-0.5 text-xs">
                    <li v-for="issue in row.issues" :key="issue.label" :class="issue.status === 'fail' ? 'text-(--theme-color-danger)' : 'text-amber-700 dark:text-amber-400'">
                        <strong>{{ issue.label }}:</strong> {{ issue.message }}
                    </li>
                </ul>
            </template>
        </Listing>
    </template>
</template>
