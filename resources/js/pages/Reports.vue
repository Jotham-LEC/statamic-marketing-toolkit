<script setup>
import { Head, Link, router } from '@statamic/cms/inertia';
import { Badge, Button, Card, Header } from '@statamic/cms/ui';
import { computed, getCurrentInstance, ref } from 'vue';
import ReportProgress from '../components/ReportProgress.vue';
import When from '../components/When.vue';

const props = defineProps({
    reports: { type: Array, required: true },
    canRun: { type: Boolean, required: true },
    runUrl: { type: String, required: true },
});

const { $axios: axios, $toast: toast } = getCurrentInstance().appContext.config.globalProperties;
const starting = ref(false);
const started = ref(null);
const running = computed(() => started.value ?? props.reports.find((report) => report.status === 'running'));

async function run() {
    starting.value = true;

    try {
        started.value = (await axios.post(props.runUrl)).data;
    } catch (error) {
        toast.error(error.response?.data?.message ?? __('seo::reports.cp.could_not_start'));
    } finally {
        starting.value = false;
    }
}

function finished(report) {
    started.value = null;
    report.status === 'done' ? router.visit(report.url) : router.reload();
}
</script>

<template>
    <Head :title="__('seo::reports.cp.title')" />

    <Header :title="__('seo::reports.cp.title')" icon="charts-donut-graph">
        <Button v-if="canRun" :text="__('seo::reports.cp.run')" variant="primary" :loading="starting" :disabled="!!running" @click="run" />
    </Header>

    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        {{ __('seo::reports.cp.intro') }}
    </p>

    <Card v-if="running" class="mb-6 p-4">
        <ReportProgress :key="running.id" :report="running" @done="finished" />
    </Card>

    <Card v-if="reports.length" class="overflow-hidden">
        <table class="w-full text-sm">
            <thead class="text-left text-gray-600 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-2 font-medium">{{ __('seo::reports.cp.column_report') }}</th>
                    <th class="px-4 py-2 font-medium">{{ __('seo::reports.cp.issues') }}</th>
                    <th class="px-4 py-2 font-medium">{{ __('seo::reports.cp.pages') }}</th>
                    <th class="px-4 py-2 font-medium">{{ __('seo::reports.cp.finished') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="report in reports" :key="report.id" class="border-t border-gray-200 dark:border-gray-700">
                    <td class="px-4 py-2">
                        <Link v-if="report.status === 'done'" :href="report.url" class="font-medium">{{ __('seo::reports.cp.report', { id: report.id }) }}</Link>
                        <span v-else>{{ __('seo::reports.cp.report', { id: report.id }) }}</span>
                    </td>
                    <td class="px-4 py-2">
                        <span v-if="report.status === 'done'" class="tabular-nums" :class="{ 'font-semibold text-(--theme-color-danger)': report.with_issues }">{{ report.with_issues ?? '—' }}</span>
                        <Badge v-else-if="report.status === 'running'" :text="__('seo::reports.cp.running')" />
                        <Badge v-else color="red" :text="__('seo::reports.cp.failed')" :title="report.error" />
                    </td>
                    <td class="px-4 py-2 tabular-nums">{{ report.pages_total }}</td>
                    <td class="px-4 py-2"><When :value="report.finished_at" /></td>
                </tr>
            </tbody>
        </table>
    </Card>
    <p v-else-if="!running" class="text-sm text-gray-600 dark:text-gray-400">{{ __('seo::reports.cp.none') }}<template v-if="canRun"> {{ __('seo::reports.cp.run_first') }}</template></p>
</template>
