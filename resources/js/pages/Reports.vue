<script setup>
import { Head, Link, router } from '@statamic/cms/inertia';
import { Badge, Button, Card, Header } from '@statamic/cms/ui';
import { computed, getCurrentInstance, ref } from 'vue';
import ReportProgress from '../components/ReportProgress.vue';
import Score from '../components/Score.vue';
import When from '../components/When.vue';

const props = defineProps({
    reports: { type: Array, required: true },
    canRun: { type: Boolean, required: true },
    runUrl: { type: String, required: true },
    settingsUrl: { type: String, default: null },
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
        toast.error(error.response?.data?.message ?? 'The report could not start.');
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
    <Head title="SEO reports" />

    <Header title="SEO reports" icon="charts-donut-graph">
        <Button v-if="settingsUrl" text="Settings" :href="settingsUrl" />
        <Button v-if="canRun" text="Run report" variant="primary" :loading="starting" :disabled="!!running" @click="run" />
    </Header>

    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        A report renders every published page and checks it: titles, descriptions, headings, canonical links, the sitemap, image descriptions, links within the site,
        share images and structured data. Each page gets a score out of 100; the site’s score is their average.
    </p>

    <Card v-if="running" class="mb-6 p-4">
        <ReportProgress :key="running.id" :report="running" @done="finished" />
    </Card>

    <Card v-if="reports.length" class="overflow-hidden">
        <table class="w-full text-sm">
            <thead class="text-left text-gray-600 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Report</th>
                    <th class="px-4 py-2 font-medium">Score</th>
                    <th class="px-4 py-2 font-medium">Pages</th>
                    <th class="px-4 py-2 font-medium">Finished</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="report in reports" :key="report.id" class="border-t border-gray-200 dark:border-gray-700">
                    <td class="px-4 py-2">
                        <Link v-if="report.status === 'done'" :href="report.url" class="font-medium">SEO report #{{ report.id }}</Link>
                        <span v-else>SEO report #{{ report.id }}</span>
                    </td>
                    <td class="px-4 py-2">
                        <Score v-if="report.status === 'done'" :value="report.score" />
                        <Badge v-else-if="report.status === 'running'" text="Running" />
                        <Badge v-else color="red" text="Failed" :title="report.error" />
                    </td>
                    <td class="px-4 py-2 tabular-nums">{{ report.pages_total }}</td>
                    <td class="px-4 py-2"><When :value="report.finished_at" /></td>
                </tr>
            </tbody>
        </table>
    </Card>
    <p v-else-if="!running" class="text-sm text-gray-600 dark:text-gray-400">No reports yet.{{ canRun ? ' Run the first one.' : '' }}</p>
</template>
