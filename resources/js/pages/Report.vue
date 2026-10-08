<script setup>
import { Head, Link, router } from '@statamic/cms/inertia';
import { Badge, Button, Card, Header, Listing, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows } from '@statamic/cms/ui';
import { computed, ref } from 'vue';
import ReportProgress from '../components/ReportProgress.vue';
import Gauge from '../components/Gauge.vue';
import Score from '../components/Score.vue';
import { formatDate } from '../util.js';

const props = defineProps({
    report: { type: Object, required: true },
    counts: { type: Object, required: true },
    rules: { type: Array, required: true },
    listingUrl: { type: String, required: true },
    listUrl: { type: String, required: true },
    exportUrl: { type: String, required: true },
});

const rule = ref(null);
const url = computed(() => (rule.value ? `${props.listingUrl}?${new URLSearchParams({ rule: rule.value })}` : props.listingUrl));
const ruleLabel = computed(() => props.rules.find((item) => item.handle === rule.value)?.label);
// As When shows it, but as text, so a translation can put it anywhere in the sentence.
const finished = computed(() => (props.report.finished_at ? formatDate(props.report.finished_at) : '—'));

function toggle(handle) {
    rule.value = rule.value === handle ? null : handle;
}
</script>

<template>
    <Head :title="__('marketing-toolkit::reports.cp.report', { id: report.id })" />

    <Link :href="listUrl" class="mb-2 inline-block text-sm text-gray-600 dark:text-gray-400">{{ __('marketing-toolkit::reports.cp.back') }}</Link>
    <Header :title="__('marketing-toolkit::reports.cp.report', { id: report.id })" icon="charts-donut-graph">
        <Button v-if="report.status === 'done'" :text="__('marketing-toolkit::reports.cp.export')" :href="exportUrl" />
    </Header>

    <Card v-if="report.status === 'running'" class="mb-6 p-4">
        <ReportProgress :report="report" @done="router.reload()" />
    </Card>

    <Card v-else-if="report.status === 'failed'" class="mb-6 p-4 text-sm">
        <p class="font-semibold text-(--theme-color-danger)">{{ __('marketing-toolkit::cp.report_progress.report_failed') }}</p>
        <p v-if="report.error" class="mt-1">{{ report.error }}</p>
    </Card>

    <template v-else>
        <div class="mb-6 grid gap-4 md:grid-cols-[auto_1fr]">
            <Card class="flex flex-col items-center justify-center p-6">
                <Gauge :value="report.score" size="lg" :label="__('marketing-toolkit::reports.cp.out_of')" />
            </Card>
            <Card class="p-4 text-sm">
                <p>
                    {{ __n('marketing-toolkit::reports.cp.scored', counts.scored, { count: counts.scored, finished }) }}
                    <template v-if="counts.noindex"> {{ __n('marketing-toolkit::reports.cp.noindex', counts.noindex, { count: counts.noindex }) }}</template>
                    <template v-if="counts.errors">
                        <strong class="text-(--theme-color-danger)">{{ __n('marketing-toolkit::reports.cp.not_rendered', counts.errors, { count: counts.errors }) }}</strong>
                        {{ __('marketing-toolkit::reports.cp.scores_zero') }}
                    </template>
                </p>
                <p class="mt-2 text-gray-600 dark:text-gray-400">
                    {{ __('marketing-toolkit::reports.cp.weights') }}
                </p>
            </Card>
        </div>

        <Card class="mb-6">
            <Table class="overflow-x-auto">
                <TableColumns>
                    <TableColumn>{{ __('marketing-toolkit::reports.cp.check') }}</TableColumn>
                    <TableColumn>{{ __('marketing-toolkit::reports.cp.weight') }}</TableColumn>
                    <TableColumn>{{ __('marketing-toolkit::reports.cp.failing') }}</TableColumn>
                    <TableColumn>{{ __('marketing-toolkit::reports.cp.warnings') }}</TableColumn>
                </TableColumns>
                <TableRows>
                    <!-- The whole row filters; the button's click bubbles up to it, and is how the keyboard gets there. -->
                    <TableRow
                        v-for="item in rules"
                        :key="item.handle"
                        class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
                        :class="{ 'bg-blue-50 dark:bg-blue-950': rule === item.handle }"
                        @click="toggle(item.handle)"
                    >
                        <TableCell>
                            <button type="button" class="text-start" :aria-pressed="rule === item.handle">{{ item.label }}</button>
                        </TableCell>
                        <TableCell class="tabular-nums">{{ item.weight }}</TableCell>
                        <TableCell class="tabular-nums" :class="{ 'font-semibold text-(--theme-color-danger)': item.fail }">{{ item.fail }}</TableCell>
                        <TableCell class="tabular-nums" :class="{ 'text-amber-700 dark:text-amber-400': item.warn }">{{ item.warn }}</TableCell>
                    </TableRow>
                </TableRows>
            </Table>
        </Card>

        <p class="mb-2 text-sm text-gray-600 dark:text-gray-400">
            <template v-if="rule">
                {{ __('marketing-toolkit::reports.cp.flagged_by', { check: ruleLabel }) }} <button type="button" class="underline" @click="rule = null">{{ __('marketing-toolkit::reports.cp.show_all') }}</button>
            </template>
            <template v-else>{{ __('marketing-toolkit::reports.cp.all_pages') }}</template>
        </p>

        <Listing :key="url" :url="url" sort-column="score" sort-direction="asc" :allow-presets="false" :allow-bulk-actions="false" preferences-prefix="mt.report">
            <template #cell-score="{ row }">
                <Score :value="row.score" />
            </template>
            <template #cell-title="{ row }">
                <div class="flex items-center gap-2">
                    <a :href="row.url" target="_blank" rel="noopener" class="font-medium">{{ row.title }}</a>
                    <Badge v-if="row.noindex" size="sm" :text="__('marketing-toolkit::reports.cp.hidden')" />
                    <Button v-if="row.edit_url" :href="row.edit_url" :text="__('marketing-toolkit::reports.cp.fix')" size="sm" />
                </div>
                <div class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ row.path }}</div>
                <ul v-if="row.issues.length" class="mt-1 space-y-0.5 text-xs">
                    <li v-for="issue in row.issues" :key="issue.label" :class="issue.status === 'fail' ? 'text-(--theme-color-danger)' : 'text-amber-700 dark:text-amber-400'">
                        <strong>{{ issue.label }}:</strong> {{ issue.message }}
                    </li>
                </ul>
            </template>
            <template #cell-in_sitemap="{ row }">
                <span class="text-sm">{{ row.in_sitemap ? __('marketing-toolkit::reports.cp.yes') : __('marketing-toolkit::reports.cp.no') }}</span>
            </template>
        </Listing>
    </template>
</template>
